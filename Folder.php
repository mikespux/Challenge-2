<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class Folder extends DataObject {

		private $sets = null;
		private $show_sets = false;
		private $sets_changed = false;
		
		const OrderMethodTitle = "title";
		const OrderMethodIndex = "orderIndex";

		public static function tableName() {
			return "onsong_connect_set_folder";
		}

		public static function className() {
			return "Folder";
		}
		
		public static function queryList() {
			return array("title");
		}
		
		public static function updateQuantities() {
			global $pdo;
			$statement = $pdo->prepare("UPDATE onsong_connect_set_folder f SET f.quantity = ( SELECT COUNT(s.ID) FROM onsong_connect_set s INNER JOIN onsong_connect_set_folder_item r ON s.ID = r.setID WHERE r.folderID = f.ID )");
			$statement->execute();
		}
		
		public function sets($value = null) {
			global $pdo;

			if(is_null($value)) {
				if($this->sets == null) {
					$sql = "SELECT s.* FROM onsong_connect_set_folder_item i INNER JOIN onsong_connect_set s ON f.setID = s.ID WHERE s.accountID = ? AND i.folderID = ? ORDER BY ";
					if($this->orderMethod() != null) {
						$sql .= "i." . $this->orderMethod();
					} else {
						$sql .= "i.orderIndex";
					}
					if($this->orderDirection() != null) {
						if($this->orderDirection()) {
							$sql .= " DESC";
						} else {
							$sql .= " ASC";
						}
					}
					$statement = $pdo->prepare($sql);
					$statement->execute(array($this->accountID(), $this->ID()));
					if($statement) {
						$this->sets = array();
						while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
							array_push($this->sets, new Set($row));
						}
					}
				}

				// Otherwise, let's make sure that if they are strings, they are converted into sets
				else {
					for($i=0;$i<count($this->sets);$i++) {
						$item = $this->sets[$i];
						if(is_string($item)) {
							$this->sets[$i] = new Set($item);
						}
					}
				}
				return $this->sets;
			} else if(is_int($value)) {
				return $this->sets()[$value];
			} else { 
				if(is_string($value)) {
					$value = array($value);
				}
				if(is_array($value)) {
					$this->sets = $value;
					$this->sets_changed = true;
				}
			}
		}

		public function save(&$errors = null, $exceptions = null) {
			global $pdo;
			
			if($errors == null) {
				$errors = array();
			}
			if(parent::save($errors)) {

				// If we've made changes to sets,
				if($this->sets_changed && $this->sets != null) {
					
					// Set the processing column so that we can know which ones weren't handled
					$processID = uniqid("", true);
					$statement = $pdo->prepare("UPDATE onsong_connect_set_folder_item SET processID = ? WHERE folderID = ?");
					$statement->execute(array($processID, $this->ID()));

					// Then save those changes
					for($i=0;$i<count($this->sets);$i++) {
						
						// Get the set identifier
						$set = $this->sets[$i];
						$setID = null;
						if(is_object($set)) {
							$setID = $set->ID();
						} else if(is_string($set)) {
							$setID = $set;
						}

						// If we don't have a set ID, then skip along little fella...
						if($setID == null) { continue; }

						// See if the set is already in the list
						$statement = $pdo->prepare("SELECT ID FROM onsong_connect_set_folder_item WHERE folderID = ? AND setID = ? AND processID IS NOT NULL ORDER BY orderIndex");
						$statement->execute(array($this->ID(), $setID));
						if($statement && $row = $statement->fetch(PDO::FETCH_ASSOC)) {
							
							// Then update the order index, but leave everything else alone
							$statement = $pdo->prepare("UPDATE onsong_connect_set_folder_item SET orderIndex = ?, deleted = NULL, modified = NOW(), processID = NULL WHERE ID = ?");
							$statement->execute(array($i, $row['ID']));
						}

						// Otherwise, let's insert it instead
						else {
							$statement = $pdo->prepare("INSERT INTO onsong_connect_set_folder_item ( ID, folderID, setID, orderIndex, created, modified ) VALUES ( UUID(), ?, ?, ?, NOW(), NOW() )");
							$statement->execute(array($this->ID(), $setID, $i));
						}
					}

					// Now let's mark all the processed items as deleted because they were not handled
					$statement = $pdo->prepare("UPDATE onsong_connect_set_folder_item SET deleted = NOW() WHERE folderID = ? AND processID = ?");
					$statement->execute(array($this->ID(), $processID));

					// Mark the sets as not being changed
					$this->sets_changed = false;
				}
				return true;
			}
			return false;
		}
	}
?>