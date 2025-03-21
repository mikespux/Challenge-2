<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class FeaturedEvent extends DataObject {

		public static function tableName() {
			return "onsong_featured_event";
		}

		public static function className() {
			return "FeaturedEvent";
		}
	}
?>