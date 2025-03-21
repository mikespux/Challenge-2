<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class Account extends DataObject {
		private $affiliatesToSave = null;
		private $featureLookup = null;
		private $wasFree = true;
		private $wasExpired = false;
		private $storage = null;
		private $cancelled = false;

		public static function possessive($string) {
			$last_character = substr($string, -1);
			if($last_character == 's' || $last_character == 'S') {
				return "$string'";
			} else {
				return "$string's";
			}
		}

		public function __construct($data = null, $qualifier = null) {
			parent::__construct($data, $qualifier);
			$this->wasFree = (!$this->isPaid());
			$this->wasExpired = $this->isExpired();
			if(self::active() == null) {
				self::active(true);
			}
		}

		public function storage() {
			
			// Create a storage object
			if(is_null($this->storage)) {
				$this->storage = new Storage($this);
			}
			
			// Then return it
			return $this->storage;
		}
		
		public function baseStorage() {
			$baseStorage = 0;
			if(!is_null($this->plan()->storage())) {
				$baseStorage = $this->plan()->storage();
			} else if(!is_null($this->plan()->tier()->storage())) {
				$baseStorage = $this->plan()->tier()->storage();
			}
			return $baseStorage;
		}

		public function sourceID($value = "____") {
			if(is_string($value) == false || $value == "____") {
				$s = $this->value("sourceID");
				if(empty($s) && strpos($this->planID() ?? "", "lifetime") !== false) {
					$s = "onsong";
				}
				return $s;
			} else {
				$this->value("sourceID", $value);
			}
		}
		
		public function periodID($value = "____") {
			if(is_string($value) == false || $value == "____") {
				return parent::periodID();
			} else {
				if(in_array($value, ["normal", "trial", "cancelled"])) {
					if($value == "cancelled") {
						$this->cancelled = true;
					}
					parent::periodID($value);
				}
			}
		}
		
		public function activePlan() {
			if($this->isExpired() || empty($this->planID()) || empty($this->plan())) {
				return $this->basePlan();
			} else {
				return $this->plan();
			}
		}

		public function basePlanID($value = "____") {
		
			// Handle the getter
			if(is_string($value) && $value == "____") {
				return $this->value("basePlanID");
			}
		
			// Otherwise, it's a setter
			else {
				if(!empty(Plan::retrieve($value))) {
					$this->value("basePlanID", $value);
				}
			}
		}

		public function additionalStorage($value = "____") {
			global $bytesPerGB;

			if($value == "____") {
				return parent::additionalStorage();
			} else {
				if($value < 1000000) {
					$value = $value * $bytesPerGB;
				}
				parent::additionalStorage($value);
			}
		}
		
		public function paidStorage($value = "____") {
			global $bytesPerGB;

			if($value == "____") {
				return parent::paidStorage();
			} else {
				if($value < 1000000) {
					$value = $value * $bytesPerGB;
				}
				parent::paidStorage($value);
			}
		}

		public function name($value = "____") {

			// Handle the getter
			if(is_string($value) && $value == "____") {
				$name = $this->value("name");
				if($name == "Organization" || $name == "Individual") {
					$name = null;
				}
				if(empty($name) && !empty($this->organizationID()) && !empty($this->organization())) {
					$name = $this->organization()->name();
				}
				if(empty($name)) {
					if(!empty($this->plan()) && $this->users() > 1) {
						$members = $this->members(Role::PERMISSIONS_ALL);
						if(count($members) > 0) {
							$name = static::possessive($members[0]->firstName()) ." Team"; 
						} else {
							$name = "Organization";
						}
					} else {
						if(!empty($this->plan()) && !empty($this->plan()->tier())) {
							$name = "OnSong " . $this->plan()->tier()->name();
						} else {
							$name = "Personal Account";
						}
					}
				}
				return $name;
			}

			// Otherwise, it's a setter
			else {
				$this->value("name", $value);
			}
		}

		public function users($value = "____") {

			// Handle the getter
			if(is_string($value) && $value == "____") {
				$users = $this->value("users");
				if(empty($users) && !empty($this->plan())) {
					$users = $this->plan()->users();
				}
				return $users;
			}
			
			// Otherwise, it's a setter
			else {
				$this->value("users", $value);
			}
		}
		
		public function devicesPerUser() {
			if($this->plan()->isPerUser()) {
				return $this->plan()->devices();
			} else {
				return $this->plan()->devices() / $this->users();
			}
		}
		
		public function expires($value = "____") {
			// Handle the getter
			if(is_string($value) && $value == "____") {
				return parent::expires();
			}

			// Otherwise, it's a setter
			else {

				// Now add any credit to the value before setting to the database.
				if(!empty($this->credit())) {
					parent::expires($value + $this->credit());
				} else {
					parent::expires($value);
				}
			}
		}

		// Properties
		public function isExpired() {
			if($this->expires() != null) {
				return ($this->expires() < time());
			}
			return false;
		}
		
		public function isSubscribed() {
			return ($this->planID() != null && $this->subscriberID() != null);
		}

		public function isTrial($value = null) {
			if(is_null($value)) {
				if($this->periodID() == 'trial') {
					return true;
				} else {
					return ($this->planID() != null && $this->plan()->price() == 0);
				}
			} else {
				$trial = Plan::freeTrial();
				if($value) {
					if(is_null($this->planID()) && is_null($this->expires())) {
						if(is_null($trial) == null) {
							$this->plan($trial);
							$d = new DateTime();
							$d->add($trial->dateInterval());
							$this->expires($d->getTimestamp());
							$this->save();
						}
					}
				} else {
					if($this->planID() == $trial->ID()) {
						$this->planID(null);
						$this->expires(null);
						$this->save();
					}
				}
				return $this->isTrial();
			}
		}
		
		public function isSubscription() {
			return (!empty($this->planID()) && !empty($this->plan()->sourceID()));
		}
		
		public function isPaid() {
			return (!empty($this->planID()) && $this->planID() != "trial");
		}

		public function isCancelled() {
			return ((!empty($this->cancelled()) && $this->cancelled() <= time()) || $this->periodID() == "cancelled");
		}
		
		public function isDeleted() {
			return (!empty($this->deleted()) && $this->deleted() <= time());
		}
		
		public function availableFeatures($activeOnly = true) {
			global $pdo;
			$a = array();
			$features = array();
			if(!empty($this->features())) {
				$features = array_map('trim', preg_split("/\r\n|\n|\r|\s/", $this->features()));
			}
			if(!empty($this->plan())) {
				$sql = "SELECT featureID FROM onsong_connect_feature_tier WHERE tierID = ? ";
				$statement = $pdo->prepare($sql);
				$statement->execute(array($this->plan()->tierID()));
				if($statement) {
					while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
						if(in_array($row['featureID'], $features) == false) {
							array_push($features, $row['featureID']);
						}
					}
				}
			}

			$in = str_repeat('?,', count($features) - 1) . '?';
			$sql = "SELECT * FROM onsong_connect_feature WHERE ID IN ($in) ";
			if($activeOnly) {
				$sql .= "AND active = 1 ";
			}
			$sql .= "ORDER BY name ";
			$statement = $pdo->prepare($sql);
			$statement->execute($features);
			if($statement) {
				while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
					array_push($a, new Feature($row));
				}
			}
			return $a;
		}
		
		public function hasFeature($feature) {
			if($this->featureLookup == null) {
				$this->featureLookup = array();
				foreach($this->availableFeatures(false) as $f) {
					$this->featureLookup[$f->ID()] = $f;
				}
			}
			return array_key_exists($feature, $this->featureLookup);
		}
		
		public function devices($index = null) {
			global $pdo;
			$a = array();
			$sql = "SELECT d.*, t.accessed FROM onsong_connect_token t INNER JOIN onsong_connect_role r ON r.ID = t.roleID INNER JOIN onsong_connect_device d ON d.ID = t.deviceID WHERE r.accountID = ? AND r.deleted IS NULL ORDER BY d.name";
			$statement = $pdo->prepare($sql);
			$statement->execute(array($this->ID()));
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

		// Determine if they are using OnSong Pro
		public function isPro() {
			foreach($this->devices() as $device) {
				if(strpos($device->appID(), "Pro") !== false || strpos($device->appID(), "2020") !== false) {
					return true;
				}
			}
			return false;
		}

		// Determine if they are an OnSong free user
		public function isFree() {
			foreach($this->devices() as $device) {
				if($device->appID() == "OnSong") {
					return true;
				}
			}
			return false;
		}
		
		// Return a list of apps the user is using
		public function apps() {
			$a = array();
			foreach($this->devices() as $device) {
				if(!empty($device->appID()) && in_array($device->appID(), $a) == false) {
					array_push($a, $device->appID());
				}
			}
			return $a;
		}
		
		public function roles($permissions = null, $includeDeleted = false) {
			global $pdo;

			$roles = array();
			$params = array($this->ID());
			$sql = "SELECT * FROM onsong_connect_role WHERE accountID = ? ";
			
			// Handle permissions if provided
			if(is_null($permissions) == false) {
				$sql .= " AND (permissions & ?) = ? ";
				array_push($params, $permissions);
				array_push($params, $permissions);
			}
			
			// If we are not including deleted
			if($includeDeleted == false) {
				$sql .= " AND deleted IS NULL";
			}
			
			// Order by deleted, then created
			$sql .= " ORDER BY permissions DESC, deleted ASC, created DESC ";
			
			// Execute the statement
			$statement = $pdo->prepare($sql);
			$statement->execute($params);
			while($statement && $row = $statement->fetch(PDO::FETCH_ASSOC)) {
				$role = new Role($row);
				if(!empty(Role::current()) && $role->ID() == Role::current()->ID()) {
					array_unshift($roles, $role);
				} else {
					array_push($roles, $role);
				}
			}
			return $roles;
		}
		
		public function admin() {
			foreach($this->roles(Role::PERMISSIONS_INVITE) as $role) {
				return $role->user();
			}
			return null;
		}
		
		public function members($permissions = null) {
			global $pdo;

			$members = array();
			$params = array($this->ID());
			$sql = "SELECT u.* FROM onsong_connect_user u INNER JOIN onsong_connect_role r ON u.ID = r.userID WHERE r.accountID = ? AND r.deleted IS NULL";
			
			// Handle permissions if provided
			if(is_null($permissions) == false) {
				$sql .= " AND (permissions & ?) = ? ";
				array_push($params, $permissions);
				array_push($params, $permissions);
			}
			
			// Bring up the oldest administrators first
			$sql .= " ORDER BY r.created ";

			// Execute the statement
			$statement = $pdo->prepare($sql);
			$statement->execute($params);
			while($statement && $row = $statement->fetch(PDO::FETCH_ASSOC)) {
				array_push($members, new User($row));
			}
			return $members;
		}
		
		public function affiliates($value = null) {
			global $pdo;
			
			// Get a list of the managed affiliates
			$existing = array();
			$a = array();
			$sql = "SELECT * FROM onsong_connect_account_affiliate WHERE accountID = ? ";
			$statement = $pdo->prepare($sql);
			$statement->execute(array($this->ID()));
			if($statement) {
				while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
					$us = new AccountAffiliate($row);
					array_push($a, $us);
					array_push($existing, $us->affiliateID());
				}
			}

			// If we have no value to set, return the list
			if(is_array($value) == false) {
				return $a;
			}

			// Otherwise,
			else {
				
				// If we are new, save this after
				if($this->isNew()) {
					$this->affiliatesToSave = $value;
				}
				
				// Otherwise
				else {

					// Prepare a list of string affiliate IDs
					$input = array();
					// Review each item in the array
					foreach($value as $new) {
						
						// Get the affiliate ID
						$affiliateID = null;
						if(is_object($new)) {
							$affiliateID = $new->affiliateID();
						} else if(is_array($new)) {
							$affiliateID = $new['affiliateID'];
						} else if(is_string($new)) {
							$affiliateID = $new;
						}
						if(!empty($affiliateID)) {
							array_push($input, $affiliateID);
						}
					}
	
					// Create removes and adds
					$toRemove = array();
					$toAdd = array();
	
					// Loop though the input
					foreach($input as $affiliateID) {
	
						// If this is not in the list of existing ids,
						if(in_array($affiliateID, $existing) == false) {
	
							// Then we should add it
							array_push($toAdd, $affiliateID);
						}
					}
					
					// Now see if there are items missing that should be removed
					foreach($a as $old) {
						
						// If it's not in the value arrays
						if(in_array($old->affiliateID(), $input) == false) {
	
							// Then we should remove it
							array_push($toRemove, $old);
						}
					}
					
					// Now let's remove the old item
					foreach($toRemove as $remove) {
						$remove->delete();
					}
					
					// Then add the new ones
					foreach($toAdd as $add) {
						$us = new AccountAffiliate();
						$us->account($this);
						$us->affiliateID($add);
						$us->save();
					}
					
					// Clear out affiliates to save
					$this->affiliatesToSave = null;
				}
			}
		}
		
		public function save(&$errors = null, $exceptions = null) {
			$success = parent::save($errors, $exceptions);
			if($success) {

				// Now update the billing lists if needed
				foreach($this->members(Role::PERMISSIONS_BILLING) as $user) {
					
					// If we are cancelled, then unsubscribe
					if($this->cancelled) {
						sendy_unsubscribe(null, $user->email());
					}
					
					// If we moved to a paid account, let's remove them from that list
					if($this->wasFree != (!$this->isPaid())) {
						if($this->wasFree && $this->isPaid()) {
							sendy_unsubscribe("uZ53lsgZC5Pwm6sd8J6OnA", $user->email());
						} else if($this->wasFree == false && $this->isPaid() == false) {
							sendy_subscribe("uZ53lsgZC5Pwm6sd8J6OnA", $user->email(), $user->fullName(), array("Language"=>$user->language(), "State"=>$user->locationStateID()));
						}
						$this->wasFree = (!$this->isPaid());
					}
		
					// If we've changed our expired status
					if($this->wasExpired != $this->isExpired()) {
						
						// If we've expired, then add to the expired accounts list
						if($this->isExpired()) {
							sendy_subscribe("rXpdkVlxvN5x3opwE2lKdQ", $user->email(), $user->fullName(), array("Language"=>$user->language(), "State"=>$user->locationStateID()));
						}

						// Otherwise, unsubscribe from that list
						else {
							sendy_unsubscribe("rXpdkVlxvN5x3opwE2lKdQ", $user->email());
						}
						$this->wasExpired = $this->isExpired();
					}
				}
			}
			return $success;
		}

		public function delete($permanently = false, $ignorePermissions = false) {
			foreach($this->roles() as $role) {
				$role->delete($permanently);
			}
			return parent::delete($permanently, $ignorePermissions);
		}

		// Reviews the number of users available in the plan and deactivates the least active members
		public function cull() {
			global $pdo;
			
			// First, see if we even have a plan
			$plan = $this->plan();
			
			// If there's not plan, just return null
			if(empty($plan)) { return null; }
			
			// Prepare an array of roles that will be deactivated
			$deactivated = array();
			
			// Get the number of active roles
			$activeRoles = count($this->roles(1, false));
			
			// If we fit into the number of possible users,
			if($activeRoles <= $this->users()) {

				// Then just return an empty array
				return $deactivated;
			}
			
			// If we've gotten here, we need to cull. Get a list of roles that should be deactivated
			$sql = "SELECT r.*, MAX(t.accessed) AS accessed FROM onsong_connect_role r LEFT OUTER JOIN onsong_connect_token t ON r.ID = t.roleID WHERE accountID = ? AND r.deleted IS NULL AND r.permissions > 0 GROUP BY t.accessed ORDER BY r.permissions, MAX(t.accessed) LIMIT ? ";
			$statement = $pdo->prepare($sql);
			$statement->execute(array($this->ID(), ($activeRoles - $plan->user())));
			while($statement && $row = $statement->fetch(PDO::FETCH_ASSOC)) {
				
				// Get the role and inactivate
				$role = new Role($row);
				$role->permissions(Role::PERMISSIONS_INACTIVE);
				if($role->save()) {
					array_push($deactivated, $role);
				}
			}
			
			// Then return the list of users that was deactivated
			return $deactivated;
		}
		
		public function send($templateID, $recipients = null, $additional = null) {
			
			// If we have no template, return false
			if(empty($templateID)) { return false; }
			
			// If we have no recipient permissions, send to admins
			if(is_null($recipients)) {
				$recipients = Role::PERMISSIONS_ALL;
			}
			
			// Keep track of the additional information to send
			if(empty($additional)) {
				$additional = array();
			}
			
			// Now add the account information
			$additional["account"] = $this;
		
			// Set up the sender from email address
			$from = array("email"=>"no-reply@onsongapp.com");

			// Create a new list of users to send to
			$a = array();
			foreach($this->members($recipients) as $user) {

				// Add the user to the additional
				$additional["user"] = $user;

				// Push this to the array
				$p = array("to"=>array(array("email"=>$user->email(), "name"=>$user->fullName())), "dynamic_template_data"=>$additional);
				array_push($a, $p);
			}
		
			// Process 1000 at a time
			$success = false;
			foreach(array_chunk($a, 1000) as $recipients) {
				
				// If we have something
				if(count($recipients) > 0) {

					// Create the POST
					$post = array("template_id"=>$templateID, "personalizations"=>$recipients, "from"=>$from);
	
					// Send the email
					$result = null;
					
					sendgrid_call("mail/send", "POST", $post);
				}
			}
		}
		
		public static function fromEnrollmentCode($code) {
			global $pdo;
			
			// If we have no code, then return
			if(empty(trim($code))) { return null; }
			
			// Let's first be sure that we aren't applying an existing subscriber ID to another account
			$sql = "SELECT * FROM onsong_connect_account WHERE enrollmentCode = ? LIMIT 1";
			$statement = $pdo->prepare($sql);
			$statement->execute(array($code));
			if($statement && $row = $statement->fetch(PDO::FETCH_ASSOC)) {
				return new Account($row);
			}
			return null;
		}
		
		public static function generateEnrollmentCode() {
			$code = rand(100000, 999999);
			if(empty(static::fromEnrollmentCode($code))) {
				return $code;
			} else {
				return static::generateEnrollmentCode();
			}
		}
		
		public function enableEnrollment() {
			
			// If we don't have a code,
			if(empty($this->enrollmentCode())) {

				// Generate a random code that doesn't exist yet
				$this->enrollmentCode(static::generateEnrollmentCode());
				
				// Then save the account
				$this->save();
			}
			
			// Then return that
			return $this->enrollmentCode();
		}
		
		public function disableEnrollment() {
			if(!empty($this->enrollmentCode())) {
				$this->enrollmentCode(null);
				$this->save();
			}
		}
		
		public function enrollUser($user = null, $errors = null) {
			
			// If the errors is null, make one
			if(is_null($errors)) {
				$errors = array();
			}
			
			// If we don't have a user
			if(empty($user)) {
				
				// Then use the currently signed in user
				$user = User::current();
			}
			
			// If we don't have a user, throw an error
			if(empty($user)) {
				array_push($errors, "The user is required");
				return null;
			}
			
			// Keep track of the number of active roles
			$active = 0;
			$existingRole = null;
			
			// Now go through the roles and see if we already are a member
			foreach($this->roles(null, true) as $role) {
				
				// Update the active count if we are active
				if($role->isDeleted() == false && $role->permissions() > 0) {
					$active++;
				}
				
				// If this is the user, then it's an existing role
				if($role->userID() == $user->ID()) {
					$existingRole = $role;
				}
			}

			// Determines what permission to apply
			$permissions = ($active < $this->users()) ? Role::PERMISSIONS_READ : Role::PERMISSIONS_INACTIVE;

			// If we have an existing role, then let's just change things
			if(!empty($existingRole)) {

				// If the role is deleted, then undelete
				if($existingRole->isDeleted()) {
					$existingRole->undelete();
				}
				
				// Then set the permission if we need to
				if($existingRole->permissions() < $permissions) {
					$existingRole->permissions($permissions);
				}
			}
			
			// Otherwise, we need to set up a new role
			else {
				$existingRole = Role::create($this, $user, $permissions);
			}
			$existingRole->save($errors);
			return $existingRole;
		}

		public static function tableName() {
			return "onsong_connect_account";
		}

		public static function className() {
			return "Account";
		}

		public static function current() {
			if(Role::current()) {
				return Role::current()->account();
			}
			return null;
		}
		
		public static function fromSubscriber($subscriberID, $sourceID = null, $tierID = null) {
			global $pdo;

			// If we have no subscriber, return null
			if(empty($subscriberID)) { return null; }

			// If we have no source ID, then we need to figure that out
			if(empty($sourceID)) {
				
				// If it starts with $RC, it's Revenue Cat
				if(strpos($subscriberID, '$RC') !== false) {
					$sourceID = "revenue_cat";
				}
				
				// Otherwise, if it starts with sub_ it's stripe
				else if(strpos($subscriberID, 'sub_') !== false) {
					$sourceID = "stripe";
				}
			}
			
			// If we have no source, return null
			if(empty($sourceID)) { return null; }
			
			// Set up the parameter list
			$params = array($sourceID, $subscriberID);

			// Let's first be sure that we aren't applying an existing subscriber ID to another account
			if(empty($tierID) == false) {
				array_push($params, $tierID);
				$sql = "SELECT a.* FROM onsong_connect_account a INNER JOIN onsong_connect_plan p ON p.ID = a.planID WHERE a.sourceID = ? AND a.subscriberID = ? AND p.tierID = ?";
			} else {
				$sql = "SELECT * FROM onsong_connect_account WHERE sourceID = ? AND subscriberID = ?";
			}
			$statement = $pdo->prepare($sql);
			$statement->execute($params);
			if($statement && $row = $statement->fetch(PDO::FETCH_ASSOC)) {
				return new Account($row);
			}
			return null;
		}
		
		#[\ReturnTypeWillChange]
		public function jsonSerialize() {
			 return self::jsonSerializeIncluding(null);
		 }
		
		public function jsonSerializeIncluding($include = null) {
			$o = array();
			foreach(static::schema() as $name=>$type) {
				if(!is_null($include) && !in_array($name, $include)) { continue; }
				$value = $this->__call($name, array());
				if(isset($value) && !is_null($value) && $name != 'password' && $name != 'accountID') {
					$o[$name] = $type->jsonSerialize($value);
				}
			}
			$o['name'] = $this->name();
			$plan = $this->plan();
			if(!empty($plan)) {
				if(!empty($plan->planID())) {
					$o['planID'] = $plan->planID();
				}
			}
			return $o;
		}
		
		public static function classNamesForProperty($name) {
			if($name == "basePlan") {
				return "Plan";
			} else {
				return parent::classNamesForProperty($name);
			}
		}
	}
?>