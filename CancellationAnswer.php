<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class CancellationAnswer extends DataObject {

		public static function tableName() {
			return "onsong_connect_cancellation_answer";
		}

		public static function className() {
			return "CancellationAnswer";
		}
	}
?>