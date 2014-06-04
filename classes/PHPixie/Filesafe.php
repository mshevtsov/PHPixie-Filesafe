<?php
namespace PHPixie;

/**
 * Module for safe using uploaded files for PHPixie
 *
 * Idea of module is not clear enough yet.
 * It's gonna contain basic functions for security of uploaded files
 * and their processing for better using with website content.
 *
 * I've never ever made my own modules for composer or GitHub
 * 2014-05-16
*/

class Filesafe {
	/**
	 * Pixie Dependancy Container
	 * @var \PHPixie\Pixie
	 */
	public $pixie;

	/**
	 * Initializes the Filesafe module
	 * 
	 * @param \PHPixie\Pixie $pixie Pixie dependency container
	 */
	public function __construct($pixie) {
		$this->pixie = $pixie;
		$this->rootDir = $this->pixie->root_dir;
	}

	// Making a thumbnail for the image
	// $style - name of image style configured in the config file
	// $subfolder - subfolder of folder for all original uploaded files where the needed file is located
	// $filename - image that needs a thumbnail
	// This method returns a ready url to a thumbnail image for using in <img> tag.
	// It generates new image with configured parameters if it is not exist yet.
	// Otherwise it is just returns url for previously generated thumbnail.
	public function imageStyle($style, $subfolder, $filename) {
		$path = $this->pixie->config->get("filesafe.path");
		$edit = $this->pixie->config->get("filesafe.imagestyle.". $style);

		$result = $path['allfiles'] .'/'. $style .'/'. $subfolder .'/'. $filename;

		if(!file_exists($result)) {
			$method = $edit['method'];
			$image = $this->pixie->image->read($path['allfiles'] .'/'. $path['imagesources'] .'/'. $subfolder .'/'. $filename);
			if(!file_exists($path['allfiles'] .'/'. $style .'/'. $subfolder))
				mkdir($path['allfiles'] .'/'. $style .'/'. $subfolder, 0755, true);

			$image->$method($edit['width'], $edit['height'])->save($path['allfiles'] .'/'. $style .'/'. $subfolder .'/'. $filename);
		}
		return '/'. $result;
	}

	// Checking and accepting uploaded file
	// $name - name of element of global array $_FILES
	// $subfolder - subfolder of folder for all uploaded files where the file should be moved
	public function getFileImage($name, $subfolder) {
		$config = $this->pixie->config->get("filesafe");

		try {

			// Undefined | Multiple Files | $_FILES Corruption Attack
			// If this request falls under any of them, treat it invalid.
			if(!isset($_FILES[$name]['error']) || is_array($_FILES[$name]['error'])) {
				throw new Exception('Invalid parameters.');
			}

			switch($_FILES[$name]['error']) {
				case UPLOAD_ERR_OK:
					break;
				case UPLOAD_ERR_NO_FILE:
					throw new Exception('No file sent.');
				case UPLOAD_ERR_INI_SIZE:
				case UPLOAD_ERR_FORM_SIZE:
					throw new Exception('Exceeded filesize limit.');
				default:
					throw new Exception('Unknown errors.');
			}

			if($_FILES[$name]['size'] > $config['size']['max'] || $_FILES[$name]['size'] <= $config['size']['min']) {
				throw new Exception('Exceeded filesize limit.');
			}

			// Check MIME Type
			if(false === $ext = array_search(
				mime_content_type($_FILES[$name]['tmp_name']),
				$config['type']['image'],
				true
			)) {
				throw new Exception('Invalid file format.');
			}

			$newpath = $config['path']['allfiles'] ."/". $config['path']['imagesources'] ."/". $subfolder ."/";
			$filename = $_FILES[$name]['name'];
			$filename = substr($filename, 0, 255);
			$filename = strstr($filename, ".", true);
			$filename = $this->transliterate($filename);
			if(!$filename = $this->makeUnique($filename, $ext, $newpath))
				throw new Exception('Too many files with same name uploaded.');
			$filename .= '.'. $ext;

			if(!move_uploaded_file($_FILES[$name]['tmp_name'], $newpath . $filename)) {
				throw new Exception('Failed to move uploaded file.');
			}

			return $filename;

		} catch (Exception $e) {
			echo $e->getMessage();
		}

		return false;
	}







