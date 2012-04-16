<?php
/**
 * BakedCarrot ORM model class
 * 
 * Provides entity-related ORM functionality. All user models should inherits this class.
 * The objects of this class managed my Collection and should not be created manually.
 *	
 *		<code>      
 *		$user = Orm::collection('user')->load(); // getting new user from collection
 *		$user->name = 'John'; // setting the property OOP-way
 *		$user['email'] = 'john@example.com'; // or as array
 *		$user->store(); // saving record to table "user"
 *		</code>      
 *
 * @package BakedCarrot
 * @subpackage Db
 * 
 */
class Model implements ArrayAccess
{
	private $storage = null;
	private $modified = false;
	private $modified_fields = array();
	private $collection = null;
	private $columns_meta = null;
	private $is_loaded = false;
	protected $model_name = null;
	protected $_table = null;
	protected $_primary_key = 'id';
	protected $_has_one = null;
	protected $_has_many = null;
	protected $_has_many_through = null;
	protected $_belongs_to = null;
	
	
	/**
	 * Creates a new entity object 
	 *
	 * @param string $name name of the table
	 * @param Collection $collection reference to owning collection 
	 * @return void
	 */
	public function __construct($model_name)
	{
		$this->model_name = ucfirst($model_name);
		
		if(!$this->_table) {
			$this->_table = self::createTableFromModelName($model_name);
		}
		
		$this->storage[$this->_primary_key] = 0;
	}


	/**
	 * Loads property data into object
	 *
	 * @param array $data data to be loaded
	 * @return void
	 */
	public function hydrate(array $data)
	{
		$this->storage = $data;
		
		foreach(array_keys($data) as $field) {
			$this->modified_fields[$field] = false;
		}
		
		$this->modified = false;
		$this->is_loaded = true; // assume the object is loaded from database
	}
	
	
	/**
	 * Checks if object actually loaded from database
	 *
	 * @return bool
	 */
	public function loaded()
	{
		return $this->is_loaded;
	}
	
	
	/**
	 * Checks if object or fields are modified
	 *
	 * @param string $filed name of the field
	 * @return bool
	 */
	public function modified($field = null)
	{
		if(is_string($field)) {
			return isset($this->modified_fields[$field]) ? $this->modified_fields[$field] : null;
		}
		
		return $this->modified;
	}
	
	
	/**
	 * Returns primary key value of object
	 *
	 * @return int
	 */
	public function getId()
	{
		return isset($this[$this->_primary_key]) ? $this[$this->_primary_key] : null;
	}
	
	
	/**
	 * Runs model event handler (if defined)
	 *
	 * @param string $event_name name of the event to be executed
	 * @return bool returns TRUE if event has been executed, FALSE otherwise
	 */
	public function runEvent($event_name) 
	{
		if(method_exists($this, $event_name)) {
			call_user_func(array($this, $event_name));
			
			return true;
		}
		
		return false;
	}
	

	/**
	 * Returns the information about the model
	 *
	 * @return array
	 */
	public function info() 
	{
		return array(
				'model'				=> $this->model_name,
				'table'				=> $this->_table,
				'primary_key'		=> $this->_primary_key,
				'has_one'			=> $this->_has_one,
				'has_many'			=> $this->_has_many,
				'has_many_through'	=> $this->_has_many_through,
				'belongs_to'		=> $this->_belongs_to,
			);
	}
	

	/**
	 * Export object's properties as array
	 *
	 * @return array
	 */
	public function export()
	{
		$to_export = array();
		
		foreach($this->storage as $key => $val) {
			if(!is_object($val) && !is_array($val)) {
				$to_export[$key] = $val;
			}
		}
	
		return $to_export;
	}
	
	
	/**
	 * Stores object to database
	 *
	 * @return array
	 */
	public function store()
	{
		$this->runEvent('onBeforeStore');
		
		// clearing the cache
		OrmCache::clearCacheForTable($this->_table);
		
		if($this->loaded()) {
			$this->runEvent('onBeforeUpdate');
			
			$this->storeUpdate();
			
			$this->runEvent('onAfterUpdate');
		}
		else {
			$this->runEvent('onBeforeInsert');

			$this->storeInsert();
			
			$this->runEvent('onAfterInsert');
		}
		
		$this->runEvent('onAfterStore');

		$this->modified = false;
		$this->is_loaded = true;
		
		return $this->getId();
	}
	

