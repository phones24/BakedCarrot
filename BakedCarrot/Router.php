<?php
/**
 * Router 
 * Takes care of route handling, keeps all routes added in appinit.php
 *
 * @package BakedCarrot
 */
class Router
{
	private static $routes = array();
	private static $matched_route = null;

	/**
	 * Number of routes in router
	 * @static
	 * @var int
	 */
	public static $routes_count = 0;


	/**
	 * Adds route to Router
	 *
	 * @static
	 * @param string $name name of the route (must be unique)
	 * @param string $path_mask pseudo regular expression
	 * @param array $params additional params(like "controller")
	 * @return mixed created route object
	 */
	public static function add($name, $path_mask, $params = array())
	{
		self::$routes[(string)$name] = new Route($name, $path_mask, $params);
		
		return self::$routes[(string)$name];
	}


	/**
	 * Returns current matched route
	 *
	 * @static
	 * @return mixed null or Route object
	 */
	public static function getCurrentRoute()
	{
		if(!self::$matched_route) {
			self::getMatchedRoute();
		}

		return self::$matched_route;
	}


	/**
	 * Returns last matched route
	 *
	 * @static
	 * @param bool $trailing_slash ask the router to add slash to URI before checking
	 * @param int $offset offset from the first route
	 * @return mixed null or Route object
	 */
	public static function getMatchedRoute($trailing_slash = false, $offset = 0)
	{
		self::$matched_route = self::getRouteByUri(Request::getUri() . ($trailing_slash ? '/' : ''), $offset);
		
		return self::$matched_route;
	}


	/**
	 * Returns route by given URI
	 *
	 * @static
	 * @param string $uri URI to match
	 * @param int $offset - offset from the first route in router
	 * @return mixed null or Route object
	 */
	public static function getRouteByUri($uri, $offset = 0)
	{
		$m_route = null;
		$i = 0;

		foreach(self::$routes as $name => $route) {
			if($i++ >= $offset && $route->match($uri)) {
				$m_route = clone $route; // clone to keep route params!
				break;
			}
		}
		
		return $m_route;
	}


	/**
	 * Returns route by its name
	 *
	 * @static
	 * @param string $name name of the route
	 * @return mixed null or Route object
	 */
	public static function getRouteByName($name)
	{
		return isset(self::$routes[$name]) ? self::$routes[$name] : null;
	}
	

	/**
	 * Resets all the route and cleaning the container
	 *
	 * @static
	 * @return void
	 */
	public static function reset()
	{
		self::$routes = array();
		self::$matched_route = null;
	}

}	

