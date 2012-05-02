<?php
/**
 * BakedCarrot database unit
 * Keeps basic database functions (CRUD, etc)
 *
 * @package BakedCarrot
 * @subpackage Db
 */
 
class Db
{
	private static $pdo = null;
	private static $columns_meta = null;
	private static $query_cache = null;


	/**
	 * Connects to database
	 * All the parameters could also be set in config file:
	 *
	 * 		'db_dsn'		=> 'mysql:dbname=bakedcarrot;host=localhost',
	 *      'db_username' 	=> 'db_user',
	 *      'db_password' 	=> 'db_password',
	 *      'db_charset' 	=> 'utf8',
	 *
	 * @static
	 * @param null $dsn DSN in PDO format
	 * @param null $username username to connect to database server
	 * @param null $password connection password
	 * @param string $charset default character set
	 * @return void
	 * @throws BakedCarrotDbException
	 */
	public static function connect($dsn = null, $username = null, $password = null, $charset = 'utf8')
	{
		if(!is_null(self::$pdo) && !$dsn) {
			return;
		}
		elseif(!is_null(self::$pdo) && $dsn) {
			self::$pdo = null;
		}
	
		if(!$dsn) {
			if(!Config::checkVar('db_dsn')) {
				throw new BakedCarrotDbException('Database access parameters are not properly configured');
			}
			
			$dsn = Config::getVar('db_dsn');
			$username = Config::getVar('db_username');
			$password = Config::getVar('db_password');
			$charset = Config::getVar('db_charset', 'utf8');
		}
		
		self::$pdo = new DbPDO($dsn, $username, $password, array(
				PDO::ATTR_ERRMODE				=> PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_DEFAULT_FETCH_MODE	=> PDO::FETCH_ASSOC,
				PDO::MYSQL_ATTR_INIT_COMMAND	=> 'set names ' . $charset,
				PDO::ATTR_AUTOCOMMIT 			=> true,
			));
		
		Log::out(__METHOD__ . ' Connected to: "' . $dsn . '"', Log::LEVEL_DEBUG);
	}


	/**
	 * Insert data into table
	 * This is shorthand for SQL INSERT
	 *
	 * @static
	 * @param string $table name of the table
	 * @param array $data_to_insert associative array of data to be inserted where keys is field name and values is the values that would be inserted
	 * @return int last insert id
	 * @throws BakedCarrotDbException
	 */
	public static function insert($table, array $data_to_insert)
	{
		self::connect();
	
		$real_field = Db::getColumns($table);
		foreach($data_to_insert as $field_name => $field_val) {
			if(!isset($real_field[$field_name])) {
				unset($data_to_insert[$field_name]);
				continue;
			}
			
			// do not insert if column == null, has default value and cannot be null
			if($real_field[$field_name]['Null'] == 'NO' && $field_val === null && $real_field[$field_name]['Default'] != null) {
				unset($data_to_insert[$field_name]);
				continue;
			}
		}
		
		if(empty($data_to_insert)) {
			throw new BakedCarrotDbException('Nothing to insert');
		}
		
		$sql = 'insert into ' . $table . ' (';
		$sql .= implode(', ', array_keys($data_to_insert));
		$sql .= ') values (';
		$sql .= implode(', ', array_fill(0, count($data_to_insert), '?'));
		$sql .= ')';
		
		self::exec($sql, array_values($data_to_insert));
		
		return self::$pdo->lastInsertId();
	}


