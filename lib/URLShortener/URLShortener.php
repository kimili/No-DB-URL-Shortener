<?php
/*

	No DB URL Shortener
	(c) 2014 Michael Bester
	https://github.com/kimili/No-DB-URL-Shortener
	This code may be freely distributed under the MIT license.

	Props to Ryan Petrich for the idea (https://gist.github.com/rpetrich/627137)
	Thanks to Ivan Akimov for the awesome HashIds library (http://www.hashids.org/php/)
	Thanks to Solar Designs at Openwall for PHPass (http://www.openwall.com/phpass/)

*/


/*
 * Load required libraries
 */
require_once(realpath(__DIR__ . '/../PasswordHash/PasswordHash.php'));
require_once(realpath(__DIR__ . '/../Hashids/Hashids.php'));

define('CONFIG_FILE', realpath(__DIR__ . '/../../inc/config.php'));

/*
 * Get the config
 */
if ( ! file_exists(CONFIG_FILE) ) {
	die("Can't find the configuration file. Please make sure it is set up.");
}
require_once(CONFIG_FILE);



/**
* URLShortener Class
*/
class URLShortener
{

	private $_version          = '0.2.2';
	private $_content_dir      = 'content/';
	private $_daily_count_file = '';
	private $_alphabet         = '123456789abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ'; // Alphabet excludes 0, O, I, and l to minimize ambiguious hashes

	public $needs_password_set = false;

	/**
	 * The class constructor. Handles basic flow.
	 *
	 * @return void
	 **/
	function __construct()
	{
		// Set up the private variable values
		$this->_daily_count_file = realpath(__DIR__ . '/../../inc/daily-count.txt');

		// Off the bat, let's check to see if we have a hashed password
		if ( ! $this->_is_hashed_password_defined() ) {
			// It's up to the end user to implement a method (a form)
			// which sets a password and allows us to save a salted hash of it.
			if ( $this->_get_param('newpassword') == '' ) {
				$this->needs_password_set = true;
			} else {
				// If we're here, we've passed in a new password to hash.
				$this->_set_hashed_password();
			}
			return;
		}

		// Here, let's check to see if we're passing in a new link
		if ( isset($_GET["create"])) {
			// Let's put this in JSON mode
			header('Content-type: application/json');
			echo json_encode($this->_create_new_shortlink());
			exit();
		}
		if ( isset($_GET["delete"]) ) {
			// Let's put this in JSON mode
			header('Content-type: application/json');
			echo json_encode($this->_delete_shortlink());
			exit();
		}

		// Did we get here? See if we need to forward the user along to a URL
		$this->_do_saved_link_check();
	}

	/**
	 * Returns the current version number
	 *
	 * @return string - the current version
	 **/
	public function get_version()
	{
		return $this->_version;
	}


	/**
	 * Redirect to the default REDIRECT from config.php
	 *
	 **/
	public function redirect_default()
	{
		header('HTTP/1.1 301 Moved Permanently');
		header('Location: '.REDIRECT);
	}


	/**
	 * Creates the new shortlink if the parameters are good
	 *
	 * @return void
	 **/
	private function _create_new_shortlink()
	{

		// First, check the password
		$this->_init_password_hasher();
		$authCheck = $this->_hasher->CheckPassword($this->_get_param('pw'), HASHED_PASSWORD);

		if ( $authCheck ) {
			// In here, we're authorized to create a new shortlink
			// First, let's see if we have a specific hash that was passed in.
			// We'll use it as long as we have it and it's unique
			$hash = $this->_get_param('hash');
			if ( ! $hash || ($hash != '' && $this->_does_hash_exist($hash)) ) {
				// Did we get here? We need a new unique hash
				$hash = $this->_generate_new_hash();
			}
			// Now that we have a hash, try to save the link.
			return $this->_save_link($hash);
		} else {
			$output = new stdClass;
			$output->error = "Sorry, but your password was incorrect.";
			return $output;
		}
	}
	
	/**
	 * Deletes a shortlink if the parameters are good
	 * Added by @fellwell5
	 *
	 * @return void
	 **/
	private function _delete_shortlink()
	{
		$output = new stdClass;
		// First, check the password
		$this->_init_password_hasher();
		$authCheck = $this->_hasher->CheckPassword($this->_get_param('pw'), HASHED_PASSWORD);

		if ( $authCheck ) {
			// In here, we're authorized to delete a new shortlink
			$hash = $this->_get_param('hash');
			$file = "$this->_content_dir/urls/$hash.url";
			if ( ! $hash || ($hash != '' && $this->_does_hash_exist($hash)) ) {
				$output->hashid = $hash;
				$output->deleted = unlink($file);
				return $output;
			}
			$output = new stdClass;
			$output->error = "Sorry, but your hash was not found.";
			return $output;
		} else {
			$output = new stdClass;
			$output->error = "Sorry, but your password was incorrect.";
			return $output;
		}
	}

