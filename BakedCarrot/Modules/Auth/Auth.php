<?php
/**
 * BakedCarrot auth module
 *
 * @package BakedCarrot
 * @subpackage Auth
 * 
 */

require 'AuthException.php';
require 'AuthDriver.php';


class Auth extends ParamLoader
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
		$this->setLoaderPrefix('auth');
		
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
		$user = $this->driver->getUserByCredentials($username, App::hash($password));

		if(!$user) {
			return false;
		}
		
		$token = $this->driver->createSession($user);

		if($remember) {
			$ret = App::setCookie($this->session_name, $token, time() + $this->session_lifetime);
		}
		else {
			$ret = App::setCookie($this->session_name, $token, 0);
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
		
		$token = App::getCookie($this->session_name);
		$this->driver->removeSession($token);
		
		App::deleteCookie($this->session_name);
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
			$token = App::getCookie($this->session_name);
			$this->user = $this->driver->getUserByToken($token);

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
		$anon_user = $this->getAnonUser();
		
		return ((bool)$this->user && $anon_user != $this->user);
	}
	
	
	public function hasAccessToRoute(Route $route)
	{
		if(!$this->getUser()) {
			return false;
		}
		
		if(!isset($route->acl)) {
			return true;
		}
		
		return $this->driver->userHasRole($this->getUser(), $route->acl);
	}
}
