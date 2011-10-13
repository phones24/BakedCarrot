<?
	class Model implements ArrayAccess
	{
		private $storage = null;
		private $modified = false;
		private $table_name = '';
		private $collection = null;
		private $columns_meta = null;
		
		
		final public function __construct($name, Collection $collection)
		{
			$this->table_name = $name;
			$this->collection = $collection;
			
			$this->storage[Collection::PK] = 0;
		}


		final public function loadData(array $data)
		{
			$this->storage = $data;
		}
		
		
		final public function loaded()
		{
			return isset($this[Collection::PK]) && $this[Collection::PK] != 0;
		}
		

		final public function getId()
		{
			return $this[Collection::PK];
		}
		

		final public function getTableName()
		{
			return $this->table_name;
		}
		
		
		final public function store()
		{
			if($this->getId() > 0) {
				$this->runEvent('onBeforeUpdate');
				
				$this->storeUpdate();
				
				$this->runEvent('onAfterUpdate');
			}
			else {
				$this->runEvent('onBeforeInsert');

				$this->storeInsert();
				
				$this->runEvent('onAfterInsert');
			}
			
			$this->modified = false;
			
			return $this[Collection::PK];
		}
		

		final public function runEvent($event_name) 
		{
			if(method_exists($this, $event_name)) {
				$this->$event_name();
				
				return true;
			}
			
			return false;
		}
		

		final public function export()
		{
			$to_export = array();
			
			foreach($this->storage as $key => $val) {
				if(!is_object($val) && !is_array($val)) {
					$to_export[$key] = $val;
				}
			}
		
			return $to_export;
		}
		
		
		private function storeUpdate()
		{
			$values = array();
			$real_field = Db::getColumns($this->table_name);

			foreach($this->storage as $field_name => $field_val) {
				if($field_name == Collection::PK || !isset($real_field[$field_name])) {
					continue;
				}
				
				$values[$field_name] = $field_val;
			}
			
			Db::update($this->table_name, 
					$values, 
					Collection::PK . ' = ?', 
					array($this->getId())
				);
		}
		
		
		private function storeInsert()
		{
			$values = array();
			$real_field = Db::getColumns($this->table_name);

			foreach($this->storage as $field_name => $field_val) {
				if(!isset($columns[$field_name])) {
					continue;
				}
				
				$values[$field_name] = $field_val;
			}
			
			$this[Collection::PK] = Db::insert($this->table_name, array_keys($values));
		}
		
		
		final public function import(array $source, $fields = null)
		{
			if(!empty($fields)) {
				$fields = explode(',', $fields . ',');
				
				foreach($fields as $field) {
					$field = trim($field);
					if($field) {
						$this[$field] = isset($source[$field]) && !is_array($source[$field]) && !is_object($source[$field]) ? $source[$field] : null;
					}
				}
			}
			else {
				$this->storage = array_merge($this->storage, $source);
			}
		}
		
		
		final public function related($related_table, $where = null, array $values = null)
		{
			$mm_table = Collection::getRelationTable($related_table, $this->table_name);
			
			$sql = 'select `' . $related_table . '`.* from `' . $mm_table . '`, `' . $related_table . '` ' .
					'where ' . ($where ? $where . ' and ' : ' ') . 
					'`' . $related_table . '`.' . Collection::PK . ' = `' . $mm_table . '`.' . $related_table . '_id and ' . 
					'`' . $mm_table . '`.' . $this->table_name . '_id = ?';
			
			$values[] = $this->getId();
			
			$rows = Db::getAll($sql, $values);
			
			$result = null;
			$new_collection = Orm::collection($related_table);
			foreach($rows as $row) {
				$result[] = $new_collection->createObject($row);
			}
			
			return $result;
		}
		
		
		final public function relatedOne($related_table, $where = null, array $values = null)
		{
			$mm_table = Collection::getRelationTable($related_table, $this->table_name);

			$sql = 'select `' . $related_table . '`.* from `' . $mm_table . '`, `' . $related_table . '` ' .
					'where ' . ($where ? $where . ' and ' : ' ') . 
					'`' . $related_table . '`.' . Collection::PK . ' = `' . $mm_table . '`.' . $related_table . '_id and ' . 
					'`' . $mm_table . '`.' . $this->table_name . '_id = ? ' .
					'limit 1';

			$values[] = $this->getId();

			if($row = Db::getRow($sql, $values)) {
				return Orm::collection($related_table)->createObject($row);
			}
			
			return null;
		}
		
		
		final public function countRelated($related_table, $where = null, array $values = null)
		{
			$mm_table = Collection::getRelationTable($related_table, $this->table_name);

			$sql = 'select count(*) from `' . $mm_table . '`, `' . $related_table . '` ' .
					'where ' . ($where ? $where . ' and ' : ' ') . 
					'`' . $related_table . '`.' . Collection::PK . ' = `' . $mm_table . '`.' . $related_table . '_id and ' . 
					'`' . $mm_table . '`.' . $this->table_name . '_id = ?';
			
			$values[] = $this->getId();
			
			return Db::getCol($sql, $values);
		}


		final public function isRelated($object)
		{
			$related_table = $object->getTableName();
			$mm_table = Collection::getRelationTable($related_table, $this->table_name);

			$sql = 'select id from `' . $mm_table . '` ' .
				'where `' . $related_table . '_id`  = ? and `' . $this->table_name . '_id` = ?';
			
			return Db::getCol($sql, array($object->getId(), $this->getId())) ? true : false;
		}


		final public function addRelation($object, array $extra_values = null)
		{
			if($this->isRelated($object)) {
				return false;
			}
			
			if(!$this->loaded()) {
				throw new OrmException('Cannot establish relation with non existent entity of class "' . get_class($this) . '"');
			}
			
			$related_table = $object->getTableName();
			$mm_table = Collection::getRelationTable($related_table, $this->table_name);
			
			$values = array($related_table . '_id' => $object->getId(), $this->table_name . '_id' => $this->getId());
			
			if(is_array($extra_values)) {
				$values = array_merge($values, $extra_values);
			}
			
			return Db::insert($mm_table, $values);
		}


		final public function removeRelation($object)
		{
			$related_table = $object->getTableName();
			$mm_table = Collection::getRelationTable($related_table, $this->table_name);
			
			return Db::delete($mm_table, $related_table . '_id = ? and ' . $this->table_name . '_id = ?', array($object->getId(), $this->getId()));
		}


		final public function clearRelations($related_table)
		{
			$mm_table = Collection::getRelationTable($related_table, $this->table_name);
			
			return Db::delete($mm_table, $this->table_name . '_id = ?', array($this->getId()));
		}


		final public function offsetSet($offset, $value) 
		{
			$this->modified = true;

			if(is_null($offset)) {
				$this->storage[] = $value;
			} 
			else {
				$this->storage[$offset] = $value;
			}
		}
		
		
		final public function offsetExists($offset) 
		{
			return isset($this->storage[$offset]);
		}
		
		
		final public function offsetUnset($offset) 
		{
			unset($this->storage[$offset]);
		}
		
		
		final public function offsetGet($offset) 
		{
			return isset($this->storage[$offset]) ? $this->storage[$offset] : null;
		}
		
		
		final public function __isset($key)
		{
			return isset($this->storage[$key]);
		}
		

		final public function __get($key)
		{
			if(isset($this->storage[$key . '_id'])) {
				if(isset($this->storage[$key]) && is_object($key) && is_a($key, Orm::MODEL_BASE_CLASS)) {
					return $key;
				}
				else {
					$this->storage[$key] = Orm::collection($key)->load($this->storage[$key . '_id']);
					
					return $this->storage[$key];
				}
			}
		
			return isset($this->storage[$key]) ? $this->storage[$key] : null;
		}
		
		
		final public function __set($key, $val)
		{
			$this->modified = true;
			
			if(is_object($val) && is_a($val, Orm::MODEL_BASE_CLASS)) {
				$this->storage[$key . '_id'] = $val->getId();
			}
			
			$this->storage[$key] = $val;
		}
		
		
	}
?>