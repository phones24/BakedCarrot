<?php
/**
 * BakedCarrot PDO subclass
 *
 * @package BakedCarrot
 * @subpackage Db
 * @author Yury Vasiliev
 *
 *
 * 
 */
 
class DbPDO extends PDO 
{
	private $last_sql = null;
	

	public function query($sql) 
	{
		Log::out(__METHOD__ . " $sql", Log::LEVEL_DEBUG);
		
		$this->last_sql = $sql;
		
		$stmt = parent::query($sql);
		
		return $stmt;
	}


	public function prepare($sql, $options = array())
	{
		Log::out(__METHOD__ . " $sql", Log::LEVEL_DEBUG);

		$this->last_sql = $sql;
		
		$stmt = parent::prepare($sql, $options);
			
		return $stmt;
	}
	
	
	public function lastSql()
	{
		return $this->last_sql;
	}
	
}
