<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class FeatureCategory extends DataObject {

		public static function tableName() {
			return "onsong_connect_feature_category";
		}

		public static function className() {
			return "FeatureCategory";
		}
	}
?>