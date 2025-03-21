<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class Message extends DataObject {

		public static function tableName() {
			return "onsong_connect_message";
		}

		public static function className() {
			return "Message";
		}
		
		public function __construct($data = null, $qualifier = null) {
			parent::__construct($data, $qualifier);
			$this->role(Role::current());
		}
		
		public function attachments($query = null) {
			if(is_null($query)) {
				$query = array();
			}
			$query["messageID"] = $this->ID();
			if(empty($query["sort"])) {
				$query["sort"] = ["orderIndex", "created"];
				
			}
			return new DataObjectList("MessageAttachment", $query);
		}
	}
?>