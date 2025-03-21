<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class Book extends DataObject {

		private $songs = null;
		private $songIdentifiers = null;
		private $show_songs = false;
		private $songs_changed = false;

		public static function tableName() {
			return "onsong_connect_book";
		}

		public static function className() {
			return "Book";
		}
		
		public static function queryList() {
			return array("name");
		}

		public function showSongs($value = null) {
			if($value == null) {
				return $this->show_songs;
			} else {
				if(is_bool($value)) {
					$this->show_songs = (bool)$value;
				}
			}
		}

		public function songs($value = null) {
			global $pdo;

			if(is_null($value)) {
				if($this->songs == null) {
					$sql = "SELECT s.* FROM onsong_connect_book_item i INNER JOIN onsong_connect_song s ON i.songID = s.ID WHERE s.accountID = ? AND i.bookID = ? AND i.deleted IS NULL AND s.deleted IS NULL ORDER BY ";
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
						$this->songs = array();
						while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
							array_push($this->songs, new Song($row));
						}
					}
				}

				// Otherwise, let's make sure that if they are strings, they are converted into songs
				else {
					for($i=0;$i<count($this->songs);$i++) {
						$item = $this->songs[$i];
						if(is_string($item)) {
							$this->songs[$i] = new Song($item);
						}
					}
				}
				return $this->songs;
			} else if(is_int($value)) {
				return $this->songs()[$value];
			} else { 
				if(is_string($value)) {
					$value = array($value);
				}
				if(is_array($value)) {
					$this->songIdentifiers = null;
					$this->songs = $value;
					$this->songs_changed = true;
				}
			}
		}

		public function songIdentifiers($value = null) {
			global $pdo;

			if(is_null($value)) {
				if($this->songIdentifiers == null) {
					$sql = "SELECT s.ID FROM onsong_connect_book_item i INNER JOIN onsong_connect_song s ON i.songID = s.ID WHERE s.accountID = ? AND i.bookID = ? AND i.deleted IS NULL AND s.deleted IS NULL ORDER BY ";
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
						$this->songIdentifiers = array();
						while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
							array_push($this->songIdentifiers, $row["ID"]);
						}
					}
				}
				return $this->songIdentifiers;
			} else if(is_int($value)) {
				return $this->songIdentifiers()[$value];
			} else { 
				if(is_string($value)) {
					$value = array($value);
				}
				if(is_array($value)) {
					$this->songs = null;
					$this->songIdentifiers = $value;
					$this->songs_changed = true;
				}
			}
		}

		public function add($value) {
			global $pdo;
			$ids = $this->getSongIDs($value);
			if(count($ids) > 0) {
				foreach($ids as $songID) {
					$statement = $pdo->prepare("INSERT INTO onsong_connect_book_item ( ID, bookID, songID, created, modified ) VALUES ( UUID(), ?, ?, NOW(), NOW() )");
					$statement->execute(array($this->ID(), $songID));
				}
				$this->songs = null;
				return true;
			}
			return false;
		}
		
		// update deleted field with datetime to mark that song has been removed from book
		public function remove($value) {
			global $pdo;
			$ids = $this->getSongIDs($value);
			if(count($ids) > 0) {
// 				$statement = $pdo->prepare("DELETE FROM onsong_connect_book_item WHERE bookID = ? AND songID IN ('". implode("', '", $ids) ."')");
				$statement = $pdo->prepare("UPDATE onsong_connect_book_item SET deleted = NOW() WHERE bookID = ? AND songID IN ('". implode("', '", $ids) ."')");
				$statement->execute(array($this->ID()));
				if($statement->rowCount() > 0) {
					$this->songs = null;
					return true;
				}
			}
			return false;
		}
		
		private function getSongIDs($value, $existing = false) {
			// Create an array of identifier
			$ids = array();
			if(is_array($value)) {
				foreach($value as $item) {
					if(is_string($item)) {
						array_push($ids, $item);
					} else if($item instanceof Song) {
						array_push($ids, $item->ID());
					}
				}
			} else if(is_string($value)) {
				array_push($ids, $value);
			} else if($value instanceof Song) {
				array_push($ids, $value->ID());
			}

			return $ids;
		}

		public function hasSeparateStyles() {
			if($this->useSeparateStyles() == null) {
				return Defaults::get("separateBookStyles", false);
			} else {
				return $this->useSeparateStyles();
			}
		}

		public function updateStyles($song) {
			global $pdo;
			
			// If we don't have a song, then return false
			if(empty($song)) { return false; }
			
			// Create data that will get saved to the relational item
			$data = $song->jsonSerialize(Song::formattingColumns());
			
			// Then set this to the song set item
			$sql = "UPDATE onsong_connect_book_item SET data = ? WHERE bookID = ? AND songID = ?";
			$statement = $pdo->prepare($sql);
			return $statement->execute(array(json_encode($data), $this->ID(), $song->ID()));
		}

		public function applyStyles($song) {
			global $pdo;
			
			// If we don't have a song, then return false
			if(empty($song)) { return false; }
			
			// Then set this to the song set item
			$sql = "SELECT data FROM onsong_connect_book_item WHERE bookID = ? AND songID = ?";
			$statement = $pdo->prepare($sql);
			$statement->execute(array($this->ID(), $song->ID()));
			if($statement) {
				$row = $statement->fetch(PDO::FETCH_NUM);
				$data = $row[0];
				
				// Now populate the song with the data
				$this->populate($data);
			}
		}

		public function save(&$errors = null, $exceptions = null) {
			global $pdo;
			
			if($errors == null) {
				$errors = array();
			}
			if(parent::save($errors)) {

				// If we've made changes to songs,
				if($this->songs_changed && $this->songs != null) {
					
					// Set the processing column so that we can know which ones weren't handled
					$processID = uniqid("", true);
					$statement = $pdo->prepare("UPDATE onsong_connect_book_item SET processID = ? WHERE bookID = ?");
					$statement->execute(array($processID, $this->ID()));

					// Then save those changes
					for($i=0;$i<count($this->songs);$i++) {
						
						// Get the song identifier
						$song = $this->songs[$i];
						$songID = null;
						if(is_object($song)) {
							$songID = $song->ID();
						} else if(is_string($song)) {
							$songID = $song;
						}

						// If we don't have a song ID, then skip along little fella...
						if($songID == null) { continue; }

						// See if the song is already in the list
						$statement = $pdo->prepare("SELECT ID FROM onsong_connect_book_item WHERE bookID = ? AND songID = ? AND processID IS NOT NULL ORDER BY orderIndex");
						$statement->execute(array($this->ID(), $songID));
						if($statement && $row = $statement->fetch(PDO::FETCH_ASSOC)) {
							
							// Then update the order index, but leave everything else alone
							$statement = $pdo->prepare("UPDATE onsong_connect_book_item SET orderIndex = ?, deleted = NULL, modified = NOW(), processID = NULL WHERE ID = ?");
							$statement->execute(array($i, $row['ID']));
						}

						// Otherwise, let's insert it instead
						else {
							$statement = $pdo->prepare("INSERT INTO onsong_connect_book_item ( ID, bookID, songID, orderIndex, created, modified ) VALUES ( UUID(), ?, ?, ?, NOW(), NOW() )");
							$statement->execute(array($this->ID(), $songID, $i));
						}
					}
					
					// Now let's mark all the processed items as deleted because they were not handled
					$statement = $pdo->prepare("UPDATE onsong_connect_book_item SET deleted = NOW() WHERE bookID = ? AND processID = ?");
					$statement->execute(array($this->ID(), $processID));

					// Mark the songs as not being changed
					$this->songs_changed = false;
				}
				return true;
			}
			return false;
		}

		public static function named($name) {
			global $pdo;

			// Make sure that we have the required parameters
			if($name == null || strlen($name) == 0) { return null; }

			// Retrieve the book based on this information
			$sql = "SELECT * FROM onsong_connect_book WHERE accountID = ? AND name = ? AND deleted IS NULL";
			$statement = $pdo->prepare($sql);
			$statement->execute(array(API::currentToken()->role()->accountID(), $name));
			if($statement && $row = $statement->fetch(PDO::FETCH_ASSOC)) {
				return new Book($row);
			} else {
				return new Book(array("name"=>$name, "accountID"=>API::currentToken()->role()->accountID()));
			}
		}

		public function jsonSerialize($include = null) {
			$o = parent::jsonSerialize($include);
			if($this->showSongs()) {
				$o["songs"] = $this->songs();
			}
			return $o;
		}
	}
?>