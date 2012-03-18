<?php
/**
 * BakedCarrot image manipulation module
 *
 * @package BakedCarrot
 * @subpackage Image
 */

require 'ImageException.php';
	
	
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
	protected $dir_mask = 0777;
	protected $file_mask = 0665;
	protected $width;
	protected $height;
	protected $type;
	protected $mime;
	
	
	public function __construct(array $params = null)
	{
		if(!$info = @gd_info()) {
			throw new ImageException('GD extension not installed');
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
			throw new ImageException('File is unreadable: ' . $file);
		}
		
		$this->image_info = @getimagesize($file);
		
		if(!$this->image_info) {
			throw new ImageException('Invalid file format');
		}
		
		if(!$this->formatSupported($this->image_info[2])) {
			throw new ImageException('File format not supported');
		}

		$this->image_file = $file;
		$this->loadData();
		
		return $this;
	}
	
	
	public function loadFromUrl($url)
	{
		if(strpos($url, 'http://') !== 0 && strpos($url, 'https://') !== 0) {
			throw new ImageException('Wrong url format');
		}
	
		if(!($result = @file_get_contents($url))) {
			throw new ImageException('Cannot load image or file format not supported');
		}
		
		if(($this->handle = imagecreatefromstring($result)) === false) {
			throw new ImageException('Cannot create image from url');
		}
		
		unset($result);
		
		$this->width = imagesx($this->handle); 
		$this->height = imagesy($this->handle); 
		
		return $this;
	}
	
	
	public function upload($file, $dest, $remove_orig = true)
	{
		if(!is_readable($file)) {
			throw new ImageException('File is unreadable: ' . $file);
		}
		
		$this->image_info = @getimagesize($file);
		
		if(!$this->image_info) {
			throw new ImageException('Invalid file format');
		}
		
		if(!$this->formatSupported($this->image_info[2])) {
			throw new ImageException('File format not supported');
		}
		
		$orig_file_info = pathinfo($dest);
		
		if(!is_dir($orig_file_info['dirname'])) {
			if(!mkdir($orig_file_info['dirname'], $this->dir_mask, true)) {
				throw new ImageException('Cannot create directory: ' . $orig_file_info['dirname']);
			}
		}
		
		if(is_dir($dest)) {
			$ext = isset($orig_file_info['extension']) ? $orig_file_info['extension'] : 'jpg';
			$dest = rtrim($dest, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $orig_file_info['filename'] . '.' . $ext;
		}
		
		if(is_uploaded_file($file)) {
			if(!move_uploaded_file($file, $dest)) {
				throw new ImageException('File upload error: ' . $file);
			}
		}
		else {
			if($remove_orig && !rename($file, $dest)) {
				throw new ImageException('Cannot move file ' . $file . ' to ' . $dest);
			}
			
			if(!$remove_orig && !copy($file, $dest)) {
				throw new ImageException('Cannot copy file ' . $file . ' to ' . $dest);
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
			throw new ImageException('Invalid "base" parameter');
		}
		
		$new_width = floor($this->width / $coef);
		$new_height = floor($this->height / $coef);
		
		$im_new = $this->createEmpty($new_width, $new_height);
		
		if(!imagecopyresampled($im_new, $this->handle, 0, 0, 0, 0, $new_width, $new_height, $this->width, $this->height)) {
			throw new ImageException('Image resizing failed');
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
		
		$im_new = $this->createEmpty($width, $height);
		
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
			throw new ImageException('Image cropping failed');
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
			throw new ImageException('Image sharpen failed');
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
			throw new ImageException('Cannot apply filter');
		}
		 
		return $this;
	}
	
	
	public function createIcon($string, $text_color, $bgr_color, $width = 100, $height = 100, $quality = 50)
	{
		if(!is_null($this->handle)) {
			imagedestroy($this->handle);
		}
		
		$this->handle = $this->createEmpty($width, $height);
		
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
			if(!mkdir($file_info['dirname'], $this->dir_mask, true)) {
				throw new ImageException('Cannot create directory: ' . $file_info['dirname']);
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
			throw new ImageException('Cannot save file ' . $file);
		}
		
		$this->mime = image_type_to_mime_type($this->type);
		
		return $this;
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
			throw new ImageException('Cannot load image data from file ' . $this->image_file);
		}
	}
	
	
	private function createEmpty($width, $height)
	{
		$image = imagecreatetruecolor($width, $height);
		
		imagealphablending($image, false);
		imagesavealpha($image, true);
		
		if(function_exists('imageantialias')) {
			imageantialias($image, true);
		}
		
		return $image;
	}


	private function formatSupported($format)
	{
		return in_array($format, $this->format_supported);
	}
	
	
	private function checkHandle()
	{
		if(is_null($this->handle)) {
			throw new ImageException('No image loaded');
		}
	}

}
