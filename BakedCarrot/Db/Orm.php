<?php
/**
 * BakedCarrot ORM module
 * 
 * @package BakedCarrot
 * @subpackage Db
 *
 * 
 */
class Orm
{
	const MODEL_CLASS_PREFIX = 'Model';
	const MODEL_BASE_CLASS = 'Model';
	const COLLECTION_CLASS_PREFIX = 'Collection';
	const COLLECTION_BASE_CLASS = 'Collection';
	
	private static $collections = array();
	private static $model_info = array();

	
	/**
	 * Creates a new object of given model
	 *
	 * @param string $name name of the model
	 * @return Collection $collection collection object
	 */
    public static function &collection($name)
	{
		if(!isset(self::$collections[$name])) {
			$class = self::COLLECTION_CLASS_PREFIX . ucfirst($name);
			
			if(!class_exists($class) && is_file(COLLPATH . $name . EXT)) {
				require COLLPATH . $name . EXT;
			}
			
			if(class_exists($class)) {
				$collection = new $class($name);
				
				if(!is_subclass_of($collection, self::COLLECTION_BASE_CLASS)) {
					throw new OrmException("Class $class is not subclass of " . self::COLLECTION_BASE_CLASS);
				}
			}
			else {
				$class_name = self::COLLECTION_BASE_CLASS;
				$collection = new $class_name($name);
			}
			
			return $collection;
		}
		
		self::$collections[$name]->reset();
		
		return self::$collections[$name];
	}
	

	/**
	 * Returns the information about given model
	 *
	 * @param string $model_name name of the model
	 * @return array
	 */
	public static function &modelInfo($model_name)
	{
		if(isset(self::$model_info[$model_name])) {
			return self::$model_info[$model_name];
		}
		
		$class = Collection::loadModel($model_name);
		
		if(class_exists($class)) {
			$object = new $class($model_name);
			
			if(!is_subclass_of($object, Orm::MODEL_BASE_CLASS) && get_class($object) != Orm::MODEL_BASE_CLASS) {
				throw new OrmException("Class $class is not a subclass of " . Orm::MODEL_BASE_CLASS);
			}
			
		}
		else {
			$class = Orm::MODEL_BASE_CLASS;
			$object = new $class($model_name);
		}
		
		self::$model_info[$model_name] = $object->info();
		
		unset($object);
		
		return self::$model_info[$model_name];
	}
}

