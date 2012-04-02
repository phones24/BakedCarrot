<?php
/**
 * Route
 * Represents route object
 *
 * @package BakedCarrot
 * 
 */
class Route
{
	private $params = array();
	private $route_params = array();
	private $pattern_regex = null;
	private $offset = -1;
	

	public function __construct($name, $pattern, $params = array())
	{
		$this->params = $params;
		$this->params['name'] = trim($name);
		$this->params['pattern'] = $pattern;
		$this->params['controller'] = isset($this->params['controller']) ? $this->params['controller'] : $this->params['name'];
		$this->params['action'] = isset($this->params['action']) ? $this->params['action'] : 'index';
		$this->offset = ++Router::$routes_count;
	}


	public function __get($key)
	{
		return isset($this->params[$key]) ? $this->params[$key] : null;
	}


	public function __isset($key)
	{
		return isset($this->params[$key]);
	}


	public function getPatternRegex()
	{
		return $this->pattern_regex;
	}
	
	
	public function getParam($key)
	{
		return isset($this->route_params[$key]) ? $this->route_params[$key] : null;
	}
	
	
	public function getParams()
	{
		return $this->route_params;
	}

	
	public function getOffset()
	{
		return $this->offset;
	}


	public function match($uri_to_match)
	{
		if(preg_match("/[^a-zA-Z0-9_\-\.\/\\\]/", $this->controller)) {
			throw new BakedCarrotException('Invalid controller name "' . $this->controller . '"');
		}
	
		if(!self::isValidName($this->action)) {
			throw new BakedCarrotException('Invalid action name "' . $this->action . '"');
		}
		
		$this->pattern_regex = self::convertPatternToRegex($this->pattern);
		$matched = @preg_match($this->pattern_regex, $uri_to_match, $matches);
		
		if($matched === false) {
			throw new BakedCarrotException('Route error: ' . $this->name);
		}
		
		$result = false;
		
		if($matched > 0) {
			$this->route_params = $matches;

			if(isset($matches['controller'])) {
				if(!self::isValidName($matches['controller'])) {
					throw new BakedCarrotException('Invalid controller name "' . $matches['controller'] . '"');
				}
			
				$this->controller = $matches['controller'];
			}
			
			if(isset($matches['action'])) {
				if(!self::isValidName($matches['action'])) {
					throw new BakedCarrotException('Invalid action name "' . $matches['action'] . '"');
				}
			
				$this->action = $matches['action'];
			}
			
			$result = true;
		}
		
		return $result;
	}
	
	
	private static function convertPatternToRegex($pattern)
	{
		$regex = str_replace(')', ')?', $pattern);
		$regex = str_replace('(', '(?:', $regex);
		$regex = preg_replace("/<([^>\:]+):?([^>]+)?>/", '(?P<\\1>\\2)', $regex);
		$regex = preg_replace("/\(\?P<([^>]+)>\)/", '(?P<\\1>[^/]+)', $regex);
		
		return '#^' . $regex . '$#';
	}
	
	
	private static function isValidName($name)
	{
		return preg_match("/[^a-zA-Z0-9_]/", $name) ? false : true;
	}
}	

