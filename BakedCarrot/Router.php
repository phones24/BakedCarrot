<?php
/**
 * Router 
 * 
 *
 * @package BakedCarrot
 * @author Yury Vasiliev
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php) 
 * 
 */
class Router
{
	private static $routes = array();
	private static $matched_route = null;
	

	public static function add($name, $path_mask, $params = array())
	{
		self::$routes[(string)$name] = new Route($name, $path_mask, $params);
		
		return self::$routes[(string)$name];
	}
	
	
	public static function getCurrentRoute()
	{
		return self::$matched_route;
	}
	
	
	public static function getMatchedRoute($trailing_slash = false)
	{
		if(is_null(self::$matched_route)) {
			self::$matched_route = self::getRouteByUri(Request::getUri() . ($trailing_slash ? '/' : ''));
		}
		
		return self::$matched_route;
	}
	
	
	public static function getRouteByUri($uri)
	{
		$matched_route = null;
		
		foreach(self::$routes as $name => $route) {
			if($route->match($uri)) {
				$matched_route = $route;
				break;
			}
		}
		
		return $matched_route;
	}
	
	
	public static function getRouteByName($name)
	{
		return isset(self::$routes[$name]) ? self::$routes[$name] : null;
	}
	
}	

