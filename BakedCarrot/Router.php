<?php
/**
 * Router 
 * 
 *
 * @package BakedCarrot
 */
class Router
{
	private static $routes = array();
	private static $matched_route = null;
	public static $routes_count = 0;
	

	public static function add($name, $path_mask, $params = array())
	{
		self::$routes[(string)$name] = new Route($name, $path_mask, $params);
		
		return self::$routes[(string)$name];
	}
	
	
	public static function getCurrentRoute()
	{
		return self::$matched_route;
	}
	
	
	public static function getMatchedRoute($trailing_slash = false, $offset = 0)
	{
		self::$matched_route = self::getRouteByUri(Request::getUri() . ($trailing_slash ? '/' : ''), $offset);
		
		return self::$matched_route;
	}
	
	
	public static function getRouteByUri($uri, $offset = 0)
	{
		$matched_route = null;
		$i = 0;
		
		foreach(self::$routes as $name => $route) {
			if($i++ >= $offset && $route->match($uri)) {
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
	
	
	public static function reset()
	{
		self::$routes = array();
		self::$matched_route = null;
	}
	
}	

