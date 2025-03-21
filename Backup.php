<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class Backup extends S3DataObject {

		public static function tableName() {
			return "onsong_connect_backup";
		}

		public static function className() {
			return "Backup";
		}
		
		public function extension() {
			return "backup";
		}

		// Returns the name of the backup as either the title or a formatted date
		public function name($input = false) {
			if($input !== false) {
				$this->value("name", $input);
			} else {
				$name = $this->value("name");
				if(empty($name)) {
					if(!empty($this->device())) {
						$name = $this->device()->name();
					} else {
						$name = date('F d, Y', $this->modified());
					}
				}
				return $name;
			}
		}
		
		public function downloadFilename() {
			$name = $this->name();
			if($this->library() != "Default") {
				$name .= " - " . $this->library();
			}
			$name .= "." . $this->extension();
			return $name;
		}

		#[\ReturnTypeWillChange]
		public function jsonSerialize() {
			return self::jsonSerializeIncluding(null);
		}
				
		public function jsonSerializeIncluding($include = null) {
			$o = parent::jsonSerializeIncluding($include = null);
			$o["name"] = $this->name();
			return $o;
		}
	}
?>