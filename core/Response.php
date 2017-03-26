<?php

class Response
{
    protected static $messages = array(
        100 => 'Continue',
        101 => 'Switching Protocols',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        307 => 'Temporary Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        509 => 'Bandwidth Limit Exceeded',
    );
    protected $_header;
    protected $_body = '';
    public static $_status = 200;
    public static $content_type = 'application/json';
    public static $charset = 'utf-8';

    public static function factory(array $config = array())
    {
        return new self($config);
    }

    public function __construct(array $config = array())
    {
        $this->_header = new ArrayObject();
        foreach ($config as $key => $value) {
            if (property_exists($this, $key)) {
                if ($key == '_header') {
                    $this->headers($value);
                } else {
                    $this->$key = $value;
                }
            }
        }
    }

    public static function HTTPError($code, $message = '')
    {
        return self::factory()->error($code, $message);
    }

    public function error($code, $message = '') {
        return $this->status($code)->body($message);
    }

    public function body($content = null)
    {
        if ($content === null) {
            return $this->_body;
        }
        if ($this->status() !== 200) {
            $content = array('error' => array('code' => $this->status(), 'message' => $content));
        }
        $this->_body = json_encode($content);

        return $this;
    }

    public function status($status = null)
    {
        if ($status === null) {
            return self::$_status;
        } elseif (array_key_exists($status, self::$messages)) {
            self::$_status = (int) $status;

            return $this;
        } else {
            self::$_status = 500;
        }
    }

    public function cors($allowed_headers = null, $exposed_headers = null)
    {
        $h1 = "";
        $h2 = "";
        if (is_string($allowed_headers)) {
            $h1 = ", " . $allowed_headers;
        } elseif (is_array($allowed_headers)) {
            $h1 = ", " . implode(", ", $allowed_headers);
        }
        if (is_string($exposed_headers)) {
            $h2 = ", " . $exposed_headers;
        } elseif (is_array($exposed_headers)) {
            $h2 = ", " . implode(", ", $exposed_headers);
        }
        $this->header("Access-Control-Allow-Headers", "Accept, Authorization, Content-Type, Origin, X-Requested-With" . $h1);
        $this->header("Access-Control-Allow-Methods", "GET, POST, PATCH, PUT, DELETE, OPTIONS");
        $this->header("Access-Control-Expose-Headers", "X-Total-Count" . $h2);

        return $this;
    }

    public function headers($key = null, $value = null)
    {
        if ($key === null) {
            return $this->_header;
        } elseif (is_array($key)) {
            $this->_header->exchangeArray(array_change_key_case($key, CASE_LOWER));

            return $this;
        } elseif ($value === null) {
            return Arr::get($this->_header, $key);
        }
        $this->_header[$key] = $value;

        return $this;
    }
    public function header($key = null, $value = null)
    {
        $this->headers($key, $value);
    }

    public function send_headers($replace = false)
    {
        $protocol = 'HTTP/1.1';
        $status = $this->status();
        $processed_headers = array($protocol.' '.$status.' '.self::$messages[$status]);
        $headers = $this->headers();
        foreach ($headers as $header => $value) {
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            $processed_headers[] = str_replace(' ', '-', ucwords(str_replace('-', ' ', $header))).': '.$value;
        }
        if (!isset($headers['content-type'])) {
            $processed_headers[] = 'Content-Type: '.self::$content_type.'; charset='.self::$charset;
        }
        if (headers_sent()) {
            return $this;
        }

        foreach ($processed_headers as $key => $line) {
            header($line, $replace);
        }

        return $this;
    }
}
