<?
/**
 * BakedCarrot Auth provider
 *
 * @package BakedCarrot
 * @subpackage Auth
 * @author Yury Vasiliev
 * 
 *
 *
 * 
 */



abstract class AuthProvider
{
	abstract public function getUserByCredentials($login, $password);
	
	abstract public function createSession($user);
	
	abstract public function removeSession($token);

	abstract public function clearAllSessions($user);

	abstract public function getAnonUser();
}
?>