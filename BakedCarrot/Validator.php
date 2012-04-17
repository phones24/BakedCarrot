<?php
/**
 * Input validator class
 *
 * @package BakedCarrot
 */
class Validator
{
	const RULE_STRING = 1;
	const RULE_NUMERIC = 2;
	const RULE_FLOAT = 3;
	const RULE_ARRAY = 4;
	const RULE_ID = 4;
	const RULE_EMAIL = 5;
	const RULE_INT = 5;

	protected $errors = null;
	private $accum_errors = false;
	

	public function __construct($accum_errors = false)
	{
		$this->accum_errors = $accum_errors;
	}

	
	public function hasErrors()
	{
		return !empty($this->errors);
	}

	
	public function &getErrors()
	{
		return $this->errors;
	}


	public function getLastError()
	{
		return !empty($this->errors) ? end($this->errors) : null;
	}
	

	public function addError($error_message, $error_field = null)
	{
		$this->errors[] = array('message' => $error_message, 'field' => $error_field);
	}
	
	
	public function clearErrors()
	{
		$this->errors = null;
	}
	
	
	public function validateExpr($expr, $error_message, $error_field = null)
	{
		if(!$this->accum_errors && $this->hasErrors()) {
			return;
		}
	
		if((bool)$expr === true) {
			$this->addError($error_message, $error_field);
		}
		
		return (bool)$expr === true;
	}
	

	public function validate($value, $rule, $error_message, $error_field = null)
	{
		if(!$this->accum_errors && $this->hasErrors()) {
			return;
		}
		
		$valid = false;

		switch($rule) {
			//basic types
			case Validator::RULE_STRING:
				$value = trim($value);
				$valid = is_string($value) && strlen($value) > 0;
				break;
		
			case Validator::RULE_INT:
				$value = trim($value);
				$valid = is_int($value) && $value >= 0;
				break;
		
			case Validator::RULE_FLOAT:
				$value = trim($value);
				$valid = is_float($value);
				break;
		
			case Validator::RULE_ARRAY:
				$valid = is_array($value) && !empty($value);
				break;
			
			// complex types
			case Validator::RULE_NUMERIC:
				$value = trim($value);
				$valid = is_numeric($value);
				break;
		
			case Validator::RULE_ID:
				$value = trim($value);
				$valid = is_int($value) && $value >= 0;
				break;
		
			case Validator::RULE_EMAIL:
				$value = trim($value);
				$valid = (bool)preg_match('/^[A-sZ0-9._-]+@[A-Z0-9.-]+\.[A-Z]{2,6}$/i', $vlue);
				break;
		
			default:
				$valid = false;
				break;
		}
		
		if(!$valid) {
			$this->addError($error_message, $error_field);
		}
		
		return $valid;
	}
	

	public function validateGet($var_name, $rule, $error_message, $error_field = null)
	{
		if(!isset($_GET[$var_name])) {
			$this->addError($error_message, $error_field);
			return false;
		}
		
		return $this->validate($_GET[$var_name], $rule, $error_message, $error_field);
	}
	
	
	public function validatePost($var_name, $rule, $error_message, $error_field = null)
	{
		if(!isset($_POST[$var_name])) {
			$this->addError($error_message, $error_field);
			return false;
		}
		
		return $this->validate($_POST[$var_name], $rule, $error_message, $error_field);
	}
	
	
	public function validateCookie($var_name, $rule, $error_message, $error_field = null)
	{
		if(!isset($_COOKIE[$var_name])) {
			$this->addError($error_message, $error_field);
			return false;
		}
		
		return $this->validate($_COOKIE[$var_name], $rule, $error_message, $error_field);
	
	}
}	

