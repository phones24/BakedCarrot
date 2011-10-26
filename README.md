# BakedCarrot PHP Framework

BakedCarrot is lightweight and fast php framework inpired by many other frameworks out there, like Kohana, Cake, Slim, etc.

## Features

Notable features of BakedCarrot:

* MVC compilant
* Built in simple database module, based on PDO
* Built in simple ORM module with collection support
* Powerfull routing
* Custom exception and error handling
* Useful modules/extension
	* Auth - authetification using database or file storage
	* Filelib - powerful tool for organazing web based file libraries
	* Image - simple image manipulation module
	* Navigation - helper module for organizing menues and navigational elements
	* Pagination - navigation with pages
* Templating with different template engine (like PHPTAL, Smarty, etc...)
* Very little or no configuration, and powerfull configuration abilities at the same time
* Works from any directory. More than one application by host is possible with one copy of library
* Simple logging
* Easy extendable
* Supports PHP 5.2



## Setup


### Simple setup

Place `BakedCarrot`, `app`, `.htaccess`, `config.php`, `appinit.php` and `index.php` to your hosting root directory

Now try to access you website through the browser. You should see something like this:

	Hello!
	
That's it!


### Setting up directories, different from defaults

Change these constants in `index.php`:
	
	DOCROOT - document root
	SYSPATH - path to system files
	APPPATH - path to application files
	TMPPATH - path to temporary files storage, usually used for template cache
	
If you want your application in `Application` directory, instead of `app`, then `APPPATH` should be looking like this:

	define('APPPATH', DOCROOT . 'Application' . DIRECTORY_SEPARATOR);

	
### Setting up several applications in separate sub-directories

You can setup several applications per hosting and have only one copy of library.
Assume we want to have one main application accessed from the root directory and two others named `app2` and `app3`.
Our root directory should be looking like this:
	
	app
	app2
	app3
	BakedCarrot
	.htaccess
	appinit.php
	config.php
	index.php

Copy `app`, `.htaccess`, `appinit.php` and `index.php` to `app2` directory.
	
	app2/app
	app2/.htaccess
	app2/appinit.php
	app2/config.php
	app2/index.php


Add `RewriteBase` directive to `.htaccess` that corresponds current location:
	
	RewriteBase /app2/

Edit `app2/appinit.php` and change `SYSPATH` to something like this:

	define('SYSPATH', DOCROOT . '../BakedCarrot' . DIRECTORY_SEPARATOR);	
	
Now do the same for `app3` and you can access the application with `/app2/` and `/app3/`


### Setting up database connection

The easiest way to setup database connection is to place all connection parameters in configuration file.

Edit `config.php` to something like this:

	<?php return array(
			'db_dsn'		=> 'mysql:dbname=bakedcarrot;host=localhost',
			'db_username'	=> 'bakedcarrot', 
			'db_password'	=> 'password'
		);

Consult PHP documentation if you're having trouble creating valid DSN string.

Now, all configuration parameters from `config.php` are avaliable in your application.





