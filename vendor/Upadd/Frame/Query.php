<?php
namespace Upadd\Frame;

use Upadd\Bin\Db\Db;
use Upadd\Bin\Tool\Verify;
use Upadd\Bin\Tool\Log;
use Upadd\Bin\Tool\PageData;
use Upadd\Frame\ProcessingSql;
use Upadd\Bin\UpaddException;

class Query extends ProcessingSql
{

    /**
     * 数据库对象
     *
     * @var unknown
     */
    private $_db;

    /**
     * DB信息
     * @var
     */
    protected $_dbInfo;

    /**
     * 默认库
     * @var string
     */
    private $use = 'local';

    /**
     * 分页参数
     * @var array
     */
    protected $_pageData = array();


//    /**
//     * Query constructor.
//     * @param Db $db
//     * @param    $_table
//     * @param    $_primaryKey
//     * @param    $db_prefix
//     */
//    public function __construct(Db $db, $_table, $_primaryKey, $db_prefix)
//    {
//        $this->_db = $db;
//        $this->_table = $_table;
//        $this->_primaryKey = $_primaryKey;
//        $this->db_prefix = $db_prefix;
//    }

    /**
     * 派发链接DB对象
     */
    public function distribution()
    {
        if (conf('database@many') === true)
        {
            foreach ($this->_dbInfo as $key => $value)
            {
                if ($this->use === $value['use'])
                {
                    $this->_dbInfo = $value;
                    continue;
                }
            }
        }
    }


    /**
     * 链接数据库
     */
    protected function connection()
    {
        $this->db_prefix = $this->_dbInfo ['prefix'];
        /**
         * 设置表名
         */
        $this->setTableName($this->_table);

        $this->_db = new \Upadd\Bin\Db\LinkPdoMysql($this->_dbInfo);
        p($this->_db);
//        $this->_query = new Query($this->_db, $this->getTableName(), $this->_primaryKey, $this->db_prefix);
    }


    /**
     * 设置表名称
     * @param $table
     */
    private function setTableName($table)
    {
        if ($this->_table !== $this->db_prefix . $table)
        {
            $this->_table = $this->db_prefix . $table;
        }
    }

    /**
     * 获取表名
     * @return unknown
     */
    protected function getTableName()
    {
        return $this->_table;
    }

    /**
     * 查询列表
     * @param null $_field
     * @return array 有分页
     */
    public function get($_field = null)
    {
        $this->joint_field($_field);
        $this->_db->_sql = 'SELECT ' . $this->mergeSqlLogic() . ';';
        $_data = $this->_db->select();
        if (count($this->_pageData) > 0 && $_data) {
            $this->_pageData['data'] = $_data;
            $_data = $this->_pageData;
        }
        $this->clear_where();
        return $_data;
    }


    protected function one($field = null)
    {
        if ($field) {
            $data = $this->find();
            if (isset($data[$field])) {
                return $data[$field];
            }
        }
        return false;
    }

    /**
     * 单行查询
     * @param null $_field
     * @return mixed
     */
    protected function find($_field = null)
    {
        $this->joint_field($_field);
        $this->_db->_sql = 'SELECT ' . $this->mergeSqlLogic() . ';';
        $this->clear_where();
        return $this->_db->find();
    }


    /**
     * 返回数据库所有的表名
     * @return mixed
     */
    protected function getTableAll()
    {
        $this->_db->_sql = 'Show Tables';
        $tmp = $this->_db->select();
        $list = [];
        if (count($tmp) > 1) {
            $list = arrayToOne($tmp);
        }
        return $list;
    }


    /**
     * 通过主键查询
     * @param      $value
     * @param null $_field
     * @return mixed
     */
    protected function first($value, $_field = null)
    {
        return $this->where(array($this->_primaryKey => $value))->find($_field);
    }

    /**
     *  多表查询
     * @param null $_table
     * @return $this
     */
    protected function join($_table = array())
    {
        if (empty($_table)) {
            return false;
        }
        $name = '';
        foreach ($_table as $k => $v) {
            $name .= $this->db_prefix . $k . ' as ' . $v . ' ,';
        }
        $this->_join = $name;
        return $this;
    }


    /**
     * where判断
     * @param data $_where as array|null|string
     * @return $this
     */
    protected function where($_where = null)
    {
        $this->joint_where($_where);
        return $this;
    }


