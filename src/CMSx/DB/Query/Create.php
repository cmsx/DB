<?php

namespace CMSx\DB\Query;

use CMSx\DB;
use CMSx\DB\Query;
use CMSx\DB\Builder;

class Create extends Query
{
  protected $definition = array(
    'type'        => DB::TYPE_MyISAM,
    'columns'     => array(),
    'enum'        => array(),
    'index'       => array(),
    'unique'      => array(),
    'fulltext'    => array(),
    'foreign_key' => array(),
    'primary_key' => null,
  );

  public function make($bind_values = false)
  {
    $parts = array();
    foreach ($this->definition['columns'] as $col => $def) {
      $parts[] = '`' . $col . '` ' . $def;
    }

    foreach ($this->definition['index'] as $name => $index) {
      $parts[] = 'INDEX `' . $name . '` (' . Builder::BuildNames($index) . ')';
    }

    foreach ($this->definition['unique'] as $name => $index) {
      $parts[] = 'UNIQUE INDEX `' . $name . '` (' . Builder::BuildNames($index) . ')';
    }

    foreach ($this->definition['fulltext'] as $name => $index) {
      $parts[] = 'FULLTEXT `' . $name . '` (' . Builder::BuildNames($index) . ')';
    }

    if (!is_null($this->definition['primary_key'])) {
      $parts[] = 'PRIMARY KEY (' . Builder::BuildNames($this->definition['primary_key']) . ')';
    }

    foreach ($this->definition['foreign_key'] as $name => $arr) {
      $parts[] = 'FOREIGN KEY `' . $name . '` (`' . $arr['column'] . '`)'
        . ' REFERENCES `' . $this->getPrefix() . $arr['f_table'] . '`(`' . $arr['f_column'] . '`)'
        . ' ON DELETE ' . Builder::BuildReferenceAction($arr['on_delete'])
        . ' ON UPDATE ' . Builder::BuildReferenceAction($arr['on_update']);
    }

    $this->sql = 'CREATE TABLE ' . Builder::QuoteTable($this->table, $this->getPrefix()) . " (\n  "
      . join(",\n  ", $parts) . "\n) ENGINE=" . $this->definition['type'];

    return $this->sql;
  }

  /**
   * Получение компонентов запроса
   *
   * * type - тип таблицы DB::TYPE_*
   *
   * * columns - столбцы для создания
   *
   * * index - массив имя индекса => набор столбцов
   *
   * * unique - массив имя уникального индекса => набор столбцов
   *
   * * fulltext - полнотекстовый индекс (только для MyISAM)
   *
   * * primary_key - столбцы для первичного ключа
   */
  public function getDefinition($component = null)
  {
    if (is_null($component)) {
      return $this->definition;
    }

    return isset($this->definition[$component]) ? $this->definition[$component] : null;
  }

  /** @return $this */
  public function add($column, $definition)
  {
    $this->definition['columns'][$column] = $definition;

    return $this;
  }

  /** @return $this */
  public function addPrimaryKey($columns, $_ = null)
  {
    if (is_array($columns) || is_null($columns)) {
      $index = $columns;
    } else {
      $index = func_get_args();
    }
    $this->definition['primary_key'] = $index;

    return $this;
  }

  /** @return $this */
  public function addIndex($columns, $_ = null)
  {
    if (is_array($columns) || is_null($columns)) {
      $index = $columns;
    } else {
      $index = func_get_args();
    }
    $this->definition['index']['i_' . join('_', $index)] = $index;

    return $this;
  }

  /** @return $this */
  public function addUniqueIndex($columns, $_ = null)
  {
    if (is_array($columns) || is_null($columns)) {
      $index = $columns;
    } else {
      $index = func_get_args();
    }
    $this->definition['unique']['u_' . join('_', $index)] = $index;

    return $this;
  }

  /** @return $this */
  public function addFulltextIndex($columns, $_ = null)
  {
    if ($this->definition['type'] != DB::TYPE_MyISAM) {
      DB::ThrowError(DB::ERROR_FULLTEXT_ONLY_MYISAM, $this->table, $this->definition['type']);
    }
    if (is_array($columns) || is_null($columns)) {
      $index = $columns;
    } else {
      $index = func_get_args();
    }
    $this->definition['fulltext']['f_' . join('_', $index)] = $index;

    return $this;
  }

  /**
   * @param string       $table
   * @param array|string $columns   - список столбцов
   * @param int          $on_delete - SQL::FOREIGN_RESTRICT | SQL::FOREIGN_CASCADE | SQL::FOREIGN_SET_NULL
   * @param int          $on_update - SQL::FOREIGN_RESTRICT | SQL::FOREIGN_CASCADE | SQL::FOREIGN_SET_NULL
   */

