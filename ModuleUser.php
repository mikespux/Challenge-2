<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class ModuleUSer extends DataObject {
		private $roles = null;
		private $settings = null;

		public static function tableName() {
			return "onsong_connect_module_user";
		}

		public static function className() {
			return "ModuleUser";
		}

		public static function find($module, $user) {
			global $pdo;
			
			// If this module is a string
			$moduleID = null;
			if(is_string($module)) {
				$moduleID = $module;
			} else if($module instanceof Module) {
				$moduleID = $module->ID();
			}
			if(empty($moduleID)) {
				return null;
			}
			
			// Now get the username
			$username = null;
			if(is_string($user)) {
				$username = $user;
			} else if($user instanceof User) {
				$username = $user->username();
			}
			if(empty($username)) {
				return null;
			}

			$a = array();
			$sql = "SELECT * FROM onsong_connect_module_user WHERE moduleID = ? AND username = ?";
			$statement = $pdo->prepare($sql);
			$statement->execute(array($moduleID, $username));
			if($statement) {
				if($row = $statement->fetch(PDO::FETCH_ASSOC)) {
					return new ModuleUser($row);
				}
			}
			return null;
		}

		public function roles($value = false) {
			if($value === false) {
				if(is_null($this->roles)) {
					$this->roles = explode(" ", $this->value('roles'));
				}
				return $this->roles;
			} else {
				if(!empty($value)) {
					if(is_string($value)) {
						$value = explode(" ", $value);
					}
					if(is_array($value)) {
						$this->value('roles', strtolower(implode(" ", $value)));
						$this->roles = explode(" ", $this->value('roles'));
					}
				}
			}
		}
		
		public function hasRole($role) {
			return $this->hasRoles(array($role));
		}
		
		public function hasRoles($roles) {
			foreach($roles as $role) {
				if(in_array(strtolower($role), $this->roles()) == false) {
					return false;
				}
			}
			return true;
		}

		public function settings($value = false) {
			if($value === false) {
				if(is_null($this->settings) && !empty($this->value('settings'))) {
					$this->settings = json_decode($this->value('settings'), true);
				}
				return $this->settings;
			} else {
				if(is_assoc($value) || is_object($value) || is_array($value) || is_null($value)) {
					if(!is_null($value)) {
						$value = json_encode($value);
					}
					$this->value('settings', $value);
					$this->settings = (!is_null($value)) ? json_decode($value, true) : null;
				}
				
				// Otherwise, return using a key path
				else if(is_string($value)) {
					
					// Split this up using the dot syntax
					$keys = explode(".", $value);
					
					// Get the current object
					$o = $this->settings();
					
					// Now dig in
					foreach($keys as $key) {
						
						// If the object is an associative array
						if(is_assoc($o) && isset($o[$key])) {
							
							// Then set the object
							$o = $o[$key];
						}
						
						// Otherwise, it's not found
						else {
							$o = null;
							break;
						}
					}
					return $o;
				}
			}
		}
	}
?>