    /**
     * OR 类型查询
     * @param array $or_where
     * @return $this
     * @throws UpaddException
     */
    protected function or_where($or_where='')
    {
        if (empty($or_where) || count($or_where) < 2)
        {
            throw new UpaddException('or_where 类型,需要传至少两个参数');
        }

        $tmp = '';
        if ($this->_where) {
            $tmp = ' AND ( ';
        } else {
            $tmp = ' WHERE ( ';
        }

        foreach ($or_where as $value) {
            if (count($value) < 2) {
                $tmp .= $this->where_arr_to_sql($value);
            } else {
                $tmp .= ' ( ' . $this->where_arr_to_sql($value) . ' ) ';
            }
            $tmp .= ' OR ';
        }

        $tmp = substr($tmp, 0, -4);
        $tmp .= ' ) ';
        $this->_or_where = $tmp;
        return $this;
    }

    /**
     * InWhere类型
     * @param        $key
     * @param        $data
     * @param string $type
     * @return $this
     */
    protected function in_where($key, $data = array())
    {
        if (empty($key) && empty($data)) {
            throw new UpaddException('in_where 类型,需传key或data');
        }
        if (is_array($data)) {
            $data = lode(',', $data);
        }
        if ($this->_where) {
            $this->_in_where = " AND `{$key}` IN ({$data}) ";
        } else {
            $this->_in_where = " WHERE `{$key}` IN ({$data}) ";
        }
        return $this;
    }


    /**
     *
     * @param       $key
     * @param array $data
     * @return $this
     * @throws UpaddException
     */
    protected function not_where($key = null, $data = null)
    {
        if (empty($key) && empty($data)) {
            throw new UpaddException('not_where类型:传key或data');
        }
        if (is_array($data)) {
            $data = lode(',', $data);
        }
        if ($this->_where) {
            $this->_not_in_where = " AND `{$key}`  NOT IN ({$data}) ";
        } else {
            $this->_not_in_where = " WHERE `{$key}`  NOT IN ({$data}) ";
        }
        return $this;
    }


    /**
     * 去重统计
     * @param $key
     * @return $this
     */
    protected function count_distinct($key, $field = null)
    {
        if ($key) {
            $tmp = null;
            if ($field) {
                $tmp = ", `{$field}` ";
            }
            $sql = " COUNT(distinct `{$key}`) AS `conut` ";
            if ($tmp) {
                $sql .= $tmp;
            }
            $this->_db->_sql = 'SELECT ' .$sql. $this->mergeSqlLogic() . ';';
            return $this->_db->getTotal();
        }
    }


    /**
     * 去重返回列表
     * @param $key
     * @return $this
     */
    protected function get_distinct($key, $field = null)
    {
        if ($key) {
            $tmp = null;
            if ($field) {
                $tmp = ", `{$field}` ";
            }
            $sql = " distinct `{$key}` ";
            if ($tmp) {
                $sql .= $tmp;
            }
            $this->_db->_sql = 'SELECT ' .$sql. $this->mergeSqlLogic() . ';';
            $_data = $this->_db->select();
            $this->clear_where();
            if($_data) {
                return $_data;
            }
        }
        return [];
    }



    /**
     * 排序
     * @param unknown $sort
     * @return string
     */
    protected function sort($sort, $by = true)
    {
        if ($by) {
            $this->_sort = " ORDER BY {$sort} DESC";
        } else {
            $this->_sort = " ORDER BY {$sort} ASC";
        }
        return $this;
    }


    /**
     * 模糊查询
     * @param unknown $key
     * @param string  $_field
     * @return \Upadd\Frame\Model
     */
    protected function like($key, $_field = null)
    {
        $this->_like = $key . ' LIKE ' . " '{$_field}' ";
        return $this;
    }


    /**
     * 构造分页参数
     * @param int $pagesize
     * @return $this
     */
    protected function page($pagesize = 10)
    {
        //查询条件
        $getTotal = $this->getTotal();
        $page = new PageData($getTotal, $pagesize);
        $pageArr = $page->show();
        $this->setLimit($pageArr['limit']);
        $this->_pageData = $pageArr['data'];
        return $this;
    }

    /**
     * 查询条数
     * @param null $param in array,string
     * @return $this
     */
    protected function limit($param = null)
    {
        $tmp = 'LIMIT ';
        if (is_array($param)) {
            $tmp .= lode(',', $param);

        } elseif (is_string($param)) {
            $tmp .= $param;
        } else {
            throw new UpaddException('limit()参数错误');
        }
        $this->setLimit($tmp);
        return $this;
    }

    /**
     * 新增
     * @param array $_data
     */
    protected function add($_data)
    {
        $field = array();
        $value = array();
        foreach ($_data as $k => $v) {
            $field [] = $k;
            $value [] = $v;
        }
        $field = implode("`,`", $field);
        $value = implode("','", $value);
        $this->_db->_sql = "INSERT INTO `{$this->_table}` (`$field`) VALUES ('$value') ;";
        if ($this->_db->sql()) {
            return $this->getId();
        }
        return false;
    }

