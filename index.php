<?php

/*

	No DB URL Shortener
	(c) 2014 Michael Bester

	https://github.com/kimili/No-DB-URL-Shortener
	This code may be freely distributed under the MIT license.

	Props to Ryan Petrich for the idea (https://gist.github.com/rpetrich/627137)
	And to Ivan Akimov for the awesome HashIds library (http://www.hashids.org/php/)

*/


/*
 * Configuration
 */

$password = 'password';         // Change this to a secure password in order to secure the creation of new short links
$hashSalt = 'example salt';     // Change this to a word or phrase of your choosing to salt the hashes.
$timezone = 'America/New_York'; // Change this to your timezone. The available options are listed at http://www.php.net/manual/en/timezones.php

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
if ( param('pw') == $password && trim(param('link')) != '' ) {
	$slug = trim(param('slug'));
	if ( $slug != '' ) {
		// Did we pass in a slug to use?
		$hash = param('slug');
	} else {
		// if not, generate one.
		require_once "lib/HashIds/Hashids.php";
		// Alphabet excludes 0, O, I, and l to minimize ambiguious hashes
		$alphabet = '123456789abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
		$hashids = new Hashids\Hashids($hashSalt, 1, $alphabet);

		// Set the default timezone
		date_default_timezone_set($timezone);

		// get the current timestamp as a number that represents YYMMDDHHMMSS and use it as an ID
		$id = intval(date('ymdHis'));
		$hash = $hashids->encrypt($id);
	}
	$fh = fopen("$contentDir/urls/$hash.url", 'w') or die("Can't open file for writing. Please check your permissions");
	fwrite($fh, param('link'));
	fclose($fh);
	echo ($_SERVER['HTTPS'] ? 'https' : 'http') . '://' . $_SERVER['SERVER_NAME'] . '/' . $hash;
	exit();
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
	Welcome, friend. Sorry, but there's not much to see here.
</body>
</html>