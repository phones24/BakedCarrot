<?php
/**
 * Abstract parameter loader class
 * Provides method for loading configuration parameters for modules
 *
 * @package BakedCarrot
 */
abstract class ParamLoader
{
	protected $loader_prefix = null;
	
	
	protected function setLoaderPrefix($prefix)
	{
		$this->loader_prefix = $prefix;
	}
	
	
	protected function loadParam($name, array $params = null, $default = null)
	{
		if($params && isset($params[$name])) {
			return $params[$name];
		}
		
		$prefix = ($this->loader_prefix ? $this->loader_prefix : strtolower(get_class($this))) . '_';
	
		if(Config::checkVar($prefix . $name)) {
			return Config::getVar($prefix . $name);
		}
		
		return $default;
	}
}
