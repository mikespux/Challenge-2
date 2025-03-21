<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");
	
	class ChartCatch extends DataObject {
		private $score = null;
		
		public static function tableName() {
			return "onchord_song_content";
		}
		
		public static function className() {
			return "ChartCatch";
		}
		
		public function score($includeChordPro = true) {
			
			// If we already have a score, output
			if(is_null($this->score) == false) { return $this->score; }
			
			// Otherwise, we need to calculate a score by tracking total points
			$total = 0;
			$points = 0;
			
			// Then see if we have an artist
			$total += 2;
			if(!empty(trim($this->artist()))) {
				$points++;
				
				// See if the artist is title case
				if(strtoupper($this->artist()) != $this->artist() && strtolower($this->artist()) != $this->artist()) {
					$points++;
				}
			}
			
			// See if the title is title case
			$total++;
			if(strtoupper($this->title()) != $this->title() && strtolower($this->title()) != $this->title()) {
				$points++;
			}
			
			// Determine if we are using ChordPro
			$isChordPro = (strpos($this->content(), "{") !== false && strpos($this->content(), "}") !== false);
			
			// Determine if we have bracketed something or other
			$isBracketed = (strpos($this->content(), "[") !== false && strpos($this->content(), "]") !== false);
			
			// Add some points for being bracketed chords
			$total += 10;
			if($isBracketed) {
				$points += 10;
			}
			
			// Keep track of the number of labeled sections
			$labeledSections = 0;

			// Get the lines as an array
			$lines = preg_split("/\r\n|\n|\r/", $this->content());
			
			// Add the total for each line
			$total += count($lines);
			
			// If we are using ChordPro
			if($isChordPro == false || $includeChordPro) {
				
				// Keep track if we are in the metadata section
				$isMetadata = true;
				
				// Keep track of section labels
				$wasSectionLabel = false;

				// Valid metadata tags
				$metadataTags = array("t", "title", "st", "subtitle", "a", "artist", "k", "key", "tempo", "time", "duration", "capo", "lyricist", "composer");
	
				// Now process line by line
				for($i=0;$i<count($lines);$i++) {
					$line = $lines[$i];
					
					// If this is a blank line, we aren't in metadata anymore 
					if(empty($line)) {
						$isMetadata = false;
						$points++;
						continue;
					}
					
					// Get the tag information
					$line = trim($line, " \n\r\t\v\x00{}");
					$parts = explode(":", $line);
					$tagName = null;
					$tagValue = $line;
					if(count($parts) >= 2) {
						$tagName = trim($parts[0]);
						$tagValue = trim($parts[1]);
					}

					// If this is metadata
					if($isMetadata) {
						
						// If this is the first line
						if($i == 0) {

							// If this is ChordPro
							if($isChordPro) {
								
								// Then the tag needs to be title
								if($tagName == "t" || $tagName == "title") {
									$points++;
								}
							} else {
								if(empty($tagName)) {
									$points++;
								}
							}
						}
						
						// If this is the second line
						else if($i == 1) {
						
							// If this is ChordPro
							if($isChordPro) {
								
								// Then the tag needs to be title
								if($tagName == "a" || $tagName == "artist" || $tagName == "st"  || $tagName == "subtitle") {
									$points++;
								}
							} else {
								if(empty($tagName)) {
									$total += 1;
									if(preg_match('~[0-9]+~', $tagValue) == false) {
										$points++;
									}
								}
							}
						}
						
						// Otherwise, this should be a supported tag
						else {
							if(in_array($tagName, $metadataTags)) {
								$points++;
							}
						}
					}

					// Otherwise
					else {
						
						// Determine if this is a section label
						if($wasSectionLabel == false) {

							// See if there's a section label
							if($isChordPro) {
								if(($tagName == "c" || $tagName == "comment") && !empty($tagValue)) {
									$wasSectionLabel = true;
									$points++;
									$labeledSections++;
									continue;
								}
							} else {
								$wasSectionLabel = (endsWith($line, ":"));
								if($wasSectionLabel) {
									$points++;
									$labeledSections++;
									continue;
								}
							}
						}
						
						// Now if we had a section label, make sure we have content
						if($wasSectionLabel && !empty($line) && empty($tagName)) {
							$points++;
						}
						$wasSectionLabel = false;
						
						// Now, lets see if we have square brackets in the line
						$chords = array();
						$pattern = "/\[(.*?)\]/";
						preg_match_all($pattern, $line, $chords, PREG_SET_ORDER);
						
						// If we have bracketed chords
						if(count($chords) > 0) {

							// Go through and,
							for($c=0;$c<count($chords);$c++) {
								
								// Make sure that these are all valid chords
								if(Chord::isChord($chords[$c][1])) {
									$points++;
								}
								
								// Add to the total
								$total++;
							}
						}
						
						// Otherwise, see if it's a chord line
						else if(Chord::isChordLine($line)) {
							$points++;
						}
						
						// Otherwise
						else {
							
							// If everything is well-trimmed
							if($line == trim($line)) {
								$points++;
							}
						}
					}
				}
			}
			
			// If we have labeled sections, let's track that
			$total += 20;
			$points += $labeledSections;

			// Calculate the score
			$this->score = $points / $total;
			if($this->score > 1) { $this->score = 1; }

			// Return the score
			return $this->score;
		}
		
		public static function find($title, $artist = null, $key = null) {
			global $pdo;

			$results = array();
			$params = array($title);
			$sql = "SELECT * FROM onchord_song_content WHERE title LIKE ? ";
			if(!empty($artist)) {
				$sql .= " AND artist LIKE ?";
				array_push($params, $artist);
			}
			if(!empty($key)) {
				$sql .= " AND key = ?";
				array_push($params, $key);
			}
			$statement = $pdo->prepare($sql);
			$statement->execute($params);
			if($statement) {
				while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
					array_push($results, new ChartCatch($row));
				}
			}
			
			// Now sort descending by the score
			usort($results, function($a, $b) { return $a->score() - $b->score();});
			
			// Return the results
			return $results;
		}
		
		public function jsonSerialize($include = null) {
			$o = parent::jsonSerialize($include);
			$o['score'] = $this->score();
			unset($o['content']);
			return $o;
		}
	}
?>