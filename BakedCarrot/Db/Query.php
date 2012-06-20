<?php
/**
 * BakedCarrot database query 
 *
 * Used as sql builder/filter for querying objects from collections
 *
 * @package BakedCarrot
 * @subpackage Db
 */
 
class Query
{
	protected $entity_name = Orm::ENTITY_BASE_CLASS;
	private $sql_accum = array();
	private $values_accum = array();
	private $use_cache = false;


	/**
	 * Creates empty query builder
	 *
	 * @param string|null $entity_name
	 */
	public function __construct($entity_name = null)
	{
		if($entity_name) {
			$this->entity_name = $entity_name;
		}
	}


	/**
	 * Returns array of objects from collection
	 *
	 * @return array
	 */
	public function findAll()
	{
		$result = array();
		
		if(!$this->hasStatement('select')) {
			$this->select('*');
		}
		
		$sql = $this->compile();
		$tbl = $this->getStatement('table');
		$cache_key = OrmCache::genKey($sql, $this->values_accum);
		
		if(($result_cached = OrmCache::getCachedQuery($cache_key)) !== false && $this->use_cache) {
			return $result_cached;
		}
		
		$rows = Db::getAll($sql, $this->values_accum);
		$new_collection = Orm::collection($this->entity_name);
		$pk = isset($this->entity_info['primary_key']) ? $this->entity_info['primary_key'] : 'id';

		foreach($rows as $row) {
			$pk_value = isset($row[$pk]) ? $row[$pk] : null;
			$result[$pk_value] = $new_collection->create($row);
		}
		
		if($this->use_cache) {
			OrmCache::cacheQuery($cache_key, $tbl['sql'], $result);
		}
		
		return $result;
	}


	/**
	 * Returns one object from collection
	 *
	 * @return mixed|null
	 */
	public function findOne()
	{
		if(!$this->hasStatement('select')) {
			$this->select('*');
		}
		
		$this->limit(1);
		
		$result = null;
		$sql = $this->compile();
		$tbl = $this->getStatement('table');
		$cache_key = OrmCache::genKey($sql, $this->values_accum);
		
		if(($result_cached = OrmCache::getCachedQuery($cache_key)) !== false && $this->use_cache) {
			return $result_cached;
		}
		
		if(($result_cached = OrmCache::getFromInternalCache($cache_key)) !== false) {
			return $result_cached;
		}
		
		if($row = Db::getRow($sql, $this->values_accum)) {
			$result = Orm::collection($this->entity_name)->create($row);
		}
		
		if($this->use_cache) {
			OrmCache::cacheQuery($cache_key, $tbl['sql'], $result);
			return $result;
		}
		
		OrmCache::storeInternal($cache_key, $tbl['sql'], $result);
		
		return $result;
	}


	/**
	 * Returns objects count in collection
	 *
	 * @return bool|null
	 */
	public function count()
	{
		$this->select('count(*)');
		
		$result = null;
		$sql = $this->compile();
		$tbl = $this->getStatement('table');
		$cache_key = OrmCache::genKey($sql, $this->values_accum);
		
		if(($result_cached = OrmCache::getCachedQuery($cache_key)) !== false && $this->use_cache) {
			return $result_cached;
		}
		
		if(($result_cached = OrmCache::getFromInternalCache($cache_key)) !== false) {
			return $result_cached;
		}

		$result = Db::getCell($this->compile(), $this->values_accum);
		
		if($this->use_cache) {
			OrmCache::cacheQuery($cache_key, $tbl['sql'], $result);
			return $result;
		}
		
		OrmCache::storeInternal($cache_key, $tbl['sql'], $result);

		return $result;
	}


	/**
	 * Remove objects from collection
	 *
	 * @return mixed
	 */
	public function delete()
	{
		$this->remove('select');
		$this->sql_accum[] = array(
				'stmt'	=> 'delete', 
				'sql'	=> null, 
				'sort'	=> 1,
				'val'	=> array()
			);
				
		$sql = $this->compile();
		$tbl = $this->getStatement('table');

		OrmCache::clearCacheForTable($tbl['sql']);
		
		return Db::exec($sql, $this->values_accum);
	}


