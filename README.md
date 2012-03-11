# BakedCarrot PHP Framework

BakedCarrot is lightweight and fast php framework inpired by many other frameworks out there, like Kohana, Cake, Slim, etc.

## Features

Notable features of BakedCarrot:

* MVC compilant
* Built in simple database module, based on PDO
* Built in ORM module with collections support
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

Place `BakedCarrot`, `app`, `.htaccess` and `index.php` to your hosting root directory

Now try to access you website through the browser. You should see something like this:

	Hello!
	
That's it!


### Setting up directories, different from defaults

Change these defines in `index.php`:
	
* `DOCROOT` - document root, better not to touch
* `SYSPATH` - path to system files
* `APPPATH` - path to application files
* `TMPPATH` - path to temporary storage, usually used for template cache
	
For example, if you want your application in `Application` directory, instead of `app`, then `APPPATH` should be looking like this:

	define('APPPATH', DOCROOT . 'Application' . DIRECTORY_SEPARATOR);

	
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





