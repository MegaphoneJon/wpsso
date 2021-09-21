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

if ( ! class_exists( 'WpssoMessagesTooltipMetaSchema' ) ) {

	/**
	 * Instantiated by WpssoMessagesTooltipMeta->get() only when needed.
	 */
	class WpssoMessagesTooltipMetaSchema extends WpssoMessages {

		public function get( $msg_key = false, $info = array() ) {

			$text = '';

			switch ( $msg_key ) {

				/**
				 * Document SSO > Customize tab.
				 */
				case 'tooltip-meta-schema_title':	// Name / Title.

					$text = __( 'A customized name / title for the Schema "name" property.', 'wpsso' );

				 	break;

				case 'tooltip-meta-schema_title_alt':	// Alternate Name.

					$text = __( 'A customized alternate name / title for the Schema "alternateName" property.', 'wpsso' );

				 	break;

				case 'tooltip-meta-schema_bc_title':	// Breadcrumb Name.

					$text = __( 'A short title used for the Schema and/or HTML breadcrumb item name.', 'wpsso' );

				 	break;

				case 'tooltip-meta-schema_desc':	// Description.

					$text = __( 'A customized description for the Schema "description" property.', 'wpsso' );

				 	break;

				case 'tooltip-meta-schema_addl_type_url':	// Microdata Type URLs.

					$text = sprintf( __( 'Additional (and optional) type URLs for the item, typically used to specify more precise types from an external vocabulary in microdata syntax. For example, an additional Schema type URL for a product item could be http://www.productontology.org/id/Hammer (see %s for more examples).', 'wpsso' ), '<a href="http://www.productontology.org/">The Product Types Ontology</a>' );

				 	break;

				case 'tooltip-meta-schema_sameas_url':	// Same-As URLs.

					$text = __( 'Additional (and optional) webpage reference URLs that unambiguously indicate the item\'s identity. For example, the URL of the item\'s Wikipedia page, Wikidata entry, IMDB page, official website, etc.', 'wpsso' );

				 	break;

				case 'tooltip-meta-schema_ispartof_url':	// Is Part of URL.

					$text = __( 'Optional URLs to other Schema CreativeWorks that this content is a part of.', 'wpsso' );

				 	break;

				case 'tooltip-meta-schema_headline':	// Headline.

					$text = __( 'The headline for the Schema CreativeWork type and/or its sub-types.', 'wpsso' );

				 	break;

				case 'tooltip-meta-schema_text':	// Full Text.

					$text = __( 'The complete textual and searchable content for the Schema CreativeWork type and/or its sub-types.', 'wpsso' );

				 	break;

				case 'tooltip-meta-schema_keywords':	// Keywords.

					$text = __( 'Comma delimited list of keywords or tags describing the Schema CreativeWork content.', 'wpsso' );

				 	break;

				case 'tooltip-meta-schema_lang':	// Language.

					$text = __( 'The language (aka locale) for the Schema CreativeWork content.', 'wpsso' );

				 	break;

				case 'tooltip-meta-schema_family_friendly':	// Family Friendly.

					$text = __( 'The content of this Schema CreativeWork is family friendly.', 'wpsso' );

				 	break;

				case 'tooltip-meta-schema_pub_org_id':	// Publisher Org.

					$text = __( 'Select a publisher organization for the Schema CreativeWork type and/or its sub-types (Article, BlogPosting, WebPage, etc).', 'wpsso' );

				 	break;

				case 'tooltip-meta-schema_pub_person_id':	// Publisher Person.

					$text = __( 'Select a publisher person for the Schema CreativeWork type and/or its sub-types (Article, BlogPosting, WebPage, etc).', 'wpsso' );

				 	break;

				case 'tooltip-meta-schema_prov_org_id':	// Service Prov. Org.
				case 'tooltip-meta-schema_prov_person_id':	// Service Prov. Person.

					$text = __( 'Select a service provider, service operator or service performer (example: "Netflix").', 'wpsso' );

				 	break;

				case 'tooltip-meta-schema_copyright_year':	// Copyright Year.

					$text = __( 'The year during which the claimed copyright was first asserted for this creative work.', 'wpsso' );

				 	break;

				case 'tooltip-meta-schema_license_url':	// License URL.

					$text = __( 'A license document URL that applies to this content.', 'wpsso' );

				 	break;

				case 'tooltip-meta-schema_book_audio_duration_time':	// Audiobook Duration.

					$text = __( 'The total duration of the audio recording.', 'wpsso' );

				 	break;

				case ( 0 === strpos( $msg_key, 'tooltip-meta-schema_event_' ) ? true : false ):

					switch ( $msg_key ) {

						case 'tooltip-meta-schema_event_lang':	// Event Language.

							$text = __( 'The language (aka locale) for the event performance.', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_event_attendance':	// Event Attendance.

							$text = __( 'Select whether the event occurs online, offline at a physical location, or a mix of both online and offline.', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_event_online_url':	// Event Online URL.

							$text = __( 'An online or virtual location URL to attend the event.', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_event_location_id':	// Event Physical Venue.

							$text = __( 'Select a physical venue for the event.', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_event_organizer_org_id':	// Organizer Org.

							$text = __( 'Select an organizer (organization) for the event.', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_event_organizer_person_id':	// Organizer Person.

							$text = __( 'Select an organizer (person) for the event.', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_event_performer_org_id':	// Performer Org.

							$text = __( 'Select a performer (organization) for the event.', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_event_performer_person_id':	// Performer Person.

							$text = __( 'Select a performer (person) for the event.', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_event_status':	// Event Status.

							// translators: Please ignore - translation uses a different text domain.
							$cancelled   = _x( 'Cancelled', 'option value', 'wpsso' );

							// translators: Please ignore - translation uses a different text domain.
							$postponed   = _x( 'Postponed', 'option value', 'wpsso' );

							// translators: Please ignore - translation uses a different text domain.
							$rescheduled = _x( 'Rescheduled', 'option value', 'wpsso' );

							$text = __( 'Select the event status (default is Scheduled).', 'wpsso' ) . ' ';

							// translators: %s is the "Cancelled" event status.
							$text .= sprintf( __( 'If you select %s, do not change the original event start date.',
								'wpsso' ), $cancelled ) . ' ';

							// translators: %s is the "Postponed" event status.
							$text .= sprintf( __( 'If you select %s (but the rescheduled date isn\'t known yet), do not change the original event start date.',
								'wpsso' ), $postponed ) . ' ';

							// translators: %s is the "Rescheduled" event status.
							$text .= sprintf( __( 'If you select %s, update the previous start date option, then change the original start and end dates.',
								'wpsso' ), $rescheduled ) . ' ';

						 	break;

						case 'tooltip-meta-schema_event_start':	// Event Start.

							$text = __( 'Select the event start date and time.', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_event_end':	// Event End.

							$text = __( 'Select the event end date and time.', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_event_previous':	// Event Previous Start.

							$text = __( 'The previously scheduled start date for the event, if the event has been rescheduled.', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_event_offers_start':	// Event Offers Start.

							$text = __( 'The date and time when tickets go on sale.', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_event_offers_end':	// Event Offers End.

							$text = __( 'The date and time when tickets are no longer on sale.', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_event_offers':	// Event Offers.

							$text = __( 'One or more offers for the event, including the offer name, price and currency.', 'wpsso' );

						 	break;

					}	// End of 'tooltip-meta-schema_event' switch.

					break;	// End of 'tooltip-meta-schema_event' case.

				case ( 0 === strpos( $msg_key, 'tooltip-meta-schema_howto_' ) ? true : false ):

					switch ( $msg_key ) {

						case 'tooltip-meta-schema_howto_steps':	// How-To Steps.

							$text = __( 'A list of steps for this How-To, including the How-To step name and (optional) longer How-To step description.', 'wpsso' ) . ' ';

							$text .= __( 'You can also (optionally) define How-To sections to group individual steps together.', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_howto_supplies':	// How-To Supplies

							$text = __( 'A list of supplies that are consumed when completing this How-To.', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_howto_tools':	// How-To Tools

							$text = __( 'A list of tools or objects that are required to complete this How-To.', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_howto_prep_time':	// Preparation Time.

							$text = __( 'The total time it takes to prepare the items before executing the instruction steps.', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_howto_total_time':	// Total Time.

							$text = __( 'The total time required to perform the all instructions (including any preparation time).', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_howto_yield':

							$text = __( 'The quantity made when following these How-To instructions (example: "a paper airplane", "10 personalized candles", etc.).', 'wpsso' );

						 	break;

					}	// End of 'tooltip-meta-schema_howto' switch.

					break;	// End of 'tooltip-meta-schema_howto' case.

				case ( 0 === strpos( $msg_key, 'tooltip-meta-schema_job_' ) ? true : false ):

					switch ( $msg_key ) {

						case 'tooltip-meta-schema_job_title':	// Job Title.

							$text = __( 'The title of this job, which may be different than the WordPress post / page title.', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_job_hiring_org_id':	// Job Hiring Organization.

							$text = __( 'Select a organization for the Schema JobPosting hiring organization.', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_job_location_id':	// Job Location.

							$text = __( 'Select a location for the Schema JobPosting job location.', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_job_location_type':	// Job Location Type.

							$text = sprintf( __( 'Select an optional Google approved location type (see <a href="%s">Google\'s Job Posting guidelines</a> for more information).', 'wpsso' ), 'https://developers.google.com/search/docs/data-types/job-postings' );

						 	break;

						case 'tooltip-meta-schema_job_salary':	// Job Base Salary.

							$text = __( 'Optionally provide details on the base salary.', 'wpsso' );

							$text .= __( 'The base salary must be numeric, like 120000, 50.00, etc.', 'wpsso' );

							$text .= __( 'Do not use spaces, commas, or currency symbols, as these are not valid numeric values.', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_job_empl_type':	// Job Employment Type.

							$text = sprintf( __( 'Check one or more Google approved employment types (see <a href="%s">Google\'s Job Posting guidelines</a> for more information).', 'wpsso' ), 'https://developers.google.com/search/docs/data-types/job-postings' );

						 	break;

						case 'tooltip-meta-schema_job_expire':	// Job Posting Expires.

							$text = __( 'Select a job posting expiration date and time.', 'wpsso' );

							$text .= __( 'If a job posting never expires, or you do not know when the job will expire, do not select an expiration date and time.', 'wpsso' );

						 	break;

					}	// End of 'tooltip-meta-schema_job' switch.

					break;	// End of 'tooltip-meta-schema_job' case.

				case ( 0 === strpos( $msg_key, 'tooltip-meta-schema_movie_' ) ? true : false ):

					switch ( $msg_key ) {

						case 'tooltip-meta-schema_movie_actor_person_names':	// Cast Names.

							$text = __( 'The name of one or more actors appearing in the movie.', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_movie_director_person_names':	// Director Names.

							$text = __( 'The name of one or more directors of the movie.', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_movie_prodco_org_id':	// Movie Production Company.

							$text = __( 'The principle production company or studio responsible for the movie.', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_movie_duration_time':	// Movie Runtime.

							$text = __( 'The total movie runtime from the start to the end of the credits.', 'wpsso' );

						 	break;

					}	// End of 'tooltip-meta-schema_movie' switch.

					break;	// End of 'tooltip-meta-schema_movie' case.

				case 'tooltip-meta-schema_organization_id':

					$text = __( 'Optionally select a different organization for the Schema Organization item type and/or its sub-type (Airline, Corporation, School, etc). Select "[None]" to use the default organization details.', 'wpsso' );

				 	break;

				case 'tooltip-meta-schema_person_id':

					$role_label_transl = _x( 'Person', 'user role', 'wpsso' );

					$text = sprintf( __( 'Select a person from the list of eligible WordPress users. To be included in this list, a user must be member of the WordPress "%s" role.', 'wpsso' ), $role_label_transl );

				 	break;

				case 'tooltip-meta-schema_place_id':

					$text = __( 'Select an existing place or enter custom place details below.', 'wpsso' );

					break;

				case 'tooltip-meta-schema_qa_desc':

			 		$text = __( 'An optional heading / description of the question and it\'s answer.', 'wpsso' ) . ' ';

					$text .= __( 'If the question is part of a larger group of questions on the same subject, then this would be an appropriate field to describe that subject (example: "QA about a Flying Toaster" ).', 'wpsso' );

				 	break;

				case ( 0 === strpos( $msg_key, 'tooltip-meta-schema_recipe_' ) ? true : false ):

					switch ( $msg_key ) {

						case 'tooltip-meta-schema_recipe_cuisine':	// Recipe Cuisine.

							$text = __( 'The type of cuisine for this recipe (example: French, Indian, Italian, Japanese, Thai, etc.).', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_recipe_course':	// Recipe Course.

							$text = __( 'The course name for this recipe (example: Appetizer, Entr&eacute;e, Main Course / Main Dish, Dessert, Side-dish, etc.).', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_recipe_yield':	// Recipe Makes.

							$text = __( 'The quantity or servings made by this recipe (example: "5 servings", "Serves 4-6", "Yields 10 burgers", etc.).', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_recipe_cook_method':	// Cooking Method.

							$text = __( 'The cooking method used for this recipe (example: Baking, Frying, Steaming, etc.)', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_recipe_prep_time':	// Preparation Time.

							$text = $this->p->msgs->get( 'tooltip-meta-schema_howto_prep_time' );

						 	break;

						case 'tooltip-meta-schema_recipe_cook_time':	// Cooking Time.

							$text = __( 'The total time it takes to cook this recipe.', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_recipe_total_time':	// Total Time.

							$text = $this->p->msgs->get( 'tooltip-meta-schema_howto_total_time' );

						 	break;

						case 'tooltip-meta-schema_recipe_ingredients':	// Recipe Ingredients.

							$text = __( 'A list of ingredients for this recipe (example: "1 cup flour", "1 tsp salt", etc.).', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_recipe_instructions':	// Recipe Instructions.

							$text = __( 'A list of instructions for this recipe, including the instruction name (example: "beat eggs", "add and mix flour", etc.) and (optional) longer instruction description.', 'wpsso' ) . ' ';

							$text .= __( 'You can also (optionally) define recipe instruction sections to group individual instructions together.', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_recipe_nutri_serv':	// Serving Size.

							$text = __( 'The serving size in volume or mass. A serving size is required to include nutrition information in the Schema recipe markup.', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_recipe_nutri_cal':	// Calories.

							$text = __( 'The number of calories per serving.', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_recipe_nutri_prot':	// Protein.

							$text = __( 'The number of grams of protein per serving.', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_recipe_nutri_fib':	// Fiber.

							$text = __( 'The number of grams of fiber per serving.', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_recipe_nutri_carb':	// Carbohydrates.

							$text = __( 'The number of grams of carbohydrates per serving.', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_recipe_nutri_sugar':	// Sugar.

							$text = __( 'The number of grams of sugar per serving.', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_recipe_nutri_sod':	// Sodium.

							$text = __( 'The number of milligrams of sodium per serving.', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_recipe_nutri_fat':	// Fat.

							$text = __( 'The number of grams of fat per serving.', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_recipe_nutri_sat_fat':	// Saturated Fat.

							$text = __( 'The number of grams of saturated fat per serving.', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_recipe_nutri_unsat_fat':	// Unsaturated Fat.

							$text = __( 'The number of grams of unsaturated fat per serving.', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_recipe_nutri_trans_fat':	// Trans Fat.

							$text = __( 'The number of grams of trans fat per serving.', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_recipe_nutri_chol':	// Cholesterol.

							$text = __( 'The number of milligrams of cholesterol per serving.', 'wpsso' );

						 	break;

					}	// End of 'tooltip-meta-schema_recipe' switch.

					break;	// End of 'tooltip-meta-schema_recipe' case.

				case ( 0 === strpos( $msg_key, 'tooltip-meta-schema_review_' ) ? true : false ):

					switch ( $msg_key ) {

						case 'tooltip-meta-schema_review_rating':	// Review Rating

							$text = __( 'A rating for the subject being reviewed, along with the low / high rating scale (default is 1 to 5).', 'wpsso' ) . ' ';

							$text .= __( 'If you are reviewing a claim, the following rating scale is used: 1 = False, 2 = Mostly false, 3 = Half true, 4 = Mostly true, 5 = True.', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_review_rating_alt_name':	// Rating Value Name

							$text = __( 'An alternate name for the rating value (example: False, Misleading, Accurate, etc.).', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_review_item_type':	// Reviewed Subject Webpage Type.

							$text = __( 'A Schema type for the subject of the webpage (ie. the content) being reviewed.', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_review_item_url':	// Reviewed Subject Webpage URL.

							$text = __( 'A webpage URL for the subject of the review.', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_review_item_sameas_url':	// Reviewed Subject Same-As URL.

							$text = $this->p->msgs->get( 'tooltip-meta-schema_sameas_url' );

						 	break;

						case 'tooltip-meta-schema_review_item_name':	// Reviewed Subject Name.

							$text = __( 'A name for the subject of the review.', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_review_item_desc':	// Reviewed Subject Description.

							$text = __( 'A description for the subject of the review.', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_review_item_img_id':	// Reviewed Subject Image ID.

							$text = __( 'An image ID showing the subject of the review.', 'wpsso' ) . ' ';

							$text .= '<em>' . __( 'This option is disabled if a subject image URL is entered.', 'wpsso' ) . '</em>';

						 	break;

						case 'tooltip-meta-schema_review_item_img_url':	// Reviewed Subject Image URL.

							$text = __( 'An image URL (instead of an image ID) showing the subject of the review.', 'wpsso' ) . ' ';

							$text .= '<em>' . __( 'This option is disabled if a subject image ID is selected.', 'wpsso' ) . '</em>';

						 	break;

						case 'tooltip-meta-schema_review_item_cw_author_type':	// Reviewed C.W. Author Type.

							$text .= __( 'The creative work author can be a person or an organization.', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_review_item_cw_author_name':	// Reviewed C.W. Author Name.

							$text = __( 'Enter the name of the author for this creative work.', 'wpsso' ) . ' ';

						 	break;

						case 'tooltip-meta-schema_review_item_cw_author_url':	// Reviewed C.W. Author URL.

							$text = __( 'The home page of the author, or another definitive URL that provides information about the author, such as the person or organization\'s Wikipedia or Wikidata page.', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_review_item_cw_pub':	// Reviewed C.W. Publish Date

							$text = __( 'The date when this creative work was published or became popular / entered public discourse (for example, when it became popular on social networks).', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_review_item_cw_created':	// Reviewed C.W. Created Date.

							$text = __( 'The date when this creative work was created.', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_review_item_cw_book_isbn':	// Reviewed Book ISBN.

							$text = __( 'The ISBN code (aka International Standard Book Number) for the book being reviewed.', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_review_item_cw_movie_actor_person_names':	// Reviewed Movie Cast Names.

							$text = $this->p->msgs->get( 'tooltip-meta-schema_movie_actor_person_names' );

						 	break;

						case 'tooltip-meta-schema_review_item_cw_movie_director_person_names':	// Reviewed Movie Director Names.

							$text = $this->p->msgs->get( '', 'tooltip-meta-schema_movie_director_person_names' );

						 	break;

						case 'tooltip-meta-schema_review_item_product_brand':	// Reviewed Product Brand.

							$text = __( 'The brand name of the product being reviewed.', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_review_item_product_offers':	// Reviewed Product Offers.

							$text = __( 'One or more offers for the product being reviewed, including the offer name, price and currency.', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_review_item_product_retailer_part_no':	// Reviewed Product SKU.

							$text = __( 'The SKU (aka Stock-Keeping Unit) of the product being reviewed.', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_review_item_product_mfr_part_no':	// Reviewed Product MPN.

							$text = __( 'The MPN (aka Manufacturer Part Number) of the product being reviewed.', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_review_item_software_app_offers':	// Reviewed Software App Offers.

							$text = __( 'One or more offers for the software application being reviewed, including the offer name, price and currency.', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_review_claim_reviewed':	// Short Summary of Claim

							$text = __( 'A short summary of specific claim(s) being reviewed in the Schema ClaimReview content.', 'wpsso' ) . ' ';

							$text .= __( 'The summary should be less than 75 characters to minimize wrapping on mobile devices.', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_review_claim_first_url':	// First Appearance URL

							$text = __( 'An optional webpage URL where this specific claim first appeared.', 'wpsso' );

						 	break;

						case 'tooltip-meta-schema_review_item_software_app_cat':	// Reviews Software App Category.

							$text = $this->p->msgs->get( 'tooltip-meta-schema_software_app_cat' );

						 	break;

						case 'tooltip-meta-schema_review_item_software_app_os':	// Reviews Software App Operating System.

							$text = $this->p->msgs->get( 'tooltip-meta-schema_software_app_os' );

						 	break;

					}	// End of 'tooltip-meta-schema_review' switch.

					break;	// End of 'tooltip-meta-schema_review' case.

				case 'tooltip-meta-schema_software_app_cat':	// Application Category.

					$text = sprintf( __( 'Type of software application (example: %s, etc.).', 'wpsso' ), '"Game, Multimedia"' );

				 	break;

				case 'tooltip-meta-schema_software_app_os':	// Operating System.

					$text = sprintf( __( 'The operating system supported (example: %s, etc.).', 'wpsso' ), '"Windows 7", "OSX 10.6", "Android 1.6"' );
				 	break;

				/**
				 * Document SSO > Priority Media tab.
				 */
				case 'tooltip-meta-schema_img_id':	// Image ID.

					$text = __( 'A customized image ID to include first in the Schema meta tags and JSON-LD markup.', 'wpsso' ) . ' ';

					$text .= '<em>' . __( 'This option is disabled if a custom image URL is entered.', 'wpsso' ) . '</em>';

				 	break;

				case 'tooltip-meta-schema_img_url':	// or an Image URL.

					$text = __( 'A customized image URL (instead of an image ID) to include first in the Schema meta tags and JSON-LD markup.', 'wpsso' ) . ' ';

					$text .= '<em>' . __( 'This option is disabled if a custom image ID is selected.', 'wpsso' ) . '</em>';

				 	break;

				default:

					$text = apply_filters( 'wpsso_messages_tooltip_meta_schema', $text, $msg_key, $info );

					break;

			}	// End of 'tooltip-meta-schema' switch.

			return $text;
		}
	}
}
