<?php

namespace CMSx\DB;

use CMSx\DB;
use CMSx\Container;
use CMSx\DB\Exception;

abstract class Item extends Container
{
  function __construct($id = null)
  {
    $this->init();

    if ($id) {
      $this->load($id);
    }
  }

  /**
   * Менеджер подключения к БД
   *
   * @return DB
   */
  abstract function getManager();
  
  /** Функция возвращает имя таблицы в БД */
  abstract function getTable();

  /**
   * Загрузка объекта из БД по ID
   * @throws Exception Если объект не найден
   */
  public function load($id = null)
  {
    if (is_null($id)) {
      $id = $this->get('id');
    }

    if (!$id) {
      DB::ThrowError(DB::ERROR_ITEM_NO_ID, get_called_class(), 'load', $id);
    }

    if ($res = $this->getManager()->select($this->getTable())->where($id)->fetchOne()) {
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
    if ($id = $this->get('id')) {
      $this->beforeSave(false);
      $this->getManager()->update($this->getTable())
        ->setArray($this->vars)
        ->where($id)
        ->execute();
    } else {
      $this->beforeSave(true);
      $id = $this->getManager()->insert($this->getTable())
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
    if (!$id = $this->get('id')) {
      DB::ThrowError(DB::ERROR_ITEM_NO_ID, get_called_class(), 'delete', var_export($clean, true));
    }

    $this->beforeDelete($id);
    $this->getManager()->delete($this->getTable())->where($id)->execute();
    $this->afterDelete($id);

    if ($clean) {
      $this->vars = array();
    }

    return $this;
  }

  /** Приведение поля с датой в нужный формат */
  public function getAsDate($column, $format = null)
  {
    if ($v = $this->get($column)) {
      return date($format ? : 'd.m.Y', strtotime($v));
    }

    return false;
  }

  /** Установка значения поля с датой */
  public function setAsDate($column, $value)
  {
    $this->set($column, $value ? date('Y-m-d H:i:s', strtotime($value)) : null);

    return $this;
  }

  /** NumberFormat для числового поля */
  public function getAsFloat($column, $decimals = null, $point = null, $thousands = null)
  {
    if (is_null($decimals)) {
      $decimals = 2;
    }
    if (is_null($point)) {
      $point = '.';
    }
    if (is_null($thousands)) {
      $thousands = '';
    }
    $v = $this->get($column);

    return $v ? number_format($v, $decimals, $point, $thousands) : false;
  }

  /**
   * Найти элементы. Возвращает false если ничего не найдено
   *
   * @return Item[]
   */
  public static function Find($where = null, $orderby = null, $onpage = null, $page = null)
  {
    $c = get_called_class();

    return static::getManager()->select(static::getTable())
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

    return static::getManager()->select(static::getTable())
      ->where($where)
      ->orderby($orderby)
      ->limit(1)
      ->fetchObject($c);
  }

  /** Подсчет количества элементов в таблице */
  public static function Count($where = null)
  {
    return (int) static::getManager()->select(static::getTable())
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