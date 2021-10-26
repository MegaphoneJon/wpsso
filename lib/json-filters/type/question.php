<?php
/**
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2016-2021 Jean-Sebastien Morisset (https://wpsso.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {

	die( 'These aren\'t the droids you\'re looking for.' );
}

if ( ! class_exists( 'WpssoJsonFiltersTypeQuestion' ) ) {

	class WpssoJsonFiltersTypeQuestion {

		private $p;	// Wpsso class object.

		/**
		 * Instantiated by Wpsso->init_json_filters().
		 */
		public function __construct( &$plugin ) {

			$this->p =& $plugin;

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			$this->p->util->add_plugin_filters( $this, array(
				'json_data_https_schema_org_question' => 5,
			) );
		}

		public function filter_json_data_https_schema_org_question( $json_data, $mod, $mt_og, $page_type_id, $is_main ) {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			$json_ret = array();

			/**
			 * Property:
			 *      dateCreated
			 *      datePublished
			 *      dateModified
			 */
			WpssoSchema::add_data_itemprop_from_assoc( $json_ret, $mt_og, array(
				'dateCreated'   => 'article:published_time',	// In WordPress, created and published times are the same.
				'datePublished' => 'article:published_time',
				'dateModified'  => 'article:modified_time',
			) );

			/**
			 * Property:
			 *      author as https://schema.org/Person
			 *      contributor as https://schema.org/Person
			 */
			WpssoSchema::add_author_coauthor_data( $json_ret, $mod );

			WpssoSchema::add_data_itemprop_from_assoc( $json_ret, $json_data, array( 
				'text' => 'name',
			) );

			/**
			 * Answer:
			 *
			 * Schema Question is a sub-type of CreativeWork. We already have the question in 'name' (the post/page
			 * title), the answer excerpt in 'description', and the full answer text in 'text'. Create the answer
			 * first, before changing / removing some question properties.
			 */
			$accepted_answer = WpssoSchema::get_schema_type_context( 'https://schema.org/Answer' );

			WpssoSchema::add_data_itemprop_from_assoc( $accepted_answer, $json_data, array( 
				'url'        => 'url',
				'name'       => 'description',	// The Answer name is CreativeWork custom description or excerpt.
				'text'       => 'text',		// May not exist if the 'schema_add_text_prop' option is disabled.
				'inLanguage' => 'inLanguage',
			) );

			unset( $json_data[ 'description' ] );

			if ( empty( $accepted_answer[ 'text' ] ) ) {

				$text_max_len = $this->p->options[ 'schema_text_max_len' ];

				$accepted_answer[ 'text' ] = $this->p->page->get_text( $text_max_len, $dots = '...', $mod );
			}

			WpssoSchema::add_data_itemprop_from_assoc( $accepted_answer, $json_ret, array( 
				'dateCreated'   => 'dateCreated',
				'datePublished' => 'datePublished',
				'dateModified'  => 'dateModified',
				'author'        => 'author',
			) );

			$accepted_answer[ 'upvoteCount' ] = 0;

			$json_ret[ 'acceptedAnswer' ] = $accepted_answer;

			$json_ret[ 'answerCount' ] = 1;

			return WpssoSchema::return_data_from_filter( $json_data, $json_ret, $is_main );
		}
	}
}
