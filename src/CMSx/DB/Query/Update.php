<?php

namespace CMSx\DB\Query;

use CMSx\DB\Query;
use CMSx\DB\Builder;

class Update extends Query
{
  public function make($bind_values = false)
  {
    if (!isset($this->values['set'])) {
      return null;
    }
    $this->sql = 'UPDATE ' . Builder::QuoteTable($this->table, $this->getPrefix())
      . Builder::BuildSet($this->values['set'], $bind_values)
      . Builder::BuildWhere($this->where, $bind_values, $this->where_and)
      . Builder::BuildLimit($this->limit, $this->offset);
    if ($bind_values) {
      $this->sql = Builder::ReplaceBindedValues($this->sql, $this->binded_values);
    }

    return $this->sql;
  }

  /** Выполняет запрос и возвращает количество затронутых строк. */
  public function execute($values = null)
  {
    $res = parent::execute($values);
    if ($res) {
      return $res->rowCount();
    } else {
      return false;
    }
  }

  // QUERY SETUP

  /** Ограничение LIMIT */
  public function limit($limit, $offset = null)
  {
    $this->limit = $limit;
    if (!is_null($offset)) {
      $this->offset = $offset;
    }

    return $this;
  }

  /** Условие WHERE. Массив или перечисление условий. */
  public function where($where, $_ = null)
  {
    $this->processWhere(func_get_args());

    return $this;
  }

  /** Условие WHERE IN. Не подставляет значения, только биндит! */
  public function whereIn($column, array $array)
  {
    $this->processWhereIn($column, $array);

    return $this;
  }

  /** Условие WHERE column = $value */
  public function whereEqual($column, $value)
  {
    $this->processWhere(array($column => $value));

    return $this;
  }

  /**
   * Условие WHERE $column BETWEEN $from AND $to.
   * Не подставляет значения, только биндит!
   */
  public function whereBetween($column, $from, $to)
  {
    $this->processWhereBetween($column, $from, $to);

    return $this;
  }

  /** Установка значения для изменения в БД */
  public function set($key, $value)
  {
    $this->values['set'][$key] = $value;

    return $this;
  }

  /** Установка присвоения выражением, например: `key`=now() */
  public function setExpression($expression)
  {
    $this->values['set'][] = $expression;

    return $this;
  }

  /** Установка данных для изменения в БД по массиву ключ-значение */
  public function setArray(array $array)
  {
    foreach ($array as $key => $val) {
      $this->set($key, $val);
    }

    return $this;
  }

  /** Условие WHERE объединяется AND или OR */
  public function setWhereJoinByAnd($on)
  {
    $this->where_and = (bool)$on;

    return $this;
  }
}