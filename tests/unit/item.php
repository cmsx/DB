<?php

require_once __DIR__ . '/../init.php';

use CMSx\DB;
use CMSx\DB\Item;
use CMSx\DB\Connection;
use CMSx\DB\Exception;

class MyItem extends Item
{
  static public $default_table = 'test';

  protected function beforeDelete()
  {
    $this->set('before_delete', true);
  }

  protected function afterDelete()
  {
    $this->set('after_delete', true);
  }

  protected function beforeSave($is_insert)
  {
    if ($is_insert) {
      $this->set('name', $this->get('name') . ' Yeah!');
    }
  }

  protected function afterSave()
  {
    $this->set('yeah', 123);
  }
}

class ItemTest extends PHPUnit_Framework_TestCase
{
  function testLoad()
  {
    $this->needConnection();

    $i = new MyItem(1);
    $this->assertEquals('One', $i->get('name'), 'Первый элемент');

    $i = new MyItem;
    $i->load(2);
    $this->assertEquals('Two', $i->get('name'), 'Второй элемент');

    try {
      $i = new MyItem();
      $i->load();
    } catch (Exception $e) {
      $this->assertEquals(DB::ERROR_ITEM_NO_ID, $e->getCode(), 'Код ошибки "не указан ID"');
      $this->assertNotEmpty($e->getMessage(), 'Есть текст ошибки "не указан ID"');
    }

    try {
      $i = new MyItem(3);
      $this->fail('Несуществующий элемент должен выбрасывать исключение');
    } catch (Exception $e) {
      $this->assertEquals(DB::ERROR_ITEM_LOAD_NOT_FOUND, $e->getCode(), 'Код ошибки "не найден"');
      $this->assertNotEmpty($e->getMessage(), 'Есть текст ошибки "не найден"');
    }
  }

  function testFind()
  {
    $this->needConnection();

    $arr = MyItem::Find();
    $this->assertTrue(is_array($arr), 'Получен набор элементов #1');
    $this->assertEquals(2, count($arr), 'В наборе 2 элемента');

    $i = current($arr);
    $this->assertEquals('MyItem', get_class($i), 'Объект загружен в нужный класс');

    $arr = MyItem::Find(1);
    $this->assertTrue(is_array($arr), 'Получен набор элементов #2');
    $this->assertEquals(1, count($arr), 'В наборе 1 элемент');

    $arr = MyItem::Find(null, 'id DESC', 1, 2);
    $this->assertTrue(is_array($arr), 'Получен набор элементов #2');
    $this->assertEquals(1, count($arr), 'В наборе 1 элемент');

    $i = current($arr);
    $this->assertEquals('One', $i->get('name'), 'Правильная сортировка');
  }

  function testFindOne()
  {
    $this->needConnection();

    $i = MyItem::FindOne(array('id' => 1, 'is_active' => true));
    $this->assertEquals('MyItem', get_class($i), 'Объект загрузился');

    $i = MyItem::FindOne(null, 'id DESC');
    $this->assertEquals('MyItem', get_class($i), 'Объект загрузился');
    $this->assertEquals('Two', $i->get('name'), 'Выборка отсортировалась');

    $this->assertFalse(MyItem::FindOne(3), 'Несуществующая запись');
  }

  function testCount()
  {
    $this->needConnection();

    $this->assertEquals(2, MyItem::Count(), 'Подсчет всего строк');
    $this->assertEquals(1, MyItem::Count(array('is_active' => 1)), 'Одна включенная запись');
  }

  function testSave()
  {
    $this->needConnection();

    $i = new Item;
    try {
      $i->save();
      $this->fail('Для элемента не указана таблица');
    } catch (Exception $e) {
      $this->assertEquals(DB::ERROR_ITEM_NO_TABLE, $e->getCode(), 'Исключение "таблица не указана"');
      $this->assertNotEmpty($e->getMessage(), 'Текст ошибки "таблица не указана" есть');
    }

    $this->assertEquals(1, MyItem::Count(array('is_active' => 1)), 'Включенных элементов 1 шт.');

    $i = new MyItem(2);
    $i->set('is_active', 1);
    $i->save();

    $this->assertEquals(2, MyItem::Count(array('is_active' => 1)), 'Включенных элементов 2 шт.');
    $this->assertEquals('Two', $i->get('name'), 'Триггер до сохранения не изменил имя, т.к. обновляем');
    $this->assertEquals(123, $i->get('yeah'), 'Триггер после сохранения сработал');

    $i = new MyItem();
    $i->set('name', 'Three');
    $i->set('is_active', 1);
    $i->save(); //Создаем новый элемент

    $this->assertEquals(3, MyItem::Count(array('is_active' => 1)), 'Включенных элементов 3 шт.');
    $this->assertEquals('Three Yeah!', $i->get('name'), 'Триггер до сохранения изменил имя, т.к. новый элемент');
    $this->assertNotEmpty($i->get('created_at'), 'Значения по-умолчанию были загружены из БД');
  }

