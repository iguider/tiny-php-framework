<?php

class Route
{
    const REGEX_SEGMENT = '[^/.,;?\n]++';
    const REGEX_ESCAPE = '[.\+*?[^\]${}=!|]'; // special characters except <, >, (, )

    protected $_uri;
    protected $_regex;
    protected $_defaults = array();
    protected $protected = false;
    protected static $_routes = array("GET" => array(), "POST" => array(), "PUT" => array(), "DELETE" => array(), "ALL" => array());

    public function __construct($uri, $regex = null)
    {
        if (!empty($uri)) {
            $this->_uri = $uri;
        }

        $this->_regex = self::compile($uri, $regex);
    }

    public static function compile($uri, $regex)
    {
        $expression = preg_replace('#'.self::REGEX_ESCAPE.'#', '\\\\$0', $uri);

        if (strpos($expression, '(') !== false) {
            $expression = str_replace(array('(', ')'), array('(?:', ')?'), $expression);
        }

        $expression = str_replace(array('<', '>'), array('(?P<', '>'.self::REGEX_SEGMENT.')'), $expression);

        if ($regex) {
            $search = $replace = array();
            foreach ($regex as $key => $value) {
                $search[] = "<$key>".self::REGEX_SEGMENT;
                $replace[] = "<$key>$value";
            }
            $expression = str_replace($search, $replace, $expression);
        }

        return '#^'.$expression.'$#uD';
    }

    private static function set($type, $name, $uri = null, $regex = null)
    {
        return self::$_routes[$type][$name] = new self($uri, $regex);
    }

    public static function get($name, $uri = null, $regex = null)
    {
        return self::set("GET", $name, $uri, $regex);
    }

    public static function post($name, $uri = null, $regex = null)
    {
        return self::set("POST", $name, $uri, $regex);
    }

    public static function put($name, $uri = null, $regex = null)
    {
        return self::set("PUT", $name, $uri, $regex);
    }

    public static function del($name, $uri = null, $regex = null)
    {
        return self::set("DELETE", $name, $uri, $regex);
    }

    public static function all($name, $uri = null, $regex = null)
    {
        return self::set("ALL", $name, $uri, $regex);
    }

    public function defaults(array $defaults = null)
    {
        if ($defaults === null) {
            return $this->_defaults;
        }

        $this->_defaults = $defaults;

        return $this;
    }

    public function protect()
    {
        $this->protected = true;
    }

    public static function all_routes($type = "GET")
    {
        return array_merge(self::$_routes[$type], self::$_routes["ALL"]);
    }

    public function matches(Request $request)
    {
        $uri = trim($request->uri(), '/');
        if (!preg_match($this->_regex, $uri, $matches)) {
            return false;
        }
        $params = array('protected' => $this->protected);
        foreach ($matches as $key => $value) {
            if (is_int($key)) {
                continue;
            }
            $params[$key] = $value;
        }
        foreach ($this->_defaults as $key => $value) {
            if (!isset($params[$key]) or $params[$key] === '') {
                $params[$key] = $value;
            }
        }
        if (!empty($params['controller'])) {
            $params['controller'] = str_replace(' ', '_', ucwords(str_replace('_', ' ', $params['controller'])));
        }

        return $params;
    }
}
