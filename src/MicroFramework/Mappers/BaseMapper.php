<?php
namespace YMF\Mappers;
use Illuminate\Database\DatabaseManager;
use YMF\Models\BaseModel;

class BaseMapper
{
    protected $_pdo;
    protected $_model_class;
    protected $usr;
    /**
     * Create a new response.
     *
     * @return void
     */
    public function __construct(DatabaseManager $databaseManager)
    {
        global $_USR;
        $this->usr = $_USR;
        $this->_pdo = $databaseManager->connection('mysql')->getPdo();
    }

    protected function _decorate(\PDOStatement $stmt)
    {
        $stmt->setFetchMode(\PDO::FETCH_CLASS|\PDO::FETCH_PROPS_LATE, $this->_model_class);
        return $stmt;
    }

    public function beginTransaction()
    {
        $this->_pdo->beginTransaction();
    }

    public function endTransaction()
    {
        try {
            $this->_pdo->commit();
        } catch (\Exception $e) {
            $this->_pdo->rollBack();
            throw new \Exception($e);
        }
    }

    // v
    protected function _insertSQL($dataModel, bool $isWork = false):string
    {
        $tbl = ($isWork)? $dataModel->getWork():$dataModel->getTable();
        $keys = $dataModel->getColumns();
        $schemas = $dataModel->getSchemas();
        $auto = $dataModel->getAutoKey();
        if (($key = array_search($auto, $keys)) !== false) {
            unset($keys[$key]);
        }
        if (($key = array_search($auto, $schemas)) !== false) {
            unset($schemas[$key]);
        }
        $clms = '`' . implode('`,`', $keys) . '`';
        $bind_str = ':' . implode(',:', $schemas);
        $del_flg = '';
        if ($dataModel->getDelFlg()) {
            $del_flg = 'false,';
        }
        $usr_id = $this->usr['ID'];
        $sql = "INSERT INTO `{$tbl}` ({$clms}) VALUES ({$bind_str},{$del_flg}{$usr_id},NOW());";
        return $sql;
    }

    protected function _updateSQL($dataModel, bool $isAll = false, bool $isWork = false):string
    {
        $tbl = ($isWork)? $dataModel->getWork():$dataModel->getTable();
        $pkey =  $dataModel->getPkey();
        $pval =  $dataModel->getPVal();
        if (empty($pval)) {
            throw new \Exception('SQL ERROR');
        }
        $clms = $this->_createUpdateSQL($dataModel, $pkey, $isAll);
        return "UPDATE {$tbl} SET {$clms} WHERE `{$pkey}` = {$pval};";
    }

    // v
    protected function _updateCustomSQL(BaseModel $dataModel, array $params, int $pk, bool $isWork = false):string
    {
        $tbl = ($isWork)? $dataModel->getWork():$dataModel->getTable();
        $pkey =  $dataModel->getPkey();
        $clms = '';
        foreach ($params as $key => $val) {
            $clms .= '`' . $key . '`=:' . $key . ',';
        }

        $usr_id = $this->usr['ID'];
        $clms .= "`edit_id`={$usr_id},`edit_dt`=NOW()";
        return "UPDATE {$tbl} SET {$clms} WHERE `{$pkey}` = {$pk};";
    }

    protected function _bulkUpdateSQL($dataModel, bool $isAll = false):string
    {
        $tbl = $dataModel->getTable();
        $pkey =  $dataModel->getPkey();
        $clms = $this->_createUpdateSQL($dataModel, $pkey, $isAll);
        return "UPDATE {$tbl} SET {$clms} WHERE `{$pkey}`=:{$pkey};";
    }

    private function _createUpdateSQL($dataModel, string $pkey, bool $isAll):string
    {
        $schemas = $dataModel->getSchemas();
        unset($schemas[$pkey]);

        $auto = $dataModel->getAutoKey();
        if (($key = array_search($auto, $schemas)) !== false) {
            unset($schemas[$key]);
        }

        if ($isAll === false) {
            $fixes = $dataModel->getFixKey();
            foreach ($fixes as $val) {
                if (($key = array_search($val, $schemas)) !== false) {
                    unset($schemas[$key]);
                }
            }
        }
        $clms = '';
        foreach ($schemas as $clm) {
            $clms .= '`' . $clm . '`=:' . $clm . ',';
        }
        $usr_id = $this->usr['ID'];
        $clms .= "`edit_id`={$usr_id},`edit_dt`=NOW()";
        return $clms;
    }

