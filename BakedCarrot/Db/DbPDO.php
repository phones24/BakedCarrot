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
	public function query($sql) 
	{
		Log::out(__METHOD__ . " $sql", Log::LEVEL_DEBUG);
		
		$stmt = parent::query($sql);
		
		return $stmt;
	}


	public function prepare($sql, $options = array())
	{
		Log::out(__METHOD__ . " $sql", Log::LEVEL_DEBUG);

		$stmt = parent::prepare($sql, $options);
			
		return $stmt;
	}
}
