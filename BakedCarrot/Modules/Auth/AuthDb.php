<?php
/**
 * BakedCarrot Auth DB provider
 *
 * @package BakedCarrot
 * @subpackage Auth
 * 
 */

class AuthDB extends AuthDriver
{
	protected $anon_name = null;

	
	public function __construct()
	{
		$this->anon_name = Config::getVar('auth_anon_name', 'anon');
	}
	
	
	public function getUserByCredentials($login, $password)
	{
		$user = Orm::collection('User')->where('username = ? and password = ?', array($login, $password))->findOne();
	
		return $user && $user->loaded() ? $user : null;
	}
	
	
	public function createSession($user)
	{
		if(empty($user)) {
			return;
		}
		
		try {
			Db::begin();
			
			$user->last_login = Db::now();
			$user->store();
			
			$session = Orm::collection('Session')->load();
			$session->user = $user;
			$session->token = sha1(uniqid(rand(), true));
			$session->store();
			
			Db::commit();
		} 
		catch(Exception $e) {
			Db::rollback();
			throw $e;
		}		
		
		return $session->token;
	}
	
	
	public function removeSession($token)
	{
		$session = Orm::collection('Session')->where('token = ?', array($token))->findOne();
		
		$session->delete();
	}
	
	
	public function clearAllSessions($user)
	{
		if(empty($user)) {
			return;
		}
		
		Db::delete('Session', 'user_id = ?', array($user->getId()));
	}

	
	public function getUserByToken($token)
	{
		$session = Orm::collection('Session')->where('token = ?', array($token))->findOne();
		
		return $session ? $session->user : null;
	}
	
	
	public function getAnonUser()
	{
		return Orm::collection('User')->where('username = ?', array($this->anon_name))->findOne();
	}
	
	
	public function userHasRole($user, $role)
	{
		return $user->hasRole($role);
	}
}
