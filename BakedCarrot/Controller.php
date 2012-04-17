<?php
/**
 * Base controller class
 *
 * @package BakedCarrot
 */
class Controller
{
	protected function pass()
	{
		if(ob_get_level() > 0) { 
			ob_clean(); 
		} 
		
		throw new BakedCarrotPassException();
	}
}	

