<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class Peer extends DataObject {

		public static function tableName() {
			return "onsong_connect_peer";
		}

		public static function className() {
			return "Peer";
		}
		
		public function name($value = false) {
			if($value === false) {
				if(!empty($this->associatedUserID())) {
					$ass = $this->associatedUser();
					if(!empty($ass)) {
						$name = $ass->fullName();
					}
				}
				if(empty($name)) {
					$name = $this->value("name");
				}
				return $name;
			} else {
				$this->value("name", $value);
			}
		}
		
		public function email($value = false) {
			if($value === false) {
				if(!empty($this->associatedUserID())) {
					$ass = $this->associatedUser();
					if(!empty($ass)) {
						$email = $ass->email();
					}
				}
				if(empty($email)) {
					$email = $this->value("email");
				}
				return $email;
			} else {
				$this->value("email", $value);
			}
		}
		
		public static function classNamesForProperty($name) {
			if($name == "associatedUser") {
				return "User";
			} else {
				return parent::classNamesForProperty($name);
			}
		}
	}
?>