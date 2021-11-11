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

if ( ! class_exists( 'WpssoAdminHeadSuggest' ) ) {

	class WpssoAdminHeadSuggest {

		private $p;	// Wpsso class object.

		/**
		 * Instantiated by WpssoAdmin->__construct().
		 */
		public function __construct( &$plugin ) {

			$this->p =& $plugin;

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			add_action( 'admin_head', array( $this, 'suggest_addons' ), 100 );
		}

		public function suggest_addons() {

			if ( ! $this->p->notice->can_dismiss() || ! current_user_can( 'manage_options' ) ) {

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'exiting early: cannot dismiss or cannot manage options' );
				}

				return;	// Stop here.
			}

			$this->suggest_addons_woocommerce();
		}

		/**
		 * Suggest purchasing the WPSSO Core Premium plugin and activating WooCommerce related add-ons.
		 *
		 * These private notice functions should return the number of notices shown.
		 */
		private function suggest_addons_woocommerce() {

			$notice_shown = 0;

			if ( empty( $this->p->avail[ 'ecom' ][ 'woocommerce' ] ) ) {

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'woocommerce is not active' );
				}

				return $notice_shown;
			}

			if ( empty( $this->p->avail[ 'p' ][ 'schema' ] ) ) {

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'schema markup is disabled' );
				}

				return $notice_shown;
			}

			$pkg_info = $this->p->admin->get_pkg_info();	// Returns an array from cache.

			if ( empty( $pkg_info[ 'wpsso' ][ 'pp' ] ) ) {

				$notice_key = 'suggest-premium-for-woocommerce';

				if ( $this->p->notice->is_admin_pre_notices( $notice_key ) ) {

					$action_links = array();	// Init a new action array for the notice message.

					$action_links[] = $this->get_purchase_plugin_link( 'wpsso' );

					$prod_info_msg = __( 'brand, color, condition, EAN, dimensions, GTIN-8/12/13/14, ISBN, material, MPN, pattern, size, SKU, volume, weight, etc', 'wpsso' );

					$notice_msg = __( 'The WooCommerce plugin does not provide sufficient Schema JSON-LD markup for Google Rich Results.', 'wpsso' ) . ' ';

					$notice_msg .= sprintf( __( 'The %1$s plugin reads WooCommerce product data and provides complete Schema Product JSON-LD markup for Google Rich Results, including additional product images, product variations, product information (%2$s), product reviews, product ratings, sale start / end dates, sale prices, pre-tax prices, VAT prices, shipping rates, shipping times, and much, much more.', 'wpsso' ), $pkg_info[ 'wpsso' ][ 'name_pro' ], $prod_info_msg ) . ' ';

					$notice_msg .= '<ul><li>' . implode( $glue = '</li> <li>', $action_links ) . '</li></ul>' . ' ';

					$this->p->notice->warn( $notice_msg, null, $notice_key, $dismiss_time = true );

					$notice_shown++;
				}
			}

			if ( empty( $this->p->avail[ 'p_ext' ][ 'wcmd' ] ) &&
				empty( $this->p->avail[ 'ecom' ][ 'woo-add-gtin' ] ) &&
				empty( $this->p->avail[ 'ecom' ][ 'wpm-product-gtin-wc' ] ) ) {

				$notice_key = 'suggest-wpssowcmd-for-woocommerce';

				if ( $this->p->notice->is_admin_pre_notices( $notice_key ) ) {

					$action_links = array();	// Init a new action array for the notice message.

					if ( empty( $pkg_info[ 'wpsso' ][ 'pp' ] ) ) {

						$action_links[] = $this->get_purchase_plugin_link( 'wpsso', __( '(required for WooCommerce integration)', 'wpsso' ) );
					}

					$action_links[] = $this->get_install_activate_addon_link( 'wpssowcmd' );

					$wcmd_info        = $this->p->cf[ 'plugin' ][ 'wpssowcmd' ];
					$wcmd_name_transl = _x( $wcmd_info[ 'name' ], 'plugin name', 'wpsso' );

					$notice_msg = __( 'Schema Product markup for Google Rich Results requires at least one unique product ID, like the product MPN (Manufacturer Part Number), UPC, EAN, GTIN, or ISBN.', 'wpsso' ) . ' ';

					$notice_msg .= __( 'The product SKU (Stock Keeping Unit) from WooCommerce is not a valid unique product ID.', 'wpsso' ) . ' ';

					$notice_msg .= sprintf( __( 'If you\'re not already using a plugin to manage unique product IDs for WooCommerce, you should activate the %s add-on.', 'wpsso' ), $wcmd_name_transl ) . ' ';

					$notice_msg .= '<ul><li>' . implode( $glue = '</li> <li>', $action_links ) . '</li></ul>' . ' ';

					$this->p->notice->warn( $notice_msg, null, $notice_key, $dismiss_time = true );

					$notice_shown++;
				}
			}

			if ( empty( $this->p->avail[ 'p_ext' ][ 'wcsdt' ] ) ) {

				$notice_key = 'suggest-wpssowcsdt-for-woocommerce';

				if ( $this->p->notice->is_admin_pre_notices( $notice_key ) ) {

					$action_links = array();	// Init a new action array for the notice message.

					$shipping_continents = WC()->countries->get_shipping_continents();	// Since WC v3.6.0.
					$shipping_countries  = WC()->countries->get_shipping_countries();
					$shipping_enabled    = $shipping_continents || $shipping_countries ? true : false;

					if ( $shipping_enabled ) {

						if ( empty( $pkg_info[ 'wpsso' ][ 'pp' ] ) ) {

							$action_links[] = $this->get_purchase_plugin_link( 'wpsso', __( '(required for WooCommerce integration)', 'wpsso' ) );
						}

						$action_links[] = $this->get_install_activate_addon_link( 'wpssowcsdt' );

						$wcsdt_info        = $this->p->cf[ 'plugin' ][ 'wpssowcsdt' ];
						$wcsdt_name_transl = _x( $wcsdt_info[ 'name' ], 'plugin name', 'wpsso' );

						$notice_msg = sprintf( __( 'Product shipping features are enabled in WooCommerce, but the %s add-on is not active.', 'wpsso' ), $wcsdt_name_transl ) . ' ';

						$notice_msg .= __( 'Adding shipping details to your Schema Product markup is important if you offer free or low-cost shipping options, as this will make your products more appealing in Google search results.', 'wpsso' ) . ' ';

						$notice_msg .= '<ul><li>' . implode( $glue = '</li> <li>', $action_links ) . '</li></ul>' . ' ';

						$this->p->notice->warn( $notice_msg, null, $notice_key, $dismiss_time = true );

						$notice_shown++;
					}
				}
			}

			return $notice_shown;
		}

		private function get_purchase_plugin_link( $ext, $cmt = '' ) {

			$pkg_info         = $this->p->admin->get_pkg_info();	// Returns an array from cache.
			$ext_info         = $this->p->cf[ 'plugin' ][ $ext ];
			$ext_purchase_url = $ext_info[ 'url' ][ 'purchase' ];

			if ( $cmt ) {

				// translators: %1$s is a URL, %2$s is the plugin name, and %3$s is a pre-translated comment.
				return sprintf( __( '<a href="%1$s">Purchase the %2$s plugin</a> %3$s.', 'wpsso' ), $ext_purchase_url, $pkg_info[ $ext ][ 'name_pro' ], $cmt );
			}

			return '<a href="' . $ext_purchase_url . '">' . sprintf( __( 'Purchase the %s plugin.', 'wpsso' ), $pkg_info[ $ext ][ 'name_pro' ] ) . '</a>';
		}

		private function get_install_activate_addon_link( $ext ) {

			$ext_info = $this->p->cf[ 'plugin' ][ $ext ];

			$ext_name_transl = _x( $ext_info[ 'name' ], 'plugin name', 'wpsso' );

			if ( SucomPlugin::is_plugin_installed( $ext_info[ 'base' ] ) ) {

				$search_url = is_multisite() ? network_admin_url( 'plugins.php', null ) : get_admin_url( $blog_id = null, 'plugins.php' );
				$search_url = add_query_arg( array( 's' => $ext_info[ 'slug' ] ), $search_url );

				return '<a href="' . $search_url . '">' . sprintf( __( 'Activate the %s add-on.', 'wpsso' ), $ext_name_transl ) . '</a>';

			}

			$addons_url = $this->p->util->get_admin_url( 'addons#' . $ext );

			return '<a href="' . $addons_url . '">' . sprintf( __( 'Install and activate the %s add-on.', 'wpsso' ), $ext_name_transl ) . '</a>';
		}
	}
}