  /**
   * Создание внешнего ключа. Автоматически меняет тип создаваемой таблицы на InnoDB
   *
   * @param string $column    - столбец создаваемой таблицы
   * @param string $f_table   - внешняя таблица
   * @param string $f_column  - столбец внешней таблицы
   * @param int    $on_delete - Действие при удалении. По умолчанию = SQL::FOREIGN_RESTRICT.
   * Еще варианты: SQL::FOREIGN_CASCADE | SQL::FOREIGN_SET_NULL
   * @param int    $on_update - Действие при изменении. Если не задано - равно действию при удалении
   *
   * @return $this
   */
  public function addForeignKey($column, $f_table, $f_column, $on_delete = null, $on_update = null)
  {
    $this->definition['foreign_key']['fk_' . $column] = array(
      'column'    => $column,
      'f_table'   => $f_table,
      'f_column'  => $f_column,
      'on_delete' => $on_delete,
      'on_update' => $on_update ? : $on_delete,
    );

    $this->setType(DB::TYPE_InnoDB);

    return $this;
  }

  /**
   * @param $type - тип таблицы: MyISAM, InnoDB и т.п.
   */
  public function setType($type)
  {
    if (!empty($this->definition['fulltext']) && $type != DB::TYPE_MyISAM) {
      DB::ThrowError(DB::ERROR_FULLTEXT_ONLY_MYISAM, $this->table, $type);
    }
    $this->definition['type'] = $type;

    return $this;
  }

  // ЧАСТО ИСПОЛЬЗУЕМЫЕ ТИПЫ ПОЛЕЙ

  /**
   * id - INT UNSIGNED AUTO_INCREMENT
   * 
   * @return $this
   */
  public function addId($col = null)
  {
    return $this
      ->add(($col ? $col : 'id'), 'INT UNSIGNED AUTO_INCREMENT')
      ->addPrimaryKey('id');
  }

  /** parent_id - INT UNSIGNED DEFAULT NULL */
  public function addForeignId($col = null)
  {
    return $this->add(($col ? $col : 'parent_id'), 'INT UNSIGNED DEFAULT NULL');
  }

  /** price - FLOAT(10,2) UNSIGNED */
  public function addPrice($col = null, $unsigned = true)
  {
    return $this->add(($col ? $col : 'price'), 'FLOAT(10,2)' . ($unsigned ? ' UNSIGNED' : ''));
  }

  /** text - $long ? LONGTEXT : TEXT */
  public function addText($col = null, $long = false)
  {
    return $this->add(($col ? $col : 'text'), ($long ? 'LONGTEXT' : 'TEXT'));
  }

  /** BOOL DEFAULT $default = 0 */
  public function addBool($col, $default = 0)
  {
    return $this->add($col, 'BOOL DEFAULT ' . $default);
  }

  /** $unix ? TIMESTAMP : DATETIME, DEFAULT 0 */
  public function addTime($col, $unix = true)
  {
    return $this->add($col, ($unix ? 'TIMESTAMP' : 'DATETIME') . ' DEFAULT 0');
  }

  /** TIMESTAMP DEFAULT CURRENT_TIMESTAMP */
  public function addTimeCreated($col = null)
  {
    return $this->add($col ? $col : 'created_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
  }

  /** TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP */
  public function addTimeUpdated($col = null)
  {
    return $this->add($col ? $col : 'updated_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
  }

  /** VARCHAR( $length ? $length : 250 ) DEFAULT NULL */
  public function addChar($col, $length = null)
  {
    return $this->add($col, 'VARCHAR(' . ($length ? $length : 250) . ') DEFAULT NULL');
  }

  /** INT UNSIGNED DEFAULT 0 */
  public function addInt($col)
  {
    return $this->add($col, 'INT UNSIGNED DEFAULT 0');
  }

  /** TINYINT UNSIGNED DEFAULT 0 */
  public function addTinyInt($col)
  {
    return $this->add($col, 'TINYINT UNSIGNED DEFAULT 0');
  }

  /** BIGINT UNSIGNED DEFAULT 0 */
  public function addBigInt($col)
  {
    return $this->add($col, 'BIGINT UNSIGNED DEFAULT 0');
  }

  /** ENUM ("value", 12) ($null ? : NOT NULL) */
  public function addEnum($col, $values, $null = null)
  {
    $q = 'ENUM (' . Builder::BuildValues($values) . ')' . ($null ? : ' NOT NULL');
    $this->definition['enum'][$col] = $values;

    return $this->add($col, $q);
  }
}