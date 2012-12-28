<?php

namespace CMSx\DB;

use CMSx\DB;
use CMSx\Container;

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

  /** Загрузка объекта из БД */
  public function load($id = null)
  {
    $exc_str = get_called_class() . '->load(' . $id . '): ';

    if (!$table = $this->getTable()) {
      throw new Exception($exc_str . 'для объекта не указана таблица');
    }

    if (is_null($id)) {
      $id = $this->get('id');
    }

    if (!$id) {
      throw new Exception($exc_str . 'для объекта не указан ID');
    }

    $this->vars = DB::Select($table)->where($id)->fetchOne();

    return $this;
  }

  /**
   * Сохранение объекта в БД
   * Если ID не указан - будет создан новый объект.
   * После создания делается load(), чтобы загрузить default-значения
   */
  public function save()
  {
    if (!$table = $this->getTable()) {
      throw new Exception(get_called_class() . '->save(): для объекта не указана таблица');
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
    $exc_str = get_called_class() . '->delete(' . var_export($clean, true) . '): ';
    if (!$table = $this->getTable()) {
      throw new Exception($exc_str . 'для объекта не указана таблица');
    }

    if (!$id = $this->get('id')) {
      throw new Exception($exc_str . 'для объекта не указан ID');
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