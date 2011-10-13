<?
/**
 * BakedCarrot ORM Collection
 *
 * @package BakedCarrot
 * @author Yury Vasiliev
 *
 *
 * 
 */
 
class Collection
{
	const PK = 'id';
	private $table_name = '';
	
	
	public function __construct($name)
	{
		$this->table_name = Db::clean($name);
	}
	
	
	public function getTableName()
	{
		return $this->table_name;
	}
	
	
	public function load($id = null)
	{	
		$row = array();
	
		if(!is_numeric($id) && !is_null($id)) {
			throw new OrmException('Collection::load only accepts numeric parameter or null');
		}
	
		if(is_numeric($id)) {
			$row = Db::getRow(
					'select * from `' . $this->getTableName() . '` where ' . self::PK . ' = ?',
					array($id)
				);
		}
		
		return $this->createObject($row);
	}
	

	public function find($where = null, array $values = null)
	{
		$sql = 'select * from `' . $this->getTableName() . '` ';
		$sql .= !empty($where) ? 'where ' . $where : '';
		
		$result = array();
		$rows = Db::getAll($sql, $values);
		foreach($rows as $num => $row) {
			$result[$row['id']] = $this->createObject($row);
		}
		
		return $result;
	}

	
	public function findAll($where = null, array $values = null)
	{
		$this->find($where, $values);
	}
	
	
	public function findOne($where = null, array $values = null)
	{
		$sql = 'select * from `' . $this->getTableName() . '` ';
		$sql .= !empty($where) ? 'where ' . $where : ' ';
		$sql .= ' limit 1';
		
		return $this->createObject(Db::getRow($sql, $values));
	}


	public function findPaging($page, $count, $where = null, array $values = null)
	{
		if($count <= 0) {
			throw new OrmException('$count cannot be less or equal zero in Collection::findPaging');
		}
		
		$page = $page < 0 ? 1 : $page;
		
		$where = !$where ? '1' : $where;
		$where .= ' limit ' . ($count * ($page - 1)) . ', ' . $count;
		
		return $this->find($where, $values);
	}
	
	
	public function count($where = null, array $values = null)
	{
		$sql = 'select count(*) from `' . $this->getTableName() . '` ';
		$sql .= !empty($where) ? 'where ' . $where : ' ';

		return Db::getCol($sql, $values);
	}


	public function delete(Model $object)
	{
		if(empty($object)) {
			return;
		}
		
		$object->runEvent('onBeforeDelete');

		Db::delete($this->getTableName(), self::PK . ' = ?', array($object->getId()));
		
		unset($object);
	}


	public function swap(Model $object1, Model $object2, $property)
	{
		$old_property = $object1[$property];
		
		$object1[$property] = $object2[$property];
		$object2[$property] = $old_property;
		
		$object1->store();
		$object2->store();
	}

	
	final public function createObject($data = null)
	{
		$class = Orm::MODEL_CLASS_PREFIX . ucfirst($this->getTableName());
		
		if(!class_exists($class)) {
			$files_to_try[] = ucfirst($this->getTableName()) . EXT;
			$files_to_try[] = $this->getTableName() . EXT;
			
			foreach($files_to_try as $file) {
				if(is_file(MODELPATH . $file)) {
					require MODELPATH . $file;
					break;
				}
			}
		}
		
		if(class_exists($class)) {
			$object = new $class($this->getTableName(), $this);
			
			if(!is_subclass_of($object, Orm::MODEL_BASE_CLASS)) {
				throw new OrmException("Class $class is not subclass of " . Orm::MODEL_BASE_CLASS);
			}
		}
		else {
			$class_name = Orm::MODEL_BASE_CLASS;
			$object = new $class_name($this->getTableName(), $this);
		}
		
		if(is_array($data) && !empty($data)) {
			$object->loadData($data);
			$object->runEvent('onLoad');
		}
		else {
			$object->runEvent('onCreate');
		}
		
		return $object;
	}
	

	final public static function getRelationTable($table1, $table2)
	{
		$tables = array($table1, $table2);
		
		sort($tables);
		
		return implode('_', $tables);
	}
	
	
	

}
?>