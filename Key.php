<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");
	require_once(__DIR__ . "/Functions.php");
	require_once(__DIR__ . "/CFPropertyList/CFPropertyList.php");

	class Key {
		private $isValid = false;
		private $name = null;
		private $record = null;
		private $lookup = null;
		
		public function __construct($input = null) {
			self::init($input);
		}
		
		private function init($input = null) {
			$this->isValid = false;
			if(!is_null($input)) {
				$this->record = static::keys("keys", $input);
			}
			if(is_null($this->record)) {
				$input = static::keyNameWithChord($input);
				$this->record = static::keys($input);
			}
			if(!is_null($this->record)) {
				$this->name = $input;
				$this->isValid = true;
			}
		}

		private static $keyLookup = array();
		public static function named($name) {
			$name = Key::keyNameWithChord($name);
			if(!is_null($name))  {
				if(isset(self::$keyLookup[$name])) {
					$key = self::$keyLookup[$name];
				} else {
					$key = new Key($name);
					if($key->isValid()) {
						self::$keyLookup[$name] = $key;
					} else {
						$key = null;
					}
				}
				return $key;
			}
			return null;
		}
		
		public function name() {
			return $this->name;
		}
		
		public function isValid() {
			return $this->isValid;
		}

		public function enharmonicPreference() {
			return $this->record["signature"];
		}
		
		public function count() {
			return intval($this->record["count"]);
		}
		
		public function notes($index = null) {
			if(is_null($index)) {
				return $this->record["notes"];
			} else {
				return $this->record["notes"][$index];
			}
		}
		
		public function minor($flag = null) {

			// Get the minor state from the key name
			if(is_null($flag)) {
				return hasSuffix($this->name, "m");
			}

			// Otherwise, set the minor
			else if(is_bool($flag)) {
				if($flag == $this->minor()) {
					return;
				}
				if($flag) {
					$k = $this->transpose(-3, "sharp");
					self::init($k->name());
				} else {
					$k = $this->transpose(3, "flat");
					self::init(str_replace("m", "", $k->name()));
				}
			}
		}
		
		public function transpose($amount = 0, $enharmonic = null) {
			return new Key(Chord::transpose($this->name(), $amount, $enharmonic));
//			return [[[OSKey alloc] initWithName:[Song transposeChord:self.name halfSteps:amount signature:enharmonic]] autorelease];
		}

		public function transposeChord($chord, $targetKey, $unlocalize = false) {
			$transposed = "";

			// Be sure to first unlocalize the chord before transposing because of those damn Germans
			if($unlocalize) {
				$chord = Chord::unlocalize($chord);
			}
			
			// Store if it's optional and strip parenthesis
			$optional = false;
			if(hasPrefix($chord, "(") && hasSuffix($chord, ")")) {
				$optional = true;
				$chord = substr($chord, 1, strlen($chord) - 2);
			}

			// Then split into parts to transpose
			$parts = explode("/", $chord);
			for($i=0;$i<count($parts);$i++) {
				$part = $parts[$i];
				if($i > 0) {
					$transposed .= "/";
				}
				$chord = self::transposeNote($part, $targetKey);
				if(is_null($chord) == false) {
					$transposed .= $chord;
				} else {
					$transposed .= $part;
				}
			}
			if(strlen($transposed) > 0) {
				if($optional) {
					$transposed = "(" . $transposed . ")";
				}
				return $transposed;
			}
			return null;
		}
		
		public function transposeNote($chord, $targetKey) {
			$chord = trim($chord);

			// Cancel if we have nothing
			if(strlen($chord) < 1) { return null; }

			// Store if it's optional and strip parenthesis
			$optional = false;
			if(hasPrefix($chord, "(") && hasSuffix($chord, ")")) {
				$optional = true;
				$chord = substr($chord, 1, strlen($chord) - 2);
			}

			// Strip off the chord formation and keep track of the dividing line
			$to = 1;
			if(strlen($chord) > 1) {
				$m = substr($chord, 1, 1);
				if ($m == "#" || $m == "b" || $m == "♯" || $m == "♭") {
					$to = 2;
				}
			}
			$note = $chord;
			if(strlen($chord) >= $to) {
				$note = substr($chord, 0, $to);
			}

			// Look up the note position in the current key
			$position = self::positionOfChord($note);
			
			// Now look up the note in the new key
			$transposed = $targetKey->noteAtPosition($position);

			// If we were able to transpose
			if(is_null($transposed) == false) {

				// If we have no sharps or flats or if we ought to force the enharmonic preference
				if($targetKey->count() == 0 || Defaults::get("enharmonicPreferenceForce", false)) {

					// Store the enharmonic preference
					$ep = Defaults::get("enharmonicPreference", "");
					if(strlen($ep) > 0 && strlen($transposed) > 1) {

						// Get note information from the tables
						$noteInfo = static::keys("notes", $transposed);

						// If we could find information
						if($noteInfo) {
							
							// Get the number of steps
							$step = (string)$noteInfo["step"];
							if($step) {

								// Now look up the value as an operation of enharmonic preferences
								$transposed = static::keys($ep, $step);
							}
						}
					}
				}

				// Remove any of weird keys for flats
				if(Defaults::get("useNaturalizedNotes", true)) {
					$transposed = Chord::naturalize($transposed);
				}

				$o = $transposed;
				if(strlen($chord) >= $to) {
					$o .= substr($chord, $to);
				}
				if($optional) {
					$o = "(" . $o . ")";
				}

				// Add the formation back on and return
				return $o;
			}
			
			// Otherwise, return nil so we can handle this another way
			return nil;
		}
		
		public function positionOfChord($chord) {

			// Strip off everything that isn't the note letter, enharmonic preference and minor designation
			$note = static::keyNameWithChord($chord);
			return self::positionOfNote($note);
		}

		public function positionOfNote($note) {
			if(!is_null(self::lookup($note))) {
				return intval(self::lookup($note));
			} else {

				// Try flipping the enharmonic preferences
				$flippedNote = self::flipEnharmonicPreference($note);
				if($note != $flippedNote) {
					if(!is_null(self::lookup($flippedNote))) {
						return intval(self::lookup($flippedNote));
					}
				}

				$doubledNote = self::doubleEnharmonicPreference($note);
				if($note != $doubledNote) {
					if(!is_null(self::lookup($doubledNote))) {
						return intval(self::lookup($doubledNote));
					}
				}
			}
			return -1;
		}

		public function flipEnharmonicPreference($note) {
			if(strlen($note) == 2) {
				$epc = substr($note, 1);
				$ep = null;
				if($epc == "b") { $ep = "sharp"; }
				if($epc == "#") { $ep = "flat"; }
				if($ep) {
					$noteInfo = static::keys("notes", $note);
					if(!is_null($noteInfo)) {
						$step = (string)$noteInfo["step"];
						$note = static::keys($ep, $step);
					}
				}
			}
			return $note;
		}

		public function doubleEnharmonicPreference($note) {

			// If this isn't a natural note then we can't process it
			if(strlen($note) != 1) {
				return $note;
			}

			// Find the character and reject if not valid
			$c = ord(substr(strtoupper($note), 0, 1));
			if($c < 65 || c > 71) { return $note; }

			// If it's sharp, then double sharp
			if($this->enharmonicPreference() == "sharp") {

				// Step down
				$nc = ($c - 1);
				if($nc < 65) { $nc += 7; }

				// Find the new note and add double sharp
				$o = char($nc);
				if($nc != 66 && $nc != 69) { // B or E
					$o .= "#";
				}
				$o .= "#";
				return $o;
			}
			
			// If it's flat, then double flat
			else if($this->enharmonicPreference() == "flat") {

				// Step up
				$nc = ($c + 1);
				if($nc > 71) { $nc -= 7; }
		
				// Find the new note and add double flat
				$o = char($nc);
				if($nc != 67 && $nc != 70) { // C or F
					$o .= "b";
				}
				$o .= "b";
				return $o;
			}
			
			// Otherwise, just return
			else {
				return note;
			}
		}

		public function noteAtPosition($position) {
			if($position < 0) { return null; }
			while($position > 11) {
				$position -= 12;
			}
			while($position < 0) {
				$position += 12;
			}
			return $this->notes()[$position];
		}

		public function lookup($note = null) {
			if(is_null($this->lookup)) {
				$this->lookup = array();
				for($i=0;$i<count($this->notes());$i++) {
					$this->lookup[$this->notes()[$i]] = $i;
				}
			}
			if(is_null($note)) {
				return $this->lookup;
			} else {
				return $this->lookup[$note];
			}
		}
		
		public function __toString() {
			return "Key of ". $this->name();
		}

		public static function keyNameWithChord($chord) {
			if(strlen($chord) < 2) { return $chord; }
			$note = substr($chord, 0, 1);

			$signature = "";
			$isMinor = false;
			if(strlen($chord) > 1) {
				$s = substr($chord, 1, 1);
				if($s == "b" || $s == "#" || $s == "♭" || $s == "♯") {
					$signature = $s;
					if(strlen($chord) > 2) {
						$isMinor = (substr($chord, 2, 1) == "m" && strpos($chord, "maj") === false);
					}
				} else {
					$isMinor = ($s == "m" && strpos($chord, "maj") === false);
				}
			}
			if(strpos("ABCDEFG", $note) !== false) {
				return $note . $signature . ($isMinor) ? "m" : "";
			} else {
				return null;
			}
		}

		public static function rootNameWithChord($chord) {
			$r = self::keyNameWithChord($chord);
			if(hasSuffix($r, "m")) {
				$r = substr($r, 0, strlen($r) - 1);
			}
			return $r;
		}

		private static $keys = null;
		public static function keys(...$args) {
			if(self::$keys == null) {
				$plist = new \CFPropertyList\CFPropertyList(__DIR__ . '/../lists/Keys.plist', \CFPropertyList\CFPropertyList::FORMAT_XML);
				self::$keys = $plist->toArray();
			}
			if(isset($args) && count($args) > 0) {
				$o = self::$keys;
				foreach($args as $key) {
					$o = $o[$key];
				}
				return $o;
			} else {
				return self::$keys;
			}
		}
	}
?>