<?php
/**
 +----------------------------------------------------------------------
 | UPADD [ Can be better to Up add]
 +----------------------------------------------------------------------
 | Copyright (c) 20011-2014 http://upadd.cn All rights reserved.
 +----------------------------------------------------------------------
 | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
 +----------------------------------------------------------------------
 | Author: Richard.z <v3u3i87@gmail.com>
 **/
is_upToken();
//参数调用处理类
class Conf{

	protected static $in;

	protected $_data = array();

	final protected function __construct(){
		$this->_data =  require UPADD_HOST.'/Upadd/Common/Config.inc.php';
	}

	final public function __clone(){}

	public static function getConf(){
		if (!(self::$in instanceof self)) {
			self::$in = new self();
		}
		return self::$in;
	}

	public function __get($key){
		if(array_key_exists($key, $this->_data)){
			return $this->_data[$key];
		}else {
			return null;
		}
	}

	public function __set($key,$value){
		$this->_data[$key] = $value;
	}
	
	

}
