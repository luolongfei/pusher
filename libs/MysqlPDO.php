<?php
/**
 * Mysql PDO
 *
 * @author mybsdc <mybsdc@gmail.com>
 * @date 2019/8/24
 * @time 14:36
 */

namespace Luolongfei\Lib;

use Luolongfei\Lib\Base;
use Luolongfei\Lib\Log;

class MysqlPDO extends Base
{
    /**
     * @var MysqlPDO
     */
    protected static $instance;

    /**
     * @var \PDO
     */
    private $db;

    /**
     * MysqlPDO constructor.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            config('database.mysql.host'),
            config('database.mysql.port'),
            config('database.mysql.database'),
            config('database.mysql.charset')
        );

        try {
            $this->db = new \PDO(
                $dsn,
                config('database.mysql.username'),
                config('database.mysql.password')
            );
        } catch (\PDOException $e) {
            LOG::error('连接Mysql出错：' . $e->getMessage());
        }

//        $this->db->exec(sprintf('SET NAMES %s;', config('mysql.charset')));
    }

    /**
     * @return MysqlPDO
     * @throws \Exception
     */
    public static function getInstance()
    {
        if (!self::$instance instanceof MysqlPDO) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @param $sql
     *
     * @return int
     */
    public function exec($sql)
    {
        return $this->db->exec($sql);
    }

    public function get($sql)
    {
        $query = $this->db->query($sql);
        $rows = $query->fetchAll(\PDO::FETCH_ASSOC);

        return $rows;
    }

    public function __destruct()
    {
        $this->db = null;
    }
}