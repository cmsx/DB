<?php

namespace CMSx\DB\Query;

use CMSx\DB;
use CMSx\DB\Query;
use CMSx\DB\Builder;

class Select extends Query
{
  public function make($bind_values = false)
  {
    $this->sql = 'SELECT '
      . ($this->columns ? Builder::BuildNames($this->columns) : '*')
      . ' FROM ' . Builder::QuoteTable($this->table, $this->getPrefix())
      . Builder::BuildJoin($this->join)
      . Builder::BuildWhere($this->where, $bind_values, $this->where_and)
      . Builder::BuildGroupBy($this->groupby)
      . Builder::BuildHaving($this->having, $bind_values, $this->having_and)
      . Builder::BuildOrderBy($this->orderby)
      . Builder::BuildLimit($this->limit, $this->offset);
    if ($bind_values) {
      $this->sql = Builder::ReplaceBindedValues($this->sql, $this->binded_values);
    }

    return $this->sql;
  }

  // FETCHING

  /** Получение следующего элемента из запроса в виде массива. */
  public function fetch()
  {
    if (!$this->statement) {
      $this->execute();
    }
    $res = $this->statement->fetch(\PDO::FETCH_ASSOC);

    return $res ? $res : false;
  }

  /**
   * Получение всех элементов полученных запросом
   * $column - одномерный массив содержащий указанный столбец
   */
  public function fetchAll($column = null)
  {
    if (!$this->statement) {
      $this->execute();
    }

    $res = $this->statement->fetchAll(\PDO::FETCH_ASSOC);

    if (empty($res)) {
      return false;
    }

    if (!is_null($column)) {
      //Проверка наличия столбца
      $row = current($res);
      if (!isset($row[$column])) {
        DB::ThrowError(DB::ERROR_SELECT_BY_PAIR_NO_VALUE, $column);
      }

      $out = array();
      foreach ($res as $row) {
        $out[] = $row[$column];
      }

      return $out;
    } else {
      return $res ? $res : false;
    }
  }

  /** Получение следующего элемента из запроса в виде объекта */
  public function fetchObject($class, $constructor_parameters = null)
  {
    if (!$this->statement) {
      $this->execute();
    }
    $this->statement->setFetchMode(\PDO::FETCH_CLASS | \PDO::FETCH_PROPS_LATE, $class, $constructor_parameters);
    $res = $this->statement->fetch();

    return $res ? $res : false;
  }

  /** Получение одного элемента по запросу. Автоматически ставит LIMIT 1 */
  public function fetchOne($column = null)
  {
    $this->limit(1);
    $this->execute();
    $res = $this->statement->fetch(\PDO::FETCH_ASSOC);
    if (!is_null($column)) {
      return isset($res[$column]) ? $res[$column] : false;
    }

    return $res;
  }

  /** Получение массива ключ-значение */
  public function fetchAllByPair($key, $value)
  {
    if (!$res = $this->fetchAll()) {
      return false;
    }

    $out = array();
    if (!array_key_exists($key, current($res))) {
      DB::ThrowError(DB::ERROR_SELECT_BY_PAIR_NO_KEY, $key);
    }
    if (!array_key_exists($value, current($res))) {
      DB::ThrowError(DB::ERROR_SELECT_BY_PAIR_NO_VALUE, $value);
    }
    foreach ($res as $row) {
      $out[$row[$key]] = $row[$value];
    }

    return $out;
  }

  /** Получение всех элементов по запросу в указанный объект */
  public function fetchAllInObject($class, $constructor_parameters = null)
  {
    $out = array();
    while ($obj = $this->fetchObject($class, $constructor_parameters)) {
      $out[] = $obj;
    }

    return $out;
  }

  // QUERY SETUP

  /** Столбцы для выборки. Массив или перечисление столбцов. */
  public function columns($columns, $_ = null)
  {
    if (is_array($columns) || is_null($columns)) {
      $this->columns = $columns;
    } else {
      $this->columns = func_get_args();
    }

    return $this;
  }

  /** Объединение таблиц */
  public function join($table, $on, $type = null)
  {
    $this->join[$table] = array(
      'table' => $this->getPrefix() . $table,
      'on'    => $on,
      'type'  => $type,
    );

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

  /** Сортировка. Массив или перечисление условий. */
  public function orderby($orderby, $_ = null)
  {
    if (is_array($orderby) || is_null($orderby)) {
      $this->orderby = $orderby;
    } else {
      $this->orderby = func_get_args();
    }

    return $this;
  }

  /** Объединение. Массив или перечисление условий. */
  public function groupby($groupby, $_ = null)
  {
    if (is_array($groupby) || is_null($groupby)) {
      $this->groupby = $groupby;
    } else {
      $this->groupby = func_get_args();
    }

    return $this;
  }

  /** Условие HAVING. Массив или перечисление условий. */
  public function having($having, $_ = null)
  {
    if (is_array($having) || is_null($having)) {
      $this->having = $having;
    } else {
      $this->having = func_get_args();
    }
    if ($this->having) {
      $this->setValues($this->having, 'having');
    }

    return $this;
  }

  /** Ограничение LIMIT */
  public function limit($limit, $offset = null)
  {
    $this->limit = $limit;
    if (!is_null($offset)) {
      $this->offset = $offset;
    }

    return $this;
  }

  /** Формирование ограничения LIMIT для постраничности */
  public function page($page, $onpage)
  {
    if (empty($page) || $page < 1) {
      $page = 1;
    }

    $this->limit($onpage, (($page - 1) * $onpage));

    return $this;
  }

  /** Условие WHERE объединяется AND или OR */
  public function setWhereJoinByAnd($on)
  {
    $this->where_and = (bool)$on;

    return $this;
  }

  /** Условие HAVING объединяется AND или OR */
  public function setHavingJoinByAnd($on)
  {
    $this->having_and = (bool)$on;

    return $this;
  }
}