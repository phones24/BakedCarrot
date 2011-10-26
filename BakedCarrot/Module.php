<?
/**
 * BakedCarrot abstract module class
 *
 * @package BakedCarrot
 * @author Yury Vasiliev
 * 
 *
 *
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