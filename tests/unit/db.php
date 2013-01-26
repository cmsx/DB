<?php

require_once __DIR__ . '/../init.php';

use CMSx\DB;
use CMSx\DB\Item;
use CMSx\DB\Exception;
use CMSx\DB\Connection;

class DBTest extends PHPUnit_Framework_TestCase
{
  /** Таблица создаваемая для тестов */
  protected $table = 'test';

  function testExec()
  {
    $this->needConnection();

    $this->createTable();

    $stmt = DB::Execute('SHOW TABLES');
    $this->assertEquals('PDOStatement', get_class($stmt), 'Получен объект PDOStatement');
    $arr = $stmt->fetchAll(PDO::FETCH_ASSOC);

    try {
      DB::Execute('DESCRIBE never_existing_table');
      $this->fail('Ошибки выбрасывают CMSx\DB\Exception');
    } catch (\CMSx\DB\Exception $e) {
    }

    $ok = false;
    foreach ($arr as $r) { //Ищем свежесозданную таблицу
      if ($r['Tables_in_cmsx_test'] == $this->table) {
        $ok = true;
      }
    }
    $this->assertTrue($ok, 'Таблица была создана');

    $stmt = DB::Select($this->table)->fetchAll();
    $this->assertEquals(2, count($stmt), 'Две записи');

    $count = DB::Select($this->table)
      ->columns('count(*) as total')
      ->fetchOne('total');
    $this->assertEquals(2, $count, 'Две записи в БД');

    $one = DB::Select($this->table)
      ->where(true)
      ->fetchObject('CMSx\DB\Item');

    $this->assertEquals('CMSx\DB\Item', get_class($one), 'Выгрузка в объект');
    $this->assertEquals('two', $one->get('name'), 'Запись с name=two');

    $stmt = DB::Select($this->table)->fetchAllByPair('name', 'is_active');
    $this->assertEquals(2, count($stmt), 'Два элемента в массиве');
    $this->assertEquals($stmt['one'], 0, 'one => 0');
    $this->assertEquals($stmt['two'], 1, 'two => 1');

    DB::Update($this->table)
      ->set('name', 'Hello')
      ->where('name = :name')
      ->bind('name', 'one')
      ->execute();

    $stmt = DB::Select($this->table)
      ->where(array('name' => 'Hello'))
      ->fetch();

    $exp = array('id' => 1, 'is_active' => 0, 'name' => 'Hello');
    $this->assertEquals($exp, $stmt, 'Одна запись в виде ассоциативного массива');

    DB::Delete($this->table)->where(1)->execute();
    $stmt = DB::Select($this->table)->where(1)->fetchOne();
    $this->assertFalse($stmt, 'Элемент был удален');

    $this->dropTable();

    try {
      DB::Select($this->table)->execute();
      $this->fail('Таблица удалена');
    } catch (\Exception $e) {
    }
  }

  function createTable()
  {
    $this->dropTable();

    DB::Create($this->table)
      ->addId()
      ->addBool('is_active')
      ->addChar('name')
      ->execute();

    DB::Insert($this->table)
      ->setArray(array('id' => 1, 'name' => 'one'))
      ->execute();

    DB::Insert($this->table)
      ->setArray(array('id' => 2, 'name' => 'two', 'is_active' => 1))
      ->execute();
  }

  function dropTable()
  {
    DB::Drop($this->table)->execute();
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