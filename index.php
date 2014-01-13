<?php
require_once __dir__ . '/lib/URLShortener/URLShortener.php';
$URLShortener = new URLShortener();
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<title><?php echo ucfirst($_SERVER['SERVER_NAME']) ?></title>
</head>
<body>
	<?php if ($URLShortener->needs_password_set): ?>
		<form action="." method="post" accept-charset="utf-8">
			<label for="password">Set a password</label>
			<input type="password" name="newpassword" value="" id="password">
			<input type="submit" value="Continue &rarr;">
		</form>
	<?php else: ?>
		Welcome, friend. Sorry, but there's not much to see here.
	<?php endif ?>
</body>
</html>