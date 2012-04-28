<?php
/**
 * BakedCarrot ORM module
 * 
 * @package BakedCarrot
 * @subpackage Db
 */
class Orm
{
	const ENTITY_BASE_CLASS = 'Entity';
	const COLLECTION_CLASS_PREFIX = 'Collection';
	const COLLECTION_BASE_CLASS = 'Collection';
	
	private static $entity_info = array();

	
	/**
	 * Creates a new object of given entity
	 *
	 * @param string $name name of the entity
	 * @return Collection $collection collection object
	 */
	public static function &collection($name)
	{
		$collection = null;
		$name = ucfirst($name);
		$class = self::COLLECTION_CLASS_PREFIX . $name;
		
		if(!class_exists($class) && is_file(COLLPATH . $name . EXT)) {
			require COLLPATH . $name . EXT;
		}
		
		if(class_exists($class)) {
			$collection = new $class($name);
			
			if(!is_subclass_of($collection, self::COLLECTION_BASE_CLASS)) {
				throw new BakedCarrotOrmException("Class $class is not subclass of " . self::COLLECTION_BASE_CLASS);
			}
		}
		else {
			$class_name = self::COLLECTION_BASE_CLASS;
			$collection = new $class_name($name);
		}
		
		return $collection;
	}
	

	/**
	 * Returns the information about given entity
	 *
	 * @param string $entity_name name of the entity
	 * @return array
	 */
	public static function &entityInfo($entity_name)
	{
		if(isset(self::$entity_info[$entity_name])) {
			return self::$entity_info[$entity_name];
		}
		
		$class = Collection::loadEntity($entity_name);
		
		if(class_exists($class)) {
			$object = new $class($entity_name);
			
			if(!is_subclass_of($object, Orm::ENTITY_BASE_CLASS) && get_class($object) != Orm::ENTITY_BASE_CLASS) {
				throw new BakedCarrotOrmException("Class $class is not a subclass of " . Orm::ENTITY_BASE_CLASS);
			}
			
		}
		else {
			$class = Orm::ENTITY_BASE_CLASS;
			$object = new $class($entity_name);
		}
		
		self::$entity_info[$entity_name] = $object->info();
		
		unset($object);
		
		return self::$entity_info[$entity_name];
	}
}

