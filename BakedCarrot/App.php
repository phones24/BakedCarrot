<?php
/**
 * BakedCarrot application class
 *
 * @package BakedCarrot
 * @author Yury Vasiliev
 * @version 0.4
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
define('BAKEDCARROT_VERSION', '0.4');

// full path to external libraries
define('VENDPATH', SYSPATH . 'Vendors' . DIRECTORY_SEPARATOR);

// full path to modules
define('MODULEPATH', SYSPATH . 'Modules' . DIRECTORY_SEPARATOR);

// full path to controllers
define('CTRLPATH', APPPATH . 'controllers' . DIRECTORY_SEPARATOR);

// full path to views
define('VIEWSPATH', APPPATH . 'views' . DIRECTORY_SEPARATOR);

// full path to entities
define('ENTPATH', APPPATH . 'entities' . DIRECTORY_SEPARATOR);

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
	private static $autoload_sys_dirs = array('Db', 'View', 'Exceptions');

	
	/**
	 * MCRYPT cipher
	 * @var int
	 * @static
	 */
	private static $mcrypt_cipher = MCRYPT_RIJNDAEL_256;
	
	
	/**
	 * MCRYPT mode
	 * @var int
	 * @static
	 */
	private static $mcrypt_mode = MCRYPT_MODE_ECB;
	
	
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
		self::$app_mode = self::MODE_DEVELOPMENT;
			
		if(is_null(self::$instance)) {
			self::$instance = new self;
		}
		
		// setting up autoloader
		spl_autoload_register(array('App', 'autoload'));
		
		try {
			// setup config
			Config::create(isset($params['config']) ? $params['config'] : null);
			Config::setVar($params);

			// application mode
			self::setMode(Config::getVar('mode', self::MODE_DEVELOPMENT));
			
			// our error handler
			if(!self::isDevMode()) {
				set_error_handler(array('App', 'handleErrors'));
			}
			
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
			Request::create();
			
			// saving application params
			self::$params = $params;

			// unregister globals
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
				
				// log the uri
				Log::out(__METHOD__ . ' URI: ' . Request::getBaseUri() . Request::getUri(), Log::LEVEL_DEBUG);
				
				// only starts output buffering in development mode
				if(!self::isDevMode()) {
					ob_start();
				}
				
				$all_routes_processed = false;
				$matched_route = null;
				$offset = -1;
				
				// start searching for matched route
				while(!$all_routes_processed) {
					$matched_route = Router::getMatchedRoute(false, $offset + 1);
					
					if(!$matched_route) {
						App::notFound();
					}
					
					// log the pattern
					Log::out(__METHOD__ . ' Matched route: "' . $matched_route->name . '", pattern: ' . 
							$matched_route->getPatternRegex() . ', offset: ' . 
							$matched_route->getOffset(), Log::LEVEL_DEBUG);
					
					// log route params
					Log::out(__METHOD__ . " Route params: \n" . print_r($matched_route->getParams(), true), 
							Log::LEVEL_DEBUG); 
					
					$offset = $matched_route->getOffset();
					
					try {
						Loader::invoke($matched_route);
					}
					catch(BakedCarrotPassException $e) {
						$all_routes_processed = false;
						continue;
					}
					
					$all_routes_processed = true;
				}
				
				if(!self::isDevMode()) {
					ob_end_flush();
				}
			}
			catch(Exception $e) {
				if(self::isDevMode()) {
					while(@ob_end_clean());
				}
				
				$classes_to_test = array(get_class($e), get_parent_class($e), 'Exception');
				$executed = false;
				
				foreach($classes_to_test as $class) {
					if(isset(self::$exception_handlers[$class])) {
						Loader::invokeExceptionHandler($e, self::$exception_handlers[$class]);
						
						Log::out(__METHOD__ . ' Exception handler "' . self::$exception_handlers[$class] . '" invoked for class "' . 
								$class . '"', Log::LEVEL_INFO);
						
						$executed = true;
						break;
					}
				}
				
				if(!$executed) {
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
	 * @param Exception $e exception to be handled
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
		else {
			if($e instanceOf BakedCarrotNotFoundException) {
				if(!headers_sent()) {
					header('HTTP/1.0 404 Not Found');
				}
				
				if(Request::isAjax() || Request::isFlash()) {
					print $e->getMessage();
				}
				else {
					print '<html><head></head><body style="font: 10pt arial; margin: 40px;">' .
						'<h1 style="font-weight: normal; font-size: 30px;">404 Page Not Found</h1>' . 
						($e->getMessage() ? '<h3 style="margin: 0; font-weight: normal;">Message: ' . $e->getMessage() . '</h3>' : '') .
						(self::isDevMode() ? '<p>' . nl2br($e->getTraceAsString()) . '</p>' : '') .
						'</body>';
				}
			}
			else {
				if(!headers_sent()) {
					header('HTTP/1.1 500 Internal Server Error');
				}
				
				if(Request::isAjax() || Request::isFlash()) {
					print 'EXCEPTION (' . get_class($e) . '): ' . $e->getMessage() . "\n";
					
					if(self::isDevMode() && get_class($e) == 'PDOException') {
						print 'SQL: ' . Db::lastSql();
					}
				}
				else {
					print '<html><head></head><body style="font: 10pt arial; margin: 40px;">' .
						'<h1 style="font-weight: normal; font-size: 30px;">' . get_class($e) . ' occured</h1>' . 
						'<h3 style="margin: 0; font-weight: normal;">Message: ' . $e->getMessage() . '</h3>' .
						'<h3 style="margin: 0; font-weight: normal;">Code: ' . $e->getCode() . '</h3>' .
						(self::isDevMode() && get_class($e) == 'PDOException' ? '<h3 style="margin: 0;">SQL: ' . Db::lastSql() . '</h3>' : '') .
						(self::isDevMode() ? '<p>' . nl2br($e->getTraceAsString()) . '</p>' : '') .
						'<h4 style="font-weight: normal;"><em>Baked Carrot ver ' . BAKEDCARROT_VERSION . '</em></h4>' .
						'</body>';
				}
			}
		}
		
		exit(-1);
	}

	
	/**
	 * Handle php errors (except fatals) by throwing BakedCarrotException.
	 * Returns true if error has been handled
	 *
	 * @param $errno error number
	 * @param $errstr error message
	 * @return bool
	 * @static
	 */
	public static function handleErrors($errno, $errstr)
	{
		if(error_reporting() & $errno) {
			throw new BakedCarrotException($errstr, $errno);
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
	 * @param $class_name name of the exception to be handled
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
		
		// then sysdirs
		foreach(self::$autoload_sys_dirs as $dir) {
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
	
	
	/**
	 * Check if the application initialised
	 *
	 * @return bool 
	 * @static
	 */
	public static function initialized()
	{
		return !is_null(self::$instance);
	}
	
	
	/**
	 * Set the encrypted cookie
	 *
	 * @param string $key name of the cookie
	 * @param string $val value of the cookie
	 * @param integer $exp expiration time
	 * @param string $path cookie path
	 * @param string $domain cookie domain
	 * @param bool $secure sends the cookie only for SSL connection
	 * @param bool $httponly cookie only accessible through plain HTTP connection
	 * @return bool operation result
	 * @static
	 */
	public static function setCookie($key, $val, $exp = 0, $path = '/', $domain = '', $secure = false, $httponly = false)
	{
		$cookie_secret = Config::getVar('secret_key');
		
		if(!$cookie_secret) {
			throw new BakedCarrotException('Cannot set cookie without "secret_key" parameter');
		}

		$cookie_hash = App::hash($key . $val . $cookie_secret); 
		
		if(extension_loaded('mcrypt')) {
			$iv_size = mcrypt_get_iv_size(self::$mcrypt_cipher, self::$mcrypt_mode);
			$iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
			$crypted_val = mcrypt_encrypt(self::$mcrypt_cipher, $cookie_secret, $val, self::$mcrypt_mode, $iv);
			$val = trim(base64_encode($crypted_val));
		}
		
		$val = $cookie_hash . '~~' . $val;
		
		return setcookie($key, $val, $exp, $path, $domain, $secure, $httponly);
	}
	
	
	/**
	 * Get cookie value
	 *
	 * @param string $key name of the cookie
	 * @return string|false cookie value or false if cookie is invalid or doesn't exists
	 * @static
	 */
	public static function getCookie($key)
	{
		if(!isset($_COOKIE[$key])) {
			return false;
		}
		
		$cookie_val = $_COOKIE[$key];

		if(strpos($cookie_val, '~~') !== false) {
			list($cookie_hash, $cookie_val) = explode('~~', $cookie_val);
			
			if(!strlen($cookie_hash) && !strlen($cookie_val)) {
				return false;
			}

			$cookie_secret = Config::getVar('secret_key');
			
			if(!$cookie_secret) {
				throw new BakedCarrotException('Cannot set cookie without "secret_key" parameter');
			}

			if(extension_loaded('mcrypt')) {
				$cookie_val = base64_decode($cookie_val);
		
				$iv_size = mcrypt_get_iv_size(self::$mcrypt_cipher, self::$mcrypt_mode);
				$iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
				$cookie_val = mcrypt_decrypt(self::$mcrypt_cipher, Config::getVar('secret_key'), $cookie_val, self::$mcrypt_mode, $iv);
				$cookie_val = rtrim($cookie_val, "\x0");
			}

			$hash_to_test = App::hash($key . $cookie_val . $cookie_secret); 
			
			if($hash_to_test != $cookie_hash) {
				self::deleteCookie($key);
				$cookie_val = false;
			}
		}
	
		return $cookie_val;
	}
	

	/**
	 * Remove cookie
	 *
	 * @param string $key name of the cookie
     * @param string $path cookie path
     * @param string $domain cookie domain
     * @param bool $secure sends the cookie only for SSL connection
     * @param bool $httponly cookie only accessible through plain HTTP connection
	 * @static
	 */
	public static function deleteCookie($key, $path = '/', $domain = '', $secure = false, $httponly = false)
	{
		setcookie($key, null, time() - 3600, $path, $domain, $secure, $httponly);
	}
	
	
	/**
	 * Calculating hash of the string
	 *
	 * @param string $string input string
	 * @return string hash value
	 * @static
	 */
	public static function hash($string)
	{
		if(extension_loaded('hash')) {
			if(!($key = Config::getVar('secret_key'))) {
				throw new BakedCarrotException('"secret_key" parameter is not defined');
			}

			return hash_hmac('sha256', $string, $key);
		}
		else {
			return sha1($key);
		}
	}
	
	
	/**
	 * Throws special exception that sends 404 error to client
	 *
	 * @param string $message message to client
	 * @return void
	 * @static
	 */
	public static function notFound($message = null)
	{
		throw new BakedCarrotNotFoundException($message);
	}
	
	
	/**
	 * Throws exception that sends 500 error to client
	 *
	 * @param string $message message to client
	 * @return void
	 * @static
	 */
	public static function error($message = null)
	{
		throw new BakedCarrotException((string)$message);
	}
}

