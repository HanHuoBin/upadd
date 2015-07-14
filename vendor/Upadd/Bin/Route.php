<?php

/**
+----------------------------------------------------------------------
| UPADD [ Can be better to Up add]
+----------------------------------------------------------------------
| Copyright (c) 20011-2015 http://upadd.cn All rights reserved.
+----------------------------------------------------------------------
| Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
+----------------------------------------------------------------------
| Author: Richard.z <v3u3i87@gmail.com>
 **/
namespace Upadd\Bin;

/**
 * @method static Route get(string $route, Callable $callback)
 * @method static Route post(string $route, Callable $callback)
 * @method static Route put(string $route, Callable $callback)
 * @method static Route delete(string $route, Callable $callback)
 * @method static Route options(string $route, Callable $callback)
 * @method static Route head(string $route, Callable $callback)
 */
use Upadd\Frame\Action;
use Upadd\Bin\Rbac;

class Route
{

    public static $halts = false;

    public static $routes = array();

    public static $methods = array();

    public static $callbacks = array();

    public static $prefix = null;

    public static $_filters = array();

    public static $_filtersData = null;

    public static $_group = array();

    public static $_url = array();

    public static $error_callback;

    /**
     * Defines a route w/ callback and method
     */
    public static function __callstatic($method, $params)
    {
       if(self::$prefix){
           self::$_url[] = $params[0];
           $url = self::$prefix.$params[0];
       }else{
           $url = $params[0];
       }
        $callback = $params[1];
        self::$routes[$url] = $url;
        self::$methods[$url] = strtoupper($method);
        self::$callbacks[$url] = $callback;
    }

    public static function group($method,$_callback,$name=null){
        self::$_group[] = $method;
        if(isset($method['prefix'])) {
            self::$prefix = $method['prefix'];
        }

        if(isset($method['filters'])){
            self::$_filters[] = $method['filters'];
        }

        if(is_callable($_callback)) {
            call_user_func($_callback);
        }
    }


    public static function filters($key,$_callback){
        $_url = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $name = null;
        foreach(self::$_url as $k=>$v){
            if($v){
                foreach(self::$_group as $prefix=>$val){
                    if(isset($val['prefix'])){
                        if($val['prefix'].$v==$_url){
                            $name = $val['prefix'].$v;
                            break;
                        }
                    }
                }
            }
        }

        if($name){
            foreach(self::$_filters as $k=>$v){
                if( $key == $v) {
                    //self::$_filtersData = $_callback;
                    if(is_callable($_callback)) {
                        call_user_func($_callback);
                    }
                    break;
                }
            }
        }
    }

    /**
     * Defines callback if route is not found
     */
    public static function error($callback)
    {
        self::$error_callback = $callback;
    }

    public static function haltOnMatch($flag = true)
    {
        self::$halts = $flag;
    }

    public static function dispatch()
    {
        $_url = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        //请求方式
        $method = $_SERVER['REQUEST_METHOD'];
        $found_route = false;
        $_Routes = null;
        isset(self::$routes[$_url]) ? $_Routes = self::$routes[$_url] : msg(404,'不存在的地址');
        /**
         * 判断请求类型
         */
        if (self::$methods[$_Routes] == $method)
        {
            $_callbacks = self::$callbacks[$_Routes];
            $found_route = true;
            if(!is_object($_callbacks))
            {
                //获取控制器对象
                $_objAction = explode('@',$_callbacks);
                //判断下是否为数组
                !is_array($_objAction) ? msg() : null;
                self::$halts = true;
                list($_actionName,$functuion) = $_objAction;

                $controller = new $_actionName();
                //设置模板控制器
                $controller->setViewAction($_actionName);
                $controller->$functuion();
                if (self::$halts) return;
            } else {
                //停止执行
                call_user_func(self::$callbacks[$_Routes]);
                if (self::$halts) return;
            }
        }

        /**
         * 运行错误如果路线不存在回调
         */
        if ($found_route == false) {
            if (!self::$error_callback) {
                self::$error_callback = function() {
                   msg(404,'不存在的地址');
                };
            }
            call_user_func(self::$error_callback);
        }

    }


    public static function getAction(){
        $_url = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        $_Routes = null;
        isset(self::$routes[$_url]) ? $_Routes = self::$routes[$_url] : msg(404,'不存在的地址');
        $_callbacks = self::$callbacks[$_Routes];

        if(!is_object($_callbacks))
        {
            //获取控制器对象
            $_objAction = explode('@',$_callbacks);

            $lode = lode('\\',$_objAction[0]);
            $_action = end($lode);
            return array(
                $_action,
                $_objAction[1]
            );
        }else{
            return  array();
        }
    }



}