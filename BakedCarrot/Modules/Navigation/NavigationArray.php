<?php
class NavigationArray extends NavigationDriver
{
	protected $params = null;
	protected $items = null;
	protected $source = null;
	
	
	public function __construct($params = null)
	{
		$this->params = $params;
		
		if(!is_array($params['source'])) {
			throw new BakedCarrotException('"source" must be an array');
		}
		
		$this->source = $params['source'];
	}
	
	
	public function getData()
	{
		return array('name' => '', 'uri' => '', 'children' => $this->source);
	}
	
}

