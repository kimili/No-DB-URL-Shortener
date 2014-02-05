<?php
require_once __dir__ . '/lib/URLShortener/URLShortener.php';

// Set up the URL shortener.
$URLShortener = new URLShortener();

if ( ! $URLShortener->needs_password_set ):
	// If we've gotten here, and there's a password set, we haven't found a short URL
	// Do the default redirect
	$URLShortener->redirect_default();
else:
	// Present the set password form
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<title><?php echo ucfirst($_SERVER['SERVER_NAME']) ?></title>
</head>
<body>
	<form action="." method="post" accept-charset="utf-8">
		<label for="password">Set a password</label>
		<input type="password" name="newpassword" value="" id="password">
		<input type="submit" value="Continue &rarr;">
	</form>
</body>
</html>
<?php endif ?>
