<?php

namespace CMSx;

use CMSx\DB\Query;
use CMSx\DB\Query\Alter;
use CMSx\DB\Query\Create;
use CMSx\DB\Query\Delete;
use CMSx\DB\Query\Drop;
use CMSx\DB\Query\Insert;
use CMSx\DB\Query\Select;
use CMSx\DB\Query\Truncate;
use CMSx\DB\Query\Update;
use CMSx\DB\Exception;
use CMSx\DB\Connection;

class DB
{
  /** Не настроено соединение с БД */
  const ERROR_NO_CONNECTION_AVAILABLE = 10;
  /** Невозможно подключиться к БД */
  const ERROR_CANT_CONNECT = 11;
  /** В объекте нет соединения */
  const ERROR_NO_CONNECTION = 15;
  /** Соединение не является объектом PDO */
  const ERROR_BAD_CONNECTION = 16;
  /** Ошибка при select`е по паре ключ-значение, нет такого ключа */
  const ERROR_SELECT_BY_PAIR_NO_KEY = 20;
  /** Ошибка при select`е по паре ключ-значение, нет такого значения */
  const ERROR_SELECT_BY_PAIR_NO_VALUE = 21;
  /** Ошибка при попытке создания полнотекстового индекса на таблице не MyISAM */
  const ERROR_FULLTEXT_ONLY_MYISAM = 31;
  /** Ошибка при выполнении запросе */
  const ERROR_QUERY = 40;
  /** Для объекта Item не указана таблица */
  const ERROR_ITEM_NO_TABLE = 50;
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

  protected static $prefix;
  /** @var \PDO */
  protected static $connection;

  protected static $errors_arr = array(
    self::ERROR_NO_CONNECTION_AVAILABLE => 'Не настроено соединение с БД',
    self::ERROR_CANT_CONNECT            => 'Невозможно подключиться к БД',
    self::ERROR_NO_CONNECTION           => 'Для запросов не указано соединения',
    self::ERROR_BAD_CONNECTION          => 'Соединение не является объектом PDO',
    self::ERROR_SELECT_BY_PAIR_NO_KEY   => 'В запросе нет ключа "%s"',
    self::ERROR_SELECT_BY_PAIR_NO_VALUE => 'В запросе нет значений "%s"',
    self::ERROR_FULLTEXT_ONLY_MYISAM    => 'Попытка назначения полнотекстового индекса таблице "%s" с типом "%s"',
    self::ERROR_QUERY                   => 'Ошибка выполнения "%s": %s',
    self::ERROR_ITEM_NO_TABLE           => '%s->%s(%s): для объекта не указана таблица',
    self::ERROR_ITEM_NO_ID              => '%s->%s(%s): для объекта не указан ID',
    self::ERROR_ITEM_LOAD_NOT_FOUND     => '%s->%s(%s): объект не найден',
  );

  /** Префикс по умолчанию для всех запросов */
  public static function SetPrefix($prefix)
  {
    self::$prefix = $prefix;
  }

  /** Префикс по умолчанию для всех запросов */
  public static function GetPrefix()
  {
    return self::$prefix;
  }

  /**
   * Выполнение запроса в БД.
   *
   * @param Query|string $sql
   *
   * @return \PDOStatement|bool
   */
  public static function Execute($sql, $values = null)
  {
    if (!self::$connection) {
      try {
        self::$connection = Connection::Get();
      } catch (\Exception $e) {
        self::ThrowError(self::ERROR_NO_CONNECTION);
      }
    }
    if (!(self::$connection instanceof \PDO)) {
      self::ThrowError(self::ERROR_BAD_CONNECTION);
    }

    if ($sql instanceof Query) {
      $q = $sql->make();
      if (is_null($values)) {
        $values = $sql->getBindedValues();
      }
    } else {
      $q = $sql;
    }

    $stmt = self::$connection->prepare($q);
    $res  = $stmt->execute($values ? $values : null) ? $stmt : false;

    if (!$res) {
      $stmt_err = $stmt->errorInfo();
      self::ThrowError(
        self::ERROR_QUERY,
        $sql instanceof Query ? $sql->make(true) : $sql,
        '[' . $stmt_err[1] . '] ' . $stmt_err[2]
      );
    }

    return $res;
  }

  /** Последний добавленный ID */
  public static function GetLastInsertID()
  {
    return self::$connection->lastInsertId();
  }

  /**
   * Подключение по умолчанию для всех запросов
   * @static
   *
   * @param \PDO $connection
   */
  public static function SetConnection(\PDO $connection)
  {
    self::$connection = $connection;
  }

  /** @return Select */
  public static function Select($table)
  {
    return self::Configure(new Select($table));
  }

  /** @return Update */
  public static function Update($table)
  {
    return self::Configure(new Update($table));
  }

  /** @return Delete */
  public static function Delete($table)
  {
    return self::Configure(new Delete($table));
  }

  /** @return Create */
  public static function Create($table)
  {
    return self::Configure(new Create($table));
  }

  /** @return Truncate */
  public static function Truncate($table)
  {
    return self::Configure(new Truncate($table));
  }

  /** @return Insert */
  public static function Insert($table)
  {
    return self::Configure(new Insert($table));
  }

  /** @return Drop */
  public static function Drop($table)
  {
    return self::Configure(new Drop($table));
  }

  /** @return Alter */
  public static function Alter($table)
  {
    return self::Configure(new Alter($table));
  }

  /** Установка подключения и префикса по умолчанию в запрос */
  protected function Configure(Query $query)
  {
    if (self::$prefix) {
      $query->setPrefix(self::$prefix);
    }
    if (self::$connection) {
      $query->setConnection(self::$connection);
    }

    return $query;
  }

  /**
   * Текст ошибки по коду. Если переданы аргументы, они будут подставлены в sprintf
   *
   * @static
   *
   * @param              $code
   * @param array|string $args
   * @param string       $_
   */
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

  /**
   * Выброс ошибки по коду
   */
  public static function ThrowError($code, $args = null, $_ = null)
  {
    $args = func_get_args();
    array_shift($args);
    $msg = static::GetMessage($code, $args);
    throw new Exception($msg, $code);
  }
}