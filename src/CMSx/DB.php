<?php

namespace CMSx;

use CMSx\DB\Exception;
use CMSx\DB\Query\Alter;
use CMSx\DB\Query\Create;
use CMSx\DB\Query\Delete;
use CMSx\DB\Query\Drop;
use CMSx\DB\Query\Insert;
use CMSx\DB\Query\Select;
use CMSx\DB\Query\Truncate;
use CMSx\DB\Query\Update;

class DB
{
  /** Не настроено соединение с БД */
  const ERROR_NO_CONNECTION_AVAILABLE = 10;
  /** В объекте нет соединения */
  const ERROR_NO_CONNECTION = 15;
  /** Ошибка при выполнении запросе */
  const ERROR_QUERY = 40;
  /** Ошибка при попытке создания полнотекстового индекса на таблице не MyISAM */
  const ERROR_FULLTEXT_ONLY_MYISAM = 31;

  /** Ошибка при select`е по паре ключ-значение, нет такого ключа */
  const ERROR_SELECT_BY_PAIR_NO_KEY = 20;
  /** Ошибка при select`е по паре ключ-значение, нет такого значения */
  const ERROR_SELECT_BY_PAIR_NO_VALUE = 21;

  /** Ошибка при загрузке объекта Item не указан ID */
  const ERROR_ITEM_NO_ID = 51;
  /** Ошибка при загрузке объекта Item по ID */
  const ERROR_ITEM_LOAD_NOT_FOUND = 52;

  /** Тип таблиц MyISAM принят по умолчанию в MySQL */
  const TYPE_MyISAM = 'MyISAM';
  /** Таблицы с поддержкой транзакций и блокировкой строк. */
  const TYPE_InnoDB = 'InnoDB';
  /** Данные для этой таблицы хранятся только в памяти. */
  const TYPE_HEAP = 'HEAP';

  /** Запрет удаления внешних значений */
  const FOREIGN_RESTRICT = 1;
  /** Удалить записи относящиеся к внешнему ключу */
  const FOREIGN_CASCADE = 2;
  /** Обнуление в таблице внешних ключей */
  const FOREIGN_SET_NULL = 3;

  /** Префикс используемый в запросах */
  protected $prefix;
  /** @var \PDO */
  protected $connection;
  /** Выполненные запросы */
  protected $queries = array();

  protected static $errors_arr = array(
    self::ERROR_NO_CONNECTION_AVAILABLE => 'Не настроено соединение с БД',
    self::ERROR_NO_CONNECTION           => 'Для запросов не указано соединения',
    self::ERROR_SELECT_BY_PAIR_NO_KEY   => 'В запросе нет ключа "%s"',
    self::ERROR_SELECT_BY_PAIR_NO_VALUE => 'В запросе нет значений "%s"',
    self::ERROR_FULLTEXT_ONLY_MYISAM    => 'Попытка назначения полнотекстового индекса таблице "%s" с типом "%s"',
    self::ERROR_QUERY                   => 'Ошибка выполнения "%s": %s',
    self::ERROR_ITEM_NO_ID              => '%s->%s(%s): для объекта не указан ID',
    self::ERROR_ITEM_LOAD_NOT_FOUND     => '%s->%s(%s): объект не найден',
  );

  function __construct(\PDO $connection = null, $prefix = null)
  {
    if ($connection) {
      $this->setConnection($connection);
    }

    if ($prefix) {
      $this->setPrefix($prefix);
    }
  }

  public function setConnection(\PDO $connection)
  {
    $this->connection = $connection;

    return $this;
  }

  /** @return \PDO */
  public function getConnection()
  {
    if (!$this->connection) {
      static::ThrowError(self::ERROR_NO_CONNECTION);
    }

    return $this->connection;
  }

  /** Префикс для автоматически собираемых запросов */
  public function setPrefix($prefix)
  {
    $this->prefix = $prefix;

    return $this;
  }

  /** Префикс для автоматически собираемых запросов */
  public function getPrefix()
  {
    return $this->prefix;
  }

  /** Массив выполненных запросов или false */
  public function getQueries()
  {
    return $this->getQueriesCount()
      ? $this->queries
      : false;
  }

  /** Количество выполненных запросов */
  public function getQueriesCount()
  {
    return count($this->queries);
  }

  /**
   * Выполнение запроса. $values - параметры для bind`инга в запрос
   *
   * @return \PDOStatement
   */
  public function query($sql, $values = null)
  {
    if (!$this->connection) {
      static::ThrowError(self::ERROR_NO_CONNECTION);
    }

    $this->queries[] = $sql;

    $stmt = static::Execute($this->connection, $sql, $values);

    return $stmt;
  }

  public function getLastInsertId()
  {
    return $this->getConnection()->lastInsertId();
  }

  /** @return Select */
  public function select($table)
  {
    $s = new Select($table);
    $s->setManager($this);

    return $s;
  }

  /** @return Update */
  public function update($table)
  {
    $s = new Update($table);
    $s->setManager($this);

    return $s;
  }

  /** @return Delete */
  public function delete($table)
  {
    $s = new Delete($table);
    $s->setManager($this);

    return $s;
  }

  /** @return Create */
  public function create($table)
  {
    $s = new Create($table);
    $s->setManager($this);

    return $s;
  }

  /** @return Truncate */
  public function truncate($table)
  {
    $s = new Truncate($table);
    $s->setManager($this);

    return $s;
  }

  /** @return Insert */
  public function insert($table)
  {
    $s = new Insert($table);
    $s->setManager($this);

    return $s;
  }

  /** @return Drop */
  public function drop($table)
  {
    $s = new Drop($table);
    $s->setManager($this);

    return $s;
  }

  /** @return Alter */
  public function alter($table)
  {
    $s = new Alter($table);
    $s->setManager($this);

    return $s;
  }

  /** Создание объекта PDO */
  public static function PDO($host, $user, $pass, $dbname, $charset = 'UTF8')
  {
    try {
      $conn = new \PDO('mysql:host=' . $host . ';dbname=' . $dbname, $user, $pass);
      if ($charset) {
        if (!is_null($charset)) {
          $conn->query('SET NAMES ' . $charset);
        }
      }
    } catch (\PDOException $e) {
      self::ThrowError(self::ERROR_NO_CONNECTION_AVAILABLE);
    }

    return $conn;
  }

  /** Текст ошибки по коду. Если переданы аргументы, они будут подставлены в sprintf */
  public static function GetMessage($code, $args = null, $_ = null)
  {
    if (!isset(static::$errors_arr[$code])) {
      return false;
    }
    $msg = static::$errors_arr[$code];
    if (!is_null($args)) {
      if (is_array($args)) {
        array_unshift($args, $msg);
      } else {
        $args    = func_get_args();
        $args[0] = $msg;
      }

      return call_user_func_array('sprintf', $args);
    }

    return $msg;
  }

  /** Выброс ошибки по коду */
  public static function ThrowError($code, $args = null, $_ = null)
  {
    $args = func_get_args();
    array_shift($args);
    $msg = static::GetMessage($code, $args);
    throw new Exception($msg, $code);
  }

  /** Выполнение запроса */
  public static function Execute(\PDO $connection, $sql, $values = null)
  {
    $stmt = $connection->prepare($sql);
    $res = $stmt->execute($values);

    if (!$res) {
      $stmt_err = $stmt->errorInfo();
      self::ThrowError(
        self::ERROR_QUERY,
        $sql, //$sql instanceof Query ? $sql->make(true) : $sql,
        '[' . $stmt_err[1] . '] ' . $stmt_err[2]
      );
    }

    return $stmt;
  }
}