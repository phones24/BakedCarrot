<?php
/**
 * BakedCarrot Auth File provider
 *
 * @package BakedCarrot
 * @subpackage Auth
 * 
 */
class AuthFile extends AuthDriver
{
	protected $delim = ':';
	protected $anon_name = null;
	protected $file = null;
	protected $users = null;

	
	public function __construct(array $params = null)
	{
		$this->setLoaderPrefix('auth');
	
		$this->anon_name = $this->loadParam('anon_name', $params, 'anon');
		$this->file = $this->loadParam('file', $params);
		
		if(!$this->file) {
			throw new AuthException('"auth_file" is not defined');
		}
		
		$this->readFile();
	}
	
	
	public function getUserByCredentials($login, $password)
	{
		foreach($this->users as $username => $data) {
			if($data['username'] == $login && $data['password'] == $password) {
				return $data;
			}
		}
		
		return null;
	}
	
	
	public function createSession($user)
	{
		if(empty($user)) {
			return;
		}
		
		if(!isset($user['username'])) {
			return;
		}
		
		$token = sha1(uniqid(rand(), true));
		$this->users[$user['username']]['token'] = $token;
		
		$this->writeFile();

		return $token;
	}
	
	
	public function removeSession($token)
	{
		foreach($this->users as $username => &$data) {
			if(isset($data['token']) && $data['token'] == $token) {
				$data['token'] = null;
			}
		}
		
		$this->writeFile();
	}
	
	
	public function clearAllSessions($user)
	{
		if(empty($user)) {
			return;
		}
		
		$this->users[$user['username']]['token'] = null;
		
		$this->writeFile();
	}

	
	public function getUserByToken($token)
	{
		foreach($this->users as $username => $data) {
			if(isset($data['token']) && $data['token'] == $token) {
				return $data;
			}
		}
		
		return null;
	}
	
	
	public function getAnonUser()
	{
		foreach($this->users as $username => $data) {
			if(strtolower($data['username']) == strtolower($this->anon_name)) {
				return $data;
			}
		}
		
		return null;
	}
	
	
	public function userHasRole($user, $role)
	{
		return true;
	}
	
	
	private function readFile()
	{
		if(!empty($this->users)) {
			return;
		}
	
		if(!is_file($this->file) || !is_readable($this->file)) {
			throw new AuthException('User database file is not readable');
		}
		
		if(!($rows = file($this->file))) {
			throw new AuthException('User database contains no valid data');
		}
		
		foreach($rows as $row) {
			$row = trim($row);
			
			if(!$row) {
				continue;
			}
			
			$data = explode($this->delim, $row);
			
			if(!isset($data[0])) {
				continue;
			}
			
			$data[0] = trim($data[0]);
			
			$this->users[$data[0]] = array(
					'username' => trim($data[0]), 
					'password' => isset($data[1]) ? $data[1] : null, 
					'token' => isset($data[2]) ? $data[2] : null
				);
		}
	}

	
	private function writeFile()
	{
		if(!is_file($this->file) || !is_writable($this->file)) {
			throw new AuthException('User database file is not writable');
		}
		
		if(!is_array($this->users)) {
			return;
		}
		
		$rows = '';
		foreach($this->users as $data) {
			$rows .= $data['username'] . $this->delim . $data['password'] . $this->delim . $data['token'] . "\n";
		}
		
		file_put_contents($this->file, $rows, LOCK_EX);
	}
}