    // 簡易型のWHERE文作成
    protected function _whereParam(array $where): string
    {
        $retCnd = ' WHERE 1=1 ';
        foreach ($where as $cond) {
            if (is_array($cond)) {
                $retCnd .= $cond[0] . ' `' . $cond[1] . '`' . $cond[2] . '? ';
            } elseif (is_string($cond)) {
                $retCnd .= $cond;
            }
        }
        return $retCnd;
    }

    // 簡易型のWHERE文作成
    protected function _whereRParam(array $where): string
    {
        $retCnd = ' WHERE 1=1 ';
        foreach ($where as $cond) {
            if (is_array($cond)) {
                $retCnd .= $cond[0] . ' `' . $cond[5] . '`.`' . $cond[1] . '`' . $cond[2] . '? ';
            } elseif (is_string($cond)) {
                $retCnd .= $cond;
            }
        }
        return $retCnd;
    }

    private function _getLColumns(array  $LKeys):string
    {
        $retStr = '';
        foreach ($LKeys as $key) {
            $retStr .= "`L`.`{$key}` AS `L_{$key}`,";
        }
        return $retStr;
    }


    // 主キーで指定カラムの数値を変更
    public function _setCount(string $class_name, string $clm, string $num, int $pk):bool
    {
        $this->_model_class = __namespace__ . $class_name;// 使わなくても宣言
        $tbl = $this->_model_class::getTable();
        $pkey = $this->_model_class::getPKey();
        $usr_id = $this->usr['ID'];
        $clms = "`{$clm}`=`{$clm}`{$num},`edit_id`={$usr_id},`edit_dt`=NOW()";
        $sql = "UPDATE `{$tbl}` SET {$clms} WHERE `{$pkey}` = ?;";

        $stmt = $this->_pdo->prepare($sql);
        $stmt->bindValue(1, $pk, \PDO::PARAM_INT);
        $rst = $stmt->execute();

        $cnt = $stmt->rowCount();
        return ($rst === true && $cnt === 1);
    }

    // 条件次第で複数HITする可能性があるが最初の1個しか返さない。
    // 基本的にPKEYで検索かける。
    protected function _getObject(string $class_name, array $keys, array $where, bool $isWork = false)
    {
        $this->_model_class = __namespace__ . $class_name;// 使わなくても宣言
        if (empty($where)) {
            return false;
        }

        if ($isWork) {
            $tbl = $this->_model_class::getWork();
        } else {
            $tbl = $this->_model_class::getTable();
        }
        if (empty($keys)) {
            $keys = $this->_model_class::getColumns();
        }
        $clms = '`' . implode('`,`', $keys) . '`';
        $cond = $this->_whereParam($where);// 失敗の場合は例外がTHROWされる
        $sql = "SELECT {$clms} FROM `{$tbl}` {$cond}";

        $stmt = $this->_pdo->prepare($sql);
        $bund_num = 1;
        foreach ($where as $key => $val) {
            if (is_array($val)) {
                $stmt->bindValue($bund_num, $val[3], $val[4]);
                $bund_num++;
            }
        }
        $stmt->execute();
        $this->_decorate($stmt);
        return $stmt->fetch();
    }

    // whereは文字列と配列で処理が違う
    protected function _getObjects(
        string $class_path,
        array $keys,
        array $where,
        string $limit = '',
        string $order= '',
        bool $isDec = false,
        bool $isWork = false)
    {
        $this->_model_class = $class_path;// 使わなくても宣言
        if ($isWork) {
            $tbl = $this->_model_class::getWork();
        } else {
            $tbl = $this->_model_class::getTable();
        }

        if (empty($keys)) {
            $keys = $this->_model_class::getColumns();
            $clms = '`' . implode('`,`', $keys) . '`';
        }else{
            $clms = implode(',', $keys);
        }
        $cnt = [0];

        $cond = $this->_whereParam($where);// 失敗の場合は例外がTHROWされる
        $sql_cnt = "SELECT COUNT(1) FROM `{$tbl}` {$cond}";
        $stmt_cnt = $this->_pdo->prepare($sql_cnt);

        $sql = "SELECT {$clms} FROM `{$tbl}` {$cond} {$order} {$limit}";
        $stmt = $this->_pdo->prepare($sql);
        $bund_num = 1;
        foreach ($where as $key => $val) {
            if (is_array($val)) {
                $stmt_cnt->bindValue($bund_num, $val[3], $val[4]);
                $stmt->bindValue($bund_num, $val[3], $val[4]);
                $bund_num++;
            }
        }
        $stmt_cnt->execute();
        $cnt = $stmt_cnt->fetch(\PDO::FETCH_NUM);
        $stmt->execute();

        if ($isDec) {
            $this->_decorate($stmt);
            return ['cnt'=>$cnt[0], 'data'=>$stmt->fetchAll()];
        } else {
            return ['cnt'=>$cnt[0], 'data'=>$stmt->fetchAll(\PDO::FETCH_ASSOC)];
        }
    }

