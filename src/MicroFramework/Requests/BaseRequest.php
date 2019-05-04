<?php
namespace YMF\Requests;

class BaseRequest
{
    protected static $_validate = [];
    protected static $_default = [];

    public function getValidateParam(): array
    {
        return static::$_validate;
    }

    public function setDefault(array $param): array
    {
        foreach (static::$_default as $key => $val) {
            if (!isset($param[$key])) {
                $param[$key] = $val;
            }
        }
        return $param;
    }
}
