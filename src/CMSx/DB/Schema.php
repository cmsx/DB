<?php

namespace CMSx\DB;

use CMSx\DB;
use CMSx\DB\Query\Create;
use CMSx\DB\Exception;

/** При наследовании в init() должны быть заданы имя таблицы и запрос для её создания через DB::Create()... */
abstract class Schema
{
  /** Имя создаваемой таблицы без префикса */
  protected $table;
  /** @var Create */
  protected $query;
  /** Название таблицы */
  protected $name;

  function __construct()
  {
    $this->init();

    if (empty($this->name)) {
      $a   = explode('_', $this->table);
      $res = array();
      foreach ($a as $str) {
        $res[] = ucfirst($str);
      }
      $this->name = join(' ', $res);
    }
  }

  /** Создание таблицы */
  public function createTable($drop = false)
  {
    if (is_null($this->table)) {
      throw new Exception(get_called_class() . ': Имя таблицы не определено');
    }
    if (is_null($this->query)) {
      throw new Exception(get_called_class() . ': SQL для создания таблицы не определен');
    }

    if ($drop) {
      DB::Drop($this->table)->execute();
    }

    return $this->query->execute();
  }

  /** Забивание таблицы стартовым контентом */
  public function fillContent()
  {
    return true;
  }

  /** Имя таблицы в БД */
  public function getTable()
  {
    return $this->table;
  }

  /** Название таблицы */
  public function getName()
  {
    return $this->name;
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
    return $this->query->getDefinition($component);
  }

  /** Настройка схемы */
  protected function init()
  {
  }
}