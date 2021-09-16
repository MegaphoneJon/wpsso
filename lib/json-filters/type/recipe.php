<?php
/**
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2016-2021 Jean-Sebastien Morisset (https://wpsso.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {

	die( 'These aren\'t the droids you\'re looking for.' );
}

if ( ! class_exists( 'WpssoJsonFiltersTypeRecipe' ) ) {

	class WpssoJsonFiltersTypeRecipe {

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
				'json_data_https_schema_org_recipe' => 5,
			) );
		}

		public function filter_json_data_https_schema_org_recipe( $json_data, $mod, $mt_og, $page_type_id, $is_main  ) {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			$json_ret = array();

			if ( ! empty( $mod[ 'obj' ] ) ) {	// Just in case.

				$md_opts = SucomUtil::get_opts_begin( 'schema_recipe_', array_merge( 
					(array) $mod[ 'obj' ]->get_defaults( $mod[ 'id' ] ),
					(array) $mod[ 'obj' ]->get_options( $mod[ 'id' ] )	// Returns empty string if no meta found.
				) );

			} else {
				$md_opts = array();
			}

			/**
			 * Property:
			 * 	recipeCuisine
			 */
			if ( ! empty( $md_opts[ 'schema_recipe_cuisine' ] ) ) {

				$json_ret[ 'recipeCuisine' ] = (string) $md_opts[ 'schema_recipe_cuisine' ];
			}

			/**
			 * Property:
			 * 	recipeCategory
			 */
			if ( ! empty( $md_opts[ 'schema_recipe_course' ] ) ) {

				$json_ret[ 'recipeCategory' ] = (string) $md_opts[ 'schema_recipe_course' ];
			}

			/**
			 * Property:
			 * 	recipeYield
			 */
			if ( ! empty( $md_opts[ 'schema_recipe_yield' ] ) ) {

				$json_ret[ 'recipeYield' ] = (string) $md_opts[ 'schema_recipe_yield' ];
			}

			/**
			 * Property:
			 * 	cookingMethod
			 */
			if ( ! empty( $md_opts[ 'schema_recipe_cook_method' ] ) ) {

				$json_ret[ 'cookingMethod' ] = (string) $md_opts[ 'schema_recipe_cook_method' ];
			}

			/**
			 * Property:
			 * 	prepTime
			 * 	cookTime
			 * 	totalTime
			 */
			WpssoSchema::add_data_time_from_assoc( $json_ret, $md_opts, array(
				'prepTime'  => 'schema_recipe_prep',
				'cookTime'  => 'schema_recipe_cook',
				'totalTime' => 'schema_recipe_total',
			) );

			/**
			 * Property:
			 * 	recipeIngredient (supersedes ingredients)
			 */
			$recipe_ingredients = SucomUtil::preg_grep_keys( '/^schema_recipe_ingredient_([0-9])+$/', $md_opts, $invert = false, $replace = '$1' );

			foreach ( $recipe_ingredients as $md_num => $md_val ) {

				$json_ret[ 'recipeIngredient' ][] = $md_val;
			}

			/**
			 * Property:
			 * 	recipeInstructions
			 */
			WpssoSchema::add_howto_step_data( $json_ret, $md_opts, $opt_prefix = 'schema_recipe_instruction', $prop_name = 'recipeInstructions' );

			/**
			 * Property:
			 * 	nutrition as https://schema.org/NutritionInformation
			 */
			if ( ! empty( $md_opts[ 'schema_recipe_nutri_serv' ] ) ) {	// serving size is required

				if ( false !== ( $nutrition = WpssoSchema::get_data_itemprop_from_assoc( $md_opts, array(
					'servingSize'           => 'schema_recipe_nutri_serv',
					'calories'              => 'schema_recipe_nutri_cal',
					'proteinContent'        => 'schema_recipe_nutri_prot',
					'fiberContent'          => 'schema_recipe_nutri_fib',
					'carbohydrateContent'   => 'schema_recipe_nutri_carb',
					'sugarContent'          => 'schema_recipe_nutri_sugar',
					'sodiumContent'         => 'schema_recipe_nutri_sod',
					'fatContent'            => 'schema_recipe_nutri_fat',
					'saturatedFatContent'   => 'schema_recipe_nutri_sat_fat',
					'unsaturatedFatContent' => 'schema_recipe_nutri_unsat_fat',
					'transFatContent'       => 'schema_recipe_nutri_trans_fat',
					'cholesterolContent'    => 'schema_recipe_nutri_chol',
				) ) ) ) {

					self::add_nutrition_measures( $nutrition );

					$json_ret[ 'nutrition' ] = WpssoSchema::get_schema_type_context( 'https://schema.org/NutritionInformation', $nutrition );
				}
			}

			return WpssoSchema::return_data_from_filter( $json_data, $json_ret, $is_main );
		}

		private static function add_nutrition_measures( array &$nutrition ) {

			$measures = array(
				'calories'              => 'calories',
				'proteinContent'        => 'grams protein',
				'fiberContent'          => 'grams fiber',
				'carbohydrateContent'   => 'grams carbohydrates',
				'sugarContent'          => 'grams sugar',
				'sodiumContent'         => 'milligrams sodium',
				'fatContent'            => 'grams fat',
				'saturatedFatContent'   => 'grams saturated fat',
				'unsaturatedFatContent' => 'grams unsaturated fat',
				'transFatContent'       => 'grams trans fat',
				'cholesterolContent'    => 'milligrams cholesterol',
			);

			foreach ( $nutrition as $prop_name => &$prop_val ) {		// Update value by reference.

				if ( isset( $measures[ $prop_name ] ) ) {

					$prop_val .= ' ' . $measures[ $prop_name ];	// Add measure unit.
				}
			}
		}
	}
}
