<?php

namespace CMSx\DB\Query;

use CMSx\DB\Query;
use CMSx\DB\Builder;

class Alter extends Query
{
  public function make($bind_values = false)
  {
    return $this->sql;
  }

  protected function build($action, $definition = null)
  {
    $this->sql = 'ALTER TABLE ' . Builder::QuoteTable($this->table, $this->prefix) . ' '
      . strtoupper($action) . ($definition ? ' ' . $definition : '');

    return $this;
  }

  // ADD

  /**
   * Создание столбца
   * @param $column     - имя столбца
   * @param $definition - условие для создания
   * @param $after      - поместить после заданного столбца, если true - поставить первым
   */
  public function addColumn($column, $definition, $after = null)
  {
    if ($after === true) {
      $after = ' FIRST';
    } elseif (!empty($after)) {
      $after = ' AFTER ' . Builder::QuoteKey($after);
    }
    $def = Builder::QuoteKey($column) . ' ' . $definition . ($after ? $after : '');

    return $this->build('ADD COLUMN', $def);
  }

  /** Добавление индекса по столбцам */
  public function addIndex($columns, $_ = null)
  {
    if (is_array($columns) || is_null($columns)) {
      $index = $columns;
    } else {
      $index = func_get_args();
    }
    $def = '`i_' . join('_', $index) . '` (' . Builder::BuildNames($index) . ')';

    return $this->build('ADD INDEX', $def);
  }

  /** Добавление уникального индекса по столбцам */
  public function addUniqueIndex($columns, $_ = null)
  {
    if (is_array($columns) || is_null($columns)) {
      $index = $columns;
    } else {
      $index = func_get_args();
    }
    $def = '`u_' . join('_', $index) . '` (' . Builder::BuildNames($index) . ')';

    return $this->build('ADD UNIQUE', $def);
  }

  /** Добавление полнотекстового индекса по столбцам */
  public function addFulltextIndex($columns, $_ = null)
  {
    if (is_array($columns) || is_null($columns)) {
      $index = $columns;
    } else {
      $index = func_get_args();
    }
    $def = '`f_' . join('_', $index) . '` (' . Builder::BuildNames($index) . ')';

    return $this->build('ADD FULLTEXT', $def);
  }

  /** Добавление первичного ключа по столбцам */
  public function addPrimaryKey($columns, $_ = null)
  {
    if (is_array($columns) || is_null($columns)) {
      $index = $columns;
    } else {
      $index = func_get_args();
    }
    $def = '(' . Builder::BuildNames($index) . ')';

    return $this->build('ADD PRIMARY KEY', $def);
  }

  // DROP

  public function dropColumn($column)
  {
    return $this->build('DROP COLUMN', Builder::QuoteKey($column));
  }

  public function dropPrimaryKey()
  {
    return $this->build('DROP PRIMARY KEY');
  }

  public function dropIndex($index)
  {
    return $this->build('DROP INDEX', Builder::QuoteKey($index));
  }

  // CHANGE

  /**
   * Изменение столбца
   * @param $column     - имя столбца
   * @param $definition - условие для создания
   * @param $after      - поместить после заданного столбца, если true - поставить первым
   */
  public function modifyColumn($column, $definition, $after = null)
  {
    if ($after === true) {
      $after = ' FIRST';
    } elseif (!empty($after)) {
      $after = ' AFTER ' . Builder::QuoteKey($after);
    }
    $def = Builder::QuoteKey($column) . ' ' . $definition . ($after ? $after : '');

    return $this->build('MODIFY COLUMN', $def);
  }

  /** Переименование таблицы */
  public function rename($name)
  {
    return $this->build('RENAME TO', Builder::QuoteKey($this->prefix . $name));
  }

  /** Установка по какому столбцу таблица сортируется по умолчанию */
  public function setOrderBy($column)
  {
    return $this->build('ORDER BY', Builder::QuoteKey($column));
  }
}