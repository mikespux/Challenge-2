<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class DataObjectList implements JsonSerializable {
		private $query = null;
		private $sort = null;
		private $descending = null;
		private $limit = null;
		private $max = null;
		private $start = null;
		private $count = null;
		private $results = null;
		private $class = null;
		private $other = array();
		private $additional = null;
		private $status = null;
		private $process = null;
		private $ignorePermissions = false;
		private $queryMode = null;
		private $logging = false;
		private $includes = null;
		private $includeClasses = null;
		
		const STATUS_ACTIVE = 0;
		const STATUS_DELETED = 1;
		const STATUS_ALL = -1;
		
		const QUERYMODE_CONTAINS = 0;
		const QUERYMODE_STARTSWITH = 1;
		const QUERYMODE_ENDSWITH = 2;
		const QUERYMODE_EQUALS = 3;
		
		public function logging($value = null) {
			if($value != null) {
				if(is_bool($value)) {
					$this->logging = $value;
				}
			} else {
				return $this->logging;
			}
		}

		public function includes($value = false) {
			if($value === false) {
				return $this->includes;
			} else if(is_string($value)) {
				$this->includes = explode(" ", $value);
				$this->includeClasses = null;
				$this->clear();
			} else if(is_array($value)) {
				$this->includes = $value;
				$this->includeClasses = null;
				$this->clear();
			}
		}

		public function includeClasses() {
			if(!empty($this->includeClasses)) {
				return $this->includeClasses;
			}
			$a = array();
			if(!empty($this->includes())) {
				$a = $this->includes();
			}
			$sort = $this->sort();
			if(is_null($sort) == false && is_array($sort) == false) {
				$sort = [$sort];
			}
			if(!empty($sort)) {
				foreach($sort as $key=>$value) {
					$item = null;
					if(is_numeric($key)) {
						$item = $value;
					} else {
						$item = $key;
					}
					$parts = explode(".", $item);
					array_pop($parts);
					if(count($parts) > 0) {
						$path = implode(".", $parts);
						if(in_array($path, $a) == false) {
							array_push($a, $path);
						}
					}
				}
			}

			if(!empty($a)) {
				$this->includeClasses = array();
				$includes = array_unique($a);
				sort($includes);

				foreach($includes as $path) {

					// Get the property
					$parts = explode(".", $path);
					$property = array_pop($parts);

					// Establish the parent class
					$parentClass = $this->class;
					if(count($parts) > 0) {
						$parentKey = implode(".", $parts);
						if(isset($this->includeClasses[$parentKey])) {
							$parentClass = $this->includeClasses[$parentKey];
						}
					}

					// Now get the class for the property
					$c = $parentClass::classNameForProperty($property);
					if(!empty($c) && class_exists($c)) {
						$this->includeClasses[$path] = $c;
					}
				}
			}
			return $this->includeClasses;
		}

		public function query($value = null) {
			if($value != null) {
				if(is_string($value)) {
					$this->query = $value;
					$this->clear();
				}
			} else {
				return $this->query;
			}
		}

		public function queryMode($value = null) {
			if($value != null) {
				if(is_int($value) && $value >= 0 && $value <= 3) {
					$this->queryMode = $value;
					$this->clear();
				}
			} else {
				if(is_null($this->queryMode)) {
					if(is_numeric($this->query())) {
						return self::QUERYMODE_EQUALS;
					} else if(is_string($this->query()) && strlen($this->query()) <= 3) {
						return self::QUERYMODE_STARTSWITH;
					} else {
						return self::QUERYMODE_CONTAINS;
					}
				} else {
					return $this->queryMode;
				}
			}
		}

		public function sort($value = null) {
			if($value != null) {
				if(is_string($value) || is_array($value)) {
					$this->sort = $value;
					$this->includeClasses = null;
					$this->clear();
				}
			} else {
				return $this->sort;
			}
		}

		public function descending($value = null) {
			if(isset($value)) {
				if(is_string($value)) {
					$this->descending = (strnatcasecmp($value, "true") == 0 || strnatcasecmp($value, "on") == 0 || strnatcasecmp($value, "1") == 0);
				} else {
					$this->descending = (bool)$value;
				}
				$this->clear();
			} else {
				return $this->descending;
			}
		}

		public function limit($value = null) {
			if($value != null) {
				if(is_numeric($value)) {
					if($value < 0) { $value = 100; }
					$this->limit = (int)$value;
					$this->clear();
				}
			} else {
				return $this->limit;
			}
		}

		public function start($value = null) {
			if($value != null) {
				if(is_numeric($value)) {
					if($value < 0) { $value = 0; }
					$this->start = (int)$value;
					$this->clear();
				}
			} else {
				return $this->start;
			}
		}
		
		public function max($value = null) {
			if($value != null) {
				if(is_numeric($value)) {
					if($value < 0) { $value = 0; }
					$this->max = (int)$value;
					$this->clear();
				}
			} else {
				return $this->max;
			}
		}
		
		public function other($name = false, $value = "____") {
			if($name === false) {
				return $this->other;
			} else {
				if($value != "____") {
					$this->other[$name] = $value;
				} else {
					if(is_null($name)) {
						return $this->other;
					} else if(is_dict($name)) {
						$this->other = $name;
					} else if(isset($this->other[$name])) {
						return $this->other[$name];
					} else {
						return null;
					}
				}
			}
		}
		
		public function additional($value = null) {
			if(is_null($value)) {
				return $this->additional;
			} else {
				$this->additional = $value;
			}
		}
		
		public function process($value = "____") {
			if($value == "____") {
				return $this->process;
			} else if($value instanceof \Closure) {
				$this->process = $value;
			}
		}

		public function status($value = "____") {
			if($value == "____") {
				return $this->status;
			} else {
				if((is_int($value) && $value >= -1 && $value <= 1) || is_null($value)) {
					$this->status = $value;
				}
			}
		}
		
		public function ignorePermissions($value = "____") {
			if(is_bool($value)) {
				$this->ignorePermissions = $value;
			} else if($value == "____") {
				return $this->ignorePermissions;
			}
		}

		public function count() {
			global $pdo;
			if($this->count == null) {
				$statement = $this->getStatement(true);
				if($statement && $row = $statement->fetch(PDO::FETCH_NUM)) {
					$this->count = (int)$row[0];
				}
			}
			return $this->count;
		}
		
		public function results($index = null) {
			if(is_null($this->results)) {

				// Get the statement
				$statement = $this->getStatement();
				if($statement) {
					$this->results = array();
					while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
						$skip = false;
						$o = $this->createObject($row);
						if(!empty($this->process())) {
							$p = $this->process();
							$skip = boolval($p($o));
						}
						if($skip == false) {
							array_push($this->results, $o);
						}
					}
				}
			}
			if(is_null($index)) {
				return $this->results;
			} else if(is_integer($index)) {
				return $this->results[$index];
			}
		}
		
		public function createObject($row) {

			// Set up the base object
			$object = new $this->class($row);

			// Now cycle through the included classes and set things up
			if(!empty($this->includeClasses())) {
				foreach($this->includeClasses() as $keypath=>$class) {
					$path = explode(".", $keypath);
					$prefix = implode("_", $path);
					$subobject = new $class($row, $prefix ."_");

					// Now set that property to the object
					$o = $object;
					$property = array_pop($path);
					if(count($path) > 0) {
						foreach($path as $subproperty) {
							$o = $o->$subproperty();
						}
					}
					if(!empty($o)) {
						$o->$property($subobject);
					}
				}
			}
			return $object;
		}

		public function getStatement($forCount = false) {
			global $pdo;

			// Create the SQL statement and parameters list
			$params = array();
			$sql = "SELECT ";
			if($forCount) {
				$sql .= "COUNT(*)";
			} else {
				$sql .= "_.*";
			}

			// Now we are going to add includes if we have them
			$joins = "";
			if(!empty($this->includeClasses())) {
				foreach($this->includeClasses() as $keypath=>$class) {
					$path = explode(".", $keypath);
					$prefix = implode("_", $path);
					$key = array_pop($path);
					$parentKey = null;
					$parentPrefix = null;
					if(count($path) > 0) {
						$parentKey = implode(".", $path);
						$parentPrefix = implode("_", $path);
					}
					$parentClass = null;
					if(!empty($parentKey)) {
						$parentClass = $this->includeClasses()[$parentKey];
					}
					
					// Add the class properties to the select list
					foreach($class::schema() as $property=>$definition) {
						if($property == "password") { continue; }
						$sql .= ", ". $prefix .".". $property ." AS ". $prefix ."_". $property;
					}
	
					// Append to the joins
					$joins .= "LEFT OUTER JOIN ". $class::tableName() ." AS ". $prefix ." ON ". $prefix .".ID = ";
					if($parentClass) {
						$joins .= $parentPrefix. ".". $key ."ID ";
					} else {
						$joins .= "_.". $key ."ID ";
					}
				}
			}

			// Set up the main table clause
			$sql .= " FROM ". call_user_func(array($this->class, 'tableName')) ." _ ";

			// Now add the joins
			if(!empty($joins)) {
				$sql .= $joins;
			}

			$sql .= " WHERE 1 = 1";
			
			// Determine if we should limit by deleted items
			if(call_user_func(array($this->class, 'schema'), 'deleted') != null) {
				if(empty($this->status)) {
					$sql .= " AND (_.deleted IS NULL OR _.deleted > NOW())";
				} else if($this->status == self::STATUS_ACTIVE) {
					$sql .= " AND (_.deleted IS NULL OR _.deleted > NOW())";
				} else if($this->status == self::STATUS_DELETED) {
					$sql .= " AND (_.deleted IS NOT NULL AND _.deleted <= NOW())";
				} else if($this->status == self::STATUS_ALL) {
					// Just list everything
				}
			}

			// Filter based on other properties
			foreach($this->other as $name=>$value) {
				$not = false;
				if(startsWith($name, "!")) {
					$not = true;
					$name = substr($name, 1);
				}

				if(call_user_func(array($this->class, 'schema'), $name) != null) {
					if(is_null($value)) {
						$sql .= " AND _.". $name ." IS NULL";
					} else {
						// If this is a list, then treat as a subquery
						if($value instanceof DataObjectList) {
							$sql .= "_." . $name . " IN(";
							$i = 0;
							foreach($value->results() as $item) {
								if($i > 0) {
									$sql .= ", ";
								}
								$sql .= "?";
								array_push($params, $item->ID());
							}
							$sql .= ")";
						}
						else if($value instanceof DataObject) {
							$sql .= " AND _." . $name . " = ?";
							array_push($params, $value->ID());
						}
						else if(is_array($value)) {
							$sql .= " AND ( ";
							$i = 0;
							
							if(is_dict($value) || isset($value[0]) == false) {
								foreach($value as $value=>$operator) {
									$v = $value;
									if($i > 0) {
										$sql .= " OR ";
									}
									$sql .= "_." . $name;
									if($operator == "gt" || $operator == ">") {
										$sql .= " > ";
									} else if($operator == "gte" || $operator == ">=") {
										$sql .= " >= ";
									} else if($operator == "lt" || $operator == "<") {
										$sql .= " < ";
									} else if($operator == "lte" || $operator == "<=") {
										$sql .= " <= ";
									} else if($operator == "ne" || $operator == "<>" || $operator == "!=") {
										$sql .= " <> ";
									} else if($operator == "eq" || $operator == "=") {
										$sql .= " = ";
									} else if($operator == "cn" || $operator == "contains") {
										$sql .= " LIKE ";
										$v = '%' . $v . '%';
									} else if($operator == "sw" || $operator == "starts-with") {
										$sql .= " LIKE ";
										$v = $v . '%';
									} else if($operator == "ew" || $operator == "ends-with") {
										$sql .= " LIKE ";
										$v = '%' . $v;
									} else {
										$sql .= " = ";
									}
									if($v !== false) {
										$sql .= "?";
										array_push($params, $v);
									}
								}
							} else {
								$sql .= "_." . $name ." IN(";
								foreach($value as $v) {
									if($i > 0) {
										$sql .= ", ";
									}
									$sql .= "?";
									array_push($params, $v);
									$i++;
								}
								$sql .= ")";
							}
							$sql .= " ) ";
						} else {
							if($value == "*") {
								$value = null;
								$sql .= " AND _.". $name ." IS NOT NULL";
							} else if(strpos($value, '%') !== false) {
								$sql .= " AND _.". $name ." LIKE ?";
							} else {
								$sql .= " AND _.". $name ." = ?";
							}
							if(is_null($value) == false) {
								array_push($params, $value);
							}
						}
					}
				}
			}
			
			// Filter based on query search term
			if($this->query() != null) {
				$ql = "";
				foreach(call_user_func(array($this->class, 'queryList')) as $column) {
					if(strlen($ql) > 0) {
						$ql .= " OR ";
					}
					if(is_array($column)) {
						$s = "";
						foreach($column as $c) {
							if(strlen($s) > 0) {
								$s .= ", ' ', ";
							}
							$s .= $c;
						}
						$column = "CONCAT(". $s .")";
					}
					switch($this->queryMode()) {
						case self::QUERYMODE_STARTSWITH:
							$ql .= $column ." LIKE ?";
							array_push($params, $this->query() . "%");
							break;
						case self::QUERYMODE_ENDSWITH:
							$ql .= $column ." LIKE ?";
							array_push($params, "%" . $this->query());
							break;
						case self::QUERYMODE_EQUALS:
							$ql .= $column ." = ?";
							array_push($params, $this->query());
							break;
						default:
							$ql .= $column ." LIKE ?";
							array_push($params, "%" . $this->query() . "%");
							break;
					}
				}
				$sql .= " AND (". $ql .")";
			}
			
			// Add any additional querying
			if(empty($this->additional()) == false) {
				$sql .= " AND ". $this->additional();
			}

			// Filter based on account
			if(!$this->ignorePermissions()) {
				if(call_user_func(array($this->class, 'schema'), "accountID") != null) {
					$sql .= " AND accountID = ?";
					array_push($params, API::currentToken()->role()->accountID());
				}
/*
				if(call_user_func(array($this->class, 'schema'), "userID") != null) {
					$sql .= " AND userID = ?";
					array_push($params, API::currentToken()->role()->userID());
				}
*/
			}

			// Sort the list of items
			if(!$forCount) {
				if($this->sort() != null) {
					$orderBy = "";
					$sortOptions = array();
					if(is_string($this->sort())) {
						$sortOptions[$this->sort()] = $this->descending();
					} else if(is_array($this->sort())) {
						foreach($this->sort() as $key=>$value) {
							if(is_integer($key)) {
								$sortOptions[$value] = (is_null($this->descending())) ? false : $this->descending();
							} else {
								$sortOptions[$key] = boolval($value);
							}
						}
					}

					foreach($sortOptions as $key=>$descending) {
						$parts = explode(".", $key);
						$column = array_pop($parts);
						$class = $this->class;
						if(count($parts) > 0) {
							$path = implode(".", $parts);
							$prefix = implode("_", $parts);
							if(isset($this->includeClasses()[$path])) {
								$class = $this->includeClasses()[$path];
							} else {
								$class = null;
							}
						} else {
							$prefix = "_";
						}
						
						if(strpos($column, "(") !== false || (is_null($class) == false && call_user_func(array($class, 'schema'), $column) != null)) {
							if(strlen($orderBy) > 0) {
								$orderBy .= ", ";
							}
							if(strpos($column, "(") !== false) {
								$orderBy .= DataObject::safe($column);
							} else {
								$orderBy .= $prefix .".". DataObject::safe($column);
							}
							if($descending) {
								$orderBy .= " DESC";
							} else {
								$orderBy .= " ASC";
							}
						}
					}
					if(strlen($orderBy) > 0) {
						$sql .= " ORDER BY ". $orderBy;
					}
				}
	
				// Apply limit and offset, if we aren't counting
				$l = null;
				if($this->max() != null) {
					$l = $this->max();
				}
				if($this->limit() != null && (is_null($l) || $this->limit() < $l)) {
					$l = $this->limit();
				}
				if(is_null($l) == false) {
					$sql .= " LIMIT ?";
					array_push($params, $l);
				}
				if($this->start() > 0) {
					$sql .= " OFFSET ?";
					array_push($params, $this->start());
				}
			}
			
			// Output the SQL and parameters
			if($this->logging) {
				var_dump($sql);
				var_dump($params);
			}

			// Prepare, execute and return the statement
			$statement = null;
			try {
				$statement = $pdo->prepare($sql);
				$statement->execute($params);
			} catch(PDOException $e) {
			}
			return $statement;
		}

		public function clear() {
			$count = null;
			$results = null;
		}
		
		public function run() {
			$this->clear();
			$this->count();
			$this->results();
		}

		public function __construct($resultsClass, $attributes = null, $force = false) {

			$this->class = $resultsClass;

			if($attributes != null) {
				foreach($attributes as $name=>$value) {
					if($name == "q" || $name == "query") {
						$this->query($value);
					} else if($name == "mode") {
						$this->queryMode(intval($value));
					} else if($name == "sort") {
						$this->sort($value);
					} else if($name == "descending") {
						$this->descending($value);
					} else if($name == "limit") {
						$this->limit($value);
					} else if($name == "start") {
						$this->start($value);
					} else if($name == "max") {
						$this->max($value);
					} else if($name == "other") {
						foreach($value as $a=>$b) {
							if($force || call_user_func(array($this->class, 'schema'), $a) != null) {
								$this->other[$a] = $b;
							}
						}
					} else if($force || call_user_func(array($this->class, 'schema'), $name) != null) {
						$this->other[$name] = $value;
					}
				}
			}
		}

		public function __call($name, $arguments) {

			// If there are no arguments
			if(count($arguments) == 0) {
				if(array_key_exists($name, $this->other)) {
		            return $this->other[$name];
				}
				return null;
			}

			// If there are arguments, validate and set
			else {
				$value = $arguments[0];
				$this->other[$name] = $value;
			}
		}

		#[\ReturnTypeWillChange]
		public function jsonSerialize() {
			$a = array();
			if($this->query() != null) {
				$a["q"] = $this->query();
			}
			if($this->sort() != null) {
				$a["sort"] = $this->sort();
			}
			if($this->descending() != null) {
				$a["descending"] = $this->descending();
			}
			if($this->limit() != null) {
				$a["limit"] = $this->limit();
			}
			if($this->max() != null) {
				$a["max"] = $this->max();
			}
			if($this->start() != null) {
				$a["start"] = $this->start();
			}
			foreach($this->other as $name=>$value) {
				$a[$name] = $value;
			}
			return array("count"=>$this->count(), "results"=>$this->results(), "attributes"=>$a);
		}
	}
?>