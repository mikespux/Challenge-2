<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class APIError extends DataObject {
		private $batches = null;

		public static function tableName() {
			return "onsong_connect_error";
		}

		public static function className() {
			return "APIError";
		}
	}
?>