<?
/**
 * BakedCarrot Database unit
 *
 * @package BakedCarrot
 * @author Yury Vasiliev
 *
 *
 * 
 */
 
class Db
{
	private static $pdo = null;
	private static $columns_meta = null;


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
				throw new DbException('Database access parameters are not properly configured');
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
	}
	
	
	public static function insert($table, array $data)
	{
		self::connect();
	
		$sql = 'insert into `' . Db::clean($table) . '` (';
		
		$sql1 = '';
		foreach($data as $field => $value) {
			$sql1 .= '`' . Db::clean($field) . '`, ';
		}
		
		$sql .= substr($sql1, 0, -2) . ') values (';
		$sql .= substr(str_repeat('?, ', count($data)), 0, -2) . ')';
		
		self::exec($sql, array_values($data));
		
		return self::$pdo->lastInsertId();
	}
	
	
	public static function update($table, array $data_to_update, $where = null, array $where_values = null)
	{
		self::connect();

		$sql = 'update `' . Db::clean($table) . '` set ';
		
		$sql1 = '';
		foreach($data_to_update as $field => $val) {
			$sql1 .= '`' . Db::clean($field) . '` = ?, ';
		}
		
		$sql .= substr($sql1, 0, -2);
		$sql .= !empty($where) ? ' where ' . $where : '';
	
		$values_final = array_values($data_to_update);
		
		if(!empty($where_values)) {
			$values_final = array_merge($values_final, $where_values);
		}
	
		return self::exec($sql, $values_final);
	}
	
	
	public static function delete($table, $where = null, array $values = null)
	{
		self::connect();
	
		$sql = 'delete from `' . Db::clean($table) . '`';
		$sql .= !empty($where) ? ' where ' . $where : '';

		return self::exec($sql, $values);
	}


	public static function getColumns($table, $refresh = false)
	{
		self::connect();
		
		$table = Db::clean($table);
		
		if(!isset(self::$columns_meta[$table]) && !$refresh) {
			$rows = Db::getAll('show columns from `' . $table . '`');
			
			foreach($rows as $row) {
				self::$columns_meta[$table][$row['Field']] = $row;
			}
		}
		
		return self::$columns_meta[$table];
	}

	
	public static function begin()
	{
		self::connect();

		self::$pdo->beginTransaction();
	}
	
	
	public static function commit()
	{
		self::connect();

		self::$pdo->commit();
	}
	
	
	public static function rollback()
	{
		self::connect();

		self::$pdo->rollback();
	}


	public static function getCol($sql, array $values = null)
	{
		self::connect();

		$sth = self::$pdo->prepare($sql);
		$sth->execute($values);
		
		return $sth->fetchColumn();
	}
	
	
	public static function getRow($sql, array $values = null)
	{
		self::connect();

		$sth = self::$pdo->prepare($sql);
		$sth->execute($values);
		
		return $sth->fetch();
	}
	
	
	public static function getAll($sql, array $values = null)
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
	
	
	public static function exec($sql, array $values = null)
	{
		self::connect();

		$sth = self::$pdo->prepare($sql);
		$sth->execute($values);
		
		return $sth->rowCount();
	}


	public static function expr($expr, $values = null)
	{
		return self::getCol('select ' . $expr, $values);
	}

	
	public static function clean($str)
	{
		return str_replace(' ', '', trim($str)); 
	}

	
	public static function now()
	{
		return strftime("%Y-%m-%d %H:%M:%S"); 
	}
}

?>