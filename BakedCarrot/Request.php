<?php
/**
 * Request
 *
 * Request handling class
 *
 * @package BakedCarrot
 * @author Yury Vasiliev
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php) 
 * 
 */
 
class Request
{
	private static $headers = null;
	private static $base_uri = null;
	private static $uri = null;
	
	
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
	

	public function isAjax()
	{
		return isset($_SERVER['X_REQUESTED_WITH']) && $_SERVER['X_REQUESTED_WITH'] == 'XMLHttpRequest';
	}
	
	
	public static function getBaseUri() 
	{
		if(is_null(self::$base_uri)) {
			$request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : $_SERVER['PHP_SELF'];
			$script_name = $_SERVER['SCRIPT_NAME'];
			$base_uri = strpos($request_uri, $script_name) === 0 ? $script_name : str_replace('\\', '/', dirname($script_name));
			
			self::$base_uri = rtrim($base_uri, '/');
		}
		
		return self::$base_uri;
	}

	
	public static function getUri() 
	{
		if(is_null(self::$uri)) {
			$uri = '';
			
			if(!empty($_SERVER['PATH_INFO'])){
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
		return strlen($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : null;
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


}	
