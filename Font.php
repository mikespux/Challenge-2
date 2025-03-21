<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");
	require_once(__DIR__ . "/Functions.php");

	class Font {
		private $pointSize = 0;
		private $fontName = "Helvetica";
		private $lineHeight = 1.0;
		private $bold = null;
		private $italic = null;
		
		public function __construct($fontName = null, $pointSize = 14, $lineHeight = 1) {
			if(is_null($fontName)) {
				$fontName = "Helvetica";
			}
			$this->fontName = $fontName;
			$this->pointSize = $pointSize;
			$this->lineHeight = $lineHeight;
		}
		
		public function fontName($value = null) {
			if(is_null($value)) {
				return $this->fontName;
			} else {
				$this->bold = null;
				$this->italic = null;
				$this->fontName = $value;
			}
		}
		
		public function familyName() {
			$parts = explode("-", $this->fontName());
			return $parts[0];
		}

		public function pointSize($value = null) {
			if(is_null($value)) {
				return $this->pointSize;
			} else {
				$this->pointSize = $value;
			}
		}
		
		public function lineHeight($value = null) {
			if(is_null($value)) {
				return $this->lineHeight;
			} else {
				$this->lineHeight = $value;
			}
		}
		
		public function css($textColor = null, $backgroundColor = null) {
			$o = sprintf("font-family: \"%s\"; font-size: %fpt; line-height: %fpt; ", $this->familyName(), $this->pointSize(), $this->lineHeight());
			if($this->bold()) {
				$o .= "font-weight: bold; ";
			}
			if($this->italic()) {
				$o .= "font-style: italic; ";
			}
			if(!is_null($textColor)) {
				$o .= "color: ". $textColor->css();
			}
			if(!is_null($backgroundColor)) {
				$o .= "background-color: ". $backgroundColor->css();
			}
			return $o;
		}
		
		public function bold($value = null) {
			if(is_null($value)) {
				if(is_null($this->bold)) {
					$name = strtolower($this->fontName());
					$this->bold = (strpos($name, "bold") !== false || strpos($name, "wide") !== false);
				}
				return $this->bold;
			} else {
				$this->bold = $value;
			}
		}

		public function italic($value = null) {
			if(is_null($value)) {
				if(is_null($this->italic)) {
					$name = strtolower($this->fontName());
					$this->italic = (strpos($name, "italic") !== false || strpos($name, "oblique") !== false);
				}
				return $this->italic;
			} else {
				$this->italic = $value;
			}
		}

		public function normal($value = null) {
			if(is_null($value)) {
				return ($this->bold() == false && $this->italic() == false);
			} else {
				$this->bold = false;
				$this->italic = false;
			}
		}
		
		public function format($bold = null, $italic = null) {
			if(!is_null($bold)) {
				$this->bold = $bold;
			}
			if(!is_null($italic)) {
				$this->italic = $italic;
			}
		}
	}
?>