  function testDelete()
  {
    $this->needConnection();

    $i = new Item;

    try {
      $i->delete();
      $this->fail('Для элемента не указана таблица');
    } catch (Exception $e) {
      $this->assertEquals(DB::ERROR_ITEM_NO_TABLE, $e->getCode(), 'Исключение "таблица не указана"');
      $this->assertNotEmpty($e->getMessage(), 'Текст ошибки "таблица не указана" есть');
    }

    $i->setTable(MyItem::$default_table);

    try {
      $i->delete();
      $this->fail('Для элемента не указан ID');
    } catch (Exception $e) {
      $this->assertEquals(DB::ERROR_ITEM_NO_ID, $e->getCode(), 'Исключение "ID не указан"');
      $this->assertNotEmpty($e->getMessage(), 'Текст ошибки "ID не указан" есть');
    }

    $i = new MyItem(1);
    $i->delete(false);

    $this->assertEquals(0, MyItem::Count(1), 'Записей не найдено');

    $this->assertEquals('One', $i->get('name'), 'Удаление без очищения объекта');
    $this->assertTrue($i->get('before_delete'), 'Триггер перед удалением сработал');
    $this->assertTrue($i->get('after_delete'), 'Триггер после удаления сработал');

    $i = new MyItem(2);
    $i->delete();

    $this->assertEquals(0, MyItem::Count(2), 'Записей не найдено');
    $this->assertEmpty($i->get('name'), 'Удаление с очищением объекта');
  }

  /**
   * @dataProvider dateFormatsGet
   */
  function testGetAsDate($date, $format, $exp, $msg)
  {
    $i = new Item;
    $i->set('date', $date);
    $this->assertEquals($exp, $i->getAsDate('date', $format), $msg);
  }

  function dateFormatsGet()
  {
    //$date, $format, $exp, $msg
    return array(
      array(false, null, false, 'Нулевая дата #1'),
      array('0000-00-00 00:00:00', null, false, 'Нулевая дата #2'),
      array('2012-01-10', null, '10.01.2012', 'Год вначале'),
      array('21.12.2012', 'Y-m-d H:i', '2012-12-21 00:00', 'Преобразование'),
    );
  }

  /**
   * @dataProvider dateFormatsSet
   */
  function testSetAsDate($value, $exp, $msg)
  {
    $i = new Item;
    $i->setAsDate('date', $value);
    $this->assertEquals($exp, $i->getAsDate('date'), $msg);
  }

  function dateFormatsSet()
  {
    //$value, $exp, $msg
    return array(
      array('12.01.2012', '12.01.2012', 'Сквозное преобразование'),
      array('2012-01-01 00:00', '01.01.2012', 'Разные форматы')
    );
  }

  protected function setUp()
  {
    if ($this->checkConnection()) {
      $this->createTable();
    }
  }

  protected function checkConnection()
  {
    try {
      Connection::Get();

      return true;
    } catch (Exception $e) {
      return false;
    }
  }

  protected function tearDown()
  {
    if ($this->checkConnection()) {
      $this->dropTable();
    }
  }

  protected function createTable()
  {
    $this->dropTable();

    DB::Create(MyItem::$default_table)
      ->addId()
      ->addBool('is_active')
      ->addChar('name')
      ->addTimeCreated()
      ->execute();

    DB::Insert(MyItem::$default_table)
      ->set('is_active', true)
      ->set('name', 'One')
      ->execute();

    DB::Insert(MyItem::$default_table)
      ->set('name', 'Two')
      ->execute();
  }

  protected function dropTable()
  {
    DB::Drop(MyItem::$default_table)->execute();
  }

  protected function needConnection()
  {
    try {
      Connection::Get();
    } catch (Exception $e) {
      $this->markTestSkipped('Не настроено подключение к БД: см. файл tests/config.php');
    }
  }
}