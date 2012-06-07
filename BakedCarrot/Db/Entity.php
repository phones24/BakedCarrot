<?php
/**
 * BakedCarrot ORM entity class
 * 
 * Provides entity-related ORM functionality. All user entities should inherits this class.
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
class Entity implements ArrayAccess
{
	const QUEUE_PRE_STORE = 0;
	const QUEUE_POST_STORE = 1;
	const QUEUE_TYPE_SET_VAL = 0;
	const QUEUE_TYPE_EXEC = 1;

	private $storage = null;
	private $modified = false;
	private $modified_fields = array();
	private $collection = null;
	private $columns_meta = null;
	private $is_loaded = false;
	private $queue = null;
	protected $entity_name = null;
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
	public function __construct($entity_name)
	{
		$this->entity_name = ucfirst($entity_name);
		
		if(!$this->_table) {
			$this->_table = self::createTableFromEntityName($entity_name);
		}
		
		$this->setFieldValue($this->_primary_key, 0);
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
	 * Runs entity event handler (if defined)
	 *
	 * @param string $event_name name of the event to be executed
	 * @return bool returns TRUE if event has been executed, FALSE otherwise
	 */
	public function trigger($event_name) 
	{
		if(method_exists($this, $event_name)) {
			call_user_func(array($this, $event_name));
			
			return true;
		}
		
		return false;
	}
	

	/**
	 * Returns the information about the entity
	 *
	 * @return array
	 */
	public function info() 
	{
		return array(
				'entity'			=> $this->entity_name,
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
	 * Sets the field value in a proper way
	 * This is third method of setting the value of the field, beside those mentioned in the class description
	 *
	 * @param string $key name of the field
	 * @param mixed $value value of the field
	 * @return void
	 */
	public function setFieldValue($key, $value)
	{
		if(is_null($key)) {
			return;
		}

		if(method_exists($this, 'onSetValue')) {
			$result = $this->onSetValue($key, $value);
			
			if($result === false) {
				return;
			}
		}
		
		$this->modified_fields[$key] = !isset($this->storage[$key]) || $this->storage[$key] !== $value;
		$this->storage[$key] = $value;
		
		if(!$this->modified && $this->modified_fields[$key]) {
			$this->modified = true;
		}
	}


	/**
	 * Returns list of fields cf the entity
	 *
	 * @return array
	 */
	public function getFields()
	{
		return array_keys($this->storage);
	}
	

	/**
	 * Checks if the field is really exists (even if it sets to NULL)
	 *
	 * @param $field
	 * @return bool
	 */
	public function fieldExists($field)
	{
		return array_key_exists($field, $this->storage);
	}
	

	
	/**
	 * Filter value before inserting into database
	 *
	 * @param $var
	 * @return bool
	 */
	private function filterValue($var)
	{
		if(is_object($var) && is_array($var)) {
			return false;
		}
			
		return true;
	}
	
	
	/**
	 * Stores object to database
	 *
	 * @return array
	 */
	public function store()
	{
		$this->trigger('onBeforeStore');
		
		// clearing the cache
		OrmCache::clearCacheForTable($this->_table);
		
		$this->runJobs(self::QUEUE_PRE_STORE);

		if($this->loaded()) {
			$this->trigger('onBeforeUpdate');
			$this->storeUpdate();
			$this->trigger('onAfterUpdate');
		}
		else {
			$this->trigger('onBeforeInsert');
			$this->storeInsert();
			$this->trigger('onAfterInsert');
		}
		
		$this->runJobs(self::QUEUE_POST_STORE);

		$this->trigger('onAfterStore');
		
		$this->modified = false;
		$this->is_loaded = true;
		
		return $this->getId();
	}
	

	/**
	 * Updating actual record from object's properties
	 *
	 * @return void
	 */
	private function storeUpdate()
	{
		$values = array();

		foreach($this->storage as $field_name => $field_val) {
			if($field_name == $this->_primary_key) {
				continue;
			}
			
			$values[$field_name] = $field_val;
		}

		$values = array_filter($values, array($this, 'filterValue'));
		
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
		$values = array_filter($this->storage, array($this, 'filterValue'));
		
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
					$value = isset($source[$field]) ? $source[$field] : null;
					$this->setFieldValue($field, $value);
				}
			}
		}
		else {
			$imported = array_merge($this->storage, $source);
			foreach($imported as $field => $value) {
				$this->modified_fields[$field] = !isset($this->storage[$field]) || $this->storage[$field] !== $value;
				
				if(!$this->modified && $this->modified_fields[$field]) {
					$this->modified = true;
				}
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
		$this->trigger('onBeforeDelete');

		// clearing the cache
		OrmCache::clearCacheForTable($this->_table);
		
		Db::delete($this->_table, $this->_primary_key . ' = ?', array($this->getId()));
		
		$this->trigger('onAfterDelete');
		$this->storage = null;
		$this->modified = false;
		$this->modified_fields = array();
		$this->is_loaded = false;
	}


	/**
	 * Reload record from database
	 *
	 * @return bool returns TRUE if records actually reloaded, FALSE if not
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
	 * Returns the collection of objects connected with given object through many-to-many relationship
	 * @param string $associated_class_name entity name
	 * @param string $join_table name of the relationship table
	 * @param string $base_table_key name of the foreign key of base table
	 * @param string $associated_table_key name of the foreign key of associated table
	 * @param string $join_table_fields list of fields, that will be selected from the join table
	 *
	 * @return Collection
	 */
	public function hasManyThrough($associated_class_name, $join_table = null, $base_table_key = null, $associated_table_key = null, $join_table_fields = null)
	{
		$associated_entity_info = Orm::entityInfo($associated_class_name);
		$join_table = $join_table ? $join_table : self::createJoinTable($this->_table, $associated_entity_info['table']);
		$base_table_key = $base_table_key ? $base_table_key : $this->_table . '_id';
		$associated_table_key = $associated_table_key ? $associated_table_key : $associated_entity_info['table'] . '_id';
		
		$collection = Orm::collection($associated_class_name);
	
		if(is_array($join_table_fields)) {
			foreach($join_table_fields as &$field) {
				$field = $join_table . '.' . $field . ' as ' . $field;
			}
			
			$collection->select($associated_entity_info['table'] . '.*, ' . implode(', ', $join_table_fields));
		}
		else {
			$collection->select($associated_entity_info['table'] . '.*');
		}
			
		$collection->table($join_table . ', ' . $associated_entity_info['table'])->
			where($associated_entity_info['table'] . '.' . $associated_entity_info['primary_key'] . ' = ' . $join_table . '.' . $associated_table_key . ' and ' . 
				$join_table . '.' . $base_table_key . ' = ?', array($this->getId()));

		return $collection;
	}


	/**
	 * Returns the collection of objects related to current with one-to-many relationship
	 *
	 * @param string $associated_class_name class name of related object
	 * @param null $foreign_key use this foreign key name instead of default "tablename_id"
	 * @return Collection
	 */
	public function hasMany($associated_class_name, $foreign_key = null)
	{
		$foreign_key = $foreign_key ? $foreign_key : $this->_table . '_id';
		$collection = Orm::collection($associated_class_name)->where($foreign_key . ' = ?', array($this->getId()));

		return $collection;
	}


	/**
	 * Returns the collection of objects related to current with one-to-one relationship
	 *
	 * @param string $associated_class_name class name of related object
	 * @param null $foreign_key use this foreign key name instead of default "tablename_id"
	 * @return Collection
	 */
	public function hasOne($associated_class_name, $foreign_key = null)
	{
		$foreign_key = $foreign_key ? $foreign_key : $this->_table . '_id';
		$collection = Orm::collection($associated_class_name)->where($foreign_key . ' = ?', array($this->getId()));

		return $collection;
	}


	/**
	 * Returns the collection of objects related to current with one-to-one relationship (reversed)
	 *
	 * @param string $associated_class_name class name of related object
	 * @param null $foreign_key use this foreign key name instead of default "tablename_id"
	 * @return Collection
	 */
	public function belongsTo($associated_class_name, $foreign_key = null)
	{
		$collection = Orm::collection($associated_class_name);

		$associated_entity_info = $collection->info();
		$foreign_key = $foreign_key ? $foreign_key : $associated_entity_info['table'] . '_id';
		
		$collection->where($associated_entity_info['primary_key'] . ' = ?', array($this[$foreign_key]));

		return $collection;
	}


	/**
	 * @param Entity $object
	 * @param null $foreign_key
	 * @return bool
	 */
	public function owns(Entity $object, $foreign_key = null)
	{
		$associated_entity_info = $object->info();
		$foreign_key = $foreign_key ? $foreign_key : $this->_table . '_id';
		
		$sql = 'select count(*) from ' . $associated_entity_info['table'] . ' where ' . $foreign_key . ' = ?';
		$result = Db::getCell($sql, array($this->getId()));

		return (bool)$result;
	}


	/**
	 * @param Entity $object
	 * @param null $join_table
	 * @param null $base_table_key
	 * @param null $associated_table_key
	 * @return bool
	 */
	public function ownsThrough(Entity $object, $join_table = null, $base_table_key = null, $associated_table_key = null)
	{
		$associated_entity_info = $object->info();
		$associated_table_key = $associated_table_key ? $associated_table_key : $associated_entity_info['table'] . '_id';
		$base_table_key = $base_table_key ? $base_table_key : $this->_table . '_id';
		$join_table = $join_table ? $join_table : self::createJoinTable($this->_table, $associated_entity_info['table']);
		
		$sql = 'select count(*) from ' . $join_table . ' where ' . $base_table_key . ' = ? and ' . $associated_table_key . ' = ?';
		$result = Db::getCell($sql, array($this->getId(), $object->getId()));
		
		return (bool)$result;
	}


	/**
	 * Attach one object to another with one-to-many relationship
	 * @param Entity $object
	 * @param string|null $foreign_key
	 * @return bool
	 */
	public function attach(Entity $object, $foreign_key = null)
	{
		if($object->owns($this)) {
			return false;
		}

		$associated_entity_info = $object->info();
		$foreign_key = $foreign_key ? $foreign_key : $this->_table . '_id';
		
		OrmCache::clearCacheForTable($associated_entity_info['table']);

		Db::update($associated_entity_info['table'], array($foreign_key => $this->getId()), 'id = ?', array($object->getId()));
		
		return $this->reload();
	}


	/**
	 * Attach object to current object with many-to-many relationship
	 *
	 * @param Entity $object
	 * @param null $join_table
	 * @param null $base_table_key
	 * @param null $associated_table_key
	 * @param null $join_table_fields
	 * @return bool
	 */
	public function attachThrough(Entity $object, $join_table = null, $base_table_key = null, $associated_table_key = null, $join_table_fields = null)
	{
		$associated_entity_info = $object->info();
		$associated_table_key = $associated_table_key ? $associated_table_key : $associated_entity_info['table'] . '_id';
		$base_table_key = $base_table_key ? $base_table_key : $this->_table . '_id';
		$join_table = $join_table ? $join_table : self::createJoinTable($this->_table, $associated_entity_info['table']);

		// construct data to insert (or update)
		$data = array($base_table_key => $this->getId(), $associated_table_key => $object->getId());
		if(is_array($join_table_fields)) {
			foreach($join_table_fields as $field) {
				if($object->fieldExists($field)) {
					$data = array_merge($data, array($field => $object[$field]));
				}
			}
		}
		
		if($this->ownsThrough($object, $join_table, $base_table_key, $associated_table_key)) {
			// update, if join table fields exists
			if(!empty($join_table_fields)) {
				Db::update(
						$join_table, 
						$data, 
						$associated_table_key . ' = ? and ' . $base_table_key . ' = ?', 
						array($object->getId(), $this->getId())
					);
			}
			
			return false;
		}
		
		OrmCache::clearCacheForTable($join_table);
		
		Db::insert($join_table, $data);
		
		return true;
	}


	/**
	 * Remove relation between objects related with one-to-many relationship
	 *
	 * @param Entity $object
	 * @param null $foreign_key
	 * @return bool
	 */
	public function detach(Entity $object, $foreign_key = null)
	{
		if(!$this->owns($object)) {
			return false;
		}
		
		$associated_entity_info = $object->info();
		$foreign_key = $foreign_key ? $foreign_key : $this->_table . '_id';

		OrmCache::clearCacheForTable($associated_entity_info['table']);
		
		Db::update($associated_entity_info['table'], array($foreign_key => null), 'id = ?', array($object->getId()));
		
		return $this->reload();
	}


	/**
	 * Remove relation between objects related with many-to-many relationship
	 *
	 * @param Entity $object
	 * @param null $join_table
	 * @param null $base_table_key
	 * @param null $associated_table_key
	 * @return mixed
	 */
	public function detachThrough(Entity $object, $join_table = null, $base_table_key = null, $associated_table_key = null)
	{
		$associated_entity_info = $object->info();
		$associated_table_key = $associated_table_key ? $associated_table_key : $associated_entity_info['table'] . '_id';
		$base_table_key = $base_table_key ? $base_table_key : $this->_table . '_id';
		$join_table = $join_table ? $join_table : self::createJoinTable($this->_table, $associated_entity_info['table']);
	
		OrmCache::clearCacheForTable($join_table);

		return Db::delete($join_table, $base_table_key . ' = ? and ' . $associated_table_key . ' = ?', array($this->getId(), $object->getId()));
	}


	/**
	 * Remove all many-to-many relations between current object and other object of given class name
	 *
	 * @param string $associated_class_name
	 * @param null $join_table
	 * @param null $base_table_key
	 * @return mixed
	 */
	public function clearRelations($associated_class_name, $join_table = null, $base_table_key = null)
	{
		$associated_entity_info = Orm::entityInfo($associated_class_name);
		$base_table_key = $base_table_key ? $base_table_key : $this->_table . '_id';
		$join_table = $join_table ? $join_table : self::createJoinTable($this->_table, $associated_entity_info['table']);
		
		OrmCache::clearCacheForTable($join_table);

		return Db::delete($join_table, $base_table_key . ' = ?', array($this->getId()));
	}


	/**
	 * Remove many-to-many relations with objects that not in $related_objects array
	 *
	 * @param $associated_class_name
	 * @param array $related_objects
	 * @param null $join_table
	 * @param null $base_table_key
	 * @param null $associated_table_key
	 */
	public function clearUnrelated($associated_class_name, array $related_objects, $join_table = null, $base_table_key = null, $associated_table_key = null)
	{
		$ids = array();
		$associated_entity_info = Orm::entityInfo($associated_class_name);

		foreach($related_objects as $object) {
			$ids[] = $object->getId();
		}
		
		$base_table_key = $base_table_key ? $base_table_key : $this->_table . '_id';
		$join_table = $join_table ? $join_table : self::createJoinTable($this->_table, $associated_entity_info['table']);
		$associated_table_key = $associated_table_key ? $associated_table_key : $associated_entity_info['table'] . '_id';

		OrmCache::clearCacheForTable($join_table);

		$rows = Db::getAll('select ' . $associated_table_key . ' from ' . $join_table . ' where ' . $base_table_key . ' = ?', array($this->getId()));
		foreach($rows as $row) {
			if(!in_array($row[$associated_table_key], $ids)) {
				Db::delete($join_table, $associated_table_key . ' = ? and ' . $base_table_key . ' = ?', array($row[$associated_table_key], $this->getId()));
			}
		}
	}


	/**
	 * Used to implement ArrayAccess
	 *
	 * @param $offset
	 * @param $value
	 */
	public function offsetSet($offset, $value)
	{
		$this->setFieldValue($offset, $value);
	}


	/**
	 * Used to implement ArrayAccess
	 *
	 * @param $offset
	 * @return bool
	 */
	public function offsetExists($offset)
	{
		return isset($this->storage[$offset]);
	}


	/**
	 * Used to implement ArrayAccess
	 *
	 * @param $offset
	 */
	public function offsetUnset($offset)
	{
		unset($this->modified_fields[$offset]);
		unset($this->storage[$offset]);
	}


	/**
	 * Used to implement ArrayAccess
	 *
	 * @param $offset
	 * @return null
	 */
	public function offsetGet($offset)
	{
		return isset($this->storage[$offset]) ? $this->storage[$offset] : null;
	}


	/**
	 * @param $key
	 * @return bool
	 */
	public function __isset($key)
	{
		return isset($this->_has_many[$key]) || isset($this->_has_many_through[$key]) || 
				isset($this->_belongs_to[$key]) || isset($this->_has_one[$key]) || 
				isset($this->storage[$key]);
	}


	/**
	 * @param $key
	 * @return \Collection|null
	 */
	public function __get($key)
	{
		if(isset($this->_has_many[$key]) && isset($this->_has_many[$key]['entity'])) {
			$result = $this->hasMany($this->_has_many[$key]['entity'], isset($this->_has_many[$key]['foreign_key']) ? $this->_has_many[$key]['foreign_key'] : null);
			
			foreach(array('order', 'limit', 'offset', 'cached') as $avail_params) {
				if(isset($this->_has_many[$key][$avail_params])) {
					call_user_func(array($result, $avail_params), $this->_has_many[$key][$avail_params]);
				}
			}
			
			return $result;
		}
		elseif(isset($this->_has_many_through[$key]) && isset($this->_has_many_through[$key]['entity'])) {
			$result = $this->hasManyThrough($this->_has_many_through[$key]['entity'], 
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
		elseif(isset($this->_has_one[$key]) && isset($this->_has_one[$key]['entity'])) {
			$result = $this->hasOne($this->_has_one[$key]['entity'], isset($this->_has_one[$key]['foreign_key']) ? $this->_has_one[$key]['foreign_key'] : null);
			
			foreach(array('order', 'offset', 'cached') as $avail_params) {
				if(isset($this->_has_one[$key][$avail_params])) {
					call_user_func(array($result, $avail_params), $this->_has_one[$key][$avail_params]);
				}
			}
			
			return $result;
		}
		elseif(isset($this->_belongs_to[$key]) && isset($this->_belongs_to[$key]['entity'])) {
			$result = $this->belongsTo($this->_belongs_to[$key]['entity'], isset($this->_belongs_to[$key]['foreign_key']) ? $this->_belongs_to[$key]['foreign_key'] : null);
			
			foreach(array('order', 'offset', 'cached') as $avail_params) {
				if(isset($this->_belongs_to[$key][$avail_params])) {
					call_user_func(array($result, $avail_params), $this->_belongs_to[$key][$avail_params]);
				}
			}
			
			return $result;
		}
		else {
			return $this->fieldExists($key) ? $this->storage[$key] : null;
		}
	}


	/**
	 * @param $key
	 * @param $value
	 * @return mixed
	 */
	public function __set($key, $value)
	{
		if(isset($this->_has_one[$key]) && isset($this->_has_one[$key]['entity']) && is_object($value) && is_a($value, Orm::ENTITY_BASE_CLASS)) {
			$entity_info = $value->info();

			// creating field name	
			$field = isset($this->_has_one[$key]['foreign_key']) ? $this->_has_one[$key]['foreign_key'] : $this->_table . '_id';
			
			if($entity_info['entity'] == $this->_has_one[$key]['entity']) {
				
				/* @todo remove this \|/ condition! 
				*/
				
				//only update the field if it's different from $value
				//if(!$value->fieldExists($field) || $value[$field] != $this->getId()) {
					$this->addJob(self::QUEUE_POST_STORE, self::QUEUE_TYPE_SET_VAL, array(
							'object_left' => $value, 
							'field_left' => $field, 
							'object_right' => $this, 
							'field_right' => $this->_primary_key
						));
						
					$this->addJob(self::QUEUE_POST_STORE, self::QUEUE_TYPE_EXEC, array(
							 'object' => $value, 
							 'method' => 'store'
						));
				//}
			}
		}
		elseif(isset($this->_belongs_to[$key]) && isset($this->_belongs_to[$key]['entity'])) {
			// getting entity info
			$entity_info = Orm::entityInfo($this->_belongs_to[$key]['entity']);
			
			// creating field name
			$field = isset($this->_belongs_to[$key]['foreign_key']) ? $this->_belongs_to[$key]['foreign_key'] : $entity_info['table'] . '_id';
			
			// going on if the value is an object based on Entity class
			if($entity_info['entity'] == $this->_belongs_to[$key]['entity'] && is_object($value) && is_a($value, Orm::ENTITY_BASE_CLASS)) {
				// just setting the id if the object is already loaded
				if($value->loaded()) {
					$this->setFieldValue($field, $value->getId());
				}
				else {
					// we got a new object - adding the job to save it and set the realtion after
					$this->addJob(self::QUEUE_PRE_STORE, self::QUEUE_TYPE_EXEC, array(
							 'object' => $value, 
							 'method' => 'store'
						));

					$this->addJob(self::QUEUE_PRE_STORE, self::QUEUE_TYPE_SET_VAL, array(
							'object_left' => $this, 
							'field_left' => $field, 
							'object_right' => $value, 
							'field_right' => $entity_info['primary_key']
						));
				}
			}
			else {
				// we got a numeric ID, just setting it
				$this->setFieldValue($field, $value);
			}
		}
		elseif(isset($this->_has_many[$key]) && isset($this->_has_many[$key]['entity'])) {
			if(!is_array($value)) {
				return;
			}
			
			// creating field name	
			$field = isset($this->_has_many[$key]['foreign_key']) ? $this->_has_many[$key]['foreign_key'] : $this->_table . '_id';
		
			foreach($value as $num => $object) {
				$entity_info = null;

				// create fake object if we got array of IDs as $value
				if(is_numeric($object)) { 
					$entity_info = Orm::entityInfo($this->_has_many[$key]['entity']);
					$object = Orm::collection($this->_has_many[$key]['entity'])->create(array($entity_info['primary_key'] => $object));
				}
				
				if($entity_info['entity'] == $this->_has_many[$key]['entity']) {
					//only update the field if it's different from $value
					if(!isset($object[$field]) || $object[$field] != $this->getId()) {
						$this->addJob(self::QUEUE_POST_STORE, self::QUEUE_TYPE_SET_VAL, array(
								'object_left' => $object, 
								'field_left' => $field, 
								'object_right' => $this, 
								'field_right' => $this->_primary_key
							));

						$this->addJob(self::QUEUE_POST_STORE, self::QUEUE_TYPE_EXEC, array(
								 'object' => $object, 
								 'method' => 'store'
							));
					}
				}
			}
		}
		elseif(isset($this->_has_many_through[$key]) && isset($this->_has_many_through[$key]['entity'])) {
			$join_table = isset($this->_has_many_through[$key]['join_table']) ? $this->_has_many_through[$key]['join_table'] : null;
			$base_table_key = isset($this->_has_many_through[$key]['base_table_key']) ? $this->_has_many_through[$key]['base_table_key'] : null;
			$associated_table_key = isset($this->_has_many_through[$key]['associated_table_key']) ? $this->_has_many_through[$key]['associated_table_key'] : null;
			$join_table_fields = isset($this->_has_many_through[$key]['join_table_fields']) ? $this->_has_many_through[$key]['join_table_fields'] : null;
			
			// assume we have an array of objects (or just IDs)
			if(is_array($value)) {
				$related_objects = array();
				$entity_info = null;
				
				foreach($value as $object) {
					// create fake object if we got array of IDs as $value
					if(is_numeric($object)) { 
						$entity_info = Orm::entityInfo($this->_has_many_through[$key]['entity']);
						$object = Orm::collection($this->_has_many_through[$key]['entity'])->create(array($entity_info['primary_key'] => $object));
					}
					
					// can't go on if it's not an Entity
					if(!is_a($object, Orm::ENTITY_BASE_CLASS)) {
						continue;
					}
					
					// getting entity info
					if(!$entity_info) { 
						$entity_info = $object->info();
					}
				
					// and finally, if entity name is the same as defined in class, setting the relation
					if($entity_info['entity'] == $this->_has_many_through[$key]['entity']) {
						$this->addJob(self::QUEUE_POST_STORE, self::QUEUE_TYPE_EXEC, array(
								'object' => $this, 
								'method' => 'attachThrough',
								'params' => array(
										$object, 
										$join_table,
										$base_table_key,
										$associated_table_key,
										$join_table_fields
									)
							));
							
						$related_objects[] = $object;
					}
				}
				
				// removing old relations
				$this->addJob(self::QUEUE_POST_STORE, self::QUEUE_TYPE_EXEC, array(
						'object' => $this, 
						'method' => 'clearUnrelated',
						'params' => array(
								$this->_has_many_through[$key]['entity'],
								$related_objects,
								$join_table,
								$base_table_key,
								$associated_table_key
							)
					));			
			}
			elseif(empty($value)) {
				// clear all relationships
				$this->addJob(self::QUEUE_PRE_STORE, self::QUEUE_TYPE_EXEC, array(
						'object' => $this, 
						'method' => 'clearRelations',
						'params' => array(
								$this->_has_many_through[$key]['entity'],
								$join_table,
								$base_table_key
							)
					));			
			}
		}
		else {
			$this->setFieldValue($key, $value);
		}
	}


	/**
	 * Adds job to named queue with type $type
	 *
	 * @param $queue
	 * @param $type
	 * @param $params
	 */
	private function addJob($queue, $type, $params)
	{
		$this->queue[$queue][$type][] = $params;
	}


	/**
	 * Runs jobs from named queue
	 *
	 * @param $queue
	 * @throws BakedCarrotOrmException
	 */
	private function runJobs($queue)
	{
		if(isset($this->queue[$queue]) && is_array($this->queue[$queue])) {
			foreach($this->queue[$queue] as $job_type => $jobs) {
				foreach($jobs as $job) {
					if($job_type == self::QUEUE_TYPE_SET_VAL) {
						$object_left = $job['object_left'];
						$object_right = $job['object_right'];
						$object_left->setFieldValue($job['field_left'], $object_right[$job['field_right']]);
					}
					elseif($job_type == self::QUEUE_TYPE_EXEC) {
						if(method_exists($job['object'], $job['method'])) {
							call_user_func_array(array($job['object'], $job['method']), isset($job['params']) ? $job['params'] : array());
						}
						else {
							throw new BakedCarrotOrmException('Entity job error: cannot execute method "' . $job['method'] . '" of object "' . get_class($job['object']) . '"');
						}
					}
				}
			}
			
			$this->queue[$queue] = array();
		}
	}


	/**
	 * Creates join table name
	 *
	 * @static
	 * @param $table1
	 * @param $table2
	 * @return string
	 */
	private static function createJoinTable($table1, $table2)
	{
		$tables = array($table1, $table2);
		sort($tables);
		
		return implode('_', $tables);
	}


	/**
	 * Creates table name from entity name
	 *
	 *      UserProfile => user_profile
	 *
	 * @static
	 * @param $entity_name
	 * @return string
	 */
	private static function createTableFromEntityName($entity_name)
	{
		return strtolower(preg_replace("/([a-z])([A-Z])/", "\\1_\\2", $entity_name));
	}
	
}
