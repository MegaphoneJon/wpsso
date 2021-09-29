<?php
/**
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2012-2021 Jean-Sebastien Morisset (https://wpsso.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {

	die( 'These aren\'t the droids you\'re looking for.' );
}

if ( ! class_exists( 'WpssoStdAdminEdit' ) ) {

	class WpssoStdAdminEdit {

		private $p;	// Wpsso class object.

		/**
		 * Since WPSSO Core v9.0.0.
		 *
		 * Provides backwards compatibility for older WPSSO JSON add-ons.
		 */
		private $old_schema_preg = '/^(wpssojson_|subsection_(schema|creative_work|book_audio|howto|recipe|movie|review|software_app|qa|event|job|organization|person|place|product)|schema_)/';

		public function __construct( &$plugin ) {

			$this->p =& $plugin;

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			$this->p->util->add_plugin_filters( $this, array(
				'metabox_sso_edit_rows'  => 4,
				'metabox_sso_media_rows' => 4,
			), $prio = 500 );	// Run before older WPSSO JSON add-ons.

			/**
			 * Since WPSSO Core v9.0.0.
			 */
			if ( empty( $this->p->avail[ 'p' ][ 'schema' ] ) ) {

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'skipping schema filters: schema markup is disabled' );
				}

			} else {

				$this->p->util->add_plugin_filters( $this, array( 
					'metabox_sso_edit_schema_rows'  => array( 'metabox_sso_edit_rows'  => 4 ),
					'metabox_sso_media_schema_rows' => array( 'metabox_sso_media_rows' => 4 ),
				), $prio = 1500 );	// Run after older WPSSO JSON add-ons.
			}
		}

		public function filter_metabox_sso_edit_rows( $table_rows, $form, $head_info, $mod ) {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			/**
			 * Since WPSSO Core v9.0.0.
			 *
			 * Provides backwards compatibility for older WPSSO JSON add-ons.
			 */
			if ( ! empty( $this->p->avail[ 'p_ext' ][ 'json' ] ) ) {

				$json_version = WpssoJsonConfig::get_version();

				if ( version_compare( $json_version, '5.0.0', '<' ) ) {

					$table_rows[ 'subsection_schema' ] = '<td class="subsection" colspan="2"><h4>' .
						_x( 'Schema JSON-LD Markup / Google Rich Results', 'metabox title', 'wpsso' ) . '</h4></td>';
				}
			}

			return $table_rows;
		}

		public function filter_metabox_sso_edit_schema_rows( $table_rows, $form, $head_info, $mod ) {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			/**
			 * Since WPSSO Core v9.0.0.
			 *
			 * Provides backwards compatibility for older WPSSO JSON add-ons.
			 */
			if ( ! empty( $this->p->avail[ 'p_ext' ][ 'json' ] ) ) {

				if ( $this->p->check->pp( 'wpssojson' ) ) {	// Nothing to do.

					return apply_filters( 'wpsso_metabox_sso_edit_schema_rows', $table_rows, $form, $head_info, $mod );
				}

				$json_version = WpssoJsonConfig::get_version();

				if ( version_compare( $json_version, '5.0.0', '<' ) ) {

					$table_rows = SucomUtil::preg_grep_keys( $this->old_schema_preg, $table_rows, $invert = true );
				}
			}

			/**
			 * Select arrays.
			 */
			$currencies         = SucomUtil::get_currency_abbrev();
			$product_categories = $this->p->util->get_google_product_categories();
			$schema_types       = $this->p->schema->get_schema_types_select( $context = 'meta' );

			/**
			 * Maximum lengths.
			 */
			$og_title_max_len        = $this->p->options[ 'og_title_max_len' ];
			$schema_headline_max_len = $this->p->cf[ 'head' ][ 'limit_max' ][ 'schema_headline_len' ];
			$schema_desc_max_len     = $this->p->options[ 'schema_desc_max_len' ];		// Schema Description Max. Length.
			$schema_text_max_len     = $this->p->options[ 'schema_text_max_len' ];

			/**
			 * Default values.
			 */
			$dots             = '...';
			$read_cache       = true;
			$no_hashtags      = false;
			$do_encode        = true;
			$schema_desc_keys = array( 'seo_desc', 'og_desc' );

			$def_schema_title     = $this->p->page->get_title( $max_len = 0, '', $mod, $read_cache, $no_hashtags, $do_encode, 'og_title' );
			$def_schema_title_alt = $this->p->page->get_title( $og_title_max_len, $dots, $mod, $read_cache, $no_hashtags, $do_encode, 'og_title' );
			$def_schema_headline  = $this->p->page->get_title( $schema_headline_max_len, '', $mod, $read_cache, $no_hashtags, $do_encode, 'og_title' );
			$def_schema_desc      = $this->p->page->get_description( $schema_desc_max_len, $dots, $mod, $read_cache, $no_hashtags, $do_encode, $schema_desc_keys );
			$def_schema_text      = $this->p->page->get_text( $schema_text_max_len, '', $mod, $read_cache, $no_hashtags, $do_encode, $md_key = 'none' );
			$def_schema_keywords  = $this->p->page->get_keywords( $mod, $read_cache, $md_key = 'none' );
			$def_copyright_year   = $mod[ 'is_post' ] ? trim( get_post_time( 'Y', $gmt = true, $mod[ 'id' ] ) ) : '';

			/**
			 * Organization variables.
			 */
			$org_req_msg = $this->p->msgs->maybe_ext_required( 'wpssoorg' );
			$org_disable = empty( $org_req_msg ) ? false : true;
			$org_names   = $this->p->util->get_form_cache( 'org_names', $add_none = true );

			/**
			 * Person variables.
			 */
			$person_names = $this->p->util->get_form_cache( 'person_names', $add_none = true );

			/**
			 * Place variables.
			 */
			$plm_req_msg        = $this->p->msgs->maybe_ext_required( 'wpssoplm' );
			$plm_disable        = empty( $plm_req_msg ) ? false : true;
			$place_names        = $this->p->util->get_form_cache( 'place_names', $add_none = true );
			$place_names_custom = $this->p->util->get_form_cache( 'place_names_custom', $add_none = true );

			/**
			 * Javascript classes to hide/show rows by selected schema type.
			 */
			$schema_type_row_class             = WpssoSchema::get_schema_type_row_class( 'schema_type' );
			$schema_review_item_type_row_class = WpssoSchema::get_schema_type_row_class( 'schema_review_item_type' );

			/**
			 * Metabox form rows.
			 */
			$form_rows = array(
				'subsection_schema' => array(
					'td_class' => 'subsection',
					'header'   => 'h4',
					'label'    => _x( 'Schema JSON-LD Markup / Google Rich Results', 'metabox title', 'wpsso' )
				),
				'pro_feature_msg_schema' => array(
					'table_row' => '<td colspan="2">' . $this->p->msgs->pro_feature( 'wpsso' ) . '</td>',
				),
				'schema_title' => array(
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Name / Title', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_title',
					'content'  => $form->get_no_input_value( $def_schema_title, $css_class = 'wide' ),
				),
				'schema_title_alt' => array(
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Alternate Name', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_title_alt',
					'content'  => $form->get_no_input_value( $def_schema_title_alt, $css_class = 'wide' ),
				),
				'schema_desc' => array(
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Description', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_desc',
					'content'  => $form->get_no_textarea_value( $def_schema_desc, $css_class = '', $css_id = '', $schema_desc_max_len ),
				),
				'schema_addl_type_url' => array(
					'tr_class' => $form->get_css_class_hide_prefix( 'basic', 'schema_addl_type_url' ),
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Microdata Type URLs', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_addl_type_url',
					'content'  => $form->get_no_input_value( $value = '', $css_class = 'wide', $css_id = '', '', $repeat = 2 ),
				),
				'schema_sameas_url' => array(
					'tr_class' => $form->get_css_class_hide_prefix( 'basic', 'schema_sameas_url' ),
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Same-As URLs', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_sameas_url',
					'content'  => $form->get_no_input_value( $value = '', $css_class = 'wide', $css_id = '', '', $repeat = 2 ),
				),

				/**
				 * Schema Creative Work.
				 */
				'subsection_schema_creative_work' => array(
					'tr_class' => $schema_type_row_class[ 'creative_work' ],
					'td_class' => 'subsection',
					'header'   => 'h5',
					'label'    => _x( 'Creative Work Information', 'metabox title', 'wpsso' ),
				),
				'schema_ispartof_url' => array(
					'tr_class' => $schema_type_row_class[ 'creative_work' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Is Part of URL', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_ispartof_url',
					'content'  => $form->get_no_input_value( $value = '', $css_class = 'wide', $css_id = '', '', $repeat = 2 ),
				),
				'schema_headline' => array(
					'tr_class' => $schema_type_row_class[ 'creative_work' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Headline', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_headline',
					'content'  => $form->get_no_input_value( $def_schema_headline, $css_class = 'wide' ),
				),
				'schema_text' => array(
					'tr_class' => $schema_type_row_class[ 'creative_work' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Full Text', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_text',
					'content'  => $form->get_no_textarea_value( $def_schema_text, $css_class = 'full_text' ),
				),
				'schema_keywords' => array(
					'tr_class' => $schema_type_row_class[ 'creative_work' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Keywords', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_keywords',
					'content'  => $form->get_no_input_value( $def_schema_keywords, $css_class = 'wide' ),
				),
				'schema_lang' => array(
					'tr_class' => $schema_type_row_class[ 'creative_work' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Language', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_lang',
					'content'  => $form->get_no_select( 'schema_lang', SucomUtil::get_available_locales(),
						$css_class = 'locale' ),
				),
				'schema_family_friendly' => array(
					'tr_class' => $schema_type_row_class[ 'creative_work' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Family Friendly', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_family_friendly',
					'content'  => $form->get_no_select_none( 'schema_family_friendly',
						$this->p->cf[ 'form' ][ 'yes_no' ], $css_class = 'yes-no', $css_id = '', $is_assoc = true ),
				),
				'schema_copyright_year' => array(
					'tr_class' => $schema_type_row_class[ 'creative_work' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Copyright Year', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_copyright_year',
					'content'  => $form->get_no_input_value( $def_copyright_year, $css_class = 'year' ),
				),
				'schema_license_url' => array(
					'tr_class' => $schema_type_row_class[ 'creative_work' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'License URL', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_license_url',
					'content'  => $form->get_no_input_value( $value = '', $css_class = 'wide' ),
				),
				'schema_pub_org_id' => array(
					'tr_class' => $schema_type_row_class[ 'creative_work' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Publisher Org.', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_pub_org_id',
					'content'  => $form->get_no_select( 'schema_pub_org_id', $org_names,
						$css_class = 'long_name', $css_id = '', $is_assoc = true ) . $org_req_msg,
				),
				'schema_pub_person_id' => array(
					'tr_class' => $schema_type_row_class[ 'creative_work' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Publisher Person', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_pub_person_id',
					'content'  => $form->get_no_select( 'schema_pub_person_id', $person_names,
						$css_class = 'long_name', $css_id = '', $is_assoc = true ),
				),
				'schema_prov_org_id' => array(
					'tr_class' => $schema_type_row_class[ 'creative_work' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Service Prov. Org.', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_prov_org_id',
					'content'  => $form->get_no_select( 'schema_prov_org_id', $org_names,
						$css_class = 'long_name', $css_id = '', $is_assoc = true ) . $org_req_msg,
				),
				'schema_prov_person_id' => array(
					'tr_class' => $schema_type_row_class[ 'creative_work' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Service Prov. Person', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_prov_person_id',
					'content'  => $form->get_no_select( 'schema_prov_person_id', $person_names,
						$css_class = 'long_name', $css_id = '', $is_assoc = true ),
				),

				/**
				 * Schema Creative Work / Book / Audiobook.
				 */
				'subsection_schema_book_audio' => array(
					'tr_class' => $schema_type_row_class[ 'book_audio' ],
					'td_class' => 'subsection',
					'header'   => 'h5',
					'label'    => _x( 'Audiobook Information', 'metabox title', 'wpsso' ),
				),
				'schema_book_audio_duration_time' => array(
					'tr_class' => $schema_type_row_class[ 'book_audio' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Duration', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_book_audio_duration_time',
					'content'  => $this->get_input_time_dhms( $form ),
				),

				/**
				 * Schema Creative Work / How-To.
				 */
				'subsection_schema_howto' => array(
					'tr_class' => $schema_type_row_class[ 'how_to' ],
					'td_class' => 'subsection',
					'header'   => 'h5',
					'label'    => _x( 'How-To Information', 'metabox title', 'wpsso' ),
				),
				'schema_howto_yield' => array(
					'tr_class' => $schema_type_row_class[ 'how_to' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'How-To Makes', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_howto_yield',
					'content'  => $form->get_no_input_value( $value = '', 'long_name' ),
				),
				'schema_howto_prep_time' => array(
					'tr_class' => $schema_type_row_class[ 'how_to' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Preparation Time', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_howto_prep_time',
					'content'  => $this->get_input_time_dhms( $form ),
				),
				'schema_howto_total_time' => array(
					'tr_class' => $schema_type_row_class[ 'how_to' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Total Time', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_howto_total_time',
					'content'  => $this->get_input_time_dhms( $form ),
				),
				'schema_howto_supplies' => array(
					'tr_class' => $schema_type_row_class[ 'how_to' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'How-To Supplies', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_howto_supplies',
					'content'  => $form->get_no_input_value( $value = '', 'long_name', $css_id = '', '', $repeat = 5 ),
				),
				'schema_howto_tools' => array(
					'tr_class' => $schema_type_row_class[ 'how_to' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'How-To Tools', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_howto_tools',
					'content'  => $form->get_no_input_value( $value = '', 'long_name', $css_id = '', '', $repeat = 5 ),
				),
				'schema_howto_steps' => array(
					'tr_class' => $schema_type_row_class[ 'how_to' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'How-To Steps', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_howto_steps',
					'content'  => $form->get_no_mixed_multi( array(
						'schema_howto_step_section' => array(
							'input_type'    => 'radio',
							'input_class'   => 'howto_step_section',
							'input_content' => _x( '%1$s How-To Step or %2$s Step Group / Section:',
								'option label', 'wpsso' ),
							'input_values'  => array( 0, 1 ),
							'input_default' => 0,
						),
						'schema_howto_step' => array(
							'input_label' => _x( 'Name', 'option label', 'wpsso' ),
							'input_type'  => 'text',
							'input_class' => 'wide howto_step_name is_required',
						),
						'schema_howto_step_text' => array(
							'input_label' => _x( 'Description', 'option label', 'wpsso' ),
							'input_type'  => 'textarea',
							'input_class' => 'wide howto_step_text',
						),
						'schema_howto_step_img' => array(
							'input_label' => _x( 'Image ID', 'option label', 'wpsso' ),
							'input_type'  => 'image',
							'input_class' => 'howto_step_img',
						),
					), $css_class = '', $css_id = 'schema_howto_step', $start_num = 0, $max_input = 3, $show_first = 3 ),
				),

				/**
				 * Schema Creative Work / How-To / Recipe.
				 */
				'subsection_schema_recipe' => array(
					'tr_class' => $schema_type_row_class[ 'recipe' ],
					'td_class' => 'subsection',
					'header'   => 'h5',
					'label'    => _x( 'Recipe Information', 'metabox title', 'wpsso' ),
				),
				'schema_recipe_cuisine' => array(
					'tr_class' => $schema_type_row_class[ 'recipe' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Recipe Cuisine', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_recipe_cuisine',
					'content'  => $form->get_no_input_value( $value = '', 'long_name' ),
				),
				'schema_recipe_course' => array(
					'tr_class' => $schema_type_row_class[ 'recipe' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Recipe Course', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_recipe_course',
					'content'  => $form->get_no_input_value( $value = '', 'long_name' ),
				),
				'schema_recipe_yield' => array(
					'tr_class' => $schema_type_row_class[ 'recipe' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Recipe Makes', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_recipe_yield',
					'content'  => $form->get_no_input_value( $value = '', 'long_name' ),
				),
				'schema_recipe_cook_method' => array(
					'tr_class' => $schema_type_row_class[ 'recipe' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Cooking Method', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_recipe_cook_method',
					'content'  => $form->get_no_input_value( $value = '', 'long_name' ),
				),
				'schema_recipe_prep_time' => array(
					'tr_class' => $schema_type_row_class[ 'recipe' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Preparation Time', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_recipe_prep_time',
					'content'  => $this->get_input_time_dhms( $form ),
				),
				'schema_recipe_cook_time' => array(
					'tr_class' => $schema_type_row_class[ 'recipe' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Cooking Time', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_recipe_cook_time',
					'content'  => $this->get_input_time_dhms( $form ),
				),
				'schema_recipe_total_time' => array(
					'tr_class' => $schema_type_row_class[ 'recipe' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Total Time', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_recipe_total_time',
					'content'  => $this->get_input_time_dhms( $form ),
				),
				'schema_recipe_ingredients' => array(
					'tr_class' => $schema_type_row_class[ 'recipe' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Recipe Ingredients', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_recipe_ingredients',
					'content'  => $form->get_no_input_value( $value = '', 'long_name', $css_id = '', '', $repeat = 5 ),
				),
				'schema_recipe_instructions' => array(
					'tr_class' => $schema_type_row_class[ 'recipe' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Recipe Instructions', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_recipe_instructions',
					'content'  => $form->get_no_mixed_multi( array(
						'schema_recipe_instruction_section' => array(
							'input_type'    => 'radio',
							'input_class'   => 'recipe_instruction_section',
							'input_content' => _x( '%1$s Recipe Instruction or %2$s Instruction Group / Section:',
								'option label', 'wpsso' ),
							'input_values'  => array( 0, 1 ),
							'input_default' => 0,
						),
						'schema_recipe_instruction' => array(
							'input_label' => _x( 'Name', 'option label', 'wpsso' ),
							'input_type'  => 'text',
							'input_class' => 'wide recipe_instruction_name is_required',
						),
						'schema_recipe_instruction_text' => array(
							'input_label' => _x( 'Description', 'option label', 'wpsso' ),
							'input_type'  => 'textarea',
							'input_class' => 'wide recipe_instruction_text',
						),
						'schema_recipe_instruction_img' => array(
							'input_label' => _x( 'Image ID', 'option label', 'wpsso' ),
							'input_type'  => 'image',
							'input_class' => 'recipe_instruction_img',
						),
					), $css_class = '', $css_id = 'schema_recipe_instruction', $start_num = 0, $max_input = 3, $show_first = 3 ),
				),

				/**
				 * Schema Creative Work / How-To / Recipe - Nutrition Information.
				 */
				'subsection_schema_recipe_nutrition' => array(
					'tr_class' => $schema_type_row_class[ 'recipe' ],
					'td_class' => 'subsection',
					'header'   => 'h5',
					'label'    => _x( 'Nutrition Information per Serving', 'metabox title', 'wpsso' ),
				),
				'schema_recipe_nutri_serv' => array(
					'tr_class' => $schema_type_row_class[ 'recipe' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Serving Size', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_recipe_nutri_serv',
					'content'  => $form->get_no_input_value( $value = '', 'long_name is_required' ),
				),
				'schema_recipe_nutri_cal' => array(
					'tr_class' => $schema_type_row_class[ 'recipe' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Calories', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_recipe_nutri_cal',
					'content'  => $form->get_no_input_value( $value = '', 'medium' ) . ' ' . 
						_x( 'calories', 'option comment', 'wpsso' ),
				),
				'schema_recipe_nutri_prot' => array(
					'tr_class' => $schema_type_row_class[ 'recipe' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Protein', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_recipe_nutri_prot',
					'content'  => $form->get_no_input_value( $value = '', 'medium' ) . ' ' . 
						_x( 'grams of protein', 'option comment', 'wpsso' ),
				),
				'schema_recipe_nutri_fib' => array(
					'tr_class' => $schema_type_row_class[ 'recipe' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Fiber', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_recipe_nutri_fib',
					'content'  => $form->get_no_input_value( $value = '', 'medium' ) . ' ' . 
						_x( 'grams of fiber', 'option comment', 'wpsso' ),
				),
				'schema_recipe_nutri_carb' => array(
					'tr_class' => $schema_type_row_class[ 'recipe' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Carbohydrates', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_recipe_nutri_carb',
					'content'  => $form->get_no_input_value( $value = '', 'medium' ) . ' ' . 
						_x( 'grams of carbohydrates', 'option comment', 'wpsso' ),
				),
				'schema_recipe_nutri_sugar' => array(
					'tr_class' => $schema_type_row_class[ 'recipe' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Sugar', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_recipe_nutri_sugar',
					'content'  => $form->get_no_input_value( $value = '', 'medium' ) . ' ' . 
						_x( 'grams of sugar', 'option comment', 'wpsso' ),
				),
				'schema_recipe_nutri_sod' => array(
					'tr_class' => $schema_type_row_class[ 'recipe' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Sodium', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_recipe_nutri_sod',
					'content'  => $form->get_no_input_value( $value = '', 'medium' ) . ' ' . 
						_x( 'milligrams of sodium', 'option comment', 'wpsso' ),
				),
				'schema_recipe_nutri_fat' => array(
					'tr_class' => $schema_type_row_class[ 'recipe' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Fat', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_recipe_nutri_fat',
					'content'  => $form->get_no_input_value( $value = '', 'medium' ) . ' ' . 
						_x( 'grams of fat', 'option comment', 'wpsso' ),
				),
				'schema_recipe_nutri_sat_fat' => array(
					'tr_class' => $schema_type_row_class[ 'recipe' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Saturated Fat', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_recipe_nutri_sat_fat',
					'content'  => $form->get_no_input_value( $value = '', 'medium' ) . ' ' . 
						_x( 'grams of saturated fat', 'option comment', 'wpsso' ),
				),
				'schema_recipe_nutri_unsat_fat' => array(
					'tr_class' => $schema_type_row_class[ 'recipe' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Unsaturated Fat', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_recipe_nutri_unsat_fat',
					'content'  => $form->get_no_input_value( $value = '', 'medium' ) . ' ' . 
						_x( 'grams of unsaturated fat', 'option comment', 'wpsso' ),
				),
				'schema_recipe_nutri_trans_fat' => array(
					'tr_class' => $schema_type_row_class[ 'recipe' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Trans Fat', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_recipe_nutri_trans_fat',
					'content'  => $form->get_no_input_value( $value = '', 'medium' ) . ' ' . 
						_x( 'grams of trans fat', 'option comment', 'wpsso' ),
				),
				'schema_recipe_nutri_chol' => array(
					'tr_class' => $schema_type_row_class[ 'recipe' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Cholesterol', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_recipe_nutri_chol',
					'content'  => $form->get_no_input_value( $value = '', 'medium' ) . ' ' . 
						_x( 'milligrams of cholesterol', 'option comment', 'wpsso' ),
				),

				/**
				 * Schema Creative Work / Movie.
				 */
				'subsection_schema_movie' => array(
					'tr_class' => $schema_type_row_class[ 'movie' ],
					'td_class' => 'subsection',
					'header'   => 'h5',
					'label'    => _x( 'Movie Information', 'metabox title', 'wpsso' ),
				),
				'schema_movie_actor_person_names' => array(
					'tr_class' => $schema_type_row_class[ 'movie' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Cast Names', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_movie_actor_person_names',
					'content'  => $form->get_no_input_value( $value = '', $css_class = 'long_name', $css_id = '', '', $repeat = 5 ),
				),
				'schema_movie_director_person_names' => array(
					'tr_class' => $schema_type_row_class[ 'movie' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Director Names', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_movie_director_person_names',
					'content'  => $form->get_no_input_value( $value = '', $css_class = 'long_name', $css_id = '', '', $repeat = 2 ),
				),
				'schema_movie_prodco_org_id' => array(
					'tr_class' => $schema_type_row_class[ 'movie' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Production Company', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_movie_prodco_org_id',
					'content'  => $form->get_no_select( 'schema_movie_prodco_org_id', $org_names,
						$css_class = 'long_name', $css_id = '', $is_assoc = true ) . $org_req_msg,
				),
				'schema_movie_duration_time' => array(
					'tr_class' => $schema_type_row_class[ 'movie' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Movie Runtime', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_movie_duration_time',
					'content'  => $this->get_input_time_dhms( $form ),
				),

				/**
				 * Schema Creative Work / Review.
				 */
				'subsection_schema_review' => array(
					'tr_class' => $schema_type_row_class[ 'review' ],
					'td_class' => 'subsection',
					'header'   => 'h5',
					'label'    => _x( 'Review Information', 'metabox title', 'wpsso' ),
				),
				'schema_review_rating' => array(	// Included as schema.org/Rating, not schema.org/aggregateRating.
					'tr_class' => $schema_type_row_class[ 'review' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Review Rating', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_review_rating',
					'content'  => $form->get_no_input_value( $form->defaults[ 'schema_review_rating' ], 'short is_required' ) . ' ' .
						_x( 'from', 'option comment', 'wpsso' ) . ' ' . 
						$form->get_no_input_value( $form->defaults[ 'schema_review_rating_from' ], 'short' ) . ' ' .
						_x( 'to', 'option comment', 'wpsso' ) . ' ' . 
						$form->get_no_input_value( $form->defaults[ 'schema_review_rating_to' ], 'short' ),
				),
				'schema_review_rating_alt_name' => array(
					'tr_class' => $schema_type_row_class[ 'review' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Rating Value Name', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_review_rating_alt_name',
					'content'  => $form->get_no_input_value(),
				),

				/**
				 * Schema Creative Work / Review - Subject.
				 */
				'subsection_schema_review_item' => array(
					'tr_class' => $schema_type_row_class[ 'review' ],
					'td_class' => 'subsection',
					'header'   => 'h4',
					'label'    => _x( 'Subject of the Review', 'metabox title', 'wpsso' ),
				),
				'schema_review_item_type' => array(
					'tr_class' => $schema_type_row_class[ 'review' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Subject Webpage Type', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_review_item_type',
					'content'  => $form->get_no_select( 'schema_review_item_type', $schema_types,
						$css_class = 'schema_type', $css_id = '', $is_assoc = true,
							$selected = false, $event_names = array( 'on_focus_load_json', 'on_show_unhide_rows' ),
								$event_args = array(
									'json_var'  => 'schema_types',
									'exp_secs'  => WPSSO_CACHE_SELECT_JSON_EXP_SECS,	// Create and read from a javascript URL.
									'is_transl' => true,					// No label translation required.
									'is_sorted' => true,					// No label sorting required.
								) ),
				),
				'schema_review_item_url' => array(
					'tr_class' => $schema_type_row_class[ 'review' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Subject Webpage URL', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_review_item_url',
					'content'  => $form->get_no_input_value( $value = '', $css_class = 'wide is_required' ),
				),
				'schema_review_item_sameas_url' => array(
					'tr_class' => $form->get_css_class_hide_prefix( 'basic', 'schema_review_item_sameas_url' ),
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Subject Same-As URLs', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_review_item_sameas_url',
					'content'  => $form->get_no_input_value( $value = '', $css_class = 'wide', $css_id = '', '', $repeat = 2 ),
				),
				'schema_review_item_name' => array(
					'tr_class' => $schema_type_row_class[ 'review' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Subject Name', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_review_item_name',
					'content'  => $form->get_no_input_value( $value = '', $css_class = 'wide is_required' ),
				),
				'schema_review_item_desc' => array(
					'tr_class' => $schema_type_row_class[ 'review' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Subject Description', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_review_item_desc',
					'content'  => $form->get_no_textarea_value( '' ),
				),
				'schema_review_item_img_id' => array(
					'tr_class' => $schema_type_row_class[ 'review' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Subject Image ID', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_review_item_img_id',
					'content'  => $form->get_no_input_image_upload( 'schema_review_item_img' ),
				),
				'schema_review_item_img_url' => array(
					'tr_class' => $schema_type_row_class[ 'review' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'or an Image URL', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_review_item_img_url',
					'content'  => $form->get_no_input_value( $value = '' ),
				),

				/**
				 * Schema Creative Work / Review - Subject: Creative Work.
				 */
				'subsection_schema_review_item_cw' => array(
					'tr_class' => 'hide_schema_type ' . $schema_review_item_type_row_class[ 'creative_work' ],
					'td_class' => 'subsection',
					'header'   => 'h5',
					'label'    => _x( 'Creative Work Subject Information', 'metabox title', 'wpsso' ),
				),
				'schema_review_item_cw_author_type' => array(
					'tr_class' => 'hide_schema_type ' . $schema_review_item_type_row_class[ 'creative_work' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'C.W. Author Type', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_review_item_cw_author_type',
					'content'  => $form->get_no_select( 'schema_review_item_cw_author_type', $this->p->cf[ 'form' ][ 'author_types' ] ),
				),
				'schema_review_item_cw_author_name' => array(
					'tr_class' => 'hide_schema_type ' . $schema_review_item_type_row_class[ 'creative_work' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'C.W. Author Name', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_review_item_cw_author_name',
					'content'  => $form->get_no_input_value( $value = '', $css_class = 'wide' ),
				),
				'schema_review_item_cw_author_url' => array(
					'tr_class' => 'hide_schema_type ' . $schema_review_item_type_row_class[ 'creative_work' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'C.W. Author URL', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_review_item_cw_author_url',
					'content'  => $form->get_no_input_value( $value = '', $css_class = 'wide' ),
				),
				'schema_review_item_cw_pub' => array(
					'tr_class' => 'hide_schema_type ' . $schema_review_item_type_row_class[ 'creative_work' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'C.W. Published Date', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_review_item_cw_pub',
					'content'  => $form->get_no_date_time_tz( 'schema_review_item_cw_pub' ),
				),
				'schema_review_item_cw_created' => array(
					'tr_class' => 'hide_schema_type ' . $schema_review_item_type_row_class[ 'creative_work' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'C.W. Created Date', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_review_item_cw_created',
					'content'  => $form->get_no_date_time_tz( 'schema_review_item_cw_created' ),
				),

				/**
				 * Schema Creative Work / Review / Claim Review.
				 */
				'subsection_schema_review_claim' => array(
					'tr_class' => $schema_type_row_class[ 'review_claim' ],
					'td_class' => 'subsection',
					'header'   => 'h5',
					'label'    => _x( 'Claim Subject Information', 'metabox title', 'wpsso' ),
				),
				'schema_review_claim_reviewed' => array(
					'tr_class' => $schema_type_row_class[ 'review_claim' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Short Summary of Claim', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_review_claim_reviewed',
					'content'  => $form->get_no_input_value( $value = '', $css_class = 'wide' ),
				),
				'schema_review_claim_first_url' => array(
					'tr_class' => $schema_type_row_class[ 'review_claim' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'First Appearance URL', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_review_claim_first_url',
					'content'  => $form->get_no_input_value( $value = '', $css_class = 'wide' ),
				),

				/**
				 * Schema Creative Work / Software Application.
				 */
				'subsection_schema_software_app' => array(
					'tr_class' => $schema_type_row_class[ 'software_app' ],
					'td_class' => 'subsection',
					'header'   => 'h5',
					'label'    => _x( 'Software Application Information', 'metabox title', 'wpsso' ),
				),
				'schema_software_app_os' => array(
					'tr_class' => $schema_type_row_class[ 'software_app' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Operating System', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_software_app_os',
					'content'  => $form->get_no_input_value( $value = '', $css_class = 'wide' ),
				),
				'schema_software_app_cat' => array(
					'tr_class' => $schema_type_row_class[ 'software_app' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Application Category', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_software_app_cat',
					'content'  => $form->get_no_input_value( $value = '', $css_class = 'wide' ),
				),

				/**
				 * Schema Creative Work / Web Page / QA Page.
				 */
				'subsection_schema_qa' => array(
					'tr_class' => $schema_type_row_class[ 'qa' ],
					'td_class' => 'subsection',
					'header'   => 'h5',
					'label'    => _x( 'QA Page Information', 'metabox title', 'wpsso' ),
				),
				'schema_qa_desc' => array(
					'tr_class' => $schema_type_row_class[ 'qa' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'QA Heading', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_qa_desc',
					'content'  => $form->get_no_input_value( $value = '', $css_class = 'wide' ),
				),

				/**
				 * Schema Event.
				 */
				'subsection_schema_event' => array(
					'tr_class' => $schema_type_row_class[ 'event' ],
					'td_class' => 'subsection',
					'header'   => 'h5',
					'label'    => _x( 'Event Information', 'metabox title', 'wpsso' ),
				),
				'schema_event_lang' => array(
					'tr_class' => $schema_type_row_class[ 'event' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Event Language', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_event_lang',
					'content'  => $form->get_no_select( 'schema_event_lang', SucomUtil::get_available_locales(), 'locale' ),
				),
				'schema_event_attendance' => array(
					'tr_class' => $schema_type_row_class[ 'event' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Event Attendance', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_event_attendance',
					'content'  => $form->get_no_select( 'schema_event_attendance', $this->p->cf[ 'form' ][ 'event_attendance' ],
						$css_class = '', $css_id = '', $is_assoc = true ),
				),
				'schema_event_online_url' => array(
					'tr_class' => $schema_type_row_class[ 'event' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Event Online URL', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_event_online_url',
					'content'  => $form->get_no_input_value( $value = '', $css_class = 'wide' ),
				),
				'schema_event_location_id' => array(
					'tr_class' => $schema_type_row_class[ 'event' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Event Physical Venue', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_event_location_id',
					'content'  => $form->get_no_select( 'schema_event_location_id', $place_names,
						$css_class = 'long_name', $css_id = '', $is_assoc = true ) . $plm_req_msg,
				),
				'schema_event_organizer_org_id' => array(
					'tr_class' => $schema_type_row_class[ 'event' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Organizer Org.', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_event_organizer_org_id',
					'content'  => $form->get_no_select( 'schema_event_organizer_org_id', $org_names,
						$css_class = 'long_name', $css_id = '', $is_assoc = true ) . $org_req_msg,
				),
				'schema_event_organizer_person_id' => array(
					'tr_class' => $schema_type_row_class[ 'event' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Organizer Person', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_event_organizer_person_id',
					'content'  => $form->get_no_select( 'schema_event_organizer_person_id', $person_names,
						$css_class = 'long_name' ),
				),
				'schema_event_performer_org_id' => array(
					'tr_class' => $schema_type_row_class[ 'event' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Performer Org.', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_event_performer_org_id',
					'content'  => $form->get_no_select( 'schema_event_performer_org_id', $org_names,
						$css_class = 'long_name', $css_id = '', $is_assoc = true ) . $org_req_msg,
				),
				'schema_event_performer_person_id' => array(
					'tr_class' => $schema_type_row_class[ 'event' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Performer Person', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_event_performer_person_id',
					'content'  => $form->get_no_select( 'schema_event_performer_person_id', $person_names,
						$css_class = 'long_name' ),
				),
				'schema_event_status' => array(
					'tr_class' => $schema_type_row_class[ 'event' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Event Status', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_event_status',
					'content'  => $form->get_no_select( 'schema_event_status', $this->p->cf[ 'form' ][ 'event_status' ],
						$css_class = '', $css_id = '', $is_assoc = true ),
				),
				'schema_event_start' => array(
					'tr_class' => $schema_type_row_class[ 'event' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Event Start', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_event_start',
					'content'  => $form->get_no_date_time_tz( 'schema_event_start' ),
				),
				'schema_event_end' => array(
					'tr_class' => $schema_type_row_class[ 'event' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Event End', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_event_end',
					'content'  => $form->get_no_date_time_tz( 'schema_event_end' ),
				),
				'schema_event_previous' => array(
					'tr_class' => $schema_type_row_class[ 'event' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Event Previous Start', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_event_previous',
					'content'  => $form->get_no_date_time_tz( 'schema_event_previous' ),
				),
				'schema_event_offers_start' => array(
					'tr_class' => $schema_type_row_class[ 'event' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Event Offers Start', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_event_offers_start',
					'content'  => $form->get_no_date_time_tz( 'schema_event_offers_start' ),
				),
				'schema_event_offers_end' => array(
					'tr_class' => $schema_type_row_class[ 'event' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Event Offers End', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_event_offers_end',
					'content'  => $form->get_no_date_time_tz( 'schema_event_offers_end' ),
				),
				'schema_event_offers' => array(
					'tr_class' => $schema_type_row_class[ 'event' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Event Offers', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_event_offers',
					'content'  => $form->get_no_mixed_multi( array(
						'schema_event_offer_name' => array(
							'input_title' => _x( 'Event Offer Name', 'option label', 'wpsso' ),
							'input_type'  => 'text',
							'input_class' => 'offer_name',
						),
						'schema_event_offer_price' => array(
							'input_title' => _x( 'Event Offer Price', 'option label', 'wpsso' ),
							'input_type'  => 'text',
							'input_class' => 'price',
						),
						'schema_event_offer_currency' => array(
							'input_title'    => _x( 'Event Offer Currency', 'option label', 'wpsso' ),
							'input_type'     => 'select',
							'input_class'    => 'currency',
							'select_options' => $currencies,
							'select_default' => $this->p->options[ 'og_def_currency' ],
						),
						'schema_event_offer_avail' => array(
							'input_title'    => _x( 'Event Offer Availability', 'option label', 'wpsso' ),
							'input_type'     => 'select',
							'input_class'    => 'stock',
							'select_options' => $this->p->cf[ 'form' ][ 'item_availability' ],
							'select_default' => 'https://schema.org/InStock',
						),
					), $css_class = 'single_line', $css_id = 'schema_event_offer',
						$start_num = 0, $max_input = 2, $show_first = 2 ),
				),

				/**
				 * Schema Intangible / Job Posting.
				 */
				'subsection_schema_job' => array(
					'tr_class' => $schema_type_row_class[ 'job_posting' ],
					'td_class' => 'subsection',
					'header'   => 'h5',
					'label'    => _x( 'Job Posting Information', 'metabox title', 'wpsso' ),
				),
				'schema_job_title' => array(
					'tr_class' => $schema_type_row_class[ 'job_posting' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Job Title', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_job_title',
					'content'  => $form->get_no_input_value( $def_schema_title, $css_class = 'wide' ),
				),
				'schema_job_hiring_org_id' => array(
					'tr_class' => $schema_type_row_class[ 'job_posting' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Hiring Organization', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_job_hiring_org_id',
					'content'  => $form->get_no_select( 'schema_job_hiring_org_id', $org_names,
						$css_class = 'long_name', $css_id = '', $is_assoc = true ) . $org_req_msg,
				),
				'schema_job_location_id' => array(
					'tr_class' => $schema_type_row_class[ 'job_posting' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Job Location', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_job_location_id',
					'content'  => $form->get_no_select( 'schema_job_location_id', $place_names,
						$css_class = 'long_name', $css_id = '', $is_assoc = true ) . $plm_req_msg,
				),
				'schema_job_location_type' => array(
					'tr_class' => $schema_type_row_class[ 'job_posting' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Job Location Type', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_job_location_type',
					'content'  => $form->get_no_select( 'schema_job_location_type', $this->p->cf[ 'form' ][ 'job_location_type' ],
						$css_class = 'long_name', $css_id = '', $is_assoc = true ),
				),
				'schema_job_salary' => array(
					'tr_class' => $schema_type_row_class[ 'job_posting' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Base Salary', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_job_salary',
					'content'  => $form->get_no_input_value( $value = '', $css_class = 'medium' ) . ' ' . 
						$form->get_no_select( 'schema_job_salary_currency', $currencies, $css_class = 'currency' ) . ' ' . 
						_x( 'per', 'option comment', 'wpsso' ) . ' ' . 
						$form->get_no_select( 'schema_job_salary_period', $this->p->cf[ 'form' ][ 'time_text' ], 'short' ),
				),
				'schema_job_empl_type' => array(
					'tr_class' => $schema_type_row_class[ 'job_posting' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Employment Type', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_job_empl_type',
					'content'  => $form->get_no_checklist( 'schema_job_empl_type', $this->p->cf[ 'form' ][ 'employment_type' ] ),
				),
				'schema_job_expire' => array(
					'tr_class' => $schema_type_row_class[ 'job_posting' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Job Posting Expires', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_job_expire',
					'content'  => $form->get_no_date_time_tz( 'schema_job_expire' ),
				),

				/**
				 * Schema Organization.
				 */
				'subsection_schema_organization' => array(
					'tr_class' => $schema_type_row_class[ 'organization' ],
					'td_class' => 'subsection',
					'header'   => 'h5',
					'label'    => _x( 'Organization Information', 'metabox title', 'wpsso' ),
				),
				'schema_organization_id' => array(
					'tr_class' => $schema_type_row_class[ 'organization' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Select an Organization', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_organization_id',
					'content'  => $form->get_no_select( 'schema_organization_id', $org_names,
						$css_class = 'long_name', $css_id = '', $is_assoc = true ) . $org_req_msg,
				),

				/**
				 * Schema Person.
				 */
				'subsection_schema_person' => array(
					'tr_class' => $schema_type_row_class[ 'person' ],
					'td_class' => 'subsection',
					'header'   => 'h5',
					'label'    => _x( 'Person Information', 'metabox title', 'wpsso' ),
				),
				'schema_person_id' => array(
					'tr_class' => $schema_type_row_class[ 'person' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Select a Person', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_person_id',
					'content'  => $form->get_no_select( 'schema_person_id', $person_names,
						$css_class = 'long_name' ),
				),

				/**
				 * Schema Place.
				 */
				'subsection_schema_place' => array(
					'tr_class' => $schema_type_row_class[ 'place' ],
					'td_class' => 'subsection',
					'header'   => 'h5',
					'label'    => _x( 'Place Information', 'metabox title', 'wpsso' ),
				),
				'schema_place_id' => array(
					'tr_class' => $schema_type_row_class[ 'place' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Select a Place', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_place_id',
					'content'  => $form->get_no_select( 'schema_place_id', $place_names_custom,
						$css_class = 'long_name', $css_id = '', $is_assoc = true,
							 $selected = true, $event_names = 'on_show_unhide_rows' ) . $plm_req_msg,
				),

				/**
				 * Schema Product.
				 *
				 * Note that unlike most schema option names, product options start with 'product_' and not 'schema_'.
				 */
				'subsection_product' => array(
					'tr_class' => $schema_type_row_class[ 'product' ],
					'td_class' => 'subsection',
					'header'   => 'h5',
					'label'    => _x( 'Product Information', 'metabox title', 'wpsso' ),
				),
				'schema_product_category' => array(
					'tr_class' => $schema_type_row_class[ 'product' ],
					'th_class' => 'medium',
					'label'    => _x( 'Product Type', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-product_category',
					'content'  => $form->get_no_select( 'product_category', $product_categories, $css_class = 'wide', $css_id = '', $is_assoc = true ),
				),
				'schema_product_brand' => array(
					'tr_class' => $schema_type_row_class[ 'product' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Product Brand', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-product_brand',
					'content'  => $form->get_no_input( 'product_brand', $css_class = '', $css_id = '', $holder = true ),
				),
				'schema_product_price' => array(
					'tr_class' => $schema_type_row_class[ 'product' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Product Price', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-product_price',
					'content'  => $form->get_no_input( 'product_price', $css_class = 'price', $css_id = '', $holder = true ) . ' ' .
						$form->get_no_select( 'product_currency', $currencies, $css_class = 'currency' ) .
							( empty( $this->p->avail[ 'ecom' ][ 'woocommerce' ] ) ? '' :
								' ' . __( 'for simple or main product' ) ),
				),
				'schema_product_avail' => array(
					'tr_class' => $schema_type_row_class[ 'product' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Product Availability', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-product_avail',
					'content'  => $form->get_no_select( 'product_avail', $this->p->cf[ 'form' ][ 'item_availability' ],
						$css_class = '', $css_id = '', $is_assoc = true ),
				),
				'schema_product_condition' => array(
					'tr_class' => $schema_type_row_class[ 'product' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Product Condition', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-product_condition',
					'content'  => $form->get_no_select( 'product_condition', $this->p->cf[ 'form' ][ 'item_condition' ],
						$css_class = '', $css_id = '', $is_assoc = true ),
				),
				'schema_product_material' => array(
					'tr_class' => $schema_type_row_class[ 'product' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Product Material', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-product_material',
					'content'  => $form->get_no_input( 'product_material', $css_class = '', $css_id = '', $holder = true ),
				),
				'schema_product_color' => array(
					'tr_class' => $schema_type_row_class[ 'product' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Product Color', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-product_color',
					'content'  => $form->get_no_input( 'product_color', $css_class = '', $css_id = '', $holder = true ),
				),
				'schema_product_target_gender' => array(
					'tr_class' => $schema_type_row_class[ 'product' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Product Target Gender', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-product_target_gender',
					'content'  => $form->get_no_select( 'product_target_gender', $this->p->cf[ 'form' ][ 'audience_gender' ],
						$css_class = 'gender', $css_id = '', $is_assoc = true ),
				),
				'schema_product_size' => array(
					'tr_class' => $schema_type_row_class[ 'product' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Product Size', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-product_size',
					'content'  => $form->get_no_input( 'product_size', $css_class = '', $css_id = '', $holder = true ),
				),
				'schema_product_weight_value' => array(
					'tr_class' => $schema_type_row_class[ 'product' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Product Weight', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-product_weight_value',
					'content'  => $form->get_no_input( 'product_weight_value', $css_class = '', $css_id = '', $holder = true ) .
						WpssoAdmin::get_option_unit_comment( 'product_weight_value' ),
				),
				'schema_product_length_value' => array(
					'tr_class' => $schema_type_row_class[ 'product' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Product Length', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-product_length_value',
					'content'  => $form->get_no_input( 'product_length_value', '', $css_id = '', $holder = true ) .
						WpssoAdmin::get_option_unit_comment( 'product_length_value' ),
				),
				'schema_product_width_value' => array(
					'tr_class' => $schema_type_row_class[ 'product' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Product Width', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-product_width_value',
					'content'  => $form->get_no_input( 'product_width_value', '', $css_id = '', $holder = true ) .
						WpssoAdmin::get_option_unit_comment( 'product_width_value' ),
				),
				'schema_product_height_value' => array(
					'tr_class' => $schema_type_row_class[ 'product' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Product Height', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-product_height_value',
					'content'  => $form->get_no_input( 'product_height_value', '', $css_id = '', $holder = true ) .
						WpssoAdmin::get_option_unit_comment( 'product_height_value' ),
				),
				'schema_product_depth_value' => array(
					'tr_class' => $schema_type_row_class[ 'product' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Product Depth', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-product_depth_value',
					'content'  => $form->get_no_input( 'product_depth_value', '', $css_id = '', $holder = true ) .
						WpssoAdmin::get_option_unit_comment( 'product_depth_value' ),
				),
				'schema_product_fluid_volume_value' => array(
					'tr_class' => $schema_type_row_class[ 'product' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Product Fluid Volume', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-product_fluid_volume_value',
					'content'  => $form->get_no_input( 'product_fluid_volume_value', '', $css_id = '', $holder = true ) .
						WpssoAdmin::get_option_unit_comment( 'product_fluid_volume_value' ),
				),
				'schema_product_retailer_part_no' => array(
					'tr_class' => $schema_type_row_class[ 'product' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Product SKU', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-product_retailer_part_no',
					'content'  => $form->get_no_input( 'product_retailer_part_no', $css_class = '', $css_id = '', $holder = true ),
				),
				'schema_product_mfr_part_no' => array(
					'tr_class' => $schema_type_row_class[ 'product' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Product MPN', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-product_mfr_part_no',
					'content'  => $form->get_no_input( 'product_mfr_part_no', $css_class = '', $css_id = '', $holder = true ),
				),
				'schema_product_gtin14' => array(
					'tr_class' => $schema_type_row_class[ 'product' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Product GTIN-14', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-product_gtin14',
					'content'  => $form->get_no_input( 'product_gtin14', '', $css_id = '', $holder = true ),
				),
				'schema_product_gtin13' => array(
					'tr_class' => $schema_type_row_class[ 'product' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Product GTIN-13 (EAN)', 'option label', 'wpsso' ),	// aka Product EAN.
					'tooltip'  => 'meta-product_gtin13',
					'content'  => $form->get_no_input( 'product_gtin13', '', $css_id = '', $holder = true ),
				),
				'schema_product_gtin12' => array(
					'tr_class' => $schema_type_row_class[ 'product' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Product GTIN-12 (UPC)', 'option label', 'wpsso' ),	// aka Product UPC.
					'tooltip'  => 'meta-product_gtin12',
					'content'  => $form->get_no_input( 'product_gtin12', '', $css_id = '', $holder = true ),
				),
				'schema_product_gtin8' => array(
					'tr_class' => $schema_type_row_class[ 'product' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Product GTIN-8', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-product_gtin8',
					'content'  => $form->get_no_input( 'product_gtin8', '', $css_id = '', $holder = true ),
				),
				'schema_product_gtin' => array(
					'tr_class' => $schema_type_row_class[ 'product' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Product GTIN', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-product_gtin',
					'content'  => $form->get_no_input( 'product_gtin', '', $css_id = '', $holder = true ),
				),
				'schema_product_isbn' => array(
					'tr_class' => $schema_type_row_class[ 'product' ],
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Product ISBN', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-product_isbn',
					'content'  => $form->get_no_input( 'product_isbn', $css_class = '', $css_id = '', $holder = true ),
				),
			);

			$table_rows = $form->get_md_form_rows( $table_rows, $form_rows, $head_info, $mod );

			return apply_filters( 'wpsso_metabox_sso_edit_schema_rows', $table_rows, $form, $head_info, $mod );
		}

		public function filter_metabox_sso_media_rows( $table_rows, $form, $head_info, $mod ) {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			/**
			 * Default priority media.
			 */
			$max_media_items = $this->p->cf[ 'form' ][ 'max_media_items' ];
			$size_name       = 'wpsso-opengraph';
			$media_request   = array( 'pid', 'img_url' );
			$media_info      = $this->p->og->get_media_info( $size_name, $media_request, $mod, $md_pre = 'none' );

			$form_rows = array(
				'info_priority_media' => array(
					'table_row' => '<td colspan="2">' . $this->p->msgs->get( 'info-meta-priority-media' ) . '</td>',
				),
				'subsection_opengraph' => array(
					'td_class' => 'subsection top',
					'header'   => 'h4',
					'label'    => _x( 'Default Priority Media', 'metabox title', 'wpsso' ),
				),
				'pro_feature_msg_opengraph' => array(
					'table_row' => '<td colspan="2">' . $this->p->msgs->pro_feature( 'wpsso' ) . '</td>',
				),
				'subsection_priority_image' => array(
					'td_class' => 'subsection top',
					'header'   => 'h5',
					'label'    => _x( 'Priority Image Information', 'metabox title', 'wpsso' )
				),
				'og_img_max' => $mod[ 'is_post' ] ? array(
					'tr_class' => $form->get_css_class_hide( 'basic', 'og_img_max' ),
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Maximum Images', 'option label', 'wpsso' ),
					'tooltip'  => 'og_img_max',		// Use tooltip message from settings.
					'content'  => $form->get_select( 'og_img_max', range( 0, $max_media_items ), $css_class = 'medium' ),
				) : '',	// Placeholder if not a post module.
				'og_img_id' => array(
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Image ID', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-og_img_id',
					'content'  => $form->get_no_input_image_upload( 'og_img', $media_info[ 'pid' ] ),
				),
				'og_img_url' => array(
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'or an Image URL', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-og_img_url',
					'content'  => $form->get_no_input_holder( $media_info[ 'img_url' ], $css_class = 'wide' ),
				),
				'subsection_priority_video' => array(
					'td_class'     => 'subsection',
					'header'       => 'h5',
					'label'        => _x( 'Priority Video Information', 'metabox title', 'wpsso' )
				),
				'pro_feature_msg_video_api' => array(
					'table_row' => '<td colspan="2">' . $this->p->msgs->pro_feature_video_api() . '</td>',
				),
				'og_vid_prev_img' => array(
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Include Preview Images', 'option label', 'wpsso' ),
					'tooltip'  => 'og_vid_prev_img',	// Use the tooltip from plugin settings.
					'content'  => $form->get_no_checkbox( 'og_vid_prev_img' ) . $this->p->msgs->preview_images_are_first(),
				),
				'og_vid_max' => $mod[ 'is_post' ] ? array(
					'tr_class' => $form->get_css_class_hide( 'basic', 'og_vid_max' ),
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Maximum Videos', 'option label', 'wpsso' ),
					'tooltip'  => 'og_vid_max',	// Use the tooltip from plugin settings.
					'content'  => $form->get_no_select( 'og_vid_max', range( 0, $max_media_items ), $css_class = 'medium' ),
				) : '',	// Add a placeholder if not a post module.
				'og_vid_dimensions' => array(
					'tr_class' => $form->get_css_class_hide_vid_dim( 'basic', 'og_vid' ),
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Video Dimensions', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-og_vid_dimensions',
					'content'  => $form->get_no_input_video_dimensions( 'og_vid' ),
				),
				'og_vid_embed' => array(
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Video Embed HTML', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-og_vid_embed',
					'content'  => $form->get_no_textarea_value( $value = '' ),	// The Standard plugin does not include video modules.
				),
				'og_vid_url' => array(
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'or a Video URL', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-og_vid_url',
					'content'  => $form->get_no_input_value( $value = '', $css_class = 'wide' ),	// The Standard plugin does not include video modules.
				),
				'og_vid_title' => array(
					'tr_class' => $form->get_css_class_hide( 'basic', 'og_vid_title' ),
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Video Name / Title', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-og_vid_title',
					'content'  => $form->get_no_input_value( $value = '', $css_class = 'wide' ),	// The Standard plugin does not include video modules.
				),
				'og_vid_desc' => array(
					'tr_class' => $form->get_css_class_hide( 'basic', 'og_vid_desc' ),
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Video Description', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-og_vid_desc',
					'content'  => $form->get_no_textarea_value( '' ),	// The Standard plugin does not include video modules.
				),
			);

			/**
			 * Pinterest Pin It.
			 */
			$size_name        = 'wpsso-pinterest';
			$media_request = array( 'pid', 'img_url' );
			$media_info       = $this->p->og->get_media_info( $size_name, $media_request, $mod, $md_pre = array( 'schema', 'og' ) );
			$pin_img_disabled = empty( $this->p->options[ 'pin_add_img_html' ] ) ? true : false;
			$pin_img_msg      = $pin_img_disabled ? $this->p->msgs->pin_img_disabled() : '';
			$row_class        = ! $pin_img_disabled && $form->in_options( '/^pin_img_/' ) ? '' : 'hide_in_basic';

			$form_rows[ 'subsection_pinterest' ] = array(
				'tr_class' => $row_class,
				'td_class' => 'subsection',
				'header'   => 'h4',
				'label'    => _x( 'Pinterest Pin It', 'metabox title', 'wpsso' ),
			);

			$form_rows[ 'pro_feature_msg_pinterest' ] = array(
				'tr_class'  => $row_class,
				'table_row' => '<td colspan="2">' . $this->p->msgs->pro_feature( 'wpsso' ) . '</td>',
			);

			$form_rows[ 'pin_img_id' ] = array(
				'tr_class' => $row_class,
				'th_class' => 'medium',
				'td_class' => 'blank',
				'label'    => _x( 'Image ID', 'option label', 'wpsso' ),
				'tooltip'  => 'meta-pin_img_id',
				'content'  => $form->get_no_input_image_upload( 'pin_img', $media_info[ 'pid' ] ),
			);

			$form_rows[ 'pin_img_url' ] = array(
				'tr_class' => $row_class,
				'th_class' => 'medium',
				'td_class' => 'blank',
				'label'    => _x( 'or an Image URL', 'option label', 'wpsso' ),
				'tooltip'  => 'meta-pin_img_url',
				'content'  => $form->get_no_input_holder( $media_info[ 'img_url' ], $css_class = 'wide' ) . ' ' . $pin_img_msg,
			);

			/**
			 * Twitter Card.
			 *
			 * App and Player cards do not have a $size_name.
			 *
			 * Only show custom image options for the Summary and Summary Large Image cards. 
			 */
			list( $card_type, $card_label, $size_name, $tc_prefix ) = $this->p->tc->get_card_info( $mod, $head_info );

			if ( ! empty( $size_name ) ) {

				$media_request = array( 'pid', 'img_url' );
				$media_info    = $this->p->og->get_media_info( $size_name, $media_request, $mod, $md_pre = 'og' );
				$row_class     = $form->in_options( '/^' . $tc_prefix . '_img_/' ) ? '' : 'hide_in_basic';

				$form_rows[ 'subsection_tc' ] = array(
					'tr_class' => $row_class,
					'td_class' => 'subsection',
					'header'   => 'h4',
					'label'    => $card_label,
				);

				$form_rows[ 'pro_feature_msg_tc' ] = array(
					'tr_class'  => $row_class,
					'table_row' => '<td colspan="2">' . $this->p->msgs->pro_feature( 'wpsso' ) . '</td>',
				);

				$form_rows[ $tc_prefix . '_img_id' ] = array(
					'tr_class' => $row_class,
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Image ID', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-' . $tc_prefix . '_img_id',
					'content'  => $form->get_no_input_image_upload( $tc_prefix . '_img', $media_info[ 'pid' ] ),
				);

				$form_rows[ $tc_prefix . '_img_url' ] = array(
					'tr_class' => $row_class,
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'or an Image URL', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-' . $tc_prefix . '_img_url',
					'content'  => $form->get_no_input_holder( $media_info[ 'img_url' ], $css_class = 'wide' ),
				);
			}

			$table_rows = $form->get_md_form_rows( $table_rows, $form_rows, $head_info, $mod );

			/**
			 * Since WPSSO Core v9.0.0.
			 *
			 * Provides backwards compatibility for older WPSSO JSON add-ons.
			 */
			if ( ! empty( $this->p->avail[ 'p_ext' ][ 'json' ] ) ) {

				$json_version = WpssoJsonConfig::get_version();

				if ( version_compare( $json_version, '5.0.0', '<' ) ) {

					$table_rows[ 'subsection_schema' ] = '<td class="subsection" colspan="2"><h4>' .
						_x( 'Schema JSON-LD Markup / Google Rich Results', 'metabox title', 'wpsso' ) . '</h4></td>';
				}
			}

			return $table_rows;
		}

		public function filter_metabox_sso_media_schema_rows( $table_rows, $form, $head_info, $mod ) {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			/**
			 * Since WPSSO Core v9.0.0.
			 *
			 * Provides backwards compatibility for older WPSSO JSON add-ons.
			 */
			if ( ! empty( $this->p->avail[ 'p_ext' ][ 'json' ] ) ) {

				if ( $this->p->check->pp( 'wpssojson' ) ) {	// Nothing to do.

					return apply_filters( 'wpsso_metabox_sso_media_schema_rows', $table_rows, $form, $head_info, $mod );
				}

				$json_version = WpssoJsonConfig::get_version();

				if ( version_compare( $json_version, '5.0.0', '<' ) ) {

					$table_rows = SucomUtil::preg_grep_keys( $this->old_schema_preg, $table_rows, $invert = true );
				}
			}

			$max_media_items = $this->p->cf[ 'form' ][ 'max_media_items' ];
			$size_names      = $this->p->util->get_image_size_names( 'schema' );	// Always returns an array.
			$size_name       = reset( $size_names );
			$media_request   = array( 'pid', 'img_url' );
			$media_info      = $this->p->og->get_media_info( $size_name, $media_request, $mod, $md_pre = 'og' );

			$form_rows = array(
				'subsection_schema' => array(
					'td_class' => 'subsection',
					'header'   => 'h4',
					'label'    => _x( 'Schema JSON-LD Markup / Google Rich Results', 'metabox title', 'wpsso' )
				),
				'pro_feature_msg_schema' => array(
					'table_row' => '<td colspan="2">' . $this->p->msgs->pro_feature( 'wpsso' ) . '</td>',
				),
				'schema_img_max' => $mod[ 'is_post' ] ? array(
					'tr_class' => $form->get_css_class_hide( 'basic', 'schema_img_max' ),
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Maximum Images', 'option label', 'wpsso' ),
					'tooltip'  => 'schema_img_max',	// Use tooltip message from settings.
					'content'  => $form->get_no_select( 'schema_img_max', range( 0, $max_media_items ), $css_class = 'medium' ),
				) : '',	// Add a placeholder if not a post module.
				'schema_img_id' => array(
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'Image ID', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_img_id',
					'content'  => $form->get_no_input_image_upload( 'schema_img', $media_info[ 'pid' ] ),
				),
				'schema_img_url' => array(
					'th_class' => 'medium',
					'td_class' => 'blank',
					'label'    => _x( 'or an Image URL', 'option label', 'wpsso' ),
					'tooltip'  => 'meta-schema_img_url',
					'content'  => $form->get_no_input_value( $value = '', $css_class = 'wide', $css_id = '', $media_info[ 'img_url' ] ),
				),
			);

			$table_rows = $form->get_md_form_rows( $table_rows, $form_rows, $head_info, $mod );

			return apply_filters( 'wpsso_metabox_sso_media_schema_rows', $table_rows, $form, $head_info, $mod );
		}

		private function get_input_time_dhms( $form ) {

			static $days_sep  = null;
			static $hours_sep = null;
			static $mins_sep  = null;
			static $secs_sep  = null;

			if ( null === $days_sep ) {	// Translate only once.

				$days_sep  = ' ' . _x( 'days', 'option comment', 'wpsso' ) . ', ';
				$hours_sep = ' ' . _x( 'hours', 'option comment', 'wpsso' ) . ', ';
				$mins_sep  = ' ' . _x( 'mins', 'option comment', 'wpsso' ) . ', ';
				$secs_sep  = ' ' . _x( 'secs', 'option comment', 'wpsso' );
			}

			return $form->get_no_input_value( $value = '0', 'xshort' ) . $days_sep . 
				$form->get_no_input_value( $value = '0', 'xshort' ) . $hours_sep . 
				$form->get_no_input_value( $value = '0', 'xshort' ) . $mins_sep . 
				$form->get_no_input_value( $value = '0', 'xshort' ) . $secs_sep;
		}
	}
}
