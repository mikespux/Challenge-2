<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class Defaults {
		const VOID = 0;
		const INT = 1;
		const STRING = 2;
		const BOOLEAN = 3;
		const DATETIME = 4;
		const CALLBACK = 5;
		const FLOAT = 6;
		const RESOURCE = 7;

		private static $lookup = null;
		public static function lookup($name = null, $default = null) {
			global $pdo;
			if(self::$lookup == null) {
				self::$lookup = array();
				$statement = $pdo->prepare("SELECT name, value, type FROM onsong_connect_defaults WHERE roleID = ?");
				$statement->execute(array(API::currentToken()->role()->ID()));
				if($statement) {
					while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
						self::$lookup[$row['name']] = self::toType($row["value"], $row["type"]);
					}
				}
			}
			if(is_null($name)) {
				return self::$lookup;
			} else {
				if(isset(self::$lookup[$name])) {
					return self::$lookup[$name];
				} else {
					return $default;
				}
			}
		}

		public static function get($name, $default = null) {
			return self::lookup($name, $default);
		}

		public static function set($name, $value) {
			global $pdo;

			// Determine if this is a new item
			$isNew = isset(self::$lookup[$name]);

			// Update the static list
			self::$lookup[$name] = $value;

			// If it hasn't been set
			if($isNew) {

				// Then insert a new record
				$statement = $pdo->prepare("INSERT INTO onsong_connect_defaults ( ID, roleID, name, value, type, created, modified ) VALUES ( UUID(), ?, ?, ?, ?, NOW(), NOW() )");
				$statement->execute(array(API::currentToken()->role()->ID(), $name, $value, self::getType($value)));
				return ($statement->rowCount());
			}

			// Otherwise...
			else {

				// Update the existing record
				$statement = $pdo->prepare("UPDATE onsong_connect_defaults SET value = ?, type = ?, modified = NOW() WHERE roleID = ? AND name = ?");
				$statement->execute(array($value, self::getType($value), API::currentToken()->role()->ID(), $name));
				return ($statement->rowCount());
			}
		}

		public static function remove($name) {
			global $pdo;
			
			// Remove the static list
			unset(self::$lookup[$name]);

			// Update the database
			$statement = $pdo->prepare("DELETE FROM onsong_connect_defaults WHERE roleID = ? AND name = ?");
			$statement->execute(array(API::currentToken()->role()->ID(), $name));
			return ($statement->rowCount());
		}
		
		public static function toType($value, $type) {
			switch($type) {
				case self::INT:
					return intval($value);
				case self::BOOLEAN:
					return boolval($value);
				case self::FLOAT:
					return floatval($value);
				case self::STRING:
					return (string)$value;
				case self::DATETIME:
					return strtotime($value);
				default:
					return $value;
			}
		}
		
		public static function getType($value) {
			if(isset($value) && $value != null) {
				if(is_string($value)) {
					return self::STRING;
				} else if(is_bool($value)) {
					return self::BOOLEAN;
				} else if(is_int($value)) {
					return self::INT;
				} else if(is_float($value)) {
					return self::FLOAT;
				} else if(strtotime($value)) {
					return self::DATETIME;
				} else if(is_resource($value)) {
					return self::RESOURCE;
				} else if(is_callable($value)) {
					return self::CALLBACK;
				}
			}
			return self::VOID;
		}
	}
?>