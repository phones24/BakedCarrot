<?php
/**
 * BakedCarrot ORM collection
 * Collection class represent TABLE of database, that holds rows represented by Entity class
 *
 * @package BakedCarrot
 * @subpackage Db
 */
 
class Collection extends Query
{
	/**
	 * Creates new collection that holds entities of $entity_name class
	 *
	 * @param string $entity_name name of the entity
	 */
	public function __construct($entity_name)
	{
		$this->entity_name = ucfirst($entity_name);
		$this->entity_info = Orm::entityInfo($entity_name);
		$this->reset();
	}


	/**
	 * Returns the object by its ID or creates new object if it's not found
	 *
	 * @param null $id
	 * @return bool|null
	 */
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


	/**
	 * Resets internal query
	 *
	 * @return Query
	 */
	public function reset()
	{	
		parent::reset();

		return $this->table($this->entity_info['table']);
	}


	/**
	 * Swaps field values of two objects
	 *
	 * @param Entity $object1
	 * @param Entity $object2
	 * @param string $field name of the field to be swapped
	 */
	public function swap(Entity $object1, Entity $object2, $field)
	{
		$old_field = $object1[$field];
		
		$object1[$field] = $object2[$field];
		$object2[$field] = $old_field;
		
		$object1->store();
		$object2->store();
	}


	/**
	 * Loads the class of an entity
	 *
	 * @static
	 * @param $clsss
	 * @return string
	 */
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


	/**
	 * Creates an empty entity, optionally hydrate it with $data
	 *
	 * @param null $data
	 * @return mixed
	 * @throws BakedCarrotOrmException
	 */
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
		}

		$object->trigger('onLoad');
		
		return $object;
	}


	final public function &info() 
	{
		return $this->entity_info;
	}
}