	/**
	 * Sets the hashed password in the config file if one doesn't exist
	 *
	 * @return void
	 **/
	private function _set_hashed_password()
	{
		$newpassword = $this->_get_param('newpassword');

		if ( $this->_is_hashed_password_defined() || $newpassword == '' ) {
			return;
		}

		// Set up a new hasher instance
		$this->_init_password_hasher();
		$hash = $this->_hasher->HashPassword($newpassword);

		// Open up the config file for writing the hash to
		$handle = fopen(CONFIG_FILE, 'a') or die('Cannot open file: ' . CONFIG_FILE);

		// Set up the new data to write to the config.
		$configAdditions = array();
		$configAdditions[] = '';
		$configAdditions[] = '// This hashed password is set automatically.';
		$configAdditions[] = '// To reset your password, delete this comment and the HASHED_PASSWORD line below reload index.php in a browser';
		$configAdditions[] = "define('HASHED_PASSWORD', '$hash');";

		// And write it.
		fwrite($handle, implode("\n", $configAdditions));
		// Close out the file
		fclose($handle);
	}

	/**
	 * Tells us whether or not the hashed password is defined
	 *
	 * @return void
	 **/
	private function _is_hashed_password_defined()
	{
		return defined('HASHED_PASSWORD');
	}

	/**
	 * Checks to see if we have a saved link via the incoming hash and forwards the user if it's found
	 *
	 * @return void
	 **/
	private function _do_saved_link_check()
	{
		$url = $_SERVER['REQUEST_URI'];
		if ( strpos($url, '.') == false ) {
			$hash = substr($url, 1);
			// Shortened URL
			if (file_exists("$this->_content_dir/urls/$hash.url")) {
				$contents = file("$this->_content_dir/urls/$hash.url");
				header('HTTP/1.1 301 Moved Permanently');
				header('Location: '.$contents[0]);
				exit();
			}
		}
	}

	/**
	 * Generates a new unique hash
	 *
	 * @return string - the new hash
	 **/
	private function _generate_new_hash()
	{
		// Set up a new instance of hashids
		$hashids = new Hashids\Hashids(HASH_SALT, 1, $this->alphabet);

		// Set the default timezone
		date_default_timezone_set(TIMEZONE);

		// Get the daily count
		$dailycount = $this->_get_daily_count();

		// Let's check if the user passed in an ID to hash
		$passed_id = $this->_get_param('id');
		if ( $passed_id ) {
			$id = intval($passed_id . $dailycount);
		} else {
			// No id passed in?
			// Get the current timestamp as a number that represents YYMMDD
			// We'll append the daily count to it and use it as an ID
			$datestr = date('ymd');
			$id = intval($datestr . $dailycount);
		}
		// Generate the hash
		$hash = $hashids->encrypt($id);

		// Check to see if the hash is already in use
		if ( $this->_does_hash_exist($hash) ) {
			$this->_increment_daily_count();
			$this->_generate_new_hash();
		}

		return $hash;
	}

	/**
	 * Writes a new link file
	 *
	 * @param $hash - the to use to save it
	 * @return Object - The link data, or an error if the file was not writable.
	 **/
	private function _save_link($hash)
	{
		$output = new stdClass;
		$link = trim($this->_get_param('link'));

		if ( $link ) {
			$fh = fopen("$this->_content_dir/urls/$hash.url", 'w');
			if ( $fh ) {
				fwrite($fh, $link);
				fclose($fh);

				$output->originalURL = $link;
				$output->shortURL = ($_SERVER['HTTPS'] ? 'https' : 'http') . '://' . $_SERVER['SERVER_NAME'] . '/' . $hash;
				$output->baseURL = $_SERVER['SERVER_NAME'];
				$output->hash = $hash;
				// Increment the daily count
				$this->_increment_daily_count();
			} else {
				$output->error = "Could not save $link as a short URL. Please check your file permissions.";
			}
		} else {
			$output->error = "No link passed in to shorten.";
		}

		return $output;
	}

	/**
	 * Checks to see if a hash already exists
	 *
	 * @param $hash - the hash string to check
	 * @return boolean
	 **/
	private function _does_hash_exist($hash)
	{
		return file_exists("$this->_content_dir/urls/$hash.url");
	}

	/**
	 * creates a new instance of the password hasher
	 *
	 * @return void
	 **/
	private function _init_password_hasher()
	{
		if ( isset($this->_hasher) ) {
			return;
		}
		$this->_hasher = new PasswordHash(32768, false);
	}

	/**
	 * Reads the value from the daily count file
	 *
	 * @return void
	 **/
	private function _get_daily_count()
	{
		return trim(file_get_contents($this->_daily_count_file));
	}

	/**
	 * Updates the value of the daily count file
	 *
	 * @return void
	 **/
	private function _increment_daily_count()
	{
		$now = time();
		$last_modified_time = filemtime($this->_daily_count_file);
		$daily_seconds = 24 * 60 * 60;
		$daily_count = $this->_get_daily_count();

		// Was the last modified time of the daily count file today?
		if ( $last_modified_time > ($now - ($now % $daily_seconds)) ) {
			$daily_count = intval($daily_count) + 1;
		} else {
			// it was last modified before today - let's restart the count
			$daily_count = 0;
		}
		// update the daily count file
		$handle = fopen($this->_daily_count_file, 'w+') or die('Cannot open file: ' . $this->_daily_count_file);
		fwrite($handle, $daily_count);
		fclose($handle);
	}

	/**
	 * A function to get params from either get or post requests.
	 *
	 * @param key - a parameter key to get
	 * @return string - the parameter value, if found
	 **/
	private function _get_param($key = null)
	{
		if ( $key == null ) {
			return '';
		}
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'POST':
				return $_POST[$key];
				break;
			default:
				return $_GET[$key];
				break;
		}
	}

}


?>
