<?php

class NavigationDb extends NavigationDriver
{
	protected $items = null;
	protected $source = null;
	
	
	public function __construct(array $params = null)
	{
		$this->setLoaderPrefix('navigation');

		$this->source = $this->loadParam('source', $params);
		
		if(!$this->source) {
			throw new BakedCarrotException('"navigation_source" is not defined');
		}
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
			$this->items = Orm::collection($this->source)->order('sort')->findAll();
		}
	}
	
	
	protected function findChildren($parent_id)
	{
		$result = array();
		
		if(is_array($this->items)) {
			foreach($this->items as $item) {
				if($item->parent_id == $parent_id) {
					$result[$item->getId()] = $item;
				}
			}
		}
		
		return $result;
	}
	
}

