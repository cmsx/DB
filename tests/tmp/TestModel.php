<?php

use CMSx\DB\Item;

/** Этот класс был создан автоматически 26.01.2013 10:57 по схеме Schema2 */
class TestModel extends Item
{
  protected static $default_table = 'test_me';

  public function getId()
  {
    return $this->get('id');
  }

  public function setId($id)
  {
    return $this->set('id', $id);
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

  public function getText()
  {
    return $this->get('text');
  }

  public function setText($text)
  {
    return $this->set('text', $text);
  }
}
