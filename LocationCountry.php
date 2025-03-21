<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class LocationCountry extends DataObject {

		public static function tableName() {
			return "onsong_country";
		}

		public static function className() {
			return "LocationCountry";
		}
		
		public function taxCode() {
			return TaxCode::forCountry($this->ID());
		}
		
		public function states() {
			return LocationState::list(array("countryID"=>$this->ID()))->results();
		}
		
		public function isValidPostalCode($postalCode) {
			
			// If we have no pattern, we can't validate so just accept it
			if(empty($this->postalCodePattern())) {
				return true;
			}

			// Check the pattern
			if(preg_match("/". $this->postalCodePattern() ."/", $postalCode) != 1) {
				return false;
			}
			
			// Otherwise, it validates
			return true;
		}
	}
?>