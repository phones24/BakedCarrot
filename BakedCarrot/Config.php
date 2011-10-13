<?
/**
 * BakedCarrot Config class
 *
 * @package BakedCarrot
 * @author Yury Vasiliev
 * @version 0.3
 *
 *
 * 
 */
 
class Config implements ArrayAccess
{
	private static $storage = null;
	private static $instance = null;
	
	
	public static function create($file = null)
	{
		self::$instance = new self;
		
		if($file && is_readable($file)) {
			$vars_to_add = require $file;

			self::setVar($vars_to_add);
		}
		
		return self::$instance;
	}


	public static function getInstance()
	{
		return self::$instance;
	}
	
	
	public static function setVar($param1, $param2 = null)
	{
		if(is_array($param1)) {
			self::$storage = array_merge((array)self::$storage, (array)$param1);
		}
		elseif(!is_null($param1)) {
			self::$storage[$param1] = $param2;
		}
	}

	
	public static function getVar($key1, $default = null)
	{
		if(isset(self::$storage[$key1])) {
			return self::$storage[$key1];
		}
		
		return $default;
	}

	
	public static function checkVar($key1)
	{
		if(isset(self::$storage[$key1])) {
			return true;
		}
		
		return false;
	}

/*		
	public static function loadVars($table)
	{
		$settings = DB::find($table);
		
		foreach($settings as $item) {
			
		}
	}
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

?>