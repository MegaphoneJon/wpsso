<?php
/**
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2012-2021 Jean-Sebastien Morisset (https://wpsso.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {

	die( 'These aren\'t the droids you\'re looking for.' );
}

if ( ! defined( 'WPSSO_PLUGINDIR' ) ) {

	die( 'Do. Or do not. There is no try.' );
}

if ( ! class_exists( 'WpssoCompat' ) ) {

	/**
	 * Third-party plugin and theme compatibility actions and filters.
	 */
	class WpssoCompat {

		private $p;	// Wpsso class object.

		public function __construct( &$plugin ) {

			static $do_once = null;

			if ( true === $do_once ) {

				return;	// Stop here.
			}

			$do_once = true;

			$this->p =& $plugin;

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			$this->common_hooks();

			if ( is_admin() ) {

				$this->back_end_hooks();

			} else {

				$this->front_end_hooks();
			}
		}

		public function common_hooks() {

			if ( ! empty( $this->p->avail[ 'seo' ][ 'aioseop' ] ) ) {

				add_filter( 'aioseo_schema_disable', '__return_true', 10, 1 );
			}

			/**
			 * Perfect Images + Retina (aka WP Retina 2x).
			 */
			if ( ! empty( $this->p->avail[ 'media' ][ 'wp-retina-2x' ] ) ) {

				/**
				 * Filter for the get_option() and update_option() functions.
				 */
				add_filter( 'option_wr2x_retina_sizes', array( $this, 'update_wr2x_retina_sizes' ), 10, 1 );

				add_filter( 'pre_update_option_wr2x_retina_sizes', array( $this, 'update_wr2x_retina_sizes' ), 10, 1 );
			}

			/**
			 * Yoast SEO.
			 */
			if ( ! empty( $this->p->avail[ 'seo' ][ 'wpseo' ] ) ||
				! empty( $this->p->options[ 'plugin_wpseo_social_meta' ] ) ) {	// Import Yoast SEO Social Meta.

				$this->p->util->add_plugin_filters( $this, array( 
					'wpseo_replace_vars' => 2,
				) );
			}
		}

		public function back_end_hooks() {

			/**
			 * Gravity Forms and Gravity View.
			 */
			if ( class_exists( 'GFForms' ) ) {

				add_action( 'gform_noconflict_styles', array( $this, 'update_gform_noconflict_styles' ) );

				add_action( 'gform_noconflict_scripts', array( $this, 'update_gform_noconflict_scripts' ) );
			}

			if ( class_exists( 'GravityView_Plugin' ) ) {

				add_action( 'gravityview_noconflict_styles', array( $this, 'update_gform_noconflict_styles' ) );

				add_action( 'gravityview_noconflict_scripts', array( $this, 'update_gform_noconflict_scripts' ) );
			}

			/**
			 * Rank Math.
			 */
			if ( ! empty( $this->p->avail[ 'seo' ][ 'rank-math' ] ) ) {

				$this->p->util->add_plugin_filters( $this, array( 
					'admin_page_style_css_rank_math' => array(	// Class method.
						'admin_page_style_css' => 1,		// Filter name.
					),
				) );
			}

			/**
			 * The SEO Framework.
			 */
			if ( ! empty( $this->p->avail[ 'seo' ][ 'seoframework' ] ) ) {

				add_filter( 'the_seo_framework_inpost_settings_tabs', array( $this, 'cleanup_seoframework_tabs' ), 1000 );
			}

			/**
			 * SEOPress.
			 */
			if ( ! empty( $this->p->avail[ 'seo' ][ 'seopress' ] ) ) {

				add_filter( 'seopress_metabox_seo_tabs', array( $this, 'cleanup_seopress_tabs' ), 1000 );
			}

			/**
			 * Yoast SEO.
			 */
			if ( ! empty( $this->p->avail[ 'seo' ][ 'wpseo' ] ) ) {

				add_action( 'admin_init', array( $this, 'cleanup_wpseo_notifications' ), 15 );

				$this->p->util->add_plugin_filters( $this, array( 
					'admin_page_style_css_wpseo' => array(		// Class method.
						'admin_page_style_css' => 1,		// Filter name.
					),
				) );
			}
		}

		public function front_end_hooks() {

			/**
			 * JetPack.
			 */
			if ( ! empty( $this->p->avail[ 'util' ][ 'jetpack' ] ) ) {

				add_filter( 'jetpack_enable_opengraph', '__return_false', 1000 );

				add_filter( 'jetpack_enable_open_graph', '__return_false', 1000 );

				add_filter( 'jetpack_disable_twitter_cards', '__return_true', 1000 );
			}

			/**
			 * NextScripts: Social Networks Auto-Poster.
			 */
			if ( function_exists( 'nxs_initSNAP' ) ) {

				add_action( 'wp_head', array( $this, 'remove_snap_og_meta_tags_holder' ), -2000 );
			}

			/**
			 * Rank Math.
			 */
			if ( ! empty( $this->p->avail[ 'seo' ][ 'rank-math' ] ) ) {

				add_action( 'rank_math/head', array( $this, 'cleanup_rank_math_actions' ), -2000 );
			}

			/**
			 * SEOPress.
			 */
			if ( ! empty( $this->p->avail[ 'seo' ][ 'seopress' ] ) ) {

				add_filter( 'seopress_titles_author', '__return_empty_string', 1000 );
			}

			/**
			 * Yoast SEO.
			 */
			if ( ! empty( $this->p->avail[ 'seo' ][ 'wpseo' ] ) ) {

				/**
				 * Since Yoast SEO v14.0.
				 */
				if ( method_exists( 'Yoast\WP\SEO\Integrations\Front_End_Integration', 'get_presenters' ) ) {

					add_filter( 'wpseo_frontend_presenters', array( $this, 'cleanup_wpseo_frontend_presenters' ), 1000 );

				} else {

					add_action( 'template_redirect', array( $this, 'cleanup_wpseo_actions' ), 1000 );

					add_action( 'amp_post_template_head', array( $this, 'cleanup_wpseo_actions' ), -2000 );
				}
			}
		}

		/**
		 * Filter for the get_option() and update_option() functions.
		 *
		 * Prevent Perfect Images + Retina (aka WP Retina 2x) from creating 2x images for WPSSO image sizes.
		 */
		public function update_wr2x_retina_sizes( $mixed ) {

			if ( is_array( $mixed ) ) {

				foreach ( $mixed as $num => $size_name ) {

					if ( 0 === strpos( $size_name, 'wpsso-' ) ) {

						unset( $mixed[ $num ] );
					}
				}
			}

			return $mixed;
		}

		public function update_gform_noconflict_styles( $styles ) {

			return array_merge( $styles, array(
				'jquery-ui.js',
				'jquery-qtip.js',
				'sucom-admin-page',
				'sucom-metabox-tabs',
				'sucom-settings-page',
				'sucom-settings-table',
				'wp-color-picker',
			) );
		}

		public function update_gform_noconflict_scripts( $scripts ) {

			return array_merge( $scripts, array(
				'jquery-ui-datepicker',
				'jquery-qtip',
				'sucom-admin-media',
				'sucom-admin-page',
				'sucom-block-editor-admin',
				'sucom-metabox',
				'sucom-settings-page',
				'sucom-tooltips',
				'wp-color-picker',
			) );
		}

		public function cleanup_seoframework_tabs( $tabs ) {

			unset( $tabs[ 'social' ] );

			return $tabs;
		}

		public function cleanup_seopress_tabs( $tabs ) {

			unset( $tabs[ 'social-tab' ] );

			return $tabs;
		}

		/**
		 * Cleanup incorrect Yoast SEO notifications.
		 */
		public function cleanup_wpseo_notifications() {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			/**
			 * Yoast SEO only checks for a conflict with WPSSO if the Open Graph option is enabled.
			 */
			if ( method_exists( 'WPSEO_Options', 'get' ) ) {

				if ( ! WPSEO_Options::get( 'opengraph' ) ) {

					return;
				}
			}

			if ( class_exists( 'Yoast_Notification_Center' ) ) {

				$info = $this->p->cf[ 'plugin' ][ $this->p->id ];
				$name = $this->p->cf[ 'plugin' ][ $this->p->id ][ 'name' ];

				/**
				 * Since WordPress SEO v4.0.
				 */
				if ( method_exists( 'Yoast_Notification_Center', 'get_notification_by_id' ) ) {

					$notif_id     = 'wpseo-conflict-' . md5( $info[ 'base' ] );
					$notif_msg    = '<style type="text/css">#' . $notif_id . '{display:none;}</style>';	// Hide our empty notification. ;-)
					$notif_center = Yoast_Notification_Center::get();
					$notif_obj    = $notif_center->get_notification_by_id( $notif_id );

					if ( empty( $notif_obj ) ) {

						return;
					}

					/**
					 * Note that Yoast_Notification::render() wraps the notification message with
					 * '<div class="yoast-alert"></div>'.
					 */
					if ( method_exists( 'Yoast_Notification', 'render' ) ) {

						$notif_html = $notif_obj->render();

					} else {

						$notif_html = $notif_obj->message;
					}

					if ( strpos( $notif_html, $notif_msg ) === false ) {

						update_user_meta( get_current_user_id(), $notif_obj->get_dismissal_key(), 'seen' );

						$notif_obj = new Yoast_Notification( $notif_msg, array( 'id' => $notif_id ) );

						$notif_center->add_notification( $notif_obj );
					}

				} elseif ( defined( 'Yoast_Notification_Center::TRANSIENT_KEY' ) ) {

					if ( false !== ( $wpseo_notif = get_transient( Yoast_Notification_Center::TRANSIENT_KEY ) ) ) {

						$wpseo_notif = json_decode( $wpseo_notif, $assoc = false );

						if ( ! empty( $wpseo_notif ) ) {

							foreach ( $wpseo_notif as $num => $notif_msgs ) {

								if ( isset( $notif_msgs->options->type ) && $notif_msgs->options->type == 'error' ) {

									if ( false !== strpos( $notif_msgs->message, $name ) ) {

										unset( $wpseo_notif[ $num ] );

										set_transient( Yoast_Notification_Center::TRANSIENT_KEY, json_encode( $wpseo_notif ) );
									}
								}
							}
                                        	}
					}
				}
			}
		}

		/**
		 * Since Yoast SEO v14.0.
		 *
		 * Disable Yoast SEO social meta tags.
		 */
		public function cleanup_wpseo_frontend_presenters( $presenters ) {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			foreach ( $presenters as $num => $obj ) {

				$class_name = get_class( $obj );

				if ( preg_match( '/(Open_Graph|Twitter)/', $class_name ) ) {

					if ( $this->p->debug->enabled ) {

						$this->p->debug->log( 'removing presenter: ' . $class_name );
					}

					unset( $presenters[ $num ] );

				} else {

					if ( $this->p->debug->enabled ) {

						$this->p->debug->log( 'skipping presenter: ' . $class_name );
					}
				}
			}

			return $presenters;
		}

		/**
		 * Deprecated since 2020/04/28 by Yoast SEO v14.0.
		 *
		 * Disable Yoast SEO social meta tags.
		 */
		public function cleanup_wpseo_actions() {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}


			if ( isset( $GLOBALS[ 'wpseo_og' ] ) && is_object( $GLOBALS[ 'wpseo_og' ] ) ) {

				if ( false !== ( $prio = has_action( 'wpseo_head', array( $GLOBALS[ 'wpseo_og' ], 'opengraph' ) ) ) ) {

					if ( $this->p->debug->enabled ) {

						$this->p->debug->log( 'removing wpseo_head action for opengraph' );
					}

					$ret = remove_action( 'wpseo_head', array( $GLOBALS[ 'wpseo_og' ], 'opengraph' ), $prio );
				}
			}

			if ( class_exists( 'WPSEO_Twitter' ) ) {

				if ( false !== ( $prio = has_action( 'wpseo_head', array( 'WPSEO_Twitter', 'get_instance' ) ) ) ) {

					if ( $this->p->debug->enabled ) {

						$this->p->debug->log( 'removing wpseo_head action for twitter' );
					}

					$ret = remove_action( 'wpseo_head', array( 'WPSEO_Twitter', 'get_instance' ), $prio );
				}
			}

			if ( isset( WPSEO_Frontend::$instance ) ) {

				if ( false !== ( $prio = has_action( 'wpseo_head', array( WPSEO_Frontend::$instance, 'publisher' ) ) ) ) {

					if ( $this->p->debug->enabled ) {

						$this->p->debug->log( 'removing wpseo_head action for publisher' );
					}

					$ret = remove_action( 'wpseo_head', array( WPSEO_Frontend::$instance, 'publisher' ), $prio );
				}
			}
		}

		public function cleanup_rank_math_actions() {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			/**
			 * Disable Rank Math social meta tags.
			 */
			remove_all_actions( 'rank_math/opengraph/facebook' );

			remove_all_actions( 'rank_math/opengraph/twitter' );
		}

		public function remove_snap_og_meta_tags_holder() {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			/**
			 * Prevent SNAP from adding meta tags for the Facebook user agent.
			 */
			remove_action( 'wp_head', 'nxs_addOGTagsPreHolder', 150 );
		}


		public function filter_wpseo_replace_vars( $text, $obj ) {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			if ( ! function_exists( 'wpseo_replace_vars' ) ) {	// Just in case.

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'exiting early: wpseo_replace_vars() not found' );
				}

				return $text;
			}

			if ( ! is_object( $obj ) ) {	// Just in case.

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'exiting early: $obj is not an object' );
				}

				return $text;
			}

			if ( $this->p->debug->enabled ) {

				$id_str = 'unknown id';

				if ( isset( $obj->ID ) ) {	// Most common.

					$id_str = 'id ' . $obj->ID;

				} elseif ( isset( $obj->term_id ) ) {

					$id_str = 'term id ' . $obj->term_id;
				}

				$this->p->debug->log( 'given object is ' . get_class( $obj ) . ' with ' . $id_str );
			}

			if ( empty( $text ) || ! is_string( $text ) ) {	// Just in case.

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'exiting early: $text is empty or not a string' );
				}

				return $text;
			}

			if ( false === strpos( $text, '%%' ) ) {

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'exiting early: no inline vars in text = ' . $text );
				}

				return $text;
			}

			if ( $this->p->debug->enabled ) {

				$this->p->debug->log( 'wpseo replace vars before: ' . $text );
			}

			$text = wpseo_replace_vars( $text, $obj );

			if ( $this->p->debug->enabled ) {

				$this->p->debug->log( 'wpseo replace vars after: ' . $text );
			}

			return $text;
		}

		public function filter_admin_page_style_css_rank_math( $custom_style_css ) {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			/**
			 * Fix the width of Rank Math list table columns.
			 */
			$custom_style_css .= '
				table.wp-list-table > thead > tr > th.column-rank_math_seo_details,
				table.wp-list-table > tbody > tr > td.column-rank_math_seo_details {
					width:170px;
				}
			';

			/**
			 * The "Social" metabox tab and its options cannot be disabled, so hide them instead.
			 */
			$custom_style_css .= '
				.rank-math-tabs > div > a[href="#setting-panel-social"] { display: none; }
				.rank-math-tabs-content .setting-panel-social { display: none; }
			';

			return $custom_style_css;
		}

		public function filter_admin_page_style_css_wpseo( $custom_style_css ) {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			/**
			 * Fix the width of Yoast SEO list table columns.
			 */
			$custom_style_css .= '
				table.wp-list-table > thead > tr > th.column-wpseo-links,
				table.wp-list-table > tbody > tr > td.column-wpseo-links,
				table.wp-list-table > thead > tr > th.column-wpseo-linked,
				table.wp-list-table > tbody > tr > td.column-wpseo-linked,
				table.wp-list-table > thead > tr > th.column-wpseo-score,
				table.wp-list-table > tbody > tr > td.column-wpseo-score,
				table.wp-list-table > thead > tr > th.column-wpseo-score-readability,
				table.wp-list-table > tbody > tr > td.column-wpseo-score-readability {
					width:40px;
				}
				table.wp-list-table > thead > tr > th.column-wpseo-title,
				table.wp-list-table > tbody > tr > td.column-wpseo-title,
				table.wp-list-table > thead > tr > th.column-wpseo-metadesc,
				table.wp-list-table > tbody > tr > td.column-wpseo-metadesc {
					width:20%;
				}
				table.wp-list-table > thead > tr > th.column-wpseo-focuskw,
				table.wp-list-table > tbody > tr > td.column-wpseo-focuskw {
					width:8em;	/* Leave room for the sort arrow. */
				}
			';

			return $custom_style_css;
		}
	}
}
