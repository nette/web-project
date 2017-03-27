Nette Web Project
=================

This is a simple skeleton application, using the [Nette](https://nette.org) framework. This project is meant to
be used as a starting point for your new projects.

[Nette](https://nette.org) is a popular tool for PHP web development.
It is designed to be as user-friendly and easy-to-use as possible. It's focus lies
in security and performance, and it is definitely one of the most secure PHP frameworks.


Requirements
------------

PHP 5.6 or higher.


Installation
------------

The best way to install this Web Project is by using Composer. If you don't have Composer yet,
download it by following [these instructions](https://doc.nette.org/composer). Then use this 
command in your console:

	composer create-project nette/web-project path/to/install
	cd path/to/install


Disable read-only for the `temp/` and `log/` directories.


Web Server Setup
----------------

The simplest way to get started is to start the built-in PHP server in the root directory of your project:

	php -S localhost:8000 -t www

Then visit `http://localhost:8000` in your browser to see the welcome page.

For Apache or Nginx, setup a virtual host to point to the `www/` directory of the project and you
should be ready to go.

**It is CRITICAL that the whole `app/`, `log/` and `temp/` directories are not accessible directly
via a web browser. See [security warning](https://nette.org/security-warning).**
