<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class DataObject implements JsonSerializable {
		protected $data = array();
		protected $changed = array();
		protected $loggingEnabled = false;
		protected $properties = array();
		private $additional = null;
		private $setting = null;
		private $jsonIncludes;
		private $temporary = false;

		private static $schema = array();

		public function __construct($data = null, $qualifier = null) {
			$this->data = array();
			if($data != null) {
				if($data instanceof DataObject) {
					$data = $data->data;
				}
				foreach((array)$data as $key=>$value) {
					if(is_numeric($key)) {
						if(!empty($qualifier) && $qualifier instanceof PDOStatement) {
							$meta = $qualifier->getColumnMeta($key);
							if(empty($meta["table"]) || $meta["table"] == static::tableName()) {
								self::__call($meta["name"], array($value));
							}
						}
					} else {
						if(empty($qualifier)) {
							self::__call($key, array($value));
						} else if(is_string($qualifier)) {
							$len = strlen($qualifier); 
							if(substr($key, 0, $len) === $qualifier) {
								$key = substr($key, $len);
								self::__call($key, array($value));
							}
						}
					}
				}
			}
			if(static::className() != "Role" && static::schema("accountID") != null) {
				if(API::currentToken() != null && API::currentToken()->role() != null && empty($this->accountID())) {
					$this->accountID(API::currentToken()->role()->accountID());
				}
			}
/*
			if(static::className() != "Role" && static::schema("userID") != null) {
				if(API::currentToken() != null && API::currentToken()->role() != null && empty($this->userID())) {
					$this->userID(API::currentToken()->role()->userID());
				}
			}
*/
		}

		public function __call($name, $arguments) {
			global $pdo;

			// Get the type of the property
			$type = static::schema($name);

			// If not found, let's try to process some other way
			if($type == null) {
				if(is_null($arguments) || count($arguments) == 0) {
					if($this->hasProperty($name)) {
						return $this->getProperty($name);
					} else {
						if(isset($this->data[$name])) {
							return $this->data[$name];
						} else {
							return null;
						}
					}
				} else {
					if($this->hasProperty($name)) {
						$this->setProperty($name, $arguments);
					} else {
						if(isset($this->data[$name])) {
							$this->changed[$name] = $this->data[$name];
						}
						$this->data[$name] = $arguments[0];
					}
					return;
				}
			}

			// If there are no arguments
			if($arguments == null || count($arguments) == 0) {
				if(array_key_exists($name, $this->data)) {
		            return $type->convertValue($this->data[$name]);
				} else {
					return $type->defaultValue();
				}
				return null;
			}

			// If there are arguments, validate and set
			else {
				$value = $arguments[0];
				if($type->check($value) == false) {
					$value = $type->convertValue($value);
				}
				if($type->check($value)) {
					$this->data[$name] = $value;
				}
			}
		}
		
		public function populate($values, $allowed = null) {
			$output = array();
			foreach((array)$values as $name=>$value) {
				if(is_string($name)) {
					if($allowed == null || in_array($name, $allowed)) {
						if(method_exists($this, $name)) {
							$original = call_user_func(array($this, $name));
							if($original != $value) {
								call_user_func(array($this, $name), $value);
								if($original != $value) {
									$output[$name] = $value;
								}
							}
						} else {
							$original = $this->__call($name, array());
							if($original != $value) {
								$this->__call($name, array($value));
								if($original != $value) {
									$output[$name] = $value;
								}
							}
						}
					}
				}
			}
			return $output;
		}

		public function data($input = null) {
			if($input == null) {
				return json_encode($this->data);
			} else {
				foreach($input as $name=>$value) {
					$this->__call($name, array($value));
				}
			}
		}

		public function value($name, $value = "____") {
			if(is_string($value) && $value == "____") {
				if(isset($this->data[$name])) {
					return $this->data[$name];
				}
				return null;
			} else {
				$this->data[$name] = $value;
			}
		}
		
		public function jsonIncludes($value = false) {
			if($value === false) {
				return $this->jsonIncludes;
			} else {
				$this->jsonIncludes = $value;
			}
		}
		
		public function loggingEnabled($flag = null) {
			if($flag == null) {
				return $this->loggingEnabled;
			} else {
				$this->loggingEnabled = $flag;
			}
		}
		
		public function additional($value = "____") {
			if($value == "____") {
				return $this->additional;
			} else {
				$this->additional = $value;
			}
		}
		
		public function hasChanges() {
			foreach($this->changes as $name=>$original) {
				if(isset($this->data[$name])) {
					if($this->data[$name] != $original) {
						return true;
					}
				}
			}
			return false;
		}
		
		public function hasChanged($property) {
			if(isset($this->data[$property])) {
				$new = $this->data[$property];
				if(isset($this->changes[$property])) {
					$original = $this->changes[$property];
					return ($new != $original);
				}
			}
			return false;
		}
		
		public function temporary($value = "____") {
			if($value == "____") {
				return $this->temporary;
			} else {
				$this->temporary = boolval($value);
			}
		}
		
		public static function classNamesForProperty($name) {
							
			// Create an array of possible class names
			$classNames = array(static::className() . ucwords($name));
			
			// Handle the camelcase back reference
			$words = preg_split('/(?=[A-Z])/', static::className());
			
			// Remove any blanks and reindex
			$words = array_values(array_filter($words));
			
			array_pop($words);
			array_push($classNames, implode("", $words) . ucwords($name));
			if(count($words) > 0) {
				array_push($classNames, $words[0] . ucwords($name));
			}
			array_push($classNames, ucwords($name));
			return $classNames;
		}
		
		public static function classNameForProperty($name) {

			// Get a list of class names
			$classNames = static::classNamesForProperty($name);
				
			// If it's not an array, make it one
			if(is_array($classNames) == false) {
				$classNames = array($classNames);
			}

			// Now go through the class names and see if it works
			foreach($classNames as $className) {
				if(!empty($className) && class_exists($className)) {
					return $className;
				}
			}
			return null;
		}

		public function hasProperty($name, $force = false) {
			if($force = false && (method_exists($this, $name) || array_key_exists($name, $this->properties))) {
				return true;
			} else if(static::schema($name . "ID") != null) {

				// Get a list of class names
				$classNames = static::classNamesForProperty($name);
					
				// If it's not an array, make it one
				if(is_array($classNames) == false) {
					$classNames = array($classNames);
				}

				// Now go through the class names and see if it works
				foreach($classNames as $className) {
					if(!empty($className) && class_exists($className)) {
						$this->addProperty($name, $className, $name . "ID");
						return true;
					}
				}
			}
			return false;
		}

		public function addProperty($name, $className, $key, $readOnly = false) {
			$this->properties[$name] = array("class"=>$className, "key"=>$key, "readOnly"=>$readOnly);
		}
		
		public function getProperty($name) {
			$dbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
			$caller = isset($dbt[2]['function']) ? $dbt[2]['function'] : null;

			if(method_exists($this, $name) && $caller != $name) {
				return call_user_func(array($this, $name));
			}
			if($caller == $name) {
				$this->hasProperty($name, true);
			}
			$property = $this->properties[$name];
			if($property) {
				if(isset($property["object"])) {
					return $property["object"];
				} else {
					$foreignKey = null;
					if(isset($this->data[$property["key"]])) {
						$foreignKey = $this->data[$property["key"]];
					}
					if($foreignKey) {
						$className = $property["class"];
						$object = $className::retrieve($foreignKey, false, true);
						$property["object"] = $object;
						$this->properties[$name] = $property;
						return $object;
					}
				}
			}
			return null;
		}
		
		public function setProperty($name, $arguments) {
			if(method_exists($this, $name)) {
				if($this->setting == $name) {
					if(isset($this->data[$name])) {
						$this->changed[$name] = $this->data[$name];
					}
					$this->data[$name] = $arguments[0];
					$this->setting = null;
				} else {
					$this->setting = $name;
					call_user_func_array(array($this, $name), $arguments);
				}
			} else {
				$this->properties[$name]["object"] = $arguments[0];
				
				$key = $this->properties[$name]["key"];
				if(isset($this->data[$key])) {
					$this->changed[$key] = $this->data[$key];
				}
				if(is_null($arguments[0])) {
					$this->data[$key] = null;
				} else {
					if(is_object($arguments[0])) {
						$this->data[$key] = $arguments[0]->ID();
					} else if(is_assoc($arguments[0]) && isset($arguments[0]['ID'])) {
						$this->data[$key] = $arguments[0]['ID'];
					}
				}
			}
		}
		
		public static function selectList($prefix = "") {
			$s = "";
			foreach($this->schema() as $name=>$value) {
				if(strlen($s) > 0) {
					$s .= ", ";
				}
				$s .= ($prefix . $name);
			}
			return $s;
		}

		public static function schema($name = null) {
			global $pdo;
			$a = array();
			$tb = static::tableName();
			if(empty($tb)) { return null; }
			if(isset(self::$schema[$tb]) == false) {
				$sql = "SHOW COLUMNS IN ". $tb;
				$statement = $pdo->prepare($sql);
				$statement->execute();
				if($statement) {
					while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
						$ot = new DataObjectType($row['Field']);
						$ot->required(($row['Null'] == 'NO'));
						if($row['Key'] == 'PRI') {
							if(strpos($row['Extra'], 'auto_increment')) {
								$ot->primaryKey(DataObjectType::PK_AUTOINCREMENT);
							} else if($row['Type'] == 'char(36)') {
								$ot->primaryKey(DataObjectType::PK_UUID);
							} else {
								$ot->primaryKey(DataObjectType::PK_CUSTOM);
							}
						}

						$t = $row['Type'];
						if(strpos($t, 'char') !== false || strpos($t, 'text') !== false) {
							$ot->type(DataObjectType::STRING);
							$m = array();
							preg_match('/(\d+)/', $t, $m);
							if(count($m) > 1) {
								$ot->maxLength(intval($m[1]));
							}

						} else if(strpos($t, 'int') !== false) {
							$ot->type(DataObjectType::INT);
						} else if(strpos($t, 'bit') !== false || strpos($t, 'boolean') !== false) {
							$ot->type(DataObjectType::BOOLEAN);
						} else if($t == 'decimal' || $t == 'float' || $t == 'double' || $t == 'real') {
							$ot->type(DataObjectType::FLOAT);
						} else if(strpos($t, 'date') !== false || strpos($t, 'time') !== false) {
							$ot->type(DataObjectType::DATETIME);
						}
						
						if($row['Default'] != null) {
							$ot->defaultValue($row['Default']);
						}

						$a[$ot->name()] = $ot;
					}
					self::$schema[$tb] = $a;
				}
			}
			if($name != null) {
				if(isset(self::$schema[$tb][$name])) {
					return self::$schema[$tb][$name];
				}
				return null;
			} else {
				return self::$schema[$tb];
			}
		}

		public static function list($attributes = null) {
			$cn = static::className() . "List";
			if(class_exists($cn)) {
				return new $cn($attributes);
			} else {
				return new DataObjectList(static::className(), $attributes);
			}
		}

		public static function permissionsEnabled() {
			return true;
		}

		public static function tableName() {
			return null;
		}

		public static function retrieve($ID, $includeDeleted = false, $disablePermissions = false) {
			global $pdo;
			if(!empty($ID) && !empty(static::tableName())) {
				$params = array($ID);
				$sql = "SELECT * FROM ". static::tableName() ." WHERE ID = ?";
				if($includeDeleted == false && static::schema('deleted') != null) {
					$sql .= " AND deleted IS NULL";
				}
				if(static::className() != "Role" && static::schema("accountID") != null && (static::permissionsEnabled() && $disablePermissions == false)) {
					$accountID = null;
					if(is_null(API::currentToken()) == false && is_null(API::currentToken()->role()) == false) {
						$sql .= " AND accountID = ?";
						array_push($params, API::currentToken()->role()->accountID());
					} else {
						$sql .= " AND accountID = NULL";
					}
				}
				$statement = $pdo->prepare($sql);
				$statement->execute($params);
				if($statement && $row = $statement->fetch(PDO::FETCH_ASSOC)) {
					$className = static::className();
					if(class_exists($className)) {
						return new $className($row);
					}
				}
			}
			return null;
		}
		
		public function isDeleted() {
			if(empty(static::tableName())) {
				return false;
			} else {
				return !is_null($this->deleted());
			}
		}

		public function delete($permanently = false, $ignorePermissions = false) {
			global $pdo;
			if(empty(static::tableName())) { return false; }
			$params = array($this->ID());
			if($permanently == false && static::schema('deleted') != null) {
				$sql = "UPDATE ". $this->tableName() ." SET deleted = NOW() WHERE ID = ?";
			} else {
				$sql = "DELETE FROM ". $this->tableName() ." WHERE ID = ?";
			}
			if(static::className() != "Role" && static::schema("accountID") != null && $ignorePermissions == false && static::permissionsEnabled()) {
				$accountID = null;
				if(is_null(API::currentToken()) == false && is_null(API::currentToken()->role()) == false) {
					$sql .= " AND accountID = ? ";
					array_push($params, API::currentToken()->role()->accountID());
				} else {
					$sql .= " AND accountID = NULL ";
				}
			}
			$statement = $pdo->prepare($sql);
			$success = ($statement->execute($params));
			if($success) {
				$changed = array();
			}
			return $success;
		}

		public function undelete() {
			global $pdo;
			if(empty(static::tableName())) { return false; }
			if(static::schema('deleted') != null) {
				if(is_null($this->deleted()) == false) {
					$sql = "UPDATE ". $this->tableName() ." SET deleted = NULL WHERE ID = ?";
					if(static::className() != "Role" && static::schema("accountID") != null && static::permissionsEnabled()) {
						$accountID = null;
						if(is_null(API::currentToken()) == false && is_null(API::currentToken()->role()) == false) {
							$sql .= " AND accountID = ?";
							array_push($params, API::currentToken()->role()->accountID());
						} else {
							$sql .= " AND accountID = NULL";
						}
					}
					$statement = $pdo->prepare($sql);
					return ($statement->execute(array($this->ID())) && $statement->rowCount() > 0);
				}
			}
			return false;
		}

		public function isNew() {

			// Review values and determine if there are any errors
			foreach(static::schema() as $key => $type) {

				// Get the value
				$value = null;
				if(isset($this->data[$key])) {
					$value = $this->data[$key];
				}

				// If it's required and the value is null
				if($type->required() && $value == null) {

					// And if this is a primary key or the created timestamp
					if($type->primaryKey() || ($type->type() == DataObjectType::DATETIME && $type->name() == 'created')) {

						// Then it's new
						return true;
					}
				}
			}

			// Otherwise, it exists
			return false;
		}

		public function save(&$errors = null, $exceptions = null) {
			global $pdo;
			if(empty(static::tableName())) { return false; }

			$pk = null;
			$pkID = null;
			$hasErrors = false;
			$fields = array();

			// Review values and determine if there are any errors
			foreach(static::schema() as $key => $type) {

				// Ignore the exceptions if we have any
				if($exceptions != null && in_array($key, $exceptions)) {
					continue;
				}
/* Swapped the property for the data lookup */
				$value = null;
				if($key != "data" && array_key_exists($key, $this->data)) {
					$value = $type->convertValue($this->data[$key]);
				}
				else if($this->hasProperty($key)) {
					$value = $this->getProperty($key);
				}

				if($type->primaryKey()) {
					$pk = $type;
					if($value != null && strlen($value) > 0) {
						$pkID = $value;
					}
				} else {
					if($type->required()) {
						
						// If we haven't set the created date/time, then set it
						if(is_null($value) && $type->type() == DataObjectType::DATETIME && $type->name() == 'created') {
							$value = date('Y-m-d H:i:s');
						}

						// Always update the modified date/time
						else if($type->type() == DataObjectType::DATETIME && $type->name() == 'modified') {
							$value = date('Y-m-d H:i:s');
						}
						
						// If the value is null, use the default value
						else if(is_null($value)) {
							$value = $type->defaultValue();
						}

						if(!isset($value) || is_null($value) || (is_string($value) && strlen(trim($value)) == 0)) {
							$hasErrors = true;
							$msg = ucwords($key) ." is required";
							self::handleError($msg, $errors);
							continue;
						}
					}
					if($type->check($value)) {
						$fields[$key] = $type->databaseValue($value);
					} else {
						$hasErrors = true;
						$msg = ucwords($key) ." is not a valid type";
						self::handleError($msg, $errors);
						continue;
					}
				}
			}

			// If there are errors, then return false
			if($hasErrors) { return false; }
			
			// Otherwise, generate the SQL statement accordingly
			if(!$this->isNew()) {
				$sql = "";
				$params = array();
				foreach($fields as $column => $value) {
					if(strlen($sql) > 0) { $sql .= ", "; }
					$sql .= "`" . $column . "` = ";
					if(is_null($value)) {
						$sql .= "NULL";
					} else {
						$sql .= "?";
						array_push($params, $value);
					}
				}
				$sql = "UPDATE ". static::tableName() ." SET ". $sql ." WHERE ". $pk->name() ." = ? ";
				array_push($params, $pkID);
				$statement = $pdo->prepare($sql);
				
				// Log if enabled
				if($this->loggingEnabled()) {
					var_dump($sql);
					var_dump($params);
				}

				// If this worked
				if($statement->execute($params) && $statement->rowCount() > 0) {
					if(isset($params['modified'])) {
						$this->data["modified"] = $params['modified'];
					}
					return true;
				}
			}

			// If we don't have a PK ID, we will want to figure out it's value at run time
			if($pkID == null) {
				if($pk->type() == DataObjectType::STRING) {
					$statement = $pdo->prepare("SELECT UUID()");
					if($statement->execute() && $row = $statement->fetch(PDO::FETCH_NUM)) {
						$fields[$pk->name()] = $row[0];
						$this->data[$pk->name()] = $row[0];
					}
				}
			} else {
				$fields[$pk->name()] = $this->data[$pk->name()];
			}

			// Create the INSERT statement
			$params = array();
			$sql = "REPLACE INTO ". static::tableName() ." ( ";
			foreach($fields as $column => $value) {
				$sql .= '`' . $column ."`, ";
			}
			$sql = substr($sql, 0, strlen($sql) - 2);
			$sql .= " ) VALUES ( ";
			foreach($fields as $column => $value) {
				if(is_null($value)) {
					$sql .= "NULL, ";
				} else {
					array_push($params, $value);
					$sql .= "?, ";
				}
			}
			$sql = substr($sql, 0, strlen($sql) - 2);
			$sql .= " )";
			$statement = $pdo->prepare($sql);
			
			// Log if enabled
			if($this->loggingEnabled()) {
				var_dump($sql);
				var_dump($params);
			}

			$success = false;
			try {
				$success = ($statement->execute($params) && $statement->rowCount() > 0);
			} catch(Exception $ex) {
				self::handleError($ex->getMessage(), $errors);
			}
			if($success) {
				$changed = array();
				if(empty($this->created())) {
					$this->data["created"] = time();
				}
				if(empty($this->modified())) {
					$this->data["modified"] = time();
				}
				if(empty($this->ID())) {
					$this->data["ID"] = $pdo->lastInsertId();
				}
			}
			return $success;
		}

		public function validate(&$errors = null) {
			$hasErrors = false;

			// Review values and determine if there are any errors
			foreach(static::schema() as $key => $type) {
				$value = null;
				if($this->hasProperty($key)) {
					$value = $this->getProperty($key);
				} else if(isset($this->data[$key])) {
					$value = $type->convertValue($this->data[$key]);
				}

				if(!$type->primaryKey()) {
					if($type->required()) {
						if($type->type() == DataObjectType::DATETIME && ($type->name() == 'created' || $type->name() == 'modified')) {
							$value = date('Y-m-d H:i:s');
						} else if(is_null($value)) {
							$value = $type->defaultValue();
						}
						if(!isset($value) || is_null($value)) {
							$hasErrors = true;
							$msg = $key ." is required";
							self::handleError($msg, $errors);
							continue;
						}
					}
					if($type->check($value) == false) {
						$hasErrors = true;
						$msg = $key ." is not a valid type";
						self::handleError($msg, $errors);
						continue;
					}
				}
			}
			return !$hasErrors;
		}
		
		public static function queryList() {
			return ['ID'];
		}

		#[\ReturnTypeWillChange]
		public function jsonSerialize() {
			return self::jsonSerializeIncluding();
		}

		public function jsonSerializeIncluding($include = null) {
			
			if(empty($include)) {
				$include = $this->jsonIncludes();
			}
			
			// Now divide this into buckets
			$exists = array();
			$remove = array();
			$add = array();
			if(!empty($include)) {
				foreach($include as $item) {
					if(startsWith($item, "+")) {
						array_push($add, substr($item, 1));
					}
					else if(startsWith($item, "-")) {
						array_push($remove, substr($item, 1));
					}
					else {
						array_push($exists, substr($item, 1));
					}
				}
			}

			$o = array();
			foreach(static::schema() as $name=>$type) {
				if(in_array($name, $remove)) { continue; }
				if(!empty($exists) && !in_array($name, $exists)) { continue; }
				$value = $this->__call($name, array());
				if(isset($value) && !is_null($value) && $name != 'password' && $name != 'accountID') {
					$o[$name] = $type->jsonSerialize($value);
				}
			}
			if(!empty($exists)) {
				foreach($exists as $key) {
					if(!empty(static::schema($key))) {
						$o[$key] = json_decode(json_encode($this->__call($key, [])));
					}
				}
			}
			if(!empty($add)) {
				foreach($add as $key) {
					if($this->hasProperty($key)) {
						$o[$key] = json_decode(json_encode($this->__call($key, [])));
					}
				}
			}
			if(is_array($this->additional)) {
				foreach($this->additional as $name=>$type) {
					if(isset($o[$name])) {
						continue;
					}
					if(is_int($this->additional[$name])) {
						$type = new DataObjectType($name, $type);
					}
					if(!empty($type)) {
						$value = $this->__call($name, array());
						if(isset($value) && !is_null($value)) {
							$o[$name] = $type->jsonSerialize($value);
						}
					}
				}
			}
			return $this->jsonProcess($o);
		}
		
		public function jsonProcess($o) {
			return $o;
		}

		public function debug() {
			echo("DATA:\n");
			var_dump($this->data);
			echo("\n\nPROPERTIES:\n");
			var_dump($this->properties);
		}
		
		public function isEqual($object) {
			if(is_object($object) && is_a($object, $this->className(), true)) {
				return ($object->ID() == $this->ID());
			} else {
				return false;
			}
		}
		
		public function within($array) {
			foreach($array as $item) {
				if(is_object($item) && is_a($item, $this->className(), true)) {
					if($item->ID() == $this->ID()) {
						return true;
					}
				}
			}
			return false;
		}

		public function handleError($msg, &$errors = null) {
			if(isset($errors) && is_array($errors)) {
				array_push($errors, $msg);
			} else {
				throw new Exception($msg);
			}
		}
		
		public static function safe($input) {
			if($input != null) {
				return str_replace("--", "—", str_replace("'", "''", $input));
			} else {
				return "";
			}
		}
		
		public static function listClasses($suffix = null) {
			
			// Prepare the suffix filter
			if(!empty($suffix)) {
				if(endsWith($suffix, "ID")) {
					$suffix = substr($suffix, 0, strlen($suffix) - 2);
				}
				$suffix = ucwords($suffix);
			}
			
			// Keep track of the output
			$o = array();
			
			// Now loop
			foreach(scandir(__DIR__) as $filename) {
				if(endsWith($filename, ".php")) {
					$className = substr($filename, 0, strlen($filename) - 4);
					
					// If we have a filter, check the name
					if(!empty($suffix) && endsWith($className, $suffix) == false) {
						continue;
					}
					
					// Otherwise, include the class file
					if(class_exists($className, true)) {
						array_push($o, $className);
					}
				}
			}
			return $o;
		}
		
		public static function isUUID($string = null) {
			if(empty($string) || strlen($string) < 36) { return false; }
			return (preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/i', $string) == 1);
		}
	}
?>