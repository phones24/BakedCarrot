<?php
/**
 * BakedCarrot ORM collection
 *
 * @package BakedCarrot
 * @subpackage Db
 */
 
class Collection extends Query
{
	private $entity_info = null;
	
	
	public function __construct($entity_name)
	{
		$this->entity_name = ucfirst($entity_name);
		$this->entity_info = Orm::entityInfo($entity_name);
		$this->reset();
	}
	
	
	public function load($id = null)
	{	
		$object = null;
		
		if(mb_strlen($id) > 0) {
			$object = $this->where($this->entity_info['primary_key'] . ' = ?', array($id))->findOne();
		}
		
		if(!$object) {
			$object = $this->create();
		}
		
		return $object;
	}
	
	
	public function reset()
	{	
		parent::reset();
		
		// default query
		$this->select('*')->from($this->entity_info['table']);
		
		return $this;
	}
	
	
	public function swap(Entity $object1, Entity $object2, $property)
	{
		$old_property = $object1[$property];
		
		$object1[$property] = $object2[$property];
		$object2[$property] = $old_property;
		
		$object1->store();
		$object2->store();
	}


	static function loadEntity($clsss)
	{
		$class = ucfirst($clsss);
		
		if(!class_exists($class)) {
			if(is_file(ENTPATH . $class . EXT)) {
				require ENTPATH . $class . EXT;
			}
		}
		
		return $class;
	}

	
	final public function create($data = null)
	{
		$class = self::loadEntity($this->entity_name);

		if(class_exists($class)) {
			$object = new $class($this->entity_name);

			if(!is_subclass_of($object, Orm::ENTITY_BASE_CLASS) && get_class($object) != Orm::ENTITY_BASE_CLASS) {
				throw new BakedCarrotOrmException("Class $class is not a subclass of " . Orm::ENTITY_BASE_CLASS);
			}
		}
		else {
			$class = Orm::ENTITY_BASE_CLASS;
			$object = new $class($this->entity_name);
		}
		
		if(is_array($data) && !empty($data)) {
			$object->hydrate($data);
			$object->trigger('onLoad');
		}
		else {
			$object->trigger('onCreate');
		}
		
		return $object;
	}


	final public function &info() 
	{
		return $this->entity_info;
	}
}
