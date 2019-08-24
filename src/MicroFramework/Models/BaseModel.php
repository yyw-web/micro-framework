<?php
namespace YMF\Models;

class BaseModel
{
    protected $_data = [];
    protected static $_schema = [];
    protected static $_table = '';
    protected static $_work = '';
    protected static $_pkey = '';
    protected static $_auto = '';
    protected static $_fix= [];
    protected static $_simple= [];
    protected static $_softDel = false;

    public function __get($prop)
    {
        if (isset($this->_data[$prop])) {
            return $this->_data[$prop];
        } elseif (isset(static::$_schema[$prop])) {
            return null;
        } else {
            throw new \InvalidArgumentException;
        }
    }

    public function __isset($prop)
    {
        return isset($this->_data[$prop]);
    }

    public function __set($prop, $val)
    {
        // 共通カラム
        switch ($prop) {
            case 'del_flg':
                if (!static::$_softDel) {
                    throw new \Exception($prop . ' not found');
                }
            case 'edit_id':
            case 'edit_dt':
                $this->_data[$prop] = $val;
                return;
        }
        // それ以外
        if (!isset(static::$_schema[$prop])) {
            throw new \Exception($prop . ' not found');
        }
        $schema = static::$_schema[$prop];
        // 空データが来た場合default値をセット
        if (is_null($val)) {
            if (is_null($schema['default'])) {    //default値がないものは例外発生
                throw new \Exception($prop . ' not define');
            }
            $this->_data[$prop] = $schema['default'];
            return;
        } elseif (empty($val)) {
            $this->_data[$prop] = $val;
            return;
        }

        $value = filter_var($val, $schema['filter'], $schema['param']);
        if ($schema['filter'] === FILTER_VALIDATE_BOOLEAN) {
            if (is_null($value)) {
                throw new \Exception('Bool型違い' . $val . "\n" . get_class($this));
            }
        } else {
            if ($value === false) {
                throw new \Exception($prop . ' 型違い' . $val . "\n" . get_class($this));
            }
        }
        $this->_data[$prop] = $value;
    }

    public function toArray()
    {
        return $this->_data;
    }

    public function fromArray(array $arr, bool $isAdd)
    {
        $this->delColumn($arr, $isAdd);

        foreach (static::$_schema as $key => $val) {
            if (isset($arr[$key])) {
                $this->__set($key, $arr[$key]);
            }
        }
    }

    public function fromReqArray(array $arr, bool $isAdd, bool $is_check = true):array
    {
        $this->delColumn($arr, $isAdd);

        foreach (static::$_schema as $key => $val) {
            if (isset($arr[$key])) {
                $this->_data[$key] = $arr[$key];
            }
        }

        if($is_check){
            return $this->isValidAndSet($isAdd);
        }else{
            return [];// チェックしない場合は空を返す。
        }
    }

    private function delColumn(array &$arr, bool $isAdd)
    {
        if (static::$_auto !== '') {
            unset($arr[static::$_auto]);
        }

        if (!$isAdd) {
            foreach (static::$_fix as $key => $val) {
                unset($arr[$key]);
            }
        }
    }

    public function fromArrayByKey($keys = [], $posts = [])
    {
        foreach ($keys as $key) {
            if (isset($posts[$key])) {
                $this->__set($key, $posts[$key]);
            }
        }
    }

    public function getPVal():int
    {
        return $this->_data[static::$_pkey];
    }

    public static function getTable():string
    {
        return static::$_table;
    }
    public static function getWork():string
    {
        return static::$_work;
    }
    public static function getPKey():string
    {
        return static::$_pkey;
    }
    public static function getAutoKey():string
    {
        return static::$_auto;
    }
    public static function getFixKey():array
    {
        return static::$_fix;
    }
    public static function getSchemas():array
    {
        return array_keys(static::$_schema);
    }
    public static function getColumnsSimple():array
    {
        return static::$_simple;
    }
    public static function getDelFlg():bool
    {
        return static::$_softDel;
    }

    public static function getColumns():array
    {
        $keys = array_keys(static::$_schema);

        if (static::$_softDel) {
            $keys[] = 'del_flg';
        }
        $keys[] = 'edit_id';
        $keys[] ='edit_dt';
        return $keys;
    }



    public function isValidAndSet(bool $isAdd):array
    {
        $msgs = [];
        $schema = static::$_schema;
        $pkey = static::$_pkey;
        if ($isAdd) {
            unset($schema[$pkey]);
        }

        foreach ($schema as $key => $val) {
            $value = $this->__get($key);
            if (is_null($value)) {
                if (is_null($val['default'])) {
                    $msgs[] = $key . ' Invalid Parameter';
                }
                $this->_data[$key] = $val['default'];
            }
        }
        return $msgs;
    }

    // 例外は発生しない＆共通処理を抜き出しただけなので関数名もログに出さない
    public function setParam(\PDOStatement &$stmt, bool $isAdd = true)
    {
        $schema = static::$_schema;
        if (static::$_auto !== '') {
            unset($schema[static::$_auto]);
        }

        if (!$isAdd) {
            foreach (static::$_fix as $val) {
                unset($schema[$val]);
            }
        }
        foreach ($schema as $key => $val) {
            $stmt->bindValue(':' . $key, $this->_data[$key],  $val['type']);
        }
    }

    // 例外は発生しない＆共通処理を抜き出しただけなので関数名もログに出さない
    public function setCustomParam(\PDOStatement &$stmt, array $params)
    {
        $schema = static::$_schema;
        foreach ($params as $key => $val) {
            $stmt->bindValue(':' . $key, $val,  $schema[$key]['type']);
        }
    }

    // 例外は発生しない＆共通処理を抜き出しただけなので関数名もログに出さない
    public function setParamForAdd(\PDOStatement &$stmt)
    {
        // なにもしない
    }

    // 例外は発生しない＆共通処理を抜き出しただけなので関数名もログに出さない
    public function setParamForBulkMod(\PDOStatement &$stmt)
    {
        // なにもしない
    }
}
