<?php

namespace CMSx\DB;

use CMSx\DB;
use CMSx\DB\Exception;

abstract class Query
{
  /** @var \PDO */
  protected $connection;
  /** @var \PDOStatement */
  protected $statement;
  /** @var DB */
  protected $manager;

  protected $sql;
  protected $join;
  protected $limit;
  protected $table;
  protected $where;
  protected $prefix;
  protected $values;
  protected $offset;
  protected $having;
  protected $columns;
  protected $orderby;
  protected $groupby;
  protected $binded_values;
  protected $where_and = true;
  protected $having_and = true;
  protected $last_insert_id;

  function __construct($table, \PDO $connection = null, $prefix = null)
  {
    $this->table = $table;

    if (!is_null($connection)) {
      $this->setConnection($connection);
    }

    if (!is_null($prefix)) {
      $this->setPrefix($prefix);
    }
  }

  function __toString()
  {
    return $this->make(true);
  }

  function __invoke()
  {
    return $this->execute();
  }

  /** Создание SQL запроса по настроенным параметрам */
  abstract public function make($bind_values = false);

  /**
   * Выполнение запроса
   * @return \PDOStatement
   * @throws Exception
   */
  public function execute($values = null)
  {
    if (is_array($values)) {
      $this->bindArray($values);
    }

    $this->make();

    if ($this->getManager()) {
      $this->statement = $this->getManager()->query($this);
    } else {
      $this->statement = DB::Execute($this->getConnection(), $this);
    }

    return $this->statement;
  }

  /** Бинд произвольной переменной в запрос */
  public function bind($key, $value)
  {
    if (substr($key, 0, 1) != ':') {
      $key = ':' . $key;
    }
    $this->binded_values[$key] = $value;

    return $this;
  }

  /** Бинд произвольных переменных в запрос из массива ключ-значение */
  public function bindArray(array $values)
  {
    foreach ($values as $key => $val) {
      $this->bind($key, $val);
    }

    return $this;
  }

  /** Получение массива значений разбитых по частям запроса */
  public function getValues()
  {
    return $this->values;
  }

  /** Последний сгенерированный запрос в том виде, каким он был. */
  public function getLastSQL()
  {
    return !empty($this->sql) ? $this->sql : false;
  }

  /** Получение массива значений для всех частей запроса в виде для бинда в PDO */
  public function getBindedValues()
  {
    $binded_values = $this->binded_values;
    if ($this->values) {
      foreach ($this->values as $part => $arr) {
        foreach ($arr as $key => $val) {
          $binded_values[Builder::BuildBinding($part, $key)] = $val;
        }
      }
    }

    return count($binded_values) ? $binded_values : false;
  }

  public function setConnection(\PDO $connection)
  {
    $this->connection = $connection;

    return $this;
  }

  /** @return \PDO */
  public function getConnection()
  {
    if ($this->getManager()) {
      return $this->getManager()->getConnection();
    }

    return $this->connection;
  }

  public function setManager(DB $manager)
  {
    $this->manager = $manager;

    return $this;
  }

  /** @return \CMSx\DB */
  public function getManager()
  {
    return $this->manager;
  }

  public function setPrefix($prefix)
  {
    $this->prefix = $prefix;

    return $this;
  }

  public function getPrefix()
  {
    if (!$this->prefix && $this->getManager()) {
      return $this->getManager()->getPrefix();
    }

    return $this->prefix;
  }

  /** Обработка условия where */
  protected function processWhere($where)
  {
    foreach ($where as $key => $val) {
      if (is_numeric($key)) {
        if ($val === true || $val === false) {
          unset($this->where[$key]);
          $this->where['is_active'] = true;
        } elseif (is_array($val)) {
          $this->processWhere($val);
        } elseif (is_numeric($val)) {
          unset($this->where[$key]);
          $this->where['id'] = $val;
        } elseif (!is_null($val)) {
          $this->where[] = $val;
        }
      } else {
        if (is_array($val)) {
          $this->processWhereIn($key, $val);
        } else {
          $this->where[$key] = $val;
        }
      }
    }

    if ($this->where) {
      $this->setValues($this->where, 'where');
    }
  }

  /** Обработка условия Where IN */
  protected function processWhereIn($column, array $array)
  {
    $keys = array();
    $i = 0;
    foreach ($array as $val) {
      $key    = Builder::CleanKeyName($column) . '_' . ++$i;
      $keys[] = Builder::BuildBinding('where', $key);
      $this->setValue($key, $val, 'where');
    }

    $this->processWhere(array(sprintf('%s IN (%s)', Builder::QuoteKey($column), join(',', $keys))));
  }

  /** Обработка условия WHERE Between */
  protected function processWhereBetween($column, $from, $to)
  {
    $f = Builder::CleanKeyName($column) . '_from';
    $this->setValue($f, $from, 'where');

    $t = Builder::CleanKeyName($column) . '_to';
    $this->setValue($t, $to, 'where');

    $w = sprintf(
      '%s BETWEEN %s AND %s',
      Builder::QuoteKey($column),
      Builder::BuildBinding('where', $f),
      Builder::BuildBinding('where', $t)
    );

    $this->processWhere(array($w));
  }

  /**
   * Установка значения
   * @param $key   - ключ
   * @param $value - значение
   * @param $part  - часть SQL к которой относится
   */
  protected function setValue($key, $value, $part)
  {
    $this->values[$part][$key] = $value;

    return $this;
  }

  /**
   * Установка значений по массиву ключ-значение
   * Если ключ числовой и не запрещено добавление, он будет добавлен с подчеркиванием, напр. :_1
   *
   * @param $array           - массив значений
   * @param $part            - часть SQL к которой относится
   * @param $ignore_num_keys - не добавлять значение, если ключ числовой
   */
  protected function setValues($array, $part, $ignore_num_keys = true)
  {
    foreach ($array as $key => $val) {
      if (is_numeric($key)) {
        if ($ignore_num_keys) {
          continue;
        }
        $key = '_' . $key;
      }
      $this->setValue($key, $val, $part);
    }

    return $this;
  }
}