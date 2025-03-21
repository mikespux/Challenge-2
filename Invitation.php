<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");
	require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/includes/sendgrid.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . "/assets/classes/Twilio/autoload.php";

	class Invitation extends DataObject {
	
		public static function tableName() {
			return "onsong_connect_invitation";
		}

		public static function className() {
			return "Invitation";
		}
		
		public function url() {
			return get_absolute_url("/account/invitation/accept.php?code=". $this->ID(), true);
		}
		
		public function isAccepted() {
			return (is_null($this->acceptedOn()) == false);
		}

		public function name($value = null) {
			if($value == null) {
				$o = parent::name();
				if(empty($o)) {
					if(!empty($this->email())) {
						$o = $this->email();
					} else if(!empty($this->mobile())) {
						$o = $this->mobile();
					}
				}
				return $o;
			} else {
				return parent::name($value);
			}
		}
		
		public function groupName() {
			$o = null;
			if(empty($o) && !empty($this->team())) { 
				$o = $this->team()->name();
			}
			if(empty($o) && $this->account()) {
				$o = $this->account()->name();
			}
			if(empty($o) && !empty($this->account()->organizationID())) {
				$o = $this->account()->organization()->name();
			}
			if(empty($o)) {
				$o = "their account";
			}
			return $o;
		}

		public function accept(&$errors = null, $user = null) {
			
			// Create an errors array if needed
			if($errors == null) {
				$errors = array();
			}

			// Let's see if we are allowed to accept this invitation
			if(count($this->account()->roles()) >= $this->account()->users()) {
				array_push($errors, "Unable to accept new members");
				return false;
			}

			// If the user is null, then use the current user
			if(is_null($user)) {
				$user = User::current();
			}
			
			// Check to see if we have a user
			if(is_null($user)) {
				array_push($errors, "User is required to accept the invitation");
				return false;
			}
			
			// Let's add the user to the account, if there are not already
			$isMember = false;
			$account = $this->account();
			foreach($account->members() as $existingUser) {
				if($existingUser->ID() == $user->ID()) {
					$isMember = true;
					break;
				}
			}

			// If we are not a member of the account,
			if($isMember == false) {
				
				// Then do that
				$role = Role::create($account, $user, (empty($this->permissions())) ? Role::PERMISSIONS_READ : $this->permissions(), $this->title());
				$role->save($errors);
			}

			// Look up the team and add the user
			if($this->team() != null && $this->team()->addMember($user) == false) {
				array_push($errors, "Could not add the member to the team.");
				return false;
			}
			
			// If we have no errors, save the changes
			if(count($errors) == 0) {

				// Set the user to the invitation
				$this->acceptedBy($user->ID());
			
				// Set the accepted date
				$this->acceptedOn(time());

				// Save the changes
				if($this->save($errors)) {
					
					// Send the email
					$this->sendEmail("d-93398086eb484593a5ff7db4388f6fb4");
					
					// Send via mobile
					$this->sendSMS("You're receiving this message because you've accepted an invitation to join OnSong. Be sure to download the OnSong app at https://onsongapp.com/join and sign into your account.");
					
					// Return true
					return true;
				}
			}
			
			// Otherwise, return false
			return false;
		}

		public function send(&$errors = null) {
			
			// If we have no ID, save first
			if(empty($this->ID())) {
				if($this->save($errors) == false) {
					return false;
				}
			}

			// Let's make sure we have invitations we can send
			if(count($this->account()->roles()) >= $this->account()->users()) {
				array_push($errors, "Unable to invite new members");
				return false;
			}

			// Keep track of the results
			$success = false;

			// If we have an email address
			if(!empty($this->email())) {
				if($this->sendEmail("d-3b9c938d4d7a4801bc989d2643bd1338", array("user"=>$this->user(), "invitation"=>$this))) {
					$success = true;
				} else {
					if(is_null($errors) == false) {
						array_push($errors, "Email could not be sent");
					}
				}
			}
			
			// If we have a mobile number
			if(!empty($this->mobile()) && strlen($this->mobile()) >= 10) {
				if($this->sendSMS($this->generateContent(false))) {
					$success = true;
				} else {
					if(is_null($errors) == false) {
						array_push($errors, "SMS could not be sent");
					}
				}
			}

			// If we have successfully sent the invitation, 
			if($success) {
			
				// Update the sent date
				$this->sentOn(time());
				
				// Then save
				return $this->save($errors);
			}
			
			// Otherwise, return false
			return false;
		}
		
		public function sendEmail($templateID, $additional = null) {
			if(empty($additional)) {
				$additional = array();
			}
			$from = array("email"=>"no-reply@onsongapp.com");
			$recipients = array(array("to"=>array(array("email"=>$this->email(), "name"=>$this->fullName())), "dynamic_template_data"=>$additional));
			$post = array("template_id"=>$templateID, "personalizations"=>$recipients, "from"=>$from);
			sendgrid_call("mail/send", "POST", $post);
			return true;
		}

		public function sendSMS($content) {
			global $twilioAccountSid, $twilioAuthToken;
			
			// If we don't have a mobile number, don't send
			if(empty($this->mobile()) || strlen($this->mobile()) < 7) {
				return false;
			}
			
			// Determine the best from number
			$from = '7175167350';
			if(startsWith($this->mobile(), '+44')) {
				$from = 'OnSong';
			}

			// Set up the Twilio client
			$client = new Twilio\Rest\Client($twilioAccountSid, $twilioAuthToken);

			// Send to message to the mobile number
	        $sms = $client->messages->create(
				
				// The number we are sending to - Any phone number
				$this->mobile(), [
	            	'from'=>$from,
					'body'=>$content
				]
	        );
	        return true;
		}
		
		public function jsonSerialize($include = null) {
			$o = parent::jsonSerialize($include = null);
			$o["groupName"] = $this->groupName();
			return $o;
		}

		public static function permissionsEnabled() {
			return false;
		}
		
		private function generateContent($html = false, $withURL = true) {
			$content = "";
			
			// Add the first portion of the message
			if($html) {
				$content ."<p>";
			}
			$content .= "You've been invited to join ". $this->groupName() ." using OnSong.";
			if($html) {
				$content .= "</p>";
			}
			
			// Add the message
			if(strlen($this->message()) > 0) {
				if($html) {
					$content .= "<p><em>";
				} else {
					$content .= "\n\n";
				}
				$content .= $this->message();
				if($html) {
					$content .= "</em></p>";
				}
			}
			
			// Add the web address
			if($withURL) {
				$content .= "Go to ";
				if($html) {
					$content .= "<a href=\"". $this->url() ."\">";
				} else {
					$content .= "\n\n";
				}
				$content .= $this->url();
				if($html) {
					$content .= "</a>";
				}
				$content .= " to accept or decline.";
			}

			// Return the content
			return $content;
		}
	}
?>