    protected function _getLRObject(
                             String $LTbl, array  $LKeys,
                             String $RTbl, array  $RKeys,
                             array  $where, String $on_where)
    {
        if (empty($where)) {
            return false;
        }

        $LClms = self::_getLColumns($LKeys);
        $RClms = '`R`.`' . implode('`,`R`.`', $RKeys) . '`';
        $sql = "SELECT {$LClms}{$RClms} FROM `{$LTbl}` AS `L` LEFT OUTER JOIN `{$RTbl}` AS `R` {$on_where} ";
        $sql .= $this->_whereRParam($where);// 失敗の場合は例外がTHROWされる
        $stmt = $this->_pdo->prepare($sql);
        $bund_num = 1;
        foreach ($where as $key => $val) {
            if (is_array($val)) {
                $stmt->bindValue($bund_num, $val[3], $val[4]);
                $bund_num++;
            }
        }
        $stmt->execute();
        return $stmt->fetch();
    }

    protected function _getLRObjects(
                             String $LTbl, array  $LKeys,
                             String $RTbl, array  $RKeys,
                             array  $where, String $on_where, String $order, String $limit)
    {
        $LClms = self::_getLColumns($LKeys);
        $RClms = '`R`.`' . implode('`,`R`.`', $RKeys) . '`';
        $sql_cnt = "SELECT COUNT(1) FROM `{$LTbl}` AS `L` LEFT OUTER JOIN `{$RTbl}` AS `R` {$on_where}";
        $sql = "SELECT {$LClms}{$RClms} FROM `{$LTbl}` AS `L` LEFT OUTER JOIN `{$RTbl}` AS `R` {$on_where}";
        $cnt[0] = 0;
        if (empty($where)) {
            $sql_cnt .= ";";
            $sql .= " {$order} {$limit};";

            $stmt_cnt = $this->_pdo->query($sql_cnt);
            $cnt = $stmt_cnt->fetch(\PDO::FETCH_NUM);

            $sql .= " {$order} {$limit};";
            $stmt = $this->_pdo->query($sql);
        } else {
            $cond = $this->_whereRParam($where);// 失敗の場合は例外がTHROWされる
            $sql_cnt .= " {$cond};";
            $stmt_cnt = $this->_pdo->prepare($sql_cnt);
            $sql .= " {$cond} {$order} {$limit};";
            $stmt = $this->_pdo->prepare($sql);
            $bund_num = 1;
            foreach ($where as $key => $val) {
                if (is_array($val)) {
                    $stmt_cnt->bindValue($bund_num, $val[3], $val[4]);
                    $stmt->bindValue($bund_num, $val[3], $val[4]);
                    $bund_num++;
                }
            }
            $stmt_cnt->execute();
            $cnt = $stmt_cnt->fetch(\PDO::FETCH_NUM);
            $stmt->execute();
        }
        return ['cnt'=>$cnt[0], 'data'=>$stmt->fetchAll(\PDO::FETCH_ASSOC)];
    }

    // v
    protected function _getCount(string $tbl, array $where):int
    {
        if (empty($where)) {
            $sql_cnt = "SELECT COUNT(1) FROM `{$tbl}`";
            $stmt_cnt = $this->_pdo->query($sql_cnt);
            $cnt = $stmt_cnt->fetch(\PDO::FETCH_NUM);
        } else {
            $cond = $this->_whereParam($where);// 失敗の場合は例外がTHROWされる
            $sql_cnt = "SELECT COUNT(1) FROM `{$tbl}` {$cond}";
            $stmt_cnt = $this->_pdo->prepare($sql_cnt);

            $bund_num = 1;
            foreach ($where as $key => $val) {
                if (is_array($val)) {
                    $stmt_cnt->bindValue($bund_num, $val[3], $val[4]);
                    $bund_num++;
                }
            }
            $stmt_cnt->execute();
        }
        $cnt = $stmt_cnt->fetch(\PDO::FETCH_NUM);
        return $cnt[0];
    }

