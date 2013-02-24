<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__.'/../../vendor/autoload.php'; 

$app = new Silex\Application(); 

$app['app.url'] = 'http://localhost';
$app['app.socketio.address'] = 'http://localhost:1337';
$app['request'] = $app->share(function($app){
	return new Request($_GET, $_POST, array(), $_COOKIE, $_FILES, $_SERVER);
});
		
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/../private/templates',
));

$app['elephant'] = new ElephantIO\Client($app['app.socketio.address'], 'socket.io', 1, false, true, true);

$app['messages'] = $app->share(function($app){
	$mongo_db = new Mongo();
	$app['db'] = $mongo_db->selectDB('discuss');
	return $app['db']->selectCollection('messages');
});

$app->get('/', function() use($app) {
	$messages = $app['messages']->find(array(),array());
    return $app['twig']->render('home.twig.html', array('messages' => $messages));
}); 

$app->post('/', function() use($app) {
		
	$message = array('message' => $app['request']->get('message'), 'user' => $app['request']->get('user') );
    $result = $app['messages']->insert($message);    

	$app['elephant']->init();
	$app['elephant']->emit(
	  'new_message',$message,null,null
	);
	$app['elephant']->close();
	
	$subRequest = Request::create('/src/public/', 'GET');
    return $app->handle($subRequest, HttpKernelInterface::SUB_REQUEST);
}); 



$app->run(); 

?>