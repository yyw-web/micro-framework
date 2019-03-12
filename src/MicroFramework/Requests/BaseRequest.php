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
        $value = [];
        foreach (static::$_default as $key => $val) {
            if (is_null($param[$key]) || ($param[$key] === '')) { // 0はOK
                $value[$key] = $val;
            }else{
                $value[$key] = $param[$key];
            }
        }
        return $value;
    }
}
