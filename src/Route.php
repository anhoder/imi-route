<?php

namespace Alan\ImiRoute;

use Closure;
use Imi\App;
use Imi\Event\Event;
use Imi\Server\Http\Route\HttpRoute;
use Imi\Server\Http\Server;
use Imi\Server\Route\RouteCallable;
use Imi\ServerManage;
use Imi\Server\Route\Annotation\Route as RouteAnnotation;
use Imi\Util\Imi;

class Route
{
    protected $server;

    /**
     * 路由数组
     * [
     *     [annotation => Annotation/Route, callback => RouteCallable, middleware => []]
     * ]
     * @var array
     */
    protected $routes = [];

    /**
     * group栈
     * @var array
     */
    public $groupStack = [];


    /**
     * 初始化，将路由注入到HttpRoute中
     * @param string $serverName
     * @param string $routeFile
     */
    public static function init(string $serverName = 'main', string $routeFile = '')
    {
        Event::one('IMI.INIT.WORKER.BEFORE', function () use ($routeFile, $serverName) {
            // 默认获取APP下的route/route.php
            if (!$routeFile) {
                $routeFile = rtrim(Imi::getNamespacePath(App::getNamespace()), DIRECTORY_SEPARATOR) .
                    DIRECTORY_SEPARATOR . 'route/route.php';
            }
            if (!file_exists($routeFile)) {
                echo "\x1b[31m路由文件不存在($routeFile)\e[0m" . PHP_EOL;
                return;
            }

            /**
             * @var $httpRoute HttpRoute
             */
            $server = ServerManage::getServer($serverName);
            if (!$server instanceof Server) $server = self::getFirstHttpServer();
            if (!$server) {
                echo "\x1b[31m未启动任何http服务器\e[0m", PHP_EOL;
                return;
            }
            $router = new static();
            $router->server = $server;

            require_once $routeFile;

            $httpRoute = $server->getBean('HttpRoute');
            foreach ($router->routes as $route) {
                if (!isset($route['annotation'], $route['callback']) ||
                    !$route['annotation'] instanceof RouteAnnotation ||
                    !$route['callback'] instanceof RouteCallable) continue;
                $options = empty($route['middleware']) ? [] : ['middlewares' => $route['middleware']];
                $httpRoute->addRuleAnnotation($route['annotation'], $route['callback'], $options);
            }

            unset($router);
        });
    }


    /**
     * 获取第一个HTTPServer
     * @return \Imi\Server\Base|null
     */
    private static function getFirstHttpServer()
    {
        $servers = ServerManage::getServers();
        foreach ($servers as $server) {
            if ($server instanceof Server) return $server;
        }

        return null;
    }


    /**
     * 路由分组
     * @param array $attributes
     * @param Closure $callback
     */
    public function group(array $attributes, Closure $callback)
    {
        $attributes = $this->handleAttributes($attributes);

        $this->groupStack[] = $attributes;
        call_user_func($callback, $this);
        array_pop($this->groupStack);
    }


    /**
     * 处理attributes
     * @param array $attributes
     * @return array
     */
    protected function handleAttributes(array $attributes)
    {
        $old = $this->groupStack ? end($this->groupStack) : [];
        if (!$this->groupStack) {
            $namespace = $this->formatNamespace($old, $attributes);
            if ($namespace) $attributes['namespace'] = $namespace;

            $prefix = $this->formatPrefix($old, $attributes);
            if ($prefix) $attributes['prefix'] = $prefix;
            unset($old['namespace']);
            unset($old['prefix']);
        }

        if (isset($attributes['domain'])) unset($old['domain']);
        if (isset($attributes['ignoreCase'])) unset($old['ignoreCase']);

        return array_merge_recursive($old, $attributes);
    }


    /**
     * 拼接命名空间
     * @param array $old
     * @param array $new
     * @return mixed|string|null
     */
    public function formatNamespace(array $old, array $new)
    {
        if (isset($new['namespace'])) {
            return isset($old['namespace']) && strpos($new['namespace'], '\\') !== 0
                ? trim($old['namespace'], '\\').'\\'.trim($new['namespace'], '\\')
                : trim($new['namespace'], '\\');
        }

        return $old['namespace'] ?? null;
    }


    /**
     * 拼接前缀
     * @param array $old
     * @param array $new
     * @return mixed|string|null
     */
    public function formatPrefix(array $old, array $new)
    {
        $oldPrefix = $old['prefix'] ?? null;

        if (isset($new['prefix'])) {
            return trim($oldPrefix, '/').'/'.trim($new['prefix'], '/');
        }

        return $oldPrefix;
    }


    /**
     * 处理回调
     * @param $callback
     * @return array
     */
    public function handleCallback($callback)
    {
        if (is_string($callback)) {
            list($controller, $action) = explode('@', $callback);
        } elseif (is_array($callback)) {
            if (count($callback) < 2) return [];
            $controller = $callback['controller'] ?? $callback[0];
            $action = $callback['action'] ?? $callback[1];
        }

        return compact('controller', 'action');
    }


    /**
     * 添加路由
     * @param mixed $method
     * @param string $url
     * @param $callback
     */
    public function addRoute($method, string $url, $callback)
    {
        list('controller' => $controller, 'action' => $action) = $this->handleCallback($callback);
        $attributes = $this->groupStack ? end($this->groupStack) : [];
        $url = trim($url, '/');

        // 中间件
        $middleware = $attributes['middleware'] ?? [];
        if (!is_array($middleware)) $middleware = [$middleware];

        // 前缀
        $prefix = $attributes['prefix'] ?? null;
        if ($prefix) $url = trim($prefix, '/') . '/' . $url;

        unset($attributes['middleware'], $attributes['prefix']);

        $data = $method ? ['url' => '/' . $url, 'method' => $method] : ['url' => '/' . $url];
        $annotation = new RouteAnnotation(array_merge($attributes, $data));
        $callable = new RouteCallable($this->server, $controller, $action);
        $this->routes[] = [
            'annotation' => $annotation,
            'callback' => $callable,
            'middleware' => $middleware
        ];
    }


    /**
     * GET
     * @param string $url
     * @param $callback
     */
    public function get(string $url, $callback)
    {
        $this->addRoute('GET', $url, $callback);
    }


    /**
     * POST
     * @param string $url
     * @param $callback
     */
    public function post(string $url, $callback)
    {
        $this->addRoute('POST', $url, $callback);
    }


    /**
     * PUT
     * @param string $url
     * @param $callback
     */
    public function put(string $url, $callback)
    {
        $this->addRoute('PUT', $url, $callback);
    }


    /**
     * PATCH
     * @param string $url
     * @param $callback
     */
    public function patch(string $url, $callback)
    {
        $this->addRoute('PATCH', $url, $callback);
    }


    /**
     * DELETE
     * @param string $url
     * @param $callback
     */
    public function delete(string $url, $callback)
    {
        $this->addRoute('DELETE', $url, $callback);
    }


    /**
     * OPTION
     * @param string $url
     * @param $callback
     */
    public function option(string $url, $callback)
    {
        $this->addRoute('OPTION', $url, $callback);
    }


    /**
     * HEAD
     * @param string $url
     * @param $callback
     */
    public function head(string $url, $callback)
    {
        $this->addRoute('HEAD', $url, $callback);
    }


    /**
     * ANY
     * @param string $url
     * @param $callback
     */
    public function any(string $url, $callback)
    {
        $this->addRoute('', $url, $callback);
    }
}
