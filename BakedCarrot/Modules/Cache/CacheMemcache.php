<?php
/**
 * BakedCarrot APC cache driver
 *
 * @package BakedCarrot
 * @subpackage Cache
 */
 
class CacheMemcache extends CacheDriver
{
	const MAX_TTL = 2592000;
	
	private $mc = null;
	private $compression = null;
	private $servers = null;
	

	public function __construct(array $params = null)
	{
		if(!extension_loaded('memcache')) {
			throw new CacheException('Memcache extension is not loaded');
		}
		
		$this->mc = new Memcache();
		
		$this->setLoaderPrefix('cache');
		$this->servers = $this->loadParam('servers', $params, null);
		$this->compression = $this->loadParam('compression', $params, false);
		
		if($this->compression) {
			$this->compression = MEMCACHE_COMPRESSED;
		}
		
		if(!is_array($this->servers)) {
			throw new CacheException('"servers" must be defined');
		}
		
		$default_server = array(
				'host'             => 'localhost',
				'port'             => 11211,
				'persistent'       => false,
				'weight'           => 1,
				'timeout'          => 1,
				'retry_interval'   => 15,
				'status'           => true
			);
		
		foreach($this->servers as &$server) {
			$server += $default_server;
			if(!$this->mc->addServer($server['host'], $server['port'], $server['persistent'], $server['weight'], $server['timeout'], $server['retry_interval'], $server['status'], array($this, 'failureСallback'))) {
				throw new CacheException('Cannot connect to memcache server ' . $server['host'] . ':' . $server['port']);
			}
		}
	}

	
	public function set($key, $value, $ttl)
	{
		// borrowed from kohana
		if($ttl > self::MAX_TTL) {
			$ttl = self::MAX_TTL + time();
		}
		elseif($ttl > 0) {
			$ttl += time();
		}
		else {
			$ttl = 0;
		}
		
		return $this->mc->set($key, $value, $this->compression, $ttl);
	}
	
	
	public function get($key, $default)
	{
		$result = $this->mc->get($key);
		
		return $result === false ? $default : $result;
	}

	
	public function delete($key)
	{
		return $this->mc->delete($key, 0);
	}
	
	
	public function clear()
	{
		return $this->mc->flush();
	}
	
	
	public function increment($key, $step)
	{
		return $this->mc->increment($key, $step);
	}
	
	
	public function decrement($key, $step)
	{
		return $this->mc->decrement($key, $step);
	}
	
	
	public function failureСallback($host, $port)
	{
		foreach($this->servers as $server) {
			if($server['host'] == $host && $server['port'] == $port) { // put server offline
				return $this->mc->setServerParams($server['host'], $server['port'], $server['persistent'], $server['weight'], $server['timeout'], $server['retry_interval'], false, array($this, 'failureСallback'));
			}
		}
		
	}
}
