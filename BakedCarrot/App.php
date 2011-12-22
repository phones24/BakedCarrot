<?php
/**
 * BakedCarrot application class
 *
 * @package BakedCarrot
 * @author Yury Vasiliev
 * @version 0.3.1
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

 
// checking that all constants are defined
if(!defined('DOCROOT')) {
	trigger_error('DOCROOT constant is not defined', E_USER_ERROR);
}

if(!defined('SYSPATH')) {
	trigger_error('SYSPATH constant is not defined', E_USER_ERROR);
}

if(!defined('APPPATH')) {
	trigger_error('APPPATH constant is not defined', E_USER_ERROR);
}

 
// version of library
define('BAKEDCARROT_VERSION', '0.3.1');

// full path to external libraries
define('VENDPATH', SYSPATH . 'Vendors' . DIRECTORY_SEPARATOR);

// full path to modules
define('MODULEPATH', SYSPATH . 'Modules' . DIRECTORY_SEPARATOR);

// full path to controllers
define('CTRLPATH', APPPATH . 'controllers' . DIRECTORY_SEPARATOR);

// full path to views
define('VIEWSPATH', APPPATH . 'views' . DIRECTORY_SEPARATOR);

// full path to models
define('MODELPATH', APPPATH . 'models' . DIRECTORY_SEPARATOR);

// full path to collections
define('COLLPATH', APPPATH . 'collections' . DIRECTORY_SEPARATOR);

// default extension used by class loaders
define('EXT', '.php');


/**
 * BakedCarrot Application class
 *
 * 
 */
class App
{
	/**
	 * Application mode constants
	 * @see setMode
	 */
	const MODE_DEVELOPMENT = 'dev';
	const MODE_PRODUCTION = 'prod';
	
	/**
	 * Ponter to itself
	 * @var App
	 * @static
	 */
	private static $instance = null;

	/**
	 * List of all loaded modules
	 * @var array
	 * @static
	 */
	private static $modules = null;

	/**
	 * List of params
	 * @var array
	 * @static
	 */
	private static $params = null;

	/**
	 * Current application mode
	 * @var int
	 * @static
	 */
	private static $app_mode = null;
	
