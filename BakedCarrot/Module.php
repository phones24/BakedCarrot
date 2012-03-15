<?php
/**
 * Abstract module class
 * Provides method for loading configuration parameters for modules
 *
 * @package BakedCarrot
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