    protected function _getMaxObject(string $tbl, string $clm, array $where):int
    {
        $cond = $this->_whereParam($where);// 失敗の場合は例外がTHROWされる
        $sql = "SELECT MAX(`{$clm}`) FROM `{$tbl}` {$cond}";

        $stmt = $this->_pdo->prepare($sql);
        $bund_num = 1;
        foreach ($where as $key => $val) {
            if (is_array($val)) {
                $stmt->bindValue($bund_num, $val[3], $val[4]);
                $bund_num++;
            }
        }
        $stmt->execute();
        $maxNum = $stmt->fetch(\PDO::FETCH_NUM);
        return $maxNum[0];
    }

    protected function _isExist(string $tbl, array $where):bool
    {
        if (empty($where)) {
            $sql = "SELECT 1 FROM `{$tbl}` LIMIT 0,1";
            $stmt = $this->_pdo->query($sql);
            $cnt = $stmt->fetch(\PDO::FETCH_NUM);
        } else {
            $cond = $this->_whereParam($where);// 失敗の場合は例外がTHROWされる
            $sql = "SELECT 1 FROM `{$tbl}` {$cond} LIMIT 0,1";
            $stmt = $this->_pdo->prepare($sql);

            $bund_num = 1;
            foreach ($where as $key => $val) {
                if (is_array($val)) {
                    $stmt->bindValue($bund_num, $val[3], $val[4]);
                    $bund_num++;
                }
            }
            $stmt->execute();
            $cnt = $stmt->fetch(\PDO::FETCH_NUM);
        }
        return !($cnt === false);
    }

    // v
    protected function _addObject(BaseModel $obj, bool $isWork = false):int
    {
        $sql = $this->_insertSQL($obj, $isWork);
        $stmt = $this->_pdo->prepare($sql);
        $obj->setParam($stmt);
        $obj->setParamForAdd($stmt);

        if ($stmt->execute()) {
            return $this->_pdo->lastInsertId();
        } else {
            return 0;
        }
    }

    protected function _modObject(BaseModel $obj, bool $isWork = false):bool
    {
        $sql = $this->_updateSQL($obj, false, $isWork);
        $stmt = $this->_pdo->prepare($sql);
        $obj->setParam($stmt);
        $rst = $stmt->execute();
        $cnt = $stmt->rowCount();// 0行じゃないことを確認
        return ($rst === true && $cnt === 1);
    }

    protected function _modCustomObject(BaseModel $obj, array $params, int $pk, bool $isWork = false): bool
    {
        $sql = $this->_updateCustomSQL($obj, $params, $pk, $isWork);
        $stmt = $this->_pdo->prepare($sql);
        $obj->setCustomParam($stmt, $params);
        $rst = $stmt->execute();
        $cnt = $stmt->rowCount();// 0行じゃないことを確認
        return ($rst === true && $cnt === 1);
    }

    // pkで削除する。論理削除
    protected function _delObject(string $class_name, int $pk):bool
    {
        $this->_model_class = __namespace__ . $class_name;
        $tbl = $this->_model_class::getTable();
        $pkey = $this->_model_class::getPKey();
        $usr_id = $this->usr['ID'];
        $sql = "UPDATE {$tbl} SET `del_flg`=true,`edit_id`={$usr_id},`edit_dt`=NOW() WHERE `{$pkey}` = {$pk};";

        $cnt = $this->_pdo->exec($sql);
        return ($cnt === 1);
    }

    // pkで削除する
    protected function _forceDelObjectByID(string $tbl, string $pkey, int $pk):bool
    {
        $sql = "DELETE FROM {$tbl} WHERE `{$pkey}` = {$pk};";
        $cnt = $this->_pdo->exec($sql);
        return ($cnt === 1);
    }

