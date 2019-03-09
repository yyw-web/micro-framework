<?php
namespace YMF\Requests;
use Illuminate\Http\Request;

class BaseRequest extends Request
{
    protected $_data = [];
    protected static $_args = [];
    protected static $_files = [];

    public function __get($prop)
    {
        if (isset($this->_data[$prop])) {
            return $this->_data[$prop];
        } elseif (isset(static::$_args[$prop])) {
            return null;
        } else {
            throw new \InvalidArgumentException;
        }
    }

    public function getData(): array
    {
        return $this->_data;
    }

    public function validation(): array
    {
        $this->_data = filter_input_array(INPUT_POST, static::$_args);
        $errors = [];
        foreach (static::$_args as $key => $val) {
            if (is_null($this->_data[$key]) || ($_POST[$key] === '')) { // 0はOK
                if ($val['required']) {
                    if (is_null($val['name'])) {
                        $keyTime = time();
                        $this->log->addError($keyTime. __CLASS__ . __METHOD__ . __LINE__, [$key]);
                        throw new \Exception($keyTime);
                    } else {
                        $errors[$key] = $val['name'] . 'は必須です';
                    }
                } else {
                    $this->_data[$key] = $val['default'];
                }
            } elseif ($this->_data[$key] === false) {
                $errors[$key] = $val['name'] . 'のフォーマットが違います';
            }
        }
        foreach (static::$_files as $key) {
            $ret_mag = $this->validation_image($key);
            if ($ret_mag !== '') {
                $errors[$key] = $ret_mag;
            }
        }
        return $errors;
    }

    public function validation_image(string $image_name): string
    {
        if (!isset($_FILES[$image_name]['error']) || !is_int($_FILES[$image_name]['error'])) {
            return 'アップロードファイルにエラーがあります。';
        }

        switch ($_FILES[$image_name]['error']) {
            case UPLOAD_ERR_OK: // OK
                break;
            case UPLOAD_ERR_NO_FILE:   // ファイル未選択
                return 'ファイルが選択されていません';
            case UPLOAD_ERR_INI_SIZE:  // php.ini定義の最大サイズ超過
            case UPLOAD_ERR_FORM_SIZE: // フォーム定義の最大サイズ超過 (設定した場合のみ)
                return 'ファイルサイズが大きすぎます';
            default:
                return 'その他のエラーが発生しました';
        }

        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $_FILES[$image_name]['tmp_name']);

        $ext = array_search(
                $mime_type, [
                    'gif' => 'image/gif',
                    'jpg' => 'image/jpeg',
                    'png' => 'image/png',
                ], true
        );
        if (!$ext) {
            return 'ファイル形式が不正です';
        }
        $this->_data[$image_name] = $ext;
        return '';
    }
}