	/**
	 * Bulk updates objects in collection
	 *
	 * @param array $field_values
	 * @return mixed
	 */
	public function update(array $field_values)
	{
		$this->remove('select');
		$this->sql_accum[] = array(
				'stmt'	=> 'update', 
				'sql'	=> null, 
				'sort'	=> 1,
				'val'	=> $field_values
			);
			
		$sql = $this->compile();
		$tbl = $this->getStatement('table');
		
		OrmCache::clearCacheForTable($tbl['sql']);
	
		return Db::exec($sql, $this->values_accum);
	}


	/**
	 * Sets the entity name
	 *
	 * @param $class_name
	 * @return \Query
	 */
	public function setEntity($class_name)
	{
		$this->entity_name = $class_name;

		return $this;
	}


	/**
	 * Adds WHERE statement to internal query
	 *
	 * @param $sql
	 * @param array $values
	 * @return \Query
	 */
	public function where($sql, $values = array())
	{
		$this->sql_accum[] = array(
				'stmt'	=> 'where', 
				'sql'	=> $sql, 
				'sort'	=> 3,
				'val'	=> $values
			);
		
		return $this;
	}


	/**
	 * Adds SELECT statement to internal query
	 *
	 * @param $sql
	 * @return \Query
	 */
	public function select($sql)
	{
		$this->remove('select');
		$this->sql_accum[] = array(
				'stmt'	=> 'select', 
				'sql'	=> $sql, 
				'sort'	=> 1,
				'val'	=> array()
			);
	
		return $this;
	}


	/**
	 * Sets the table name to operate with
	 *
	 * @param $sql
	 * @return \Query
	 */
	public function table($sql)
	{
		$this->remove('table');
		$this->sql_accum[] = array(
				'stmt'	=> 'table', 
				'sql'	=> $sql, 
				'sort'	=> 2,
				'val'	=> array()
			);
	
		return $this;
	}


	/**
	 * Adds LIMIT statement to internal query
	 *
	 * @param $limit
	 * @return \Query
	 */
	public function limit($limit)
	{
		$this->remove('limit');
		$this->sql_accum[] = array(
				'stmt'	=> 'limit', 
				'sql'	=> $limit, 
				'sort'	=> 5,
				'val'	=> array()
			);

		return $this;
	}


	/**
	 * Adds OFFSET statement to internal query
	 *
	 * @param $offset
	 * @return \Query
	 */
	public function offset($offset)
	{
		$this->remove('offset');
		$this->sql_accum[] = array(
				'stmt'	=> 'offset', 
				'sql'	=> $offset, 
				'sort'	=> 6,
				'val'	=> null
			);

		return $this;
	}


	/**
	 * Adds ORDER statement to internal query
	 *
	 * @param $sql
	 * @return \Query
	 */
	public function order($sql)
	{
		$this->remove('order');
		$this->sql_accum[] = array(
				'stmt'	=> 'order', 
				'sql'	=> $sql, 
				'sort'	=> 4,
				'val'	=> null
			);

		return $this;
	}


	/**
	 * Use Pagination object for limiting the results
	 *
	 * @param Pagination $pager
	 * @return \Query
	 */
	public function pagination(Pagination $pager)
	{
		$this->offset($pager->getOffset());
		$this->limit($pager->getRowsCount());
		
		return $this;
	}


	/**
	 * Removes the statement from internal query
	 *
	 * @param $stmt
	 * @return \Query
	 */
	public function remove($stmt)
	{
		foreach($this->sql_accum as $num => $options) {
			if($options['stmt'] == $stmt) {
				unset($this->sql_accum[$num]);
			}
		}

		return $this;
	}


