# imi-route

The library enables the IMI framework to support the management of routes through PHP files.

## Usage

1. Install package

```sh
composer require alanalbert/imi-route
```

2. Add code 

Add the following code to the `/project/Main.php` file:

```php
<?php
namespace ImiApp;

use Imi\Main\AppBaseMain;

class Main extends AppBaseMain
{
    public function __init()
    {
        \Alan\ImiRoute\Route::init(); // Add this line
    }

}
```

3. Create `route.php` file

Create `/project/route/route.php` file(and dir).

And then, you can manage your routes through PHP files. For example:

```php
/**
 * @var $router Route
 */

use Alan\ImiRoute\Route;
use ImiApp\ApiServer\Controller\IndexController;
use ImiApp\ApiServer\Middleware\Test2Middleware;
use ImiApp\ApiServer\Middleware\TestMiddleware;

$router->group(['middleware' => TestMiddleware::class], function (Route $router) {

    $router->group(
        [
            'middleware' => Test2Middleware::class, 
            'ignoreCase' => true, 
            'prefix' => 'prefix'
        ], function (Route $router) {
        $router->get('hi', 'ImiApp\ApiServer\Controller\IndexController@index');
    });

    $router->any('/hi/api/abc', [IndexController::class, 'index']);

    $router->any('/hi/api/{time}', [IndexController::class, 'api']);

});

$router->group(['prefix' => 'prefix'], function (Route $router) {
    $router->get('/TEST/{time}', [IndexController::class, 'api']);
});
```