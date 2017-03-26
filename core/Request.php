<?php

class Request
{
    public static $base_url;
    protected $_route;
    protected $_get;
    protected $_post;
    protected $_uri;
    protected $_protected;
    protected $_controller;
    protected $_action;
    protected $_headers = array();
    protected $_body;
    protected $_params = array();
    protected $_method;

    public static function factory()
    {
        $method = 'GET';
        if (isset($_SERVER['REQUEST_METHOD'])) {
            $method = $_SERVER['REQUEST_METHOD'];
        }

        if ($method !== 'GET') {
            $body = file_get_contents('php://input');
        }

        $uri = self::detect_uri();

        $request = new self($uri);

        $request->query($_GET)->post($_POST);

        if (isset($method)) {
            $request->method($method);
        }

        if (isset($body)) {
            $request->set_body($request->parse_body($body, $request->header("content-type")));
        }

        return $request;
    }

    public function __construct($uri)
    {
        $splitUri = explode('?', $uri);
        $uri = array_shift($splitUri);
        $this->_uri = trim($uri, '/');
    }

    private static function detect_uri()
    {
        if (!empty($_SERVER['PATH_INFO'])) {
            $uri = $_SERVER['PATH_INFO'];
        } else {
            if (isset($_SERVER['REQUEST_URI'])) {
                $uri = $_SERVER['REQUEST_URI'];

                if ($request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)) {
                    $uri = $request_uri;
                }

                $uri = rawurldecode($uri);
            } elseif (isset($_SERVER['PHP_SELF'])) {
                $uri = $_SERVER['PHP_SELF'];
            } elseif (isset($_SERVER['REDIRECT_URL'])) {
                $uri = $_SERVER['REDIRECT_URL'];
            } else {
                die('"Unable to detect the URI"');
            }

            $base_url = parse_url(self::$base_url, PHP_URL_PATH);

            if (strpos($uri, $base_url) === 0) {
                $uri = (string) substr($uri, strlen($base_url));
            }
        }

        return $uri;
    }

    public function execute()
    {
        if ($this->method() === "OPTIONS") {
            return Response::factory();
        }

        $processed = self::process($this);
        if ($processed) {
            $this->_route = $processed['route'];
            $params = $processed['params'];
            $this->_protected = $params['protected'];
            $this->_controller = $params['controller'];
            $this->_directory = Arr::get($params, 'directory', '');
            $this->_action = $params['action'];
            $this->_params = $params;
        }

        if (!$this->_route instanceof Route) {
            return Response::HTTPError(404, "Unable to find a route to match the URI: {$this->_uri}");
        }

        return $this->execute_request();
    }

    private function execute_request()
    {
        $prefix = 'Controller_';
        if ($this->_directory) {
    	    $prefix .= str_replace(array('\\', '/'), '_', trim($this->_directory, '/')).'_';
    	}

        $c = $prefix.$this->_controller;

        if (!class_exists($c)) {
            return Response::HTTPError(404, "Unable to find the controller: {$c}");
        }

        $class = new ReflectionClass($c);
        $controller = $class->newInstance();
        $response = $class->getMethod('execute')->invoke($controller, $this);
        if (!$response instanceof Response) {
            return Response::HTTPError(500, "Controller failed to return a Response");
        }

        return $response;
    }

    public function process(Request $request)
    {
        $routes = Route::all_routes($this->method());
        $params = null;

        foreach ($routes as $name => $route) {
            if ($params = $route->matches($request)) {
                return array(
                    'params' => $params,
                    'route' => $route,
                );
            }
        }

        return;
    }

    public function uri($uri = null)
    {
        if ($uri === null) {
            return ($this->_uri === '') ? '/' : $this->_uri;
        }
        $this->_uri = $uri;

        return $this;
    }

    public function method($method = null)
    {
        if ($method === null) {
            return $this->_method;
        }
        $this->_method = strtoupper($method);

        return $this;
    }

    public function params($p)
    {
        if ($p === null) {
            return $this->_params;
        }
        return Arr::get($this->_params, $p);
    }

    public function param($p)
    {
        return $this->params($p);
    }

    public function query($key = null, $value = null)
    {
        if (is_array($key)) {
            $this->_get = $key;

            return $this;
        }
        if ($key === null) {
            return $this->_get;
        } elseif ($value === null) {
            return Arr::path($this->_get, $key);
        }
        $this->_get[$key] = $value;

        return $this;
    }

    public function post($key = null, $value = null)
    {
        if (is_array($key)) {
            $this->_post = $key;

            return $this;
        }
        if ($key === null) {
            return $this->_post;
        } elseif ($value === null) {
            return Arr::path($this->_post, $key);
        }
        $this->_post[$key] = $value;

        return $this;
    }

    private static function parse_body($body, $ct)
    {
        if ($ct === "application/json") {
            return json_decode($body, true);
        } elseif ($ct === "application/x-www-form-urlencoded") {
            parse_str($body, $r);
            return $r;
        }
        return $body;
    }

    public function set_body($content)
    {
        $this->_body = $content;
        return $this;
    }

    public function body($b = null)
    {
        if (is_array($this->_body) && $b != null) {
            return Arr::path($this->_body, $b);
        }

        return $this->_body;
    }

    public function is_protected()
    {
        return $this->_protected;
    }

    public function headers($key = NULL, $value = NULL)
	{
		if (is_array($key))
		{
			$this->_headers = $key;
			return $this;
		}
		if (empty($this->_headers))
		{
			$this->_headers = self::getallheaders();
		}
		if ($key === NULL)
		{
			return $this->_headers;
		}
		elseif ($value === NULL)
		{
			return Arr::get($this->_headers, strtolower($key));
		}
		$this->_headers[strtolower($key)] = $value;

        return $this;
	}

    public function header($key = NULL, $value = NULL)
	{
        return $this->headers($key, $value);
    }

    private static function getallheaders()
    {
		if (function_exists('apache_request_headers'))
		{
			return array_change_key_case(apache_request_headers(), CASE_LOWER);
		}
		elseif (extension_loaded('http'))
		{
            return array_change_key_case(\http\Env::getRequestHeader(), CASE_LOWER);
		}
		$headers = array();
		if (!empty($_SERVER['CONTENT_TYPE']))
		{
			$headers['content-type'] = $_SERVER['CONTENT_TYPE'];
		}
		if (!empty($_SERVER['CONTENT_LENGTH']))
		{
			$headers['content-length'] = $_SERVER['CONTENT_LENGTH'];
		}
		foreach ($_SERVER as $key => $value)
		{
			if (strpos($key, 'HTTP_') !== 0)
			{
				continue;
			}
			$headers[strtolower(str_replace('_', '-', substr($key, 5)))] = $value;
		}
		return $headers;
    }

    public function action($action = null)
    {
        if ($action === null) {
            return $this->_action;
        }
        $this->_action = (string) $action;

        return $this;
    }
}
