<?php
/**
 * Input validator class
 *
 * @package BakedCarrot
 */
class Validator
{
	/**
	 * Rule ids
	 */
	const RULE_STRING = 1;
	const RULE_NUMERIC = 2;
	const RULE_FLOAT = 3;
	const RULE_ARRAY = 4;
	const RULE_ID = 5;
	const RULE_EMAIL = 6;
	const RULE_INT = 7;

	protected $errors = array();
	private $exception_on_error = false;


	/**
	 * Public constructor
	 *
	 * @param bool $exception_on_error should we throw the exception on the error
	 */
	public function __construct($exception_on_error = false)
	{
		$this->exception_on_error = $exception_on_error;
	}


	/**
	 * Throw the exception
	 *
	 * @param array $error
	 * @throw BakedCarrotValidatorException
	 */
	public function raise(array $error = null)
	{
		if(!$error) {
			$error = $this->getLastError();
		}
		
		throw new BakedCarrotValidatorException(isset($error['message']) ? $error['message'] : '');
	}

	
	/**
	 * Checks if there are errors
	 *
	 * @return bool
	 */
	public function hasErrors()
	{
		return !empty($this->errors);
	}


	/**
	 * Returns all errors
	 *
	 * @return null
	 */
	public function &getErrors()
	{
		return $this->errors;
	}


	/**
	 * Returns last error
	 *
	 * @return mixed|null
	 */
	public function getLastError()
	{
		return !empty($this->errors) ? end($this->errors) : null;
	}


	/**
	 * Adds error to container
	 *
	 * @param $error_message
	 * @param null $error_field
	 */
	public function addError($error_message, $error_field = null)
	{
		$this->errors[] = array('message' => $error_message, 'field' => $error_field);
	}


	/**
	 * Clears error container
	 *
	 * @param $error_message
	 * @param null $error_field
	 */
	public function clearErrors()
	{
		$this->errors = null;
	}


	/**
	 * Validate the expression for TRUE
	 *
	 * @param $expr
	 * @param $error_message
	 * @param null $error_field
	 * @return bool|null
	 */
	public function validateExpr($expr, $error_message, $error_field = null)
	{
		if((bool)$expr === true) {
			$this->addError($error_message, $error_field);
		}
		
		if($this->exception_on_error && $this->hasErrors()) {
			$this->raise();
		}
		
		return (bool)$expr === true;
	}


	/**
	 * Validates the value according to rule
	 *
	 * @param $value
	 * @param $rule
	 * @param $error_message
	 * @param null $error_field
	 * @return bool|null
	 */
	public function validate($value, $rule, $error_message, $error_field = null)
	{
		$valid = false;
		
		switch($rule) {
			//basic types
			case Validator::RULE_STRING:
				$value = trim($value);
				$valid = is_string($value) && strlen($value) > 0;
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
				$valid = is_numeric($value) && strval(intval($value)) == strval($value) && $value >= 0;
				break;
		
			case Validator::RULE_EMAIL:
				$value = trim($value);
				$valid = (bool)preg_match('/^[A-sZ0-9._-]+@[A-Z0-9.-]+\.[A-Z]{2,6}$/i', $value);
				break;
		
			default:
				$valid = false;
				break;
		}
		
		if(!$valid) {
			$this->addError($error_message, $error_field);
		}
		
		if($this->exception_on_error && $this->hasErrors()) {
			$this->raise();
		}
			
		return $valid;
	}


	/**
	 * Validates GET value according to rule
	 *
	 * @param $var_name
	 * @param $rule
	 * @param $error_message
	 * @param null $error_field
	 * @return bool
	 */
	public function validateGet($var_name, $rule, $error_message, $error_field = null)
	{
		if(!array_key_exists($var_name, $_GET)) {
			$this->addError($error_message, $error_field);
			
			if($this->exception_on_error) {
				$this->raise();
			}
			
			return false;
		}
		
		return $this->validate($_GET[$var_name], $rule, $error_message, $error_field);
	}


	/**
	 * Validates POST value according to rule
	 *
	 * @param $var_name
	 * @param $rule
	 * @param $error_message
	 * @param null $error_field
	 * @return bool
	 */
	public function validatePost($var_name, $rule, $error_message, $error_field = null)
	{
		if(!array_key_exists($var_name, $_POST)) {
			$this->addError($error_message, $error_field);
			
			if($this->exception_on_error) {
				$this->raise();
			}

			return false;
		}
		
		return $this->validate($_POST[$var_name], $rule, $error_message, $error_field);
	}

}	

