<?php
namespace RequestValidator\Form\Exception;

use Cake\Http\Exception\HttpException;

class ValidationException extends HttpException
{
    /**
     * HttpExceptionがデフォルトで500を指定しているので上書きする
     *
     * @param string|null $message 例外の内容
     * @param int $code エラーコード。指定がなければ422: Unprocessable Entity
     */
    public function __construct($message = null, $code = 422)
    {
        parent::__construct($message, $code);
    }
}
