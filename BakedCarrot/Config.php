<?php
/**
 * BakedCarrot Config class
 * 
 * Singleton class for configuration array. 
 * To load external configuration file pass the name to App::create 
 * or create class instance with Config::create('config.php');
 *
 * Example of use:
 *
 * 		App::create(array(
 * 				'config' => DOCROOT . 'config.php',
 * 				...
 *			));
 * 
 *		// config.php
 *		<?php return array('my_var' => 'my value'); ?>		
 *
 *
 * 		// static access
 * 		$val = Config::getVar('my_var', 'default value');
 *		
 *		// via Config object
 *		$conf = Config::getInstance();
 *		$val = $conf->my_var;
 *
 *		// as array
 *		$conf = Config::getInstance();
 *		$val = $conf['my_var'];
 *
 *
 * @package BakedCarrot
 * @author Yury Vasiliev
 * 
 *
 *
 * 
 */
class Config implements ArrayAccess
{
	private static $storage = null;
	private static $instance = null;
	
	
	/**
	 * Creates instance of Config class and loads configuration file if passed
	 *
	 * @param $file configuration file to load
	 * @return void
	 * @static
	 */
	public static function create($file = null)
	{
		if(is_null(self::$instance)) {
			self::$instance = new self;
		}
		
		if($file && is_readable($file)) {
			$vars_to_add = require $file;

			Log::out(__METHOD__ . ' Configuration file loaded: "' . $file . '"', Log::LEVEL_DEBUG);
			
			self::setVar($vars_to_add);
		}
		
		return self::$instance;
	}


	/**
	 * Returns instance of Config
	 *
	 * @param $file configuration file to load
	 * @return Config
	 * @static
	 */
	public static function getInstance()
	{
		return self::$instance;
	}
	
	
	/**
	 * Set configuration parameter
	 *
	 * @param $param1 parameter name or array with key=>value
	 * @param $param2 parameter value
	 * @return void
	 * @static
	 */
	public static function setVar($param1, $param2 = null)
	{
		if(is_array($param1)) {
			self::$storage = array_merge((array)self::$storage, (array)$param1);
		}
		elseif(!is_null($param1)) {
			self::$storage[$param1] = $param2;
		}
	}

	
	/**
	 * Get the value of configuration parameter, returns $default if no parameter found with given name
	 *
	 * @param $key parameter name 
	 * @param $default default value
	 * @return mixed
	 * @static
	 */
	public static function getVar($key, $default = null)
	{
		if(isset(self::$storage[$key])) {
			return self::$storage[$key];
		}
		
		return $default;
	}

	
	/**
	 * Check if parameter exists
	 *
	 * @param $key parameter name 
	 * @return bool
	 * @static
	 */
	public static function checkVar($key)
	{
		if(isset(self::$storage[$key])) {
			return true;
		}
		
		return false;
	}


	/**
	 * Magic method, returns parameter value 
	 *
	 * @param $key parameter name 
	 * @return bool
	 * @static
	 */
	public function __get($key) 
	{
		if(isset(self::$storage[$key])) {
			return self::$storage[$key];
		}
		
		return null;
		
	}
	
	
	public function offsetSet($offset, $value) 
	{
		if (is_null($offset)) {
			self::$storage[] = $value;
		} 
		else {
			self::$storage[$offset] = $value;
		}
	}
	
	
	public function offsetExists($offset) 
	{
		return isset(self::$storage[$offset]);
	}
	
	
	public function offsetUnset($offset) 
	{
		unset(self::$storage[$offset]);
	}
	
	
	public function offsetGet($offset) 
	{
		return isset(self::$storage[$offset]) ? self::$storage[$offset] : null;
	}
}

