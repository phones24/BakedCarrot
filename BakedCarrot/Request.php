<?php
/**
 * Request
 * Request handling class
 *
 * @package BakedCarrot
 * 
 */
class Request
{
	/**
	 * List received headers
	 * @var array
	 * @static
	 */
	private static $headers = null;

	/**
	 * Base URI
	 * @var string
	 * @static
	 */
	private static $base_uri = null;

	/**
	 * Request URI
	 * @var string
	 * @static
	 */
	private static $uri = null;
	
	
	/**
	 * Private constructor
	 *
	 * @return void
	 */
	private function __construct()
	{
	}
	
	
	/**
	 * Creates instance of Request class
	 *
	 * @return void
	 * @static
	 */
	public static function create()
	{
		// taking care of magic quotes
		if(get_magic_quotes_gpc()) {
			$_GET = self::clearMagicQuotes($_GET);
			$_POST = self::clearMagicQuotes($_POST);
			$_COOKIE = self::clearMagicQuotes($_COOKIE);
		}
	}
	
	
	public static function getMethod()
	{
		return isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : false;
	}
	
	
	public static function isPost()
	{
		return self::getMethod() == 'POST';
	}
	
	
	public static function isGet()
	{
		return self::getMethod() == 'GET';
	}
	

	public function isPut() 
	{
		return self::getMethod() === 'PUT';
	}

	
	public function isDelete() 
	{
		return self::getMethod() === 'DELETE';
	}


	public function isHead() 
	{
		return self::getMethod() === 'HEAD';
	}

	
	public function isOptions() 
	{
		return self::getMethod() === 'OPTIONS';
	}


	public static function isHttps()
	{
		return (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off' );
	}
	

	public static function isAjax()
	{
		return isset($_SERVER['X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
	}
	
	
	public static function isFlash()
	{
		return isset($_SERVER['X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'Shockwave Flash';
	}
	
	
	public static function GET($var_name, $default = null)
	{
		if(!isset($_GET[$var_name])) {
			return $default;
		}
		
		return $_GET[$var_name];
	}
	
	
	public static function POST($var_name, $default = null)
	{
		if(!isset($_POST[$var_name])) {
			return $default;
		}
		
		return $_POST[$var_name];
	}
	
	
	public static function COOKIE($var_name, $default = null)
	{
		if(!isset($_COOKIE[$var_name])) {
			return $default;
		}
		
		return $_COOKIE[$var_name];
	}
	
	
	public static function getBaseUri() 
	{
		if(is_null(self::$base_uri)) {
			$request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : $_SERVER['PHP_SELF'];
			$script_name = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : null;
			$base_uri = strpos($request_uri, $script_name) === 0 ? $script_name : str_replace('\\', '/', dirname($script_name));
			
			self::$base_uri = rtrim($base_uri, '/');
		}
		
		return self::$base_uri;
	}

	
	public static function getUri() 
	{
		if(is_null(self::$uri)) {
			$uri = '';
			
			if(isset($_SERVER['PATH_INFO']) && !empty($_SERVER['PATH_INFO'])){
				$uri = $_SERVER['PATH_INFO'];
			} 
			else {
				if(isset($_SERVER['REQUEST_URI'])) {
					$uri = parse_url('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], PHP_URL_PATH);
				} 
				elseif(isset($_SERVER['PHP_SELF'])) {
					$uri = $_SERVER['PHP_SELF'];
				} 
				else {
					throw new BakedCarrotException('Unable to detect request URI');
				}
			}
			
			if(self::getBaseUri() !== '' && strpos($uri, self::getBaseUri()) === 0 ) {
				$uri = substr($uri, strlen(self::getBaseUri()));
			}
			
			self::$uri = '/' . ltrim($uri, '/');
		}
		
		return self::$uri;
	}
	
	
	private static function loadHeaders()
	{
		foreach($_SERVER as $key => $val) {
			if(strpos($key, 'HTTP_') === 0) {
				$key = str_replace('HTTP_', '', $key);
				$key = str_replace('_', ' ', $key);
				$key = strtolower($key);
				$key = ucwords($key);
				$key = str_replace(' ', '-', $key);
				
				self::$headers[$key] = $val;
			}
		}
	}
	
	
	public static function headers($key = null)
	{
		if(empty(self::$headers)) {
			self::loadHeaders();
		}
		
		if($key === null) {
			return self::$headers;
		}
		elseif(isset(self::$headers[$key])) {
			return self::$headers[$key];
		}
		
		return null;
	}
	
	
	public static function getQueryString()
	{
		return isset($_SERVER['QUERY_STRING']) && strlen($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : null;
	}

	
	public static function getReferer()
	{
		return isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null;
	}

	
	public static function isValidReferer()
	{
		if(!isset($_SERVER['HTTP_REFERER'])) {
			return false;
		}
		
		return $_SERVER['HTTP_HOST'] == parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
	}

	
	public static function reset()
	{
		self::$headers = null;
		self::$base_uri = null;
		self::$uri = null;
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
}	
