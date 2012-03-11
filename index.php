<?php
/**
 * BakedCarrot bootstrap file
 *
 * @package BakedCarrot
 */

define('DOCROOT', realpath(dirname(__FILE__)) . DIRECTORY_SEPARATOR);
define('SYSPATH', DOCROOT . 'BakedCarrot' . DIRECTORY_SEPARATOR);	
define('APPPATH', DOCROOT . 'app' . DIRECTORY_SEPARATOR);
define('TMPPATH', DOCROOT . 'tmp' . DIRECTORY_SEPARATOR);

require APPPATH . 'appinit.php';
