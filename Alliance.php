<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class Alliance extends DataObject {

		public static function tableName() {
			return "onsong_connect_alliance";
		}

		public static function className() {
			return "Alliance";
		}
	}
?>