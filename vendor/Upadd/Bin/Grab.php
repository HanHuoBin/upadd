<?php
namespace Upadd\Bin;

use Config;
use Upadd\Bin\Tool\Log;

class Grab extends Debug
{

    /**
     * 确定错误类型是否致命
     * @var array
     */
    public static $errorType = [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE];

    /**
     * 运行异常处理
     * @return void
     */
    public static function run()
    {
        error_reporting(E_ALL);
        //自定义错误获取
        set_error_handler([__CLASS__, 'setError']);
        //自定义异常处理
        set_exception_handler([__CLASS__, 'setGlobalException']);
        //设置退出方式
        register_shutdown_function([__CLASS__, 'setExit']);
    }

    /**
     * Error Handler
     * @param  integer $errno 错误编号
     * @param  integer $errstr 详细错误信息
     * @param  string $errfile 出错的文件
     * @param  integer $errline 出错行号
     * @param array $errcontext
     * @throws ErrorException
     */
    public static function setError($errno, $errstr, $errfile = '', $errline = 0, $errcontext = [])
    {
        $error = [$errno, $errstr, $errfile, $errline, $errcontext];
        if (Config::get('tag@debug'))
        {
            self::printError($error);
        }
        $body = "Error\n";
        $body .= "---\n";
        $body .= "Level:" . $error[0] . "\n";
        $body .= "Msg:" . $error[1] . "\n";
        $body .= "File:" . $error[2] . "\n";
        $body .= "Line:" . $error[3] . "\n";
        if ($error[4]) {
            $body .= "Info:\n" . json($error[4]) . "\n";
        }
        $body .= "---\n";
        Log::run($body);
    }

    /**
     * 全局异常
     * @param $e
     */
    public static function setGlobalException($e)
    {
        $error = [
            'msg' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'code' => $e->getCode(),
            'previous' => $e->getPrevious()
        ];
        $body = "Exception\n";
        $body .= "----\n";
        $body .= "Msg:" . $error['msg'] . "\n";
        $body .= "File:" . $error['file'] . "\n";
        $body .= "Line:" . $error['line'] . "\n";
        $body .= "Code:" . $error['code'] . "\n";
        $body .= "Previous:" . $error['previous'] . "\n";
        $body .= "----\n";
        Log::run($body);
        if (Config::get('tag@debug'))
        {
            self::printError($error);
        }
    }


    /**
     * 正常退出
     */
    public static function setExit()
    {
        /**
         * type,message,fileline
         */
        $error = error_get_last();

        if($error)
        {
            /**
             * 检测是否有错误
             */
            if (is_null($error) === false)
            {
                //待处理全局致命类型错误
                if (in_array($error['type'], static::$errorType)) {

                }
            }
            self::printError($error);
        }

        $endtime = "Date:" . date('Y/m/d H:i:s') . "\n";
        $time = (microtime(true)) - RUNTIME;
        $endtime .= 'End Run Time consuming ' . round($time, 3) . ' second';
        $endtime .= "\r\n" . "======\n\r";
        Log::run($endtime);
    }

    /**
     * 打印错误或是异常
     * @param array $error
     */
    private static function printError($error = [])
    {
        if ($error)
        {
            if (is_run_evn()) {
                echo '<pre>';
                print_r($error);
                echo '</pre>';
            } else {
                print_r($error);
            }
        }
    }


}