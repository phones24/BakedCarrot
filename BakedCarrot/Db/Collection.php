<?php
/**
 * BakedCarrot ORM collection
 *
 * @package BakedCarrot
 * @subpackage Db
 *
 *
 * 
 */
 
class Collection extends Query
{
	//private $_table = null;
	//private $_primary_key = 'id';
	private $model_info = null;
	//private static $empty_models = null;
	
	
	public function __construct($model)
	{
		$this->model = $model;
		$this->model_info = Orm::modelInfo($model);
		
		//$object = $this->createObject();

		//$this->_table = $object->getTable();
		//$this->_primary_key = $object->getPrimaryKey();
		
		// default query
		$this->select('*')->from($this->model_info['table']);
	}
	
/*	
	public function getModelName()
	{
		return $this->model;
	}

	
	public function getTable()
	{
		return $this->_table;
	}
	
	
	public function getPrimaryKey()
	{
		return $this->_primary_key;
	}
*/
	
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
			$files_to_try[] = $class . EXT;
			$files_to_try[] = strtolower($class) . EXT;
			
			foreach($files_to_try as $file) {
				if(is_file(MODELPATH . $file)) {
					require MODELPATH . $file;
					break;
				}
			}
		}
		
		return $class;
	}

	
	final public function createObject($data = null)
	{
		$class = self::loadModel($this->model);
	
		//ucfirst($this->model);
/*		
		if(!class_exists($class)) {
			$files_to_try[] = $class . EXT;
			$files_to_try[] = strtolower($class) . EXT;
			
			foreach($files_to_try as $file) {
				if(is_file(MODELPATH . $file)) {
					require MODELPATH . $file;
					break;
				}
			}
		}
*/	
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
