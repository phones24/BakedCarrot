<?
/**
 * BakedCarrot view Smarty driver
 *
 * @package BakedCarrot
 * @subpackage View
 * @author Yury Vasiliev
 *
 *
 * 
 */
class ViewSmarty extends ViewBase
{
	private $smarty = null;
	
	
	public function __construct()
	{
		require_once VENDPATH . 'Smarty/Smarty.class.php';

		$this->smarty = new Smarty();
		$this->smarty->setTemplateDir(Config::getVar('view_template_dir', VIEWSPATH));
		
		if(Config::checkVar('view_compile_dir')) {
			$this->smarty->setCompileDir(Config::getVar('view_compile_dir'));
		}
		
		if(Config::checkVar('view_cache_dir')) {
			$this->smarty->caching = true;
			$this->smarty->setCacheDir(Config::getVar('view_cache_dir'));
		}
		
		if(Config::checkVar('view_cache_lifetime')) {
			$this->smarty->cache_lifetime = Config::getVar('view_cache_lifetime');
		}
		
		if(Config::checkVar('view_debugging')) {
			$this->smarty->debugging = Config::getVar('view_debugging');
		}
	}
	
	
	public function getDriver()
	{
		return $this->smarty;
	}	
	
	
	public function render($template) 
	{
		$this->smarty->assign($this->data);
		
		return $this->smarty->fetch($template);
	}
}

?>