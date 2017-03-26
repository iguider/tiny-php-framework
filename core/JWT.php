<?php

class JWT
{
    public static $supported_algs = array(
        'HS256' => array('hash_hmac', 'SHA256'),
        'HS512' => array('hash_hmac', 'SHA512'),
        'HS384' => array('hash_hmac', 'SHA384'),
        'RS256' => array('openssl', 'SHA256'),
    );

    public static function decode($jwt, $key, $options = array())
    {
        if (empty($key)) {
            throw new InvalidArgumentException('Key may not be empty');
        }
        if (!is_array($options)) {
            throw new InvalidArgumentException('Options should be an array');
        }
        if (!isset($options['algorithms']) || !is_array($options['algorithms'])) {
            $options['algorithms'] = array('HS256', 'HS384', 'HS512');
        }
        $tks = explode('.', $jwt);
        if (count($tks) != 3) {
            throw new UnexpectedValueException('JWT malformed');
        }
        list($headb64, $bodyb64, $cryptob64) = $tks;

        if (null === ($header = static::json_decode(static::base64url_decode($headb64)))) {
            throw new UnexpectedValueException('Invalid header encoding');
        }
        if (null === $payload = static::json_decode(static::base64url_decode($bodyb64))) {
            throw new UnexpectedValueException('Invalid claims encoding');
        }
        $signature = static::base64url_decode($cryptob64);

        if (empty($header->alg)) {
            throw new DomainException('Empty algorithm');
        }
        if (empty(static::$supported_algs[$header->alg])) {
            throw new UnexpectedValueException('Algorithm not supported');
        }
        if (!in_array($header->alg, $options['algorithms'])) {
            throw new UnexpectedValueException('Algorithm not allowed');
        }
        if (is_array($key) || $key instanceof \ArrayAccess) {
            if (isset($header->kid)) {
                $key = $key[$header->kid];
            } else {
                throw new UnexpectedValueException('"kid" empty, unable to lookup correct key');
            }
        }
        if (!static::verify("$headb64.$bodyb64", $signature, $key, $header->alg)) {
            die("$headb64.$bodyb64");
            throw new UnexpectedValueException('Signature verification failed');
        }

        $timestamp = Arr::get($options, 'clockTimestamp', time());
        $clockTolerance = Arr::get($options, 'clockTolerance', 0);
        if (isset($payload->nbf) && !Arr::get($options, 'ignoreNotBefore', false)) {
            if(!is_numeric($payload->nbf)) {
                throw new DomainException('Invalid nbf value');
            }
            if($payload->nbf > ($timestamp + $clockTolerance)) {
                throw new UnexpectedValueException('Cannot handle token prior to ' . date(DateTime::ISO8601, $payload->nbf));
            }
        }
        if (isset($options['maxAge'])) {
            if (!isset($payload->iat) || !is_numeric($payload->iat)) {
                throw new DomainException('iat required when maxAge is specified');
            }
            if ($timestamp - $payload->iat > $options['maxAge'] + $clockTolerance) {
                throw new UnexpectedValueException('maxAge exceeded' . date(DateTime::ISO8601, $payload->iat + $options['maxAge']));
            }
        }
        if (isset($payload->exp) && !Arr::get($options, 'ignoreExpiration', false)) {
            if(!is_numeric($payload->exp)) {
                throw new DomainException('Invalid exp value');
            }
            if($timestamp >= $payload->exp + $clockTolerance) {
                throw new UnexpectedValueException('Expired token');
            }
        }
        return $payload;
    }

    private static function verify($msg, $signature, $key, $alg = 'HS256')
    {
        list($function, $algorithm) = static::$supported_algs[$alg];
        switch($function) {
            case 'openssl':
                $success = openssl_verify($msg, $signature, $key, $algorithm);
                if (!$success) {
                    throw new DomainException("OpenSSL unable to verify data: " . openssl_error_string());
                }
                return $signature;
            case 'hash_hmac':
            default:
                return hash_equals($signature, hash_hmac($algorithm, $msg, $key, true));
        }
    }

    public static function encode($payload, $key, $options = array())
    {
        $alg = Arr::get($options, 'alg', 'HS256');
        $header = array('typ' => 'JWT', 'alg' => $alg);
        if (isset($options['keyid'])) {
            $header['kid'] = $options['keyid'];
        }
        if (isset($options['header']) && is_array($options['header']) ) {
            $header = array_merge($options['header'], $header);
        }

        $timestamp = $payload['iat'] = Arr::get($options, 'issuedAt', time());
        if (isset($options['noTimestamp']) && $options['noTimestamp']) {
            unset($payload['iat']);
        }

        if (isset($options['expiresIn'])) {
            $payload['exp'] = strtotime($options['expiresIn'], $timestamp);
            if ($payload['exp'] === false) {
                throw new DomainException('Invalid format for the "expiresIn" option. (Please refer to https://php.net/manual/en/datetime.formats.php)');
            }
        }

        if (isset($options['notBefore'])) {
            $payload['nbf'] = strtotime($options['notBefore'], $timestamp);
            if ($payload['nbf'] === false) {
                throw new DomainException('Invalid format for the "notBefore" option. (Please refer to https://php.net/manual/en/datetime.formats.php)');
            }
        }

        $segments = array();
        $segments[] = static::base64url_encode(static::json_encode($header));
        $segments[] = static::base64url_encode(static::json_encode($payload));
        $signing_input = implode('.', $segments);
        $signature = static::sign($signing_input, $key, $alg);
        $segments[] = static::base64url_encode($signature);
        return implode('.', $segments);
    }

    private static function sign($msg, $key, $alg = 'HS256')
    {
        if (empty(static::$supported_algs[$alg])) {
            throw new DomainException('Algorithm not supported');
        }
        list($function, $algorithm) = static::$supported_algs[$alg];
        switch($function) {
            case 'hash_hmac':
                return hash_hmac($algorithm, $msg, $key, true);
            case 'openssl':
                $signature = '';
                $success = openssl_sign($msg, $signature, $key, $algorithm);
                if (!$success) {
                    throw new DomainException("OpenSSL unable to sign data");
                }
                return $signature;
        }
    }

    private static function base64url_encode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64url_decode($data)
    {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }

    private static function json_decode($input)
    {
        $obj = json_decode($input);
        if (function_exists('json_last_error') && $errno = json_last_error()) {
            static::_handleJsonError($errno);
        } elseif ($obj === null && $input !== 'null') {
            throw new DomainException('Null result with non-null input');
        }
        return $obj;
    }

    private static function json_encode($input)
    {
        $json = json_encode($input);
        if (function_exists('json_last_error') && $errno = json_last_error()) {
            static::_handleJsonError($errno);
        } elseif ($json === 'null' && $input !== null) {
            throw new DomainException('Null result with non-null input');
        }
        return $json;
    }

    private static function _handleJsonError($errno)
    {
        $messages = array(
            JSON_ERROR_DEPTH => 'Maximum stack depth exceeded',
            JSON_ERROR_CTRL_CHAR => 'Unexpected control character found',
            JSON_ERROR_SYNTAX => 'Syntax error, malformed JSON'
        );
        throw new DomainException(
            isset($messages[$errno])
            ? $messages[$errno]
            : 'Unknown JSON error: ' . $errno
        );
    }
}
