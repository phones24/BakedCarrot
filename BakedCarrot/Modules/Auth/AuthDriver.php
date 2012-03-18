<?php
/**
 * BakedCarrot auth driver abstract class
 *
 * @package BakedCarrot
 * @subpackage Auth
  */
abstract class AuthDriver extends ParamLoader
{
	abstract public function getUserByCredentials($login, $password);
	
	abstract public function createSession($user);
	
	abstract public function removeSession($token);

	abstract public function clearAllSessions($user);

	abstract public function getAnonUser();
}
