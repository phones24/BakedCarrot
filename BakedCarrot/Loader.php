<?php
/**
 * Loader 
 *
 * Takes care of controllers loading
 *
 * @package BakedCarrot
 * 
 */
class Loader
{
	/**
	 * Invokes controller's method of given route
	 *
	 * @param Route $route
	 * @return void
	 */
	public static function invoke(Route $route)
	{
		$ctrl_file = CTRLPATH . $route->controller . EXT;
		$class_name = self::getClassNameByController($route->controller);
		
		if(!$ctrl_file) {
			throw new NotFoundException('Could not find controller "' . $route->controller . '" for route "' . $route->name . '"');
		}
		
		if(!is_readable($ctrl_file)) {
			throw new NotFoundException('Cannot load file "' . $ctrl_file . '" for route "' . $route->name . '"');
		}
		
		if(!class_exists($class_name)) {
			require $ctrl_file;
		}
		
		$ctrl_class = new $class_name(); 
		
		$methods_to_try[] = 'action' . ucfirst(Request::getMethod()) . '__' . $route->action;
		$methods_to_try[] = 'action__' . $route->action;
		
		$called = false;
		foreach($methods_to_try as $method) {
			if(method_exists($ctrl_class, $method)) {
				
				call_user_func_array(array($ctrl_class, $method), array());
				$called = true;
				
				break;
			}
		}
		
		if(!$called) {
			throw new NotFoundException('Could not find valid method of class "' . $class_name . '" to execute');
		}
		
		unset($ctrl_class);
	}
	
	
	/**
	 * Invokes exception handler
	 *
	 * @param Exception $e 
	 * @param string $handler 	name of the handler 
	 * @return void
	 */
	public static function invokeExceptionHandler($e, $handler)
	{
		$handler_file = realpath(CTRLPATH . $handler . EXT);
		$class_name = 'ExceptionHandler';
		$method = 'handler' . get_class($e);

		if(!$handler_file || !is_readable($handler_file)) {
			throw new BakedCarrotException('Could not find exception handler "' . $handler . '"');
		}
		
		if(!class_exists($class_name)) {
			require $handler_file;
		}
		
		if(!class_exists($class_name)) {
			throw new BakedCarrotException('Class not defined "' . $class_name . '"');
		}
		
		$handler_class = new $class_name(); 
		
		if(!method_exists($handler_class, $method)) {
			throw new BakedCarrotException('Could not find method "' . $method . '" of class "' . $class_name . '"');
		}
		
		call_user_func_array(array($handler_class, $method), array($e));
	}
	
	
	/**
	 * Return class name generated from controller's name
	 *
	 * @param $handler controller
	 * @return string
	 */
	private static function getClassNameByController($handler)
	{
		$class_name = str_replace(array("/", "\\", '_'), ' ', $handler);
		$class_name = ucwords($class_name);
		$class_name = str_replace(' ', '', $class_name);
		$class_name = 'Controller' . $class_name;
		
		return $class_name;
	}
}	

