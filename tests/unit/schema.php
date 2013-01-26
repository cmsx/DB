<?php

require_once __DIR__ . '/../init.php';

use CMSx\DB;
use CMSx\DB\Schema;
use CMSx\DB\Connection;
use CMSx\DB\Exception;

//Изначальная схема таблицы в БД
class Schema1 extends Schema
{
  protected function init()
  {
    $this->table = 'test_me';
    $this->query = DB::Create($this->table)
      ->addId()
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
  protected function init()
  {
    $this->table = 'test_me';
    $this->name  = 'My Test';
    $this->query = DB::Create($this->table)
      ->addId()
      ->addPrice('price')
      ->addChar('title')
      ->addText();
  }
}

class SchemaTest extends PHPUnit_Framework_TestCase
{
  function testCreate()
  {
    $this->needConnection();

    //Создаем таблицу
    $s = new Schema1();
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
    $stmt = DB::Execute('DESCRIBE `test_me`');
    $this->assertEquals($exp, $stmt->fetchAll(PDO::FETCH_ASSOC), 'Таблица создалась корректно');

    try {
      $s->createTable();
      $this->fail('Таблица уже существует');
    } catch (Exception $e) {
    }

    //Меняем схему
    $s = new Schema2();
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
        'Field'   => 'text',
        'Type'    => 'text',
        'Null'    => 'YES',
        'Key'     => '',
        'Default' => null,
        'Extra'   => '',
      ),
    );
    $stmt = DB::Execute('DESCRIBE `test_me`');
    $this->assertEquals($exp, $stmt->fetchAll(PDO::FETCH_ASSOC), 'Таблица обновилась корректно');
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
    try {
      Connection::Get();
    } catch (Exception $e) {
      return; //Если нет соединения, дропать ничего не требуется
    }

    DB::Drop('test_me')->execute();
  }

  function needConnection()
  {
    try {
      Connection::Get();
    } catch (Exception $e) {
      $this->markTestSkipped('Не настроено подключение к БД: см. файл tests/config.php');
    }
  }
}