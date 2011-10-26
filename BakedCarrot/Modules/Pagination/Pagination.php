<?php
/**
 * BakedCarrot pagination module
 *
 * @package BakedCarrot
 * @subpackage Pagination
 * @author Yury Vasiliev
 * @version 0.3
 *
 *
 * 
 */
class Pagination extends Module
{
	protected $page = null;
	protected $rows_count = null;
	protected $query_param = null;
	protected $pages_count = null;
	protected $total_count = null;
	protected $pages = array();
	protected $visible_pages = 3;
	protected $max_pages = 8;
	

	public function __construct(array $params = null)
	{
		if(!($this->rows_count = $this->loadParam('rows_count', $params))) {
			throw new InvalidArgumentException('"rows_count" is not defined');
		}
		
		if(!($this->total_count = $this->loadParam('total_count', $params))) {
			throw new InvalidArgumentException('"total_count" is not defined');
		}

		$this->page = $this->loadParam('page', $params, 1);
		$this->page = $this->page < 1 ? 1 : $this->page;
		$this->query_param = $this->loadParam('query_param', $params);
		$this->max_pages = $this->loadParam('max_pages', $params, $this->max_pages);
		$this->visible_pages = $this->loadParam('visible_pages', $params, $this->visible_pages);
		$this->visible_pages = $this->visible_pages % 2 == 0 ? ++$this->visible_pages : $this->visible_pages;
		$this->visible_pages = $this->visible_pages < 1 ? 1 : $this->visible_pages;
	}
	

	public function getPages()
	{
		if(!empty($this->pages)) {
			return $this->pages;
		}
	
		$page_array = array();
		
		if($this->getTotalPages() <= $this->max_pages) {
			for($p = 1; $p <= $this->getTotalPages(); $p++) {
				$page_array[] = array(
						'page' => $p, 
						'selected' => $p == $this->page, 
						'query_param' => $this->query_param,
						'link' => ($this->query_param ? '?' . $this->query_param : '') . ($this->query_param ? '&' : '?') . 'page=' . $p
					);
			}
		}
		else {
			$h = floor($this->visible_pages / 2);
			
			for($p = 1; $p <= $this->getTotalPages(); $p++) {
				$page_data = array();
				
				if($p == 1 || $p == $this->getTotalPages() || abs($p - $this->page) <= $h) {
					$page_data = array(
							'page' => $p, 
							'selected' => $p == $this->page, 
							'query_param' => $this->query_param, 
							'link' => ($this->query_param ? '?' . $this->query_param : '') . ($this->query_param ? '&' : '?') . 'page=' . $p
						);
				}
				elseif(abs($p - $this->page) == $h + 1) {
					$page_data = array('dots' => true);
				}

				if($page_data) {
					$page_array[] = $page_data;
				}
			}
		} 
		
		$this->pages = $page_array;

		return $this->pages;
	}
	
	
	public function showLeftArrow()
	{
		return $this->page > 1; 
	}
	
	
	public function showRightArrow()
	{
		return $this->page < $this->getTotalPages(); 
	}
	
	
	public function getPrevLink()
	{
		return ($this->query_param ? '?' . $this->query_param : '') . ($this->query_param ? '&' : '?') . 'page=' . ($this->page - 1);
	}
	
	
	public function getNextLink()
	{
		return ($this->query_param ? '?' . $this->query_param : '') . ($this->query_param ? '&' : '?') . 'page=' . ($this->page + 1);
	}
	
	
	public function getTotalPages()
	{
		if(is_null($this->pages_count)) {
			$this->pages_count = ceil($this->total_count / $this->rows_count);
		}
		
		return $this->pages_count;
	}
	
	
	public function getRowsCount()
	{
		return $this->rows_count;
	}
	
	
	public function getCurrentPage()
	{
		return $this->page;
	}
	
	
	public function showPages()
	{
		return $this->getTotalPages() > 1;
	}

}
