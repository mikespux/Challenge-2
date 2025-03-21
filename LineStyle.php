<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");
	require_once(__DIR__ . "/Functions.php");
	
	class LineStyle {

		private $css = null;
		private $bold = false;
		private $italic = false;
		private $underlined = false;
		private $textColor = null;
		private $backgroundColor = null;
		
		public function bold($value = null) {
			if(is_null($value)) {
				return $this->bold;
			} else {
				$this->bold = $value;
			}
		}

		public function italic($value = null) {
			if(is_null($value)) {
				return $this->italic;
			} else {
				$this->italic = $value;
			}
		}

		public function underlined($value = null) {
			if(is_null($value)) {
				return $this->underlined;
			} else {
				$this->underlined = $value;
			}
		}

		public function css() {
			if(is_null($this->css)) {
				$s = "";
				if(!is_null($this->textColor())) {
					$s .= "color:". $this->textColor()->css() .";";
				}
				if(!is_null($this->backgroundColor())) {
					$s .= "background-color:". $this->backgroundColor()->css() .";";
				}
				if($this->bold()) {
					$s .= "font-weight:bold;";
				}
				if($this->italic()) {
					$s .= "font-style:italic;";
				}
				if($this->underlined()) {
					$s .= "text-decoration:underline;";
				}
				$this->css = $s;
			}
			return $this->css;
		}
		
		public function textColor() {
			return $this->textColor;
		}

		public function backgroundColor() {
			return $this->backgroundColor;
		}
		
		public function acquire($line) {
		
			// Keep track of the original line
			$original = $line;

			// Clear out the colors
			$this->textColor = null;
			$this->backgroundColor = null;

			// Keep track of an offset to avoid infinite loops
			$offset = 0;
		
			// Keep track of how many passes we make
			$pass = 0;
		
			// Keep looking until we have no more characters
			$chars = "*/_!&>@";
			$tokens = ":*/_!&>@";
			$lc = null;
			while(strlen($line) > $offset && strpos($chars, substr($line, $offset, 1)) !== false) {
				$c = substr($line, $offset, 1);
				$at = charpos($line, $tokens, $offset + 1, strlen($line) - $offset - 1);
		
				// Determine bold
				if($c == "*") {
					if($c == $lc) {
						if($pass == 1) {
							$this->bold = false;
							return $original;
						} else {
							return $line;
						}
					}
					$this->bold = true;
					$line = substr($line, 1);
				}

				// Determine italic
				else if($c == "/") {
					if($c == $lc) {
						if($pass == 1) {
							$this->italic = false;
							return $original;
						} else {
							return $line;
						}
					}
					$this->italic = true;
					$line = substr($line, 1);
				}
				
				// Determine underline
				else if($c == "_") {
					if($c == $lc) {
						if($pass == 1) {
							$this->underlined = false;
							return $original;
						} else {
							return $line;
						}
					}
					$this->underlined = true;
					$line = substr($line, 1);
				}
		
				// Determine emphasis
				else if($c == "!") {
					if($c == $lc) {
						if($pass == 1) {
							$this->bold = $this->italic = false;
							return $original;
						} else {
							return $line;
						}
					}
					$this->bold = $this->italic = true;
					$line = substr($line, 1);
				}
		
				// Determine color
				else if($c == "&") {
					if($c == $lc) {
						if($pass == 1) {
							$this->textColor = null;
							return $original;
						} else {
							return $line;
						}
					}
					if($at !== false) {
						$color = trim(substr(substr($line, $at), 1));
						$this->textColor = Color::named($color);
		
						$i = $at;
						$token = substr($line, $at, 1);
						if($token == ":") {
							$i += 1;
						}
						if(!is_null($this->textColor)) {
							$line = substr($line, $i);
						} else {
							$offset += $i;
						}
					} else {
						$offset++;
					}
				}
		
				// Determine background color
				else if($c == ">") {
					if($c == $lc) {
						if($pass == 1) {
							$this->backgroundColor = null;
							return $original;
						} else {
							return $line;
						}
					}
					if($at !== false) {
						$color = trim(substr(substr($line, $at), 1));
						$this->backgroundColor = Color::named($color);
						$this->backgroundColor->alpha(0.5);
		
						$i = $at;
						$token = substr($line, $at, 1);
						if($token == ":") {
							$i += 1;
						}
						if(!is_null($this->textColor)) {
							$line = substr($line, $i);
						} else {
							$offset += $i;
						}
					} else {
						$offset++;
					}
				}
		
				// Load style name
				else if($c == "@") {
					if($c == $lc) {
						if($pass == 1) {
							$this->reset();
							return $original;
						} else {
							return $line;
						}
					}
					if($at !== false) {
						$styleName = trim(substr(substr($line, $at), 1));

						$token = substr($line, $at, 1);
						if($token == ":") {
							$i += 1;
						}
						if(!is_null($this->textColor)) {
							$line = substr($line, $i);
						} else {
							$offset += $i;
						}
					} else {
						$offset++;
					}
				}
		
				// Keep track of the last character
				$lc = $c;
				$pass++;
			}
		
			// Return the line
			return $line;
		}
		
		public function reset() {
			$this->bold(false);
			$this->italic(false);
			$this->underlined(false);
			$this->textColor = null;
			$this->backgroundColor = null;
			$this->css = null;
		}
	}
?>