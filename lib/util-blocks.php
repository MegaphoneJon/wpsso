<?php
/**
 * IMPORTANT: READ THE LICENSE AGREEMENT CAREFULLY. BY INSTALLING, COPYING, RUNNING, OR OTHERWISE USING THE WPSSO CORE PREMIUM
 * APPLICATION, YOU AGREE  TO BE BOUND BY THE TERMS OF ITS LICENSE AGREEMENT. IF YOU DO NOT AGREE TO THE TERMS OF ITS LICENSE
 * AGREEMENT, DO NOT INSTALL, RUN, COPY, OR OTHERWISE USE THE WPSSO CORE PREMIUM APPLICATION.
 * 
 * License URI: https://wpsso.com/wp-content/plugins/wpsso/license/premium.txt
 * 
 * Copyright 2012-2022 Jean-Sebastien Morisset (https://wpsso.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {

	die( 'These aren\'t the droids you\'re looking for.' );
}

if ( ! defined( 'WPSSO_PLUGINDIR' ) ) {

	die( 'Do. Or do not. There is no try.' );
}

if ( ! class_exists( 'WpssoUtilBlocks' ) ) {

	class WpssoUtilBlocks {

		private $p;	// Wpsso class object.
		private $u;	// WpssoUtil class object.

		/**
		 * Instantiated by WpssoUtil->__construct().
		 */
		public function __construct( &$plugin, &$util ) {

			$this->p =& $plugin;
			$this->u =& $util;

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			$this->u->add_plugin_filters( $this, array(
				'import_content_blocks' => 2,
			) );
		}

		public function filter_import_content_blocks( array $md_opts, $content = '' ) {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			if ( empty( $content ) ) {

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'exiting early: content is empty' );
				}

				return $md_opts;
			}

			if ( ! function_exists( 'parse_blocks' ) ) {

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'exiting early: parse_blocks function not found' );
				}

				return $md_opts;
			}

			$blocks = parse_blocks( $content );

			foreach ( $blocks as $block ) {

				if ( empty( $block[ 'blockName' ] ) || empty( $block[ 'attrs' ] ) ) {

					continue;
				}

				/**
				 * Example filter name: wpsso_import_block_attrs_yoast_how_to_block
				 */
				$filter_name = SucomUtil::sanitize_hookname( 'wpsso_import_block_attrs_' . $block[ 'blockName' ] );

				$md_opts = apply_filters( $filter_name, $md_opts, $block[ 'attrs' ] );
			}

			return $md_opts;
		}
	}
}