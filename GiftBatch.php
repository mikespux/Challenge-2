<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class GiftBatch extends DataObject {

		public static function tableName() {
			return "onsong_connect_gift_batch";
		}

		public static function className() {
			return "GiftBatch";
		}
	}
?>