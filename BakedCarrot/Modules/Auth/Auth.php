<?php
/**
 * BakedCarrot auth module
 *
 * @package BakedCarrot
 * @subpackage Auth
 * @author Yury Vasiliev
 * 
 *
 *
 * 
 */

require 'AuthException.php';
require 'AuthDriver.php';


class Auth extends Module
{
	protected $user = null;
	protected $driver = null;
	protected $config = null;
	protected $session_name = null;
	protected $session_lifetime = null;
	protected $anon_login = null;
	protected $anon_name = null;
	

	public function __construct(array $params = null)
	{
		if(!($driver_class = $this->loadParam('driver', $params))) {
			throw new AuthException('"driver" is not defined');
		}
		
		$this->session_name = $this->loadParam('session_name', $params, 'bakedcarrot');
		$this->session_lifetime = $this->loadParam('session_lifetime', $params, 3600 * 24 * 365);
		$this->anon_login = $this->loadParam('anon_login', $params, false);
		$this->anon_name = $this->loadParam('anon_name', $params, 'anon');

		$driver_class = 'Auth' . $driver_class;
		if(!class_exists($driver_class)) {
			require $driver_class . EXT;
		}

		$this->driver = new $driver_class();
	}
	

	public function login($username, $password, $remember)
	{
		$user = $this->driver->getUserByCredentials($username, $this->hash($password));
		
		if(!$user) {
			return false;
		}
		
		$token = $this->driver->createSession($user);

		if($remember) {
			$ret = setcookie($this->session_name, $token, time() + $this->session_lifetime, '/');
		}
		else {
			$ret = setcookie($this->session_name, $token, 0, '/');
		}
		
		if(!$ret) {
			throw new AuthException('Cannot set session cookie');
		}
		
		return $user;
	}
	
	
	public function logout()
	{
		if(!isset($_COOKIE[$this->session_name])) {
			return;
		}
		
		$this->driver->removeSession($_COOKIE[$this->session_name]);
		
		setcookie($this->session_name, null, time() - 6000000, '/');
	}

	
	public function clearAllSessions()
	{
		$this->driver->clearAllSessions($this->getUser());
	}


	private function getAnonUser()
	{
		$user = null;
		
		if($this->anon_login) {
			$user = $this->driver->getAnonUser();

			if(!$user) {
				throw new AuthException('Anonymous user not found');
			}
		}
		
		return $user;
	}
	
	
	public function autoLogin()
	{
		$this->user = null;
		
		if(!isset($_COOKIE[$this->session_name]) && $this->anon_login) {
			$this->user = $this->getAnonUser();
		}
		elseif(isset($_COOKIE[$this->session_name])) {
			$this->user = $this->driver->getUserByToken($_COOKIE[$this->session_name]);

			if(!$this->user && $this->anon_login) {
				$this->user = $this->getAnonUser();
			}
		}
		
		return $this->user;
	}
	
	
	public function getUser()
	{
		return $this->user;
	}
	
	
	public function loggedIn()
	{
		return (bool)$this->user;
	}
	
	
	public function hasAccessToRoute(Route $route)
	{
		if(!$this->getUser()) {
			throw new AuthException('No logged in user');
		}
		
		if(!isset($route->acl)) {
			return true;
		}
		
		return $this->driver->userHasRole($this->getUser(), $route->acl);
	}
	

	public function hash($string)
	{
		if(!($auth_hash_key = $this->loadParam('hash_key'))) {
			throw new AuthException('Hash key is not defined');
		}

		return hash_hmac('sha256', $string, $auth_hash_key);
	}
	
	
}
