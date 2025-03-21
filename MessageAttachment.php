<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class MessageAttachment extends DataObject {

		public static function tableName() {
			return "onsong_connect_message_attachment";
		}

		public static function className() {
			return "MessageAttachment";
		}
		
		public function keypath() {
			return "messages/". $this->messageID() ."/attachments/". $this->ID() ."/". $this->filename();
		}
	}
?>