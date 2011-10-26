<?php
/**
 * BakedCarrot view PHPTAL driver
 *
 * @package BakedCarrot
 * @subpackage View
 * @author Yury Vasiliev
 *
 *
 * 
 */
class ViewPHPTAL extends ViewBase
{
	private $tmpl = null;
	
	
	public function __construct()
	{
		require_once VENDPATH . 'PHPTAL/PHPTAL.php';

		$this->tmpl = new PHPTAL(); 
		$this->tmpl->setTemplateRepository(Config::getVar('view_template_dir', VIEWSPATH));
		
		if(Config::checkVar('view_cache_dir')) {
			$this->tmpl->setPhpCodeDestination(Config::getVar('view_cache_dir'));
		}
	}
	
	
	public function getDriver()
	{
		return $this->tmpl;
	}	
	
	
	public function render($template) 
	{
		foreach($this->data as $key => $val) {
			$this->tmpl->set($key, $val);
		}
	
		$this->data = array();
		$this->tmpl->setTemplate($template);
		
		return $this->tmpl->execute();
	}
}

