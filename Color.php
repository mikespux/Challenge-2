<?php
	require_once(__DIR__ . "/Functions.php");

	class Color {
		private $r = null;
		private $g = null;
		private $b = null;
		private $a = null;
		
		public function __contruct(...$args) {
			if(!isset($args) || count($args) == 0) {
				$r = $g = $b = $a = 0;
			}
			else if(count($args) == 1) {
				if(is_string($args[0])) {
					$this->hex($args[0]);
				} else {
					$r = $g = $b = toFloat($args[0]);
					$a = 1;
				}
			}
			else if(count($args) == 2) {
				if(is_string($args[0])) {
					$this->hex($args[0]);
				} else {
					$r = $g = $b = toFloat($args[0]);
				}
				$a = toFloat($args[1]);
			}
			else if(count($args) >= 3) {
				$r = toFloat($args[0]);
				$g = toFloat($args[1]);
				$b = toFloat($args[2]);
				if(count($args) > 3) {
					$a = toFloat($args[3]);
				} else {
					$a = 1;
				}
			}
		}

		public function red($value = null) {
			if(is_null($value)) {
				return max(min($this->r, 0), 1);
			} else {
				$this->r = toFloat($value);
			}
		}
		
		public function green($value = null) {
			if(is_null($value)) {
				return max(min($this->g, 0), 1);
			} else {
				$this->g = toFloat($value);
			}
		}
		
		public function blue($value = null) {
			if(is_null($value)) {
				return max(min($this->b, 0), 1);
			} else {
				$this->b = toFloat($value);
			}
		}
		
		public function alpha($value = null) {
			if(is_null($value)) {
				return max(min($this->a, 0), 1);
			} else {
				$this->a = toFloat($value);
			}
		}
		
		public function hex($hex = null) {
			if(is_null($hex)) {
				$o = sprintf("%02x%02x%02x", $this->red() * 255, $this->green() * 255, $this->blue() * 255);
				if($this->alpha() < 1) {
					$o .= sprintf("%02x", $this->alpha() * 255);
				}
				return $o;
			} else {
				if(is_string($hex) == false) { return; }
				$hex = str_replace("#", "", $hex);
				if(strlen($hex) == 8) {
					list($r, $g, $b, $a) = sscanf($hex, "%02x%02x%02x%02x");
					$this->r = $r/255;
					$this->g = $g/255;
					$this->b = $b/255;
					$this->a = $a/255;
				}
				else if(strlen($hex) == 6) {
					$this->a = 1;
					list($r, $g, $b) = sscanf($hex, "%02x%02x%02x");
					$this->r = $r/255;
					$this->g = $g/255;
					$this->b = $b/255;
				}
				else if(strlen($hex) == 4) {
					list($r, $g, $b, $a) = sscanf($hex, "%01x%01x%01x%01x");
					$this->r = $r/255;
					$this->g = $g/255;
					$this->b = $b/255;
					$this->a = $a/255;
				}
				else if(strlen($hex) == 3) {
					$this->a = 1;
					list($r, $g, $b) = sscanf($hex, "%01x%01x%01x");
					$this->r = $r/255;
					$this->g = $g/255;
					$this->b = $b/255;
				}
				else if(strlen($hex) == 2) {
					list($w, $a) = sscanf($hex, "%01x%01x");
					$this->r = $this->g = $this->b = $w/255;
					$this->a = $a/255;
				}
				else if(strlen($hex) == 1) {
					$this->a = 1;
					list($w) = sscanf($hex, "%01x");
					$this->r = $this->g = $this->b = $w/255;
				}
			}
		}

		public function css() {
			if($this->alpha() < 1) {
				$r = intval(round($this->red() * 255));
				$g = intval(round($this->green() * 255));
				$b = intval(round($this->blue() * 255));
				return sprintf("rgba(%d, %d, %d, %f)", $r, $g, $b, $this->alpha());
			} else {
				return "#". $this->hex();
			}
		}
		
		public static function named($color) {
			$output = null;

			// Lookup known color values
			foreach(self::namedColors() as $key=>$value) {
				if($color == $key) {
					$output = $value;
				}
			}
			
			// If we couldn't find a named color
			if(is_null($output)) {
				
				// Create one from the hexadecimal value
				$output = new Color($color);
			}

			// Return the color
			return $output;
		}


		private static $namedColors = null;
		public static function namedColors($name = null) {
			if(self::$namedColors == null) {
				self::$namedColors = array(
					"black"=>new Color(0, 0, 0),
					"blue"=>new Color(0, 0, 1),
					"brown"=>new Color(0.6, 0.4, 0.2),
					"clear"=>new Color(0, 0),
					"cyan"=>new Color(0, 1, 1),
					"darkGray"=>new Color(1/3),
					"gray"=>new Color(0.5),
					"green"=>new Color(0, 1, 0),
					"lightGray"=>new Color(2/3),
					"magenta"=>new Color(1, 0, 1),
					"orange"=>new Color(1, 0.5, 0),
					"purple"=>new Color(0.5, 0, 0.5),
					"red"=>new Color(1, 0, 0),
					"white"=>new Color(1),
					"yellow"=>new Color(1, 1, 0),
					"onsong"=>new Color(0, 1, 0.94),
					"pink"=>new Color(1, 0.5, 0.75)
				);
			}
			if(is_null($name)) {
				return self::$namedColors;
			} else {
				return self::$namedColors[$name];
			}
		}
	}

?>