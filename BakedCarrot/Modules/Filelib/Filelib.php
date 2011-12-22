<?php
/**
 * BakedCarrot file library module
 *
 * @package BakedCarrot
 * @subpackage Filelib
 * @author Yury Vasiliev
 * @todo add more callbacks
 *
 * 
 */

require 'FilelibException.php';


class Filelib extends Module
{
	private $current_dir = '';
	private $selected_file = '';
	private $filelib_uri = '';
	private $root_dir = '';
	private $thumbnails = false;
	private $thumbnails_width = 50;
	private $thumbnails_height = 50;
	private $size_format = "%2.2f Kb";
	private $date_format = "%d.%m.%y %H:%M";
	private $thumbnails_color = array(100, 100, 100);
	private $thumbnails_bgr = array(200, 200, 200);
	private $callbacks = array();
	
	
	public function __construct(array $params = null)
	{	
		if(!($this->root_dir = $this->loadParam('root_dir', $params)) || !is_dir($this->root_dir)) {
			throw new FilelibException('Invalid root directory: ' . $this->root_dir);
		}
		
		$this->thumbnails = $this->loadParam('thumbnails', $params, $this->thumbnails);
		$this->thumbnails_width = $this->loadParam('thumbnails_width', $params, $this->thumbnails_width);
		$this->thumbnails_height = $this->loadParam('thumbnails_height', $params, $this->thumbnails_height);
		$this->thumbnails_color = $this->loadParam('thumbnails_color', $params, $this->thumbnails_color);
		$this->size_format = $this->loadParam('size_format', $params, $this->size_format);
		$this->date_format = $this->loadParam('date_format', $params, $this->date_format);
		$this->callbacks['after_upload'] = $this->loadParam('after_upload_callback', $params);
		
		$this->root_dir = rtrim(realpath($this->root_dir), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
		$this->filelib_uri = $this->getUriByPath($this->root_dir);
		$this->current_dir = $this->root_dir;
	}

	
	public function getCurrentDir()
	{
		return $this->current_dir;
	}
	
	
	public function getCurrentUri()
	{
		return $this->getUriByPath($this->current_dir);
	}
	
	
	public function getCurrentUriRel()
	{
		return $this->getUriByPath($this->current_dir, $this->getFilelibDir());
	}
	

	public function changeDir($dir)
	{
		if(!mb_strlen($dir)) {
			return false;
		}
		
		if($dir[0] == DIRECTORY_SEPARATOR || $dir[0] == '/') {
			$dir = realpath($this->fixPath(DOCROOT . $dir));
		}
		else {
			$dir = realpath($this->fixPath($this->getCurrentDir() . $dir));
		}
		
		$dir = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
		
		if(!$this->isValidDir($dir)) {
			return false;
		}
		
		$this->current_dir = $dir;
		return true;
	}


	public function getSelectedFile()
	{
		return $this->selected_file;
	}


	public function setSelectedFile($file)
	{
		$this->selected_file = $file;
	}


	public function getFilelibUri()
	{
		return $this->filelib_uri;
	}


	public function getFilelibDir()
	{
		return $this->root_dir;
	}
	
	
	public function isValidDir($dir)
	{
		$dir = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
	
		return mb_strpos($dir, $this->root_dir) === 0 && is_dir($dir);
	}
	
	
	public function upload($src_file_path, $dst_file_name = null, $remove_orig = true, $file_mask = 0665)
	{
		if(!$dst_file_name) {
			$dst_file_path = $this->getCurrentDir() . pathinfo($src_file_path, PATHINFO_BASENAME);
		}
		else {
			$dst_file_path = $this->getCurrentDir() . $dst_file_name;
		}
		
		if(is_uploaded_file($src_file_path)) {
			if(!@move_uploaded_file($src_file_path, $dst_file_path)) {
				throw new FilelibException('Cannot move uploaded file');
			}
		}
		else {
			if($remove_orig && !rename($src_file_path, $dst_file_path)) {
				throw new FilelibException('Cannot move file ' . $src_file_path . ' to ' . $dst_file_path);
			}
			
			if(!$remove_orig && !copy($src_file_path, $dst_file_path)) {
				throw new FilelibException('Cannot copy file ' . $src_file_path . ' to ' . $dst_file_path);
			}
				
			chmod($dst_file_path, $file_mask);
		}
		
		$this->runCallback('after_upload', array($dst_file_path));
	}

	
	public function makeDir($newdir, $dir_mask = 0777) 
	{
		$newdir = trim($this->fixPath($newdir), DIRECTORY_SEPARATOR);
		$real_dir = $this->getCurrentDir() . $newdir . DIRECTORY_SEPARATOR;
		
		if(strpos($real_dir, $this->root_dir) !== 0) {
			throw new FilelibException('Invalid directory: ' . $real_dir);
		}
		
		if(is_dir($real_dir)) {
			return;
		}
		
		if(!mkdir($real_dir, $dir_mask)) {
			throw new FilelibException('Cannot create directory: ' . $real_dir);
		}
		
		@chmod($real_dir, $dir_mask);
	}
	
	
	public function removeDir($dir) 
	{
		$dir = trim($this->fixPath($dir), DIRECTORY_SEPARATOR); 
		$real_dir = $this->getCurrentDir() . $dir . DIRECTORY_SEPARATOR;
		
		if(!$this->isValidDir($real_dir)) {
			throw new FilelibException('Invalid directory: ' . $real_dir);
		}
		
		if(!$this->rmdirRecursive($real_dir)) {
			throw new FilelibException('Cannot remove directory: ' . $real_dir);
		}
	}
	
	
	public function removeFile($file) 
	{
		$file = $this->fixPath($file);
		
		if(!$file) {
			throw new FilelibException('Invalid filename: ' . $file);
		}
		
		$orig_file = realpath($this->getCurrentDir() . $file);
		$pathinfo_orig = pathinfo($orig_file);	
		
		if(!isset($pathinfo_orig['dirname']) || !$this->isValidDir($pathinfo_orig['dirname'])) {
			throw new FilelibException('Invalid file path: ' . $orig_file);
		}
		
		if(!is_file($orig_file) || !is_readable($orig_file)) {
			throw new FilelibException('File not found: ' . $orig_file);
		}
		
		$this->runCallback('before_remove_file', array($orig_file));
		
		if(!unlink($orig_file)) {
			throw new FilelibException('Cannot remove file: ' . $orig_file);
		}
		
		// remove cache files
		if($this->thumbnails) {
			$mask = $pathinfo_orig['dirname'] . DIRECTORY_SEPARATOR . '.' . $pathinfo_orig['filename'] . '__*';
			
			foreach(glob($mask) as $file) {
				unlink($file);
			}
		}
		
		$this->runCallback('after_remove_file', array($orig_file));
	}
	
	
	public function getFileList($selected_file = '')
	{
		$files = array();
		$dirs = array();
		
		if($selected_file) {
			$this->setSelectedFile($selected_file);
		}

		$current_dir_local = $this->getCurrentDir();
		
		if(!($handle = @opendir($current_dir_local))) {
			throw new FilelibException('Cannot read directory contents ' . $current_dir_local);
		}

		while(false !== ($file = readdir($handle))) { 
			if(is_dir($current_dir_local . $file)) {
				if($file == '.') {
					continue;
				}
	
				if($current_dir_local == $this->getFilelibDir() && $file == '..') {
					continue;
				}
				
				$dirs[] = array(
						'type'		=> $file == '..' ? 'level_up' : 'dir',
						'name'		=> $file,
						'uri'		=> $this->fixPath($this->getUriByPath($current_dir_local . $file)),
						'path'		=> realpath($current_dir_local . $file) . DIRECTORY_SEPARATOR,
						'info'		=> $this->getFileInfo($current_dir_local . $file),
						'selected'	=> false,
						'removable'	=> is_writable($current_dir_local . $file)
					);	    	
			}
			elseif(is_file($current_dir_local . $file)) {
				if($file[0] == '.') { // skip cache files
					continue;
				}
				
				$files[] = array(
						'type'		=> 'file',
						'name'		=> $file,
						'uri'		=> $this->fixPath($this->getUriByPath($current_dir_local . $file)),
						'path'		=> realpath($current_dir_local . $file),
						'info'		=> $this->getFileInfo($current_dir_local . $file),
						'selected'	=> $current_dir_local . $file == $this->getSelectedFile(),
						'removable'	=> is_writable($current_dir_local) && is_writable($current_dir_local . $file),
					);	    	
			}
		}
		
		closedir($handle); 
		
		uasort($files, array($this, 'sortByNameInc'));
		uasort($dirs, array($this, 'sortByNameInc'));
		
		return array_merge($dirs, $files);
	}


	public function explainUploadError($num)
	{
		switch($num) {
			case UPLOAD_ERR_INI_SIZE: 
				return 'The uploaded file exceeds the upload_max_filesize directive in php.ini';
			
			case UPLOAD_ERR_FORM_SIZE: 
				return 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form';
			
			case UPLOAD_ERR_PARTIAL: 
				return 'The uploaded file was only partially uploaded';
			
			case UPLOAD_ERR_NO_FILE: 
				return 'No file was uploaded';
			
			case UPLOAD_ERR_NO_TMP_DIR: 
				return 'Missing a temporary folder';
			
			case UPLOAD_ERR_CANT_WRITE: 
				return 'Failed to write file to disk';
			
			case UPLOAD_ERR_EXTENSION: 
				return 'A PHP extension stopped the file upload';
			
			default:
				return null;
		}
	}


	// TODO: convert DIRECTORY_SEPARATOR to / EVERYWHERE!
	private function getUriByPath($dir, $base_dir = DOCROOT)
	{
		$uri = '/' . substr($dir, strlen($base_dir));
		$uri = rtrim($uri, '/');
		$uri = $uri && is_dir($dir) ? ($uri . '/') : $uri;
		
		return $uri;
	}
	
	
	private function getFileInfo($file)
	{
		$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
		$file_info = stat($file);
		$image_info = @getimagesize($file);

		$w_orig = $h_orig = 0; 
		$thmb_uri = '';
		$thmb_path = '';
		
		if(!$image_info) {
			if($this->thumbnails && is_file($file)) {
				$thmb_path = $this->createThmbFileFromExtension($file);
				$thmb_uri = $this->getUriByPath($thmb_path);
			}
		}
		elseif($image_info) {
			$w_orig = $image_info[0];
			$h_orig = $image_info[1];
			
			if($this->thumbnails) {
				$thmb_path = $this->createThmbFileFromImage($file);
				$thmb_uri = $this->getUriByPath($thmb_path);
			}
		}
		
		return array(
				'ext'				=> $ext,
				'width'				=> $w_orig,
				'height' 			=> $h_orig,
				'thumbnail_uri'		=> $thmb_uri,
				'thumbnail_path'	=> $thmb_path,
				'is_image'			=> $image_info ? true : false,
				'size'				=> sprintf($this->size_format,  $file_info[7] / 1024),
				'date'				=> strftime($this->date_format, $file_info['mtime']),
			);
	}
	
	
	private function createThmbFileFromExtension($file)
	{
		$cache_file = $this->getCachedFileName($file);
		$string = strtoupper(pathinfo($file, PATHINFO_EXTENSION));

		if(!is_file($cache_file)) {
			$ipr = App::module('Image');
			$ipr->createIcon($string, $this->thumbnails_color, $this->thumbnails_bgr, $this->thumbnails_width, $this->thumbnails_height)
				->saveAs($cache_file);
			unset($ipr);
		}
		
		return $cache_file;
	}


	private function createThmbFileFromImage($file)
	{
		$cache_file = $this->getCachedFileName($file);

		if(!is_file($cache_file)) {
			$ipr = App::module('Image');
			$ipr->loadFromFile($file)
				->saveAsThumbnail($cache_file, $this->thumbnails_width, $this->thumbnails_height);
			unset($ipr);
		}
		
		return $cache_file;
	}
	
	
	private function getCachedFileName($file)
	{
		$path_parts = pathinfo($file);

		return $path_parts['dirname'] . '/' . ($path_parts['filename'][0] != '.' ? '.' : '') . $path_parts['filename'] . '__' .  $this->thumbnails_width . 'x' . $this->thumbnails_height . '.jpg';
	}
	
	
	private function sortByDateDesc($a, $b) 
	{                    
		if ($a['filedate'] == $b['filedate']) {
			return 0;
		}
		
		return ($a['filedate'] > $b['filedate']) ? -1 : 1;
	}

	
	private function sortByDateInc($a, $b) 
	{
		if ($a['filedate'] == $b['filedate']) {
			return 0;
		}
		
		return ($a['filedate'] < $b['filedate']) ? -1 : 1;
	}
	
	
	private function sortByNameInc($a, $b) 
	{ 
		return strcmp($a['name'], $b['name']); 
	} 

	
	private function fixPath($path)
	{
		if(empty($path)) {
			return '';
		}
			
		$new_path_array = array();
		$path_array = preg_split('/\//', $path);

		foreach($path_array as $p) {
			if($p == '..') {
				array_pop($new_path_array);
			}
			else {
				array_push($new_path_array, $p);
			}
		}

		$path = implode($new_path_array, '/');
		$path = preg_replace("/\/\./", '', $path);
		$path = preg_replace("/\/{2,}/", '/', $path);
		
		return $path;
	}
	
	
	private function rmdirRecursive($dir) 
	{ 
		if(is_dir($dir)) { 
			$items = scandir($dir); 
			
			foreach($items as $item) { 
				if($item != '.' && $item != '..') { 
					if(filetype($dir . DIRECTORY_SEPARATOR . $item) == 'dir') {
						$this->rmdirRecursive($dir . DIRECTORY_SEPARATOR . $item); 
					}
					else {
						unlink($dir . DIRECTORY_SEPARATOR . $item); 
					}
				} 
			}
			
			reset($items); 
			
			if(!rmdir($dir)) {
				return false;
			} 
			
			return true;
		} 

		return false;
	} 
	
	
	private function runCallback($name, $params)
	{
		if(!isset($this->callbacks[$name])) {
			return;
		}
		
		call_user_func_array($this->callbacks[$name], $params);
	}

}

