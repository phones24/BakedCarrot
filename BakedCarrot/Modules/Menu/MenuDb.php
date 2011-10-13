<?
require_once 'MenuDriver.php';


class MenuDb extends MenuDriver
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
		
		$this->source = $params['source'];
	}
	
	
	public function getData($item = null, &$pointer = null)
	{
		$this->loadAll();
	
		if(is_null($item)) {
			$pointer = array('id' => 0, 'object' => Orm::collection($this->source)->load());
			
			foreach($this->findChildren(0) as $child) {
				$this->getData($child, $pointer['children']);
			}
		}
		else {
			$pointer[$item->getId()] = $item->export();
			$pointer[$item->getId()]['object'] = $item;
			
			foreach($this->findChildren($item->getId()) as $child) {
				$this->getData($child, $pointer[$item->getId()]['children']);
			}
		}
		
		return $pointer;
	}
	
	
	protected function loadAll()
	{
		if(is_null($this->items)) {
			$this->items = Orm::collection($this->source)->find();
		}
	}
	
	
	protected function findChildren($parent_id)
	{
		$result = array();
		
		foreach($this->items as $item) {
			if($item->parent_id == $parent_id) {
				$result[$item->getId()] = $item;
			}
		}
		
		return $result;
	}
	
}

?>