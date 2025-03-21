<?php
	class DataObjectType {
		const SKIP = 0;
		const INT = 1;
		const STRING = 2;
		const BOOLEAN = 3;
		const DATETIME = 4;
		const CALLBACK = 5;
		const FLOAT = 6;
		const RESOURCE = 7;
		
		const PK_NONE = 0;
		const PK_CUSTOM = 1;
		const PK_AUTOINCREMENT = 2;
		const PK_UUID = 3;

		private $name = null;
		private $type = self::STRING;
		private $maxLength = 0;
		private $primaryKey = false;
		private $defaultValue = null;
		private $required = false;
		
		public function __construct($name = null, $type = null, $required = null, $maxLength = null, $primaryKey = null, $defaultValue = null) {
			if(isset($name)) {
				$this->name = $name;
			}
			if(isset($type)) {
				$this->type = $type;
			}
			if(isset($required)) {
				$this->required = $required;
			}
			if(isset($maxLength)) {
				$this->maxLength = $maxLength;
			}
			if(isset($primaryKey)) {
				$this->primaryKey = $primaryKey;
			}
			if(isset($defaultValue)) {
				$this->defaultValue = $this->convertValue($defaultValue);
			}
		}
	
		public function name($value = null) {
			if($value != null) {
				if(is_string($value)) {
					$this->name = $value;
				} else {
					trigger_error("Value must be of type string");
				}
			}
			return $this->name;
		}

		public function type($value = null) {
			if($value != null) {
				if(is_string($value) || (is_int($value) && $value >= 0 && $value <= 8)) {
					$this->type = $value;
				} else {
					trigger_error("Value must be a valid type");
				}
			}
			return $this->type;
		}
		
		public function maxLength($value = null) {
			if($value != null) {
				if(is_int($value) && $value >= 0) {
					$this->maxLength = $value;
				} else {
					trigger_error("Value must be greater than zero or zero for an undefined maximum length");
				}
			}
			return $this->maxLength;
		}

		public function primaryKey($value = null) {
			if($value != null) {
				if(is_int($value) && $value >= 0 && $value <= 3) {
					$this->primaryKey = $value;
				} else {
					trigger_error("Value must be a primary key enumeration");
				}
			}
			return $this->primaryKey;
		}

		public function defaultValue($value = null) {
			if($value != null) {
				$value = $this->convertValue($value);
				if($this->checkType($value)) {
					$this->defaultValue = $value;
				} else {
					trigger_error("Value is not the proper type");
				}
			}
			return $this->defaultValue;
		}

		public function required($value = null) {
			if($value != null) {
				if(is_bool($value)) {
					$this->required = $value;
				}
			}
			return $this->required;
		}

		public function check($value) {
			if(!isset($value) || is_null($value)) {
				if($this->required) {
					return false;
				} else {
					return true;
				}
			}
			return $this->checkType($value);
		}

		public function checkType($value) {
			if(is_int($this->type)) {
				switch($this->type) {
					case self::SKIP:
						return false;
					case self::INT:
						return is_int($value);
					case self::STRING:
						return is_string($value);
					case self::BOOLEAN:
						return is_bool($value);
					case self::DATETIME:
						if(is_numeric($value)) {
							return ($value !== 0);
						} else {
							return (strtotime((string)$value) !== 0);
						}
					case self::CALLBACK:
						return is_callable($value);
					case self::FLOAT:
						return is_float($value);
					case self::RESOURCE:
						return is_resource($value);
//					case self::ARRAY:
//						return is_array($value);
				}
			} else if(is_string($this->type)) {
				return (is_object($value) && is_subclass_of($value, $this->type));
			}
			return false;
		}

		public function convertValue($value) {
			if(!is_null($value) && is_int($this->type)) {
				switch($this->type) {
					case self::INT:
						return intval($value);
					case self::STRING:
						return strval($value);
					case self::BOOLEAN:
						if(is_string($value)) {
							$value = strtolower($value);
							if($value == "yes" || $value == "true") {
								return true;
							} else {
								return false;
							}
						} else {
							return boolval($value);
						}
						break;
					case self::DATETIME:
						if(is_numeric($value)) {
							return $value;
						} else {
							return strtotime((string)$value);
						}
					case self::FLOAT:
						return floatval($value);
				}
			}
			return $value;
		}

		public function databaseValue($value) {
			if(!is_null($value) && is_int($this->type)) {
				switch($this->type) {
					case self::INT:
						return intval($value);
					case self::STRING:
						if($this->name() == "password" && (bool)preg_match('/^[0-9a-f]{'. $this->maxLength() .'}$/i', $value) == false) {
							if($this->maxLength() >= 40) {
								return sha1(strval($value));
							} else if($this->maxLength() >= 32) {
								return md5(strval($value));
							} else {
								return strval($value);
							}
						} else {
							return strval($value);
						}
					case self::BOOLEAN:
						if(is_string($value)) {
							$value = strtolower($value);
							if($value == "yes" || $value == "true") {
								return true;
							} else {
								return false;
							}
						} else {
							return boolval($value);
						}
						break;
					case self::DATETIME:
						if(is_numeric($value)) {
							return date('Y-m-d H:i:s', $value);
						} else {
							return $value;
						}
					case self::FLOAT:
						return floatval($value);
				}
			}
			return $value;
		}

		public function jsonSerialize($value) {
			if($this->type == self::DATETIME) {
				return date(DATE_ATOM, $value);
			} else {
				return $value;
			}
		}
	}
?>