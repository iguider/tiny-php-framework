<?php

class Arr
{
    public static $delimiter = ".";

    /**
     * Gets a value from an array using a dot separated path.
     *
     *     // Get the value of $array['foo']['bar']
     *     $value = Arr::path($array, 'foo.bar');
     *
     * Using a wildcard "*" will search intermediate arrays and return an array.
     *
     *     // Get the values of "color" in theme
     *     $colors = Arr::path($array, 'theme.*.color');
     *
     *     // Using an array of keys
     *     $colors = Arr::path($array, array('theme', '*', 'color'));
     *
     * @param array  $array     array to search
     * @param mixed  $path      key path string (delimiter separated) or array of keys
     * @param mixed  $default   default value if the path is not set
     * @param string $delimiter key path delimiter
     *
     * @return mixed
     */
    public static function path($array, $path, $default = null, $delimiter = null)
    {
        if (!self::is_array($array)) {
            return $default;
        }
        if (is_array($path)) {
            $keys = $path;
        } else {
            if (array_key_exists($path, $array)) {
                return $array[$path];
            }
            if ($delimiter === null) {
                $delimiter = self::$delimiter;
            }
            $path = ltrim($path, "{$delimiter} ");
            $path = rtrim($path, "{$delimiter} *");
            $keys = explode($delimiter, $path);
        }
        do {
            $key = array_shift($keys);
            if (ctype_digit($key)) {
                $key = (int) $key;
            }
            if (isset($array[$key])) {
                if ($keys) {
                    if (self::is_array($array[$key])) {
                        $array = $array[$key];
                    } else {
                        break;
                    }
                } else {
                    return $array[$key];
                }
            } elseif ($key === '*') {
                $values = array();
                foreach ($array as $arr) {
                    if ($value = self::path($arr, implode('.', $keys))) {
                        $values[] = $value;
                    }
                }
                if ($values) {
                    return $values;
                } else {
                    break;
                }
            } else {
                break;
            }
        } while ($keys);
        return $default;
    }

    public static function get($array, $key, $default = null)
    {
        return isset($array[$key]) ? $array[$key] : $default;
    }
}