	/**
	 * Updating record from object's properties
	 *
	 * @return void
	 */
	private function storeUpdate()
	{
		$values = array();
		$real_field = Db::getColumns($this->_table);

		foreach($this->storage as $field_name => $field_val) {
			if($field_name == $this->_primary_key || !isset($real_field[$field_name])) {
				continue;
			}
			
			$values[$field_name] = $field_val;
		}
		
		Db::update($this->_table, 
				$values, 
				$this->_primary_key . ' = ?', 
				array($this->getId())
			);
	}
	
	
	/**
	 * Inserting new record using object's properties
	 *
	 * @return void
	 */
	private function storeInsert()
	{
		$values = array();
		$real_field = Db::getColumns($this->_table);

		foreach($this->storage as $field_name => $field_val) {
			if(!isset($real_field[$field_name])) {
				continue;
			}
			
			$values[$field_name] = $field_val;
		}
		
		$this[$this->_primary_key] = Db::insert($this->_table, $values);
	}
	
	
	/**
	 * Import data from array to object's properties
	 * @param array $source source array
	 * @param $fields comma separated fields to be imported
	 *
	 * @return void
	 */
	public function import(array $source, $fields = null)
	{
		if(!empty($fields)) {
			$fields = explode(',', $fields . ',');
			
			foreach($fields as $field) {
				$field = trim($field);
				if($field) {
					$value = isset($source[$field]) && !is_array($source[$field]) && !is_object($source[$field]) ? $source[$field] : null;
					$this->modified_fields[$field] = !isset($this->storage[$field]) || $this->storage[$field] !== $value;
					$this->storage[$field] = $value;
				}
			}
		}
		else {
			$imported = array_merge($this->storage, $source);
			foreach($imported as $field => $value) {
				$this->modified_fields[$field] = !isset($this->storage[$field]) || $this->storage[$field] !== $value;
			}
			$this->storage = $imported;
		}
	}


	/**
	 * Remove the underlying record and clear the properties
	 *
	 * @return void
	 */
	public function delete()
	{
		$this->runEvent('onBeforeDelete');

		// clearing the cache
		OrmCache::clearCacheForTable($this->_table);
		
		Db::delete($this->_table, $this->_primary_key . ' = ?', array($this->getId()));
		
		$this->runEvent('onAfterDelete');
		
		$this->storage = null;
		$this->modified = false;
		$this->modified_fields = array();
		$this->is_loaded = false;
	}


	/**
	 * Reload record from database
	 *
	 * @return bool returns TRUE if records actully reloaded, FALSE if not
	 */
	public function reload()
	{
		if(!$this->loaded()) {
			return false;
		}
		
		$row = Db::getRow('select * from ' . $this->_table . ' where ' . $this->_primary_key . ' = ?', array($this->getId()));
		
		if(!$row) {
			return false;
		}
		
		$this->hydrate($row);
		
		return true;
	}

	
	/**
	 * Returns the array of objects connected with given object through many-to-many relationship
	 * @param string $associated_class_name model name
	 * @param string $join_table name of the relationship table
	 * @param string $base_table_key name of the foreign key of base table
	 * @param string $associated_table_key name of the foreign key of associated table
	 * @param string $join_table_fields list of fields, that will be selected from the join table
	 *
	 * @return array 
	 */
	public function hasManyThrough($associated_class_name, $join_table = null, $base_table_key = null, $associated_table_key = null, $join_table_fields = null)
	{
		$associated_model_info = Orm::modelInfo($associated_class_name);
		$join_table = $join_table ? $join_table : self::createJoinTable($this->_table, $associated_model_info['table']);
		$base_table_key = $base_table_key ? $base_table_key : $this->_table . '_id';
		$associated_table_key = $associated_table_key ? $associated_table_key : $associated_model_info['table'] . '_id';
		
		$query = new Query($associated_class_name);
	
		if(is_array($join_table_fields)) {
			foreach($join_table_fields as &$field) {
				$field = $join_table . '.' . $field . ' as ' . Orm::JOIN_TABLE_FIELD_PREFIX . $field;
			}
			
			$query->select($associated_model_info['table'] . '.*, ' . implode(', ', $join_table_fields));
		}
		else {
			$query->select($associated_model_info['table'] . '.*');
		}
			
		$query->
			from($join_table . ', ' . $associated_model_info['table'])->
			where($associated_model_info['table'] . '.' . $associated_model_info['primary_key'] . ' = ' . $join_table . '.' . $associated_table_key . ' and ' . 
				$join_table . '.' . $base_table_key . ' = ?', array($this->getId()));

		return $query;
	}
	

