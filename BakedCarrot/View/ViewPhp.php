<?php
/**
 * BakedCarrot view PHP driver
 *
 * @package BakedCarrot
 * @subpackage View
 * 
 */
class ViewPhp extends ViewBase
{
	public function __construct()
	{
	
	}

	
	public function render($template) 
	{
		ob_start();
		
		$template_dir = realpath(Config::getVar('view_template_dir', VIEWSPATH));
		
		require $template_dir . DIRECTORY_SEPARATOR . $template;
		
		return ob_get_clean();
	}
}

