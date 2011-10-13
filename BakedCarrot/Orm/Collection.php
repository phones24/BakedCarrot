<?
	class Collection
	{
		const PK = 'id';
		private $table_name = '';
		
		
		final public function __construct($name)
		{
			$this->table_name = $name;
		}
		
		
		final public function load($id = null)
		{	
			$row = array();
		
			if(!is_numeric($id) && !is_null($id)) {
				throw new OrmException('Collection::load only accepts numeric parameter or null');
			}
		
			if(is_numeric($id)) {
				$row = Db::getRow(
						'select * from `' . $this->table_name . '` where ' . self::PK . ' = ?',
						array($id)
					);
			}
			
			return $this->createObject($row);
		}
		

		final public function find($where = null, array $values = null)
		{
			$sql = 'select * from `' . $this->table_name . '` ';
			$sql .= !empty($where) ? 'where ' . $where : '';
			
			$result = null;
			$rows = Db::getAll($sql, $values);
			foreach($result as $num => $row) {
				$result[] = $this->createObject($row);
			}
			
			return $result;
		}

		
		final public function findOne($where = null, array $values = null)
		{
			$sql = 'select * from `' . $this->table_name . '` ';
			$sql .= !empty($where) ? 'where ' . $where : ' ';
			$sql .= ' limit 1';
			
			return $this->createObject(Db::getRow($sql, $values));
		}


		final public function count($where = null, array $values = null)
		{
			$sql = 'select count(*) from `' . $this->table_name . '` ';
			$sql .= !empty($where) ? 'where ' . $where : ' ';

			return Db::getCol($sql, $values);
		}


		final public function delete(Model $object)
		{
			if(empty($object)) {
				return;
			}
			
			$object->runEvent('onBeforeDelete');

			Db::delete($this->table_name, self::PK . ' = ?', array($object->getId()));
			
			unset($object);
		}


		final public function swap(Model $object1, Model $object2, $property)
		{
			$old_property = $object1[$property];
			
			$object1[$property] = $object2[$property];
			$object2[$property] = $old_property;
			
			$object1->store();
			$object2->store();
		}

		
		final public function createObject($data = null)
		{
			$class = Orm::MODEL_CLASS_PREFIX . ucfirst($this->table_name);
			
			if(!class_exists($class)) {
				$files_to_try[] = ucfirst($this->table_name) . EXT;
				$files_to_try[] = $this->table_name . EXT;
				
				foreach($files_to_try as $file) {
					if(is_file(MODELPATH . $file)) {
						require MODELPATH . $file;
						break;
					}
				}
			}
			
			if(class_exists($class)) {
				$object = new $class($this->table_name, $this);
				
				if(!is_subclass_of($object, Orm::MODEL_BASE_CLASS)) {
					throw new OrmException("Class $class is not subclass of " . Orm::MODEL_BASE_CLASS);
				}
			}
			else {
				$class_name = Orm::MODEL_BASE_CLASS;
				$object = new $class_name($this->table_name, $this);
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