	public function getFileGeneral($name, $subfolder) {
		$config = $this->pixie->config->get("filesafe");

		try {

			// Undefined | Multiple Files | $_FILES Corruption Attack
			// If this request falls under any of them, treat it invalid.
			if(!isset($_FILES[$name]['error']) || is_array($_FILES[$name]['error'])) {
				throw new \Exception('Invalid parameters.');
			}

			switch($_FILES[$name]['error']) {
				case UPLOAD_ERR_OK:
					break;
				case UPLOAD_ERR_NO_FILE:
					throw new Exception('No file sent.');
				case UPLOAD_ERR_INI_SIZE:
				case UPLOAD_ERR_FORM_SIZE:
					throw new \Exception('Exceeded filesize limit.');
				default:
					throw new \Exception('Unknown errors.');
			}

			if($_FILES[$name]['size'] > $config['size']['max'] || $_FILES[$name]['size'] <= $config['size']['min']) {
				throw new \Exception('Exceeded filesize limit.');
			}

			// Check MIME Type
			if(false === $ext = array_search(
				mime_content_type($_FILES[$name]['tmp_name']),
				$config['type']['document'],
				true
			)) {
				throw new \Exception('Invalid file format.');
			}

			$newpath = $config['path']['allfiles'] ."/". $config['path']['documents'] ."/". $subfolder ."/";

			if(!file_exists($newpath)) {
				mkdir($newpath, 0775, true);
			}
			$filename = $_FILES[$name]['name'];
			$filename = substr($filename, 0, 255);
			$filename = strstr($filename, ".", true);
			$filename = $this->transliterate($filename);
			if(!$filename = $this->makeUnique($filename, $ext, $newpath))
				throw new \Exception('Too many files with same name uploaded.');
			$filename .= '.'. $ext;

			if(!move_uploaded_file($_FILES[$name]['tmp_name'], $newpath . $filename)) {
				throw new \Exception('Failed to move uploaded file.');
			}

			return $filename;

		} catch (\Exception $e) {
			echo $e->getMessage();
			exit;
		}

		return false;
	}








	// Deleting image file and all its thumbnails
	// $filename like "album.jpg"
	// $subfolder like "products"
	public function deleteImage($filename, $subfolder) {
		$config = $this->pixie->config->get("filesafe");

		$fullname = $config['path']['allfiles'] ."/". $config['path']['imagesources'] ."/". $subfolder ."/". $filename;
		if(file_exists($fullname))
			unlink(realpath($fullname));

		foreach($config['imagestyle'] as $style=>$params) {
			$fullname = $config['path']['allfiles'] ."/". $style ."/". $subfolder ."/". $filename;
			if(file_exists($fullname))
				unlink(realpath($fullname));
		}
	}


	public function deleteDocument($filename, $subfolder) {
		$config = $this->pixie->config->get("filesafe");

		$fullname = $config['path']['allfiles'] ."/". $config['path']['documents'] ."/". $subfolder ."/". $filename;
		if(file_exists($fullname))
			unlink(realpath($fullname));
	}


	// Appending digits to a filename if there is already one with same name
	// in target folder.
	// $filename: supposed name of new file without extension (like "album")
	// $ext: its extension (like "jpg")
	// $newpath: folder to check for files with same names (like "content/images/")
	public function makeUnique($filename, $ext, $newpath) {
		$testname = $filename;
		$extra = 2;
		while(file_exists($newpath . $testname .".". $ext) && $extra<30) {
			$testname = $filename ."_". $extra;
			$extra++;
		}
		return $extra>=30
			? false
			: $testname;
	}

	// Replacing cyrillic letters with phonetic equivalent latin letters,
	// changing to lowercase, replacing spaces and slashes with hyphen
	// and removing rest symbols.
	public function transliterate($str) {
		$tr = array(
			"ье"=>"ye","ьё"=>"yo","ью"=>"yu","ья"=>"ya",
			"А"=>"a","Б"=>"b","В"=>"v","Г"=>"g",
			"Д"=>"d","Е"=>"e","Ё"=>"e","Ж"=>"j","З"=>"z","И"=>"i",
			"Й"=>"y","К"=>"k","Л"=>"l","М"=>"m","Н"=>"n",
			"О"=>"o","П"=>"p","Р"=>"r","С"=>"s","Т"=>"t",
			"У"=>"u","Ф"=>"f","Х"=>"h","Ц"=>"c","Ч"=>"ch",
			"Ш"=>"sh","Щ"=>"sch","Ъ"=>"","Ы"=>"y","Ь"=>"",
			"Э"=>"e","Ю"=>"yu","Я"=>"ya",
			"а"=>"a","б"=>"b",
			"в"=>"v","г"=>"g","д"=>"d","е"=>"e","ё"=>"e","ж"=>"j",
			"з"=>"z","и"=>"i","й"=>"y","к"=>"k","л"=>"l",
			"м"=>"m","н"=>"n","о"=>"o","п"=>"p","р"=>"r",
			"с"=>"s","т"=>"t","у"=>"u","ф"=>"f","х"=>"h",
			"ц"=>"c","ч"=>"ch","ш"=>"sh","щ"=>"sch","ъ"=>"y",
			"ы"=>"y","ь"=>"","э"=>"e","ю"=>"yu","я"=>"ya", 
			" "=> "-", "."=> "", "/"=> "-", "\\"=> "-"
		);
		$str = preg_replace('/[^A-Za-z0-9_\-]/', '', strtr($str,$tr));
		$str = strtolower($str);
		$str = trim($str, "-");
		return $str;
	}

}