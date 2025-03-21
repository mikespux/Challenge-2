<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");
	
	class PivotalOwner extends PivotalObject {
	
		public static function tableName() {
			return "pivotal_story_owner";
		}
	
		public static function className() {
			return "PivotalOwner";
		}
	}
?>