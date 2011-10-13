<?
abstract class ViewBase 
{
	protected $data = array();

	
	abstract public function __construct();
	

	abstract public function render($template);
	
	
	public function setData($param1, $param2 = null)
	{
		if(is_array($param1)) {
			$this->data = array_merge($this->data, $param1);
		}
		elseif(is_string($param1)) {
			$this->data[$param1] = $param2;
		}
	}
	
	
	public function getData($key = null) 
	{
		if(!is_null($key)) {
			return isset($this->data[$key]) ? $this->data[$key] : null;
		} 
		else {
			return $this->data;
		}
	}
	
	
	public function checkData($key) 
	{
		return isset($this->data[$key]);
	}

	
	public function __get($key)
	{
		return $this->getData($key);
	}
	
	
	public function __set($key, $val)
	{
		$this->setData($key, $val);
	}
	
	
	public function __isset($key)
	{
		return $this->checkData($key);
	}
	
}

?>