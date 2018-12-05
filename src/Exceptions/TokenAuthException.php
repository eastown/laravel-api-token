<?php
/**
 * Created by PhpStorm.
 * User: eastown
 * Date: 2018/12/3
 * Time: 16:16
 */

namespace Eastown\ApiToken\Exceptions;

use Exception;


class TokenAuthException extends Exception
{
    const USER = 1;

    const PWD = 2;

    const PERMISSION = 3;

    const TOKEN = 4;

    const TOKEN_EXPIRED = 5;

    const SINGLE_TOKEN = 6;

    const FINGERPRINT = 7;

    const AUTH_SETTING = 8;

    const NOT_AUTH = 9;

    public function __construct($code, $message = '', Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}