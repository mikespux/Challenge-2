<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class ChannelRole extends DataObject {

		public static function tableName() {
			return "onsong_connect_channel_role";
		}

		public static function className() {
			return "ChannelRole";
		}
	}
?>