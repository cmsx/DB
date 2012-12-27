<?php

namespace CMSx\DB\Query;

use CMSx\DB\Query;
use CMSx\DB\Builder;

/**
 * @method Delete bind($key, $value) Бинд произвольной переменной в запрос
 * @method Delete bindArray(array $values) Бинд произвольных переменных в запрос из массива ключ-значение
 * @method Delete setPrefix($prefix) Префикс для всех таблиц в запросах
 */
class Delete extends Query
{
  public function make($bind_values = false)
  {
    $this->sql = 'DELETE FROM ' . Builder::QuoteTable($this->table, $this->prefix)
      . Builder::BuildWhere($this->where, $bind_values, $this->where_and)
      . Builder::BuildLimit($this->limit, $this->offset);
    if ($bind_values) {
      $this->sql = Builder::ReplaceBindedValues($this->sql, $this->binded_values);
    }

    return $this->sql;
  }

  /** Выполняет запрос и возвращает количество затронутых строк */
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
    if (is_array($where) || is_null($where)) {
      $this->where = $where;
    } else {
      $this->where = func_get_args();
    }
    $this->processWhere();

    return $this;
  }

  /** Условие WHERE объединяется AND или OR */
  public function setWhereJoinByAnd($on)
  {
    $this->where_and = (bool)$on;

    return $this;
  }
}