<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");
	require_once(__DIR__ . "/Functions.php");

	// Defines a chord and provides chord-related utilities
	class Chord {
		const STYLE_ALPHA = 0;
		const STYLE_NUMERIC = 1;
		const STYLE_ROMAN = 2;
		const STYLE_ITALIAN = 3;
		
		const PROCESSING_NONE = 0;
		const PROCESSING_WINDOW_COMPATIBLE = 1;
		const PROCESSING_INCLUDE_TAB_CHORDS = 2;
		
		const CHORD_DETECTION_REGEX = "/(?:\\b(?:([A-H]|NC)(?:#|b|♯|♭)?(?:add|sus|m|min|maj|aug|dim)?(?:0|2|4|5|6|7|9|11|13)?)(?:\\/|)?((?:#|b|♯|♭)?(?:add|sus|m|min|maj|aug|dim)?(?:0|2|4|5|6|7|9|11|13)?)(?:\\/[A-H](?:#|b|♯|♭)?)?\\b)/";

		public static function transpose($chord, $halfSteps = 0, $signature = null, &$transpose = null) {
			$transpose = 0;

			$step = Key::keys("notes", $chord, "step");

			// If we don't have a step found, then it's not a fetchable chord and we should just return it.
			if(is_null($step)) { return $chord; }

			// Calculate the new step
			$newStep = intval($step) + $halfSteps;
			if($newStep > 11) {
				$newStep -= 12;
				$transpose = 1;
			}

			// Adjust for octave shift
			if($newStep < 0) {
				$newStep += 12;
				$transpose = -1;
			}

			// Return the new note
			$newChord = null;
			if(!is_null($signature)) {
				$newChord = Key::keys($signature, (string)$newStep);
			} else {
				$m = strpos($chord, "m");
				$isMinor = ($m == 1 || $m == 2);
				$flatChord = Key::keys("flat", (string)newStep);
				if($isMinor) {
					$flatChord .= "m";
				}
				$sharpChord = Key::keys("sharp", (string)newStep);
				if($isMinor) {
					$sharpChord .= "m";
				}

				// See which one has the fewest sharps/flats
				$flatKey = Key::keys("keys", $flatChord);
				$sharpKey = Key::keys("keys", $sharpChord);

				// Determine which one to use
				$flats = isset($flatKey["count"]) ? intval($flatKey["count"]) : PHP_INT_MAX;
				$sharps = isset($sharpKey["count"]) ? intval($sharpKey["count"]) : -1;

				// Figure it out
				if($flats < $sharps) {
					$newChord = $flatChord;
				} else {
					$newChord = $sharpChord;
				}
			}
			if(hasSuffix($chord, "m") && hasSuffix($newChord, "m") == false) {
				$newChord .= "m";
			}
			return $newChord;
		}

		public static function localization($value = null) {
			if(is_null($value)) {
				return Defaults::get("chordLocalization", "");
			} else {
				if(in_array($value, array("", "de", "cs", "sc"))) {
					Defaults::set("chordLocalization", $value);
				}
			}
		}

		public static function localize($chord, $chordStyle = 0) {
			$chordLocalization = self::localization();

			// Handle localizing the chord for a different country preference
			if(empty($chordLocalization) == false) {
				if($chordLocalization == "de") {
					$chord = self::germanize($chord, true);
				} else if($chordLocalization == "cs") {
					$chord = self::czechify($chord, true);
				} else if($chordLocalization == "sc") {
					$chord = self::scandinavianize($chord, true);
				}
			}

			// Lowercase the minor chords if required
			if(Defaults::get("chordLowercaseMinor", false)) {
				if($chordStyle == static::STYLE_ITALIAN && strlen($chord) >= 2) {
					$chord = strtoupper(substr($chord, 0, 2)) . substr($chord, 2);
				}
				$chord = self::lowercaseMinor($chord);
			}
			
			// Naturalize the chord if needed
			if(Defaults::get("useNaturalizedNotes", true)) {
				$chord = Chord::naturalize($chord);
			}

			// Return the finished chord
			return $chord;
		}
		
		public static function unlocalize($chord) {
			$chordLocalization = self::localization();
			if(empty($chordLocalization)) {
				return $chord;
			} else if($chordLocalization == "de") {
				return self::ungermanize($chord, true);
			} else if($chordLocalization == "cs") {
				return self::unczechify($chord, true);
			} else if($chordLocalization == "sc") {
				return self::unscandinavianize($chord, true);
			}
			return $chord;
		}

		public static function germanize($chord, $flag = null) {
			if(is_null($flag)) {
				$flag = (self::localization() == "de");
			}
			if($flag) {
				$chord = str_replace("Hb", "B", str_replace("B", "H", $chord));
			}
			return $chord;
		}
		
		public static function ungermanize($chord, $flag = null) {
			if(is_null($flag)) {
				$flag = (self::localization() == "de");
			}
			if($flag) {
				$chord = str_replace("Bbb", "Bb", str_replace("H", "B", str_replace("B", "Bb", $chord)));
			}
			return $chord;
		}
		
		public static function lowercaseMinor($chord) {
			
			// Handle the alpha-numeric types
			$m = strpos($chord, "m");
			if($m === false) {
				$m = strpos($chord, "-");
			}

			$maj = (strpos($chord, "maj") !== false);
			$dim = (strpos($chord, "dim") !== false);
			if($m !== false && !$maj && !$dim) {
				$chord = strtolower(substr($chord, 0, $m)) . substr($chord, $m);
			}
			return $chord;
		}

		public static function unlowercaseMinor($chord) {
			if(strlen($chord) < 1) { return $chord; }
			
			// See if the first note is lowercased
			if(strtolower(substr($chord, 0, 1)) == substr($chord, 0, 1)) {
				$first = substr($chord, 0, 2);
				if(hasSuffix($first, "b") == false || asSuffix($first, "#") == false) {
					$first = substr($first, 0, 1);
				}
				$chord = $first . "m" . substr($chord, strlen($first));
			}
			return $chord;
		}
		
		public static function czechify($chord, $flag = null) {
			if(is_null($flag)) {
				$falg = (self::localization() == "cs");
			}
			if($flag) {
				$chord = str_replace("Hb", "B", str_replace("B", "H", $chord));

				$m = (strpos($chord, "m") !== false);
				$maj = (strpos($chord, "maj") !== false);
				$mi = (strpos($chord, "mi") !== false);
				if($m && !$maj && !$mi) {
					$chord = str_replace("m", "mi", $chord);
				}
			}
			return $chord;
		}

		public static function unczechify($chord, $flag) {
			if(is_null($flag)) {
				$flag = (self::localization() == "cs");
			}
			if($flag) {
				$chord = str_replace("mi", "m", str_replace("H", "B", str_replace("B", "Bb", $chord)));
			}
			return $chord;
		}
		
		public static function scandinavianize($chord, $flag = null) {
			if(is_null($flag)) {
				$flag = (self::localization() == "sc");
			}
			if($flag) {
				$chord = str_replace("Hb", "Bb", str_replace("B", "H", $chord));
			}
			return $chord;
		}

		public static function unscandinavianize($chord, $flag = null) {
			if(is_null($flag)) {
				$flag = (self::localization() == "sc");
			}
			if($flag) {
				$chord = str_replace("H", "B", $chord);
			}
			return $chord;
		}

		public static function naturalize($input) {

			// Remove double sharps or double flats
			$dir = 0;
			if(strpos($input, "##") !== false) {
				$dir = 1;
			} else {
				if(strpos($input, "bb") !== false) {
					$dir = -1;
				}
			}
			if($dir != 0) {
				$c = ord(substr($input, 0, 1));
				if($c >= 65 && $c <= 71) { // A to G
					$c += $dir;
					if($c < 65) { $c += 7; }
					if($c > 71) { $c -= 7; }
					$input = chr($c) . substr($input, 1);
				}
			}

			// Naturalized strange notes
			foreach(Key::keys("naturals") as $note=>$value) {
				if(hasPrefix($input, $note)) {
					$input = str_replace($note, Key::keys("naturals", $note), $input);
				}
			}
			
			return $input;
		}

		public static function bracketChordsForPlainText($content, $options = null) {
			if(is_null($options)) {
				$options = static::PROCESSING_NONE;
				if(Defaults::get("monospacedBracketChords", true)) {
					$options |= static::PROCESSING_INCLUDE_TAB_CHORDS;
				}
			}

		    // If we have square brackets, just return it
		    if(Defaults::get("translateChordsLenient", false) == false && strpos($content, "[") !== false) {
		        return $content;
		    }

			// Determine if we ought to bracket chords in the tab
			$bracketTabChords = (($options & static::PROCESSING_INCLUDE_TAB_CHORDS) == static::PROCESSING_INCLUDE_TAB_CHORDS);

		    // Otherwise, look for lines with spaces
		    $o = "";
		    $a = explode("\n", $content);
		    array_push($a, "");
			$isTab = false;
			$isMetadata = false;
		    for($i=0;$i<count($a);$i++) {
			    $line = $a[$i];

				// If it's a blank line, just skip it
				if(strlen(trim($line)) == 0) {
					$o .= "\n";
					$isMetadata = false;
					continue;
				}

				// Determine if we have changed the tab status
				if(!$bracketTabChords) {
					if(strpos($line, "{start_of_tab}") !== false || strpos($line, "{sot}") !== false) {
						$isTab = true;
					} else if(strpos($line, "{end_of_tab}") !== false || strpos($line, "{eot}") !== false) {
						$isTab = false;
					}
				}

				// If this line should be bracketed, then do it
				if($isMetadata == false && self::isChordLine($line) && ($i < (count($a) - 1)) && $isTab == false) {
					$i++;
					$next = $a[$i];
					if(self::isChordLine($next) || strlen(self::trim($next)) == 0 || strpos($line, "|") !== false) {
						$i--;
						$o .= self::trim(self::mergeChords($line)) . "\n";
					} else {
						$o .= self::trim(self::mergeChords($line, $next)) . "\n";
					}
				}
		
				// Otherwise, append the string
				else {
					$o .= $line . "\n";
				}
		    }
			if(($options & static::PROCESSING_WINDOW_COMPATIBLE) == static::PROCESSING_WINDOW_COMPATIBLE) {
				return self::replaceNewlineCharacters(self::trim($o));
			} else {
				return self::trim($o);
			}
		}

		public static function replaceNewlineCharacters($input) {
			return str_replace("\n", "\r\n", $input);
		}

		public static function trim($input) {
			$output = trim($input);
			if(hasSuffix($output, ":") || Defaults::get("songTrimEnabled", true) == false) {
				return $input;
			} else {
				return $output;
			}
		}
		
		public static function isChord($value) {
			return (preg_match(static::CHORD_DETECTION_REGEX, $value) == 1);
		}

		public static function isChordLine($line) {

			// If it's a section label, it's NOT a chord line
			if(hasSuffix($line, ":") || strpos($line, "{") !== false) { return false; }

			// If it starts with a period or a funny apostrophe, it's IS a chord line.
			if((hasPrefix($line, ".") && hasPrefix($line, "..") == false) || hasPrefix($line, "`")) { return true; }

			// Ignore periods
			$line = str_replace(".", " ", $line);

			// Remove barlines and slashes
			if(strpos($line, "|") !== false) {
				$line = str_replace("/", " ", str_replace("\\", " ", str_replace("|", " ", $line)));
			}

			// If there are too many hyphens, replace them with something else
			if(strpos($line, "----") !== false) {
				$line = str_replace("----", "zzzz", $line);
			}
		
			// Ignore anything in a parenthesis
			$line = preg_replace("/\(([^()]*+|(?R))*\)/", "", $line);

			// Count matches versus words
			$matches = preg_match_all(static::CHORD_DETECTION_REGEX, $line);
			$words = preg_match_all("/\\b\\S+\\b/", $line);
			return ($matches > 0 && $matches >= $words);
		}

		public static function mergeChords($chords, $lyrics = null) {
			$isInline = is_null($lyrics);
			if(is_null($lyrics)) { $lyrics = ""; }

			// If the lyrics are an eot tag, then replace with nothing
			$append = null;
			foreach(array("{end_of_tab}", "{eot}") as $tag) {
				if(strpos($lyrics, $tag) !== false) {
					$lyrics = str_replace($tag, "", $lyrics);
					$append = $tag;
				}
			}

			// Strip off and formatting characters from the beginning and save for later
			$stripped = (new LineStyle())->acquire($lyrics);
			$formatting = substr($lyrics, 0, strlen($lyrics) - strlen($stripped));
			$lyrics = $stripped;

			// Trim the chords by the formatting
			$clip = 0;
			for($i=0;$i<strlen($formatting);$i++) {
				if(substr($chords, $i, 1) != ' ') {
					break;
				}
				$clip++;
			}
			if($clip > 0) {
				$chords = substr($chords, $clip);
			}

			// Replace tabs with spaces
			$chords = str_replace(".", " ", $chords);
			$chords = str_replace("`", " ", $chords);
			$chords = str_replace("\t", self::tabSpaces(), $chords);
			
			// Replace the ideograph space with a real one
			$chords = str_replace("\u3000", " ", $chords);

			// Create a new lyrics to add padding
			$paddedLyrics = $lyrics;
			if(strlen($paddedLyrics) < strlen($chords)) {
				for($i=strlen($paddedLyrics);$i<=strlen($chords);$i++) {
					$paddedLyrics .= " ";
				}
			}

			// Prepare variables
			$position = 0;
			$o = "";
			$chord = "";
			$positions = array();

			// Figure out the positions
			for($i=0;$i<strlen($chords);$i++) {
				$c = substr($chords, $i, 1);
				if($c == " ") {
					if(strlen($chord)) {
						array_push($positions, array("position"=>$position, "chord"=>$chord));
						$position += strlen($chord);
						$chord = "";
					}
					$position++;
				} else {
					$chord .= $c;
				}
			}
			
			// Add the last position
			if(strlen($chord)) {
				array_push($positions, array("position"=>$position, "chord"=>$chord));
			}

			// Go through each position and merge
			$lastPosition = 0;
			foreach($positions as $item) {
				$position = intval($item["position"]);
		        $chord = $item["chord"];
		        $o .= substr($paddedLyrics, $lastPosition, ($position - $lastPosition));
				$o .= "[". $chord ."]";
		        $lastPosition = $position;
		        if($isInline) {
		            $lastPosition += strlen($chord);
		        }
			}

			// Add the last part
			$o .= substr($paddedLyrics, $lastPosition);
			
			// Add the formatting to the front
			$o = $formatting . $o;
			
			// Append the last bit
			if(!is_null($append)) {
				$o .= "\n" . $append;
			}

			// Return the output
			return $o;
		}

		private static $tabSpaces = null;
		private static $numberOfSpaces = 8;
		public static function tabSpaces() {
			if(is_null(self::$tabSpaces) || Defaults::get("tabSpaces", 8) != self::$numberOfSpaces) {
				self::$numberOfSpaces = Defaults::get("tabSpaces", 0);
				if(self::$numberOfSpaces <= 0) {
					self::$numberOfSpaces = 8;
				}
				self::$tabSpaces = str_repeat(" ", self::$numberOfSpaces);
			}
			return self::$tabSpaces;
		}
	}
?>