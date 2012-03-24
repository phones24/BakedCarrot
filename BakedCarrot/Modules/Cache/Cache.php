<?php
/**
 * BakedCarrot cache module
 *
 * @package BakedCarrot
 * @subpackage Cache
 */
require 'CacheException.php';
require 'CacheDriver.php';


class Cache extends ParamLoader
{
	private $driver = null;
	
	
	public function __construct(array $params = null)
	{
		$this->setLoaderPrefix('cache');
	
		if(!($driver_class = $this->loadParam('driver', $params))) {
			throw new CacheException('"driver" is not defined');
		}
		
		$driver_class = 'Cache' . $driver_class;
		if(!class_exists($driver_class)) {
			require $driver_class . EXT;
		}

		$this->driver = new $driver_class($params);
	}
	
	
	public function set($key, $value, $ttl = 3600)
	{
		return $this->driver->set($this->sanitize($key), $value, $ttl);
	}
	
	
	public function get($key, $default = null)
	{
		return $this->driver->get($this->sanitize($key), $default);
	}

	
	public function delete($key)
	{
		return $this->driver->delete($this->sanitize($key));
	}
	
	
	public function exists($key)
	{
		return $this->driver->exists($this->sanitize($key));
	}
	
	
	public function clear()
	{
		return $this->driver->clear();
	}
	
	
	public function increment($key, $step = 1)
	{
		return $this->driver->increment($this->sanitize($key), $step);
	}
	
	
	public function decrement($key, $step = 1)
	{
		return $this->driver->decrement($this->sanitize($key), $step);
	}
	
	
	private function sanitize($key) 
	{
		// borrowed from kohana
		return str_replace(array('/', '\\', ' '), '_', $key);
	}
}
