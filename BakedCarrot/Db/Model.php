<?
/**
 * BakedCarrot ORM model class
 * 
 * Provides entity-related ORM functionality. All user models should inherits this class.
 * The objects of this class managed my Collection and should not be created manually.
 *	
 *		$user = Orm::collection('user')->load(); // getting new user from collection
 *		$user->name = 'John'; // setting the property OOP-way
 *		$user['email'] = 'john@example.com'; // or as array
 *		$user->store(); // saving record to table "user"
 *
 * @package BakedCarrot
 * @subpackage Db
 * @author Yury Vasiliev
 *
 * 
 */
class Model implements ArrayAccess
{
	private $storage = null;
	private $modified = false;
	private $table_name = '';
	private $collection = null;
	private $columns_meta = null;
	
	
	/**
	 * Creates a new entity object 
	 *
	 * @param string $name name of the table
	 * @param Collection $collection reference to owning collection 
	 * @return void
	 */
	public function __construct($name, Collection $collection)
	{
		$this->table_name = $name;
		$this->collection = $collection;
		
		$this->storage[Collection::PK] = 0;
	}


	/**
	 * Loads property data into object
	 *
	 * @param array $data data to be loaded
	 * @return void
	 */
	public function loadData(array $data)
	{
		$this->storage = $data;
	}
	
	
	/**
	 * Checks if object actually loaded from database
	 *
	 * @return bool
	 */
	final public function loaded()
	{
		return isset($this[Collection::PK]) && $this[Collection::PK] != 0;
	}
	

	/**
	 * Returns primary key value of object
	 *
	 * @return int
	 */
	public function getId()
	{
		return $this[Collection::PK];
	}
	

	/**
	 * Returns name of the table associated with the object
	 *
	 * @return string
	 */
	public function getTableName()
	{
		return $this->table_name;
	}
	
	
	/**
	 * Runs model event handler (if defined)
	 *
	 * @param string $event_name name of the event to be executed
	 * @return bool returns TRUE if event has been executed, FALSE otherwise
	 */
	final public function runEvent($event_name) 
	{
		if(method_exists($this, $event_name)) {
			$this->$event_name();
			
			return true;
		}
		
		return false;
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
	

	private function storeUpdate()
	{
		$values = array();
		$real_field = Db::getColumns($this->getTableName());

		foreach($this->storage as $field_name => $field_val) {
			if($field_name == Collection::PK || !isset($real_field[$field_name])) {
				continue;
			}
			
			$values[$field_name] = $field_val;
		}
		
		Db::update($this->getTableName(), 
				$values, 
				Collection::PK . ' = ?', 
				array($this->getId())
			);
	}
	
	
	private function storeInsert()
	{
		$values = array();
		$real_field = Db::getColumns($this->getTableName());

		foreach($this->storage as $field_name => $field_val) {
			if(!isset($real_field[$field_name])) {
				continue;
			}
			
			$values[$field_name] = $field_val;
		}
		
		$this[Collection::PK] = Db::insert($this->getTableName(), $values);
	}
	
	
	public function import(array $source, $fields = null)
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
	
	
	public function related($related_table, $where = null, array $values = null)
	{
		$mm_table = Db::clean(Collection::getRelationTable($related_table, $this->getTableName()));
		$related_table = Db::clean($related_table);
		
		$sql = 'select `' . $related_table . '`.* from `' . $mm_table . '`, `' . $related_table . '` ' .
				'where ' . ($where ? $where . ' and ' : ' ') . 
				'`' . $related_table . '`.' . Collection::PK . ' = `' . $mm_table . '`.' . $related_table . '_id and ' . 
				'`' . $mm_table . '`.' . $this->getTableName() . '_id = ?';
		
		$values[] = $this->getId();
		
		$rows = Db::getAll($sql, $values);
		
		$result = null;
		$new_collection = Orm::collection($related_table);
		
		foreach($rows as $row) {
			$result[$row['id']] = $new_collection->createObject($row);
		}
		
		return $result;
	}
	
	
	public function relatedOne($related_table, $where = null, array $values = null)
	{
		$mm_table = Db::clean(Collection::getRelationTable($related_table, $this->getTableName()));
		$related_table = Db::clean($related_table);
		
		$sql = 'select `' . $related_table . '`.* from `' . $mm_table . '`, `' . $related_table . '` ' .
				'where ' . ($where ? $where . ' and ' : ' ') . 
				'`' . $related_table . '`.' . Collection::PK . ' = `' . $mm_table . '`.' . $related_table . '_id and ' . 
				'`' . $mm_table . '`.' . $this->getTableName() . '_id = ? ' .
				'limit 1';

		$values[] = $this->getId();

		if($row = Db::getRow($sql, $values)) {
			return Orm::collection($related_table)->createObject($row);
		}
		
		return null;
	}
	
	
	public function countRelated($related_table, $where = null, array $values = null)
	{
		$mm_table = Db::clean(Collection::getRelationTable($related_table, $this->getTableName()));
		$related_table = Db::clean($related_table);
		
		$sql = 'select count(*) from `' . $mm_table . '`, `' . $related_table . '` ' .
				'where ' . ($where ? $where . ' and ' : ' ') . 
				'`' . $related_table . '`.' . Collection::PK . ' = `' . $mm_table . '`.' . $related_table . '_id and ' . 
				'`' . $mm_table . '`.' . $this->getTableName() . '_id = ?';
		
		$values[] = $this->getId();
		
		return Db::getCol($sql, $values);
	}


	public function isRelated($object)
	{
		$related_table = $object->getTableName();
		$mm_table = Db::clean(Collection::getRelationTable($related_table, $this->getTableName()));

		$sql = 'select id from `' . $mm_table . '` ' .
			'where `' . $related_table . '_id`  = ? and `' . $this->getTableName() . '_id` = ?';
		
		return Db::getCell($sql, array($object->getId(), $this->getId())) ? true : false;
	}


	public function addRelation($object, array $extra_values = null)
	{
		if($this->isRelated($object)) {
			return false;
		}
		
		if(!$this->loaded()) {
			throw new OrmException('Cannot establish relation with non existent entity of class "' . get_class($this) . '"');
		}
		
		$related_table = $object->getTableName();
		$mm_table = Db::clean(Collection::getRelationTable($related_table, $this->getTableName()));
		
		$values = array($related_table . '_id' => $object->getId(), $this->getTableName() . '_id' => $this->getId());
		
		if(is_array($extra_values)) {
			$values = array_merge($values, $extra_values);
		}
		
		return Db::insert($mm_table, $values);
	}


	public function removeRelation($object)
	{
		$related_table = $object->getTableName();
		$mm_table = Db::clean(Collection::getRelationTable($related_table, $this->getTableName()));
		
		return Db::delete($mm_table, $related_table . '_id = ? and ' . $this->getTableName() . '_id = ?', array($object->getId(), $this->getId()));
	}


	public function clearRelations($related_table)
	{
		$mm_table = Db::clean(Collection::getRelationTable($related_table, $this->getTableName()));
		
		return Db::delete($mm_table, $this->getTableName() . '_id = ?', array($this->getId()));
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
			if(isset($this->storage[$key]) && is_object($key) && @is_a($key, Orm::MODEL_BASE_CLASS)) {
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
		
		if(is_object($val) && @is_a($val, Orm::MODEL_BASE_CLASS)) {
			$this->storage[$key . '_id'] = $val->getId();
		}
		
		$this->storage[$key] = $val;
	}
	
	
}
?>