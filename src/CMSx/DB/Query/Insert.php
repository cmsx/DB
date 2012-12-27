<?php

namespace CMSx\DB\Query;

use CMSx\DB;
use CMSx\DB\Query;
use CMSx\DB\Builder;

class Insert extends Query
{
  public function make($bind_values = false)
  {
    if (!isset($this->values['insert'])) {
      return null;
    }
    $columns   = array_keys($this->values['insert']);
    $this->sql = 'INSERT INTO ' . Builder::QuoteTable($this->table, $this->prefix)
      . ' (' . Builder::BuildNames($columns) . ')'
      . ' VALUES (' . Builder::BuildValues($this->values['insert'], $bind_values, 'insert') . ')';

    return $this->sql;
  }

  /** Выполняет запрос и возвращает количество затронутых строк. */
  public function execute($values = null)
  {
    $res = parent::execute($values);
    if ($res) {
      return DB::GetLastInsertID();
    } else {
      return false;
    }
  }

  /** Установка значения для изменения в БД */
  public function set($key, $value)
  {
    $this->values['insert'][$key] = $value;

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
}