	/**
	 * List of installed exception handlers
	 * @var array
	 * @static
	 */
	private static $exception_handlers = array();
	
	
	/**
	 * List of classes for autoloader
	 * @var array
	 * @static
	 */
	private static $autoload_dirs = array('Db', 'View', 'Exceptions');

	
	/**
	 * Private constructor
	 *
	 * @return void
	 */
	private function __construct()
	{
	}
	
	
	/**
	 * Creates instance of App class and initialize the application
	 *
	 * @param array $params initialization parameters
	 * @return void
	 * @static
	 */
	public static function create(array $params = null)
	{
		if(is_null(self::$instance)) {
			self::$instance = new self;
		}
		
		// setting up autoloader
		spl_autoload_register(array('App', 'autoload'));
		
		try {
			self::$app_mode = self::MODE_DEVELOPMENT;
			
			// application mode
			if(isset($params['mode'])) {
				self::setMode($params['mode']);
			}

			// our error handler
			if(!self::isDevMode()) {
				set_error_handler(array('App', 'handleErrors'));
			}
			
			// setup config
			Config::create(isset($params['config']) ? $params['config'] : null);
			Config::setVar($params);

			// language specific params
			if(function_exists('mb_internal_encoding')) {
				mb_internal_encoding(Config::getVar('app_encoding', 'UTF-8'));
			}
			
			if(function_exists('date_default_timezone_set')) {
				date_default_timezone_set(Config::getVar('app_timezone', 'UTC'));
			}
			
			setlocale(LC_CTYPE, Config::getVar('app_locale', 'en_US.UTF-8'));
			
			// creating base objects
			Log::create();
			
			// saving application params
			self::$params = $params;
			
			// taking care of magic quotes
			if(get_magic_quotes_gpc()) {
				$_GET = self::clearMagicQuotes($_GET);
				$_POST = self::clearMagicQuotes($_POST);
				$_COOKIE = self::clearMagicQuotes($_COOKIE);
			}

			if(ini_get('register_globals')) {
				self::unregisterGlobals(array('_POST', '_GET', '_COOKIE', '_REQUEST', '_SERVER', '_ENV', '_FILES'));
				ini_set('register_globals', false);
			}
		}
		catch(Exception $e) {
			self::$instance->handleDefaultException($e);
		}
	}
	
	
	/**
	 * Sends response with HTTP redirect
	 *
	 * @param string $url address 
	 * @param integer $status HTTP status code (300-307)
	 * @return void
	 * @static
	 */
	public static function redirect($url, $status = 302) 
	{
		if($status >= 300 && $status <= 307) {
			while(@ob_end_clean());
			header('Location: ' . (string)$url, true, $status);
			exit;
		} 
		else {
			throw new BakedCarrotException('Redirect only accepts HTTP 300-307 status codes');
		}
	}

	
	/**
	 * Runs the application
	 * Should be called once in appinit.php
	 *
	 *
	 * @return void
	 * @static
	 */
	public static function run()
	{
		try {
			try {
				if(substr(Request::getUri(), -1) !== '/') {
					if(Router::getMatchedRoute(true)) {
						self::redirect(Request::getBaseUri() . Request::getUri() . '/' . Request::getQueryString());
					}
				}
				
				$matched_route = Router::getMatchedRoute();
				
				if(!$matched_route) {
					throw new NotFoundException();
				}
			
				Log::out(__METHOD__ . ' Matched route: "' . $matched_route->name . '", pattern: ' . $matched_route->raw_pattern, Log::LEVEL_DEBUG);
				
				// only starts output buffering in development mode
				if(!self::isDevMode()) {
					ob_start();
				}
				
				Loader::invoke($matched_route);
				
				if(!self::isDevMode()) {
					ob_end_flush();
				}
			}
			catch(Exception $e) {
				if(self::isDevMode()) {
					while(@ob_end_clean());
				}
				
				$class = get_class($e);
				
				if(isset(self::$exception_handlers[$class])) {
					Loader::invokeExceptionHandler($e, self::$exception_handlers[$class]);
				}
				else {
					throw $e;
				}
			}
		}
		catch(Exception $e) {
			self::$instance->handleDefaultException($e);
		}
	}
	
	
	/**
	 * Handles default exception if it wasn't handled before
	 *
	 * @param Exception $e exception to be handeled 
	 * @return void
	 * @static
	 */
	private function handleDefaultException($e)
	{
		Log::out(get_class($e) . ', ' . $e->getMessage(), Log::LEVEL_CRIT);
		
		if(php_sapi_name() == 'cli') {
			print "\n" . get_class($e) . " occured\n" .
				'Message: ' . $e->getMessage() . "\n" .
				'Code: ' . $e->getCode() . "\n" .
				$e->getTraceAsString() . "\n";
				
		}
		elseif(isset($_SERVER['HTTP_USER_AGENT']) && $_SERVER['HTTP_USER_AGENT'] == 'Shockwave Flash') {
			print 'EXCEPTION (' . get_class($e) . '): ' . $e->getMessage();
		}
		else {
			if(!headers_sent()) {
				header('HTTP/1.1 500 Internal Server Error');
			}
			
			print '<html><head></head><body style="font: 10pt arial; margin: 30px;">' .
				'<h1><em>' . get_class($e) . '</em> occured</h1>' . 
				'<h3 style="margin: 0;">Message: ' . $e->getMessage() . '</h3>' .
				'<h3 style="margin: 0;">Code: ' . $e->getCode() . '</h3>' .
				(self::isDevMode() && get_class($e) == 'PDOException' ? '<h3 style="margin: 0;">SQL: ' . Db::lastSql() . '</h3>' : '') .
				(self::isDevMode() ? '<p>' . nl2br($e->getTraceAsString()) . '</p>' : '') .
				'<h4><em>Baked Carrot ver ' . BAKEDCARROT_VERSION . '</em></h4>' .
				'</body>';
		}
		
		return;
	}

	
	/**
	 * Handle php errors (except fatals) by throwing BakedCarrotException.
	 * Returns true if error has been handeled
	 *
	 * @param $errno error number
	 * @param $errstr error message
	 * @param $errfile source file
	 * @param $errline line in file
	 * @param $errcontext the context
	 * @return bool
	 * @static
	 */
	public static function handleErrors($errno, $errstr, $errfile = '', $errline = 0, $errcontext = array())
	{
		if(error_reporting() & $errno) {
			throw new BakedCarrotException($errstr, $errno, 0, $errfile, $errline);
		}
		
		return true;
	}
	
	
	/**
	 * Sets application mode. Function only accepts App::MODE_DEVELOPMENT or
	 * App::MODE_PRODUCTION
	 *
	 * @param $mode application mode
	 * @return void
	 * @static
	 */
	public static function setMode($mode)
	{
		if($mode != self::MODE_DEVELOPMENT && $mode != self::MODE_PRODUCTION) {
			throw new BakedCarrotException('Invalid setMode parameter');
		}
		
		self::$app_mode = $mode;
	}
	
	
	/**
	 * Checks if the application is in development mode
	 *
	 * @return bool
	 * @static
	 */
	public static function isDevMode()
	{
		return self::$app_mode === self::MODE_DEVELOPMENT;
	}
	
	
	/**
	 * Sets the handler for certain exception
	 *
	 *		<code>
	 *		App::setExceptionHandler('NotFoundException', 'exception_handler');
	 *		</code>
	 * 
	 * "handlerExceptionName" must be defined in controller
	 *
	 * @param $class_name name of the exception to be handeled
	 * @param $handler name of the controller that handle the exception
	 * @return void
	 * @static
	 */
	public static function setExceptionHandler($class_name, $handler)
	{
		self::$exception_handlers[$class_name] = $handler;
	}
	

