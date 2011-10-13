<?
class Route
{
	private $params = array();
	private $route_params = array();
	

	public function __construct($name, $pattern, $params = array())
	{
		$this->params = $params;
		$this->params['name'] = trim($name);
		$this->params['pattern'] = $pattern;
		$this->params['controller'] = isset($this->params['controller']) ? $this->params['controller'] : $this->params['name'];
		$this->params['action'] = isset($this->params['action']) ? $this->params['action'] : 'index';
	}

	
	public function __get($key)
	{
		return isset($this->params[$key]) ? $this->params[$key] : null;
	}
	

	public function __set($key, $val)
	{
		$this->params[$key] = $val;
	}

	
	public function __isset($key)
	{
		return isset($this->params[$key]);
	}

	
	public function getParam($key)
	{
		return isset($this->route_params[$key]) ? $this->route_params[$key] : null;
	}
	
	
	public function match($uri_to_match)
	{
		if(preg_match("/[^a-zA-Z0-9_\-\.\/\\\]/", $this->controller)) {
			throw new InvalidArgumentException('Invalid controller name "' . $this->controller . '"');
		}
	
		if(!self::isValidName($this->action)) {
			throw new InvalidArgumentException('Invalid action name "' . $this->action . '"');
		}
		
		$this->raw_pattern = self::convertPatternToRegex($this->pattern);
		$matched = @preg_match($this->raw_pattern, $uri_to_match, $matches);
		
		if($matched === false) {
			throw new RuntimeException('Route error: ' . $this->name);
		}
		
		$result = false;
		
		if($matched > 0) {
			$this->route_params = $matches;

			if(isset($matches['controller'])) {
				if(!self::isValidName($matches['controller'])) {
					throw new InvalidArgumentException('Invalid controller name "' . $matches['controller'] . '"');
				}
			
				$this->controller = $matches['controller'];
			}
			
			if(isset($matches['action'])) {
				if(!self::isValidName($matches['action'])) {
					throw new InvalidArgumentException('Invalid action name "' . $matches['action'] . '"');
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

?>