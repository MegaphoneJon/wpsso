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

if ( ! class_exists( 'WpssoMessagesInfo' ) ) {

	/**
	 * Instantiated by WpssoMessages->get() only when needed.
	 */
	class WpssoMessagesInfo extends WpssoMessages {

		private $meta = null;	// WpssoMessagesInfoMeta class object.

		public function get( $msg_key = false, $info = array() ) {

			$text = '';

			if ( 0 === strpos( $msg_key, 'info-meta-' ) ) {

				/**
				 * Instantiate WpssoMessagesInfoMeta only when needed.
				 */
				if ( null === $this->meta ) {

					require_once WPSSO_PLUGINDIR . 'lib/messages-info-meta.php';

					$this->meta = new WpssoMessagesInfoMeta( $this->p );
				}

				return $this->meta->get( $msg_key, $info );
			}

			switch ( $msg_key ) {

				case 'info-plugin-tid':

					$um_info       = $this->p->cf[ 'plugin' ][ 'wpssoum' ];
					$um_info_name  = _x( $um_info[ 'name' ], 'plugin name', 'wpsso' );
					$um_addon_link = $this->p->util->get_admin_url( 'addons#wpssoum', $um_info_name );

					$text = '<blockquote class="top-info">';

					$text .= '<p>';

					$text .= sprintf( __( 'After purchasing a %1$s license pack, you will receive an email with %2$s installation instructions and your unique Authentication ID for the license pack.', 'wpsso' ), $this->p_name_pro, $this->dist_pro ) . ' ';

					$text .=  __( 'Enter the Authentication ID in the option field below.', 'wpsso' ) . ' ';

					$text .= sprintf( __( 'As mentioned in the installation instructions, don\'t forget that the %1$s add-on must be installed and active to enable %2$s features and get %2$s updates.', 'wpsso' ), $um_addon_link, $this->dist_pro );

					$text .= '</p>';


					$text .= '</blockquote>';

					break;

				case 'info-plugin-tid-network':

					$um_info      = $this->p->cf[ 'plugin' ][ 'wpssoum' ];
					$um_info_name = _x( $um_info[ 'name' ], 'plugin name', 'wpsso' );

					$licenses_page_link = $this->p->util->get_admin_url( 'licenses', _x( 'Premium Licenses', 'lib file description', 'wpsso' ) );

					$text = '<blockquote class="top-info">';

					$text .= '<p>';

					$text .= sprintf( __( 'After purchasing a %1$s license pack, you will receive an email with %2$s installation instructions and your unique Authentication ID for the license pack.', 'wpsso' ), $this->p_name_pro, $this->dist_pro ) . ' ';

					$text .= sprintf( __( 'You may enter the Authentication ID in this settings page to define a value for all sites within the network, or enter the Authentication ID individually in each site\'s %1$s settings page.', 'wpsso' ), $licenses_page_link ) . ' ';

					$text .= sprintf( __( 'If you enter an Authentication ID in this settings page, make sure you have purchased enough licenses for all sites within the network - for example, to license the %1$s plugin for 10 sites, you would need an Authentication ID for a 10 license pack or better.', 'wpsso' ), $this->p_name_pro ) . ' ';

					$text .= '</p><p>';

					$text .= sprintf( __( 'WordPress uses the default blog (ie. BLOG_ID_CURRENT_SITE) to manage updates in the network admin interface, which means the default blog must be licensed to install %1$s updates.', 'wpsso' ), $this->dist_pro ) . ' ';

					$text .= sprintf( __( 'To update the %1$s plugin, make sure the %2$s add-on is active on the default blog, and the default blog is licensed.', 'wpsso' ), $this->p_name_pro, $um_info_name );

					$text .= '</p>';

					$text .= '</blockquote>';

					break;

				case 'info-cm':

					// translators: Please ignore - translation uses a different text domain.
					$section_label = __( 'Contact Info' );

					$profile_page_url = get_admin_url( $blog_id = null, 'profile.php' );

					$text = '<blockquote class="top-info">';

					$text .= '<p>';

					$text .= sprintf( __( 'These options allow you to customize contact fields shown in the "%1$s" section of <a href="%2$s">the user profile page</a>.', 'wpsso' ), $section_label, $profile_page_url ) . ' ';

					$text .= __( 'Contact information from the user profile can be included in meta tags and Schema markup.', 'wpsso' ) . ' ';

					$text .= '<strong>' . sprintf( __( 'You should not modify the <em>%s</em> column unless you have a <em>very</em> good reason to do so.', 'wpsso' ), _x( 'Contact Field ID', 'column title', 'wpsso' ) ) . '</strong> ';

					$text .= sprintf( __( 'The %s column is for display purposes only and can be changed as you wish.', 'wpsso' ), _x( 'Contact Field Label', 'column title', 'wpsso' ) ) . ' ';

					$text .= '</p> <p>';

					$text .= '<center>';

					$text .= '<strong>' . __( 'Do not enter your contact information here &ndash; these options are for contact field ids and labels only.', 'wpsso' ) . '</strong><br/>';

					$text .= sprintf( __( 'Enter your personal contact information in <a href="%s">the user profile page</a>.', 'wpsso' ), $profile_page_url );

					$text .= '</center>';

					$text .= '</p>';

					$text .= '</blockquote>';

					break;

				case 'info-user-about':

					// translators: Please ignore - translation uses a different text domain.
					$section_label = __( 'About Yourself' );

					$profile_page_url = get_admin_url( $blog_id = null, 'profile.php' );

					$text = '<blockquote class="top-info"><p>';

					$text .= sprintf( __( 'These options allow you to customize additional fields shown in the "%1$s" section of <a href="%2$s">the user profile page</a>.', 'wpsso' ), $section_label, $profile_page_url ) . ' ';

					$text .= __( 'This additional user profile information can be included in meta tags and Schema markup.', 'wpsso' ) . ' ';

					$text .= '</blockquote>';

					break;

				case 'info-product-attrs':

					$text = '<blockquote class="top-info"><p>';

					$text .= sprintf( __( 'These options allow you to customize product attribute names (aka attribute labels) that %s can use to request additional product information from your e-commerce plugin.', 'wpsso' ), $this->p_name_pro ) . ' ';

					$text .= __( 'Note that these are product attribute names that you can create in your e-commerce plugin and not their values.', 'wpsso' ) . ' ';

					$text .= '</p> <p><center><strong>';

					$text .= __( 'Do not enter product attribute values here &ndash; these options are for product attribute names only.', 'wpsso' );

					$text .= '</strong><br/>';

					$text .= __( 'You can create the following product attribute names and enter their corresponding values in your e-commerce plugin.', 'wpsso' );

					$text .= '</center></p>';

					if ( ! empty( $this->p->avail[ 'ecom' ][ 'woocommerce' ] ) ) {

						$text .= '<p><center><strong>';

						$text .= __( 'An active WooCommerce plugin has been detected.', 'wpsso' );

						$text .= '</strong></br>';

						$text .= __( 'Please note that WooCommerce creates a selector on the purchase page for product attributes used for variations.', 'wpsso' ) . ' ';

						// translators: Please ignore - translation uses a different text domain.
						$used_for_variations = __( 'Used for variations', 'woocommerce' );

						$text .= sprintf( __( 'Enabling the WooCommerce "%s" attribute option may not be suitable for some product attributes (like GTIN, ISBN, and MPN).', 'wpsso' ), $used_for_variations ) . ' ';

						$text .= __( 'We suggest using a supported third-party plugin to manage Brand, GTIN, ISBN, and MPN values for variations.', 'wpsso' );

						$text .= '</center></p>';
					}

					$text .= '</blockquote>';

					break;

				case 'info-custom-fields':

					$text = '<blockquote class="top-info">';

					$text .= '<p>';

					$text .= sprintf( __( 'These options allow you to customize custom field names (aka metadata names) that %s can use to get additional information about your content.', 'wpsso' ), $this->p_name_pro ) . ' ';

					$text .= '</p> <p><center><strong>';

					$text .= __( 'Do not enter custom field values here &ndash; these options are for custom field names only.', 'wpsso' ) . ' ';

					$text .= '</strong><br/>';

					$text .= __( 'Use the following custom field names when creating custom fields for your posts, pages, and custom post types.', 'wpsso' ) . ' ';

					$text .= '</center></p>';

					if ( ! empty( $this->p->avail[ 'ecom' ][ 'woocommerce' ] ) ) {


						$text .= '<p><center><strong>';

						$text .= __( 'An active WooCommerce plugin has been detected.', 'wpsso' ) . ' ';

						$text .= '</strong></br>';

						$text .= __( 'Note that product attributes from WooCommerce have precedence over custom field values.', 'wpsso' ) . ' ';

						$text .= sprintf( __( 'Refer to the <a href="%s">WooCommerce integration notes</a> for information on setting up product attributes and custom fields.', 'wpsso' ), 'https://wpsso.com/docs/plugins/wpsso/installation/integration/woocommerce-integration/' ) . ' ';

						$text .= __( 'We suggest using a supported third-party plugin to manage Brand, GTIN, ISBN, and MPN values for variations.', 'wpsso' ) . ' ';

						$text .= '</center></p>';
					}

					$text .= '</blockquote>';

					break;

				case 'info-head_tags':

					$text = '<blockquote class="top-info">';

					$text .= '<p>';

					// translators: %1$s is the plugin name, %2$s is <head>.
					$text .= sprintf( __( '%1$s adds the following Facebook, Open Graph, Twitter, Schema, Pinterest, and SEO HTML tags to the %2$s section of your webpages.', 'wpsso' ), $info[ 'short' ], '<code>&lt;head&gt;</code>' ) . ' ';

					$text .= __( 'If your theme or another plugin already creates one or more of these HTML tags, you can uncheck them here to prevent duplicates from being added.', 'wpsso' ) . ' ';

					// translators: %1$s is "link rel canonical", %2$s is "meta name description", and %3$s is "meta name robots".
					$text .= sprintf( __( 'Please note that the %1$s HTML tag is disabled by default (as themes often include this HTML tag in their header templates), and the %2$s and %3$s HTML tags are disabled automatically if a known SEO plugin is detected.', 'wpsso' ), '<code>link rel canonical</code>', '<code>meta name description</code>', '<code>meta name robots</code>' );

					$text .= '</p>';

					$text .= '</blockquote>';

					break;

				case 'info-image_dimensions':

					$text = '<blockquote class="top-info">';

					$text .= '<p>';

					$text .= sprintf( __( '%s and WordPress create image files for social sites and search engines based on the following image dimensions and crop settings.', 'wpsso' ), $info[ 'short' ] ) . ' ';

					$text .= __( 'Image sizes that use the same dimensions and crop settings will create just one image file.', 'wpsso' ) . ' ';

					$text .= sprintf( __( 'The default dimensions and crop settings from %1$s create only %2$s image files from an original full size image (provided the original image is large enough or image upscaling has been enabled).', 'wpsso' ), $info[ 'short' ], __( 'five', 'wpsso' ) );

					$text .= '</p>';

					$text .= '</blockquote>';

					break;

				case 'info-wp_sitemaps':

					$sitemap_url    = get_site_url( $blog_id = null, $path = '/wp-sitemap.xml' );
					$no_index_label = _x( 'No Index', 'option label', 'wpsso' );
					$mb_title       = _x( $this->p->cf[ 'meta' ][ 'title' ], 'metabox title', 'wpsso' );
					$robots_tab     = _x( 'Robots Meta', 'metabox tab', 'wpsso' );

					$text = '<blockquote class="top-info">';

					$text .= '<p>';

					$text .= sprintf( __( 'These options allow you to customize post and taxonomy types included in the <a href="%s">WordPress sitemap XML</a>.', 'wpsso' ), $sitemap_url ) . ' ';

					$text .= '</p><p>';

					$text .= sprintf( __( 'To <strong>exclude</strong> individual posts, pages, custom post types, taxonomy terms (categories, tags, etc.), or user profile pages from the WordPress sitemap XML, enable the <strong>%1$s</strong> option under their %2$s &gt; %3$s tab.', 'wpsso' ), $no_index_label, $mb_title, $robots_tab ) . ' ';

					$text .= '</p>';

					$text .= '</blockquote>';

					break;

				default:

					$text = apply_filters( 'wpsso_messages_info', $text, $msg_key, $info );

					break;

			}	// End of 'info' switch.

			return $text;
		}
	}
}
