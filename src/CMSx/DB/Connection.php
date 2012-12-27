<?php

namespace CMSx\DB;

class Connection
{
  protected $host;
  protected $user;
  protected $pass;
  protected $dbname;
  protected $charset;

  /** @var \PDO */
  protected $connection;

  /** Реестр подключений */
  protected static $cons = array();

  /** Имя подключения по умолчанию */
  const DEFAULT_NAME = 'default';

  /**
   * Настройки доступа. До первого запроса подключение не происходит!
   * После инициализации объект кладется в реестр подключений
   */
  function __construct($host, $user, $pass, $dbname, $charset = null, $name = null)
  {
    $this->host    = $host;
    $this->user    = $user;
    $this->pass    = $pass;
    $this->dbname  = $dbname;
    $this->charset = $charset;

    self::Add($this, $name);
  }

  /**
   * Инициализация соединения
   * @return \PDO
   * @throws \Exception
   */
  protected function PDO()
  {
    if (!$this->connection) {
      try {
        $this->connection =
          new \PDO('mysql:host=' . $this->host . ';dbname=' . $this->dbname, $this->user, $this->pass);
        if (!is_null($this->charset)) {
          $this->connection->query('SET NAMES ' . $this->charset);
        }
      } catch (\Exception $e) {
        throw new \Exception ('Не могу подключиться к БД!');
      }
    }

    return $this->connection;
  }

  /**
   * Добавление в реестр подключений
   */
  protected static function Add(self $obj, $name)
  {
    if (is_null($name)) {
      $name = self::DEFAULT_NAME;
    }
    self::$cons[$name] = $obj;
  }

  /**
   * Получение подключения
   * Возвращает либо объект PDO либо false
   *
   * @return \PDO
   */
  public static function Get($name = null)
  {
    if (is_null($name)) {
      $name = self::DEFAULT_NAME;
    }
    if (isset (self::$cons[$name])) {
      return self::$cons[$name]->PDO();
    } else {
      if ($name == self::DEFAULT_NAME) {
        throw new \Exception('Не настроено соединение с БД');
      }

      return false;
    }
  }
}