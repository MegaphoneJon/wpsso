<?php
/*
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2012-2023 Jean-Sebastien Morisset (https://wpsso.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {

	die( 'These aren\'t the droids you\'re looking for.' );
}

if ( ! defined( 'WPSSO_PLUGINDIR' ) ) {

	die( 'Do. Or do not. There is no try.' );
}

if ( ! class_exists( 'WpssoUtilCache' ) ) {

	class WpssoUtilCache {

		private $p;	// Wpsso class object.
		private $u;	// WpssoUtil class object.

		/*
		 * Instantiated by WpssoUtil->__construct().
		 */
		public function __construct( &$plugin, &$util ) {

			$this->p =& $plugin;
			$this->u =& $util;

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			add_action( 'wp_scheduled_delete', array( $this, 'clear_expired_db_transients' ) );
			add_action( 'wpsso_clear_cache', array( $this, 'clear' ), 10, 4 );	// For single scheduled task.
			add_action( 'wpsso_refresh_cache', array( $this, 'refresh' ), 10, 1 );	// For single scheduled task.

			if ( $this->is_disabled() ) {

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'plugin cache is disabled' );
				}

				$this->u->add_plugin_filters( $this, array(
					'cache_expire_head_markup' => '__return_zero',	// Used by WpssoHead->get_head_array().
					'cache_expire_gmf_xml'     => '__return_zero',	// Used by WpssoGmfXml->get().
				) );
			}
		}

		/*
		 * The WPSSO_CACHE_DISABLE constant is true or the 'plugin_cache_disable' option is checked.
		 */
		public function is_disabled() {

			if ( defined( 'WPSSO_CACHE_DISABLE' ) ) {

				$is_disabled = WPSSO_CACHE_DISABLE ? true : false;

			} else {

				$is_disabled = empty( $this->p->options[ 'plugin_cache_disable' ] ) ? false : true;
			}

			return $is_disabled;
		}

		/*
		 * Schedule the clearing of all caches.
		 */
		public function schedule_clear( $user_id = null, $clear_other = true, $clear_short = true, $refresh = true ) {

			$user_id    = $this->u->maybe_change_user_id( $user_id );	// Maybe change textdomain for user ID.
			$event_time = time() + 5;	// Add a 5 second event buffer.
			$event_hook = 'wpsso_clear_cache';
			$event_args = array( $user_id, $clear_other, $clear_short, $refresh );

			wp_schedule_single_event( $event_time, $event_hook, $event_args );
		}

		public function clear( $user_id = null, $clear_other = false, $clear_short = true, $refresh = true ) {

			static $have_cleared = null;

			if ( null !== $have_cleared ) {	// Already run once.

				return;
			}

			$have_cleared = true;						// Prevent running a second time (by an external cache, for example).
			$user_id      = $this->u->maybe_change_user_id( $user_id );	// Maybe change textdomain for user ID.
			$notice_key   = 'clear-cache-status';

			/*
			 * A transient is set and checked to limit the runtime and allow this process to be terminated early.
			 */
			$cache_md5_pre  = 'wpsso_!_';				// Protect transient from being cleared.
			$cache_exp_secs = WPSSO_CACHE_CLEAR_MAX_TIME;		// Prevent duplicate runs for max seconds.
			$cache_salt     = __CLASS__ . '::clear';		// Use a common cache salt for start / stop.
			$cache_id       = $cache_md5_pre . md5( $cache_salt );
			$cache_run_val  = 'running';
			$cache_stop_val = 'stop';

			/*
			 * Prevent concurrent execution.
			 */
			if ( false !== get_transient( $cache_id ) ) {	// Another process is already running.

				if ( $user_id ) {

					$notice_msg = __( 'Aborting task to clear the cache - another identical task is still running.', 'wpsso' );

					$this->p->notice->warn( $notice_msg, $user_id, $notice_key . '-abort' );
				}

				return;
			}

			set_transient( $cache_id, $cache_run_val, $cache_exp_secs );

			$mtime_start = microtime( $get_float = true );

			if ( $user_id ) {

				$notice_msg = sprintf( __( 'A task to clear the cache was started at %s.', 'wpsso' ), gmdate( 'c' ) );

				$this->p->notice->inf( $notice_msg, $user_id, $notice_key );
			}

			$this->stop_refresh();	// Just in case.

			if ( 0 === get_current_user_id() ) {	// User is the scheduler.

				if ( ! set_time_limit( WPSSO_CACHE_CLEAR_MAX_TIME ) ) {
				
					$time_limit = human_time_diff( 0, WPSSO_CACHE_CLEAR_MAX_TIME );

					$notice_msg = sprintf( __( 'The PHP %1$s function failed to set a maximum execution time of %s for cache clearing.', 'wpsso' ),
						'<code>set_time_limit()</code>', $time_limit );

					$this->p->notice->err( $notice_msg, $user_id );
				}
			}

			if ( defined( 'DOING_CRON' ) && DOING_CRON ) {

				/*
				 * Register image sizes and include WooCommerce front-end libs.
				 */
				do_action( 'wpsso_scheduled_task_started', $user_id );
			}

			$cleared_files      = $this->clear_cache_files();
			$cleared_ignored    = $this->clear_ignored_urls();
			$cleared_transients = $this->clear_db_transients( $clear_short, $key_prefix = 'wpsso_' );

			wp_cache_flush();	// Clear non-database transients as well.

			/*
			 * Clear all other known caches (Comet Cache, W3TC, WP Rocket, etc.).
			 */
			$cleared_other_msg = $clear_other ? $this->clear_other() : '';

			/*
			 * The 'wpsso_cache_cleared_notice' filter allows add-ons to execute refresh tasks and append a notice message.
			 */
			$notice_msg = sprintf( __( '%1$d cached files, %2$d transient cache objects, and the WordPress object cache have been cleared.',
				'wpsso' ), $cleared_files, $cleared_transients ) . ' ' . $cleared_other_msg . ' ';

			$notice_msg = apply_filters( 'wpsso_cache_cleared_notice', $notice_msg, $user_id, $clear_other, $clear_short, $refresh );

			if ( $user_id && $notice_msg ) {

				$mtime_total = microtime( $get_float = true ) - $mtime_start;

				$notice_msg .= sprintf( __( 'The total execution time for this task was %0.3f seconds.', 'wpsso' ), $mtime_total ) . ' ';

				if ( $refresh ) {

					$notice_msg .= __( 'A background task will begin shortly to refresh the post, term and user transient and metadata cache.', 'wpsso' );
				}

				$this->p->notice->inf( $notice_msg, $user_id, $notice_key );
			}

			if ( $refresh ) {

				$this->schedule_refresh( $user_id, $read_cache = true );	// Run in the next minute.
			}

			delete_transient( $cache_id );
		}

		public function clear_cache_files() {

			$count = 0;

			$cache_files = $this->get_cache_files();

			foreach ( $cache_files as $file_path ) {

				if ( @unlink( $file_path ) ) {

					if ( $this->p->debug->enabled ) {

						$this->p->debug->log( 'removed the cache file ' . $file_path );
					}

					$count++;

				} else {

					if ( $this->p->debug->enabled ) {

						$this->p->debug->log( 'error removing cache file ' . $file_path );
					}

					$error_pre = sprintf( '%s error:', __METHOD__ );

					$error_msg = sprintf( __( 'Error removing cache file %s.', 'wpsso' ), $file_path );

					$this->p->notice->err( $error_msg );

					SucomUtil::safe_error_log( $error_pre . ' ' . $error_msg );
				}
			}

			return $count++;
		}

		public function count_cache_files() {

			$cache_files = $this->get_cache_files();

			return count( $cache_files );
		}

		public function get_cache_files() {

			$cache_files = array();

			if ( ! $dh = @opendir( WPSSO_CACHE_DIR ) ) {

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'failed to open the cache folder ' . WPSSO_CACHE_DIR . ' for reading' );
				}

				$error_pre = sprintf( '%s error:', __METHOD__ );

				$error_msg = sprintf( __( 'Failed to open the cache folder %s for reading.', 'wpsso' ), WPSSO_CACHE_DIR );

				$this->p->notice->err( $error_msg );

				SucomUtil::safe_error_log( $error_pre . ' ' . $error_msg );

			} else {

				while ( $file_name = @readdir( $dh ) ) {

					$file_path = WPSSO_CACHE_DIR . $file_name;

					if ( ! preg_match( '/^(\..*|index\.php)$/', $file_name ) && is_file( $file_path ) ) {

						$cache_files[] = $file_path;

					}
				}

				closedir( $dh );
			}

			return $cache_files;
		}

		public function clear_ignored_urls() {

			return $this->p->cache->clear_ignored_urls();
		}

		public function count_ignored_urls() {

			return $this->p->cache->count_ignored_urls();
		}

		public function clear_db_transients( $clear_short = true, $key_prefix = '' ) {

			$count = 0;

			$cache_ids = $this->get_db_transients_cache_ids( $clear_short, $key_prefix );

			foreach ( $cache_ids as $cache_id ) {

				if ( delete_transient( $cache_id ) ) {

					$count++;
				}
			}

			return $count;
		}

		public function count_db_transients( $include_short = true, $key_prefix = '' ) {

			$cache_ids = $this->get_db_transients_cache_ids( $include_short, $key_prefix );

			return count( $cache_ids );
		}

		public function get_db_transients_cache_ids( $include_short = false, $key_prefix = '' ) {

			$cache_ids = array();

			$transient_keys = $this->get_db_transient_keys( $only_expired = false, $key_prefix );

			foreach ( $transient_keys as $cache_id ) {

				if ( 0 === strpos( $key_prefix, 'wpsso_' ) ) {

					/*
					 * Preserve transients that begin with "wpsso_!_".
					 */
					if ( 0 === strpos( $cache_id, 'wpsso_!_' ) ) {

						continue;
					}

					/*
					 * Maybe delete shortened URLs.
					 */
					if ( ! $include_short ) {	// If not clearing short URLs.

						if ( 0 === strpos( $cache_id, 'wpsso_s_' ) ) {	// This is a shortened URL.

							continue;	// Get the next transient.
						}
					}
				}

				if ( '' !== $key_prefix ) {	// We're only clearing a specific prefix.

					if ( 0 !== strpos( $cache_id, $key_prefix ) ) {	// The cache ID does not match that prefix.

						continue;	// Get the next transient.
					}
				}

				$cache_ids[] = $cache_id;
			}

			return $cache_ids;
		}

		public function clear_expired_db_transients() {

			$count = 0;

			$key_prefix = 'wpsso_';

			$transient_keys = $this->get_db_transient_keys( $only_expired = true, $key_prefix );

			foreach ( $transient_keys as $cache_id ) {

				if ( delete_transient( $cache_id ) ) {

					$count++;
				}
			}

			return $count;
		}

		public function get_db_transient_keys( $only_expired = false, $key_prefix = '' ) {

			global $wpdb;

			$transient_keys = array();
			$opt_row_prefix = $only_expired ? '_transient_timeout_' : '_transient_';
			$current_time   = isset( $_SERVER[ 'REQUEST_TIME' ] ) ? (int) $_SERVER[ 'REQUEST_TIME' ] : time() ;

			$db_query = 'SELECT option_name';
			$db_query .= ' FROM ' . $wpdb->options;
			$db_query .= ' WHERE option_name LIKE \'' . $opt_row_prefix . $key_prefix . '%\'';

			if ( $only_expired ) {

				$db_query .= ' AND option_value < ' . $current_time;	// Expiration time older than current time.
			}

			$db_query .= ';';	// End of query.

			$result = $wpdb->get_col( $db_query );

			/*
			 * Remove '_transient_' or '_transient_timeout_' prefix from option name.
			 */
			foreach( $result as $option_name ) {

				$transient_keys[] = str_replace( $opt_row_prefix, '', $option_name );
			}

			return $transient_keys;
		}

		public function get_db_transient_size_mb( $decimals = 2, $dec_point = '.', $thousands_sep = ',', $key_prefix = '' ) {

			global $wpdb;

			$db_query = 'SELECT CHAR_LENGTH( option_value ) / 1024 / 1024';
			$db_query .= ', CHAR_LENGTH( option_value )';
			$db_query .= ' FROM ' . $wpdb->options;
			$db_query .= ' WHERE option_name LIKE \'_transient_' . $key_prefix . '%\'';
			$db_query .= ';';	// End of query.

			$result = $wpdb->get_col( $db_query );

			return number_format( array_sum( $result ), $decimals, $dec_point, $thousands_sep );
		}

		/*
		 * Deprecated on 2021/11/09.
		 */
		public function clear_column_meta() {

			_deprecated_function( __METHOD__ . '()', '2021/11/09', $replacement = '' );	// Deprecation message.
		}

		public function clear_other() {

			$notice_msg = '';

			$cleared_msg = __( 'The cache for <strong>%s</strong> has also been cleared.', 'wpsso' ) . ' ';

			/*
			 * Autoptimize.
			 *
			 * See https://wordpress.org/plugins/autoptimize/.
			 *
			 * Note that Autoptimize is not a page caching plugin - it optimizes CSS and JavaScript.
			 */
			if ( $this->p->avail[ 'util' ][ 'autoptimize' ] ) {

				if ( method_exists( 'autoptimizeCache', 'clearall' ) ) {	// Just in case.

					autoptimizeCache::clearall();

					$notice_msg .= sprintf( $cleared_msg, 'Autoptimize' );
				}
			}

			/*
			 * Cache Enabler.
			 *
			 * See https://wordpress.org/plugins/cache-enabler/.
			 */
			if ( $this->p->avail[ 'cache' ][ 'enabler' ] ) {

				if ( method_exists( 'Cache_Enabler', 'clear_total_cache') ) {

					Cache_Enabler::clear_total_cache();

					$notice_msg .= sprintf( $cleared_msg, 'Cache Enabler' );
				}
			}

			/*
			 * Comet Cache.
			 *
			 * See https://wordpress.org/plugins/comet-cache/.
			 */
			if ( $this->p->avail[ 'cache' ][ 'comet' ] ) {

				$GLOBALS[ 'comet_cache' ]->wipe_cache();

				$notice_msg .= sprintf( $cleared_msg, 'Comet Cache' );
			}

			/*
			 * Hummingbird Cache.
			 *
			 * See https://wordpress.org/plugins/hummingbird-performance/.
			 */
			if ( $this->p->avail[ 'cache' ][ 'hummingbird' ] ) {

				if ( method_exists( '\Hummingbird\WP_Hummingbird', 'flush_cache' ) ) {

					\Hummingbird\WP_Hummingbird::flush_cache();

					$notice_msg .= sprintf( $cleared_msg, 'Hummingbird Cache' );
				}
			}

			/*
			 * LiteSpeed Cache.
			 *
			 * See https://wordpress.org/plugins/litespeed-cache/.
			 */
			if ( $this->p->avail[ 'cache' ][ 'litespeed' ] ) {

				if ( method_exists( 'LiteSpeed_Cache_API', 'purge_all' ) ) {

					LiteSpeed_Cache_API::purge_all();

					$notice_msg .= sprintf( $cleared_msg, 'LiteSpeed Cache' );
				}
			}

			/*
			 * Pagely Cache.
			 */
			if ( $this->p->avail[ 'cache' ][ 'pagely' ] ) {

				if ( method_exists( 'PagelyCachePurge', 'purgeAll' ) ) {

					PagelyCachePurge::purgeAll();

					$notice_msg .= sprintf( $cleared_msg, 'Pagely' );
				}
			}

			/*
			 * SiteGround Cache.
			 */
			if ( $this->p->avail[ 'cache' ][ 'siteground' ] ) {

				sg_cachepress_purge_cache();

				$notice_msg .= sprintf( $cleared_msg, 'Siteground Cache' );
			}

			/*
			 * W3 Total Cache (aka W3TC).
			 */
			if ( $this->p->avail[ 'cache' ][ 'w3tc' ] ) {

				w3tc_pgcache_flush();

				if ( function_exists( 'w3tc_objectcache_flush' ) ) {

					w3tc_objectcache_flush();
				}

				$notice_msg .= sprintf( $cleared_msg, 'W3 Total Cache' );
			}

			/*
			 * WP Engine Cache.
			 */
			if ( $this->p->avail[ 'cache' ][ 'wp-engine' ] ) {

				if ( method_exists( 'WpeCommon', 'purge_memcached' ) ) {

					WpeCommon::purge_memcached();
				}

				if ( method_exists( 'WpeCommon', 'purge_varnish_cache' ) ) {

					WpeCommon::purge_varnish_cache();
				}

				$notice_msg .= sprintf( $cleared_msg, 'WP Engine Cache' );
			}

			/*
			 * WP Fastest Cache.
			 *
			 * See https://wordpress.org/plugins/wp-fastest-cache/.
			 */
			if ( $this->p->avail[ 'cache' ][ 'wp-fastest' ] ) {

				wpfc_clear_all_cache( true );

				$notice_msg .= sprintf( $cleared_msg, 'WP Fastest Cache' );
			}

			/*
			 * WP Rocket Cache.
			 */
			if ( $this->p->avail[ 'cache' ][ 'wp-rocket' ] ) {

				rocket_clean_domain();

				$notice_msg .= sprintf( $cleared_msg, 'WP Rocket Cache' );
			}

			/*
			 * WP Super Cache.
			 *
			 * See https://wordpress.org/plugins/wp-super-cache/.
			 */
			if ( $this->p->avail[ 'cache' ][ 'wp-super' ] ) {

				wp_cache_clear_cache();

				$notice_msg .= sprintf( $cleared_msg, 'WP Super Cache' );
			}

			return $notice_msg;
		}

		/*
		 * Schedule the refreshing of all post, term, and user transient cache objects.
		 *
		 * $read_cache = true when called by WpssoUtilCache->clear().
		 *
		 * $read_cache = false when called by WpssoAdmin->load_setting_page() for the 'refresh_cache' action value.
		 */
		public function schedule_refresh( $user_id = null, $read_cache = false ) {

			$user_id    = $this->u->maybe_change_user_id( $user_id );	// Maybe change textdomain for user ID.
			$event_time = time() + 5;	// Add a 5 second event buffer.
			$event_hook = 'wpsso_refresh_cache';
			$event_args = array( $user_id, $read_cache );

			$this->stop_refresh();	// Just in case.

			wp_schedule_single_event( $event_time, $event_hook, $event_args );
		}

		public function stop_refresh() {

			$cache_md5_pre  = 'wpsso_!_';			// Protect transient from being cleared.
			$cache_exp_secs = WPSSO_CACHE_REFRESH_MAX_TIME;	// Prevent duplicate runs for max seconds.
			$cache_salt     = __CLASS__ . '::refresh';	// Use a common cache salt for start / stop.
			$cache_id       = $cache_md5_pre . md5( $cache_salt );
			$cache_stop_val = 'stop';

			if ( false !== get_transient( $cache_id ) ) {	// Another process is already running.

				set_transient( $cache_id, $cache_stop_val, $cache_exp_secs );	// Signal the other process to stop.
			}
		}

		/*
		 * The $user_id and $read_cache values are provided by the WpssoUtilCache->schedule_refresh() $event_args array.
		 */
		public function refresh( $user_id = null, $read_cache = false ) {

			$user_id    = $this->u->maybe_change_user_id( $user_id );	// Maybe change textdomain for user ID.
			$notice_key = 'refresh-cache-status';

			/*
			 * A transient is set and checked to limit the runtime and allow this process to be terminated early.
			 */
			$cache_md5_pre  = 'wpsso_!_';			// Protect transient from being cleared.
			$cache_exp_secs = WPSSO_CACHE_REFRESH_MAX_TIME;	// Prevent duplicate runs for max seconds.
			$cache_salt     = __CLASS__ . '::refresh';	// Use a common cache salt for start / stop.
			$cache_id       = $cache_md5_pre . md5( $cache_salt );
			$cache_run_val  = 'running';
			$cache_stop_val = 'stop';

			/*
			 * Prevent concurrent execution.
			 */
			if ( false !== get_transient( $cache_id ) ) {	// Another process is already running.

				if ( $user_id ) {

					$notice_msg = __( 'Aborting task to refresh the transient cache - another identical task is still running.', 'wpsso' );

					$this->p->notice->warn( $notice_msg, $user_id, $notice_key . '-abort' );
				}

				return;
			}

			set_transient( $cache_id, $cache_run_val, $cache_exp_secs );

			$mtime_start = microtime( $get_float = true );

			if ( $user_id ) {

				$notice_msg = sprintf( __( 'A task to refresh the transient cache was started at %s.', 'wpsso' ), gmdate( 'c' ) );

				$this->p->notice->inf( $notice_msg, $user_id, $notice_key );
			}

			if ( 0 === get_current_user_id() ) {	// User is the scheduler.

				if ( ! set_time_limit( WPSSO_CACHE_REFRESH_MAX_TIME ) ) {
				
					$time_limit = human_time_diff( 0, WPSSO_CACHE_REFRESH_MAX_TIME );

					$notice_msg = sprintf( __( 'The PHP %1$s function failed to set a maximum execution time of %s for cache refresh.', 'wpsso' ),
						'<code>set_time_limit()</code>', $time_limit );

					$this->p->notice->err( $notice_msg, $user_id );
				}
			}

			if ( defined( 'DOING_CRON' ) && DOING_CRON ) {

				/*
				 * Register image sizes and include WooCommerce front-end libs.
				 */
				do_action( 'wpsso_scheduled_task_started', $user_id );
			}

			/*
			 * Since WPSSO Core v8.0.0.
			 */
			$public_ids = WpssoPost::get_public_ids();

			$size_names = array( 'thumbnail', 'wpsso-opengraph' );

			foreach ( $public_ids as $post_id ) {

				foreach ( $size_names as $size_name ) {

					$mt_ret = $this->p->media->get_featured( $num = 1, $size_name, $post_id );
				}
			}

			unset( $public_ids );

			/*
			 * Refresh the cache for each public post, term, and user ID.
			 */
			$total_count = array(
				'post' => 0,
				'term' => 0,
				'user' => 0,
			);

			$sleep_secs = WPSSO_CACHE_REFRESH_SLEEP_TIME;

			foreach ( $total_count as $obj_name => &$count ) {

				$obj_ids = call_user_func( array( 'wpsso' . $obj_name, 'get_public_ids' ) );	// Call static method.

				foreach ( $obj_ids as $obj_id ) {

					/*
					 * Check that we are allowed to continue. Stop if cache status is not 'running'.
					 */
					if ( $cache_run_val !== get_transient( $cache_id ) ) {

						delete_transient( $cache_id );

						return;	// Stop here.
					}

					$mod = $this->p->$obj_name->get_mod( $obj_id );

					$this->refresh_mod_head_meta( $mod, $read_cache );

					usleep( $sleep_secs * 1000000 );	// Sleeps for 0.30 seconds by default.

					$count++;	// Reference to post, term, or user total count.
				}
			}

			$notice_msg = sprintf( __( 'The transient cache for %1$d posts, %2$d terms, and %3$d users has been refreshed.',
				'wpsso' ), $total_count[ 'post' ], $total_count[ 'term' ], $total_count[ 'user' ] ) . ' ';

			/*
			 * The 'wpsso_cache_refreshed_notice' filter allows add-ons to execute refresh tasks and append a notice message.
			 *
			 * See WpssoGmfFilters->filter_cache_refreshed_notice().
			 */
			$notice_msg = apply_filters( 'wpsso_cache_refreshed_notice', $notice_msg, $user_id, $read_cache );

			if ( $user_id && $notice_msg ) {

				$mtime_total = microtime( $get_float = true ) - $mtime_start;

				$notice_msg .= sprintf( __( 'The total execution time for this task was %0.3f seconds.', 'wpsso' ), $mtime_total );

				$this->p->notice->inf( $notice_msg, $user_id, $notice_key );
			}

			delete_transient( $cache_id );
		}

		public function refresh_mod_head_meta( array $mod, $read_cache = false ) {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			$use_post  = 'post' === $mod[ 'name' ] ? $mod[ 'id' ] : false;
			$head_tags = $this->p->head->get_head_array( $use_post, $mod, $read_cache );
			$head_info = $this->p->head->extract_head_info( $head_tags, $mod );

			return array( $head_tags, $head_info );
		}
	}
}
