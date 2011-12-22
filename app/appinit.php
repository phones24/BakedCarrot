<?php
/**
 * BakedCarrot application initialization file 
 *
 * @package BakedCarrot
 * 
 *
 *
 * 
 */

// turning on error reporting
error_reporting(E_ALL | E_STRICT);


// loading main library file
require SYSPATH . 'App.php';


// init
App::create(array(
		'config'		=> APPPATH . 'config.php',
		'mode'			=> App::MODE_DEVELOPMENT, //Application::MODE_PRODUCTION,
	));


// default route
Router::add('default', '/', array(
		'controller'	=> 'index',
	));
	

// run the application
App::run();