    // 条件で削除する。物理削除
    protected function _delForceObjects(string $class_name, array $where, bool $isWork = false):bool
    {
        $this->_model_class = __namespace__ . $class_name;
        if (empty($where)) {
            return false;
        }

        if ($isWork) {
            $tbl = $this->_model_class::getWork();
        } else {
            $tbl = $this->_model_class::getTable();
        }

        $cond = $this->_whereParam($where);// 失敗の場合は例外がTHROWされる

        $sql_cnt = "SELECT COUNT(1) FROM `{$tbl}` {$cond}";
        $stmt_cnt = $this->_pdo->prepare($sql_cnt);

        $sql = "DELETE FROM {$tbl} {$cond};";
        $stmt = $this->_pdo->prepare($sql);

        $bund_num = 1;
        foreach ($where as $key => $val) {
            if (is_array($val)) {
                $stmt_cnt->bindValue($bund_num, $val[3], $val[4]);
                $stmt->bindValue($bund_num, $val[3], $val[4]);
                $bund_num++;
            }
        }
        $stmt_cnt->execute();
        $cnt = $stmt_cnt->fetch(\PDO::FETCH_NUM);

        if ($cnt[0] === 0) {
            return true;// 対象がないのでtrueを返す
        } else {
            $stmt->execute();
            $del_cnt = $stmt->rowCount();
            return ($cnt[0] === $del_cnt);
        }
    }

    // バッチ用 TODO:戻り値をどうするか1個でもエラーがあったらfalse?
    protected function _bulkAddObject(array $objs)
    {
        if (empty($objs)) {
            return; // データがないだけ処理は正常
        }

        $min_key = min(array_keys($objs));        // 最初の配列でSQL作成(pkeyを入力するタイプで作成)
        $sql = $this->_insertSQL($objs[$min_key]);
        $stmt = $this->_pdo->prepare($sql);

        foreach ($objs as $obj) {
            $obj->setParam($stmt);
            $obj->setParamForAdd($stmt);

            if (!$stmt->execute()) {
                throw new \Exception('A Problem Occurred.->BulkAdd');
            }
        }
    }

    // バッチ用 TODO:戻り値をどうするか1個でもエラーがあったらfalse?
    protected function _bulkModObject(array $objs)
    {
        if (empty($objs)) {
            return; // データがないだけ処理は正常
        }

        $min_key = min(array_keys($objs));
        $sql = $this->_bulkUpdateSQL($objs[$min_key]);// 最初の配列でSQL作成(pkeyを入力するタイプで作成)
        $stmt = $this->_pdo->prepare($sql);

        foreach ($objs as $obj) {
            $obj->setParam($stmt);
            $obj->setParamForBulkMod($stmt);// 主キーをせっとするだけ
            if (!$stmt->execute()) {
                throw new \Exception('A Problem Occurred.->BulkMod');
            }
        }
    }

    //
    protected function _bulkModCustom(string $sql, array $objs)
    {
        if (empty($objs)) {
            return; // データがないだけ処理は正常
        }

        $stmt = $this->_pdo->prepare($sql);

        foreach ($objs as $obj) {
            foreach ($obj as $item) {
                $stmt->bindValue(':' . $item['key'], $item['val'], $item['type']);
            }

            if (!$stmt->execute()) {
                throw new \Exception('A Problem Occurred.->BulkMod');
            }
        }
    }

    protected function _moveRecSQL($dataModel):bool
    {
        $tbl = $dataModel->getTable();
        $work = $dataModel->getWork();
        $pkey =  $dataModel->getPkey();
        $pval =  $dataModel->getPVal();
        $sql_ins = "INSERT INTO `{$tbl}` (SELECT * FROM `{$work}` WHERE `{$pkey}` = {$pval});";
        $sql_del = "DELETE FROM `{$work}` WHERE `{$pkey}` = {$pval};";

        $this->_pdo->beginTransaction();
        $rst_ins = $this->_pdo->exec($sql_ins);
        $rst_del = $this->_pdo->exec($sql_del);
        if (($rst_ins === 1) && ($rst_del === 1)) {
            return $this->_pdo->commit();
        } else {
            $this->_pdo->rollBack();
            return false;
        }
    }

    // v
    protected function _workToTempSQL(BaseModel $obj, int $pk): bool
    {
        $tbl = $obj->getTable();
        $work = $obj->getWork();
        $pkey =  $obj->getPkey();
        $sql_ins = "INSERT INTO `{$tbl}` (SELECT * FROM `{$work}` WHERE `{$pkey}` = {$pk});";

        $rst_ins = $this->_pdo->exec($sql_ins);
        return ($rst_ins === 1);
    }
}
