<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class AccountAffiliate extends DataObject {

		public static function tableName() {
			return "onsong_connect_account_affiliate";
		}

		public static function className() {
			return "AccountAffiliate";
		}
	}
?>