<?
	require 'OrmException.php';
	require 'Model.php';
	require 'Collection.php';


	class Orm
	{
		const MODEL_CLASS_PREFIX = 'Model';
		const MODEL_BASE_CLASS = 'Model';
		const COLLECTION_CLASS_PREFIX = 'Collection';
		const COLLECTION_BASE_CLASS = 'Collection';
		
		private static $worker = null;
		private static $collections = array();
		private static $instance = null;

		
		private function __construct()
		{
		
		}

		
		public static function create()
		{
			if(is_null(self::$instance)) {
				self::$instance = new self();
			}
		}
		
		
		public static function collection($name)
		{
			if(!isset(self::$collections[$name])) {
				$class = self::COLLECTION_CLASS_PREFIX . ucfirst($name);
				
				if(!class_exists($class)) {
					$files_to_try[] = ucfirst($name) . EXT;
					$files_to_try[] = $class . EXT;
					
					foreach($files_to_try as $file) {
						if(is_file(MODELPATH . $file)) {
							require MODELPATH . $file;
							break;
						}
					}
				}
				
				if(class_exists($class)) {
					$collection = new $class($name);
					
					if(!is_subclass_of($object, self::COLLECTION_BASE_CLASS)) {
						throw new OrmException("Class $class is not subclass of " . self::COLLECTION_BASE_CLASS);
					}
				}
				else {
					$class_name = self::COLLECTION_BASE_CLASS;
					$collection = new $class_name($name);
				}
				
				self::$collections[$name] = $collection;
			}
			
			return self::$collections[$name];
		}
	
/*		
		public static function load($name, $id = null)
		{
			self::checkConnection();
			
			return self::$worker->collection($name)->load($id);
		}
		
		
		public static function find($name, $sql = null, $values = null)
		{
			self::checkConnection();
			
			return self::$worker->collection($name)->find($sql, $values);
		}

		
		public static function findOne($name, $sql = null, array $values = null)
		{
			self::checkConnection();
			
			return self::$worker->collection($name)->findOne($sql, $values);
		}

		
		public function count($name, $sql = null, $values = null)
		{
			self::checkConnection();
			
			return self::$worker->collection($name)->count($sql, $values);
		}

		
		public static function delete($object)
		{
			self::checkConnection();
			
			return self::$worker->collection($object->getTableName())->delete($object);
		}
		
*/
/*
		
		public static function begin()
		{
			self::$worker->begin();
		}
		
		
		public static function commit()
		{
			self::$worker->commit();
		}
		
		
		public static function rollback()
		{
			self::$worker->rollback();
		}
		
*/		
/*
		public static function getCell($sql, array $values = null)
		{
			return self::$worker->getCell($sql, $values);
		}
		
		
		public static function getRow($sql, array $values = null)
		{
			return self::$worker->getRow($sql, $values);
		}


		public static function getAll($sql, array $values = null)
		{
			return self::$worker->getAll($sql, $values);
		}


		public static function exec($sql, array $values = null)
		{
			return self::$worker->execute($sql, $values);
		}


		public static function expr($expr, $values = null)
		{
			return self::$worker->expr($expr, $values);
		}
*/		
	}

?>