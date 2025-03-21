<?php
	// Require the framework
	require_once(__DIR__ . "/autoload.php");
	
	class PivotalAttachment extends PivotalObject {
	
		public static function tableName() {
			return "pivotal_attachment";
		}
	
		public static function className() {
			return "PivotalAttachment";
		}
		
		public function projectID($value = false) {
			if($value === false) {
				$projectID = parent::projectID();
				if(empty($projectID)) {
					if(!empty($this->story())) {
						$projectID = $this->story()->projectID();
						parent::projectID($projectID);
					}
				}
				return $projectID;
			} else {
				parent::projectID($value);
			}
		}

		public function downloadURL() {
			$params = [
				'Bucket'=>"onsongpivotal",
				'Key'=>"projects/". $this->projectID() ."/attachments/". $this->ID() ."/". $this->filename()
			];
			
			$fn = $this->downloadFilename();
			if(!empty($fn)) {
				$params['response-content-disposition'] = "attachment;filename=\"". $this->filename() ."\"";
			}

			// Generate a pre-signed URL for downloading
			$s3Client = S3::client(false);
			$cmd = $s3Client->getCommand('GetObject', $params);
			$request = $s3Client->createPresignedRequest($cmd, '+20 minutes');
			$url = (string)$request->getUri();
			return $url;
		}

		public function upload($path, $filename = null, $errors = null) {
			
			// Load global settings
			global $awsS3WriteKey, $awsS3WriteSecret, $awsS3WriteRegion, $awsS3WriteVersion;
			
			// If the file doesn't exist
			if(file_exists($path) == false || is_dir($path)) {
				return false;
			}

			// Get the filename to use
			if(empty($filename)) {
				$filename = basename($path);
			}

			// Now set the filename, size and MIME type
			$this->filename($filename);
			$this->size(filesize($path));
			$this->contentType(MimeType::fromFilename($filename));
				
			// If the content type is an image
			if(startsWith($this->contentType(), "image/")) {
				$size = getimagesize($path);
				if(is_array($size)) {
					$this->width($size[0]);
					$this->height($size[1]);
				}
			}

			// Instantiate the S3 client with AWS credentials
			$s3Client = S3::client(true);

			// Open the file resource
			$fh = fopen($path, "r");

			// Upload the image file to S3
			$filename = "projects/". $this->projectID() ."/attachments/". $this->ID() ."/". $this->filename();
			$r = $s3Client->upload("onsongpivotal", $filename, $fh);

			// Close the file resource
			if(is_resource($fh)) {
				fclose($fh);
			}

			$this->uploaded(true);
			return $this->save($errors);
		}
		
		public function uploadURL() {

			// Create the S3 client
			$s3Client = S3::client(true);
			
			// Now create the uploader
			$cmd = $s3Client->getCommand('PutObject',[
				'Bucket'=>"onsongpivotal",
				'Key'=>"projects/". $this->projectID() ."/attachments/". $this->ID() ."/". $this->filename()
			]);
			$request = $s3Client->createPresignedRequest($cmd, '+20 minutes');
			$url = (string)$request->getUri();
			return $url;
		}
		
		public function save(&$errors = null, $exceptions = null) {
			if(empty($this->userID())) {
				$this->user(User::current());
			}
			return parent::save($errors, $exceptions);
		}
		
		public function delete($permanently = false, $ignorePermissions = false) {
		
			// Delete the object from S3
			if($permanently) {
				$key = "projects/". $this->projectID() ."/attachments/". $this->ID() ."/". $this->filename();
				if(!empty($key)) {
					$s3Client = S3::client(true);
					$result = $s3Client->deleteObject([
						'Bucket'=>S3::storageBucket(),
						'Key'=>$key
					]);
				}
			}

			// Then delete the actual object
			return parent::delete($permanently, $ignorePermissions);
		}
		
		public function kind() {
			return 'file_attachment';
		}
		
		public function jsonSerialize() {
			$d = parent::jsonSerialize();
			$d['uploader_id'] = $this->userID();
			$d['download_url'] = '/pivotal/file_attachments/'. $this->ID() .'/download';
			unset($d['user_id']);
			return $d;
		}
	}
?>