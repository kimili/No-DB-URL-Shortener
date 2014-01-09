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

define('__ROOT__', dirname(__FILE__));

/*
 * Load required libraries
 */
require_once(__ROOT__.'/lib/PasswordHash/PasswordHash.php');
require_once(__ROOT__.'/lib/Hashids/Hashids.php');

/*
 * Get the config
 */

define('CONFIG_FILE', __ROOT__.'/inc/config.php');
if ( ! file_exists(CONFIG_FILE) ) {
	die("Can't find the configuration file. Please make sure it is set up.");
}
require_once(CONFIG_FILE);

// Check for the hashed password
if ( ! defined('HASHED_PASSWORD')  ) {
	// If we don't have it, we have to generate it from the config
	// Let's see if we need to display the set password form.
	if ( ! isset($_POST['newpassword']) ) {
		$setPassword = true;
	} else {
		$hasher = new PasswordHash(32768, false);
		$hash = $hasher->HashPassword($_POST['newpassword']);

		// Open up the config file for writing the hash to
		$handle = fopen(CONFIG_FILE, 'a') or die('Cannot open file: ' . CONFIG_FILE);

		// Set up the new data to write to the config.
		$configAdditions = array();
		$configAdditions[] = '';
		$configAdditions[] = '// This hashed password is set automatically.';
		$configAdditions[] = '// To reset your password, delete this comment and the HASHED_PASSWORD line below reload index.php in a browser';
		$configAdditions[] = "define('HASHED_PASSWORD', '$hash');";

		// And write it.
		fwrite($handle, $configContents . implode("\n", $configAdditions));
		// Close out the file
		fclose($handle);
	}
}

/*
 * A function to get params from either get or post requests.
 */
function param($key = null) {
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

/*
 * Set some parameters we'll need
 */
$url = $_SERVER['REQUEST_URI'];
$contentDir = 'content/';

/*
 * Check our parameters to see if we want to write a new short URL
 */
if ( param('pw') ) {

	// We're trying to set up a new link
	// Let's spit out JSON
	header('Content-type: application/json');
	$output = new stdClass;

	// Check the password
	$hasher = new PasswordHash(32768, false);
	$authCheck = $hasher->CheckPassword(param('pw'), HASHED_PASSWORD);

	if ($authCheck) {
		// If we're in here, we're authenticated

		// Did we pass in a link?
		if ( trim(param('link')) != '' ) {
			$slug = trim(param('slug'));
			if ( $slug != '' ) {
				// Did we pass in a slug to use?
				$hash = param('slug');
			} else {
				// if not, generate one.
				// Alphabet excludes 0, O, I, and l to minimize ambiguious hashes
				$alphabet = '123456789abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
				$hashids = new Hashids\Hashids($hashSalt, 1, $alphabet);

				// Set the default timezone
				date_default_timezone_set(TIMEZONE);

				// get the current timestamp as a number that represents YYMMDDHHMMSS and use it as an ID
				$id = intval(date('ymdHis'));
				$hash = $hashids->encrypt($id);
			}
			$fh = fopen("$contentDir/urls/$hash.url", 'w') or die("Can't open file for writing. Please check your permissions");
			fwrite($fh, param('link'));
			fclose($fh);

			$output->originalURL = param('link');
			$output->shortURL = ($_SERVER['HTTPS'] ? 'https' : 'http') . '://' . $_SERVER['SERVER_NAME'] . '/' . $hash;
			$output->baseURL = $_SERVER['SERVER_NAME'];
			$output->hash = $hash;

			echo json_encode($output);
			exit();
		} else {
			$output->error = "No link passed in to shorten.";
			echo json_encode($output);
			exit();
		}
	} else {
		$output->error = "Sorry, but your password was incorrect.";
		echo json_encode($output);
		exit();
	}
}



/*
 * Handle incoming Short URLs
 */
if (strpos($url, '.') == false) {
	$hash = substr($url, 1);
	// Shortened URL
	if (file_exists("$contentDir/urls/$hash.url")) {
		$contents = file("$contentDir/urls/$hash.url");
		header('HTTP/1.1 301 Moved Permanently');
		header('Location: '.$contents[0]);
		exit();
	}
}

?>

<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<title><?php echo ucfirst($_SERVER['SERVER_NAME']) ?></title>
</head>
<body>
	<?php if ($setPassword): ?>
		<form action="." method="post" accept-charset="utf-8">
			<label for="password">Set a password</label>
			<input type="text" name="newpassword" value="" id="password">
			<input type="submit" value="Continue &rarr;">
		</form>
	<?php else: ?>
		Welcome, friend. Sorry, but there's not much to see here.
	<?php endif ?>
</body>
</html>