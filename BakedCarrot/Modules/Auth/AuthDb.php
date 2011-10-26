<?
/**
 * BakedCarrot Auth DB provider
 *
 * @package BakedCarrot
 * @subpackage Auth
 * @author Yury Vasiliev
 * 
 *
 *
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
		$user = Orm::collection('user')->findOne('username = ? and password = ?', array($login, $password));
	
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
			
			$session = Orm::collection('session')->load();
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
		$session = Orm::collection('session')->findOne('token = ?', array($token));
		
		Orm::collection('session')->delete($session);
	}
	
	
	public function clearAllSessions($user)
	{
		if(empty($user)) {
			return;
		}
		
		Db::exec('delete from session where user_id = ?', array($user->getId()));
	}

	
	public function getUserByToken($token)
	{
		$session = Orm::collection('session')->findOne('token = ?', array($token));
		
		return $session ? $session->user : null;
	}
	
	
	public function getAnonUser()
	{
		return Orm::collection('user')->findOne('username = ?', array($this->anon_name));
	}
	
	
	public function userHasRole($user, $role)
	{
		return $user->hasRole($role);
	}
}
?>