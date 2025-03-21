<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class GiftCardTemplate extends DataObject {

		public static function tableName() {
			return "onsong_connect_gift_card_template";
		}

		public static function className() {
			return "GiftCardTemplate";
		}
		
		public static function default() {
			return self::retrieve("normal-delivery");
		}
	}
?>