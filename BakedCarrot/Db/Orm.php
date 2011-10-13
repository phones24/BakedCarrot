<?
class Orm
{
	const MODEL_CLASS_PREFIX = 'Model';
	const MODEL_BASE_CLASS = 'Model';
	const COLLECTION_CLASS_PREFIX = 'Collection';
	const COLLECTION_BASE_CLASS = 'Collection';
	
	private static $worker = null;
	private static $collections = array();
	
	
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
}

?>