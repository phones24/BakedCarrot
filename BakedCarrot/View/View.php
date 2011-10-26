<?
/**
 * BakedCarrot view class
 *
 * @package BakedCarrot
 * @subpackage View
 * @author Yury Vasiliev
 *
 *
 * 
 */
 
class View
{
	private $driver = null;
	
	
	public function __construct() 
	{
		$driver_class = Config::getVar('view_driver', 'Php');
		
		$driver_class = 'View' . $driver_class;
		if(!class_exists($driver_class)) {
			require $driver_class . EXT;
		}

		$this->driver = new $driver_class();
	}
	

	public function getProvider()
	{
		return $this->driver;
	}


	public function setData($param1, $param2 = null)
	{
		$this->driver->setData($param1, $param2);
	}
	
	
	public function getData($key = null) 
	{
		return $this->driver->getData(); 
	}
	
	
	public function render($template, $data = null)
	{
		$this->setData($data);
		
		return $this->driver->render($template);
	}
	
	
	public function __get($key)
	{
		return $this->driver->getData($key);
	}
	
	
	public function __set($key, $val)
	{
		$this->driver->setData($key, $val);
	}
	
	
	public function __isset($key)
	{
		return $this->driver->checkData($key);
	}
	
}

?>