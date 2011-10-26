<?
/**
 * BakedCarrot navigation helper
 *
 * @package BakedCarrot
 * @subpackage Navigation
 * @author Yury Vasiliev
 * 
 * 
 *
 * 
 */
require 'MenuItem.php';
require 'NavigationDriver.php';


class Navigation extends Module
{
	protected $storage_array = null;
	protected $root_item = null;
	protected $user = null;
	protected $selected_item = null;
	protected $flat_list = null;
	protected $driver = null;
	protected $ignore_prefix = null;
	
	
	public function __construct($params = null)
	{
		if(!($driver_class = $this->loadParam('driver', $params))) {
			throw new InvalidArgumentException('"driver" is not defined');
		}
		
		if(!($source = $this->loadParam('source', $params))) {
			throw new InvalidArgumentException('"source" is not defined');
		}
		
		$this->ignore_prefix = $this->loadParam('ignore_prefix', $params);
		
		$driver_class = 'Navigation' . $driver_class;
		if(!class_exists($driver_class)) {
			require $driver_class . EXT;
		}
		
		if(($user = $this->loadParam('user', $params))) {
			$this->setUser($user);
		}
		
		$params['source'] = $source;
		
		$this->driver = new $driver_class($params);
		$this->storage_array = $this->driver->getData();
	}
	
	
	public function getMenu()
	{
		if(is_null($this->root_item)) {
			$this->root_item = new MenuItem($this, $this->storage_array, $this->user);
		}

		return $this->root_item;
	}

	
	public function setUser($user)
	{
		$this->user = $user;
	}
	
	
	public function ignorePrefix()
	{
		return $this->ignore_prefix;
	}
	
	
	public function getSelectedMenuItem()
	{
		if(!$this->selected_item) {
			$this->selected_item = $this->findMenuItem('selected', true);
		}
		
		return $this->selected_item;
	}
	
	
	public function getBreadcrumbs()
	{
		if($item = $this->getSelectedMenuItem()) {
			return $item->getBreadcrumbs();
		}

		return array();
	}
	
	
	public function findMenuItem($key, $value, $item = null)
	{
		if(is_null($item)) {
			$item = $this->getMenu();
		}
	
		if(isset($item[$key]) && $item[$key] === $value) {
			return $item;
		}
		
		if($item->hasChildren()) {
			foreach($item->getChildren() as $child) {
				if($item = $this->findMenuItem($key, $value, $child)) {
					return $item;
				}
			}
		}
		
		return null;
	}
	
	
	public function getFlatArray($min_depth = 0, $max_depth = -1, $cond = null)
	{
		$this->flat_list = null;
		$start = null;
		
		if(is_array($cond) && !empty($cond)) {
			$key = reset($cond);
			$start = $this->findMenuItem($key, $cond[$key]);
		}
		else {
			$start = $this->getMenu();
		}
		
		$this->createFlatArray($min_depth, $max_depth, $start);
		
		return $this->flat_list;
	}
	

	private function createFlatArray($min_depth = 0, $max_depth = -1, $item)
	{
		if($max_depth == -1 || $item->depth <= $max_depth) {
			if($item->depth >= $min_depth && $item->id != 0) {
				$this->flat_list[] = $item->asArray();
			}
			
			foreach($item->getChildren() as $child) {
				$this->createFlatArray($min_depth, $max_depth, $child);
			}
		}
	}
}

?>