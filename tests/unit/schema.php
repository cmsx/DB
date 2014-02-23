<?php

require_once __DIR__ . '/../init.php';

use CMSx\DB;
use CMSx\DB\Schema;
use CMSx\DB\Exception;

//Изначальная схема таблицы в БД
class Schema1 extends Schema
{
  public function getTable()
  {
    return 'test_me';
  }

  protected function init()
  {
    $this->getQuery()
      ->addId()
      ->addEnum('status', array('new', 'old'))
      ->addChar('name')
      ->addInt('price')
      ->addText();
  }
}

/**
 * Как будто со временем схема изменилась :)
 * Можно удалять-добавлять поля, менять их местами
 */
class Schema2 extends Schema
{
  public function getTable()
  {
    return 'test_me';
  }

  protected function init()
  {
    $this->name  = 'My Test';
    $this->getQuery()
      ->addId()
      ->addEnum('status', array('new', 'old'))
      ->addPrice('price')
      ->addChar('title')
      ->addTimeCreated()
      ->addTime('birthday', false)
      ->addText();
  }
}

class SchemaTest extends PHPUnit_Framework_TestCase
{
  function testCreate()
  {
    //Создаем таблицу
    $s = new Schema1($this->getDB());
    $this->assertEquals('test_me', $s->getTable(), 'Имя таблицы');
    $this->assertEquals('Test Me', $s->getName(), 'Автоматически созданное название таблицы');

    $s->createTable();

    $exp  = array(
      array(
        'Field'   => 'id',
        'Type'    => 'int(10) unsigned',
        'Null'    => 'NO',
        'Key'     => 'PRI',
        'Default' => null,
        'Extra'   => 'auto_increment',
      ),
      array(
        'Field'   => 'status',
        'Type'    => "enum('new','old')",
        'Null'    => 'NO',
        'Key'     => null,
        'Default' => null,
        'Extra'   => null,
      ),
      array(
        'Field'   => 'name',
        'Type'    => 'varchar(250)',
        'Null'    => 'YES',
        'Key'     => '',
        'Default' => null,
        'Extra'   => '',
      ),
      array(
        'Field'   => 'price',
        'Type'    => 'int(10) unsigned',
        'Null'    => 'YES',
        'Key'     => '',
        'Default' => '0',
        'Extra'   => '',
      ),
      array(
        'Field'   => 'text',
        'Type'    => 'text',
        'Null'    => 'YES',
        'Key'     => '',
        'Default' => null,
        'Extra'   => '',
      ),
    );
    $stmt = $this->getDB()->query('DESCRIBE `test_me`');
    $this->assertEquals($exp, $stmt->fetchAll(PDO::FETCH_ASSOC), 'Таблица создалась корректно');

    try {
      $s->createTable();
      $this->fail('Таблица уже существует');
    } catch (Exception $e) {
    }

    //Меняем схему
    $s = new Schema2($this->getDB());
    $this->assertEquals('My Test', $s->getName(), 'Произвольное название таблицы');
    $s->updateTable();

    $exp  = array(
      array(
        'Field'   => 'id',
        'Type'    => 'int(10) unsigned',
        'Null'    => 'NO',
        'Key'     => 'PRI',
        'Default' => null,
        'Extra'   => 'auto_increment',
      ),
      array(
        'Field'   => 'status',
        'Type'    => "enum('new','old')",
        'Null'    => 'NO',
        'Key'     => null,
        'Default' => null,
        'Extra'   => null,
      ),
      array(
        'Field'   => 'price',
        'Type'    => 'float(10,2) unsigned',
        'Null'    => 'YES',
        'Key'     => '',
        'Default' => null,
        'Extra'   => '',
      ),
      array(
        'Field'   => 'title',
        'Type'    => 'varchar(250)',
        'Null'    => 'YES',
        'Key'     => '',
        'Default' => null,
        'Extra'   => '',
      ),
      array(
        'Field'   => 'created_at',
        'Type'    => 'timestamp',
        'Null'    => 'NO',
        'Key'     => '',
        'Default' => 'CURRENT_TIMESTAMP',
        'Extra'   => '',
      ),
      array(
        'Field'   => 'birthday',
        'Type'    => 'datetime',
        'Null'    => 'YES',
        'Key'     => '',
        'Default' => '',
        'Extra'   => '',
      ),
      array(
        'Field'   => 'text',
        'Type'    => 'text',
        'Null'    => 'YES',
        'Key'     => '',
        'Default' => null,
        'Extra'   => '',
      ),
    );
    $stmt = $this->getDB()->query('DESCRIBE `test_me`');
    $this->assertEquals($exp, $stmt->fetchAll(PDO::FETCH_ASSOC), 'Таблица обновилась корректно');
  }

  function testBuildModel()
  {
    $s = new Schema2($this->getDB());
    $code = $s->buildModel('TestModel');

    $file = __DIR__ . '/../tmp/TestModel.php';
    file_put_contents($file, $code);
    $this->assertTrue(is_file($file), 'Файл модели создался');

    require_once $file;
    $m = new TestModel;

    $arr = TestModel::GetStatusArr();
    $m->setStatus(TestModel::STATUS_OLD);
    $this->assertEquals('new', TestModel::STATUS_NEW, 'Константы ENUM создались');
    $this->assertEquals('Old', $arr[TestModel::STATUS_OLD], 'Массив имен констант сгенерился');
    $this->assertEquals('Old', $m->getStatusName(), 'Название ENUM сгенерилось и есть функция доступа');

    $m->setTitle('One');
    $this->assertEquals('One', $m->getTitle(), 'Обычные значения');

    $m->setCreatedAt('2012-12-21 12:21');
    $this->assertEquals('21.12.2012', $m->getCreatedAt(), 'Формат даты по-умолчанию');
    $this->assertEquals('21.12.2012 12:21', $m->getCreatedAt('d.m.Y H:i'), 'Произвольный формат даты');

    $m->setPrice(12345.6789);
    $this->assertEquals(12345.68, $m->getPrice(), 'Форматирование по-умолчанию');
    $this->assertEquals('12`345,67890', $m->getPrice(5, ',', '`'), 'Произвольное форматирование');
  }

  protected function tearDown()
  {
    $this->dropTable();
  }

  protected function setUp()
  {
    $this->dropTable();
  }

  function dropTable()
  {
    $this->getDB()->drop('test_me')->execute();
  }

  function getDB()
  {
    return new DB($this->getPDO());
  }

  function getPDO()
  {
    try {
      return DB::PDO('localhost', 'test', 'test', 'test');
    } catch (PDOException $e) {
      $this->markTestSkipped('Нет подключения к БД');
    }
  }
}