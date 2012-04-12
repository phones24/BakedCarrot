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
	private static $internal_cache_data = null;
	private static $internal_cache_tables = null;

	
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
	
	
	public static function cacheQuery($key, $table, $data)
	{
		if(!self::$cache_driver) {
			return false;
		}
	
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
	
	
	public static function getCachedQuery($key)
	{
		if(!self::$cache_driver) {
			return false;
		}
	
		$result = self::$cache_driver->get($key);
		
		return $result === null ? false : $result;
	}
	
	
	public static function clearCacheForTable($table)
	{
		if(isset(self::$internal_cache_tables[$table])) {
			foreach(self::$internal_cache_tables[$table] as $key_to_wipe) {
				unset(self::$internal_cache_data[$key_to_wipe]);
			}
			
			unset(self::$internal_cache_tables[$table]);
		}
	
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
	
	
	public static function genKey($sql, $values)
	{
		$values_crc = crc32(serialize($values));
		$sql_crc = crc32($sql);
		
		return self::DATA_PREFIX . $sql_crc . '_' . $values_crc;
	}
	
	
	public static function getFromInternalCache($key)
	{
		if(isset(self::$internal_cache_data[$key])) {
			return self::$internal_cache_data[$key];
		}
	
		return false;
	}
	
	
	public static function storeInternal($key, $table, $data)
	{
		self::$internal_cache_tables[$table][] = $key;
		self::$internal_cache_data[$key] = $data;
	}
}

