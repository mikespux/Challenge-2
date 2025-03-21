<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class EmailStatus extends DataObject {

		public static function tableName() {
			return "onsong_connect_email_status";
		}

		public static function className() {
			return "EmailStatus";
		}
		
		public function success() {
			return (empty($this->error()) && is_null($this->recipients()) == false);
		}
	}
?>