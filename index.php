<?
define('MEM_PEAK_START', memory_get_peak_usage());
define('TIME_START', microtime(true));

define('DOCROOT', realpath(dirname(__FILE__)) . DIRECTORY_SEPARATOR);
define('SYSPATH', DOCROOT . 'BakedCarrot' . DIRECTORY_SEPARATOR);	
define('APPPATH', DOCROOT . 'app' . DIRECTORY_SEPARATOR);
define('TMPPATH', DOCROOT . 'tmp' . DIRECTORY_SEPARATOR);

require 'appinit.php';
/*	
print "\n\n<pre>";
print 'Mem start: ' . sprintf("%.0f Kb", MEM_PEAK_START  / 1024) . "\n";
print 'Mem peak: ' . sprintf("%.0f Kb", memory_get_peak_usage()  / 1024) . "\n";
print 'Mem app: ' . sprintf("%.0f Kb", (memory_get_peak_usage() - MEM_PEAK_START) / 1024) . "\n";
print 'Time app: ' . sprintf("%.4f", (microtime(true) - TIME_START)) . "\n";
print '</pre>';
*/
?>