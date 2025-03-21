<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");
	
	class PivotalActivity extends PivotalObject {
		private $mentions;
	
		public static function tableName() {
			return "pivotal_story_activity";
		}
	
		public static function className() {
			return "PivotalActivity";
		}
		
		public static function isTracking($value = null) {
			static $tracking = true;
			if(is_null($value)) {
				return $tracking;
			} else {
				$tracking = boolval($value);
			}
		}

		public static function import($d) {
			
			// Create the story
			$activity = new PivotalActivity();
			
			// Set the parameters
			foreach($d as $name=>$value) {
				if(empty($value)) { continue; }
				if($name == "ID") {
					$activity->storyID(intval($value));
				}
				else if($name == "Type") {
					$activity->type($value);
				}
				else if($name == "Message") {
					$activity->message($value);
				}
				else if($name == "Performed By") {
					$activity->userID(PivotalPerson::resolve($value));
				}
				else if($name == "Occurred At") {
					$activity->created(strtotime($value));
				}
			}
			
			// Save the activity
			$errors = array();
			$activity->save($errors);
			
			// Return the activity
			return $activity;
		}

		public function save(&$errors = null, $exceptions = null) {

			// Save the story
			$success = parent::save($errors, $exceptions);
			if($success) {

				// Register the mentions
				$mentions = array();
				$matches = array();
				preg_match_all('/@[a-zA-Z0-9_]+/', $this->message(), $matches);
				foreach($matches[0] as $username) {
					$username = str_replace("@", "", $username);
					$person = PivotalPerson::lookup($username);
					if($person) {
						$mentions[] = $person;
					}
				}
				
				// Keep a list to notify
				$recipients = array();

				// Send to the followers
				foreach($this->story()->followers() as $follower) {
					$recipients[$follower->userID()] = $follower->user();
				}
				
				// Now, go through the to list to notify
				foreach($mentions as $person) {
					if(isset($recipients[$person->userID()])) { continue; }
					$member = $this->story()->project()->member($person);
					if($member && $member->notifyMentions()) {
						$recipients[$person->userID()] = $person->user();
					}
				}
				
				// Not notify
				$this->notify(array_values($recipients));
			}
		}

		public function kind() {
			return $this->type();
		}

		public function jsonSerialize() {
			$d = parent::jsonSerialize();
			$d['uuid'] = $this->ID();
			$d['performed_by'] = PivotalPerson::lookup($this->userID());
			$d['occurred_at'] = $d['created_at'];
			unset($d['id']);
			unset($d['story_id']);
			unset($d['type']);
			unset($d['person_id']);
			unset($d['created_at']);
			return $d;
		}

		public function notify($users) {
			
			$templateID = "d-c8e85cafe82a419a82ccc1d3d9455b10";
			
			$to = array();
			foreach($users as $recipient) {
				$to[] = array("email"=>$recipient->email(), "name"=>$recipient->fullName());
			}

			$url = get_absolute_url("/pivotal/projects/". $this->story()->projectID() ."/stories/". $this->storyID());
			$from = array("email"=>"no-reply@onsongapp.com","name"=>"OnSong");
			$recipients = array(array("to"=>$to, "dynamic_template_data"=>array("url"=>$url,"activity"=>$this,"sender"=>$this->user(),"story"=>$this->story(),"project"=>$this->story()->project())));
			
			$post = array("template_id"=>$templateID, "personalizations"=>$recipients, "from"=>$from);
			if(!empty($replyTo)) {
				$post['reply_to'] = User::current()->email();
			}
			$result = sendgrid_call("mail/send", "POST", $post);
			return $result;
		}
	}
?>