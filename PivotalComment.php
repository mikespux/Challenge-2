<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");
	
	class PivotalComment extends PivotalObject {
	
		public static function tableName() {
			return "pivotal_story_comment";
		}
	
		public static function className() {
			return "PivotalComment";
		}
		
		public function save(&$errors = null, $exceptions = null) {
			
			// Determine if this is a new story
			$isNew = $this->isNew();
			
			// Save the story
			$success = parent::save($errors, $exceptions);
				
			// Handle any other changes to the database
			if($success) {

				// If this was new
				if(PivotalActivity::isTracking()) {
					
					// Make sure we track the creation
					$activity = new PivotalActivity();
					$activity->storyID($this->storyID());
					$activity->commentID($this->ID());
					$activity->userID($this->userID());
					if($isNew) {
						$msg = User::current()->fullName() . " added comment";
						if(!empty($this->attachmentID())) {
							$msg .= " with attachment";
						}
						if(!empty($this->text())) {
							$msg .= ": \"". $this->text() ."\"";
						}
						$activity->type('comment_create_activity');
						$activity->message($msg);
					} else {
						$activity->type('comment_update_activity');
						$activity->message(User::current()->fullName() . " edited this comment");
					}
					$activity->save();
				}
			}
			return $success;
		}
		
		public function delete($permanently = false, $ignorePermissions = false) {
			if(parent::delete($permanently, $ignorePermissions)) {
				if(PivotalActivity::isTracking()) {
					$activity = new PivotalActivity();
					$activity->storyID($this->storyID());
					$activity->commentID($this->ID());
					$activity->userID($this->userID());
					$activity->type('comment_delete_activity');
					$activity->message(User::current()->fullName() . ' deleted this comment');
					$activity->save();
				}
				return true;
			}
			return false;
		}
	}
?>