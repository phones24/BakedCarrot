<?php
/**
 * BakedCarrot image manipulation module
 *
 * @package BakedCarrot
 * @subpackage Image
 */

require 'BakedCarrotImageException.php';
	
	
class Image extends ParamLoader
{
	const RESIZE_BASE_MAX = 100;
	const RESIZE_BASE_MIN = 200;

	const CROP_BASE_TOPLEFT = 100;
	const CROP_BASE_CENTER = 200;
	const CROP_BASE_BOTTOMRIGHT = 300;
	
	protected $handle = null;
	protected $image_file = null;
	protected $image_info = null;
	protected $format_supported = array();
	protected $dir_mask = 0775;
	protected $file_mask = 0664;
	protected $width;
	protected $height;
	protected $type;
	protected $mime;
	
	
	public function __construct(array $params = null)
	{
		if(!$info = @gd_info()) {
			throw new BakedCarrotImageException('GD extension not installed');
		}
		
		if(imagetypes() & IMG_PNG) {
			$this->format_supported[] = IMAGETYPE_PNG;
		}
		
		if(imagetypes() & IMG_JPG) {
			$this->format_supported[] = IMAGETYPE_JPEG;
		}
		
		if(imagetypes() & IMG_GIF) {
			$this->format_supported[] = IMAGETYPE_GIF;
		}
		
		$this->setLoaderPrefix('image');
		
		$this->dir_mask = $this->loadParam('dir_mask', $params, $this->dir_mask);
		$this->file_mask = $this->loadParam('file_mask', $params, $this->file_mask);
	}
	
	
	public function __destruct()
	{
		if(!is_null($this->handle)) {
			imagedestroy($this->handle);
		}
	}
	
	
	public function loadFromFile($file)
	{
		if(!is_readable($file) || is_dir($file)) {
			throw new BakedCarrotImageException('File is unreadable: ' . $file);
		}
		
		$this->image_info = @getimagesize($file);
		
		if(!$this->image_info) {
			throw new BakedCarrotImageException('Invalid file format');
		}
		
		if(!$this->formatSupported($this->image_info[2])) {
			throw new BakedCarrotImageException('File format not supported');
		}

		$this->image_file = $file;
		$this->loadData();
		
		return $this;
	}
	
	
	public function loadFromUrl($url)
	{
		if(strpos($url, 'http://') !== 0 && strpos($url, 'https://') !== 0) {
			throw new BakedCarrotImageException('Wrong url format');
		}
	
		if(!($result = @file_get_contents($url))) {
			throw new BakedCarrotImageException('Cannot load image or file format not supported');
		}
		
		if(($this->handle = imagecreatefromstring($result)) === false) {
			throw new BakedCarrotImageException('Cannot create image from url');
		}
		
		unset($result);
		
		$this->width = imagesx($this->handle); 
		$this->height = imagesy($this->handle); 
		
		return $this;
	}
	
	
	public function upload($file, $dest, $remove_orig = true)
	{
		if(!is_readable($file)) {
			throw new BakedCarrotImageException('File is unreadable: ' . $file);
		}
		
		$this->image_info = @getimagesize($file);
		
		if(!$this->image_info) {
			throw new BakedCarrotImageException('Invalid file format');
		}
		
		if(!$this->formatSupported($this->image_info[2])) {
			throw new BakedCarrotImageException('File format not supported');
		}
		
		$orig_file_info = pathinfo($dest);
		
		if(!is_dir($orig_file_info['dirname'])) {
			if(!$this->mkdirr($orig_file_info['dirname'], $this->dir_mask)) {
				throw new BakedCarrotImageException('Cannot create directory: ' . $orig_file_info['dirname']);
			}
		}
		
		if(is_dir($dest)) {
			$ext = isset($orig_file_info['extension']) ? $orig_file_info['extension'] : 'jpg';
			$dest = rtrim($dest, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $orig_file_info['filename'] . '.' . $ext;
		}
		
		if(is_uploaded_file($file)) {
			if(!move_uploaded_file($file, $dest)) {
				throw new BakedCarrotImageException('File upload error: ' . $file);
			}
		}
		else {
			if($remove_orig && !rename($file, $dest)) {
				throw new BakedCarrotImageException('Cannot move file ' . $file . ' to ' . $dest);
			}
			
			if(!$remove_orig && !copy($file, $dest)) {
				throw new BakedCarrotImageException('Cannot copy file ' . $file . ' to ' . $dest);
			}
				
			chmod($dest, $this->file_mask);
		}
		
		$this->image_file = $dest;
		$this->loadData();
		
		return $this;
	}
	
	
	public function resize($width, $height, $base = self::RESIZE_BASE_MAX, $resize_if_smaller = false)
	{
		$this->checkHandle();

		if(!$resize_if_smaller && $this->width < $width && $this->height < $height) {
			return $this;
		}
		
		$coef = 0;
		
		if($base == self::RESIZE_BASE_MAX) {
			$coef = max($this->width / $width, $this->height / $height);
		}
		elseif($base == self::RESIZE_BASE_MIN) {
			$coef = min($this->width / $width, $this->height / $height);
		}
		
		if(!$coef) {
			throw new BakedCarrotImageException('Invalid "base" parameter');
		}
		
		$new_width = ceil($this->width / $coef);
		$new_height = ceil($this->height / $coef);
		
		$im_new = $this->newImage($new_width, $new_height);
		
		if(!imagecopyresampled($im_new, $this->handle, 0, 0, 0, 0, $new_width, $new_height, $this->width, $this->height)) {
			throw new BakedCarrotImageException('Image resizing failed');
		}
		
		imagedestroy($this->handle);
		
		$this->handle = $im_new;
		$this->width = imagesx($this->handle); 
		$this->height = imagesy($this->handle); 
		
		return $this;
	}
	
	
	public function crop($width, $height, $base = self::CROP_BASE_TOPLEFT)
	{
		$this->checkHandle();

		if($width > $this->width && $height > $this->height) {
			return $this;
		}
		
		$im_new = $this->newImage($width, $height);
		
		$ret = false;
		
		if($base == self::CROP_BASE_TOPLEFT) {
			$ret = imagecopy($im_new, $this->handle, 0, 0, 0, 0, $width, $height);
		}
		elseif($base == self::CROP_BASE_CENTER) {
			$x = round(($this->width - $width) / 2);
			$y = round(($this->height - $height) / 2);
			$ret = imagecopy($im_new, $this->handle, 0, 0, $x, $y, $width, $height);
		}
		elseif($base == self::CROP_BASE_BOTTOMRIGHT) {
			$x = $this->width - $width;
			$y = $this->height - $height;
			$ret = imagecopy($im_new, $this->handle, 0, 0, $x, $y, $width, $height);
		}
		
		if(!$ret) {
			throw new BakedCarrotImageException('Image cropping failed');
		}
		
		imagedestroy($this->handle);
		
		$this->handle = $im_new;
		$this->width = imagesx($this->handle); 
		$this->height = imagesy($this->handle); 

		return $this;
	}
	
	
	public function sharpen($amount)
	{
		$this->checkHandle();
		
		$matrix = array(
				array(-1, -1, -1),
				array(-1, $amount, -1),
				array(-1, -1, -1),
			);

		$div = array_sum(array_map('array_sum', $matrix));  
		 
		if(!imageconvolution($this->handle, $matrix, $div, 0)) {
			throw new BakedCarrotImageException('Image sharpen failed');
		}
		
		$this->width = imagesx($this->handle); 
		$this->height = imagesy($this->handle); 

		return $this;
	}
	
	
	public function filter()
	{
		$this->checkHandle();
		
		$arg_list = func_get_args();
		
		array_unshift($arg_list, $this->handle);
		
		if(!call_user_func_array('imagefilter', $arg_list)) {
			throw new BakedCarrotImageException('Cannot apply filter');
		}
		 
		return $this;
	}
	
	
	public function createIcon($string, $text_color, $bgr_color, $width = 100, $height = 100, $quality = 50)
	{
		if(!is_null($this->handle)) {
			imagedestroy($this->handle);
		}
		
		$this->handle = $this->newImage($width, $height);
		
		$font_size = 5;
		
		$background_color = imagecolorallocate($this->handle, $bgr_color[0], $bgr_color[1], $bgr_color[2]);
		$text_color = imagecolorallocate($this->handle, $text_color[0], $text_color[1], $text_color[2]);
		$text_width = imagefontwidth($font_size) * strlen($string); 
		
		$x = ($width / 2) - ($text_width / 2);
		$y = ($height / 2) - (imagefontheight($font_size) / 2);
		
		imagefill($this->handle, 1, 1, $background_color);
		imagestring($this->handle, $font_size, $x, $y, $string, $text_color);
		
		$this->width = $width;
		$this->height = $height;
		$this->type = null;
		$this->mime = null;
		
		return $this;
	}
	
	
	public function saveAsThumbnail($file, $width = 100, $height = 100, $quality = 60)
	{	
		if(is_null($file)) {
			$file = $this->image_file;
		}
		
		return $this->resize($width, $width, self::RESIZE_BASE_MAX)
			->crop($width, $width, self::CROP_BASE_CENTER)
			->sharpen(20)
			->saveAs($file, $quality);
	}
	
	
	public function save($quality = 90)
	{
		$this->saveAs($this->image_file, $quality);
		
		return $this;
	}
	
	
	public function saveAs($file, $quality = 90)
	{
		$res = false;
		
		$file_info = pathinfo($file);
		
		if(!is_dir($file_info['dirname'])) {
			if(!$this->mkdirr($file_info['dirname'], $this->dir_mask)) {
				throw new BakedCarrotImageException('Cannot create directory: ' . $file_info['dirname']);
			}
		}
		
		$type = $file_info['extension'];
		$type = empty($type) ? 'jpg' : $type;
		
		switch($type) {
			case 'gif':
				$this->type = IMAGETYPE_GIF;
				$res = imagegif($this->handle, $file);
				break;
		
			case 'png':
				$this->type = IMAGETYPE_PNG;
				$res = imagepng($this->handle, $file, round($quality / 10));
				break;
			
			case 'jpg':
			case 'jpeg':
				$this->type = IMAGETYPE_JPEG;
				$res = imagejpeg($this->handle, $file, $quality);
				break;

			default:
				break;
		}
		
		if($res == false) {
			throw new BakedCarrotImageException('Cannot save file ' . $file);
		}
		
		chmod($file, $this->file_mask);
		
		$this->mime = image_type_to_mime_type($this->type);
		
		return $this;
	}
	

	public function alphaMask(Image $mask) 
	{
		$new_img = $this->newImage($this->getWidth(), $this->getHeight());
		
		// resize the mask if it's not the same size
		if($mask->getWidth() != $this->getWidth() || $mask->getHeight() != $this->getHeight()) {
			$mask->resize($this->getWidth(), $this->getHeight(), self::RESIZE_BASE_MIN, true);
		}
		
		// put the mask on
		for($x = 0; $x < $this->getWidth(); $x++) {
			for($y = 0; $y < $this->getHeight(); $y++) {
				$alpha = imagecolorsforindex($mask->getHandle(), imagecolorat($mask->getHandle(), $x, $y));
				$alpha = 127 - floor($alpha[ 'red' ] / 2);
				$color = imagecolorsforindex($this->getHandle(), imagecolorat($this->getHandle(), $x, $y));
				$new_color = imagecolorallocatealpha($new_img, $color['red'], $color['green'], $color['blue'], $alpha);
				
				imagesetpixel($new_img, $x, $y, $new_color);
			}
		}
		
		imagecopy($this->getHandle(), $new_img, 0, 0, 0, 0, $this->getWidth(), $this->getHeight());
		imagedestroy($new_img);
	}

	
	public function getHandle()
	{
		return $this->handle;
	}


	public function getFilePath()
	{
		return $this->image_file;
	}


	public function getFileUri()
	{
		return '/' . substr($this->image_file, strlen(DOCROOT));
	}


	public function getWidth()
	{
		return $this->width;
	}


	public function getHeight()
	{
		return $this->height;
	}


	public function getAsString()
	{
		return $this->height;
	}


	public function createEmpty($width, $height)
	{
		if(!is_null($this->handle)) {
			imagedestroy($this->handle);
		}
		
		$this->handle = imagecreatetruecolor($width, $height);
		
		$this->width = $width;
		$this->height = $height;
		$this->type = null;
		$this->mime = null;

		return $this;
	}


	private function newImage($width, $height)
	{
		$image = imagecreatetruecolor($width, $height);
		
		imagealphablending($image, false);
		imagesavealpha($image, true);
		
		if(function_exists('imageantialias')) {
			imageantialias($image, true);
		}
		
		return $image;
	}


	private function loadData()
	{
		if(!is_null($this->handle)) {
			imagedestroy($this->handle);
		}
		
		$this->width = $this->image_info[0];
		$this->height = $this->image_info[1];
		$this->type = $this->image_info[2];
		$this->mime = image_type_to_mime_type($this->type);
		
		if($this->type == IMAGETYPE_GIF) {
			$this->handle = imagecreatefromgif($this->image_file);
		}
		elseif($this->type == IMAGETYPE_PNG) {
			$this->handle = imagecreatefrompng($this->image_file);
		}
		elseif($this->type == IMAGETYPE_JPEG) {
			$this->handle = imagecreatefromjpeg($this->image_file);
		}
		
		if(is_null($this->handle)) {
			throw new BakedCarrotImageException('Cannot load image data from file ' . $this->image_file);
		}
	}
	
	
	private function formatSupported($format)
	{
		return in_array($format, $this->format_supported);
	}
	
	
	private function checkHandle()
	{
		if(is_null($this->handle)) {
			throw new BakedCarrotImageException('No image loaded');
		}
	}
	
	
	private function mkdirr($pathname, $mode = 0777)
	{
		if(empty($pathname)) {
			return false;
		}
	 
		if(@is_file($pathname)) {
			return false;
		}
	 
		$next_pathname = substr($pathname, 0, strrpos($pathname, DIRECTORY_SEPARATOR));
		if($this->mkdirr($next_pathname, $mode)) {
			if(!@file_exists($pathname)) {
				if(@mkdir($pathname, $mode)) {
					chmod($pathname, $mode);
				}
			}
		}
	 
		return true;
	}


}
