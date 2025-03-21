<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class FeaturedArtist extends DataObject {
		private $links = null;
		private $albums = null;
		private $songs = null;

		public static function tableName() {
			return "onsong_featured_artist";
		}

		public static function className() {
			return "FeaturedArtist";
		}
		
		public static function featured($limit = null) {
			global $pdo;
			$sql = "SELECT * FROM onsong_featured_artist WHERE testimony IS NOT NULL ORDER BY created DESC ";
			if(is_integer($limit)) {
				$sql .= " LIMIT ". $limit;
			}

			// Execute the statement and return the list
			$list = array();
			$statement = $pdo->prepare($sql);
			$statement->execute();
			if($statement) {
				while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
					$a = new FeaturedArtist($row);
					array_push($list, $a);
				}
			}
			return $list;
		}
		
		public function iTunesURL() {
			$term = $this->alias();
			if(empty($term)) {
				$term = $this->name();
			}
			$result = json_call("https://itunes.apple.com/search?term=". urlencode($term) ."&media=music&entity=allArtist&limit=200");
			if(count($result->results) > 0) {
				return $result->results[0]->artistLinkUrl . '&at=10l4Hw&mt=1&app=music';
			}
			return null;
		}

		public function links() {
			global $pdo;

			// If we don't have any links yet
			if($this->links == null) {

				// Create the empty array
				$this->links = array();

				// Create the SQL statement
				$sql = "SELECT l.*, COALESCE(l.name, t.name) AS name FROM onsong_featured_artist_link l INNER JOIN onsong_featured_artist_link_type t ON l.typeID = t.ID WHERE l.artistID = ? ORDER BY t.orderIndex ";

				// Execute the statement and return the list
				$statement = $pdo->prepare($sql);
				$statement->execute(array($this->ID()));
				if($statement) {
					while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
						$link = new FeaturedArtistLink($row);
						if($link->typeID() == "apple-music") {
							$amg = true;
						}
						array_push($this->links, $link);
					}
				}
				
				// If we didn't have an Apple Music link, then add one
				if(count($this->links) == 0) {
					$url = $this->iTunesURL();
					if(!empty($url)) {
						$link = new FeaturedArtistLink();
						$link->typeID("apple-music");
						$link->name("Apple Music");
						$link->url($url);
						array_unshift($this->links, $link);
					}
				}
			}

			// Return the codes
			return $this->links;
		}
		
		public function albums() {
			global $pdo;

			// If we don't have any albums yet
			if($this->albums == null) {

				// Create the empty array
				$this->albums = array();

				// Create the SQL statement
				$sql = "SELECT * from onsong_featured_album WHERE artistID = ? ORDER BY created DESC ";

				// Execute the statement and return the list
				$statement = $pdo->prepare($sql);
				$statement->execute(array($this->ID()));
				if($statement) {
					while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
						$album = new FeaturedAlbum($row);
						array_push($this->albums, $album);
					}
				}
			}

			// Return the codes
			return $this->albums;
		}

		public function songs() {
			if($this->songs == null) {
				$this->songs = array();
				foreach($this->albums() as $album) {
					foreach($album->songs() as $song) {
						array_push($this->songs, $song);
					}
				}
			}
			return $this->songs;
		}
		
		public function song($songID) {
			foreach($this->songs() as $song) {
				if($song->ID() == $songID) {
					return $song;
				}
			}
			return null;
		}
	}
?>