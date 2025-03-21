<?php

	// Require the framework
	require_once(__DIR__ . "/autoload.php");

	class API {
		private static $token = null;

		public static function sessionEnabled() {
			return (session_id() !== '');
		}

		// Returns the current token
		public static function currentToken($value = "____") {
			if(is_string($value) && strcmp($value, "____") == 0) {
				
				// If we have no token
				if(is_null(self::$token)) {

					// See if we have a session parameter
					$sessionCode = retrieve_param("session");

					// If we do
					if(!empty($sessionCode)) {
						
						// Try to get the token from that session
						$t = Token::fromSession($sessionCode);
							
						// Only set the token if we have one
						if(!empty($t)) {
							self::$token = $t;
							
							// Then sign in as this token
							static::currentToken(self::$token->ID());
						}
					}
				}

				// If we still don't have one,
				if(is_null(self::$token)) {

					// Acquire it from the authorization header or the URL
					$acquired = self::acquire();
					
					// And if acquired
					if(!empty($acquired) && strlen($acquired) >= 36) {
						
						// Set the token
						self::$token = Token::retrieve($acquired);
					}
				}
				
				// Then return it
				return self::$token;
			}
			else {
				if(is_string($value)) {
					$value = Token::retrieve($value);
				}
				if(is_null($value)) {
					self::$token = null;
					unset($_SESSION['OnSong-Connect-Token']);
					unset($_SESSION['username']);
				} else {
					if(is_object($value) && $value instanceof Token) {
						self::$token = $value;
						if(self::sessionEnabled()) {
							$_SESSION['OnSong-Connect-Token'] = self::$token->ID();
							if(!empty(self::$token->role()) && !empty(self::$token->role()->user())) {
								$_SESSION['username'] = self::$token->role()->user()->username();
							}
						}
					}
				}
			}
		}

		// Authenticate the request and return the token if legitimate
		public static function acquire() {

			// Try to retrieve the token from server variable
			if(isset($_SERVER['HTTP_AUTHORIZATION'])) {
				$header_token = str_replace("Bearer ", "", $_SERVER['HTTP_AUTHORIZATION']);
				if(strlen($header_token) >= 36) {
					return $header_token;
				}
			}

			// Try to retrieve the token from server variable
			if(isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
				$header_token = str_replace("Bearer ", "", $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
				if(strlen($header_token) >= 36) {
					return $header_token;
				}
			}

			// Try to retrieve the token from the header
			if(isset(apache_request_headers()['Authorization'])) {
				$header_token = str_replace("Bearer ", "", apache_request_headers()['Authorization']);
				if(strlen($header_token) >= 36) {
					return $header_token;
				}
			}

			// Try to acquire the token from the session
			if(isset($_SESSION['OnSong-Connect-Token'])) {
				return $_SESSION['OnSong-Connect-Token'];
			}

			// Return null
			return null;
		}

		public static function authenticate($auth_token = null, $additional = null) {

			// Clear expired tokens
			Token::clear();
			
			// Acquire the token
			$acquired = self::acquire();
			if(is_null($acquired) == false && strlen($acquired) >= 36) {
				$auth_token = $acquired;
			}

			// Error: No token provided
			if(isset($auth_token) == false || strlen($auth_token) <= 1) {
				self::throwError(400, "No Token", $auth_token, $additional);
//				throw new Exception("No Token", 403);
				return null;
			}

			// Error: The token is not long enough
			if(strlen($auth_token) < 36) {
				self::throwError(400, "Invalid Token Length", $auth_token, $additional);
//				throw new Exception("Invalid Token Length", 403);
				return null;
			}

			// Otherwise, let's try to retrieve the token
			else {

				// Retrieve the token if we don't already have one
				if(self::currentToken() != null && self::currentToken()->ID() == $auth_token && self::currentToken()->isExpired() == false) {
					return self::currentToken();
				}

				// Otherwise, retrieve the token by identifier
				self::currentToken(Token::retrieve($auth_token));

				// If the token is null, then it's not found
				if(is_null(self::currentToken())) {
					self::throwError(403, "Not Signed In", $auth_token, $additional);
//					throw new Exception("Not Signed In: ". $auth_token, 403);
					return null;
				}
				
				// If the role is inactive, also bail out
				if(self::currentToken()->role()->permissions() < 1) {
					self::throwError(403, "Role is inactive", $auth_token, $additional);
//					throw new Exception("Role is inactive", 403);
					return null;
				}

				// Otherwise, it's a success
				return self::$token;
			}
		}
		
		public static function throwError($code, $reason, $auth_token, $additional = null) {
/*
			$error = new APIError();
			$error->code($code);
			$error->reason($reason);
			$error->auth_token($auth_token);
			$error->additional($additional);
			$error->save();
*/
			throw new Exception($reason, $code);
		}
		
		// Validate the inputs
		public static function validate($requirements, $data) {
			if(!isset($data)) {
				throw new Exception("No valid input was provided");
			}
			if(!is_object($data) && !is_array($data)) {
				throw new Exception("Input must be a dictionary object");
			}
			foreach($requirements as $name=>$field) {
				if(!isset($data[$name])) {
					throw new Exception($field . " is required");
				}
			}
		}
	
		// Return a standard property list for updates
		public static function createPropertyList($dir, $id, $list) {
			$o = array();
			foreach($list as $name=>$value) {
				array_push($o, array("success"=>array(("/". $dir ."/" . $id . "/" . $name)=>$value)));
			}
			return $o;
		}

		// Logs in a user with a username and password, returning a token
		public static function login($username, $password, $role = null, $app = null) {

			// Acquire the user from the username and password
			$user = User::login($username, $password);
			if(is_null($user)) {
				throw new Exception("Username or password is invalid");
			}

			// Set up the session
			$_SESSION['username'] = $user->username();

			// Create and return the token
			return self::establishToken($user, $role, $app);
		}

		// Creates a token for the user with an optional role and app
		public static function establishToken($user, $role = null, $app = null) {
			
			// If we don't have a role, use the primary role first
			if(is_null($role)) {
				$role = $user->primaryRole();
			}

			// If we still don't have a role, then use the first role we encounter
			if(is_null($role)) {
				$role = $user->roles(0);
			}

			// If we don't have an role, then we need to error
			if($role == null) {
				
				// Then let's establish a free account
				$account = new Account();
				$account->save();
				
				$role = Role::create($account, $user, 1023, 'Administrator');
				$role->save();
			}
			
			// Check to make sure we have an app
			if(is_null($app)) {
				$app = App::master();
			} else if(is_string($app)) {
				$app = App::retrieve($app);
			}
			if(is_null($app)) {
				throw new Exception("Client app is invalid or missing");
			}

			// Then, we need to create the token
			$token = Token::create($role, $app);
			if(is_null($token) == false) {
				self::currentToken($token);
				return self::currentToken();
			} else {
				throw new Exception("Could not generate token");
			}
			
			// Return false if we have nothing
			return false;
		}
	}
?>