<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");
	
	class PivotalMe extends PivotalPerson {
		
		public function kind() {
			return "me";
		}

		public function jsonSerialize() {
			$d = parent::jsonSerialize();
			$d['accounts'] = $this->accounts();
			$d['api_token'] = null;
			$d['email'] = $this->email();
			$d['has_google_identity'] = !(empty(UserAlliance::lookup('google')));
			$d['initials'] = $this->initials();
			$d['name'] = $this->name();
			$d['projects'] = PivotalProjectMembershipSummary::list()->results();
			$d['receives_in_app_notifications'] = false;
			$d['time_zone'] = ['kind'=>'time_zone','olson_name'=>'America/New_York','offset'=>'-05:00'];
			$d['twofactor_auth_enabled'] = false;
			return $d;
		}
	}
?>