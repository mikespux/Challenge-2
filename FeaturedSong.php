<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class FeaturedSong {
		private $path = null;
		private $metadata = null;
		private $album = null;
		private $artist = null;
		
		public function __construct($filepath) {
			$this->path = $filepath;
		}
		
		public function title() {
			return $this->metadata("title");
		}

		public function number() {
			$v = $this->metadata("number");
			if(is_numeric($v)) {
				return floatval($v);
			} else {
				return $v;
			}
		}

		public function ID() {
			$info = pathinfo($this->path());
			return $info['filename'];
		}

		public function path() {
			return $this->path;
		}

		public function contents() {
			$p = $this->path;
			if(strpos($p, ".") === false) {
				$p = $p . '.txt';
			}
			if(file_exists($p)) {
				return fopen($p, 'r');
			}
			return null;
		}

		public function iTunesURL() {
			$term = $this->artist()->alias();
			if(empty($term)) {
				$term = $this->artist()->name();
			}
			$url = "https://itunes.apple.com/search?term=". urlencode($term) ."&media=music&entity=song&limit=200";
			$result = json_call($url);
			foreach($result->results as $item) {
				if($item->artistName == $term && stripos($item->collectionName, $this->album()->name()) === 0 && stripos($item->trackName, $this->title()) === 0) {
					return $item->trackViewUrl . '&at=10l4Hw&mt=1&app=music';
				}
			}
			return null;
		}

		public function album() {
			if(empty($this->album)) {
				$this->album = FeaturedAlbum::retrieve($this->albumID());
			}
			return $this->album;
		}
		
		public function albumID() {
			$components = explode("/", $this->path);
			return $components[count($components)-2];
		}

		public function artist() {
			if(empty($this->artist)) {
				$this->artist = FeaturedArtist::retrieve($this->artistID());
			}
			return $this->artist;
		}
		
		public function artistID() {
			$components = explode("/", $this->path);
			return $components[count($components)-3];
		}

		public function metadata($name = null) {

			// If we have a name
			if(!empty($name)) {

				// Then go and return from the metadata collection
				if(isset($this->metadata()[$name])) {
					return $this->metadata()[$name];
				} else {
					return null;
				}
			}

			// Otherwise, create the metadata if required
			else {

				if(empty($this->metadata)) {

					// Load the contents to process
					$this->metadata = array();

					// Now process one line at a time
					$fh = $this->contents();
					
					// If we have no handler, then bail with no metadata
					if($fh == null) { return null; }
					
					// Loop through each line
					$ln = 0;
					while($fh && !feof($fh))  {

						// Get the line
						$line = fgets($fh);

						// If the line is blank, then break
						if(strlen(trim($line)) == 0) {
							break;
						}

						// Keep track of the tag
						$tag = null;
						$value = null;

						// See if we have a colon
						$parts = explode(":", $line);

						// If we have a colon, then split
						if(count($parts) >= 2) {
							$tag = trim(strtolower(array_shift($parts)));
							$value = trim(implode(":", $parts));
						}

						// Otherwise, use the line number if we have one
						else {

							// First position is title
							if($ln == 0) {
								$tag = "title";
								$value = trim($line);
							}
							
							// The second is the artist
							else if($ln == 1) {
								$tag = "artist";
								$value = trim($line);
							}

							// Otherwise, we are done processing
							else {
								break;
							}
						}

						// If we have a tag, then add the value to the array
						if(!empty($tag)) {
							$this->metadata[$tag] = $value;
						}

						// Increment the line number
						$ln++;
					}
				}
				
				// Return the metadata
				return $this->metadata;
			}
		}
		
		public static function songs($path) {
			$songs = array();
			foreach(scandir($path) as $item) {
				$info = pathinfo($item);
				if(isset($info['extension']) && strtolower($info['extension']) == "txt") {
					array_push($songs, new FeaturedSong($path . $item));
				}
			}
			usort($songs, function($a, $b) {
				return ($a->number() - $b->number());
			});
			return $songs;
		}
	}
?>