<?
	require 'DbException.php';
	require 'DbPDO.php';
	require 'Orm.php';


	class Db
	{
		private static $instance = null;
		private static $pdo = null;
		private static $columns_meta = null;
	

		private function __construct()
		{
		}
		
		
		public static function create()
		{
			if(is_null(self::$instance)) {
				self::$instance = new self();
			}
		}
		
		
		public static function connect($dsn, $username = null, $password = null)
		{
			if(!is_null(self::$pdo)) {
				unset(self::$pdo);
			}
		
			self::$pdo = new DbPDO($dsn, $username, $password, array(
					PDO::ATTR_ERRMODE				=> PDO::ERRMODE_EXCEPTION,
					PDO::ATTR_DEFAULT_FETCH_MODE	=> PDO::FETCH_ASSOC,
					PDO::MYSQL_ATTR_INIT_COMMAND	=> 'set names utf8',
				));
		}
		
		
		public static function insert($table, array $data)
		{
			self::checkConnection();
		
			$sql = 'insert into `' . $table . '` (';
			
			$sql1 = '';
			foreach($data as $field => $value) {
				$sql1 .= '`' . $field . '`, ';
			}
			
			$sql .= substr($sql1, 0, -2) . ') values (';
			$sql .= substr(str_repeat('?, ', count($data)), 0, -2) . ')';
			
			self::exec($sql, array_values($data));
			
			return self::$pdo->lastInsertId();
		}
		
		
		public static function update($table, array $data_to_update, $where = null, array $where_values = null)
		{
			self::checkConnection();

			$sql = 'update `' . $table . '` set ';
			
			$sql1 = '';
			foreach($data_to_update as $field => $val) {
				$sql1 .= '`' . $field . '` = ?, ';
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
			self::checkConnection();
		
			$sql = 'delete from `' . $table . '`';
			$sql .= !empty($where) ? ' where ' . $where : '';

			return self::exec($sql, $values);
		}


		public static function getColumns($table, $refresh = false)
		{
			self::checkConnection();

			if(!isset(self::$columns_meta[$table]) && !$refresh) {
				$sql = 'select * from `' . $table . '` limit 1';
				$sth = self::$pdo->prepare($sql);
				$sth->execute();
				
				for($i = 0; $i < $sth->columnCount(); $i++) {
					$data = $sth->getColumnMeta($i);
					self::$columns_meta[$table][$data['name']] = $data;
				}
				
				$sth->fetch();
			}
			
	        return self::$columns_meta[$table];
		}

		
		public static function begin()
		{
			self::checkConnection();

			self::$pdo->beginTransaction();
		}
		
		
		public static function commit()
		{
			self::checkConnection();

			self::$pdo->commit();
		}
		
		
		public static function rollback()
		{
			self::checkConnection();

			self::$pdo->rollback();
		}


		public static function getCol($sql, array $values = null)
		{
			self::checkConnection();

			$sth = self::$pdo->prepare($sql);
			$sth->execute($values);
			
			return $sth->fetchColumn();
		}
		
		
		public static function getRow($sql, array $values = null)
		{
			self::checkConnection();

			$sth = self::$pdo->prepare($sql);
			$sth->execute($values);
			
			return $sth->fetch();
		}
		
		
		public static function getAll($sql, array $values = null)
		{
			self::checkConnection();

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
			self::checkConnection();

			$sth = self::$pdo->prepare($sql);
			$sth->execute($values);
			
			return $sth->rowCount();
		}


		public static function expr($expr, $values = null)
		{
			return self::getCol('select ' . $expr, $values);
		}


		public static function getPrimaryKey($table)
		{
			$row = self::getRow('show keys from `' . $table . '` where Key_name = "PRIMARY"');
			
			if(!isset($row['Column_name'])) {
				return false;
			}
			
			return $row['Column_name'];
		}

		private static function checkConnection()
		{
			if(is_null(self::$pdo)) {
				throw new DbException('Database connection has not been set');
			}
		}

	}

?>