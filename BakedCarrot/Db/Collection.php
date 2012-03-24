<?php
/**
 * BakedCarrot ORM collection
 *
 * @package BakedCarrot
 * @subpackage Db
 */
 
class Collection extends Query
{
	private $model_info = null;
	
	
	public function __construct($model)
	{
		$this->model = ucfirst($model);
		$this->model_info = Orm::modelInfo($model);
		
		// default query
		$this->select('*')->from($this->model_info['table']);
	}
	
	
	public function load($id = null)
	{	
		$object = null;
		
		if(is_numeric($id)) {
			$object = $this->
				reset()->
				select('*')->
				from($this->model_info['table'])->
				where($this->model_info['primary_key'] . ' = ?', array($id))->
				findOne();
		}
		
		if(!$object) {
			$object = $this->createObject();
		}
		
		return $object;
	}
	
	
	public function swap(Model $object1, Model $object2, $property)
	{
		$old_property = $object1[$property];
		
		$object1[$property] = $object2[$property];
		$object2[$property] = $old_property;
		
		$object1->store();
		$object2->store();
	}


	static function loadModel($clsss)
	{
		$class = ucfirst($clsss);
		
		if(!class_exists($class)) {
			if(is_file(MODELPATH . $class . EXT)) {
				require MODELPATH . $class . EXT;
			}
		}
		
		return $class;
	}

	
	final public function createObject($data = null)
	{
		$class = self::loadModel($this->model);

		if(class_exists($class)) {
			$object = new $class($this->model);
			
			if(!is_subclass_of($object, Orm::MODEL_BASE_CLASS) && get_class($object) != Orm::MODEL_BASE_CLASS) {
				throw new OrmException("Class $class is not a subclass of " . Orm::MODEL_BASE_CLASS);
			}
			
		}
		else {
			$class = Orm::MODEL_BASE_CLASS;
			$object = new $class($this->model);
		}
		
		if(is_array($data) && !empty($data)) {
			$object->hydrate($data);
			$object->runEvent('onLoad');
		}
		else {
			$object->runEvent('onCreate');
		}
		
		return $object;
	}


	final public function &info() 
	{
		return $this->model_info;
	}
	

	
}
