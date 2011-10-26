<?
/**
 * Abstract module class
 *
 * Provides method for loading configuration parameters for modules
 *
 * @package BakedCarrot
 * @author Yury Vasiliev
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php) 
 * 
 */
abstract class Module
{
	protected function loadParam($name, array $params = null, $default = null)
	{
		if($params && isset($params[$name])) {
			return $params[$name];
		}
		
		$prefix = strtolower(get_class($this)) . '_';
	
		if(Config::checkVar($prefix . $name)) {
			return Config::getVar($prefix . $name);
		}
		
		return $default;
	}
}
?>