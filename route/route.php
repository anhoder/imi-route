<?php

/**
 * @var $router Route
 */

use Alan\ImiRoute\Route;
use ImiApp\ApiServer\Controller\IndexController;
use ImiApp\ApiServer\Middleware\Test2Middleware;
use ImiApp\ApiServer\Middleware\TestMiddleware;

$router->group(['middleware' => TestMiddleware::class], function (Route $router) {


    $router->group([
        'middleware' => Test2Middleware::class,
        'ignoreCase' => true,
        'prefix' => 'prefix'
    ], function (Route $router) {
        $router->get('hi', 'ImiApp\ApiServer\Controller\IndexController@index');
    });

    $router->any('/hi/api/12465', [
        IndexController::class,
        'index'
    ]);

    $router->any('/hi/api/{time}', [
        IndexController::class,
        'api'
    ]);

    

});

$router->group([
    'prefix' => 'prefix'
], function (Route $router) {
    $router->get('/TEST/{time}', [IndexController::class, 'api']);
});
