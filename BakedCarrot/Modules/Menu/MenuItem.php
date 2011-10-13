<?
class MenuItem implements ArrayAccess
{
	private $item = null;
	private $user = null;
	private $children = array();
	private $parent = null;
	private $owner = null;
	
	
	public function __construct($owner, &$item, $user = null, $parent = null, $depth = 0)
	{
		$this->item = $item;
		$this->user = $user;
		$this->parent = $parent;
		$this->owner = $owner;
		
		$uri_to_match = Request::getUri();
		
		if($this->owner->ignorePrefix()) {
			$uri_to_match = @preg_replace('#^' . $owner->ignorePrefix() . '#', '', $uri_to_match);
			
			if($uri_to_match === null) {
				throw new InvalidArgumentException('Invalid ignore_prefix parameter');
			}
		}
	
		if(isset($this->item['uri']) && $uri_to_match == $this->item['uri']) {
			$this->setActive();
			$this->item['selected'] = true;
		}
		else {
			$this->setActive(false);
			$this->item['selected'] = false;
		}
		
		$this->item['depth'] = $depth;
		$this->item['access'] = true;
		
		if(is_object($this->user) && isset($this->item['uri'])) {
			if(!method_exists($this->user, 'hasRole')) {
				throw new RuntimeException(get_class($this->user) . '::hasRole() is not defined');
			}

			if(($menu_item_route = Router::getRouteByUri($this->item['uri'])) && $menu_item_route->acl) {
				$this->item['access'] = $this->user->hasRole($menu_item_route->acl);
			}
		}
		
		$this->getChildren();
	}

	
	public function setActive($active = true)
	{
		$this->item['active'] = $active;
		
		if($active && $this->parent && is_a($this->parent, 'MenuItem')) {
			$this->parent->setActive();
		}
	}
	
	
	public function getChildren()
	{
		if($this->children) {
			return $this->children;
		}
		
		if(!isset($this->item['children'])) {
			return array();
		}
	
		foreach($this->item['children'] as &$child) {
			$this->children[] = new MenuItem($this->owner, $child, $this->user, $this, $this->item['depth'] + 1);
		}
		
		return $this->children;
	}
	

	public function getParent()
	{
		return $this->parent;
	}
	

	public function hasChildren()
	{
		return isset($this->item['children']) && !empty($this->item['children']);
	}


	public function childrenHasAccess()
	{
		$has_access = true;

		foreach($this->getChildren() as $child) {
			if(!$child->access && $has_access) {
				$has_access = false;
			}
		}
		
		return $has_access;
	}


	public function __get($name) 
	{
		if($name != 'children' && array_key_exists($name, $this->item)) {
			return $this->item[$name];
		}
		
		return null;
	}
	

	public function __set($key, $val) 
	{
		if($name != 'children') {
			$this->item[$key] = $val;
		}
	}
	

	public function __isset($key) 
	{
		return isset($this->item[$key]);
	}
	

	public function offsetSet($offset, $value) 
	{
		if (is_null($offset)) {
			$this->item[] = $value;
		} 
		else {
			$this->item[$offset] = $value;
		}
	}
	
	
	public function offsetExists($offset) 
	{
		return isset($this->item[$offset]);
	}
	
	
	public function offsetUnset($offset) 
	{
		unset($this->item[$offset]);
	}
	
	
	public function offsetGet($offset) 
	{
		return isset($this->item[$offset]) ? $this->item[$offset] : null;
	}

	
	public function asArray()
	{
		$ret = $this->item;
		
		$ret['has_children'] = isset($ret['children']) ? true : false;
		unset($ret['children']);
		
		return new ArrayObject($ret);
	}		

/*
	private function splitUri($path)
	{
		$parts = explode('/', $path);
		
		return array_values(array_filter($parts, 'strlen'));
	}
*/	
	
}

?>