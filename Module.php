<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class Module extends DataObject {

		public static function tableName() {
			return "onsong_connect_module";
		}

		public static function className() {
			return "Module";
		}
	}
?>