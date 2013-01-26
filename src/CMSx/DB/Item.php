<?php

namespace CMSx\DB;

use CMSx\DB;
use CMSx\Container;
use CMSx\DB\Exception;

class Item extends Container
{
  protected static $default_table;
  protected $table;

  function __construct($id = null)
  {
    $this->init();

    if ($id) {
      $this->load($id);
    }
  }

  /**
   * Загрузка объекта из БД по ID
   * @throws Exception Если объект не найден
   */
  public function load($id = null)
  {
    if (!$table = $this->getTable()) {
      DB::ThrowError(DB::ERROR_ITEM_NO_TABLE, get_called_class(), 'load', $id);
    }

    if (is_null($id)) {
      $id = $this->get('id');
    }

    if (!$id) {
      DB::ThrowError(DB::ERROR_ITEM_NO_ID, get_called_class(), 'load', $id);
    }

    if ($res = DB::Select($table)->where($id)->fetchOne()) {
      $this->vars = $res;
      return $this;
    }

    DB::ThrowError(DB::ERROR_ITEM_LOAD_NOT_FOUND, get_called_class(), 'load', $id);
  }

  /**
   * Сохранение объекта в БД
   * Если ID не указан - будет создан новый объект.
   * После создания делается load(), чтобы загрузить default-значения
   */
  public function save()
  {
    if (!$table = $this->getTable()) {
      DB::ThrowError(DB::ERROR_ITEM_NO_TABLE, get_called_class(), 'save', null);
    }

    if ($id = $this->get('id')) {
      $this->beforeSave(false);
      DB::Update($table)
        ->setArray($this->vars)
        ->where($id)
        ->execute();
    } else {
      $this->beforeSave(true);
      $id = DB::Insert($table)
        ->setArray($this->vars)
        ->execute();

      $this->load($id);
    }
    $this->afterSave();

    return $this;
  }

  /**
   * Удаление объекта в БД
   * $clean - очистить ли объект от значений
   */
  public function delete($clean = true)
  {
    if (!$table = $this->getTable()) {
      DB::ThrowError(DB::ERROR_ITEM_NO_TABLE, get_called_class(), 'delete', var_export($clean, true));
    }

    if (!$id = $this->get('id')) {
      DB::ThrowError(DB::ERROR_ITEM_NO_ID, get_called_class(), 'delete', var_export($clean, true));
    }

    $this->beforeDelete($id);
    DB::Delete($table)->where($id)->execute();
    $this->afterDelete($id);

    if ($clean) {
      $this->vars = array();
    }

    return $this;
  }

  /** Таблица в БД */
  public function setTable($table)
  {
    $this->table = $table;

    return $this;
  }

  /** Таблица в БД */
  public function getTable()
  {
    return $this->table ? : static::$default_table;
  }

  /**
   * Найти элементы. Возвращает false если ничего не найдено
   *
   * @return Item[]
   */
  public static function Find($where = null, $orderby = null, $onpage = null, $page = null)
  {
    $c = get_called_class();

    return DB::Select(static::$default_table)
      ->where($where)
      ->orderby($orderby)
      ->page($page, $onpage)
      ->fetchAllInObject($c);
  }

  /**
   * Найти один элемент
   *
   * @return Item
   */
  public static function FindOne($where, $orderby = null)
  {
    $c = get_called_class();

    return DB::Select(static::$default_table)
      ->where($where)
      ->orderby($orderby)
      ->limit(1)
      ->fetchObject($c);
  }

  /** Подсчет количества элементов в таблице */
  public static function Count($where = null)
  {
    return (int)DB::Select(static::$default_table)
      ->columns('count(*) as `total`')
      ->where($where)
      ->fetchOne('total');
  }

  /**
   * Запускается до сохранения
   * $is_insert - запись добавляется или обновляется
   */
  protected function beforeSave($is_insert)
  {
  }

  /** Запускается после сохранения */
  protected function afterSave()
  {
  }

  /**
   * Запускается при вызове delete() перед удалением записи из БД
   * В момент вызова все значения объекта доступны
   */
  protected function beforeDelete()
  {
  }

  /**
   * Запускается при вызове delete() после удаления записи из БД
   * В момент вызова все значения объекта доступны
   */
  protected function afterDelete()
  {
  }

  /** Дополнительная инициализация при наследовании */
  protected function init()
  {
  }
}