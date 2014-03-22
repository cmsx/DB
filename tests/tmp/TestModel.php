<?php

use CMSx\DB\Item;

/** Этот класс был создан автоматически 22.03.2014 21:56 по схеме Schema2 */
class TestModel extends Item
{
  const STATUS_NEW = 'new';
  const STATUS_OLD = 'old';

  protected static $status_arr = array(
    self::STATUS_NEW => 'New',
    self::STATUS_OLD => 'Old',
  );

  public function getTable() {
    return 'test_me';
  }

  /** @return \CMSx\DB */
  public function getManager() {
    //TODO: Указать менеджер БД
  }

  public function getId()
  {
    return $this->getAsInt('id');
  }

  public function setId($id)
  {
    return $this->setAsInt('id', $id);
  }

  public function getIsActive()
  {
    return $this->getAsInt('is_active');
  }

  public function setIsActive($is_active)
  {
    return $this->setAsInt('is_active', $is_active);
  }

  public function getStatus()
  {
    return $this->get('status');
  }

  public function getStatusName()
  {
    return static::GetNameForStatus($this->getStatus());
  }

  public function setStatus($status)
  {
    return $this->set('status', $status);
  }

  public function getPrice($decimals = null, $point = null, $thousands = null)
  {
    return $this->getAsFloat('price', $decimals, $point, $thousands);
  }

  public function setPrice($price)
  {
    return $this->set('price', $price);
  }

  public function getTitle()
  {
    return $this->get('title');
  }

  public function setTitle($title)
  {
    return $this->set('title', $title);
  }

  public function getCreatedAt($format = null)
  {
    return $this->getAsDate('created_at', $format);
  }

  public function setCreatedAt($created_at)
  {
    return $this->setAsDate('created_at', $created_at);
  }

  public function getBirthday($format = null)
  {
    return $this->getAsDate('birthday', $format);
  }

  public function setBirthday($birthday)
  {
    return $this->setAsDate('birthday', $birthday);
  }

  public function getText()
  {
    return $this->get('text');
  }

  public function setText($text)
  {
    return $this->set('text', $text);
  }

  public static function GetStatusArr()
  {
    return static::$status_arr;
  }

  public static function GetNameForStatus($status)
  {
    $arr = static::GetStatusArr();

    return isset($arr[$status]) ? $arr[$status] : false;
  }
}
