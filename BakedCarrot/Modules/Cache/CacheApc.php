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
			throw new BakedCarrotCacheException('APC extension is not loaded');
		}
	}

	/**
	 * Save data to the memory
	 */
	public function set($key, $value, $ttl)
	{
		if(!apc_store($key, $value, $ttl)) {
			throw new BakedCarrotCacheException('Cannot store value with key ' . $key);
		}
	}
	
	/**
	 * Get data from the memory
	 */
	public function get($key, $default)
	{
		$success = false;
		$result = apc_fetch($key, $success);
		
		return $success ? $result : $default;
	}

	/**
	 * Remove data from the memory
	 */
	public function delete($key)
	{
		$success = false;
		apc_fetch($key, $success);
		return $success ? apc_delete($key) : true;
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