	public function hasMany($associated_class_name, $foreign_key = null)
	{
		$foreign_key = $foreign_key ? $foreign_key : $this->_table . '_id';
		$collection = Orm::collection($associated_class_name)->where($foreign_key . ' = ?', array($this->getId()));

		return $collection;
	}
	
	
	public function hasOne($associated_class_name, $foreign_key = null)
	{
		$foreign_key = $foreign_key ? $foreign_key : $this->_table . '_id';
		$collection = Orm::collection($associated_class_name)->where($foreign_key . ' = ?', array($this->getId()));

		return $collection;
	}


	public function belongsTo($associated_class_name, $foreign_key = null)
	{
		$collection = Orm::collection($associated_class_name);

		$associated_model_info = $collection->info();
		$foreign_key = $foreign_key ? $foreign_key : $associated_model_info['table'] . '_id';
		
		$collection->where($associated_model_info['primary_key'] . ' = ?', array($this[$foreign_key]));

		return $collection;
	}
	

	public function owns(Model $object, $foreign_key = null)
	{
		$associated_model_info = $object->info();
		$foreign_key = $foreign_key ? $foreign_key : $this->_table . '_id';
		
		$sql = 'select count(*) from ' . $associated_model_info['table'] . ' where ' . $foreign_key . ' = ?';
		$result = Db::getCell($sql, array($this->getId()));

		return (bool)$result;
	}
	
	
	public function ownsThrough(Model $object, $join_table = null, $base_table_key = null, $associated_table_key = null)
	{
		$associated_model_info = $object->info();
		$associated_table_key = $associated_table_key ? $associated_table_key : $associated_model_info['table'] . '_id';
		$base_table_key = $base_table_key ? $base_table_key : $this->_table . '_id';
		$join_table = $join_table ? $join_table : self::createJoinTable($this->_table, $associated_model_info['table']);
		
		$sql = 'select count(*) from ' . $join_table . ' ' . 
				'where ' . $base_table_key . ' = ? and ' . $associated_table_key . ' = ?';
		
		$result = Db::getCell($sql, array($this->getId(), $object->getId()));
		
		return (bool)$result;
	}
	
	
	public function attach(Model $object, $foreign_key = null)
	{
		$associated_model_info = $object->info();
		$foreign_key = $foreign_key ? $foreign_key : $this->_table . '_id';

		OrmCache::clearCacheForTable($associated_model_info['table']);

		Db::update($associated_model_info['table'], array($foreign_key => $this->getId()), 'id = ?', array($object->getId()));
		
		return $this->reload();
	}

	
	public function attachThrough(Model $object, $join_table = null, $base_table_key = null, $associated_table_key = null, $join_table_fields = null)
	{
		$associated_model_info = $object->info();
		$associated_table_key = $associated_table_key ? $associated_table_key : $associated_model_info['table'] . '_id';
		$base_table_key = $base_table_key ? $base_table_key : $this->_table . '_id';
		$join_table = $join_table ? $join_table : self::createJoinTable($this->_table, $associated_model_info['table']);

		$data = array($base_table_key => $this->getId(), $associated_table_key => $object->getId());
		
		if(is_array($join_table_fields)) {
			foreach($join_table_fields as $field) {
				if(isset($object[Orm::JOIN_TABLE_FIELD_PREFIX . $field])) {
					$data = array_merge($data, array($field => $object[Orm::JOIN_TABLE_FIELD_PREFIX . $field]));
				}
			}
		}
		
		OrmCache::clearCacheForTable($join_table);
		
		Db::insert($join_table, $data);
		
		return true;
	}
	
	
	public function detach(Model $object, $foreign_key = null)
	{
		$associated_model_info = $object->info();
		$foreign_key = $foreign_key ? $foreign_key : $this->_table . '_id';

		OrmCache::clearCacheForTable($associated_model_info['table']);
		
		Db::update($associated_model_info['table'], array($foreign_key => null), 'id = ?', array($object->getId()));
		
		return $this->reload();
	}

	
	public function detachThrough(Model $object, $join_table = null, $base_table_key = null, $associated_table_key = null)
	{
		$associated_model_info = $object->info();
		$associated_table_key = $associated_table_key ? $associated_table_key : $associated_model_info['table'] . '_id';
		$base_table_key = $base_table_key ? $base_table_key : $this->_table . '_id';
		$join_table = $join_table ? $join_table : self::createJoinTable($this->_table, $associated_model_info['table']);
	
		OrmCache::clearCacheForTable($join_table);

		return Db::delete($join_table, $base_table_key . ' = ? and ' . $associated_table_key . ' = ?', array($this->getId(), $object->getId()));
	}
	
	
	public function clearRelations($associated_class_name, $join_table = null, $base_table_key = null)
	{
		$associated_model_info = Orm::modelInfo($associated_class_name);
		$base_table_key = $base_table_key ? $base_table_key : $this->_table . '_id';
		$join_table = $join_table ? $join_table : self::createJoinTable($this->_table, $associated_model_info['table']);
		
		OrmCache::clearCacheForTable($join_table);

		return Db::delete($join_table, $base_table_key . ' = ?', array($this->getId()));
	}
	

