<?php
/**
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2012-2022 Jean-Sebastien Morisset (https://wpsso.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {

	die( 'These aren\'t the droids you\'re looking for.' );
}

if ( ! defined( 'WPSSO_PLUGINDIR' ) ) {

	die( 'Do. Or do not. There is no try.' );
}

if ( ! class_exists( 'WpssoSchemaGraph' ) ) {

	require_once WPSSO_PLUGINDIR . 'lib/schema-graph.php';
}

if ( ! class_exists( 'WpssoSchemaSingle' ) ) {

	require_once WPSSO_PLUGINDIR . 'lib/schema-single.php';
}

if ( ! class_exists( 'WpssoSchema' ) ) {

	class WpssoSchema {

		private $p;		// Wpsso class object.

		private $types_cache = array();	// Schema types array cache.

		private static $units_cache = null;	// Schema unicodes array cache.

		public function __construct( &$plugin ) {

			$this->p =& $plugin;

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			$this->p->util->add_plugin_filters( $this, array( 
				'plugin_image_sizes'   => 1,
				'sanitize_md_defaults' => 2,
				'sanitize_md_options'  => 2,
			), $prio = 5 );

			add_action( 'wp_ajax_wpsso_schema_type_og_type', array( $this, 'ajax_schema_type_og_type' ) );
		}

		public function filter_plugin_image_sizes( array $sizes ) {

			$sizes[ 'schema_1x1' ] = array(		// Option prefix.
				'name'         => 'schema-1x1',
				'label_transl' => _x( 'Schema 1:1 (Google Rich Results)', 'option label', 'wpsso' ),
			);

			$sizes[ 'schema_4x3' ] = array(		// Option prefix.
				'name'         => 'schema-4x3',
				'label_transl' => _x( 'Schema 4:3 (Google Rich Results)', 'option label', 'wpsso' ),
			);

			$sizes[ 'schema_16x9' ] = array(	// Option prefix.
				'name'         => 'schema-16x9',
				'label_transl' => _x( 'Schema 16:9 (Google Rich Results)', 'option label', 'wpsso' ),
			);

			$sizes[ 'thumb' ] = array(		// Option prefix.
				'name'         => 'thumbnail',
				'label_transl' => _x( 'Schema Thumbnail', 'option label', 'wpsso' ),
			);

			return $sizes;
		}

		public function filter_sanitize_md_defaults( $md_defs, $mod ) {

			return $this->filter_sanitize_md_options( $md_defs, $mod );
		}

		public function filter_sanitize_md_options( $md_opts, $mod ) {

			if ( ! empty( $mod[ 'is_post' ] ) ) {

			 	self::check_prop_value_enumeration( $md_opts, $prop_name = 'product_condition', $enum_key = 'item_condition', $val_suffix = 'Condition' );

				self::check_prop_value_enumeration( $md_opts, $prop_name = 'product_avail', $enum_key = 'item_availability' );

				self::check_prop_value_enumeration( $md_opts, $prop_name = 'schema_event_attendance', $enum_key = 'event_attendance' );

				self::check_prop_value_enumeration( $md_opts, $prop_name = 'schema_event_status', $enum_key = 'event_status' );

				foreach ( SucomUtil::preg_grep_keys( '/^schema_(.*)_offer_avail/', $md_opts ) as $prop_name => $prop_val ) {

					self::check_prop_value_enumeration( $md_opts, $prop_name, $enum_key = 'item_availability' );
				}
			}

			return $md_opts;
		}

		/**
		 * Called by WpssoHead->get_head_array().
		 *
		 * Pass $mt_og by reference to assign values to the schema:type internal meta tags.
		 */
		public function get_array( array $mod, array &$mt_og = array() ) {	// Pass by reference is OK.

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark( 'build schema array' );	// Begin timer.
			}

			$page_type_id  = $mt_og[ 'schema:type:id' ]  = $this->get_mod_schema_type_id( $mod );		// Example: article.tech.
			$page_type_url = $mt_og[ 'schema:type:url' ] = $this->get_schema_type_url( $page_type_id );	// Example: https://schema.org/TechArticle.

			list(
				$mt_og[ 'schema:type:context' ],
				$mt_og[ 'schema:type:name' ],
			) = self::get_schema_type_url_parts( $page_type_url );		// Example: https://schema.org, TechArticle.

			$page_type_ids   = array();
			$page_type_added = array();	// Prevent duplicate schema types.

			if ( $this->p->debug->enabled ) {

				$this->p->debug->log( 'head schema type id is ' . $page_type_id . ' (' . $page_type_url . ')' );
			}

			/**
			 * Include Schema Organization or Person, and WebSite markup on the home page.
			 */
			if ( $mod[ 'is_home' ] ) {	// Home page (static or blog archive).

				switch ( $this->p->options[ 'site_pub_schema_type' ] ) {

					case 'organization':

						$site_org_type_id = $this->p->options[ 'site_org_schema_type' ];	// Organization or a sub-type of organization.

						if ( $this->p->debug->enabled ) {

							$this->p->debug->log( 'organization schema type id is ' . $site_org_type_id );
						}

						$page_type_ids[ $site_org_type_id ] = true;

						break;

					case 'person':

						$page_type_ids[ 'person' ] = true;

						break;
				}

				$page_type_ids[ 'website' ] = true;
			}

			/**
			 * Could be an organization, website, or person, so include last to reenable (if disabled by default).
			 */
			if ( ! empty( $page_type_url ) ) {

				$page_type_ids[ $page_type_id ] = true;
			}

			/**
			 * Array (
			 *	[product]      => true
			 *	[website]      => true
			 *	[organization] => true
			 *	[person]       => false
			 * )
			 *
			 * Hooked by WpssoBcFilters->filter_json_array_schema_page_type_ids() to add its 'breadcrumb.list' type id.
			 */
			$page_type_ids = apply_filters( 'wpsso_json_array_schema_page_type_ids', $page_type_ids, $mod );

			if ( $this->p->debug->enabled ) {

				$this->p->debug->log_arr( 'page_type_ids', $page_type_ids );
			}

			/**
			 * Start a new @graph array.
			 */
			WpssoSchemaGraph::reset_data();

			foreach ( $page_type_ids as $type_id => $is_enabled ) {

				if ( ! $is_enabled ) {

					if ( $this->p->debug->enabled ) {

						$this->p->debug->log( 'skipping schema type id "' . $type_id . '" (disabled)' );
					}

					continue;

				} elseif ( ! empty( $page_type_added[ $type_id ] ) ) {	// Prevent duplicate schema types.

					if ( $this->p->debug->enabled ) {

						$this->p->debug->log( 'skipping schema type id "' . $type_id . '" (previously added)' );
					}

					continue;

				} else {
					$page_type_added[ $type_id ] = true;	// Prevent adding duplicate schema types.
				}

				if ( $this->p->debug->enabled ) {

					$this->p->debug->mark( 'schema type id ' . $type_id );	// Begin timer.
				}

				if ( $type_id === $page_type_id ) {	// This is the main entity.

					$is_main = true;

				} else {

					$is_main = false;	// Default for all other types.
				}

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'schema main entity is ' . ( $is_main ? 'true' : 'false' ) . ' for ' . $type_id );
				}

				/**
				 * WpssoSchema->get_json_data() returns a two dimensional array of json data unless $single is true.
				 */
				$json_data = $this->get_json_data( $mod, $mt_og, $type_id, $is_main, $single = false );

				/**
				 * Add the json data to the @graph array.
				 */
				foreach ( $json_data as $single_graph ) {

					if ( empty( $single_graph ) || ! is_array( $single_graph ) ) {	// Just in case.

						continue;
					}

					if ( empty( $single_graph[ '@type' ] ) ) {

						$type_url = $this->get_schema_type_url( $type_id );

						$single_graph = self::get_schema_type_context( $type_url, $single_graph );

						if ( $this->p->debug->enabled ) {

							$this->p->debug->log( 'added @type property is ' . $single_graph[ '@type' ] );
						}

					} elseif ( $this->p->debug->enabled ) {

						$this->p->debug->log( 'existing @type property is ' . print_r( $single_graph[ '@type' ], true ) );
					}

					$single_graph = apply_filters( 'wpsso_json_data_graph_element', $single_graph, $mod, $mt_og, $page_type_id, $is_main );

					WpssoSchemaGraph::add_data( $single_graph );
				}

				if ( $this->p->debug->enabled ) {

					$this->p->debug->mark( 'schema type id ' . $type_id );	// End timer.
				}
			}

			/**
			 * Get the @graph json array and start a new @graph array.
			 */
			$graph_type_url = WpssoSchemaGraph::get_type_url();
			$graph_json     = WpssoSchemaGraph::get_json_reset_data();
			$filter_name    = 'wpsso_json_prop_' . SucomUtil::sanitize_hookname( $graph_type_url );
			$graph_json     = apply_filters( $filter_name, $graph_json, $mod, $mt_og );

			$schema_scripts  = array();

			if ( ! empty( $graph_json[ '@graph' ] ) ) {	// Just in case.

				$graph_json = WpssoSchemaGraph::optimize_json( $graph_json );

				$schema_scripts[][] = '<script type="application/ld+json">' . $this->p->util->json_format( $graph_json ) . '</script>' . "\n";
			}

			unset( $graph_json );

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark( 'build schema array' );	// End timer.
			}

			$schema_scripts = apply_filters( 'wpsso_schema_scripts', $schema_scripts, $mod, $mt_og );

			return $schema_scripts;
		}

		/**
		 * Get the JSON-LD data array.
		 *
		 * Returns a two dimensional array of json data unless $single is true.
		 */
		public function get_json_data( array $mod, array $mt_og, $page_type_id = false, $is_main = false, $single = false ) {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			/**
			 * To optimize performance and memory usage, the 'wpsso_init_json_filters' action is run at the start of
			 * WpssoSchema->get_json_data() when the Schema filters are needed. The Wpsso->init_json_filters() action
			 * then unhooks itself from the action, so it can only be run once.
			 */
			do_action( 'wpsso_init_json_filters' );

			if ( empty( $page_type_id ) ) {

				$page_type_id = $this->get_mod_schema_type_id( $mod );

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'page type id is ' . $page_type_id );
				}
			}

			/**
			 * Returns an array of type ids with gparents, parents, child (in that order).
			 */
			$child_family_urls = array();

			foreach ( $this->get_schema_type_child_family( $page_type_id ) as $type_id ) {

				$child_family_urls[] = $this->get_schema_type_url( $type_id );
			}

			if ( $this->p->debug->enabled ) {

				$this->p->debug->log_arr( 'child_family_urls', $child_family_urls );
			}

			$json_data = null;

			foreach ( $child_family_urls as $num => $type_url ) {

				$type_hookname      = SucomUtil::sanitize_hookname( $type_url );
				$data_filter_name   = 'wpsso_json_data_' . $type_hookname;
				$valid_filter_name  = 'wpsso_json_data_validate_' . $type_hookname;
				$method_filter_name = 'filter_json_data_' . $type_hookname;

				/**
				 * Add website, organization, and person markup to home page.
				 */
				if ( false !== has_filter( $data_filter_name ) ) {

					$json_data = apply_filters( $data_filter_name, $json_data, $mod, $mt_og, $page_type_id, $is_main );

					if ( false !== has_filter( $valid_filter_name ) ) {

						$json_data = apply_filters( $valid_filter_name, $json_data, $mod, $mt_og, $page_type_id, $is_main );
					}

				/**
				 * Home page (static or blog archive).
				 */
				} elseif ( $mod[ 'is_home' ] && method_exists( $this, $method_filter_name ) ) {

					/**
					 * $is_main is always false for methods.
					 */
					$json_data = call_user_func( array( $this, $method_filter_name ), $json_data, $mod, $mt_og, $page_type_id, false );
				}
			}

			if ( isset( $json_data[ 0 ] ) && SucomUtil::is_non_assoc( $json_data ) ) {	// Multiple json arrays returned.

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'multiple json data arrays returned' );
				}

			} else {

				self::update_data_id( $json_data, $page_type_id );

				$json_data = array( $json_data );
			}

			return $single ? reset( $json_data ) : $json_data;
		}

		public function get_json_data_home_website() {

			$mod = WpssoAbstractWpMeta::get_mod_home();

			$mt_og = array();

			/**
			 * WpssoSchema->get_json_data() returns a two dimensional array of json data unless $single is true.
			 */
			$json_data = $this->get_json_data( $mod, $mt_og, $page_type_id = 'website', $is_main = false, $single = true );

			return $json_data;
		}

		public function get_mod_script_type_application_ld_json_html( array $mod, $css_id = '' ) {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			$json_data = $this->get_mod_json_data( $mod );	// Can return false.

			if ( empty( $json_data ) ) {	// Just in case.

				return '';
			}

			WpssoSchemaGraph::clean_json( $json_data );

			if ( empty( $css_id ) ) {

				$css_id = 'wpsso-json-' . md5( serialize( $json_data ) );	// md5() input must be a string.
			}

			return '<script type="application/ld+json" id="' . $css_id . '">' . $this->p->util->json_format( $json_data ) . '</script>' . "\n";
		}

		public function get_mod_json_data( array $mod ) {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			if ( empty( $mod[ 'name' ] ) || empty( $mod[ 'id' ] ) ) {

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'exiting early: mod name or id is empty' );
				}

				return false;
			}

			if ( $this->p->debug->enabled ) {

				$this->p->debug->log( 'getting schema type for ' . $mod[ 'name' ] . ' id ' . $mod[ 'id' ] );
			}

			$page_type_id = $this->get_mod_schema_type_id( $mod );

			if ( empty( $page_type_id ) ) {	// Just in case.

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'exiting early: page type id is empty' );
				}

				return false;

			} elseif ( 'none' === $page_type_id ) {

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'exiting early: page type id is "none"' );
				}

				return false;
			}

			if ( $this->p->debug->enabled ) {

				$this->p->debug->log( 'page type id is ' . $page_type_id );
			}

			$ref_url = $this->p->util->maybe_set_ref( null, $mod, __( 'adding schema', 'wpsso' ) );

			if ( $this->p->debug->enabled ) {

				$this->p->debug->log( 'getting open graph meta tag array' );
			}

			$mt_og = $this->p->og->get_array( $mod, $size_names = 'schema' );

			if ( $this->p->debug->enabled ) {

				$this->p->debug->log( 'getting schema json-ld markup array' );
			}

			/**
			 * WpssoSchema->get_json_data() returns a two dimensional array of json data unless $single is true.
			 */
			$json_data = $this->get_json_data( $mod, $mt_og, $page_type_id, $is_main = true, $single = true );

			$this->p->util->maybe_unset_ref( $ref_url );

			return $json_data;
		}

		/**
		 * Since WPSSO Core v9.1.2.
		 *
		 * Returns the schema type id.
		 */
		public function get_mod_schema_type_id( array $mod, $use_md_opts = true ) {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			return $this->get_mod_schema_type( $mod, $get_id = true, $use_md_opts );
		}

		/**
		 * Since WPSSO Core v3.37.1.
		 *
		 * Returns the schema type id by default.
		 * 
		 * Use $get_id = false to return the schema type URL instead of the ID.
		 */
		public function get_mod_schema_type( array $mod, $get_id = true, $use_md_opts = true ) {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			static $local_cache = array();

			$cache_salt = false;

			/**
			 * Archive pages can call this method several times.
			 *
			 * Optimize and cache post/term/user schema type values.
			 */
			if ( ! empty( $mod[ 'obj' ] ) && $mod[ 'id' ] ) {

				$cache_salt = SucomUtil::get_mod_salt( $mod ) . '_get_id:' . (string) $get_id . '_opts:' . (string) $use_md_opts;

				if ( isset( $local_cache[ $cache_salt ] ) ) {

					return $local_cache[ $cache_salt ];

				}
			}

			$type_id      = null;
			$schema_types = $this->get_schema_types_array( $flatten = true );

			/**
			 * Maybe get a custom schema type id from the post, term, or user meta.
			 */
			if ( $use_md_opts ) {

				if ( ! empty( $mod[ 'obj' ] ) && $mod[ 'id' ] ) {	// Just in case.

					$type_id = $mod[ 'obj' ]->get_options( $mod[ 'id' ], 'schema_type' );	// Returns null if an index key is not found.

					if ( empty( $type_id ) || $type_id === 'none' || empty( $schema_types[ $type_id ] ) ) {	// Check for an invalid type id.

						$type_id = null;

					} elseif ( $this->p->debug->enabled ) {

						$this->p->debug->log( 'custom type id = ' . $type_id );
					}
				}
			}

			$is_custom = empty( $type_id ) ? false : true;

			if ( ! $is_custom ) {	// No custom schema type id from the post, term, or user meta.

				/**
				 * Similar module type logic can be found in the following methods:
				 *
				 * See WpssoOpenGraph->get_mod_og_type().
				 * See WpssoPage->get_description().
				 * See WpssoPage->get_the_title().
				 * See WpssoSchema->get_mod_schema_type().
				 * See WpssoUtil->get_canonical_url().
				 */
				if ( $mod[ 'is_home' ] ) {	// Home page (static or blog archive).

					if ( $mod[ 'is_home_page' ] ) {	// Static front page (singular post).

						$type_id = $this->get_schema_type_id_for( 'home_page' );

					} else {

						$type_id = $this->get_schema_type_id_for( 'home_posts' );
					}

				} elseif ( $mod[ 'is_comment' ] ) {

					if ( is_numeric( $mod[ 'comment_rating' ] ) ) {

						$type_id = $this->get_schema_type_id_for( 'comment_review' );

					} elseif ( $mod[ 'comment_parent' ] ) {

						$type_id = $this->get_schema_type_id_for( 'comment_reply' );

					} else {

						$type_id = $this->get_schema_type_id_for( 'comment' );
					}

				} elseif ( $mod[ 'is_post' ] ) {

					if ( $mod[ 'post_type' ] ) {	// Just in case.

						if ( $mod[ 'is_post_type_archive' ] ) {	// The post ID may be 0.

							$type_id = $this->get_schema_type_id_for( 'pta_' . $mod[ 'post_type' ] );

							if ( empty( $type_id ) ) {	// Just in case.

								$type_id = $this->get_schema_type_id_for( 'archive_page' );
							}

						} else {

							$type_id = $this->get_schema_type_id_for( $mod[ 'post_type' ] );

							if ( empty( $type_id ) ) {	// Just in case.

								$type_id = $this->get_schema_type_id_for( 'page' );
							}
						}

					} elseif ( $this->p->debug->enabled ) {

						$this->p->debug->log( 'no post type' );
					}

				} elseif ( $mod[ 'is_term' ] ) {

					if ( ! empty( $mod[ 'tax_slug' ] ) ) {	// Just in case.

						$type_id = $this->get_schema_type_id_for( 'tax_' . $mod[ 'tax_slug' ] );
					}

					if ( empty( $type_id ) ) {	// Just in case.

						$type_id = $this->get_schema_type_id_for( 'archive_page' );
					}

				} elseif ( $mod[ 'is_user' ] ) {

					$type_id = $this->get_schema_type_id_for( 'user_page' );

				} elseif ( $mod[ 'is_search' ] ) {

					$type_id = $this->get_schema_type_id_for( 'search_page' );

				} elseif ( $mod[ 'is_archive' ] ) {

					$type_id = $this->get_schema_type_id_for( 'archive_page' );
				}

				if ( empty( $type_id ) ) {	// Just in case.

					if ( $this->p->debug->enabled ) {

						$this->p->debug->log( 'unable to determine schema type id (using default)' );
					}

					$type_id = 'webpage';
				}
			}

			if ( $this->p->debug->enabled ) {

				$this->p->debug->log( 'schema type id before filter: ' . $type_id );
			}

			$type_id = apply_filters( 'wpsso_schema_type', $type_id, $mod, $is_custom );

			if ( $this->p->debug->enabled ) {

				$this->p->debug->log( 'schema type id after filter: ' . $type_id );
			}

			$get_value = false;

			if ( empty( $type_id ) ) {

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'returning false: schema type id is empty' );
				}

			} elseif ( 'none' === $type_id ) {

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'returning false: schema type id is disabled' );
				}

			} elseif ( ! isset( $schema_types[ $type_id ] ) ) {

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'returning false: schema type id ' . $type_id . ' is unknown' );
				}

			} elseif ( ! $get_id ) {

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'returning schema type url: ' . $schema_types[ $type_id ] );
				}

				$get_value = $schema_types[ $type_id ];

			} else {

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'returning schema type id: ' . $type_id );
				}

				$get_value = $type_id;
			}

			/**
			 * Optimize and cache post/term/user schema type values.
			 */
			if ( $cache_salt ) {

				$local_cache[ $cache_salt ] = $get_value;
			}

			return $get_value;
		}

		/**
		 * Since WPSSO Core v9.1.2.
		 *
		 * Returns the schema type URL.
		 */
		public function get_mod_schema_type_url( array $mod, $use_md_opts = true ) {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			return $this->get_mod_schema_type( $mod, $get_id = false, $use_md_opts );
		}

		public function get_schema_types_select( $schema_types = null ) {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			if ( ! is_array( $schema_types ) ) {

				$schema_types = $this->get_schema_types_array( $flatten = false );
			}

			$schema_types = SucomUtil::array_flatten( $schema_types );

			$select = array();

			foreach ( $schema_types as $type_id => $type_url ) {

				$type_url  = preg_replace( '/^.*\/\//', '', $type_url );
				$type_name = preg_replace( '/^.*\//U', '', $type_url );

				switch ( $this->p->options[ 'plugin_schema_types_select_format' ] ) {

					case 'id':

						$select[ $type_id ] = $type_id;

						break;

					case 'id_url':

						$select[ $type_id ] = $type_id . ' | ' . $type_url;

						break;

					case 'id_name':

						$select[ $type_id ] = $type_id . ' | ' . $type_name;

						break;

					case 'name_id':

						$select[ $type_id ] = $type_name . ' [' . $type_id . ']';

						break;

					default:

						$select[ $type_id ] = $type_name;

						break;
				}
			}

			if ( defined( 'SORT_STRING' ) ) {	// Just in case.

				asort( $select, SORT_STRING );

			} else {

				asort( $select );
			}

			return $select;
		}

		/**
		 * Returns a one-dimensional (flat) array of schema types by default, otherwise returns a multi-dimensional array
		 * of all schema types, including cross-references for sub-types with multiple parent types.
		 *
		 * $read_cache is false when called from the WpssoOptionsUpgrade::options() method.
		 *
		 * Uses a transient cache object and the $types_cache class property.
		 */
		public function get_schema_types_array( $flatten = true, $read_cache = true ) {

			if ( ! $read_cache ) {

				$this->types_cache[ 'filtered' ]  = null;
				$this->types_cache[ 'flattened' ] = null;
				$this->types_cache[ 'parents' ]   = null;
			}

			if ( ! isset( $this->types_cache[ 'filtered' ] ) ) {

				$cache_md5_pre  = 'wpsso_t_';
				$cache_exp_secs = $this->p->util->get_cache_exp_secs( $cache_md5_pre );

				if ( $cache_exp_secs > 0 ) {

					$cache_salt = __METHOD__;
					$cache_id   = $cache_md5_pre . md5( $cache_salt );

					if ( $read_cache ) {

						$this->types_cache = get_transient( $cache_id );	// Returns false when not found.

						if ( ! empty( $this->types_cache ) ) {

							if ( $this->p->debug->enabled ) {

								$this->p->debug->log( 'using schema types array from transient ' . $cache_id );
							}
						}
					}
				}

				if ( ! isset( $this->types_cache[ 'filtered' ] ) ) {	// Maybe from transient cache - re-check if filtered.

					if ( $this->p->debug->enabled ) {

						$this->p->debug->mark( 'create schema types array' );	// Begin timer.
					}

					/**
					 * Filtered array.
					 */
					$this->types_cache[ 'filtered' ] = (array) apply_filters( 'wpsso_schema_types', $this->p->cf[ 'head' ][ 'schema_type' ] );

					/**
					 * Flattened array (before adding cross-references).
					 */
					$this->types_cache[ 'flattened' ] = SucomUtil::array_flatten( $this->types_cache[ 'filtered' ] );

					/**
					 * Adding cross-references to filtered array.
					 */
					$this->add_schema_type_xrefs( $this->types_cache[ 'filtered' ] );

					/**
					 * Parents array.
					 */
					$this->types_cache[ 'parents' ] = SucomUtil::get_array_parents( $this->types_cache[ 'filtered' ] );

					if ( $cache_exp_secs > 0 ) {

						set_transient( $cache_id, $this->types_cache, $cache_exp_secs );

						if ( $this->p->debug->enabled ) {

							$this->p->debug->log( 'schema types array saved to transient cache for ' . $cache_exp_secs . ' seconds' );
						}
					}

					if ( $this->p->debug->enabled ) {

						$this->p->debug->mark( 'create schema types array' );	// End timer.
					}

				} elseif ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'schema types array already filtered' );
				}
			}

			if ( $flatten ) {

				return $this->types_cache[ 'flattened' ];
			}

			return $this->types_cache[ 'filtered' ];
		}

		/**
		 * Returns an array of schema type ids with gparent, parent, child (in that order).
		 *
		 * $use_cache is false when calling get_schema_type_child_family() recursively.
		 */
		public function get_schema_type_child_family( $child_id, $use_cache = true, &$child_family = array() ) {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			if ( $use_cache ) {

				$cache_md5_pre  = 'wpsso_t_';
				$cache_exp_secs = $this->p->util->get_cache_exp_secs( $cache_md5_pre );

				if ( $cache_exp_secs > 0 ) {

					$cache_salt   = __METHOD__ . '(child_id:' . $child_id . ')';
					$cache_id     = $cache_md5_pre . md5( $cache_salt );
					$child_family = get_transient( $cache_id );	// Returns false when not found.

					if ( is_array( $child_family ) ) {

						return $child_family;
					}
				}
			}

			$schema_types = $this->get_schema_types_array( $flatten = true );	// Defines the 'parents' array.

			if ( isset( $this->types_cache[ 'parents' ][ $child_id ] ) ) {

				foreach( $this->types_cache[ 'parents' ][ $child_id ] as $parent_id ) {

					if ( $parent_id !== $child_id )	{		// Prevent infinite loops.

						/**
						 * $use_cache is false for recursive calls.
						 */
						$this->get_schema_type_child_family( $parent_id, $child_use_cache = false, $child_family );
					}
				}
			}

			$child_family[] = $child_id;	// Add child after parents.

			$child_family = array_unique( $child_family );

			if ( $use_cache ) {

				if ( $cache_exp_secs > 0 ) {

					set_transient( $cache_id, $child_family, $cache_exp_secs );
				}
			}

			return $child_family;
		}

		/**
		 * Returns an array of schema type ids with child, parent, gparent (in that order).
		 *
		 * $use_cache is false when calling get_schema_type_children() recursively.
		 */
		public function get_schema_type_children( $type_id, $use_cache = true, &$children = array() ) {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->log( 'getting children for type id ' . $type_id );
			}

			if ( $use_cache ) {

				$cache_md5_pre  = 'wpsso_t_';
				$cache_exp_secs = $this->p->util->get_cache_exp_secs( $cache_md5_pre );

				if ( $cache_exp_secs > 0 ) {

					$cache_salt = __METHOD__ . '(type_id:' . $type_id . ')';
					$cache_id   = $cache_md5_pre . md5( $cache_salt );
					$children   = get_transient( $cache_id );	// Returns false when not found.

					if ( is_array( $children ) ) {

						return $children;
					}
				}
			}

			$children[] = $type_id;	// Add children before parents.

			$schema_types = $this->get_schema_types_array( $flatten = true );	// Defines the 'parents' array.

			foreach ( $this->types_cache[ 'parents' ] as $child_id => $parent_ids ) {

				foreach( $parent_ids as $parent_id ) {

					if ( $parent_id === $type_id ) {

						/**
						 * $use_cache is false for recursive calls.
						 */
						$this->get_schema_type_children( $child_id, $child_use_cache = false, $children );
					}
				}
			}

			$children = array_unique( $children );

			if ( $use_cache ) {

				if ( $cache_exp_secs > 0 ) {

					set_transient( $cache_id, $children, $cache_exp_secs );
				}
			}

			return $children;
		}

		public static function get_schema_type_context( $type_url, $json_data = array() ) {

			if ( preg_match( '/^(.+:\/\/.+)\/([^\/]+)$/', $type_url, $match ) ) {

				$context_value = $match[ 1 ];
				$type_value    = $match[ 2 ];

				/**
				 * Check for schema extension (example: https://health-lifesci.schema.org).
				 *
				 * $context_value = array(
				 *	"https://schema.org",
				 *	array(
				 *		"health-lifesci" => "https://health-lifesci.schema.org",
				 *	),
				 * );
				 *
				 */
				if ( preg_match( '/^(.+:\/\/)([^\.]+)\.([^\.]+\.[^\.]+)$/', $context_value, $ext ) ) {

					$context_value = array( 
						$ext[ 1 ] . $ext[ 3 ],
						array(
							$ext[ 2 ] => $ext[ 0 ],
						)
					);
				}

				$json_head = array(
					'@id'      => null,
					'@context' => null,
					'@type'    => null,
				);

				$json_values = array(
					'@context' => $context_value,
					'@type'    => $type_value,
				);

				/**
				 * Include $json_head first to keep @id, @context, and @type top-most.
				 */
				if ( is_array( $json_data ) ) {	// Just in case.

					$json_data = array_merge( $json_head, $json_data, $json_values );

					if ( empty( $json_data[ '@id' ] ) ) {

						unset( $json_data[ '@id' ] );
					}

				} else {

					return $json_values;
				}
			}

			return $json_data;
		}

		public function get_schema_type_id_for( $opt_suffix, $default_id = null ) {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->log_args( array( 
					'opt_suffix' => $opt_suffix,
					'default_id' => $default_id,
				) );
			}

			if ( empty( $opt_suffix ) ) {	// Just in case.

				return $default_id;
			}

			$opt_key      = 'schema_type_for_' . $opt_suffix;
			$type_id      = isset( $this->p->options[ $opt_key ] ) ? $this->p->options[ $opt_key ] : $default_id;
			$schema_types = $this->get_schema_types_array( $flatten = true );	// Uses a class variable cache.

			if ( empty( $type_id ) || 'none' === $type_id || empty( $schema_types[ $type_id ] ) ) {

				return $default_id;
			}

			return $type_id;
		}

		public function get_default_schema_type_name_for( $opt_suffix, $default_id = null ) {

			if ( empty( $opt_suffix ) ) {	// Just in case.

				return $default_id;
			}

			$opt_key      = 'schema_type_for_' . $opt_suffix;
			$type_id      = $this->p->opt->get_defaults( $opt_key );	// Uses a local static cache.
			$schema_types = $this->get_schema_types_array( $flatten = true );	// Uses a class variable cache.

			if ( empty( $type_id ) || 'none' === $type_id || empty( $schema_types[ $type_id ] ) ) {

				/**
				 * We're returning the Schema type name, so make sure the default schema type id is valid as well.
				 */
				if ( empty( $default_id ) || 'none' === $default_id || empty( $schema_types[ $default_id ] ) ) {

					return $default_id;
				}

				$type_id = $default_id;
			}

			$type_url = preg_replace( '/^.*\/\//', '', $schema_types[ $type_id ] );

			return preg_replace( '/^.*\//U', '', $type_url );
		}

		/**
		 * Check if the Schema type matches a pre-defined Open Graph type.
		 *
		 * For example, a Schema place sub-type would return 'place' for the Open Graph type.
		 *
		 * Returns false or an Open Graph type string.
		 */
		public function get_schema_type_og_type( $type_id ) {

			static $local_cache = array();	// Cache for single page load.

			if ( isset( $local_cache[ $type_id ] ) ) {

				return $local_cache[ $type_id ];
			}

			/**
			 * Hard-code the Open Graph type based on the Schema type.
			 */
			foreach ( $this->p->cf[ 'head' ][ 'og_type_by_schema_type' ] as $parent_id => $og_type ) {

				if ( $this->is_schema_type_child( $type_id, $parent_id ) ) {

					return $local_cache[ $type_id ] = $og_type;
				}
			}

			return $local_cache[ $type_id ] = false;
		}

		public function ajax_schema_type_og_type() {

			$doing_ajax = SucomUtilWP::doing_ajax();

			if ( ! $doing_ajax ) {	// Just in case.

				return;

			} elseif ( SucomUtil::get_const( 'DOING_AUTOSAVE' ) ) {

				die( -1 );
			}

			check_ajax_referer( WPSSO_NONCE_NAME, '_ajax_nonce', $die = true );

			$schema_type = sanitize_text_field( filter_input( INPUT_POST, 'schema_type' ) );

			if ( $og_type = $this->get_schema_type_og_type( $schema_type ) ) {

				die( $og_type );

			} else {

				die( -1 );
			}
		}

		/**
		 * Javascript classes to hide/show table rows by the selected schema type value.
		 */
		public static function get_schema_type_row_class( $name = 'schema_type' ) {

			static $local_cache = null;

			if ( isset( $local_cache[ $name ] ) ) {

				return $local_cache[ $name ];
			}

			$wpsso =& Wpsso::get_instance();

			$cache_md5_pre  = 'wpsso_t_';
			$cache_exp_secs = $wpsso->util->get_cache_exp_secs( $cache_md5_pre );

			if ( $cache_exp_secs > 0 ) {

				$cache_salt  = __METHOD__;
				$cache_id    = $cache_md5_pre . md5( $cache_salt );
				$local_cache = get_transient( $cache_id );	// Returns false when not found.

				if ( isset( $local_cache[ $name ] ) ) {

					return $local_cache[ $name ];
				}
			}

			if ( ! is_array( $local_cache ) ) {

				$local_cache = array();
			}

			$class_type_ids = array();

			switch ( $name ) {

				case 'schema_review_item_type':

					$class_type_ids = array(
						'book'           => 'book',
						'creative_work'  => 'creative.work',
						'movie'          => 'movie',
						'product'        => 'product',
						'software_app'   => 'software.application',
					);

					break;

				case 'schema_type':

					$class_type_ids = array(
						'book'           => 'book',
						'book_audio'     => 'book.audio',
						'creative_work'  => 'creative.work',
						'course'         => 'course',
						'event'          => 'event',
						'faq'            => 'webpage.faq',
						'how_to'         => 'how.to',
						'job_posting'    => 'job.posting',
						'local_business' => 'local.business',
						'movie'          => 'movie',
						'organization'   => 'organization',
						'person'         => 'person',
						'place'          => 'place',
						'product'        => 'product',
						'qa'             => 'webpage.qa',
						'question'       => 'question',
						'recipe'         => 'recipe',
						'review'         => 'review',
						'review_claim'   => 'review.claim',
						'software_app'   => 'software.application',
					);

					break;
			}

			foreach ( $class_type_ids as $class_name => $type_id ) {

				switch ( $type_id ) {

					case 'how.to':

						$exclude_match = '/^recipe$/';

						break;

					default:

						$exclude_match = '';

						break;
				}

				$local_cache[ $name ][ $class_name ] = $wpsso->schema->get_children_css_class( $type_id,
					$class_prefix = 'hide_' . $name, $exclude_match );
			}

			if ( $cache_exp_secs > 0 ) {

				set_transient( $cache_id, $local_cache, $cache_exp_secs );
			}

			return $local_cache[ $name ];
		}

		/**
		 * Get the full schema type url from the array key.
		 */
		public function get_schema_type_url( $type_id, $default_id = false ) {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->log( 'getting schema url for ' . $type_id );
			}

			$schema_types = $this->get_schema_types_array( $flatten = true );

			if ( 'none' !== $type_id && isset( $schema_types[ $type_id ] ) ) {

				return $schema_types[ $type_id ];

			} elseif ( false !== $default_id && isset( $schema_types[ $default_id ] ) ) {

				return $schema_types[ $default_id ];
			}

			return false;
		}

		/**
		 * Returns an array of schema type id for a given type URL.
		 */
		public function get_schema_type_url_ids( $type_url ) {

			$type_ids = array();

			$schema_types = $this->get_schema_types_array( $flatten = true );

			foreach ( $schema_types as $id => $url ) {

				if ( $url === $type_url ) {

					$type_ids[] = $id;
				}
			}

			return $type_ids;
		}

		/**
		 * Returns the first schema type id for a given type URL.
		 */
		public function get_schema_type_url_id( $type_url, $default_id = false ) {

			$schema_types = $this->get_schema_types_array( $flatten = true );

			foreach ( $schema_types as $id => $url ) {

				if ( $url === $type_url ) {

					return $id;
				}
			}

			return $default_id;
		}

		public static function get_schema_type_url_parts( $type_url ) {

			if ( preg_match( '/^(.+:\/\/.+)\/([^\/]+)$/', $type_url, $match ) ) {

				return array( $match[1], $match[2] );

			} else {

				return array( null, null );	// Return two elements.
			}
		}

		public function get_children_css_class( $type_id, $class_prefix = 'hide_schema_type', $exclude_match = '' ) {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			if ( empty( $class_prefix ) ) {

				$css_classes  = '';
				$class_prefix = '';

			} else {

				$css_classes  = $class_prefix;
				$class_prefix = SucomUtil::sanitize_hookname( $class_prefix ) . '_';
			}

			foreach ( $this->get_schema_type_children( $type_id ) as $child ) {

				if ( ! empty( $exclude_match ) ) {

					if ( preg_match( $exclude_match, $child ) ) {

						continue;
					}
				}

				$css_classes .= ' ' . $class_prefix . SucomUtil::sanitize_hookname( $child );
			}

			$css_classes = trim( $css_classes );

			return $css_classes;
		}

		public function is_schema_type_child( $child_id, $member_id ) {

			static $local_cache = array();		// Cache for single page load.

			if ( isset( $local_cache[ $child_id ][ $member_id ] ) ) {

				return $local_cache[ $child_id ][ $member_id ];
			}

			if ( $child_id === $member_id ) {	// Optimize and check for obvious.

				$is_child = true;

			} else {

				$child_family = $this->get_schema_type_child_family( $child_id );

				$is_child = in_array( $member_id, $child_family ) ? true : false;
			}

			return $local_cache[ $child_id ][ $member_id ] = $is_child;
		}

		public function count_schema_type_children( $type_id ) {

			$children = $this->get_schema_type_children( $type_id );

			return count( $children );
		}

		public function has_json_data_filter( array $mod, $type_url = '' ) {

			$filter_name = $this->get_json_data_filter( $mod, $type_url );

			return empty( $filter_name ) ? false : has_filter( $filter_name );
		}

		public function get_json_data_filter( array $mod, $type_url = '' ) {

			if ( empty( $type_url ) ) {

				$type_url = $this->get_mod_schema_type_url( $mod );
			}

			return 'wpsso_json_data_' . SucomUtil::sanitize_hookname( $type_url );
		}

		/**
		 * Since WPSSO Core v9.2.1.
		 *
		 * Check if Google allows aggregate rarings for this Schema type.
		 */
		public function allow_aggregate_rating( $page_type_id ) {

			foreach ( $this->p->cf[ 'head' ][ 'schema_aggregate_rating_parents' ] as $parent_id ) {

				if ( $this->is_schema_type_child( $page_type_id, $parent_id ) ) {

					if ( $this->p->debug->enabled ) {

						$this->p->debug->log( 'aggregate rating for schema type ' . $page_type_id . ' is allowed' );
					}

					return true;
				}
			}

			if ( $this->p->debug->enabled ) {

				$this->p->debug->log( 'aggregate rating for schema type ' . $page_type_id . ' not allowed' );
			}

			return false;
		}

		/**
		 * Since WPSSO Core v9.2.1.
		 *
		 * Check if Google allows reviews for this Schema type.
		 */
		public function allow_review( $page_type_id ) {

			foreach ( $this->p->cf[ 'head' ][ 'schema_review_parents' ] as $parent_id ) {

				if ( $this->is_schema_type_child( $page_type_id, $parent_id ) ) {

					if ( $this->p->debug->enabled ) {

						$this->p->debug->log( 'review for schema type ' . $page_type_id . ' is allowed' );
					}

					return true;
				}
			}

			if ( $this->p->debug->enabled ) {

				$this->p->debug->log( 'review for schema type ' . $page_type_id . ' not allowed' );
			}

			return false;
		}

		/**
		 * json_data can be null, so don't cast an array on the input argument. 
		 *
		 * The @context value can be an array if the schema type is an extension.
		 *
		 * @context = array(
		 *	"https://schema.org",
		 *	array(
		 *		"health-lifesci" => "https://health-lifesci.schema.org",
		 *	)
		 * )
		 */
		public static function get_data_type_id( $json_data, $default_id = false ) {

			$wpsso =& Wpsso::get_instance();

			$type_url = self::get_data_type_url( $json_data );

			return $wpsso->schema->get_schema_type_url_id( $type_url, $default_id );
		}

		public static function get_data_type_url( $json_data ) {

			$type_url = false;

			if ( empty( $json_data[ '@type' ] ) ) {

				return false;	// Stop here.

			} elseif ( is_array( $json_data[ '@type' ] ) ) {

				$json_data[ '@type' ] = reset( $json_data[ '@type' ] );	// Use first @type element.

				$type_url = self::get_data_type_url( $json_data );

			} elseif ( strpos( $json_data[ '@type' ], '://' ) ) {	// @type is a complete url

				$type_url = $json_data[ '@type' ];

			} elseif ( ! empty(  $json_data[ '@context' ] ) ) {	// Just in case.

				if ( is_array( $json_data[ '@context' ] ) ) {	// Get the extension url.

					$context_url = self::get_context_extension_url( $json_data[ '@context' ] );

					if ( ! empty( $context_url ) ) {	// Just in case.

						$type_url = trailingslashit( $context_url ) . $json_data[ '@type' ];
					}

				} elseif ( is_string( $json_data[ '@context' ] ) ) {

					$type_url = trailingslashit( $json_data[ '@context' ] ) . $json_data[ '@type' ];
				}
			}

			$type_url = set_url_scheme( $type_url, 'https' );	// Just in case.

			return $type_url;
		}

		public static function get_data_context( $json_data ) {

			if ( false !== ( $type_url = self::get_data_type_url( $json_data ) ) ) {

				return self::get_schema_type_context( $type_url );
			}

			return array();
		}

		public static function get_context_extension_url( array $json_data ) {

			$type_url = false;
			$ext_data = array_reverse( $json_data );	// Read the array bottom-up.

			foreach ( $ext_data as $val ) {

				if ( is_array( $val ) ) {		// If it's an extension array, drill down and return that value.

					return self::get_context_extension_url( $val );

				} elseif ( is_string( $val ) ) {	// Set a backup value in case there is no extension array.

					$type_url = $val;
				}
			}

			return false;
		}

		/**
		 * Get the site organization array.
		 *
		 * $mixed = 'default' | 'current' | post ID | $mod array
		 */
		public static function get_site_organization( $mixed = 'current' ) {

			$wpsso =& Wpsso::get_instance();

			if ( $wpsso->debug->enabled ) {

				$wpsso->debug->mark();
			}

			$org_sameas = array();

			foreach ( WpssoConfig::get_social_accounts() as $social_key => $social_label ) {

				$url = SucomUtil::get_key_value( $social_key, $wpsso->options, $mixed );	// Localized value.

				if ( empty( $url ) ) {

					continue;

				} elseif ( $social_key === 'tc_site' ) {	// Convert Twitter username to a URL.

					$url = 'https://twitter.com/' . preg_replace( '/^@/', '', $url );
				}

				if ( false !== filter_var( $url, FILTER_VALIDATE_URL ) ) {	// Just in case.

					$org_sameas[] = $url;
				}
			}

			/**
			 * Logo and banner image dimensions are localized as well.
			 *
			 * Example: 'site_org_logo_url:width#fr_FR'.
			 */
			$org_opts = array(
				'org_url'               => SucomUtil::get_home_url( $wpsso->options, $mixed ),
				'org_name'              => SucomUtil::get_site_name( $wpsso->options, $mixed ),
				'org_name_alt'          => SucomUtil::get_site_name_alt( $wpsso->options, $mixed ),
				'org_desc'              => SucomUtil::get_site_description( $wpsso->options, $mixed ),
				'org_logo_url'          => SucomUtil::get_key_value( 'site_org_logo_url', $wpsso->options, $mixed ),
				'org_logo_url:width'    => SucomUtil::get_key_value( 'site_org_logo_url:width', $wpsso->options, $mixed ),
				'org_logo_url:height'   => SucomUtil::get_key_value( 'site_org_logo_url:height', $wpsso->options, $mixed ),
				'org_banner_url'        => SucomUtil::get_key_value( 'site_org_banner_url', $wpsso->options, $mixed ),
				'org_banner_url:width'  => SucomUtil::get_key_value( 'site_org_banner_url:width', $wpsso->options, $mixed ),
				'org_banner_url:height' => SucomUtil::get_key_value( 'site_org_banner_url:height', $wpsso->options, $mixed ),
				'org_schema_type'       => $wpsso->options[ 'site_org_schema_type' ],
				'org_place_id'          => $wpsso->options[ 'site_org_place_id' ],
				'org_sameas'            => $org_sameas,
			);

			return $org_opts;
		}

		public static function add_howto_step_data( &$json_data, $mod, $md_opts, $opt_prefix = 'schema_howto_step', $prop_name = 'step' ) {

			$wpsso =& Wpsso::get_instance();

			if ( $wpsso->debug->enabled ) {

				$wpsso->debug->mark();
			}

			$howto_steps = SucomUtil::preg_grep_keys( '/^' . $opt_prefix . '_([0-9]+)$/', $md_opts, $invert = false, $replace = '$1' );

			if ( ! empty( $howto_steps ) ) {

				$section_ref = false;
				$section_pos = 1;

				$step_pos = 1;
				$step_idx = 0;

				/**
				 * $md_val is the section/step name.
				 */
				foreach ( $howto_steps as $md_num => $md_val ) {

					/**
					 * Maybe get a longer text / description value.
					 */
					$step_text = isset( $md_opts[ $opt_prefix . '_text_' . $md_num ] ) ? $md_opts[ $opt_prefix . '_text_' . $md_num ] : $md_val;

					/**
					 * Get images for the section or step.
					 */
					$step_images = array();

					if ( ! empty( $md_opts[ $opt_prefix . '_img_id_' . $md_num ] ) ) {

						/**
						 * Set reference values for admin notices.
						 */
						if ( is_admin() ) {

							$canonical_url = $wpsso->util->get_canonical_url( $mod );

							$wpsso->notice->set_ref( $canonical_url, $mod, sprintf( __( 'adding schema %s #%d image', 'wpsso' ),
								$prop_name, $md_num + 1 ) );
						}

						/**
						 * $size_names can be a keyword (ie. 'opengraph' or 'schema'), a registered size name, or an array of size names.
						 */
						$mt_images = $wpsso->media->get_mt_opts_images( $md_opts, $size_names = 'schema', $opt_prefix . '_img', $md_num );

						self::add_images_data_mt( $step_images, $mt_images );

						/**
						 * Restore previous reference values for admin notices.
						 */
						if ( is_admin() ) {

							$wpsso->notice->unset_ref( $canonical_url );
						}
					}

					/**
					 * Add a How-To Section.
					 */
					if ( ! empty( $md_opts[ $opt_prefix . '_section_' . $md_num ] ) ) {

						$json_data[ $prop_name ][ $step_idx ] = self::get_schema_type_context( 'https://schema.org/HowToSection',
							array(
								'name'            => $md_val,
								'description'     => $step_text,
								'numberOfItems'   => 0,
								'itemListOrder'   => 'https://schema.org/ItemListOrderAscending',
								'itemListElement' => array(),
							)
						);

						if ( $step_images ) {

							$json_data[ $prop_name ][ $step_idx ][ 'image' ] = $step_images;
						}

						$section_ref =& $json_data[ $prop_name ][ $step_idx ];

						$section_pos++;

						$step_pos = 1;

						$step_idx++;

					/**
					 * Add a How-To Step.
					 */
					} else {

						$step_arr = self::get_schema_type_context( 'https://schema.org/HowToStep',
							array(
								'position' => $step_pos,
								'name'     => $md_val,		// The step name.
								'text'     => $step_text,	// The step text / description.
								'image'    => null,
							)
						);

						if ( ! empty( $step_images ) ) {

							$step_arr[ 'image' ] = $step_images;
						}

						/**
						 * If we have a section, add a new step to the section.
						 */
						if ( false !== $section_ref ) {

							$section_ref[ 'itemListElement' ][] = $step_arr;

							$section_ref[ 'numberOfItems' ] = $step_pos;

						} else {

							$json_data[ $prop_name ][ $step_idx ] = $step_arr;

							$step_idx++;
						}

						$step_pos++;
					}
				}
			}
		}

		public static function add_item_reviewed_data( &$json_data, $mod, $md_opts ) {

			$wpsso =& Wpsso::get_instance();

			if ( $wpsso->debug->enabled ) {

				$wpsso->debug->mark();
			}

			if ( self::is_valid_key( $md_opts, 'schema_review_item_type' ) ) {	// Not null, an empty string, or 'none'.

				$type_id = $md_opts[ 'schema_review_item_type' ];

			} else {

				$type_id = 'thing';
			}

			$type_url = $wpsso->schema->get_schema_type_url( $type_id );

			if ( ! $wpsso->schema->allow_review( $type_id ) ) {

				$notice_msg = sprintf( __( 'Please note that although the Schema standard allows the subject of a review to be any Schema type, <a href="%1$s">Google does not allow reviews for the Schema %2$s type</a>.', 'wpsso' ), 'https://developers.google.com/search/docs/data-types/review-snippet', $type_url ) . ' ';

				$wpsso->notice->warn( $notice_msg );
			}

			$json_data = self::get_schema_type_context( $type_url, $json_data );

			self::add_data_itemprop_from_assoc( $json_data, $md_opts, array(
				'url'         => 'schema_review_item_url',
				'name'        => 'schema_review_item_name',
				'description' => 'schema_review_item_desc',
			) );

			foreach ( SucomUtil::preg_grep_keys( '/^schema_review_item_sameas_url_[0-9]+$/', $md_opts ) as $url ) {

				$json_data[ 'sameAs' ][] = SucomUtil::esc_url_encode( $url );
			}

			self::check_prop_value_sameas( $json_data );

			/**
			 * Set reference values for admin notices.
			 */
			if ( is_admin() ) {

				$canonical_url = $wpsso->util->get_canonical_url( $mod );

				$wpsso->util->maybe_set_ref( $canonical_url, $mod, __( 'adding reviewed subject image', 'wpsso' ) );
			}

			/**
			 * Add the item images.
			 *
			 * $size_names can be a keyword (ie. 'opengraph' or 'schema'), a registered size name, or an array of size names.
			 */
			$mt_images = $wpsso->media->get_mt_opts_images( $md_opts, $size_names = 'schema', $img_pre = 'schema_review_item_img' );

			self::add_images_data_mt( $json_data[ 'image' ], $mt_images );

			if ( empty( $json_data[ 'image' ] ) ) {

				unset( $json_data[ 'image' ] );	// Prevent null assignment.

			} elseif ( $wpsso->debug->enabled ) {

				$wpsso->debug->log( $json_data[ 'image' ] );
			}

			/**
			 * Restore previous reference values for admin notices.
			 */
			if ( is_admin() ) {

				$wpsso->util->maybe_unset_ref( $canonical_url );
			}

			/**
			 * Item Reviewed: Creative Work
			 */
			if ( $wpsso->schema->is_schema_type_child( $type_id, 'creative.work' ) ) {

				/**
				 * The author type value should be either 'organization' or 'person'.
				 */
				if ( self::is_valid_key( $md_opts, 'schema_review_item_cw_author_type' ) ) {	// Not null, an empty string, or 'none'.

					$author_type_url = $wpsso->schema->get_schema_type_url( $md_opts[ 'schema_review_item_cw_author_type' ] );

					$json_data[ 'author' ] = self::get_schema_type_context( $author_type_url );

					self::add_data_itemprop_from_assoc( $json_data[ 'author' ], $md_opts, array(
						'name' => 'schema_review_item_cw_author_name',
					) );

					if ( ! empty( $md_opts[ 'schema_review_item_cw_author_url' ] ) ) {

						$json_data[ 'author' ][ 'sameAs' ][] = SucomUtil::esc_url_encode( $md_opts[ 'schema_review_item_cw_author_url' ] );
					}
				}

				/**
				 * Subject Published Date.
				 *
				 * Add the creative work published date, if one is available.
				 */
				if ( $date = self::get_opts_date_iso( $md_opts, 'schema_review_item_cw_pub' ) ) {

					$json_data[ 'datePublished' ] = $date;
				}

				/**
				 * Subject Created Date.
				 *
				 * Add the creative work created date, if one is available.
				 */
				if ( $date = self::get_opts_date_iso( $md_opts, 'schema_review_item_cw_created' ) ) {

					$json_data[ 'dateCreated' ] = $date;
				}

				/**
				 * Item Reviewed: Creative Work > Book
				 */
				if ( $wpsso->schema->is_schema_type_child( $type_id, 'book' ) ) {

					self::add_data_itemprop_from_assoc( $json_data, $md_opts, array(
						'isbn' => 'schema_review_item_cw_book_isbn',
					) );

				/**
				 * Item Reviewed: Creative Work > Movie
				 */
				} elseif ( $wpsso->schema->is_schema_type_child( $type_id, 'movie' ) ) {

					/**
					 * Property:
					 * 	actor (supersedes actors)
					 */
					self::add_person_names_data( $json_data, 'actor', $md_opts, 'schema_review_item_cw_movie_actor_person_name' );

					/**
					 * Property:
					 * 	director
					 */
					self::add_person_names_data( $json_data, 'director', $md_opts, 'schema_review_item_cw_movie_director_person_name' );

				/**
				 * Item Reviewed: Creative Work > Software Application
				 */
				} elseif ( $wpsso->schema->is_schema_type_child( $type_id, 'software.application' ) ) {

					self::add_data_itemprop_from_assoc( $json_data, $md_opts, array(
						'applicationCategory'  => 'schema_review_item_software_app_cat',
						'operatingSystem'      => 'schema_review_item_software_app_os',
					) );

					$metadata_offers_max = SucomUtil::get_const( 'WPSSO_SCHEMA_METADATA_OFFERS_MAX', 5 );

					foreach ( range( 0, $metadata_offers_max - 1, 1 ) as $key_num ) {

						$offer_opts = SucomUtil::preg_grep_keys( '/^schema_review_item_software_app_(offer_.*)_' . $key_num. '$/',
							$md_opts, $invert = false, $replace = '$1' );

						/**
						 * Must have at least an offer name and price.
						 */
						if ( isset( $offer_opts[ 'offer_name' ] ) && isset( $offer_opts[ 'offer_price' ] ) ) {

							if ( false !== ( $offer = self::get_data_itemprop_from_assoc( $offer_opts, array( 
								'name'          => 'offer_name',
								'price'         => 'offer_price',
								'priceCurrency' => 'offer_currency',
								'availability'  => 'offer_avail',	// In stock, Out of stock, Pre-order, etc.
							) ) ) ) {

								/**
								 * Avoid Google validator warnings.
								 */
								$offer[ 'url' ]             = $json_data[ 'url' ];
								$offer[ 'priceValidUntil' ] = gmdate( 'c', time() + MONTH_IN_SECONDS );

								/**
								 * Add the offer.
								 */
								$json_data[ 'offers' ][] = self::get_schema_type_context( 'https://schema.org/Offer', $offer );
							}
						}
					}
				}

			/**
			 * Item Reviewed: Product
			 */
			} elseif ( $wpsso->schema->is_schema_type_child( $type_id, 'product' ) ) {

				self::add_data_itemprop_from_assoc( $json_data, $md_opts, array(
					'sku'  => 'schema_review_item_product_retailer_part_no',
					'mpn'  => 'schema_review_item_product_mfr_part_no',
				) );

				/**
				 * Add the product brand.
				 */
				$single_brand = self::get_data_itemprop_from_assoc( $md_opts, array( 
					'name' => 'schema_review_item_product_brand',
				) );

				if ( false !== $single_brand ) {	// Just in case.

					$json_data[ 'brand' ] = self::get_schema_type_context( 'https://schema.org/Brand', $single_brand );
				}

				$metadata_offers_max = SucomUtil::get_const( 'WPSSO_SCHEMA_METADATA_OFFERS_MAX', 5 );

				foreach ( range( 0, $metadata_offers_max - 1, 1 ) as $key_num ) {

					$offer_opts = SucomUtil::preg_grep_keys( '/^schema_review_item_product_(offer_.*)_' . $key_num. '$/',
						$md_opts, $invert = false, $replace = '$1' );

					/**
					 * Must have at least an offer name and price.
					 */
					if ( isset( $offer_opts[ 'offer_name' ] ) && isset( $offer_opts[ 'offer_price' ] ) ) {

						if ( false !== ( $offer = self::get_data_itemprop_from_assoc( $offer_opts, array( 
							'name'          => 'offer_name',
							'price'         => 'offer_price',
							'priceCurrency' => 'offer_currency',
							'availability'  => 'offer_avail',	// In stock, Out of stock, Pre-order, etc.
						) ) ) ) {

							/**
							 * Add the offer.
							 */
							$json_data[ 'offers' ][] = self::get_schema_type_context( 'https://schema.org/Offer', $offer );
						}
					}
				}
			}
		}

		public static function add_offers_data( &$json_data, array $mod, array $mt_offers ) {

			$wpsso =& Wpsso::get_instance();

			if ( $wpsso->debug->enabled ) {

				$wpsso->debug->mark();
			}

			$offers_added  = 0;

			if ( $wpsso->debug->enabled ) {

				$wpsso->debug->log( 'adding ' . count( $mt_offers ) . ' offers as offer' );
			}

			foreach ( $mt_offers as $offer_num => $mt_offer ) {

				if ( ! is_array( $mt_offer ) ) {	// Just in case.

					if ( $wpsso->debug->enabled ) {

						$wpsso->debug->log( 'skipping offer #' . $offer_num . ': not an array' );
					}

					continue;
				}

				$single_offer = WpssoSchemaSingle::get_offer_data( $mod, $mt_offer );

				if ( false === $single_offer ) {

					continue;
				}

				$json_data[ 'offers' ][] = self::get_schema_type_context( 'https://schema.org/Offer', $single_offer );

				$offers_added++;
			}

			return $offers_added;
		}

		public static function add_offers_aggregate_data( &$json_data, array $mod, array $mt_offers ) {

			$wpsso =& Wpsso::get_instance();

			if ( $wpsso->debug->enabled ) {

				$wpsso->debug->mark();
			}

			$aggr_added  = 0;
			$aggr_prices = array();
			$aggr_offers = array();
			$aggr_common = array();

			if ( $wpsso->debug->enabled ) {

				$wpsso->debug->log( 'adding ' . count( $mt_offers ) . ' offers as aggregateoffer' );
			}

			foreach ( $mt_offers as $offer_num => $mt_offer ) {

				if ( ! is_array( $mt_offer ) ) {	// Just in case.

					if ( $wpsso->debug->enabled ) {

						$wpsso->debug->log( 'skipping offer #' . $offer_num . ': not an array' );
					}

					continue;
				}

				$single_offer = WpssoSchemaSingle::get_offer_data( $mod, $mt_offer );

				if ( false === $single_offer ) {

					continue;
				}

				/**
				 * Keep track of the lowest and highest price by currency.
				 */
				$price_currency = $single_offer[ 'priceCurrency' ];	// Shortcut variable.

				if ( isset( $single_offer[ 'price' ] ) ) {	// Just in case.

					if ( ! isset( $aggr_prices[ $price_currency ][ 'lowPrice' ] ) ||
						$aggr_prices[ $price_currency ][ 'lowPrice' ] > $single_offer[ 'price' ] ) {

						$aggr_prices[ $price_currency ][ 'lowPrice' ] = $single_offer[ 'price' ];
					}

					if ( ! isset( $aggr_prices[ $price_currency ][ 'highPrice' ] ) ||
						$aggr_prices[ $price_currency ][ 'highPrice' ] < $single_offer[ 'price' ] ) {

						$aggr_prices[ $price_currency ][ 'highPrice' ] = $single_offer[ 'price' ];
					}
				}

				/**
				 * Save common properties (by currency) to include in the AggregateOffer markup.
				 */
				if ( $offer_num === 0 ) {

					foreach ( preg_grep( '/^[^@]/', array_keys( $single_offer ) ) as $key ) {

						$aggr_common[ $price_currency ][ $key ] = $single_offer[ $key ];
					}

				} elseif ( ! empty( $aggr_common[ $price_currency ] ) ) {

					foreach ( $aggr_common[ $price_currency ] as $key => $val ) {

						if ( ! isset( $single_offer[ $key ] ) ) {

							unset( $aggr_common[ $price_currency ][ $key ] );

						} elseif ( $val !== $single_offer[ $key ] ) {

							unset( $aggr_common[ $price_currency ][ $key ] );
						}
					}
				}

				/**
				 * Add the complete offer.
				 */
				$aggr_offers[ $price_currency ][] = $single_offer;
			}

			/**
			 * Add aggregate offers grouped by currency.
			 */
			foreach ( $aggr_offers as $price_currency => $currency_offers ) {

				if ( ( $offer_count = count( $currency_offers ) ) > 0 ) {

					$offer_group = array();

					/**
					 * Maybe set the 'lowPrice' and 'highPrice' properties.
					 */
					foreach ( array( 'lowPrice', 'highPrice' ) as $price_mark ) {

						if ( isset( $aggr_prices[ $price_currency ][ $price_mark ] ) ) {

							$offer_group[ $price_mark ] = $aggr_prices[ $price_currency ][ $price_mark ];
						}
					}

					$offer_group[ 'priceCurrency' ] = $price_currency;

					if ( ! empty( $aggr_common[ $price_currency ] ) ) {

						foreach ( $aggr_common[ $price_currency ] as $key => $val ) {

							$offer_group[ $key ] = $val;
						}
					}

					$offer_group[ 'offerCount' ] = $offer_count;

					$offer_group[ 'offers' ] = $currency_offers;

					$json_data[ 'offers' ][] = self::get_schema_type_context( 'https://schema.org/AggregateOffer', $offer_group );

					$aggr_added++;
				}
			}

			return $aggr_added;
		}

		/**
		 * Deprecated on 2021/02/08.
		 */
		public static function add_aggregate_offer_data( &$json_data, array $mod, array $mt_offers ) {

			_deprecated_function( __METHOD__ . '()', '2021/02/08', $replacement = __CLASS__ . '::add_offers_aggregate_data()' );	// Deprecation message.

			return self::add_offers_aggregate_data( $json_data, $mod, $mt_offers );
		}

		/**
		 * $user_id is optional and takes precedence over the $mod post_author value.
		 */
		public static function add_author_coauthor_data( &$json_data, $mod, $user_id = false ) {

			$wpsso =& Wpsso::get_instance();

			if ( $wpsso->debug->enabled ) {

				$wpsso->debug->mark();
			}

			$authors_added   = 0;
			$coauthors_added = 0;

			if ( empty( $user_id ) && isset( $mod[ 'post_author' ] ) ) {

				$user_id = $mod[ 'post_author' ];
			}

			if ( empty( $user_id ) || 'none' === $user_id ) {

				if ( $wpsso->debug->enabled ) {

					$wpsso->debug->log( 'exiting early: empty user_id / post_author' );
				}

				return 0;
			}

			/**
			 * Single author.
			 */
			$authors_added += WpssoSchemaSingle::add_person_data( $json_data[ 'author' ], $mod, $user_id, $list_element = false );

			/**
			 * List of contributors / co-authors.
			 */
			if ( ! empty( $mod[ 'post_coauthors' ] ) ) {

				foreach ( $mod[ 'post_coauthors' ] as $author_id ) {

					$coauthors_added += WpssoSchemaSingle::add_person_data( $json_data[ 'contributor' ], $mod, $author_id, $list_element = true );
				}
			}

			return $authors_added + $coauthors_added;	// Return count of authors and coauthors added.
		}

		public static function add_comment_list_data( &$json_data, $post_mod ) {

			$wpsso =& Wpsso::get_instance();

			if ( $wpsso->debug->enabled ) {

				$wpsso->debug->mark();
			}

			$comments_added = 0;

			if ( ! $post_mod[ 'is_post' ] || ! $post_mod[ 'id' ] || ! comments_open( $post_mod[ 'id' ] ) ) {

				return $comments_added;
			}

			$json_data[ 'commentCount' ] = (int) get_comments_number( $post_mod[ 'id' ] );

			/**
			 * Only get parent comments. The add_comment_data() method will recurse and add the children.
			 */
			if ( get_option( 'page_comments' ) ) {	// "Break comments into pages" option is checked.

				$comment_order  = strtoupper( get_option( 'comment_order' ) );
				$comment_paged  = $post_mod[ 'comment_paged' ] ? $post_mod[ 'comment_paged' ] : 1;		// Get the comment page number.
				$comment_number = get_option( 'comments_per_page' );

			} else {

				$comment_order  = 'DESC';
				$comment_paged  = 1;
				$comment_number = SucomUtil::get_const( 'WPSSO_SCHEMA_COMMENTS_MAX' );
			}

			if ( $comment_number ) {	// 0 disables the addition of comments.

				$get_comment_args = array(
					'post_id' => $post_mod[ 'id' ],
					'status'  => 'approve',
					'parent'  => 0,		// Don't get replies.
					'order'   => $comment_order,
					'orderby' => 'comment_date_gmt',
					'paged'   => $comment_paged,
					'number'  => $comment_number,
				);

				$comments = get_comments( $get_comment_args );

				if ( is_array( $comments ) ) {

					foreach( $comments as $num => $comment_obj ) {

						$comments_added += WpssoSchemaSingle::add_comment_data( $json_data[ 'comment' ], $post_mod, $comment_obj->comment_ID );
					}
				}
			}

			return $comments_added;	// Return count of comments added.
		}

		/**
		 * Pass a single or two dimension image array in $mt_images.
		 *
		 * Calls WpssoSchemaSingle::add_image_data_mt() to add each single image element.
		 */
		public static function add_images_data_mt( &$json_data, $mt_images, $media_pre = 'og:image', $resize = false ) {

			$wpsso =& Wpsso::get_instance();

			if ( $wpsso->debug->enabled ) {

				$wpsso->debug->mark();
			}

			$images_added = 0;

			if ( empty( $mt_images ) || ! is_array( $mt_images ) ) {

				return $images_added;
			}

			/**
			 * Maybe convert single image array to array of image arrays.
			 */
			if ( ! isset( $mt_images[ 0 ] ) || ! is_array( $mt_images[ 0 ] ) ) {

				$mt_images = array( $mt_images );
			}

			$resized_pids = array();	// Avoid adding the same image ID more than once.

			foreach ( $mt_images as $mt_single_image ) {

				/**
				 * Get the image ID and create a Schema images array.
				 */
				if ( $resize && $pid = $wpsso->media->get_media_value( array( $mt_single_image ), 'og:image:id' ) ) {

					if ( empty( $resized_pids[ $pid ] ) ) {	// Skip image IDs already added.

						$resized_pids[ $pid ] = true;

						$mt_resized = $wpsso->media->get_mt_pid_images( $pid, $size_names = 'schema', $check_dupes = false, $mt_pre = 'og' );

						/**
						 * Recurse this method, but make sure $resize is false so we don't re-execute this
						 * section of code (creating an infinite loop).
						 */
						$images_added += self::add_images_data_mt( $json_data, $mt_resized, $media_pre, $resize = false );
					}

				} else {	// No resize or no image ID found.

					$images_added += WpssoSchemaSingle::add_image_data_mt( $json_data, $mt_single_image, $media_pre, $list_element = true );
				}
			}

			return $images_added;
		}

		/**
		 * Called by WpssoJsonFiltersTypeItemList.
		 */
		public static function add_itemlist_data( &$json_data, array $mod, array $mt_og, $page_type_id, $is_main ) {

			$wpsso =& Wpsso::get_instance();

			if ( $wpsso->debug->enabled ) {

				$wpsso->debug->mark();
			}

			$prop_name = 'itemListElement';

			$item_count = isset( $json_data[ $prop_name ] ) ? count( $json_data[ $prop_name ] ) : 0;

			$json_data[ 'itemListOrder' ] = 'https://schema.org/ItemListUnordered';

			if ( isset( $mod[ 'query_vars' ][ 'order' ] ) ) {

				switch ( $mod[ 'query_vars' ][ 'order' ] ) {

					case 'ASC':

						$json_data[ 'itemListOrder' ] = 'https://schema.org/ItemListOrderAscending';

						break;

					case 'DESC':

						$json_data[ 'itemListOrder' ] = 'https://schema.org/ItemListOrderDescending';

						break;
				}
			}

			$page_posts_mods = $wpsso->page->get_posts_mods( $mod );

			if ( empty( $page_posts_mods ) ) {

				if ( $wpsso->debug->enabled ) {

					$wpsso->debug->log( 'exiting early: page_posts_mods array is empty' );
				}

				return $item_count;
			}

			if ( $wpsso->debug->enabled ) {

				$wpsso->debug->log( 'page_posts_mods array has ' . count( $page_posts_mods ) . ' elements' );
			}

			if ( empty( $json_data[ $prop_name ] ) ) {

				$json_data[ $prop_name ] = array();

			} elseif ( ! is_array( $json_data[ $prop_name ] ) ) {	// Convert single value to an array.

				$json_data[ $prop_name ] = array( $json_data[ $prop_name ] );
			}

			foreach ( $page_posts_mods as $post_mod ) {

				$item_count++;

				$post_canonical_url = $wpsso->util->get_canonical_url( $post_mod );

				$post_json_data = self::get_schema_type_context( 'https://schema.org/ListItem', array(
					'position' => $item_count,
					'url'      => $post_canonical_url,
				) );

				if ( $wpsso->debug->enabled ) {

					$wpsso->debug->log( 'adding post ID ' . $post_mod[ 'id' ] . ' to ' . $prop_name . ' as #' . $item_count );
				}

				$json_data[ $prop_name ][] = $post_json_data;	// Add the post data.
			}

			$filter_name = SucomUtil::sanitize_hookname( 'wpsso_json_prop_https_schema_org_' . $prop_name );

			if ( $wpsso->debug->enabled ) {

				$wpsso->debug->log( 'applying ' . $filter_name . ' filters' );
			}

			$json_data[ $prop_name ] = (array) apply_filters( $filter_name, $json_data[ $prop_name ], $mod, $mt_og, $page_type_id, $is_main );

			return $item_count;
		}

		/**
		 * $mt_og can be the main webpage open graph array or a product $mt_offer array.
		 *
		 * $size_names can be null, a string, or an array.
		 *
		 * $add_video can be true, false, or a string (property name).
		 */
		public static function add_media_data( &$json_data, $mod, $mt_og, $size_names = 'schema', $add_video = true ) {

			$wpsso =& Wpsso::get_instance();

			if ( $wpsso->debug->enabled ) {

				$wpsso->debug->mark();
			}

			/**
			 * Property:
			 *	image as https://schema.org/ImageObject
			 */
			$images_added = 0;

			$max_nums = $wpsso->util->get_max_nums( $mod, 'og' );

			$mt_images = $wpsso->media->get_all_images( $max_nums[ 'og_img_max' ], $size_names, $mod, $check_dupes = true, $md_pre = array( 'schema', 'og' ) );

			if ( ! empty( $mt_images ) ) {

				if ( $wpsso->debug->enabled ) {

					$wpsso->debug->log( 'adding images to json data' );
				}

				$images_added = self::add_images_data_mt( $json_data[ 'image' ], $mt_images );
			}

			if ( $wpsso->debug->enabled ) {

				$wpsso->debug->log( $images_added . ' images added' );
			}

			/**
			 * Property:
			 *	video as https://schema.org/VideoObject
			 *
			 * Allow the video property to be skipped -- some schema types (organization, for example) do not include a video property.
			 */
			if ( $add_video ) {

				if ( $wpsso->debug->enabled ) {

					$wpsso->debug->log( 'adding all video(s)' );
				}

				$vid_prop = is_string( $add_video ) ? $add_video : 'video';

				$vid_added = 0;

				if ( ! empty( $mt_og[ 'og:video' ] ) ) {

					if ( $wpsso->debug->enabled ) {

						$wpsso->debug->log( 'adding videos to json data "' . $vid_prop . '" property' );
					}

					$vid_added = self::add_videos_data_mt( $json_data[ $vid_prop ], $mt_og[ 'og:video' ], 'og:video' );
				}

				if ( $wpsso->debug->enabled ) {

					$wpsso->debug->log( $vid_added . ' videos added to "' . $vid_prop . '" property' );
				}

			} elseif ( $wpsso->debug->enabled ) {

				$wpsso->debug->log( 'skipping videos: add_video argument is false' );
			}

			/**
			 * Redefine mainEntityOfPage property for Attachment pages.
			 *
			 * If this is an attachment page, and the post mime_type is a known media type (image, video, or audio),
			 * then set the first media array element mainEntityOfPage to the page url, and set the page
			 * mainEntityOfPage property to false (so it doesn't get defined later).
			 */
			$main_prop = $mod[ 'is_attachment' ] ? preg_replace( '/\/.*$/', '', $mod[ 'post_mime' ] ) : '';

			$main_prop = apply_filters( 'wpsso_json_media_main_prop', $main_prop, $mod );

			if ( ! empty( $main_prop ) ) {

				if ( $wpsso->debug->enabled ) {

					$wpsso->debug->log( $mod[ 'name' ] . ' id ' . $mod[ 'id' ] . ' ' . $main_prop . ' property is main entity' );
				}

				if ( ! empty( $json_data[ $main_prop ] ) && is_array( $json_data[ $main_prop ] ) ) {

					reset( $json_data[ $main_prop ] );

					$media_key = key( $json_data[ $main_prop ] );	// Media array key should be '0'.

					if ( ! isset( $json_data[ $main_prop ][ $media_key ][ 'mainEntityOfPage' ] ) ) {

						if ( $wpsso->debug->enabled ) {

							$wpsso->debug->log( 'mainEntityOfPage for ' . $main_prop . ' key ' . $media_key . ' = ' . $mt_og[ 'og:url' ] );
						}

						$json_data[ $main_prop ][ $media_key ][ 'mainEntityOfPage' ] = $mt_og[ 'og:url' ];

					} elseif ( $wpsso->debug->enabled ) {

						$wpsso->debug->log( 'mainEntityOfPage for ' . $main_prop . ' key ' . $media_key . ' already defined' );
					}

					$json_data[ 'mainEntityOfPage' ] = false;
				}
			}
		}

		/**
		 * Called by the Blog, CollectionPage, ProfilePage, and SearchResultsPage filters.
		 *
		 * Example:
		 *
		 *	$prop_type_ids = array( 'mentions' => false )
		 *
		 *	$prop_type_ids = array( 'blogPosting' => 'blog.posting' )
		 *
		 * The 6th argument used to be $posts_per_page (now $prop_type_ids).
		 * The 7th argument used to be $prop_type_ids (now $deprecated).
		 *
		 * Do not cast $prop_type_ids as an array to allow for backwards compatibility.
		 */
		public static function add_posts_data( &$json_data, array $mod, array $mt_og, $page_type_id, $is_main, $prop_type_ids, $deprecated = null ) {

			static $added_page_type_ids = array();

			$wpsso =& Wpsso::get_instance();

			if ( $wpsso->debug->enabled ) {

				$wpsso->debug->mark();
			}

			$added_count = 0;	// Initialize the total posts added counter.

			/**
			 * The 6th argument used to be $posts_per_page (now $prop_type_ids) and 7th argument $prop_type_ids (now
			 * $deprecated).
			 */
			if ( ! is_array( $prop_type_ids ) && is_array( $deprecated ) ) {

				$prop_type_ids = $deprecated;

				$deprecated = null;
			}

			/**
			 * Sanity checks.
			 */
			if ( empty( $page_type_id ) ) {

				if ( $wpsso->debug->enabled ) {

					$wpsso->debug->log( 'exiting early: page_type_id is empty' );
				}

				return $added_count;

			} elseif ( empty( $prop_type_ids ) ) {

				if ( $wpsso->debug->enabled ) {

					$wpsso->debug->log( 'exiting early: prop_type_ids is empty' );
				}

				return $added_count;
			}

			/**
			 * Prevent recursion - i.e. webpage.collection in webpage.collection, etc.
			 */
			if ( isset( $added_page_type_ids[ $page_type_id ] ) ) {

				if ( $wpsso->debug->enabled ) {

					$wpsso->debug->log( 'exiting early: preventing recursion of page_type_id ' . $page_type_id );
				}

				return $added_count;

			} else {

				$added_page_type_ids[ $page_type_id ] = true;
			}

			/**
			 * Begin timer.
			 */
			if ( $wpsso->debug->enabled ) {

				$wpsso->debug->mark( 'adding posts data' );	// Begin timer.
			}

			$page_posts_mods = $wpsso->page->get_posts_mods( $mod );

			if ( empty( $page_posts_mods ) ) {

				if ( $wpsso->debug->enabled ) {

					$wpsso->debug->log( 'exiting early: page_posts_mods array is empty' );

					$wpsso->debug->mark( 'adding posts data' );	// End timer.
				}

				return $added_count;
			}

			if ( $wpsso->debug->enabled ) {

				$wpsso->debug->log( 'page_posts_mods array has ' . count( $page_posts_mods ) . ' elements' );
			}

			/**
			 * Set the Schema properties.
			 */
			foreach ( $prop_type_ids as $prop_name => $type_ids ) {

				if ( empty( $type_ids ) ) {		// False or empty array - allow any schema type.

					if ( $wpsso->debug->enabled ) {

						$wpsso->debug->log( 'any schema type is allowed for prop_name ' . $prop_name );
					}

					$type_ids = array( 'any' );

				} elseif ( is_string( $type_ids ) ) {	// Convert value to an array.

					if ( $wpsso->debug->enabled ) {

						$wpsso->debug->log( 'only schema type ' . $type_ids . ' allowed for prop_name ' . $prop_name );
					}

					$type_ids = array( $type_ids );

				} elseif ( ! is_array( $type_ids ) ) {

					if ( $wpsso->debug->enabled ) {

						$wpsso->debug->log( 'skipping prop_name ' . $prop_name . ': value must be false, string, or array of schema types' );
					}

					continue;
				}

				if ( empty( $json_data[ $prop_name ] ) ) {

					$json_data[ $prop_name ] = array();

				} elseif ( ! is_array( $json_data[ $prop_name ] ) ) {	// Convert single value to an array.

					$json_data[ $prop_name ] = array( $json_data[ $prop_name ] );
				}

				$prop_count = count( $json_data[ $prop_name ] );	// Initialize the posts per property name counter.

				foreach ( $page_posts_mods as $post_mod ) {

					$post_type_id = $wpsso->schema->get_mod_schema_type_id( $post_mod );

					$add_post_data = false;

					foreach ( $type_ids as $family_member_id ) {

						if ( $family_member_id === 'any' ) {

							if ( $wpsso->debug->enabled ) {

								$wpsso->debug->log( 'accepting post ID ' . $post_mod[ 'id' ] . ': any schema type is allowed' );
							}

							$add_post_data = true;

							break;	// One positive match is enough.
						}

						if ( $wpsso->debug->enabled ) {

							$wpsso->debug->log( 'checking if schema type ' . $post_type_id . ' is child of ' . $family_member_id );
						}

						$mod_is_child = $wpsso->schema->is_schema_type_child( $post_type_id, $family_member_id );

						if ( $mod_is_child ) {

							if ( $wpsso->debug->enabled ) {

								$wpsso->debug->log( 'accepting post ID ' . $post_mod[ 'id' ] . ': ' .
									$post_type_id . ' is child of ' . $family_member_id );
							}

							$add_post_data = true;

							break;	// One positive match is enough.

						} elseif ( $wpsso->debug->enabled ) {

							$wpsso->debug->log( 'post ID ' . $post_mod[ 'id' ] . ' schema type ' .
								$post_type_id . ' not a child of ' . $family_member_id );
						}
					}

					if ( ! $add_post_data ) {

						if ( $wpsso->debug->enabled ) {

							$wpsso->debug->log( 'skipping post ID ' . $post_mod[ 'id' ] . ' for prop_name ' . $prop_name );
						}

						continue;
					}

					if ( $wpsso->debug->enabled ) {

						$wpsso->debug->log( 'getting single mod data for post ID ' . $post_mod[ 'id' ] );
					}

					$post_json_data = $wpsso->schema->get_mod_json_data( $post_mod );

					if ( empty( $post_json_data ) ) {	// Prevent null assignment.

						$wpsso->debug->log( 'single mod data for post ID ' . $post_mod[ 'id' ] . ' is empty' );

						continue;	// Get the next post mod.
					}

					$added_count++;

					$prop_count++;

					if ( $wpsso->debug->enabled ) {

						$wpsso->debug->log( 'adding post ID ' . $post_mod[ 'id' ] . ' to ' . $prop_name . ' as #' . $prop_count );
					}

					$json_data[ $prop_name ][] = $post_json_data;	// Add the post data.
				}

				$filter_name = SucomUtil::sanitize_hookname( 'wpsso_json_prop_https_schema_org_' . $prop_name );

				if ( $wpsso->debug->enabled ) {

					$wpsso->debug->log( 'applying ' . $filter_name . ' filters' );
				}

				$json_data[ $prop_name ] = (array) apply_filters( $filter_name, $json_data[ $prop_name ], $mod, $mt_og, $page_type_id, $is_main );
			}

			unset( $added_page_type_ids[ $page_type_id ] );

			/**
			 * End timer.
			 */
			if ( $wpsso->debug->enabled ) {

				$wpsso->debug->mark( 'adding posts data' );	// End timer.
			}

			return $added_count;
		}

		/**
		 * Provide a single or two-dimension video array in $mt_videos.
		 */
		public static function add_videos_data_mt( &$json_data, $mt_videos, $media_pre = 'og:video' ) {

			$videos_added = 0;

			if ( isset( $mt_videos[ 0 ] ) && is_array( $mt_videos[ 0 ] ) ) {	// 2 dimensional array.

				foreach ( $mt_videos as $mt_single_video ) {

					$videos_added += WpssoSchemaSingle::add_video_data_mt( $json_data, $mt_single_video, $media_pre, $list_element = true );
				}

			} elseif ( is_array( $mt_videos ) ) {

				$videos_added += WpssoSchemaSingle::add_video_data_mt( $json_data, $mt_videos, $media_pre, $list_element = true );
			}

			return $videos_added;	// return count of videos added
		}

		public static function add_person_names_data( &$json_data, $prop_name = '', array $assoc, $key_name = '' ) {

			if ( ! empty( $prop_name ) && ! empty( $key_name ) ) {

				foreach ( SucomUtil::preg_grep_keys( '/^' . $key_name .'_[0-9]+$/', $assoc ) as $value ) {

					if ( ! empty( $value ) ) {

						$json_data[ $prop_name ][] = self::get_schema_type_context( 'https://schema.org/Person', array(
							'name' => $value,
						) );
					}
				}
			}
		}

		/**
		 * Modifies the $json_data directly (by reference) and does not return a value.
		 *
		 * Do not type-cast the $json_data argument as it may be false or an array.
		 */
		public static function organization_to_localbusiness( &$json_data ) {

			if ( ! is_array( $json_data ) ) {	// Just in case.

				return;
			}

			$wpsso =& Wpsso::get_instance();

			if ( $wpsso->debug->enabled ) {

				$wpsso->debug->mark();
			}

			/**
			 * Promote all location information up.
			 */
			if ( isset( $json_data[ 'location' ] ) ) {

				if ( $wpsso->debug->enabled ) {

					$wpsso->debug->log( 'promoting location property array' );
				}

				$prop_added = self::add_data_itemprop_from_assoc( $json_data, $json_data[ 'location' ],
					array_keys( $json_data[ 'location' ] ), $overwrite = false );

				if ( $wpsso->debug->enabled ) {

					$wpsso->debug->log( 'promoted ' . $prop_added . ' location keys' );
				}

				if ( $wpsso->debug->enabled ) {

					$wpsso->debug->log( 'removing the location property' );
				}

				unset( $json_data[ 'location' ] );

			} elseif ( $wpsso->debug->enabled ) {

				$wpsso->debug->log( 'no location property to promote' );
			}

			/**
			 * Google requires a local business to have an image.
			 *
			 * Check last as the location may have had an image that was promoted.
			 */
			if ( isset( $json_data[ 'logo' ] ) && empty( $json_data[ 'image' ] ) ) {

				if ( $wpsso->debug->enabled ) {

					$wpsso->debug->log( 'adding logo from organization markup' );
				}

				$json_data[ 'image' ][] = $json_data[ 'logo' ];

			} elseif ( $wpsso->debug->enabled ) {

				$wpsso->debug->log( 'logo is missing from organization markup' );
			}
		}

		/**
		 * Return any third-party and custom post options for a given option type.
		 * 
		 * function wpsso_get_post_event_options( $post_id, $event_id = false ) {
		 *
		 * 	WpssoSchema::get_post_type_options( $post_id, $type = 'event', $event_id );
		 * }
		 */
		public static function get_post_type_options( $post_id, $type, $type_id = false ) {

			$wpsso =& Wpsso::get_instance();

			if ( $wpsso->debug->enabled ) {

				$wpsso->debug->mark();
			}

			if ( empty( $post_id ) ) {		// Just in case.

				return false;

			} elseif ( empty( $type ) ) {	// Just in case.

				return false;

			} elseif ( ! empty( $wpsso->post ) ) {	// Just in case.

				$mod = $wpsso->post->get_mod( $post_id );

			} else {

				return false;
			}

			$type_opts = apply_filters( 'wpsso_get_' . $type . '_options', false, $mod, $type_id );

			if ( ! empty( $type_opts ) ) {

				if ( $wpsso->debug->enabled ) {

					$wpsso->debug->log_arr( 'get_' . $type . '_options filters returned', $type_opts );
				}
			}

			/**
			 * Add metadata defaults and custom values to the $type_opts array.
			 *
			 * $type_opts can be false, an empty array, or an array of one or more options.
			 */
			SucomUtil::add_type_opts_md_pad( $type_opts, $mod, array( $type => 'schema_' . $type ) );

			return $type_opts;
		}

		/**
		 * Get dates from the meta data options and add ISO formatted dates to the array (passed by reference).
		 */
		public static function add_mod_opts_date_iso( array $mod, &$opts, array $opts_md_pre ) {

			$wpsso =& Wpsso::get_instance();

			if ( $wpsso->debug->enabled ) {

				$wpsso->debug->mark();
			}

			foreach ( $opts_md_pre as $opt_pre => $md_pre ) {

				$date_iso = self::get_mod_date_iso( $mod, $md_pre );

				if ( ! is_array( $opts ) ) {	// Just in case.

					$opts = array();
				}

				$opts[ $opt_pre . '_iso' ] = $date_iso;
			}
		}

		public static function get_mod_date_iso( array $mod, $md_pre ) {

			$wpsso =& Wpsso::get_instance();

			if ( $wpsso->debug->enabled ) {

				$wpsso->debug->mark();
			}

			if ( ! is_string( $md_pre ) ) {	// Just in case.

				return '';
			}

			$md_opts = $mod[ 'obj' ]->get_options( $mod[ 'id' ] );

			return self::get_opts_date_iso( $md_opts, $md_pre );
		}

		public static function get_opts_date_iso( array $opts, $md_pre ) {

			$wpsso =& Wpsso::get_instance();

			if ( $wpsso->debug->enabled ) {

				$wpsso->debug->mark();
			}

			if ( ! is_string( $md_pre ) ) {	// Just in case.

				return '';
			}

			$md_date     = empty( $opts[ $md_pre . '_date' ] ) || 'none' === $opts[ $md_pre . '_date' ] ? '' : $opts[ $md_pre . '_date' ];
			$md_time     = empty( $opts[ $md_pre . '_time' ] ) || 'none' === $opts[ $md_pre . '_time' ] ? '' : $opts[ $md_pre . '_time' ];
			$md_timezone = empty( $opts[ $md_pre . '_timezone' ] ) || 'none' === $opts[ $md_pre . '_timezone' ] ? '' : $opts[ $md_pre . '_timezone' ];

			if ( empty( $md_date ) && empty( $md_time ) ) {		// No date or time.

				if ( $wpsso->debug->enabled ) {

					$wpsso->debug->log( 'exiting early: ' . $md_pre . ' date and time are empty' );
				}

				return '';	// Nothing to do.
			}

			if ( ! empty( $md_date ) && empty( $md_time ) ) {	// Date with no time.

				$md_time = '00:00';

				if ( $wpsso->debug->enabled ) {

					$wpsso->debug->log( $md_pre . ' time is empty: using time ' . $md_time );
				}

			}

			if ( empty( $md_date ) && ! empty( $md_time ) ) {	// Time with no date.

				$md_date = gmdate( 'Y-m-d', time() );

				if ( $wpsso->debug->enabled ) {

					$wpsso->debug->log( $md_pre . ' date is empty: using date ' . $md_date );
				}
			}

			if ( empty( $md_timezone ) ) {				// No timezone.

				$md_timezone = get_option( 'timezone_string' );

				if ( $wpsso->debug->enabled ) {

					$wpsso->debug->log( $md_pre . ' timezone is empty: using timezone ' . $md_timezone );
				}
			}

			$date_obj = date_create( $md_date . ' ' . $md_time . ' ' . $md_timezone );

			return date_format( $date_obj, 'c' );
		}

		/**
		 * Example $names array:
		 *
		 * array(
		 * 	'prepTime'  => 'schema_recipe_prep',
		 * 	'cookTime'  => 'schema_recipe_cook',
		 * 	'totalTime' => 'schema_recipe_total',
		 * );
		 */
		public static function add_data_time_from_assoc( array &$json_data, array $assoc, array $names ) {

			foreach ( $names as $prop_name => $key_name ) {

				$t = array();

				foreach ( array( 'days', 'hours', 'mins', 'secs' ) as $time_incr ) {
					$t[ $time_incr ] = empty( $assoc[ $key_name . '_' . $time_incr ] ) ?	// 0 or empty string.
						0 : (int) $assoc[ $key_name . '_' . $time_incr ];		// Define as 0 by default.
				}

				if ( $t[ 'days' ] . $t[ 'hours' ] . $t[ 'mins' ] . $t[ 'secs' ] > 0 ) {

					$json_data[ $prop_name ] = 'P' . $t[ 'days' ] . 'DT' . $t[ 'hours' ] . 'H' . $t[ 'mins' ] . 'M' . $t[ 'secs' ] . 'S';
				}
			}
		}

		/**
		 * QuantitativeValue (width, height, length, depth, weight).
		 *
		 * unitCodes from http://wiki.goodrelations-vocabulary.org/Documentation/UN/CEFACT_Common_Codes.
		 *
		 * Example $names array:
		 *
		 * array(
		 * 	'depth'        => 'product:depth:value',
		 * 	'fluid_volume' => 'product:fluid_volume:value',
		 * 	'height'       => 'product:height:value',
		 * 	'length'       => 'product:length:value',
		 * 	'weight'       => 'product:weight:value',
		 * 	'width'        => 'product:width:value',
		 * );
		 */
		public static function get_data_unit_from_assoc( array $assoc, array $names ) {

			$json_data = array();

			self::add_data_unit_from_assoc( $json_data, $assoc, $names );

			return empty( $json_data ) ? false : $json_data;
		}

		public static function add_data_unit_from_assoc( array &$json_data, array $assoc, array $names ) {

			$wpsso =& Wpsso::get_instance();

			if ( $wpsso->debug->enabled ) {

				$wpsso->debug->mark();
			}

			if ( null === self::$units_cache ) {

				self::$units_cache = apply_filters( 'wpsso_schema_units', $wpsso->cf[ 'head' ][ 'schema_units' ] );
			}

			if ( ! is_array( self::$units_cache ) ) {	// Just in case.

				return;
			}

			foreach ( $names as $key => $key_name ) {

				/**
				 * Make sure the property name we need (width, height, weight, etc.) is configured.
				 */
				if ( empty( self::$units_cache[ $key ] ) || ! is_array( self::$units_cache[ $key ] ) ) {

					continue;
				}

				/**
				 * Exclude empty string values.
				 */
				if ( ! isset( $assoc[ $key_name ] ) || $assoc[ $key_name ] === '' ) {

					continue;
				}

				/**
				 * Example array:
				 *
				 *	self::$units_cache[ 'depth' ] = array(
				 *		'depth' => array(
				 *			'@context' => 'https://schema.org',
				 *			'@type'    => 'QuantitativeValue',
				 *			'name'     => 'Depth',
				 *			'unitText' => 'cm',
				 *			'unitCode' => 'CMT',
				 *		),
				 *	),
				 */
				foreach ( self::$units_cache[ $key ] as $prop_name => $prop_data ) {

					$quant_id = 'qv-' . $key . '-' . $assoc[ $key_name ];	// Example '@id' = '#sso/qv-width-px-1200'.

					self::update_data_id( $prop_data, $quant_id, '/' );

					$prop_data[ 'value' ] = $assoc[ $key_name ];

					$json_data[ $prop_name ][] = $prop_data;
				}
			}
		}

		/**
		 * Returns a https://schema.org/unitText value (for example, 'cm', 'ml', 'kg', etc.).
		 */
		public static function get_data_unit_text( $key ) {

			$wpsso =& Wpsso::get_instance();

			if ( $wpsso->debug->enabled ) {

				$wpsso->debug->mark();
			}

			static $local_cache = array();

			if ( isset( $local_cache[ $key ] ) ) {

				return $local_cache[ $key ];
			}

			if ( null === self::$units_cache ) {

				self::$units_cache = apply_filters( 'wpsso_schema_units', $wpsso->cf[ 'head' ][ 'schema_units' ] );
			}

			if ( empty( self::$units_cache[ $key ] ) || ! is_array( self::$units_cache[ $key ] ) ) {

				return $local_cache[ $key ] = '';
			}

			/**
			 * Example array:
			 *
			 *	self::$units_cache[ 'depth' ] = array(
			 *		'depth' => array(
			 *			'@context' => 'https://schema.org',
			 *			'@type'    => 'QuantitativeValue',
			 *			'name'     => 'Depth',
			 *			'unitText' => 'cm',
			 *			'unitCode' => 'CMT',
			 *		),
			 *	),
			 */
			foreach ( self::$units_cache[ $key ] as $prop_name => $prop_data ) {

				if ( isset( $prop_data[ 'unitText' ] ) ) {	// Return the first match.

					return $local_cache[ $key ] = $prop_data[ 'unitText' ];
				}
			}

			return $local_cache[ $key ] = '';
		}

		/**
		 * Returns the number of Schema properties added to $json_data.
		 *
		 * Example usage:
		 *
		 *	WpssoSchema::add_data_itemprop_from_assoc( $json_ret, $mt_og, array(
		 *		'datePublished' => 'article:published_time',
		 *		'dateModified'  => 'article:modified_time',
		 *	) );
		 *
		 *	WpssoSchema::add_data_itemprop_from_assoc( $json_ret, $org_opts, array(
		 *		'url'           => 'org_url',
		 *		'name'          => 'org_name',
		 *		'alternateName' => 'org_name_alt',
		 *		'description'   => 'org_desc',
		 *		'email'         => 'org_email',
		 *		'telephone'     => 'org_phone',
		 *	) );
		 *
		 */
		public static function add_data_itemprop_from_assoc( array &$json_data, array $assoc, array $names, $overwrite = true ) {

			$wpsso =& Wpsso::get_instance();

			if ( $wpsso->debug->enabled ) {

				$wpsso->debug->mark();
			}

			$is_assoc = SucomUtil::is_assoc( $names );

			$prop_added = 0;

			foreach ( $names as $prop_name => $key_name ) {

				if ( ! $is_assoc ) {

					$prop_name = $key_name;
				}

				if ( self::is_valid_key( $assoc, $key_name ) ) {	// Not null, an empty string, or 'none'.

					if ( isset( $json_data[ $prop_name ] ) && empty( $overwrite ) ) {

						if ( $wpsso->debug->enabled ) {

							$wpsso->debug->log( 'skipping ' . $prop_name . ': itemprop exists and overwrite is false' );
						}

						continue;

					}

					if ( is_string( $assoc[ $key_name ] ) && false !== filter_var( $assoc[ $key_name ], FILTER_VALIDATE_URL ) ) {

						$json_data[ $prop_name ] = SucomUtil::esc_url_encode( $assoc[ $key_name ] );

					} else {

						$json_data[ $prop_name ] = $assoc[ $key_name ];
					}

					if ( $wpsso->debug->enabled ) {

						$wpsso->debug->log( 'assigned ' . $key_name . ' value to itemprop ' . $prop_name . ' = ' . 
							print_r( $json_data[ $prop_name ], true ) );
					}

					$prop_added++;
				}
			}

			return $prop_added;
		}

		/**
		 * Since WPSSO Core v8.0.0.
		 *
		 * Checks both the array key and its value. The array key must exist, and its value cannot be null, an empty
		 * string, the 'none' string, and if the key is a width or height, the value cannot be -1.
		 */
		public static function is_valid_key( $assoc, $key ) {

			if ( ! isset( $assoc[ $key ] ) ) {

				return false;

			} elseif ( ! self::is_valid_val( $assoc[ $key ] ) ) {	// Not null, an empty string, or 'none'.

				return false;

			} elseif ( 'width' === $key || 'height' === $key ) {

				if ( WPSSO_UNDEF === $assoc[ $key ] ) {	// Invalid width or height.

					return false;
				}
			}

			return true;
		}

		/**
		 * Since WPSSO Core v8.0.0.
		 *
		 * The value cannot be null, an empty string, or the 'none' string.
		 */
		public static function is_valid_val( $val ) {

			if ( null === $val ) {	// Null value is not valid.

				return false;

			} elseif ( '' === $val ) {	// Empty string.

				return false;

			} elseif ( 'none' === $val ) {	// Disabled option.

				return false;
			}

			return true;
		}

		/**
		 * Since WPSSO Core v7.7.0.
		 */
		public static function move_data_itemprop_from_assoc( array &$json_data, array &$assoc, array $names, $overwrite = true ) {

			$prop_added = self::add_data_itemprop_from_assoc( $json_data, $assoc, $names, $overwrite );

			foreach ( $names as $prop_name => $key_name ) {

				unset( $assoc[ $key_name ] );
			}

			return $prop_added;
		}

		/**
		 * Example usage:
		 *
		 *	$offer = WpssoSchema::get_data_itemprop_from_assoc( $mt_offer, array( 
		 *		'url'             => 'product:url',
		 *		'name'            => 'product:title',
		 *		'description'     => 'product:description',
		 *		'mpn'             => 'product:mfr_part_no',
		 *		'availability'    => 'product:availability',
		 *		'itemCondition'   => 'product:condition',
		 *		'price'           => 'product:price:amount',
		 *		'priceCurrency'   => 'product:price:currency',
		 *		'priceValidUntil' => 'product:sale_price_dates:end',
		 *	) );
		 */
		public static function get_data_itemprop_from_assoc( array $assoc, array $names, $exclude = array( '' ) ) {

			$wpsso =& Wpsso::get_instance();

			if ( $wpsso->debug->enabled ) {

				$wpsso->debug->mark();
			}

			$json_data = array();

			foreach ( $names as $prop_name => $key_name ) {

				if ( isset( $assoc[ $key_name ] ) && ! in_array( $assoc[ $key_name ], $exclude, $strict = true ) ) {

					$json_data[ $prop_name ] = $assoc[ $key_name ];

					if ( $wpsso->debug->enabled ) {

						$wpsso->debug->log( 'assigned ' . $key_name . ' value to itemprop ' . 
							$prop_name . ' = ' . print_r( $json_data[ $prop_name ], true ) );
					}
				}
			}

			return empty( $json_data ) ? false : $json_data;
		}

		public static function check_required_props( &$json_data, array $mod, $prop_names = array( 'image' ) ) {

			$wpsso =& Wpsso::get_instance();

			/**
			 * Check only published posts or other non-post objects.
			 */
			if ( ( $mod[ 'is_post' ] && 'publish' === $mod[ 'post_status' ] ) || ( ! $mod[ 'is_post' ] && $mod[ 'id' ] ) ) {

				$ref_url = $wpsso->util->maybe_set_ref( null, $mod, __( 'checking schema properties', 'wpsso' ) );

				foreach ( $prop_names as $prop_name ) {

					if ( empty( $json_data[ $prop_name ] ) ) {

						if ( $wpsso->debug->enabled ) {

							$wpsso->debug->log( $prop_name . ' property value is empty and required' );
						}

						/**
						 * An is_admin() test is required to make sure the WpssoMessages class is available.
						 */
						if ( $wpsso->notice->is_admin_pre_notices() ) {

							$notice_key = $mod[ 'name' ] . '-' . $mod[ 'id' ] . '-notice-missing-schema-' . $prop_name;

							$error_msg = $wpsso->msgs->get( 'notice-missing-schema-' . $prop_name );

							$wpsso->notice->err( $error_msg, null, $notice_key );
						}
					}
				}

				$wpsso->util->maybe_unset_ref( $ref_url );
			}
		}

		/**
		 * Convert a numeric category ID to its Google product type string.
		 */
		public static function check_prop_value_category( &$json_data, $prop_name = 'category' ) {

			$wpsso =& Wpsso::get_instance();

			if ( $wpsso->debug->enabled ) {

				$wpsso->debug->log( 'checking category property value' );
			}

			if ( ! empty( $json_data[ $prop_name ] ) ) {

				/**
				 * Numeric category IDs are expected to be Google product type id.
				 *
				 * See https://www.google.com/basepages/producttype/taxonomy-with-ids.en-US.txt.
				 */
				if ( is_numeric( $json_data[ $prop_name ] ) ) {

					$cat_id = $json_data[ $prop_name ];

					$categories = $wpsso->util->get_google_product_categories();

					if ( isset( $categories[ $cat_id ] ) ) {

						$json_data[ $prop_name ] = $categories[ $cat_id ];

					} else {

						unset( $json_data[ $prop_name ] );
					}
				}
			}
		}

		/**
		 * If we have a GTIN number, try to improve the assigned property name.
		 * 
		 * Pass $json_data by reference to modify the array directly.
		 *
		 * A similar method exists as WpssoOpenGraph::check_mt_value_gtin().
		 */
		public static function check_prop_value_gtin( &$json_data, $prop_name = 'gtin' ) {

			$wpsso =& Wpsso::get_instance();

			if ( $wpsso->debug->enabled ) {

				$wpsso->debug->log( 'checking ' . $prop_name . ' property value' );
			}

			if ( ! empty( $json_data[ $prop_name ] ) ) {

				/**
				 * The value may come from a custom field, so trim it, just in case.
				 */
				$json_data[ $prop_name ] = trim( $json_data[ $prop_name ] );

				$gtin_len = strlen( $json_data[ $prop_name ] );

				switch ( $gtin_len ) {

					case 14:
					case 13:
					case 12:
					case 8:

						if ( empty( $json_data[ $prop_name . $gtin_len ] ) ) {

							$json_data[ $prop_name . $gtin_len ] = $json_data[ $prop_name ];
						}

						break;
				}
			}
		}

		/**
		 * Sanitize the sameAs array - make sure URLs are valid and remove any duplicates.
		 */
		public static function check_prop_value_sameas( &$json_data, $prop_name = 'sameAs' ) {

			$wpsso =& Wpsso::get_instance();

			if ( $wpsso->debug->enabled ) {

				$wpsso->debug->log( 'checking ' . $prop_name . ' property value' );
			}

			if ( ! empty( $json_data[ $prop_name ] ) ) {

				if ( ! is_array( $json_data[ $prop_name ] ) ) {	// Just in case.

					$json_data[ $prop_name ] = array( $json_data[ $prop_name ] );
				}

				$added_urls = array();

				foreach ( $json_data[ $prop_name ] as $num => $url ) {

					if ( empty( $url ) ) {

						if ( $wpsso->debug->enabled ) {

							$wpsso->debug->log( 'skipping ' . $prop_name . ' url #' . $num . ': value is empty' );
						}

					} elseif ( isset( $json_data[ 'url' ] ) && $json_data[ 'url' ] === $url ) {

						if ( $wpsso->debug->enabled ) {

							$wpsso->debug->log( 'skipping ' . $prop_name . ' url #' . $num . ': value is "url" property (' . $url . ')' );
						}

					} elseif ( isset( $added_urls[ $url ] ) ) {	// Already added.

						if ( $wpsso->debug->enabled ) {

							$wpsso->debug->log( 'skipping ' . $prop_name . ' url #' . $num . ': value already added (' . $url . ')' );
						}

					} elseif ( false === filter_var( $url, FILTER_VALIDATE_URL ) ) {

						if ( $wpsso->debug->enabled ) {

							$wpsso->debug->log( 'skipping ' . $prop_name . ' url #' . $num . ': value is not valid (' . $url . ')' );
						}

					} else {	// Mark the url as already added and get the next url.

						$added_urls[ $url ] = true;

						continue;	// Get the next url.
					}

					unset( $json_data[ $prop_name ][ $num ] );	// Remove the duplicate / invalid url.
				}

				$json_data[ $prop_name ] = array_values( $json_data[ $prop_name ] );	// Reindex / renumber the array.
			}
		}

		/**
		 * Example usage:
		 *
		 *	WpssoSchema::check_prop_value_enumeration( $offer, 'availability', 'item_availability' );
		 *
		 *	WpssoSchema::check_prop_value_enumeration( $offer, 'itemCondition', 'item_condition', 'Condition' );
		 */
		public static function check_prop_value_enumeration( &$json_data, $prop_name, $enum_key, $val_suffix = '' ) {

			$wpsso =& Wpsso::get_instance();

			if ( $wpsso->debug->enabled ) {

				$wpsso->debug->log( 'checking ' . $prop_name . ' property value' );
			}

			if ( empty( $json_data[ $prop_name ] ) ) {

				if ( $wpsso->debug->enabled ) {

					$wpsso->debug->log( $prop_name . ' property value is empty' );
				}

			} elseif ( 'none' === $json_data[ $prop_name ] ) {

				if ( $wpsso->debug->enabled ) {

					$wpsso->debug->log( $prop_name . ' property value is none' );
				}

			} elseif ( empty( $wpsso->cf[ 'form' ][ $enum_key ] ) ) {

				if ( $wpsso->debug->enabled ) {

					$wpsso->debug->log( $enum_key . ' enumeration key is unknown' );
				}

			} else {

				$enum_select = $wpsso->cf[ 'form' ][ $enum_key ];

				$prop_val = $json_data[ $prop_name ];

				if ( ! isset( $enum_select[ $prop_val ] ) ) {

					if ( isset( $enum_select[ 'https://schema.org/' . $prop_val ] ) ) {

						$json_data[ $prop_name ] = 'https://schema.org/' . $prop_val;

					} elseif ( $val_suffix && isset( $enum_select[ 'https://schema.org/' . $prop_val . $val_suffix ] ) ) {

						$json_data[ $prop_name ] = 'https://schema.org/' . $prop_val . $val_suffix;

					} else {

						if ( $wpsso->debug->enabled ) {

							$wpsso->debug->log( 'invalid ' . $prop_name . ' property value "' . $prop_val . '"' );
						}

						unset( $json_data[ $prop_name ] );
					}
				}
			}
		}

		/**
		 * Returns false on error.
		 *
		 * $type_id can be a string, or an array.
		 */
		public static function update_data_id( &$json_data, $type_id, $data_url = null, $hash_url = false ) {

			$wpsso =& Wpsso::get_instance();

			if ( $wpsso->debug->enabled ) {

				$wpsso->debug->mark();

				$wpsso->debug->log_args( array( 
					'type_id'  => $type_id,
					'data_url' => $data_url,
					'hash_url' => $hash_url,
				) );
			}

			static $id_anchor = null;
			static $id_delim  = null;

			if ( null === $id_anchor || null === $id_delim ) {	// Optimize and call just once.

				$id_anchor = self::get_id_anchor();
				$id_delim  = self::get_id_delim();
			}

			if ( is_array( $type_id ) ) {

				$type_id = implode( $id_delim, $type_id );
			}

			$type_id = rtrim( $type_id, $id_delim );	// Just in case.

			if ( empty( $type_id ) ) {

				if ( $wpsso->debug->enabled ) {

					$wpsso->debug->log( 'exiting early: $type_id value is empty and required' );
				}

				return false;
			}

			if ( $wpsso->debug->enabled ) {

				if ( empty( $json_data[ '@id' ] ) ) {

					$wpsso->debug->log( 'input @id property is empty' );

				} else {

					$wpsso->debug->log( 'input @id property is ' . $json_data[ '@id' ] );
				}
			}

			/**
			 * If $type_id is a URL, then use it as-is.
			 */
			if ( false !== filter_var( $type_id, FILTER_VALIDATE_URL ) ) {

				if ( $wpsso->debug->enabled ) {

					$wpsso->debug->log( 'provided type_id is a valid url' );
				}

				unset( $json_data[ '@id' ] );	// Just in case.

				$json_data = array( '@id' => $type_id ) + $json_data;		// Make @id the first value in the array.

			} elseif ( null === $data_url && empty( $json_data[ 'url' ] ) ) {

				if ( $wpsso->debug->enabled ) {

					$wpsso->debug->log( 'exiting early: data_url and json_data url are empty' );
				}

				return false;

			} else {

				$id_url = '';

				if ( ! empty( $data_url ) ) {

					if ( is_string( $data_url ) ) {	// Just in case.

						$id_url = $data_url;
					}

				} elseif ( ! empty( $json_data[ '@id' ] ) ) {

					$id_url = $json_data[ '@id' ];

				} elseif ( ! empty( $json_data[ 'url' ] ) ) {

					$id_url = $json_data[ 'url' ];
				}

				/**
				 * Maybe remove an anchor ID from the begining of the type id string.
				 */
				if ( 0 === strpos( $type_id, $id_anchor ) ) {

					$type_id = substr( $type_id, strlen( $id_anchor ) - 1 );
				}

				/**
				 * Standardize the $type_id string.
				 */
				$type_id = preg_replace( '/[-_\. ]+/', '-', $type_id );

				/**
				 * Check if we already have an anchor ID in the URL.
				 */
				if ( false === strpos( $id_url, $id_anchor ) ) {

					$id_url .= $id_anchor;
				}

				/**
				 * Check if we already have the type id in the URL.
				 */
				if ( false === strpos( $id_url, $id_anchor . $type_id ) ) {

					$id_url = trim( $id_url, $id_delim ) . $id_delim . $type_id;
				}

				unset( $json_data[ '@id' ] );	// Just in case.

				$json_data = array( '@id' => $id_url ) + $json_data;	// Make @id the first value in the array.
			}

			/**
			 * Possibly hash the '@id' URL to hide a WordPress login username (as one example). Since Google reads the
			 * '@id' value as a URL, use a leading slash to create the same path for the same '@id' URLs between
			 * different Schema JSON-LD scripts (ie. not relative to the current webpage). For example:
			 *
			 *	"@id": "http://adm.surniaulula.com/author/manovotny/#sso/person"
			 *	"@id": "/06d3730efc83058f497d3d44f2f364e3#sso/person"
			 */
			if ( $hash_url ) {

				if ( preg_match( '/^(.*:\/\/.*)(' . preg_quote( $id_anchor, '/' ) . '.*)?$/U', $json_data[ '@id' ], $matches ) ) {

					$md5_url = '/' . md5( $matches[ 1 ] ) . $matches[ 2 ];

					$json_data[ '@id' ] = str_replace( $matches[ 0 ], $md5_url, $json_data[ '@id' ] );
				}
			}

			if ( $wpsso->debug->enabled ) {

				$wpsso->debug->log( 'returned @id property is ' . $json_data[ '@id' ] );
			}

			return true;
		}

		/**
		 * Sanitation used by filters to return their data.
		 */
		public static function return_data_from_filter( $json_data, $merge_data, $is_main = false ) {

			if ( ! $is_main || ! empty( $merge_data[ 'mainEntity' ] ) ) {

				unset( $json_data[ 'mainEntity' ] );
				unset( $json_data[ 'mainEntityOfPage' ] );

			} else {

				if ( ! isset( $merge_data[ 'mainEntityOfPage' ] ) ) {

					if ( ! empty( $merge_data[ 'url' ] ) ) {

						/**
						 * Remove any URL fragment from the main entity URL. The 'mainEntityOfPage' value
						 * can be empty and will be removed by WpssoSchemaGraph::optimize_json().
						 */
						$merge_data[ 'mainEntityOfPage' ] = preg_replace( '/#.*$/', '', $merge_data[ 'url' ] );
					}
				}
			}

			if ( empty( $merge_data ) ) {	// Just in case - nothing to merge.

				return $json_data;

			} elseif ( null === $json_data ) {	// Just in case - nothing to merge.

				return $merge_data;

			} elseif ( is_array( $json_data ) ) {

				$json_head = array(
					'@id'              => null,
					'@context'         => null,
					'@type'            => null,
					'mainEntityOfPage' => null,
				);

				$json_data = array_merge( $json_head, $json_data, $merge_data );

				foreach ( $json_head as $prop_name => $prop_val ) {

					if ( empty( $json_data[ $prop_name ] ) ) {

						unset( $json_data[ $prop_name ] );
					}
				}
			}

			return $json_data;
		}

		public static function get_id_anchor() {

			return '#sso/';
		}

		public static function get_id_delim() {

			return '/';
		}

		/**
		 * Add cross-references for schema sub-type arrays that exist under more than one type.
		 *
		 * For example, Thing > Place > LocalBusiness also exists under Thing > Organization > LocalBusiness.
		 */
		private function add_schema_type_xrefs( &$schema_types ) {

			$thing =& $schema_types[ 'thing' ];	// Quick ref variable for the 'thing' array.

			/**
			 * Thing > Intangible > Enumeration.
			 */
			$thing[ 'intangible' ][ 'enumeration' ][ 'specialty' ][ 'medical.specialty' ] =&
				$thing[ 'intangible' ][ 'enumeration' ][ 'medical.enumeration' ][ 'medical.specialty' ];

			$thing[ 'intangible' ][ 'service' ][ 'service.financial.product' ][ 'payment.card' ] =&
				$thing[ 'intangible' ][ 'enumeration' ][ 'payment.method' ][ 'payment.card' ];

			/**
			 * Thing > Organization > Educational Organization.
			 */
			$thing[ 'organization' ][ 'educational.organization' ] =& $thing[ 'place' ][ 'civic.structure' ][ 'educational.organization' ];

			/**
			 * Thing > Organization > Local Business.
			 */
			$thing[ 'organization' ][ 'local.business' ] =& $thing[ 'place' ][ 'local.business' ];
		}
	}
}
