<?php
class NavigationArray extends NavigationDriver
{
	protected $items = null;
	protected $source = null;
	
	
	public function __construct($params = null)
	{
		$this->setLoaderPrefix('navigation');

		$this->source = $this->loadParam('source', $params);
		
		if(!$this->source) {
			throw new BakedCarrotException('"navigation_source" is not defined');
		}
	}
	
	
	public function getData()
	{
		return array('name' => '', 'uri' => '', 'children' => $this->source);
	}
	
}

