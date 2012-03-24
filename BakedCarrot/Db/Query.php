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
	protected $model = Orm::MODEL_BASE_CLASS;
	private $sql_accum = null;
	private $values_accum = null;
	private $use_cache = false;
	

	public function __construct($model = null)
	{
		if($model) {
			$this->model = $model;
		}
	}

	
	public function findAll()
	{
		$result = array();
		$sql = $this->compile();

		if(($result_cached = OrmCache::getCachedQuery($sql, $this->values_accum)) !== false && $this->use_cache) {
			return $result_cached;
		}
		
		$rows = Db::getAll($this->compile(), $this->values_accum);
		$new_collection = Orm::collection($this->model);
		
		foreach($rows as $row) {
			$result[$row['id']] = $new_collection->createObject($row);
		}
		
		if($this->use_cache) {
			OrmCache::cacheQuery($sql, $this->values_accum, $this->getStatement('from'), $result);
		}
		
		return $result;
	}

	
	public function findOne()
	{
		$this->remove('limit')->limit(1);
		
		$result = null;
		$sql = $this->compile();
		
		if(($result_cached = OrmCache::getCachedQuery($sql, $this->values_accum)) !== false && $this->use_cache) {
			return $result_cached;
		}
		
		if($row = Db::getRow($sql, $this->values_accum)) {
			$result = Orm::collection($this->model)->createObject($row);
		}
		
		if($this->use_cache) {
			OrmCache::cacheQuery($sql, $this->values_accum, $this->getStatement('from'), $result);
		}
		
		return $result;
	}

	
	public function count()
	{
		$this->remove('select')->select('count(*)');
		
		return Db::getCell($this->compile(), $this->values_accum);
	}


	public function setModel($class_name)
	{
		$this->model = $class_name;

		return $this;
	}

	
	public function where($sql, $values = array())
	{
		$this->sql_accum[] = array('where', $sql, 3);
		$this->values_accum = array_merge((array)$this->values_accum, $values);
		
		return $this;
	}


	public function select($sql)
	{
		$this->sql_accum[] = array('select', $sql, 1);
	
		return $this;
	}

	
	public function from($sql)
	{
		$this->sql_accum[] = array('from', $sql, 2);
	
		return $this;
	}

	
	public function limit($limit)
	{
		$this->sql_accum[] = array('limit', $limit, 5);

		return $this;
	}

	
	public function offset($offset)
	{
		$this->sql_accum[] = array('offset', $offset, 6);

		return $this;
	}

	
	public function order($sql)
	{
		$this->sql_accum[] = array('order', $sql, 4);

		return $this;
	}
	
	
	public function pagination(Pagination $pager)
	{
		$this->offset($pager->getOffset());
		$this->limit($pager->getRowsCount());
		
		return $this;
	}

	
	public function remove($stmt)
	{
		foreach($this->sql_accum as $num => $options) {
			if($options[0] == $stmt) {
				unset($this->sql_accum[$num]);
			}
		}

		return $this;
	}

	
	public function reset()
	{
		$this->sql_accum = array();
		$this->values_accum = array();

		return $this;
	}

	
	public function cached()
	{
		$this->use_cache = true;
		
		return $this;
	}

	
	public function hasStatement($stmt)
	{
		foreach($this->sql_accum as $num => $options) {
			if($options[0] == $stmt) {
				return true;
			}
		}
		
		return false;
	}

	
	public function getStatement($stmt)
	{
		foreach($this->sql_accum as $num => $options) {
			if($options[0] == $stmt) {
				return $options[1];
			}
		}
		
		return null;
	}
	
	
	private static function cmpFunction($a, $b)
	{
		if($a[2] == $b[2]) {
			return 0;
		}
		
		return ($a[2] > $b[2]) ? 1 : -1;
	}
	
	
	private function compile()
	{
		$sql = '';
		$prev_stmts = array();
		
		usort($this->sql_accum, array($this, 'cmpFunction'));
		
		foreach($this->sql_accum as $options) {
			list($statement, $param) = $options;

			switch($statement) {
				case 'select':
					$sql = $statement . ' ' . $param . ' ';
					break;
					
				case 'from':
					if(!in_array('select', $prev_stmts) && !in_array('delete', $prev_stmts)) {
						
						throw new OrmException('Error in query: "select" statement is missing');
					}
					
					$sql .= $statement . ' ' . $param . ' ';
					break;
					
				case 'where':
					if(!in_array('from', $prev_stmts)) {
						throw new OrmException('Error in query: "from" statement is missing');
					}
					
					$sql .= $statement . ' ' . $param . ' ';
					break;
					
				case 'limit':
					if(!in_array('from', $prev_stmts)) {
						throw new OrmException('Error in query: "from" statement is missing');
					}
					
					$sql .= $statement . ' ' . $param . ' ';
					break;
					
				case 'offset':
					if(!in_array('from', $prev_stmts)) {
						throw new OrmException('Error in query: "from" statement is missing');
					}
					
					$sql .= $statement . ' ' . $param . ' ';
					break;

				case 'order':
					if(!in_array('from', $prev_stmts)) {
						throw new OrmException('Error in query: "from" statement is missing');
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