	public function offsetSet($offset, $value) 
	{
		$this->modified = true;
		$this->modified_fields[$offset] = !isset($this->storage[$offset]) || $this->storage[$offset] !== $value;
		
		if(is_null($offset)) {
			$this->storage[] = $value;
		} 
		else {
			$this->storage[$offset] = $value;
		}
	}
	
	
	public function offsetExists($offset) 
	{
		return isset($this->storage[$offset]);
	}
	
	
	public function offsetUnset($offset) 
	{
		unset($this->modified_fields[$offset]);
		unset($this->storage[$offset]);
	}
	
	
	public function offsetGet($offset) 
	{
		return isset($this->storage[$offset]) ? $this->storage[$offset] : null;
	}
	
	
	public function __isset($key)
	{
		return isset($this->_has_many[$key]) || isset($this->_has_many_through[$key]) || 
				isset($this->_belongs_to[$key]) || isset($this->_has_one[$key]) || 
				isset($this->storage[$key]);
	}
	
	
	public function __get($key)
	{
		if(isset($this->_has_many[$key]) && isset($this->_has_many[$key]['model'])) {
			$result = $this->hasMany($this->_has_many[$key]['model'], isset($this->_has_many[$key]['foreign_key']) ? $this->_has_many[$key]['foreign_key'] : null);
			
			foreach(array('order', 'limit', 'offset', 'cached') as $avail_params) {
				if(isset($this->_has_many[$key][$avail_params])) {
					call_user_func(array($result, $avail_params), $this->_has_many[$key][$avail_params]);
				}
			}
			
			return $result;
		}
		elseif(isset($this->_has_many_through[$key]) && isset($this->_has_many_through[$key]['model'])) {
			$result = $this->hasManyThrough($this->_has_many_through[$key]['model'], 
					isset($this->_has_many_through[$key]['join_table']) ? $this->_has_many_through[$key]['join_table'] : null,
					isset($this->_has_many_through[$key]['base_table_key']) ? $this->_has_many_through[$key]['base_table_key'] : null,
					isset($this->_has_many_through[$key]['associated_table_key']) ? $this->_has_many_through[$key]['associated_table_key'] : null,
					isset($this->_has_many_through[$key]['join_table_fields']) ? $this->_has_many_through[$key]['join_table_fields'] : null
				);
			
			foreach(array('order', 'limit', 'offset', 'cached') as $avail_params) {
				if(isset($this->_has_many_through[$key][$avail_params])) {
					call_user_func(array($result, $avail_params), $this->_has_many_through[$key][$avail_params]);
				}
			}
			
			return $result;
		}
		elseif(isset($this->_has_one[$key]) && isset($this->_has_one[$key]['model'])) {
			$result = $this->hasOne($this->_has_one[$key]['model'], isset($this->_has_one[$key]['foreign_key']) ? $this->_has_one[$key]['foreign_key'] : null);
			
			foreach(array('order', 'offset', 'cached') as $avail_params) {
				if(isset($this->_has_one[$key][$avail_params])) {
					call_user_func(array($result, $avail_params), $this->_has_one[$key][$avail_params]);
				}
			}
			
			return $result;
		}
		elseif(isset($this->_belongs_to[$key]) && isset($this->_belongs_to[$key]['model'])) {
			$result = $this->belongsTo($this->_belongs_to[$key]['model'], isset($this->_belongs_to[$key]['foreign_key']) ? $this->_belongs_to[$key]['foreign_key'] : null);
			
			foreach(array('order', 'offset', 'cached') as $avail_params) {
				if(isset($this->_belongs_to[$key][$avail_params])) {
					call_user_func(array($result, $avail_params), $this->_belongs_to[$key][$avail_params]);
				}
			}
			
			return $result;
		}
		else {
			return isset($this->storage[$key]) ? $this->storage[$key] : null;
		}
	}
	
	
	public function __set($key, $val)
	{
		$this->modified = true;
		
		$model_name = null;
		$model_info = null;
		
		if(is_object($val) && is_a($val, Orm::MODEL_BASE_CLASS)) {
			$model_info = $val->info();
			$model_name = $model_info['model'];
		}
			
		if(isset($this->_has_one[$key]) && isset($this->_has_one[$key]['model'])) {
			if(!isset($model_info)) {
				$model_info = Orm::modelInfo($this->_has_one[$key]['model']);
			}
			
			$field = isset($this->_has_one[$key]['foreign_key']) ? $this->_has_one[$key]['foreign_key'] : $this->_table . '_id';
			$val->storage[$field] = (is_object($val) && $model_info['model'] == $this->_has_one[$key]['model']) ? $this->getId() : $val;
			$val->store();
		}
		elseif(isset($this->_belongs_to[$key]) && isset($this->_belongs_to[$key]['model'])) {
			if(!isset($model_info)) {
				$model_info = Orm::modelInfo($this->_belongs_to[$key]['model']);
			}
			
			$field = isset($this->_belongs_to[$key]['foreign_key']) ? $this->_belongs_to[$key]['foreign_key'] : $model_info['table'] . '_id';
			$this->storage[$field] = (is_object($val) && $model_info['model'] == $this->_belongs_to[$key]['model']) ? $val->getId() : $val;
		}
		elseif(isset($this->_has_many_through[$key]) && isset($this->_has_many_through[$key]['model'])) {
			$this->clearRelations(
					$this->_has_many_through[$key]['model'],
					isset($this->_has_many_through[$key]['join_table']) ? $this->_has_many_through[$key]['join_table'] : null,
					isset($this->_has_many_through[$key]['base_table_key']) ? $this->_has_many_through[$key]['base_table_key'] : null
				);
			
			if(is_array($val)) {
				foreach($val as $num => $object) {
					if(is_numeric($object)) {
						$model_info = Orm::modelInfo($this->_has_many_through[$key]['model']);
						$object = Orm::collection($this->_has_many_through[$key]['model'])->create(array($model_info['primary_key'] => $object));
					}
					
					if(!is_a($object, Orm::MODEL_BASE_CLASS)) {
						continue;
					}
					
					if(!$model_name) {
						$model_info = $object->info();
						$model_name = $model_info['model'];
					}
			
					if($model_name == $this->_has_many_through[$key]['model']) {
						$this->attachThrough(
								$object, 
								isset($this->_has_many_through[$key]['join_table']) ? $this->_has_many_through[$key]['join_table'] : null,
								isset($this->_has_many_through[$key]['base_table_key']) ? $this->_has_many_through[$key]['base_table_key'] : null,
								isset($this->_has_many_through[$key]['associated_table_key']) ? $this->_has_many_through[$key]['associated_table_key'] : null,
								isset($this->_has_many_through[$key]['join_table_fields']) ? $this->_has_many_through[$key]['join_table_fields'] : null
							);
					}
				}
			}
		}
		else {
			$this->modified_fields[$key] = !isset($this->storage[$key]) || $this->storage[$key] !== $val;
			$this->storage[$key] = $val;
		}
	}
	

	public static function createJoinTable($table1, $table2)
	{
		$tables = array($table1, $table2);
		sort($tables);
		
		return implode('_', $tables);
	}


	public static function createTableFromModelName($model_name)
	{
		return strtolower(preg_replace("/([a-z])([A-Z])/", "\\1_\\2", $model_name));
	}
	
}
