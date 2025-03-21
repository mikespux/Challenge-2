<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class AppKey extends DataObject {

		public static function tableName() {
			return "onsong_connect_app_key";
		}

		public static function className() {
			return "AppKey";
		}
	}
?>