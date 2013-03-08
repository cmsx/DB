<?php

require_once __DIR__ . '/../init.php';

use CMSx\DB;
use CMSx\DB\Item;
use CMSx\DB\Exception;

/** Настройки для подключения к БД */
class MyConfig
{
  public static function GetDB()
  {
    return new DB(self::GetPDO());
  }

  public static function GetPDO()
  {
    return DB::PDO('localhost', 'test', 'test', 'test');
  }
}

class MyItem extends Item
{
  /**
   * Менеджер подключения к БД
   *
   * @return DB
   */
  public function getManager()
  {
    return MyConfig::GetDB();
  }

  /** Функция возвращает имя таблицы в БД */
  public function getTable()
  {
    return 'test';
  }

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
  protected static $table;

  function testLoad()
  {
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
    $i = MyItem::FindOne(array('id' => 1, 'is_active' => true));
    $this->assertEquals('MyItem', get_class($i), 'Объект загрузился');

    $i = MyItem::FindOne(null, 'id DESC');
    $this->assertEquals('MyItem', get_class($i), 'Объект загрузился');
    $this->assertEquals('Two', $i->get('name'), 'Выборка отсортировалась');

    $this->assertFalse(MyItem::FindOne(3), 'Несуществующая запись');
  }

  function testCount()
  {
    $this->assertEquals(2, MyItem::Count(), 'Подсчет всего строк');
    $this->assertEquals(1, MyItem::Count(array('is_active' => 1)), 'Одна включенная запись');
  }

  function testSave()
  {
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
    $i = new MyItem;

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
    $i = new MyItem;
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
    $i = new MyItem;
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

  function testGetFloatFormatted()
  {
    $i = new MyItem;
    $i->set('price', 123456.789);

    $exp = '123456.79';
    $this->assertEquals($exp, $i->getAsFloat('price'), 'Приведение по-умолчанию');

    $exp = '123`456,78900';
    $this->assertEquals($exp, $i->getAsFloat('price', 5, ',', '`'), 'Форматирование');
  }

  protected function setUp()
  {
    try {
      $this->createTable();
    } catch (\Exception $e) {
    }
  }

  protected function tearDown()
  {
    try {
      $this->dropTable();
    } catch (\Exception $e) {
    }
  }

  public static function setUpBeforeClass()
  {
    $i = new MyItem();
    static::$table = $i->getTable();
  }

  protected function createTable()
  {
    $this->dropTable();

    $this->getDB()->create(static::$table)
      ->addId()
      ->addBool('is_active')
      ->addChar('name')
      ->addTimeCreated()
      ->execute();

    $this->getDB()->insert(static::$table)
      ->set('is_active', true)
      ->set('name', 'One')
      ->execute();

    $this->getDB()->insert(static::$table)
      ->set('name', 'Two')
      ->execute();
  }

  protected function dropTable()
  {
    $this->getDB()->drop(static::$table)->execute();
  }

  function getDB()
  {
    return new DB($this->getPDO());
  }

  function getPDO()
  {
    try {
      return MyConfig::GetPDO();
    } catch (PDOException $e) {
      $this->markTestSkipped('Нет подключения к БД');
    }
  }
}