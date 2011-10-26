<?php
class ControllerIndex extends Controller
{
	public function actionGet__index()
	{
		$view = new View();
		
		$data['test_var'] = 'Hello!';
		
		echo $view->render('index.php', $data);	
	}
}
