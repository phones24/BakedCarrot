<?php
/**
 * BakedCarrot ORM cache manager
 * 
 * @package BakedCarrot
 * @subpackage Db
 */
class OrmCache
{
	const DATA_PREFIX = 'bc_orm_data_';
	const KEYS_PREFIX = 'bc_orm_keys_';

	private static $cache_driver = null;

	
	private function __construct() 
	{
	}
	
	
	public static function create(Cache $cache)
	{
		self::$cache_driver = $cache;
	}
	
	
	public static function initialized()
	{
		return (bool)self::$cache_driver;
	}
	
	
	public static function cacheQuery($sql, $values, $table, $data)
	{
		if(!self::$cache_driver) {
			return false;
		}
	
		$values_crc = crc32(serialize($values));
		$sql_crc = crc32($sql);
		
		$key = self::DATA_PREFIX . $sql_crc . '_' . $values_crc;

		self::$cache_driver->set($key, $data);
		
		// store the keys for table
		$tables = explode(',', $table);
		foreach($tables as $table) {
			$table_key = self::KEYS_PREFIX . strtolower(trim($table));
			$keys = self::$cache_driver->get($table_key);
		
			if(!is_array($keys) || !in_array($key, $keys)) {
				$keys[] = $key;
			}
			
			self::$cache_driver->set($table_key, $keys);
		}
	}
	
	
	public static function getCachedQuery($sql, $values = null)
	{
		if(!self::$cache_driver) {
			return false;
		}
	
		$values_crc = crc32(serialize($values));
		$sql_crc = crc32($sql);
		
		$key = self::DATA_PREFIX . $sql_crc . '_' . $values_crc;
		$result = self::$cache_driver->get($key);
		
		return $result === null ? false : $result;
	}
	
	
	public static function clearCacheForTable($table)
	{
		if(!self::$cache_driver) {
			return false;
		}
		
		$tables = explode(',', $table);
		foreach($tables as $table) {
			$table_key = self::KEYS_PREFIX . strtolower(trim($table));
			$keys = self::$cache_driver->get($table_key);
			
			if(is_array($keys)) {
				foreach($keys as $key) {
					self::$cache_driver->delete($key);
				}
			}
			
			self::$cache_driver->delete($table_key);
		}
	}
}

