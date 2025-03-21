<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class Language extends DataObject {

		public static function tableName() {
			return "onsong_connect_language";
		}

		public static function className() {
			return "Language";
		}
	}
?>