	/**
	 * Reset internal query
	 *
	 * @return \Query
	 */
	public function reset()
	{
		$this->sql_accum = array();

		return $this;
	}


	/**
	 * Tells to use cache
	 *
	 * @return \Query
	 */
	public function cached()
	{
		$this->use_cache = true;
		
		return $this;
	}


	/**
	 * Returns TRUE if internal query has statement $stmt
	 *
	 * @param $stmt
	 * @return bool
	 */
	public function hasStatement($stmt)
	{
		foreach($this->sql_accum as $num => $options) {
			if($options['stmt'] == $stmt) {
				return true;
			}
		}
		
		return false;
	}


	/**
	 * Returns first matched query by its name
	 *
	 * @param $stmt
	 * @return null
	 */
	public function getStatement($stmt)
	{
		foreach($this->sql_accum as $num => $options) {
			if($options['stmt'] == $stmt) {
				return $options;
			}
		}
		
		return null;
	}


	/**
	 * Used to sort statement before compilation
	 *
	 * @static
	 * @param $a
	 * @param $b
	 * @return int
	 */
	private static function cmpFunction($a, $b)
	{
		if($a['sort'] == $b['sort']) {
			return 0;
		}
		
		return ($a['sort'] > $b['sort']) ? 1 : -1;
	}


	/**
	 * Compile real SQL query from internal query
	 *
	 * @return string
	 * @throws BakedCarrotOrmException
	 */
	private function compile()
	{
		$sql = '';
		$prev_stmts = array();
		$this->values_accum = array();
		
		usort($this->sql_accum, array($this, 'cmpFunction'));
		
		foreach($this->sql_accum as $options) {
			$statement = $options['stmt'];
			$param = $options['sql'];
			$values = $options['val'];

			switch($statement) {
				case 'select':
					$sql = $statement . ' ' . $param . ' ';
					break;
					
				case 'delete':
					$sql = $statement . ' ';
					break;
					
				case 'update':
					$tbl = $this->getStatement('table');
					if(!$tbl) {
						throw new BakedCarrotOrmException('Error in query: table name is missing in update query');
					}
					
					$sql = $statement . ' ' . $tbl['sql'] . ' set ';
					$sql .= implode(' = ?, ', array_keys($values));
					$sql .= ' = ? ';
					$this->values_accum = array_merge($this->values_accum, array_values($values));
					
					break;
					
				case 'table':
					if(in_array('update', $prev_stmts)) {
						break;
					} 
					
					if(!in_array('select', $prev_stmts) && !in_array('delete', $prev_stmts)) {
						throw new BakedCarrotOrmException('Error in query: "select", "update" or "delete" statement is missing');
					}
					
					$sql .= 'from ' . $param . ' ';
					break;
					
				case 'where':
					if(!in_array('table', $prev_stmts)) {
						throw new BakedCarrotOrmException('Error in query: table name is missing');
					}
					
					if(in_array('where', $prev_stmts)) { // if WHERE already exists, add AND
						$sql .= ' and ' . $param . ' ';
					}
					else {
						$sql .= $statement . ' ' . $param . ' ';
					}
					
					$this->values_accum = array_merge($this->values_accum, $values);
					
					break;
					
				case 'limit':
					if(!in_array('table', $prev_stmts)) {
						throw new BakedCarrotOrmException('Error in query: table name is missing');
					}
					
					$sql .= $statement . ' ' . $param . ' ';

					break;
					
				case 'offset':
					if(!in_array('table', $prev_stmts)) {
						throw new BakedCarrotOrmException('Error in query: table name is missing');
					}
					
					$sql .= $statement . ' ' . $param . ' ';
					
					break;

				case 'order':
					if(!in_array('table', $prev_stmts)) {
						throw new BakedCarrotOrmException('Error in query: table name is missing');
					}
					
					$sql .= $statement . ' by ' . $param . ' ';

					break;

				default:
					break;
			}
			
			$prev_stmts[] = $statement;
		}
		
		return $sql;
	}
}