	/**
	 * Loads the module and returns created object
	 *
	 *		<code>
	 *		$auth = App::module('auth');
	 *		</code>
	 * 
	 * @param $module name of the module
	 * @param $params array with module parameters
	 * @return void
	 * @static
	 */
	public static function module($module, $params = null)
	{
		$module_object = null;
		
		try {
			if(!class_exists($module)) {
				$path = MODULEPATH . ucfirst($module) . DIRECTORY_SEPARATOR . ucfirst($module) . '.php';
				
				if(!is_readable($path)) {
					throw new BakedCarrotException('Cannot load module ' . $path);
				}
				
				require $path;
			}
			
			$module_object = new $module($params);
		}
		catch(Exception $e) {
			self::$instance->handleDefaultException($e);
		}
		
		return $module_object;
	}
	
	
	/**
	 * Recursively remove quotes from string or array
	 *
	 * @param $data source
	 * @return cleared data
	 * @static
	 */
    private static function clearMagicQuotes($data)
	{
		if(is_array($data)) {
			return array_map(array('self', 'clearMagicQuotes'), $data);
		}
		else {
			return stripslashes($data);
		}
    }
	
	
	/**
	 * Removes variable from GLOBALS
	 *
	 * @param params array with params to be removed
	 * @return void
	 * @static
	 */
	private static function unregisterGlobals(array $params)
	{
		foreach($params as $key => $value) {
			if(array_key_exists($key, $GLOBALS)) {
				unset($GLOBALS[$key]);
			}
		}
	}


	/**
	 * Autoload handler
	 *
	 * @param $class class name to be loaded
	 * @return void
	 * @static
	 */
	public static function autoload($class)
	{
		// try SYSPATH
		if(is_file(SYSPATH . $class . EXT)) {
			require(SYSPATH . $class . EXT);
			return;
		}
		
		foreach(self::$autoload_dirs as $dir) {
			$file_to_try = SYSPATH . $dir . DIRECTORY_SEPARATOR . $class . EXT;
			
			if(is_file($file_to_try)) {
				require($file_to_try);
				return;
			}
		}
	}
	
	
	/**
	 * Returns parameter of current route
	 *
	 * 		// adding route
	 *		Router::add('blog', '/blog/(<post_id:\d+>/)');
	 *		
	 *		// getting post_id
	 *		$post_id = App::routeParam('post_id');
	 *
	 *
	 * @param $name name of the parameter
	 * @return parameter value or NULL if this parameter is not defined
	 * @static
	 */
	public static function routeParam($name)
	{
		if($route = Router::getCurrentRoute()) {
			return $route->getParam($name);
		}
		
		return null;
	}
	
	
	public static function initialized()
	{
		return !is_null(self::$instance);
	}
	
}

