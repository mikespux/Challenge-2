<?php
	
	// Determine if the subject string starts with the search string
	function hasPrefix($subject, $search) {
		
		// Return false if we don't have enough information
		if(is_null($subject) || is_null($search)) {
			return false;
		}

		return (substr($subject, 0, strlen($search)) === $search);
	}

	// Determine if the subject string ends with the search string
	function hasSuffix($subject, $search) {
		
		// Return false if we don't have enough information
		if(is_null($subject) || is_null($search)) {
			return false;
		}

		return (substr($subject, 0, -strlen($search)) === $search);
	}

	function toBool($value) {
		if(is_string($value)) {
			$value = strtolower($value);
			return ($value == "yes" || $value == "true");
		} else {
			return boolVal($value);
		}
	}

	function toFloat($value) {
		$value = floatval($value);
		if($value < 0) { $value = 0; }
		if($value > 255) {
			$value = 255;
		}
		if($value > 1) {
			$value = $value/255.0;
		}
		return $value;
	}
	
	function charpos($string, $chars, $offset = 0, $length = null) {
		$found = null;
		$s = substr($string, $offset, $length);
		for($i=0;$i<strlen($chars);$i++) {
			$c = substr($chars, $i, 1);
			$at = strpos($s, $c);
			if($at !== false) {
				if($found == null || $at < $found) {
					$found = $at;
				}
			}
		}
		return $found + $offset;
	}
?>