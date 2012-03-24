<?php
/**
 * BakedCarrot APC cache driver
 *
 * @package BakedCarrot
 * @subpackage Cache
 */
 
class CacheApc extends CacheDriver
{
	public function __construct($params)
	{
		if(!extension_loaded('apc')) {
			throw new CacheException('APC extension is not loaded');
		}
	}

	
	public function set($key, $value, $ttl)
	{
		if(!apc_store($key, $value, $ttl)) {
			throw new CacheException('Cannot store value with key ' . $key);
		}
	}
	
	
	public function get($key, $default)
	{
		$result = apc_fetch($key, $success);
		
		return $success ? $result : $default;
	}

	
	public function delete($key)
	{
		return apc_delete($key);
	}
	
	
	public function exists($key)
	{
		return apc_exists($key);
	}
	
	
	public function clear()
	{
		return apc_clear_cache('user');
	}
	
	
	public function increment($key, $step)
	{
		return apc_inc($key, $step);
	}
	
	
	public function decrement($key, $step)
	{
		return apc_dec($key, $step);
	}
}
