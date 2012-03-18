<?php
/**
 * BakedCarrot abstract cache driver
 *
 * @package BakedCarrot
 * @subpackage Cache
 */
 
abstract class CacheDriver extends ParamLoader
{
	abstract public function __construct($params);
	
	abstract public function set($key, $value, $ttl);
	
	abstract public function get($key, $default);

	abstract public function delete($key);
	
	abstract public function clear();
	
	abstract public function increment($key, $step);

	abstract public function decrement($key, $step);
	
}
