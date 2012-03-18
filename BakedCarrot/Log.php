<?php
class Log
{
	const LEVEL_CRIT = 3;
	const LEVEL_WARN = 2;
	const LEVEL_INFO = 1;
	const LEVEL_DEBUG = 0;
	
	private static $instance = null;
	private static $file = null;
	private static $dir = 'logs';
	private static $file_format = 'log_%Y-%m-%d.txt';
	private static $min_level = null;
	private static $enabled = null;
	
	
	private function __construct()
	{
	}
	
	
	public static function create()
	{
		if(!is_null(self::$instance)) {
			return self::$instance;
		}

		self::$instance = new self;
		self::$file = DOCROOT . self::$dir . DIRECTORY_SEPARATOR . strftime(self::$file_format); 
		
		self::setEnabled(Config::getVar('log_enabled', false));
		self::setLevel(Config::getVar('log_level', self::LEVEL_INFO));
		
		if(self::$enabled && is_file(self::$file)) {
			@error_log("\n", 3, self::$file);
		}

		return self::$instance;
	}

	
	public static function setEnabled($enabled = true)
	{
		self::$enabled = $enabled;
	}
	
	
	public static function setFile($file)
	{
		self::$file = $file;
		
		if(self::$enabled) {
			@error_log("\n", 3, self::$file);
		}
	}
	
	
	public static function setLevel($min_level)
	{
		self::$min_level = $min_level;
	}
	
	
	public static function setDir($dir)
	{
		self::$dir = $dir;
	
		self::$file = DOCROOT . self::$dir . DIRECTORY_SEPARATOR . strftime(self::$file_format); 
	}
	
	
	public static function out($val, $level = self::LEVEL_INFO)
	{
		if(!self::$enabled) {
			return;
		}
		
		self::create();
		
		if($level >= self::$min_level) {
			$message = '[' . $level . '][' . date('H:i:s') . ' ' . $_SERVER['REQUEST_URI'] . '] ';
			$message .= is_array($val) ? print_r($val, true) : $val;
			$message .= "\n";
			
			@error_log($message, 3, self::$file);
		}
	}
	
}

