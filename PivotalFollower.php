<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");
	
	class PivotalFollower extends PivotalObject {
	
		public static function tableName() {
			return "pivotal_story_follower";
		}
	
		public static function className() {
			return "PivotalFollower";
		}
	}
?>