    /**
     * 保存数据
     * @param unknown $_data
     * @param unknown $where
     */
    protected function save($_data = array(), $where = null)
    {
        if (!empty($this->_where) && !empty($_data)) {
            return $this->update($_data, $this->_where);
        }
        if (is_array($_data) && !empty($where)) {
            return $this->update($_data, $where);
        }
        if ($this->parameter && empty($_data) && empty($this->_where)) {
            return $this->add($this->parameter);
        }
        if ($this->_where && $this->parameter) {
            return $this->update($this->parameter, $this->_where);
        }
        return false;
    }

    /**
     * 修改数据
     * @param $_data
     * @param $where
     * @return bool
     */
    protected function update($_data = [], $where = null)
    {
        if (!is_array($_data)) {
            return false;
        }

        $_editdata = '';
        foreach ($_data as $k => $v) {
            $_editdata .= " `$k`='$v',";
        }
        $_editdata = substr($_editdata, 0, -1);
        $_where = $this->joint_where($where);
        $this->_db->_sql = "UPDATE `{$this->_table}` SET {$_editdata}  WHERE {$_where};";
        return $this->_db->sql();
    }

    /**
     * 批量添加
     * @param array $all
     * @return array|bool
     */
    protected function addAll($all = array())
    {
        if ($all) {
            $keyID = array();
            foreach ($all as $k => $v) {
                $keyID [] = $this->add($v);
            }
            return $keyID;
        }
        return false;
    }

    /**
     * 删除信息
     * @param string $where
     */
    protected function del($where = null)
    {
        $_where = $this->joint_where($where);
        $this->_db->_sql = " DELETE FROM {$this->_table} WHERE {$_where};";
        return $this->_db->sql();
    }


    /**
     * 运行SQL
     * @param null $sql
     * @return mixed
     */
    protected function sql($sql = null)
    {
        $this->_db->_sql = $sql;
        return $this->_db;
    }


    /**
     * 返回当前新增ID
     */
    protected function getId()
    {
        return $this->_db->getId();
    }

    /**
     * 获取表字段
     */
    protected function getField()
    {
        $this->_db->_sql = "SHOW COLUMNS FROM {$this->_table};";
        return $this->_db->getField();
    }

    /**
     * 获取下条自增ID
     */
    protected function getNextId()
    {
        $this->_db->_sql = "SHOW TABLE STATUS LIKE `{$this->_table}`;";
        return $this->_db->getNextId();
    }


    /**
     * 锁表 Mysql in MyISAM
     * @param number $type as true in 1 WRITE  && false in 0 READ
     */
    protected function lock($type = 1)
    {
        if ($type) {
            $this->_db->_sql = "LOCK TABLES `{$this->_table}` WRITE;";
        } else {
            $this->_db->_sql = "LOCK TABLES `{$this->_table}` READ;";
        }
        return $this->_db->sql();
    }

    /**
     * 解锁 Mysql in MyISAM
     */
    protected function unlock()
    {
        $this->_db->_sql = " UNLOCK TABLES;";
        return $this->_db->sql();
    }

    /**
     * 获取当前查询条件表总数
     */
    protected function getTotal()
    {
        $this->joint_field('COUNT(*) AS `conut` ');
        $this->_db->_sql = 'SELECT ' . $this->mergeSqlLogic() . ';';
        return $this->_db->getTotal();
    }

    /**
     * getTotal别名
     * @return mixed
     */
    protected function count()
    {
        return $this->getTotal();
    }

    /**
     * @return array
     */
    protected function getParameter()
    {
        return $this->parameter;
    }

    /**
     * 打印当前运行的SQL
     * @param int $type
     * @return mixed
     */
    protected function p($status = true)
    {
        return $this->_db->p($status);
    }


    /**
     * 开启事务
     * @return mixed
     */
    protected function begin()
    {
        return $this->_db->begin();
    }

    /**
     * 提交事务并结束
     * @return mixed
     */
    protected function commit()
    {
        return $this->_db->commit();
    }

    /**
     * 回滚事务
     * @return mixed
     */
    protected function rollback()
    {
        return $this->_db->rollBack();
    }

    /**
     * 返回错误信息
     * @return mixed
     */
    protected function error()
    {
        return $this->_db->error();
    }

    /**
     * 返回上一条影响行数
     * @return mixed
     */
    protected function rowCount()
    {
        return $this->_db->rowCount();
    }



}