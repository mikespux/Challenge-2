<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");
	
	class PivotalObject extends DataObject {
		
		public static function serializeDate($value) {
			$time = strtotime($value);
			if($time > 0) {
			
				// Now handle serialization
				if(static::dateSerialization() == "ms") {
					return $time;
				} else if(static::dateSerialization() == "js") {
					return 'new Date(\''. $value .'\')';
				} else {
					return $value;
				}
			}
			return null;
		}
		
		public static function dateSerialization($value = "____") {
			static $dateSerialization = null;
			if($value == "____") {
				if($dateSerialization == null) {
					$dateSerialization = "iso";
					if(isset($_REQUEST['date_format'])) {
						if($_REQUEST['date_format'] == 'millis') {
							$dateSerialization = "ms";
						}
					}
				}
				return $dateSerialization;
			} else {
				if($value == "iso" || $value == "ms" || $value == "js") {
					$dateSerialization = $value;
				}
			}
		}
		
		public function jsonIncludes($value = false) {
			if($value === false) {
				$a = parent::jsonIncludes();
				if(is_null($a) && isset($_REQUEST['fields'])) {
					$a = array();
					foreach(array_map('trim', explode(",", $_REQUEST['fields'])) as $field) {
						if(in_array($field, $a) == false) {
							array_push($a, "+" . $field);
						}
					}
				}
				return $a;
			} else {
				parent::jsonIncludes($value);
			}
		}
		
		public function kind() {
			$kind = get_class($this);
			if(startsWith($kind, "Pivotal")) {
				$kind = substr($kind, 7);
				if(startsWith($kind, "Project") && $kind != "Project") {
					$kind = substr($kind, 7);
				}
				if(startsWith($kind, "Story") && $kind != "Story") {
					$kind = substr($kind, 5);
				}
			}
			return $kind;
		}

		public function jsonProcess($o) {
			$o = parent::jsonProcess($o);
			
			$kind = $this->kind();
			
			$a = array('kind'=>strtolower($kind));
			foreach($o as $key=>$value) {
				if($key == "sort") { continue; }
				if($key == "userID") { $key = "personID"; }
				if(!empty($value) && is_string($value) && strpos($value, "-") !== false && strpos($value, ":") !== false) {
					$time = strtotime($value);
					if($time > 0) {
						
						// Alter the key
						if(startsWith($key, "date") == false) {
							if($key == "modified")  {
								$key = "updated";
							}
							$key = $key . "At";
						}
						
						$value = static::serializeDate($value);
					}
				}
				$a[$this->camelToSnake($key)] = $value;
			}
			return $a;
		}

		private function camelToSnake($input) { 
			return ltrim(strtolower(preg_replace('/[A-Z]([A-Z](?![a-z]))*/', '_$0', $input)), '_');
		}
	}
?>