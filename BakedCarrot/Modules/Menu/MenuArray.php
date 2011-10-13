<?
require_once 'MenuDriver.php';


class MenuArray extends MenuDriver
{
	protected $params = null;
	protected $items = null;
	protected $source = null;
	
	
	public function __construct($params = null)
	{
		$this->params = $params;
		
		if(!isset($params['source'])) {
			throw new InvalidArgumentException('"source" is not defined');
		}
		
		if(!is_array($params['source'])) {
			throw new InvalidArgumentException('"source" must be an array');
		}
		
		$this->source = $params['source'];
	}
	
	
	public function getData()
	{
		return array('name' => '', 'uri' => '', 'children' => $this->source);
	}
	
}

?>