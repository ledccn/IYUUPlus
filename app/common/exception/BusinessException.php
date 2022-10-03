<?php

namespace app\common\exception;

use Exception;

/**
 * Class BusinessException
 * @access private 常驻内存运行，禁止执行器调用
 * @package app\common\exception
 */
class BusinessException extends Exception
{
}
