<?php
/**
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2012-2021 Jean-Sebastien Morisset (https://wpsso.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {

	die( 'These aren\'t the droids you\'re looking for.' );
}

/**
 * This class may be extended by some add-ons.
 *
 * Do not test and die for WPSSO_PLUGINDIR since this would crash the add-ons if WPSSO Core is deactivated.
 */
if ( ! class_exists( 'WpssoWpMeta' ) ) {

	$dir_name = dirname( __FILE__ );

	if ( file_exists( $dir_name . '/abstracts/wp-meta.php' ) ) {
	
		require_once $dir_name . '/abstracts/wp-meta.php';

	} else wpdie( 'WpssoWpMeta class not found.' );
}

if ( ! class_exists( 'WpssoPost' ) ) {

	class WpssoPost extends WpssoWpMeta {

		private static $saved_shortlink_url = null;	// Used by get_canonical_shortlink() and maybe_restore_shortlink().
		private static $cache_shortlinks    = array();	// Used by get_canonical_shortlink() and maybe_restore_shortlink().

		public function __construct( &$plugin ) {

			$this->p =& $plugin;

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			/**
			 * Maybe enable excerpts for pages.
			 */
			if ( ! empty( $this->p->options[ 'plugin_page_excerpt' ] ) ) {

				add_post_type_support( 'page', array( 'excerpt' ) );
			}

			/**
			 * Maybe register tags for pages.
			 */
			if ( $page_tag_taxonomy = SucomUtil::get_const( 'WPSSO_PAGE_TAG_TAXONOMY' ) ) {

				if ( ! empty( $this->p->options[ 'plugin_page_tags' ] ) ) {

					if ( ! taxonomy_exists( $page_tag_taxonomy ) ) {

						WpssoRegister::register_taxonomy_page_tag();
					}

				} else {

					if ( taxonomy_exists( $page_tag_taxonomy ) ) {

						unregister_taxonomy( $page_tag_taxonomy );
					}
				}
			}

			/**
			 * This hook is fired once WP, all plugins, and the theme are fully loaded and instantiated.
			 */
			add_action( 'wp_loaded', array( $this, 'add_wp_hooks' ) );
		}

		/**
		 * Add WordPress action and filters hooks.
		 */
		public function add_wp_hooks() {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			$is_admin   = is_admin();	// Only check once.
			$doing_ajax = SucomUtilWP::doing_ajax();

			if ( $is_admin ) {

				$metabox_id = $this->p->cf[ 'meta' ][ 'id' ];

				add_action( 'wp_ajax_wpsso_get_metabox_postbox_id_' . $metabox_id . '_inside', array( $this, 'ajax_get_metabox_document_meta' ) );

				add_action( 'wp_ajax_wpsso_get_validate_submenu', array( $this, 'ajax_get_validate_submenu' ) );

				if ( ! empty( $_GET ) || basename( $_SERVER[ 'PHP_SELF' ] ) === 'post-new.php' ) {

					/**
					 * load_meta_page() priorities: 100 post, 200 user, 300 term.
					 *
					 * Sets the parent::$head_tags and parent::$head_info class properties.
					 */
					add_action( 'current_screen', array( $this, 'load_meta_page' ), 100, 1 );

					/**
					 * The 'add_meta_boxes' action fires after all built-in meta boxes have been added.
					 */
					add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 10, 2 );
				}

				/**
				 * The 'save_post' action is run after other post type specific actions, so we can use it to save
				 * post meta for any post type.
				 */
				add_action( 'save_post', array( $this, 'save_options' ), WPSSO_META_SAVE_PRIORITY );	// Default is -100.

				add_action( 'wp_after_insert_post', array( $this, 'after_insert_post' ), 10, 4 );

				/**
				 * Don't hook the 'clean_post_cache' action since 'save_post' is run after 'clean_post_cache' and
				 * our custom post meta has not been saved yet.
				 */
				add_action( 'save_post', array( $this, 'clear_cache' ), WPSSO_META_CACHE_PRIORITY );	// Default is -10.

				/**
				 * The wp_insert_post() function returns after running the 'edit_attachment' action, so the
				 * 'save_post' action is never run for attachments.
				 */
				add_action( 'edit_attachment', array( $this, 'save_options' ), WPSSO_META_SAVE_PRIORITY );	// Default is -100.
				add_action( 'edit_attachment', array( $this, 'clear_cache' ), WPSSO_META_CACHE_PRIORITY );	// Default is -10.
			}

			/**
			 * Add the columns when doing AJAX as well to allow Quick Edit to add the required columns.
			 */
			if ( $is_admin || $doing_ajax ) {

				/**
				 * Add edit table columns.
				 */
				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'adding column filters for posts' );
				}

				add_filter( 'manage_pages_columns', array( $this, 'add_page_column_headings' ), WPSSO_ADD_COLUMN_PRIORITY, 1 );
				add_filter( 'manage_posts_columns', array( $this, 'add_post_column_headings' ), WPSSO_ADD_COLUMN_PRIORITY, 2 );
				add_filter( 'manage_media_columns', array( $this, 'add_media_column_headings' ), WPSSO_ADD_COLUMN_PRIORITY, 1 );

				add_action( 'manage_pages_custom_column', array( $this, 'show_column_content' ), 10, 2 );
				add_action( 'manage_posts_custom_column', array( $this, 'show_column_content' ), 10, 2 );
				add_action( 'manage_media_custom_column', array( $this, 'show_column_content' ), 10, 2 );

				/**
				 * The 'parse_query' action is hooked once in the WpssoPost class to set the column orderby for
				 * post, term, and user edit tables.
				 */
				add_action( 'parse_query', array( $this, 'set_column_orderby' ), 10, 1 );
			}

			if ( ! empty( $this->p->options[ 'plugin_shortener' ] ) && $this->p->options[ 'plugin_shortener' ] !== 'none' ) {

				if ( ! empty( $this->p->options[ 'plugin_wp_shortlink' ] ) ) {	// Use Short URL for WP Shortlink.

					if ( $this->p->debug->enabled ) {

						$this->p->debug->log( 'adding pre_get_shortlink filters to shorten the url' );
					}

					$min_int = SucomUtil::get_min_int();
					$max_int = SucomUtil::get_max_int();

					add_filter( 'pre_get_shortlink', array( $this, 'get_canonical_shortlink' ), $min_int, 4 );
					add_filter( 'pre_get_shortlink', array( $this, 'maybe_restore_shortlink' ), $max_int, 4 );

					if ( function_exists( 'wpme_get_shortlink_handler' ) ) {

						if ( $this->p->debug->enabled ) {

							$this->p->debug->log( 'removing the jetpack pre_get_shortlink filter hook' );
						}

						remove_filter( 'pre_get_shortlink', 'wpme_get_shortlink_handler', 1 );
					}
				}
			}

			/**
			 * Maybe inherit a featured image from the post/page parent.
			 */
			add_filter( 'get_post_metadata', array( $this, 'get_post_metadata_thumbnail_id' ), 100, 4 );
			add_filter( 'update_post_metadata', array( $this, 'update_post_metadata_thumbnail_id' ), 100, 5 );

			/**
			 * Maybe create or update the post column content.
			 */
			add_filter( 'get_post_metadata', array( $this, 'check_sortable_meta' ), 1000, 4 );
		}

		/**
		 * Get the $mod object for a post id.
		 */
		public function get_mod( $post_id ) {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->log_args( array(
					'post_id' => $post_id,
				) );
			}

			static $local_cache = array();

			if ( isset( $local_cache[ $post_id ] ) ) {

				return $local_cache[ $post_id ];
			}

			$mod = self::get_mod_defaults();

			/**
			 * Common elements.
			 */
			$mod[ 'id' ]          = is_numeric( $post_id ) ? (int) $post_id : 0;	// Cast as integer.
			$mod[ 'name' ]        = 'post';
			$mod[ 'name_transl' ] = _x( 'post', 'module name', 'wpsso' );
			$mod[ 'obj' ]         =& $this;

			/**
			 * WpssoPost elements.
			 */
			$mod[ 'is_post' ]       = true;
			$mod[ 'is_home_page' ]  = SucomUtil::is_home_page( $post_id );						// Static front page (singular post).
			$mod[ 'is_home_posts' ] = $mod[ 'is_home_page' ] ? false : SucomUtil::is_home_posts( $post_id );	// Static posts page or blog archive page.
			$mod[ 'is_home' ]       = $mod[ 'is_home_page' ] || $mod[ 'is_home_posts' ] ? true : false;		// Home page (static or blog archive).

			if ( $mod[ 'id' ] ) {	// Just in case.

				$post_obj = get_post( $mod[ 'id' ] );	// Optimize and fetch once for WordPress functions.

				if ( is_object( $post_obj ) ) {	// Just in case.

					$mod[ 'post_slug' ]            = get_post_field( 'post_name', $post_obj );		// Post name (aka slug).
					$mod[ 'post_type' ]            = get_post_type( $post_obj );				// Post type name.
					$mod[ 'post_mime' ]            = get_post_mime_type( $post_obj );			// Post mime type (ie. image/jpg).
					$mod[ 'post_status' ]          = get_post_status( $post_obj );				// Post status name.
					$mod[ 'post_author' ]          = (int) get_post_field( 'post_author', $post_obj );	// Post author id.
					$mod[ 'post_coauthors' ]       = array();
					$mod[ 'post_time' ]            = get_post_time( 'c', $gmt = true, $post_obj );		// Returns false on failure.
					$mod[ 'post_modified_time' ]   = get_post_modified_time( 'c', $gmt = true, $post_obj );	// Returns false on failure.
					$mod[ 'is_attachment' ]        = 'attachment' === $mod[ 'post_type' ] ? true : false;	// Post type is 'attachment'.

					$post_type_obj = get_post_type_object( $mod[ 'post_type' ] );

					$mod[ 'is_post_type_archive' ] = SucomUtil::is_post_type_archive( $post_type_obj, $mod[ 'post_slug' ] );

					if ( is_object( $post_type_obj ) ) {	// Just in case.

						if ( isset( $post_type_obj->labels->singular_name ) ) {

							$mod[ 'post_type_label' ] = $post_type_obj->labels->singular_name;
						}

						if ( isset( $post_type_obj->public ) ) {

							$mod[ 'is_public' ] = $post_type_obj->public ? true : false;
						}
					}

					/**
					 * The post type might be public, but if the post itself is private, then mark the post as not public.
					 *
					 * See https://wordpress.org/support/article/post-status/#default-statuses.
					 */
					if ( 'private' === $mod[ 'post_status' ] ) {

						$mod[ 'is_public' ] = false;
					}
				}
			}

			/**
			 * Hooked by the 'coauthors' module.
			 */
			return $local_cache[ $post_id ] = apply_filters( 'wpsso_get_post_mod', $mod, $post_id );
		}

		/**
		 * Check if the post type matches a pre-defined Open Graph type.
		 *
		 * For example, a post type of 'organization' would return 'website' for the Open Graph type.
		 *
		 * Returns false or an Open Graph type string.
		 */
		public function get_post_type_og_type( $mod ) {

			if ( ! empty( $mod[ 'post_type' ] ) ) {

				if ( ! empty( $this->p->cf[ 'head' ][ 'og_type_by_post_type' ][ $mod[ 'post_type' ] ] ) ) {

					return $this->p->cf[ 'head' ][ 'og_type_by_post_type' ][ $mod[ 'post_type' ] ];
				}
			}

			return false;
		}

		/**
		 * Option handling methods:
		 *
		 *	get_defaults()
		 *	get_options()
		 *	save_options()
		 *	delete_options()
		 */
		public function get_options( $post_id, $md_key = false, $filter_opts = true, $pad_opts = false ) {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->log_args( array( 
					'post_id'     => $post_id,
					'md_key'      => $md_key,
					'filter_opts' => $filter_opts,
					'pad_opts'    => $pad_opts,
				) );
			}

			static $local_cache = array();

			/**
			 * Use $post_id and $filter_opts to create the cache ID string, but do not add $pad_opts.
			 */
			$cache_id = SucomUtil::get_assoc_salt( array( 'id' => $post_id, 'filter' => $filter_opts ) );

			/**
			 * Maybe initialize the cache.
			 */
			if ( ! isset( $local_cache[ $cache_id ] ) ) {

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'new local cache id ' . $cache_id );
				}

				$local_cache[ $cache_id ] = null;

			} elseif ( $this->md_cache_disabled ) {

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'new local cache id ' . $cache_id . '(md cache disabled)' );
				}

				$local_cache[ $cache_id ] = null;
			}

			$md_opts =& $local_cache[ $cache_id ];	// Shortcut variable name.

			if ( null === $md_opts ) {	// Cache is empty.

				$md_opts = get_post_meta( $post_id, WPSSO_META_NAME, $single = true );

				if ( ! is_array( $md_opts ) ) {	// WPSSO_META_NAME not found.

					$md_opts = array();
				}

				/**
				 * Check if options need to be upgraded and saved.
				 */
				if ( $this->is_upgrade_options_required( $md_opts ) ) {

					$md_opts = $this->upgrade_options( $md_opts, $post_id );

					update_post_meta( $post_id, WPSSO_META_NAME, $md_opts );

					if ( $this->p->debug->enabled ) {

						$this->p->debug->log( 'post_id ' . $post_id . ' settings upgraded' );
					}
				}

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log_arr( 'post_id ' . $post_id . ' meta options read', $md_opts );
				}
			}

			if ( $filter_opts ) {

				if ( empty( $md_opts[ 'options_filtered' ] ) ) {

					$md_opts[ 'options_filtered' ] = 1;	// Set before calling filters to prevent recursion.

					$mod = $this->get_mod( $post_id );

					/**
					 * The 'import_custom_fields' filter is executed before the 'wpsso_get_md_options' and
					 * 'wpsso_get_post_options' filters, so values retrieved from custom fields may get
					 * overwritten by later filters.
					 */
					if ( $this->p->debug->enabled ) {

						$this->p->debug->log( 'applying import_custom_fields filters for post id ' . $post_id . ' metadata' );
					}

					$md_opts = apply_filters( 'wpsso_import_custom_fields', $md_opts, get_post_meta( $post_id ) );

					/**
					 * Since WPSSO Core v9.5.0.
					 *
					 * Filter 'wpsso_inherit_custom_images' added in WPSSO Core v9.10.0.
					 */
					$inherit_custom = empty( $this->p->options[ 'plugin_inherit_custom' ] ) ? false : $mod[ 'is_public' ];
					$inherit_custom = apply_filters( 'wpsso_inherit_custom_images', $inherit_custom, $mod );

					if ( $inherit_custom ) {

						if ( $this->p->debug->enabled ) {

							$this->p->debug->log( 'merging parent metadata image options' );
						}

						/**
						 * Return merged custom options from the post or term parents.
						 */
						$parent_opts = $this->get_parent_md_image_opts( $mod );

						if ( ! empty( $parent_opts ) ) {

							/**
							 * Overwrite parent options with those of the child, allowing only
							 * undefined child options to be inherited from the parent.
							 */
							$md_opts = array_merge( $parent_opts, $md_opts );
						}

					} elseif ( $this->p->debug->enabled ) {

						$this->p->debug->log( 'merging parent metadata image options is disabled' );
					}

					/**
					 * Since WPSSO Core v7.1.0.
					 */
					if ( $this->p->debug->enabled ) {

						$this->p->debug->log( 'applying get_md_options filters' );
					}

					$md_opts = apply_filters( 'wpsso_get_md_options', $md_opts, $mod );

					/**
					 * Since WPSSO Core v4.31.0.
					 *
					 * Hooked by several integration modules to provide information about the current content.
					 * e-Commerce integration modules will provide information on their product (price,
					 * condition, etc.) and disable these options in the Document SSO metabox.
					 */
					if ( $this->p->debug->enabled ) {

						$this->p->debug->log( 'applying get_post_options filters for post id ' . $post_id . ' metadata' );
					}

					$md_opts = apply_filters( 'wpsso_get_post_options', $md_opts, $post_id, $mod );

					/**
					 * Since WPSSO Core v8.2.0.
					 */
					if ( $this->p->debug->enabled ) {

						$this->p->debug->log( 'applying sanitize_md_options filters' );
					}

					$md_opts = apply_filters( 'wpsso_sanitize_md_options', $md_opts, $mod );
				}
			}

			return $this->return_options( $post_id, $md_opts, $md_key, $pad_opts );
		}

		/**
		 * Use $rel = false to extend WpssoWpMeta->save_options().
		 */
		public function save_options( $post_id, $rel = false ) {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->log_args( array(
					'post_id' => $post_id,
				) );
			}

			if ( empty( $post_id ) ) {	// Just in case.

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'exiting early: post id is empty' );
				}

				return;
			}

			if ( ! $this->user_can_save( $post_id ) ) {

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'exiting early: user cannot save post id ' . $post_id );
				}

				return;
			}

			$this->md_cache_disabled = true;	// Disable local cache for get_defaults() and get_options().

			$mod = $this->get_mod( $post_id );

			/**
			 * Merge and check submitted post, term, and user metabox options.
			 */
			$opts = $this->get_submit_opts( $mod );

			$opts = apply_filters( 'wpsso_save_md_options', $opts, $mod );

			$opts = apply_filters( 'wpsso_save_post_options', $opts, $post_id, $rel, $mod );

			if ( empty( $opts ) ) {

				return delete_post_meta( $post_id, WPSSO_META_NAME );
			}

			return update_post_meta( $post_id, WPSSO_META_NAME, $opts );
		}

		/**
		 * Use $rel = false to extend WpssoWpMeta->save_options().
		 */
		public function delete_options( $post_id, $rel = false ) {

			return delete_post_meta( $post_id, WPSSO_META_NAME );
		}

		public function after_insert_post( $post_id, $post_obj, $update, $post_before ) {

			if ( null === $post_before ) {

				if ( false === $update ) {

					if ( isset( $post_obj->post_status ) && 'auto-draft' === $post_obj->post_status ) {

						if ( ! empty( $this->p->options[ 'plugin_add_to_' . $post_obj->post_type ] ) ) {

							$mod = $this->get_mod( $post_id );

							/**
							 * Notices have already been shown before creating a new post object.
							 *
							 * $read_cache is false since there shouldn't be a cache entry for a new post.
							 */
							parent::$head_tags = $this->p->head->get_head_array( $post_id, $mod, $read_cache = false );
	
							parent::$head_info = $this->p->head->extract_head_info( parent::$head_tags, $mod );
						}
					}
				}
			}
		}

		/**
		 * Get all publicly accessible post ids.
		 *
		 * These may include post ids from non-public post types and different languages.
		 *
		 * Use $extra_args = array( 'suppress_filters' => false ) to allow WPML (and others) to filter posts for the current language.
		 */
		public static function get_public_ids( array $extra_args = array() ) {

			$posts_args = array_merge( array(
				'has_password'     => false,
				'order'            => 'DESC',		// Newest first.
				'orderby'          => 'date',
				'paged'            => false,
				'post_status'      => 'publish',	// Only 'publish', not 'auto-draft', 'draft', 'future', 'inherit', 'pending', 'private', or 'trash'.
				'post_type'        => 'any',		// Return any post, page, or custom post type.
				'posts_per_page'   => -1,		// The number of posts to query for. -1 to request all posts.
				'no_found_rows'    => true,		// Skip counting total rows found - should be enabled when pagination is not needed.
				'suppress_filters' => false,		// Allow WPML to filter posts for the current language.
			), $extra_args, array( 'fields' => 'ids' ) );	// Return an array of post ids.

			return get_posts( $posts_args );
		}

		/**
		 * Get post ids for direct children of a post id.
		 *
		 * Return an array of post ids for a given $mod object.
		 *
		 * Called by WpssoWpMeta->get_posts_mods().
		 */
		public function get_posts_ids( array $mod, array $extra_args = array() ) {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			$posts_args = array_merge( array(
				'has_password'   => false,
				'order'          => 'DESC',		// Newest first.
				'orderby'        => 'date',
				'post_status'    => 'publish',		// Only 'publish', not 'auto-draft', 'draft', 'future', 'inherit', 'pending', 'private', or 'trash'.
				'post_type'      => 'any',		// Return posts, pages, or any custom post type.
				'post_parent'    => $mod[ 'id' ],
				'child_of'       => $mod[ 'id' ],	// Only include direct children.
			), $extra_args, array( 'fields' => 'ids' ) );	// Return an array of post ids.

			if ( $this->p->debug->enabled ) {

				$this->p->debug->log( 'calling get_posts() for direct children of ' . $mod[ 'name' ] . ' ID ' . $mod[ 'id' ] );
			}

			$mtime_start = microtime( $get_float = true );
			$post_ids    = get_posts( $posts_args );
			$mtime_total = microtime( $get_float = true ) - $mtime_start;
			$mtime_max   = WPSSO_GET_POSTS_MAX_TIME;

			if ( $mtime_total > $mtime_max ) {

				$func_name   = 'get_posts()';
				$error_pre   = sprintf( __( '%s warning:', 'wpsso' ), __METHOD__ );
				$rec_max_msg = sprintf( __( 'longer than recommended max of %1$.3f secs', 'wpsso' ), $mtime_max );
				$error_msg   = sprintf( __( 'Slow WordPress function detected - %1$s took %2$.3f secs to get children of post ID %3$d (%4$s).',
					'wpsso' ), '<code>' . $func_name . '</code>', $mtime_total, $mod[ 'id' ], $rec_max_msg );

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( sprintf( 'slow WordPress function detected - %1$s took %2$.3f secs to get children of post id %3$d',
						$func_name, $mtime_total, $mod[ 'id' ] ) );
				}

				if ( $this->p->notice->is_admin_pre_notices() ) {

					$this->p->notice->warn( $error_msg );
				}

				SucomUtil::safe_error_log( $error_pre . ' ' . $error_msg, $strip_html = true );
			}

			if ( $this->p->debug->enabled ) {

				$this->p->debug->log( count( $post_ids ) . ' post ids returned in ' . sprintf( '%0.3f secs', $mtime_total ) );
			}

			return $post_ids;
		}

		public function add_page_column_headings( $columns ) {

			add_filter( 'manage_edit-page_sortable_columns', array( $this, 'add_sortable_columns' ), 10, 1 );

			return $this->add_column_headings( $columns, $post_type = 'page' );
		}

		public function add_post_column_headings( $columns, $post_type ) {

			add_filter( 'manage_edit-' . $post_type . '_sortable_columns', array( $this, 'add_sortable_columns' ), 10, 1 );

			return $this->add_column_headings( $columns, $post_type );
		}

		public function add_media_column_headings( $columns ) {

			add_filter( 'manage_upload_sortable_columns', array( $this, 'add_sortable_columns' ), 10, 1 );

			return $this->add_column_headings( $columns, $post_type = 'attachment' );
		}

		public function show_column_content( $column_name, $post_id ) {

			echo $this->get_column_content( '', $column_name, $post_id );
		}

		public function get_update_meta_cache( $post_id ) {

			return SucomUtilWP::get_update_meta_cache( $post_id, $meta_type = 'post' );
		}

		/**
		 * Hooked into the current_screen action.
		 *
		 * Sets the parent::$head_tags and parent::$head_info class properties.
		 */
		public function load_meta_page( $screen = false ) {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			/**
			 * All meta modules set this property, so use it to optimize code execution.
			 */
			if ( false !== parent::$head_tags || ! isset( $screen->id ) ) {

				return;
			}

			if ( $this->p->debug->enabled ) {

				$this->p->debug->log( 'screen id = ' . $screen->id );
			}

			switch ( $screen->id ) {

				case 'upload':
				case ( 0 === strpos( $screen->id, 'edit-' ) ? true : false ):	// Posts list table.

					if ( $this->p->debug->enabled ) {

						$this->p->debug->log( 'exiting early: not a recognized post page' );
					}

					return;
			}

			/**
			 * Get the post object for sanity checks.
			 */
			$post_obj = SucomUtil::get_post_object( true );

			$post_id = empty( $post_obj->ID ) ? 0 : $post_obj->ID;

			/**
			 * Make sure we have at least a post type and status.
			 */
			if ( ! is_object( $post_obj ) ) {

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'exiting early: post_obj is not an object' );
				}

				return;

			} elseif ( empty( $post_obj->post_type ) ) {

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'exiting early: post_type is empty' );
				}

				return;

			} elseif ( empty( $post_obj->post_status ) ) {

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'exiting early: post_status is empty' );
				}

				return;
			}

			/**
			 * Define parent::$head_tags and signal to other 'current_screen' actions that this is a valid post page.
			 */
			parent::$head_tags = array();

			$mod = $this->get_mod( $post_id );

			if ( $this->p->debug->enabled ) {

				$this->p->debug->log( 'post id = ' . $post_id );
				$this->p->debug->log( 'home url = ' . get_option( 'home' ) );
				$this->p->debug->log( 'locale current = ' . SucomUtil::get_locale() );
				$this->p->debug->log( 'locale default = ' . SucomUtil::get_locale( 'default' ) );
				$this->p->debug->log( 'locale mod = ' . SucomUtil::get_locale( $mod ) );
				$this->p->debug->log( SucomUtil::pretty_array( $mod ) );
			}

			if ( 'trash' === $post_obj->post_status ) {

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'head meta skipped: post_status is trash' );
				}

			} elseif ( isset( $_REQUEST[ 'action' ] ) && 'trash' === $_REQUEST[ 'action' ] ) {

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'head meta skipped: post is being trashed' );
				}

			} elseif ( SucomUtilWP::doing_block_editor() && ( ! empty( $_REQUEST[ 'meta-box-loader' ] ) || ! empty( $_REQUEST[ 'meta_box' ] ) ) ) {

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'head meta skipped: doing block editor for meta box' );
				}

			} elseif ( ! empty( $this->p->options[ 'plugin_add_to_' . $post_obj->post_type ] ) ) {

				/**
				 * Hooked by woocommerce module to load front-end libraries and start a session.
				 */
				do_action( 'wpsso_admin_post_head', $mod );

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'setting head_meta_info static property' );
				}

				/**
				 * $read_cache is false to generate notices etc.
				 */
				parent::$head_tags = $this->p->head->get_head_array( $post_id, $mod, $read_cache = false );

				parent::$head_info = $this->p->head->extract_head_info( parent::$head_tags, $mod );

				/**
				 * Check for missing open graph image and description values.
				 */
				if ( $mod[ 'is_public' ] && 'publish' === $mod[ 'post_status' ] ) {

					$ref_url = empty( parent::$head_info[ 'og:url' ] ) ? null : parent::$head_info[ 'og:url' ];

					$ref_url = $this->p->util->maybe_set_ref( $ref_url, $mod, __( 'checking meta tags', 'wpsso' ) );

					foreach ( array( 'image', 'description' ) as $mt_suffix ) {

						if ( empty( parent::$head_info[ 'og:' . $mt_suffix ] ) ) {

							if ( $this->p->debug->enabled ) {

								$this->p->debug->log( 'og:' . $mt_suffix . ' meta tag is value empty and required' );
							}

							/**
							 * An is_admin() test is required to use the WpssoMessages class.
							 */
							if ( $this->p->notice->is_admin_pre_notices() ) {

								$notice_msg = $this->p->msgs->get( 'notice-missing-og-' . $mt_suffix );

								$notice_key = $mod[ 'name' ] . '-' . $mod[ 'id' ] . '-notice-missing-og-' . $mt_suffix;

								$this->p->notice->err( $notice_msg, null, $notice_key );
							}
						}
					}

					$this->p->util->maybe_unset_ref( $ref_url );

					/**
					 * Check duplicates only when the post is available publicly and we have a valid permalink.
					 */
					if ( current_user_can( 'manage_options' ) ) {

						$check_head = empty( $this->p->options[ 'plugin_check_head' ] ) ? false : true;

						if ( apply_filters( 'wpsso_check_post_head', $check_head, $post_id, $post_obj ) ) {

							if ( $this->p->debug->enabled ) {

								$this->p->debug->log( 'checking post head' );
							}

							$this->check_post_head( $post_id, $post_obj );
						}
					}
				}
			}

			$action_query = 'wpsso-action';

			if ( ! empty( $_GET[ $action_query ] ) ) {

				$action_name = SucomUtil::sanitize_hookname( $_GET[ $action_query ] );

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'found action query: ' . $action_name );
				}

				if ( empty( $_GET[ WPSSO_NONCE_NAME ] ) ) {	// WPSSO_NONCE_NAME is an md5() string

					if ( $this->p->debug->enabled ) {

						$this->p->debug->log( 'nonce token query field missing' );
					}

				} elseif ( ! wp_verify_nonce( $_GET[ WPSSO_NONCE_NAME ], WpssoAdmin::get_nonce_action() ) ) {

					$this->p->notice->err( sprintf( __( 'Nonce token validation failed for %1$s action "%2$s".', 'wpsso' ), 'post', $action_name ) );

				} else {

					$_SERVER[ 'REQUEST_URI' ] = remove_query_arg( array( $action_query, WPSSO_NONCE_NAME ) );

					switch ( $action_name ) {

						default:

							do_action( 'wpsso_load_meta_page_post_' . $action_name, $post_id, $post_obj );

							break;
					}
				}
			}
		}

		public function check_post_head( $post_id = true, $post_obj = false ) {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			if ( empty( $post_id ) ) {

				$post_id = true;
			}

			if ( ! is_object( $post_obj ) ) {

				$post_obj = SucomUtil::get_post_object( $post_id );

				if ( empty( $post_obj ) ) {

					if ( $this->p->debug->enabled ) {

						$this->p->debug->log( 'exiting early: unable to get the post object');
					}

					return;	// Stop here.
				}
			}

			if ( ! is_numeric( $post_id ) ) {	// Just in case the post_id is true/false.

				if ( empty( $post_obj->ID ) ) {

					if ( $this->p->debug->enabled ) {

						$this->p->debug->log( 'exiting early: post id in post object is empty');
					}

					return;	// Stop here.
				}

				$post_id = $post_obj->ID;
			}

			static $do_once = array();

			if ( isset( $do_once[ $post_id ] ) ) {

				return;	// Stop here.
			}

			$do_once[ $post_id ] = true;

			/**
			 * Only check publicly available posts.
			 */
			if ( ! isset( $post_obj->post_status ) || 'publish' !== $post_obj->post_status ) {

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'exiting early: post_status "' . $post_obj->post_status . '" is not publish' );
				}

				return;	// Stop here.
			}

			if ( empty( $post_obj->post_type ) || SucomUtilWP::is_post_type_public( $post_obj->post_type ) ) {

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'exiting early: post_type "' . $post_obj->post_type . '" not public' );
				}

				return;	// Stop here.
			}

			$exec_count = $this->p->debug->enabled ? 0 : (int) get_option( WPSSO_POST_CHECK_COUNT_NAME, $default = 0 );
			$max_count  = SucomUtil::get_const( 'WPSSO_DUPE_CHECK_HEADER_COUNT', 10 );

			if ( $exec_count >= $max_count ) {

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'exiting early: exec_count of ' . $exec_count . ' exceeds max_count of ' . $max_count );
				}

				return;	// Stop here.
			}

			if ( ini_get( 'open_basedir' ) ) {	// Cannot follow redirects.

				$check_url = $this->p->util->get_canonical_url( $post_id, $add_page = false );

			} else {

				$check_url = SucomUtilWP::wp_get_shortlink( $post_id, $context = 'post' );
			}

			$check_url_htmlenc = SucomUtil::encode_html_emoji( urldecode( $check_url ) );	// Does not double-encode.

			if ( empty( $check_url ) ) {

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'exiting early: invalid shortlink' );
				}

				return;	// Stop here.
			}

			/**
			 * Fetch the post HTML.
			 */
			$is_admin = is_admin();	// Call the function only once.

			if ( $this->p->debug->enabled ) {

				$this->p->debug->log( 'getting html for ' . $check_url );
			}

			if ( $is_admin ) {

				$this->p->notice->inf( sprintf( __( 'Checking %1$s for duplicate meta tags...', 'wpsso' ),
					'<a href="' . $check_url . '">' . $check_url_htmlenc . '</a>' ) );
			}

			/**
			 * Use the Facebook user agent to get Open Graph meta tags.
			 */
			$curl_opts = array(
				'CURLOPT_USERAGENT' => WPSSO_PHP_CURL_USERAGENT_FACEBOOK,
			);

			$this->p->cache->clear( $check_url );	// Clear the cached webpage, just in case.

			$exp_secs     = $this->p->debug->enabled ? false : null;
			$webpage_html = $this->p->cache->get( $check_url, $format = 'raw', $cache_type = 'transient', $exp_secs, $pre_ext = '', $curl_opts );
			$url_mtime    = $this->p->cache->get_url_mtime( $check_url );
			$html_size    = strlen( $webpage_html );
			$error_size   = (int) SucomUtil::get_const( 'WPSSO_DUPE_CHECK_ERROR_SIZE', 2500000 );
			$warning_time = (int) SucomUtil::get_const( 'WPSSO_DUPE_CHECK_WARNING_TIME', 2.5 );
			$timeout_time = (int) SucomUtil::get_const( 'WPSSO_DUPE_CHECK_TIMEOUT_TIME', 3.0 );

			if ( $html_size > $error_size ) {

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'size of ' . $check_url . ' is ' . $html_size . ' bytes' );
				}

				/**
				 * If debug is enabled, the webpage may be larger than normal, so skip this warning.
				 */
				if ( $is_admin && ! $this->p->debug->enabled ) {

					$notice_msg = sprintf( __( 'The webpage HTML retrieved from %1$s is %2$s bytes.', 'wpsso' ), '<a href="' . $check_url . '">' . $check_url_htmlenc . '</a>', $html_size ) . ' ';

					$notice_msg .= sprintf( __( 'This exceeds the maximum limit of %1$s bytes imposed by the Google crawler.', 'wpsso' ), $error_size ) . ' ';

					$notice_msg .= __( 'If you do not reduce the webpage HTML size, Google will refuse to crawl this webpage.', 'wpsso' );

					$this->p->notice->err( $notice_msg );
				}
			}

			if ( true === $url_mtime ) {

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'fetched ' . $check_url . ' from transient cache' );
				}

			} elseif ( false === $url_mtime ) {

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'fetched ' . $check_url . ' returned a failure' );
				}

			} else {

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'fetched ' . $check_url . ' in ' . $url_mtime . ' secs' );
				}

				if ( $is_admin && $url_mtime > $warning_time ) {

					$this->p->notice->warn(
						sprintf( __( 'Retrieving the webpage HTML for %1$s took %2$s seconds.', 'wpsso' ),
							'<a href="' . $check_url . '">' . $check_url_htmlenc . '</a>', $url_mtime ) . ' ' . 
						sprintf( __( 'This exceeds the recommended limit of %1$s seconds (crawlers often time-out after %2$s seconds).',
							'wpsso' ), $warning_time, $timeout_time ) . ' ' . 
						__( 'Please consider improving the speed of your site.', 'wpsso' ) . ' ' . 
						__( 'As an added benefit, a faster site will also improve ranking in search results.', 'wpsso' ) . ' ;-)'
					);
				}
			}

			if ( empty( $webpage_html ) ) {

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'exiting early: error retrieving content from ' . $check_url );
				}

				if ( $is_admin ) {

					$this->p->notice->err( sprintf( __( 'Error retrieving content from <a href="%1$s">%1$s</a>.', 'wpsso' ), $check_url ) );
				}

				return;	// Stop here.

			} elseif ( stripos( $webpage_html, '<html' ) === false ) {	// Webpage must have an <html> tag.

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'exiting early: <html> tag not found in ' . $check_url );
				}

				if ( $is_admin ) {

					$this->p->notice->err( sprintf( __( 'An %1$s tag was not found in <a href="%2$s">%2$s</a>.', 'wpsso' ),
						'&lt;html&gt;', $check_url ) );
				}

				return;	// Stop here

			} elseif ( ! preg_match( '/<meta[ \n]/i', $webpage_html ) ) {	// Webpage must have one or more <meta/> tags.

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'exiting early: No <meta/> HTML tags were found in ' . $check_url );
				}

				if ( $is_admin ) {

					$this->p->notice->err( sprintf( __( 'No %1$s HTML tags were found in <a href="%2$s">%2$s</a>.', 'wpsso' ),
						'&lt;meta/&gt;', $check_url ) );
				}

				return;	// Stop here.

			} elseif ( false === strpos( $webpage_html, WPSSO_DATA_ID . ' begin' ) ) {	// Webpage should include our own meta tags.

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'exiting early: ' . WPSSO_DATA_ID . ' not found in ' . $check_url );
				}

				if ( $is_admin ) {

					$short_name = $this->p->cf[ 'plugin' ][ 'wpsso' ][ 'short' ];

					$notice_msg = sprintf( __( 'The %1$s meta tags and Schema markup section was not found in <a href="%2$s">%2$s</a>.', 'wpsso' ), $short_name, $check_url ) . ' ';

					$notice_msg .= __( 'Does a caching plugin or service needs to be refreshed?', 'wpsso' );

					$this->p->notice->err( $notice_msg );
				}

				return;	// Stop here.
			}

			/**
			 * Remove the WPSSO meta tag and Schema markup section from the webpage to check for duplicate meta tags and markup.
			 */
			if ( $this->p->debug->enabled ) {

				$this->p->debug->log( 'removing the wpsso meta tag section from the webpage html' );
			}

			$mt_mark_preg = $this->p->head->get_mt_data( 'preg' );

			$html_stripped = preg_replace( $mt_mark_preg, '', $webpage_html, -1, $mark_count );

			if ( ! $mark_count ) {

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'exiting early: preg_replace() function failed to remove the meta tag section' );
				}

				if ( $is_admin ) {

					$short_name = $this->p->cf[ 'plugin' ][ 'wpsso' ][ 'short' ];

					$notice_msg = sprintf( __( 'The PHP preg_replace() function failed to remove the %1$s meta tag section - this could be an indication of a problem with PHP\'s PCRE library, or an optimization plugin or service corrupting the webpage HTML markup.', 'wpsso' ), $short_name ) . ' ';

					$notice_msg .= __( 'You may consider updating or having your hosting provider update your PHP installation and its PCRE library.', 'wpsso' );

					$this->p->notice->err( $notice_msg );
				}

				return;	// Stop here.
			}

			/**
			 * Check the stripped webpage HTML for duplicate html tags.
			 */
			if ( $this->p->debug->enabled ) {

				$this->p->debug->log( 'checking the stripped webpage html for duplicates' );
			}

			$metas = $this->p->util->get_html_head_meta( $html_stripped, $query = '/html/head/link|/html/head/meta', $libxml_errors = true );

			$check_opts = SucomUtil::preg_grep_keys( '/^add_/', $this->p->options, $invert = false, $replace = '' );

			$conflicts_msg = __( 'Conflict detected - your theme or another plugin is adding %1$s to the head section of this webpage.', 'wpsso' );

			$conflicts_found = 0;

			if ( is_array( $metas ) ) {

				if ( empty( $metas ) ) {	// No link or meta tags found.

					if ( $this->p->debug->enabled ) {

						$this->p->debug->log( 'error parsing head meta for ' . $check_url );
					}

					if ( $is_admin ) {

						$validator_url     = 'https://validator.w3.org/nu/?doc=' . urlencode( $check_url );
						$settings_page_url = $this->p->util->get_admin_url( 'general#sucom-tabset_pub-tab_pinterest' );

						$this->p->notice->err( sprintf( __( 'An error occured parsing the head meta tags from <a href="%1$s">%1$s</a> (no "link" or "meta" HTML tags were found).', 'wpsso' ), $check_url ) . ' ' . sprintf( __( 'The webpage may contain HTML syntax errors preventing PHP from successfully parsing the HTML document - please review the <a href="%1$s">W3C Markup Validator</a> results and correct any syntax errors.', 'wpsso' ), $validator_url ) );
					}

				} else {

					foreach( array(
						'link' => array( 'rel' ),
						'meta' => array( 'name', 'property', 'itemprop' ),
					) as $tag => $types ) {

						if ( isset( $metas[ $tag ] ) ) {

							foreach( $metas[ $tag ] as $meta ) {

								foreach( $types as $type ) {

									if ( isset( $meta[ $type ] ) && $meta[ $type ] !== 'generator' &&
										! empty( $check_opts[ $tag . '_' . $type . '_' . $meta[ $type ] ] ) ) {

										$conflicts_found++;

										$conflicts_tag = '<code>' . $tag . ' ' . $type . '="' . $meta[ $type ] . '"</code>';

										$this->p->notice->err( sprintf( $conflicts_msg, $conflicts_tag ) );
									}
								}
							}
						}
					}

					if ( $is_admin ) {

						$exec_count++;

						if ( $conflicts_found ) {

							$notice_key = 'duplicate-meta-tags-found';

							$notice_msg = sprintf( __( '%1$d duplicate meta tags found.', 'wpsso' ), $conflicts_found ) . ' ';

							$notice_msg .= sprintf( __( 'Check %1$d of %2$d failed (will retry)...', 'wpsso' ), $exec_count, $max_count );

							$this->p->notice->warn( $notice_msg, null, $notice_key );

						} else {

							$notice_key = 'no-duplicate-meta-tags-found';

							$notice_msg = __( 'Awesome! No duplicate meta tags found.', 'wpsso' ) . ' :-) ';

							if ( $this->p->debug->enabled ) {

								$notice_msg .= __( 'Debug option is enabled - will keep repeating duplicate check...', 'wpsso' );

							} else {

								$notice_msg .= sprintf( __( 'Check %1$d of %2$d successful...', 'wpsso' ), $exec_count, $max_count );
							}

							update_option( WPSSO_POST_CHECK_COUNT_NAME, $exec_count, $autoload = false );

							$this->p->notice->inf( $notice_msg, null, $notice_key );
						}
					}
				}
			}
		}

		/**
		 * Use $post_obj = false to extend WpssoWpMeta->add_meta_boxes().
		 */
		public function add_meta_boxes( $post_type, $post_obj = false ) {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			$post_id = empty( $post_obj->ID ) ? 0 : $post_obj->ID;

			if ( ( 'page' === $post_type && ! current_user_can( 'edit_page', $post_id ) ) || ! current_user_can( 'edit_post', $post_id ) ) {

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'exiting early: user cannot edit page/post id ' . $post_id );
				}

				return;
			}

			if ( empty( $this->p->options[ 'plugin_add_to_' . $post_type ] ) ) {

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'exiting early: cannot add metabox to post type "' . $post_type . '"' );
				}

				return;
			}

			$metabox_id      = $this->p->cf[ 'meta' ][ 'id' ];
			$metabox_title   = _x( $this->p->cf[ 'meta' ][ 'title' ], 'metabox title', 'wpsso' );
			$metabox_screen  = $post_type;
			$metabox_context = 'normal';
			$metabox_prio    = 'default';
			$callback_args   = array(	// Second argument passed to the callback function / method.
				'__block_editor_compatible_meta_box' => true,
			);

			if ( $this->p->debug->enabled ) {

				$this->p->debug->log( 'adding metabox id wpsso_' . $metabox_id . ' for screen ' . $metabox_screen );
			}

			add_meta_box( 'wpsso_' . $metabox_id, $metabox_title,
				array( $this, 'show_metabox_document_meta' ), $metabox_screen,
					$metabox_context, $metabox_prio, $callback_args );
		}

		public function ajax_get_validate_submenu() {

			$doing_ajax = SucomUtilWP::doing_ajax();

			if ( ! $doing_ajax ) {	// Just in case.

				return;
			}

			$post_obj = $this->die_or_get_ajax_post_obj();

			require_once ABSPATH . WPINC . '/class-wp-admin-bar.php';

			$admin_bar_class = apply_filters( 'wp_admin_bar_class', 'WP_Admin_Bar' );
			$ajax_admin_bar  = new $admin_bar_class;
			$parent_id       = $this->p->page->add_validate_toolbar( $ajax_admin_bar, $post_obj->ID );
			$metabox_html    = '';

			if ( empty( $parent_id ) ) {
			
				die( $metabox_html );
			}

			$nodes       = $ajax_admin_bar->get_nodes();
			$parent_node = $nodes[ $parent_id ];
			$menu_class  = 'ab-submenu' . ( empty( $parent_node->meta[ 'class' ] ) ? '' : $parent_node->meta[ 'class' ] );

			$metabox_html .= '<ul id="' . esc_attr( 'wp-admin-bar-' . $parent_node->id . '-default' ) . '"';
			$metabox_html .= $menu_class ? ' class="' . esc_attr( trim( $menu_class ) ) . '"' : '';
			$metabox_html .= '>';

			foreach ( $nodes as $key => $node ) {

				if ( $parent_id !== $node->parent ) {

					continue;
				}

				$has_link              = ! empty( $node->href );
				$is_parent             = ! empty( $node->children );
				$is_root_top_item      = 'root-default' === $node->parent;
				$is_top_secondary_item = 'top-secondary' === $node->parent;
				$tabindex              = isset( $node->meta[ 'tabindex' ] ) && is_numeric( $node->meta[ 'tabindex' ] ) ? (int) $node->meta[ 'tabindex' ] : '';
				$aria_attributes       = '' !== $tabindex ? ' tabindex="' . $tabindex . '"' : '';
				$menu_class            = empty( $node->meta[ 'class' ] ) ? '' : $node->meta[ 'class' ];
				$arrow                 = '';

				if ( ! $is_root_top_item && ! $is_top_secondary_item && $is_parent ) {

					$arrow = '<span class="wp-admin-bar-arrow" aria-hidden="true"></span>';
		                }

				$link = $has_link ?
					'<a class="ab-item"' . $aria_attributes . ' href="' . esc_url( $node->href ) . '"' :
					'<div class="ab-item ab-empty-item"' . $aria_attributes;

				$attributes = array( 'onclick', 'target', 'title', 'rel', 'lang', 'dir' );

				foreach ( $attributes as $attribute ) {

					 if ( empty( $node->meta[ $attribute ] ) ) {

						 continue;
					 }

					$link .= ' ' . $attribute . '="';
					$link .= 'onclick' === $attribute ? esc_js( $node->meta[ $attribute ] ) : esc_attr( $node->meta[ $attribute ] );
					$link .= '"';
				}

				$link .= '>' . $arrow . $node->title;
				$link .= $has_link ? '</a>' : '</div>';

				$metabox_html .= '<li id="' . esc_attr( 'wp-admin-bar-' . $node->id ) . '"';
				$metabox_html .= $menu_class ? ' class="' . esc_attr( trim( $menu_class ) ) . '"' : '';
				$metabox_html .= '>' . $link;
				$metabox_html .= empty( $node->meta[ 'html' ] ) ? '' : $node->meta[ 'html' ];
				$metabox_html .=  '</li>' . "\n";
			}

			$metabox_html .= '</ul>';

			die( $metabox_html );
		}

		public function ajax_get_metabox_document_meta() {

			$doing_ajax = SucomUtilWP::doing_ajax();

			if ( ! $doing_ajax ) {	// Just in case.

				return;
			}

			$post_obj = $this->die_or_get_ajax_post_obj();

			if ( ! empty( $this->p->options[ 'plugin_add_to_' . $post_obj->post_type ] ) ) {

				$mod = $this->get_mod( $post_obj->ID );

				/**
				 * $read_cache is false to generate notices etc.
				 */
				parent::$head_tags = $this->p->head->get_head_array( $post_obj->ID, $mod, $read_cache = false );
	
				parent::$head_info = $this->p->head->extract_head_info( parent::$head_tags, $mod );
	
				/**
				 * Check for missing open graph image and description values.
				 */
				if ( $mod[ 'is_public' ] && 'publish' === $mod[ 'post_status' ] ) {
	
					$ref_url = empty( parent::$head_info[ 'og:url' ] ) ? null : parent::$head_info[ 'og:url' ];
	
					$ref_url = $this->p->util->maybe_set_ref( $ref_url, $mod, __( 'checking meta tags', 'wpsso' ) );
	
					foreach ( array( 'image', 'description' ) as $mt_suffix ) {
	
						if ( empty( parent::$head_info[ 'og:' . $mt_suffix ] ) ) {
	
							/**
							 * An is_admin() test is required to use the WpssoMessages class.
							 */
							if ( $this->p->notice->is_admin_pre_notices() ) {
	
								$notice_msg = $this->p->msgs->get( 'notice-missing-og-' . $mt_suffix );
	
								$notice_key = $mod[ 'name' ] . '-' . $mod[ 'id' ] . '-notice-missing-og-' . $mt_suffix;
	
								$this->p->notice->err( $notice_msg, null, $notice_key );
							}
						}
					}
	
					$this->p->util->maybe_unset_ref( $ref_url );
				}
			}

			$metabox_html = $this->get_metabox_document_meta( $post_obj );

			die( $metabox_html );
		}

		private function die_or_get_ajax_post_obj() {

			$error_msg = false;

			if ( SucomUtil::get_const( 'DOING_AUTOSAVE' ) ) {

				die( -1 );
			}

			if ( ! check_ajax_referer( WPSSO_NONCE_NAME, '_ajax_nonce', $die = false ) ) {

				$error_msg = __( 'invalid ajax referer nonce value', 'wpsso' );
			}

			if ( empty( $_POST[ 'post_id' ] ) ) {

				$error_msg = __( 'ajax request missing the post_id', 'wpsso' );
			}

			if ( ! $error_msg ) {

				$post_id = $_POST[ 'post_id' ];

				$post_obj = SucomUtil::get_post_object( $post_id );

				if ( ! is_object( $post_obj ) ) {

					die( -1 );

				} elseif ( empty( $post_obj->post_type ) ) {

					die( -1 );

				} elseif ( empty( $post_obj->post_status ) ) {

					die( -1 );

				} elseif ( 'trash' === $post_obj->post_status ) {

					die( -1 );
				}
			}

			if ( $error_msg ) {

				$stack = debug_backtrace();

				$error_pre = sprintf( __( '%s error:', 'wpsso' ), $stack[ 1 ][ 'function' ] );

				SucomUtil::safe_error_log( $error_pre . ' ' . $error_msg );

				die( -1 );
			}

			return $post_obj;
		}

		public function get_metabox_document_meta( $post_obj ) {

			$metabox_id   = $this->p->cf[ 'meta' ][ 'id' ];
			$container_id = 'wpsso_metabox_' . $metabox_id . '_inside';
			$mod          = $this->get_mod( $post_obj->ID );
			$tabs         = $this->get_document_meta_tabs( $metabox_id, $mod );
			$opts         = $this->get_options( $post_obj->ID );
			$def_opts     = $this->get_defaults( $post_obj->ID );

			$this->p->admin->get_pkg_info();

			$this->form = new SucomForm( $this->p, WPSSO_META_NAME, $opts, $def_opts, $this->p->id );

			wp_nonce_field( WpssoAdmin::get_nonce_action(), WPSSO_NONCE_NAME );

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark( $metabox_id . ' table rows' );	// Start timer.
			}

			$table_rows = array();

			foreach ( $tabs as $tab_key => $title ) {

				$filter_name = 'wpsso_metabox_' . $metabox_id . '_' . $tab_key . '_rows';

				$table_rows[ $tab_key ] = (array) apply_filters( $filter_name, array(), $this->form, parent::$head_info, $mod );

				$mod_filter_name = 'wpsso_' . $mod[ 'name' ] . '_' . $tab_key . '_rows';

				$table_rows[ $tab_key ] = (array) apply_filters( $mod_filter_name, $table_rows[ $tab_key ], $this->form, parent::$head_info, $mod );
			}

			$tabbed_args = array( 'layout' => 'vertical' );

			$metabox_html = "\n" . '<div id="' . $container_id . '">';
			$metabox_html .= $this->p->util->metabox->get_tabbed( $metabox_id, $tabs, $table_rows, $tabbed_args );
			$metabox_html .= '<!-- ' . $container_id . '_footer begin -->' . "\n";
			$metabox_html .= apply_filters( $container_id . '_footer', '', $mod );
			$metabox_html .= '<!-- ' . $container_id . '_footer end -->' . "\n";
			$metabox_html .= $this->get_metabox_javascript( $container_id );
			$metabox_html .= '</div><!-- #'. $container_id . ' -->' . "\n";

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark( $metabox_id . ' table rows' );	// End timer.
			}

			return $metabox_html;
		}

		/**
		 * Uses a static cache to clear the cache only once per post id per page load.
		 *
		 * Use $rel = false to extend WpssoWpMeta->clear_cache().
		 */
		public function clear_cache( $post_id, $rel = false ) {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->log_args( array(
					'post_id' => $post_id,
				) );
			}

			static $do_once = array();

			if ( isset( $do_once[ $post_id ] ) ) {

				return;
			}

			$do_once[ $post_id ] = true;

			if ( empty( $post_id ) ) {	// Just in case.

				return;	// Stop here.
			}

			$post_status = get_post_status( $post_id );

			switch ( $post_status ) {

				case 'inherit':	// Post revision.
				case 'trash':

					if ( $this->p->debug->enabled ) {

						$this->p->debug->log( 'exiting early: cache clearing ignored for post status ' .  $post_status );
					}

					return;	// Stop here.

				case 'auto-draft':
				case 'draft':
				case 'expired':
				case 'future':
				case 'pending':
				case 'private':
				case 'publish':
				default:	// Any other post status.

					break;
			}

			$mod = $this->get_mod( $post_id );

			/**
			 * Clear the post meta.
			 */
			$col_meta_keys = parent::get_column_meta_keys();

			foreach ( $col_meta_keys as $col_key => $meta_key ) {

				delete_post_meta( $post_id, $meta_key );
			}

			/**
			 * Clear the permalink, canonical / shortlink webpage cache.
			 */
			$permalink = get_permalink( $post_id );

			$this->p->cache->clear( $permalink );

			if ( ini_get( 'open_basedir' ) ) {

				$check_url = $this->p->util->get_canonical_url( $post_id, $add_page = false );

			} else {

				$check_url = SucomUtilWP::wp_get_shortlink( $post_id, $context = 'post' );
			}

			if ( $permalink !== $check_url ) {

				$this->p->cache->clear( $check_url );
			}

			/**
			 * Clear the plugin cache.
			 */
			$this->clear_mod_cache( $mod );

			/**
			 * Clear the post terms (categories, tags, etc.) for published (aka public) posts.
			 */
			if ( 'publish' === $post_status ) {

				$post_taxonomies = get_post_taxonomies( $post_id );

				foreach ( $post_taxonomies as $tax_slug ) {

					$post_terms = wp_get_post_terms( $post_id, $tax_slug );	// Returns WP_Error if taxonomy does not exist.

					if ( is_array( $post_terms ) ) {

						foreach ( $post_terms as $term_obj ) {

							$this->p->term->clear_cache( $term_obj->term_id, $term_obj->term_taxonomy_id );
						}
					}
				}
			}

			if ( function_exists( 'w3tc_pgcache_flush_post' ) ) {	// Clear W3 Total Cache.

				w3tc_pgcache_flush_post( $post_id );
			}

			/**
			 * The WPSSO FAQ question shortcode attaches the post id to the question so the post cache can be cleared
			 * if/when a question is updated.
			 */
			$attached_ids = self::get_attached( $post_id, 'post' );

			foreach ( $attached_ids as $post_id => $bool ) {

				if ( $bool ) {

					$this->p->post->clear_cache( $post_id );
				}
			}
		}

		/**
		 * Use $rel = false to extend WpssoWpMeta->clear_cache().
		 */
		public function user_can_save( $post_id, $rel = false ) {

			$user_can_save = false;

			if ( ! $this->verify_submit_nonce() ) {

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'exiting early: verify_submit_nonce failed' );
				}

				return $user_can_save;
			}

			if ( ! $post_type = SucomUtil::get_request_value( 'post_type', 'POST' ) ) {	// Uses sanitize_text_field.

				$post_type = 'post';
			}

			switch ( $post_type ) {

				case 'page':

					$user_can_save = current_user_can( 'edit_' . $post_type, $post_id );

					break;

				default:

					$user_can_save = current_user_can( 'edit_post', $post_id );

					break;

			}

			if ( ! $user_can_save ) {

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'insufficient privileges to save settings for ' . $post_type . ' id ' . $post_id );
				}

				/**
				 * Add notice only if the admin notices have not already been shown.
				 */
				if ( $this->p->notice->is_admin_pre_notices() ) {

					$this->p->notice->err( sprintf( __( 'Insufficient privileges to save settings for %1$s ID %2$s.', 'wpsso' ), $post_type, $post_id ) );
				}
			}

			return $user_can_save;
		}

		/**
		 * Methods that return an associative array of Open Graph meta tags.
		 */
		public function get_mt_reviews( $post_id, $mt_pre = 'product', $rating_meta = 'rating', $worst_rating = 1, $best_rating = 5 ) {

			$reviews = array();

			if ( empty( $post_id ) ) {

				return $reviews;
			}

			$comments = get_comments( array(
				'post_id' => $post_id,
				'status'  => 'approve',
				'parent'  => 0,		// Parent ID of comment to retrieve children of (0 = don't get replies).
				'order'   => 'DESC',	// Newest first.
				'orderby' => 'date',
				'number'  => WPSSO_SCHEMA_REVIEWS_MAX,
			) );

			if ( is_array( $comments ) ) {

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( count( $comments ) . ' comment objects' );
				}

				foreach( $comments as $num => $comment_obj ) {

					$og_review = $this->get_mt_comment_review( $comment_obj, $mt_pre, $rating_meta, $worst_rating, $best_rating );

					if ( ! empty( $og_review ) ) {	// Just in case.

						$reviews[] = $og_review;
					}
				}
			}

			if ( $this->p->debug->enabled ) {

				$this->p->debug->log_arr( '$reviews', $reviews );
			}

			return $reviews;
		}

		/**
		 * WpssoPost class specific methods.
		 *
		 * Filters the wp shortlink for a post - returns the shortened canonical URL.
		 *
		 * The wp_shortlink_wp_head() function calls wp_get_shortlink( 0, 'query' );
		 */
		public function get_canonical_shortlink( $shortlink = false, $post_id = 0, $context = 'post', $allow_slugs = true ) {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->log_args( array(
					'shortlink'   => $shortlink,
					'post_id'     => $post_id,
					'context'     => $context,
					'allow_slugs' => $allow_slugs,
				) );
			}

			self::$saved_shortlink_url = null;	// Just in case.

			if ( isset( self::$cache_shortlinks[ $post_id ][ $context ][ $allow_slugs ] ) ) {

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'returning shortlink from static cache = ' . self::$cache_shortlinks[ $post_id ][ $context ][ $allow_slugs ] );
				}

				return self::$saved_shortlink_url = self::$cache_shortlinks[ $post_id ][ $context ][ $allow_slugs ];
			}

			/**
			 * Check to make sure we have a plugin shortener selected.
			 */
			if ( empty( $this->p->options[ 'plugin_shortener' ] ) || $this->p->options[ 'plugin_shortener' ] === 'none' ) {

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'exiting early: no shortening service defined' );
				}

				return $shortlink;	// Return original shortlink.
			}

			/**
			 * The WordPress link-template.php functions call wp_get_shortlink() with a post id of 0. Use the same
			 * WordPress code to get a real post id and create a default shortlink (if required).
			 */
			if ( 0 === $post_id ) {

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'provided post id is 0 (current post)' );
				}

				if ( 'query' === $context && is_singular() ) {	// wp_get_shortlink() uses the same logic.

					$post_id = get_queried_object_id();

					if ( $this->p->debug->enabled ) {

						$this->p->debug->log( 'setting post id ' . $post_id . ' from queried object' );
					}

				} elseif ( 'post' === $context ) {

					$post_obj = get_post();

					if ( empty( $post_obj->ID ) ) {

						if ( $this->p->debug->enabled ) {

							$this->p->debug->log( 'exiting early: post object ID is empty' );
						}

						return $shortlink;	// Return original shortlink.

					} else {

						$post_id = $post_obj->ID;

						if ( $this->p->debug->enabled ) {

							$this->p->debug->log( 'setting post id ' . $post_id . ' from post object' );
						}
					}
				}

				if ( empty( $post_id ) ) {

					if ( $this->p->debug->enabled ) {

						$this->p->debug->log( 'exiting early: unable to determine the post id' );
					}

					return $shortlink;	// Return original shortlink.
				}

				if ( empty( $shortlink ) ) {

					if ( 'page' === get_post_type( $post_id ) &&
						(int) $post_id === (int) get_option( 'page_on_front' ) &&
							'page' === get_option( 'show_on_front' ) ) {

						$shortlink = home_url( '/' );

					} else {

						$shortlink = home_url( '?p=' . $post_id );
					}
				}

			} elseif ( ! is_numeric( $post_id ) ) {

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'exiting early: post_id argument is not numeric' );
				}

				return $shortlink;	// Return original shortlink.
			}

			$mod = $this->get_mod( $post_id );

			if ( empty( $mod[ 'post_type' ] ) ) {

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'exiting early: post_type is empty' );
				}

				return $shortlink;	// Return original shortlink.

			} elseif ( empty( $mod[ 'post_status' ] ) ) {

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'exiting early: post_status is empty' );
				}

				return $shortlink;	// Return original shortlink.

			} elseif ( 'auto-draft' === $mod[ 'post_status' ] ) {

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'exiting early: post_status is auto-draft' );
				}

				return $shortlink;	// Return original shortlink.

			} elseif ( 'trash' === $mod[ 'post_status' ] ) {

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'exiting early: post_status is trash' );
				}

				return $shortlink;	// Return original shortlink.
			}

			$canonical_url = $this->p->util->get_canonical_url( $mod, $add_page = false );

			$short_url = $this->p->util->shorten_url( $canonical_url, $mod );

			if ( $short_url === $canonical_url ) {

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'exiting early: shortened URL same as canonical URL' );
				}

				return $shortlink;	// Return original shortlink.
			}

			if ( $this->p->debug->enabled ) {

				$this->p->debug->log( 'returning shortlink = ' . $short_url );
			}

			return self::$saved_shortlink_url = self::$cache_shortlinks[ $post_id ][ $context ][ $allow_slugs ] = $short_url;
		}

		public function maybe_restore_shortlink( $shortlink = false, $post_id = 0, $context = 'post', $allow_slugs = true ) {

			if ( self::$saved_shortlink_url === $shortlink ) {	// Shortlink value has not changed.

				self::$saved_shortlink_url = null;	// Just in case.

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'exiting early: shortlink value has not changed' );
				}

				return $shortlink;
			}

			self::$saved_shortlink_url = null;	// Just in case.

			if ( isset( self::$cache_shortlinks[ $post_id ][ $context ][ $allow_slugs ] ) ) {

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'restoring shortlink ' . $shortlink . ' to ' . 
						self::$cache_shortlinks[ $post_id ][ $context ][ $allow_slugs ] );
				}

				return self::$cache_shortlinks[ $post_id ][ $context ][ $allow_slugs ];
			}

			return $shortlink;
		}

		/**
		 * Maybe inherit a featured image from the post/page parent.
		 *
		 * See get_metadata_raw() in wordpress/wp-includes/meta.php:570.
		 * See metadata_exists() in wordpress/wp-includes/meta.php:683.
		 */
		public function get_post_metadata_thumbnail_id( $check = null, $post_id, $meta_key, $single ) {

			if ( '_thumbnail_id' !== $meta_key ) {

				return $check;
			}

			/**
			 * Filter 'wpsso_inherit_featured_image' added in WPSSO Core v9.10.0.
			 */
			$mod = $this->get_mod( $post_id );	// Uses a static local cache.

			$inherit_featured = empty( $this->p->options[ 'plugin_inherit_featured' ] ) ? false : $mod[ 'is_public' ];
			$inherit_featured = apply_filters( 'wpsso_inherit_featured_image', $inherit_featured, $mod );

			if ( ! $inherit_featured ) {

				return $check;
			}

			$metadata = $this->get_update_meta_cache( $post_id );

			/**
			 * If the meta key already has a value, then no need to check the parents.
			 */
			if ( ! empty( $metadata[ $meta_key ] ) ) {

				return $check;
			}

			/**
			 * Start with the parent and work our way up - return the first value found.
			 */
			foreach ( get_post_ancestors( $post_id ) as $parent_id ) {

				$metadata = $this->get_update_meta_cache( $parent_id );

				if ( ! empty( $metadata[ $meta_key ][ 0 ] ) ) {	// Parent has a meta key value.

					if ( $single ) {

						return maybe_unserialize( $metadata[ $meta_key ][ 0 ] );
					}

					return array_map( 'maybe_unserialize', $metadata[ $meta_key ] );
				}
			}

			return $check;
		}

		/**
		 * When inheriting a featured image from the post/page parent, ignore saving the same thumbnail ID.
		 *
		 * See get_metadata_raw() in wordpress/wp-includes/meta.php:570.
		 * See metadata_exists() in wordpress/wp-includes/meta.php:683.
		 */
		public function update_post_metadata_thumbnail_id( $check = null, $post_id, $meta_key, $meta_value, $prev_value ) {

			if ( '_thumbnail_id' !== $meta_key ) {

				return $check;
			}

			/**
			 * Filter 'wpsso_inherit_featured_image' added in WPSSO Core v9.10.0.
			 */
			$mod = $this->get_mod( $post_id );	// Uses a static local cache.

			$inherit_featured = empty( $this->p->options[ 'plugin_inherit_featured' ] ) ? false : $mod[ 'is_public' ];
			$inherit_featured = apply_filters( 'wpsso_inherit_featured_image', $inherit_featured, $mod );

			if ( ! $inherit_featured ) {

				return $check;
			}

			if ( '' === $prev_value ) {	// No existing previous value.

				foreach ( get_post_ancestors( $post_id ) as $parent_id ) {

					$metadata = $this->get_update_meta_cache( $parent_id );

					if ( ! empty( $metadata[ $meta_key ][ 0 ] ) ) {	// Parent has a meta key value.

						$parent_value = maybe_unserialize( $metadata[ $meta_key ][ 0 ] );

						if ( $meta_value == $parent_value ) {	// Allow integer to numeric string comparison.

							return false;	// Do not save the meta key value.
						}
					}
				}
			}

			return $check;
		}

		/**
		 * Since WPSSO Core v8.15.0.
		 *
		 * Returns a custom or default term ID, or false if a term for the $tax_slug is not found.
		 */
		public function get_primary_term_id( array $mod, $tax_slug = 'category' ) {

			$primary_term_id = false;

			if ( $mod[ 'is_post' ] ) {	// Just in case.

				static $local_cache = array();

				$post_id = $mod[ 'id' ];

				if ( isset( $local_cache[ $post_id ][ $tax_slug ] ) ) {

					return $local_cache[ $post_id ][ $tax_slug ];	// Return value from local cache.
				}

				/**
				 * The 'wpsso_primary_tax_slug' filter is hooked by the EDD and WooCommerce integration modules.
				 */
				$primary_tax_slug = apply_filters( 'wpsso_primary_tax_slug', $tax_slug, $mod );

				/**
				 * Returns null if a custom primary term ID has not been selected.
				 */
				$primary_term_id = $this->get_options( $post_id, $md_key = 'primary_term_id' );

				/**
				 * Make sure the term is not null or false, and still exists.
				 *
				 * Note that term_exists() requires an integer ID, not a string ID.
				 */
				if ( ! empty( $primary_term_id ) && term_exists( (int) $primary_term_id ) ) {	// Since WP v3.0.

					$is_custom = true;

				} else {

					$is_custom = false;

					$primary_term_id = $this->get_default_term_id( $mod, $tax_slug );
				}

				$primary_term_id = apply_filters( 'wpsso_primary_term_id', $primary_term_id, $mod, $tax_slug, $is_custom );

				$local_cache[ $post_id ][ $tax_slug ] = empty( $primary_term_id ) ? false : (int) $primary_term_id;
			}

			return $primary_term_id;
		}

		/**
		 * Since WPSSO Core v8.18.0.
		 *
		 * Returns the first taxonomy term ID, , or false if a term for the $tax_slug is not found.
		 */
		public function get_default_term_id( array $mod, $tax_slug = 'category' ) {

			$default_term_id = false;

			if ( $mod[ 'is_post' ] ) {	// Just in case.

				/**
				 * The 'wpsso_primary_tax_slug' filter is hooked by the EDD and WooCommerce integration modules.
				 */
				$primary_tax_slug = apply_filters( 'wpsso_primary_tax_slug', $tax_slug, $mod );

				$post_terms = wp_get_post_terms( $mod[ 'id' ], $primary_tax_slug, $args = array( 'number' => 1 ) );

				if ( ! empty( $post_terms ) && is_array( $post_terms ) ) {	// Have one or more terms and taxonomy exists.

					foreach ( $post_terms as $term_obj ) {

						$default_term_id = (int) $term_obj->term_id;	// Use the first term ID found.

						break;
					}
				}

				$default_term_id = apply_filters( 'wpsso_default_term_id', $default_term_id, $mod, $tax_slug );
			}

			return $default_term_id;
		}

		/**
		 * Since WPSSO Core v8.16.0.
		 *
		 * Returns an associative array of term IDs and their names or objects.
		 *
		 * If the custom primary or default term ID exists in the post terms array, it will be moved to the top.
		 */
		public function get_primary_terms( array $mod, $tax_slug = 'category', $output = 'objects' ) {

			$primary_terms = array();

			if ( $mod[ 'is_post' ] ) {	// Just in case.

				$post_id = $mod[ 'id' ];

				/**
				 * Returns a custom or default term ID, or false if a term for the $tax_slug is not found.
				 */
				$primary_term_id = $this->p->post->get_primary_term_id( $mod, $tax_slug );	// Returns false or term ID.

				if ( $primary_term_id ) {

					/**
					 * The 'wpsso_primary_tax_slug' filter is hooked by the EDD and WooCommerce integration modules.
					 */
					$primary_tax_slug = apply_filters( 'wpsso_primary_tax_slug', $tax_slug, $mod );

					$primary_term_obj = get_term_by( 'id', $primary_term_id, $primary_tax_slug, OBJECT, 'raw' );

					if ( $primary_term_obj ) {

						$post_terms = wp_get_post_terms( $post_id, $primary_tax_slug );

						if ( ! empty( $post_terms ) && is_array( $post_terms ) ) {	// Have one or more terms and taxonomy exists.

							/**
							 * If the primary or default term ID exists in the post terms array, move it to the top.
							 */
							foreach ( $post_terms as $num => $term_obj ) {

								if ( $primary_term_obj->term_id === $term_obj->term_id ) {

									unset( $post_terms[ $num ] );

									$post_terms = array_merge( array( $primary_term_obj ), $post_terms );

									break;	// No need to continue.
								}
							}

						} else {

							$post_terms = array( $primary_term_obj );
						}

						foreach ( $post_terms as $term_obj ) {

							switch ( $output ) {

								case 'ids':
								case 'term_ids':

									$primary_terms[ $term_obj->term_id ] = (int) $term_obj->term_id;

									break;

								case 'names':

									$primary_terms[ $term_obj->term_id ] = (string) $term_obj->name;

									break;

								case 'objects':

									$primary_terms[ $term_obj->term_id ] = $term_obj;

									break;
							}
						}
					}
				}
			}

			return apply_filters( 'wpsso_primary_terms', $primary_terms, $mod, $tax_slug, $output );
		}

		/**
		 * Since WPSSO Core v8.4.0.
		 */
		public static function get_meta( $post_id, $meta_key, $single = false ) {

			return get_post_meta( $post_id, $meta_key, $single );
		}

		/**
		 * Since WPSSO Core v8.4.0.
		 */
		public static function update_meta( $post_id, $meta_key, $value ) {

			return update_post_meta( $post_id, $meta_key, $value );
		}

		/**
		 * Since WPSSO Core v8.4.0.
		 */
		public static function delete_meta( $post_id, $meta_key ) {

			return delete_post_meta( $post_id, $meta_key );
		}
	}
}
