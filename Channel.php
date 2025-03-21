<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class Channel extends DataObject {
		const CHANNEL_ROLE_METHOD_INCLUDE = "include";
		const CHANNEL_ROLE_METHOD_EXCLUDE = "exclude";

		public static function tableName() {
			return "onsong_connect_channel";
		}

		public static function className() {
			return "Channel";
		}
		
		public function __construct($data = null, $qualifier = null) {
			parent::__construct($data, $qualifier);
			$this->account(Account::current());
		}

		public function channelRoles($index = null) {
			global $pdo;
			$a = array();
			$params = array($this->ID());
			$sql = "SELECT * FROM onsong_connect_channel_role WHERE channelID = ? ORDER BY method DESC";
			$statement = $pdo->prepare($sql);
			$statement->execute($params);
			if($statement) {
				while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
					array_push($a, new ChannelRole($row));
				}
			}

			// Now either return an index or the full list
			if(isset($index)) {
				if($index < count($a)) {
					return $a[$index];
				} else {
					return null;
				}
			}
			return $a;
		}
		
		public function rolesWithMethod($method = null) {
			global $pdo;
			$a = array();
			$params = array($this->ID());
			$sql = "SELECT r.* FROM onsong_connect_channel_role cr INNER JOIN onsong_connect_role r ON r.ID = cr.roleID WHERE r.deleted IS NULL AND cr.channelID = ? ";
			if(!empty($method)) {
				$sql .= " AND cr.method = ? ";
				array_push($params, $method);
			}
			$sql .= " ORDER BY cr.method DESC";
			$statement = $pdo->prepare($sql);
			$statement->execute($params);
			if($statement) {
				while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
					array_push($a, new Role($row));
				}
			}
			return $a;
		}
		
		public function roles($index = null) {
			global $pdo;
			$a = array();
			
			// Separate into include/exclude groups
			$includes = $this->rolesWithMethod(self::CHANNEL_ROLE_METHOD_INCLUDE);
			$excludes = $this->rolesWithMethod(self::CHANNEL_ROLE_METHOD_EXCLUDE);

			// If we have inclusions,
			if(count($includes)) {

				// Add those to the list
				foreach($includes as $role) {
					$a[$role->ID()] = $role;
				}
			}
			
			// Otherwise, get a list of roles from the account
			else {
				foreach($this->account()->roles() as $role) {
					$a[$role->ID()] = $role;
				}
			}
			
			// Now go through the excludes and remove
			foreach($excludes as $role) {
				unset($a[$role->ID()]);
			}
			
			// Now switch to an indexed array
			$a = array_values($a);

			// Now either return an index or the full list
			if(isset($index)) {
				if($index < count($a)) {
					return $a[$index];
				} else {
					return null;
				}
			}
			return $a;
		}

		public function devices($index = null) {
			global $pdo;
			
			// Get a list of applicable role identifiers
			$roleIDs = array();
			foreach($this->roles() as $role) {
				array_push($roleIDs, $role->ID());
			}
			
			// Then limit the returned devices by the list or available roles
			$a = array();
			$sql = "SELECT d.*, t.accessed FROM onsong_connect_token t INNER JOIN onsong_connect_device d ON d.ID = t.deviceID WHERE t.roleID IN('". implode("', '", $roleIDs) ."') AND r.deleted IS NULL AND t.expires > NOW() ORDER BY d.name";
			$statement = $pdo->prepare($sql);
			$statement->execute();
			if($statement) {
				while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
					array_push($a, new Device($row));
				}
			}
			if(isset($index)) {
				if($index < count($a)) {
					return $a[$index];
				} else {
					return null;
				}
			}
			return $a;
		}
		
		public function messages($query = null) {
			if(is_null($query)) {
				$query = array();
			}
			$query["channelID"] = $this->ID();
			if(empty($query["sort"])) {
				$query["sort"] = "created";
				$query["descending"] = true;
				
			}
			return new DataObjectList("Message", $query);
		}
		
		public function includeRole($role) {
			return addRole($role, "include");
		}
		
		public function excludeRole($role) {
			return addRole($role, "exclude");
		}
		
		public function addRole($role, $method) {
			global $pdo;
			$this->removeRole($role);
			$sql = "INSERT INTO onsong_connect_channel_role ( channelID, roleID, method, created ) VALUES (?, ?, ?, NOW() )";
			$statement = $pdo->prepare($sql);
			return $statement->execute(array($this->ID(), $role->ID(), $method));
		}
		
		public function removeRole($role) {
			global $pdo;
			$sql = "DELETE FROM onsong_connect_channel_role WHERE channelID = ? AND roleID = ?";
			$statement = $pdo->prepare($sql);
			return $statement->execute(array($this->ID(), $role->ID()));
		}
	}
?>