	/**
	 * Update table with data from associative array
	 *
	 *
	 * @static
	 * @param string $table name of the table
	 * @param array $data_to_update associative array with "$field => $value"
	 * @param string $where WHERE statement that would be used in query. Make sure to user placeholders "?" to pass a value in it
	 * @param array|null $where_values array of values to replace placeholders in WHERE
	 * @return mixed
	 * @throws BakedCarrotDbException
	 */
	public static function update($table, array $data_to_update, $where = null, array $where_values = null)
	{
		self::connect();

		$real_field = Db::getColumns($table);
		foreach($data_to_update as $field_name => $field_val) {
			if(!isset($real_field[$field_name])) {
				unset($data_to_update[$field_name]);
				continue;
			}
		}
		
		if(empty($data_to_update)) {
			throw new BakedCarrotDbException('Nothing to update');
		}
		
		$where = trim($where);
		
		$sql = 'update ' . $table . ' set ';
		$sql .= implode(' = ?, ', array_keys($data_to_update)) . ' = ?';
		$sql .= !empty($where) ? ' where ' . $where : '';
		
		$values_final = array_values($data_to_update);
		if(!empty($where_values)) {
			$values_final = array_merge($values_final, $where_values);
		}

		return self::exec($sql, $values_final);
	}


	/**
	 * Deletes records from the table
	 *
	 * @static
	 * @param $table
	 * @param null $where
	 * @param array|null $values
	 * @return mixed
	 */
	public static function delete($table, $where = null, array $values = null)
	{
		self::connect();
	
		$sql = 'delete from ' . $table;
		$sql .= !empty($where) ? ' where ' . $where : '';

		return self::exec($sql, $values);
	}


	/**
	 * Returns column metadata for given table
	 *
	 * @static
	 * @param $table
	 * @param bool $refresh
	 * @return mixed
	 */
	public static function &getColumns($table, $refresh = false)
	{
		self::connect();
		
		if(!isset(self::$columns_meta[$table]) && !$refresh) {
			$rows = Db::getAll('show columns from ' . $table);
			
			foreach($rows as $row) {
				self::$columns_meta[$table][$row['Field']] = $row;
			}
		}
		
		return self::$columns_meta[$table];
	}


	/**
	 * Begins database transaction
	 *
	 * @static
	 * @return void
	 */
	public static function begin()
	{
		self::connect();

		self::$pdo->beginTransaction();
	}


	/**
	 * Commit database transaction
	 *
	 * @static
	 * @return void
	 */
	public static function commit()
	{
		self::connect();

		self::$pdo->commit();
	}


	/**
	 * Rollback database transaction
	 *
	 * @static
	 * @return void
	 */
	public static function rollback()
	{
		self::connect();

		self::$pdo->rollback();
	}


	/**
	 * Return single cell from result set
	 *
	 * @static
	 * @param $sql
	 * @param array|null $values
	 * @return mixed
	 */
	public static function getCell($sql, array $values = null)
	{
		self::connect();

		$sth = self::$pdo->prepare($sql);
		$sth->execute($values);
		
		return $sth->fetchColumn();
	}


	/**
	 * Returns one row from result set
	 *
	 * @static
	 * @param $sql
	 * @param array|null $values
	 * @return mixed
	 */
	public static function getRow($sql, array $values = null)
	{
		self::connect();

		$sth = self::$pdo->prepare($sql);
		$sth->execute($values);
		
		return $sth->fetch();
	}


	/**
	 * Returns all rows from result set
	 *
	 * @static
	 * @param $sql
	 * @param array|null $values
	 * @return array
	 */
	public static function &getAll($sql, array $values = null)
	{
		self::connect();

		$result = array();
		$sth = self::$pdo->prepare($sql);
		$sth->execute($values);
		
		while($row = $sth->fetch()) {
			$result[] = $row;
		}
		
		return $result;
	}


	/**
	 * Execute query
	 *
	 * @static
	 * @param $sql
	 * @param array|null $values
	 * @return mixed
	 */
	public static function exec($sql, array $values = null)
	{
		self::connect();

		$sth = self::$pdo->prepare($sql);
		$sth->execute($values);
		
		return $sth->rowCount();
	}


	/**
	 * Generate current date in MYSQL datetime format
	 *
	 * @static
	 * @return string
	 */
	public static function now()
	{
		return strftime("%Y-%m-%d %H:%M:%S"); 
	}


	/**
	 * Returns last prepared SQL query
	 *
	 * @static
	 * @return null
	 */
	public static function lastSql()
	{
		if(!self::$pdo) {
			return null;
		}

		return self::$pdo->lastSql();
	}
}

