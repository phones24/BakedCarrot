<?
error_reporting(E_ALL | E_STRICT);


require SYSPATH . 'App.php';


// init
App::create(array(
		'config'		=> DOCROOT . 'config.php',
		'mode'			=> App::MODE_DEVELOPMENT, //Application::MODE_PRODUCTION,
	));


// default route
Router::add('default', '/', array(
		'controller'	=> 'index',
	));
	

// run the application
App::run();
?>