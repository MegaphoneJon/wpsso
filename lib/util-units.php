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

if ( ! class_exists( 'WpssoUtilUnits' ) ) {

	class WpssoUtilUnits {

		private $p;	// Wpsso class object.

		/**
		 * Instantiated by WpssoUtil->__construct().
		 */
		public function __construct( &$plugin ) {

			$this->p =& $plugin;

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}
		}

		/**
		 * Dimensions.
		 */
		public static function get_dimension_label( $key ) {

			$units = self::get_dimension_units();	// Returns translated labels.

			return isset( $units[ $key ] ) ? $units[ $key ] : '';
		}

		public static function get_dimension_units() {

			static $local_cache = null;

			if ( null === $local_cache ) {

				$local_cache = array(
					'mm' => __( 'mm', 'wpsso' ),		// Millimeter.
					'cm' => __( 'cm', 'wpsso' ),		// Centimeter.
					'm'  => __( 'm', 'wpsso' ),		// Meter.
					'km' => __( 'km', 'wpsso' ),		// Kilometer.
					'in' => __( 'inches', 'wpsso' ),	// Inch.
					'ft' => __( 'feet', 'wpsso' ),		// Foot.
					'yd' => __( 'yards', 'wpsso' ),		// Yard.
					'mi' => __( 'miles', 'wpsso' ),		// Mile.
				);
			}

			return $local_cache;
		}

		public static function get_dimension( $value, $to, $from = '' ) {

			$value = (float) $value;

			if ( empty( $from ) ) {

				$from = 'cm';
			}

			if ( $from !== $to ) {	// Just in case.

				/**
				 * Convert dimension to cm first.
				 */
				switch ( $from ) {

					/**
					 * Metric units.
					 */
					case 'mm':	$value *= 0.1; break;		// Millimeter to Centimeter.
					case 'cm':	$value *= 1; break;		// Centimeter to Centimeter.
					case 'm':	$value *= 100; break;		// Meter to Centimeter.
					case 'km':	$value *= 100000; break;	// Kilometer to Centimeter.

					/**
					 * Imperial units.
					 */
					case 'in':	$value *= 2.54; break;		// Inch to Centimeter.
					case 'ft':	$value *= 30.48; break;		// Foot to Centimeter.
					case 'yd':	$value *= 91.44; break;		// Yard to Centimeter.
					case 'mi':	$value *= 160934.4; break;	// Mile to Centimeter.
				}

				/**
				 * Convert dimension from cm to desired output.
				 */
				switch ( $to ) {

					/**
					 * Metric units.
					 */
					case 'mm':	$value *= 10; break;		// Centimeter to Millimeter.
					case 'cm':	$value *= 1; break; 		// Centimeter to Centimeter.
					case 'm':	$value *= 0.01; break; 		// Centimeter to Meter.
					case 'km':	$value *= 0.00001; break;	// Centimeter to Kilometer.

					/**
					 * Imperial units.
					 */
					case 'in':	$value *= 0.3937007874; break;		// Centimeter to Inch.
					case 'ft':	$value *= 0.03280839895; break; 	// Centimeter to Foot.
					case 'yd':	$value *= 0.010936132983; break; 	// Centimeter to Yard.
					case 'mi':	$value *= 0.0000062137119224; break;	// Centimeter to Mile.
				}
			}

			return ( $value < 0 ) ? 0 : $value;
		}

		/**
		 * Fluid volumes.
		 */
		public static function get_fluid_volume_label( $key ) {

			$units = self::get_fluid_volume_units();	// Returns translated labels.

			return isset( $units[ $key ] ) ? $units[ $key ] : '';
		}

		public static function get_fluid_volume_units() {

			static $local_cache = null;

			if ( null === $local_cache ) {

				$local_cache = array(
					'ml'       => __( 'ml', 'wpsso' ),		// Millilitre.
					'cl'       => __( 'cl', 'wpsso' ),		// Centilitre.
					'l'        => __( 'l', 'wpsso' ),		// Liter.
					'kl'       => __( 'kl', 'wpsso' ),		// Kiloliter.
					'US tsp'   => __( 'US tsp', 'wpsso' ),		// US teaspoon.
					'US tbsp'  => __( 'US tbsp', 'wpsso' ),		// US tablespoon.
					'US fl oz' => __( 'US fl oz', 'wpsso' ),	// US fluid ounce.
					'US cup'   => __( 'US cup', 'wpsso' ),		// US cup.
					'US pt'    => __( 'US pt', 'wpsso' ),		// US pint.
					'US qt'    => __( 'US qt', 'wpsso' ),		// US quart.
					'US gal'   => __( 'US gal', 'wpsso' ),		// US gallon.
				);
			}

			return $local_cache;
		}

		public static function get_fluid_volume( $value, $to, $from = '' ) {

			$value = (float) $value;

			if ( empty( $from ) ) {

				$from = 'ml';
			}

			if ( $from !== $to ) {	// Just in case.

				/**
				 * Convert volume to ml first.
				 */
				switch ( $from ) {

					/**
					 * Metric units.
					 */
					case 'ml':	$value *= 1; break; 		// Millilitre to Millilitre.
					case 'cl':	$value *= 10; break; 		// Centilitre to Millilitre.
					case 'l':	$value *= 1000; break; 		// Liter to Millilitre.
					case 'kl':	$value *= 1000000; break;	// Kiloliter to Millilitre.

					/**
					 * Imperial units.
					 */
					case 'US tsp':		$value *= 4.92892; break;	// US teaspoon to Millilitre.
					case 'US tbsp':		$value *= 14.7868; break; 	// US tablespoon to Millilitre.
					case 'US fl oz':	$value *= 29.5735; break; 	// US fluid ounce to Millilitre.
					case 'US cup':		$value *= 236.588; break; 	// US cup to Millilitre.
					case 'US pt':		$value *= 473.176; break; 	// US pint to Millilitre.
					case 'US qt':		$value *= 946.353; break; 	// US quart to Millilitre.
					case 'US gal':		$value *= 3785.41; break;	// US gallon to Millilitre.
				}

				/**
				 * Convert volume from ml to desired output.
				 */
				switch ( $to ) {

					/**
					 * Metric units.
					 */
					case 'ml':	$value *= 1; break; 		// Millilitre to Millilitre.
					case 'cl':	$value *= 0.1; break; 		// Millilitre to Centilitre.
					case 'l':	$value *= 0.001; break; 	// Millilitre to Liter.
					case 'kl':	$value *= 0.000001; break;	// Millilitre to Kiloliter.

					/**
					 * Imperial units.
					 */
					case 'US tsp':		$value *= 0.202884; break; 	// Millilitre to US teaspoon.
					case 'US tbsp':		$value *= 0.067628; break; 	// Millilitre to US tablespoon.
					case 'US fl oz':	$value *= 0.033814; break; 	// Millilitre to US fluid ounce.
					case 'US cup':		$value *= 0.00422675; break; 	// Millilitre to US cup.
					case 'US pt':		$value *= 0.00211338; break; 	// Millilitre to US pint.
					case 'US qt':		$value *= 0.00105669; break; 	// Millilitre to US quart.
					case 'US gal':		$value *= 0.000264172; break;	// Millilitre to US gallon.
				}
			}

			return ( $value < 0 ) ? 0 : $value;
		}

		/**
		 * Weight.
		 */
		public static function get_weight_label( $key ) {

			$units = self::get_weight_units();	// Returns translated labels.

			return isset( $units[ $key ] ) ? $units[ $key ] : '';
		}

		public static function get_weight_units() {

			static $local_cache = null;

			if ( null === $local_cache ) {

				$local_cache = array(
					'mg'  => __( 'mg', 'wpsso' ),	// Milligram.
					'g'   => __( 'g', 'wpsso' ),	// Gram.
					'kg'  => __( 'kg', 'wpsso' ),	// Kilogram.
					't'   => __( 't', 'wpsso' ),	// Metric Ton.
					'oz'  => __( 'oz', 'wpsso' ),	// Ounce.
					'lb'  => __( 'lb', 'wpsso' ),	// Pound.
					'lbs' => __( 'lbs', 'wpsso' ),	// Pound.
					'st'  => __( 'st', 'wpsso' ),	// Stone.
				);
			}

			return $local_cache;
		}

		public static function get_weight( $value, $to, $from = '' ) {

			$value = (float) $value;

			if ( empty( $from ) ) {

				$from = 'kg';
			}

			if ( $from !== $to ) {

				/**
				 * Convert weight to kg first.
				 */
				switch ( $from ) {

					/**
					 * Metric units.
					 */
					case 'mg':	$value *= 0.000001; break;	// Milligram to Kilogram.
					case 'g':	$value *= 0.001; break;		// Gram to Kilogram.
					case 'kg':	$value *= 1; break;		// Kilogram to Kilogram.
					case 't':	$value *= 1000; break;		// Metric Ton to Kilogram.

					/**
					 * Imperial units.
					 */
					case 'oz':	$value *= 0.02834952; break;	// Ounce to Kilogram.
					case 'lb':	$value *= 0.4535924; break;	// Pound to Kilogram.
					case 'lbs':	$value *= 0.4535924; break;	// Pound to Kilogram.
					case 'st':	$value *= 6.350293; break;	// Stone to Kilogram.
				}

				/**
				 * Convert weight from kg to desired output.
				 */
				switch ( $to ) {

					/**
					 * Metric units.
					 */
					case 'mg':	$value *= 1000000; break;	// Kilogram to Milligram.
					case 'g':	$value *= 1000; break;		// Kilogram to Gram.
					case 'kg':	$value *= 1; break;		// Kilogram to Kilogram.
					case 't':	$value *= 0.001; break;		// Kilogram to Metric Ton.

					/**
					 * Imperial units.
					 */
					case 'oz':	$value *= 35.27396; break;	// Kilogram to Ounce.
					case 'lb':	$value *= 2.204623; break;	// Kilogram to Pound.
					case 'lbs':	$value *= 2.204623; break;	// Kilogram to Pound.
					case 'st':	$value *= 0.157473; break;	// Kilogram to Stone.
				}
			}

			return ( $value < 0 ) ? 0 : $value;
		}
	}
}