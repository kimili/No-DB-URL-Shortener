No DB URL Shortener
===================

This is a simple little URL shortener for personal use which utilizes flat file rather than a database in which to store its data. It's easy to set up, easy to use and secure.

Installation
------------

To install the No DB URL Shortener, take the following steps:

* Upload this entire repository to the host on which you want this to live. It should work on the document root or in a subdirectory. Don't forget the `.htaccess` file. Alternatively, you can do clone this repository to the server where you want this to live.
* In the `inc` directory, rename `config.php.sample` to `config.php` and change the values in that file to something that works for you.
* Change permissions on `inc/config.php` and `content/urls` to be writable by the server. Ususally, that involves a `chmod 777` on those files in the terminal.
* Hit the URL you've uploaded this to in your browser. You should be prompted to set a password, so do so!

That's it! Once you've set your password, the URL Shortener is ready for you to use.

Adding a Short URL
-----------------

