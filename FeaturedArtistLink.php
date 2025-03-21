<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class FeaturedArtistLink extends DataObject {

		public static function tableName() {
			return "onsong_featured_artist_link";
		}

		public static function className() {
			return "FeaturedArtistLink";
		}
		
		public function name($value = "__NOTSET") {
			if($value != "__NOTSET") {
				$this->value("name", $value);
			} else {
				$name = parent::value("name");
				if(empty($name) && !empty($this->typeID())) {
					$name = $this->type()->name();
				}
				if(empty($name) && !empty($this->url())) {
					$parts = explode("//", $this->url());
					$name = $parts[count($parts)-1];
				}
				return $name;
			}
		}
	}
?>