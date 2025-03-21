<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class LocalizedLanguage extends DataObject {

		public static function tableName() {
			return "onsong_localized_language";
		}

		public static function className() {
			return "LocalizedLanguage";
		}
	}
?>