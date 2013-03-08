<?php

require_once __DIR__ . '/../init.php';

use CMSx\DB;
use CMSx\DB\Exception;

class DBTest extends PHPUnit_Framework_TestCase
{
  /** Таблица создаваемая для тестов */
  protected $table = 'test';

  function testSelect()
  {
    $db1 = $this->getDB();

    $q1 = $db1->select($this->table);
    $this->assertEquals('SELECT * FROM `test`', $q1->make(), 'Запрос сформировался');

    $db2 = new DB(null, 'cmsx_');
    $q2  = $db2->select($this->table);
    $this->assertEquals('SELECT * FROM `cmsx_test`', $q2->make(), 'Вариант с префиксом');

    try {
      $q2->execute();
      $this->fail('В запросе нет подключения');
    } catch (Exception $e) {
      $this->assertEquals(DB::ERROR_NO_CONNECTION, $e->getCode(), 'Код ошибки');
    }
  }

  function testAllQueries()
  {
    $db = $this->getDB();
    $db->setPrefix('pre_');

    $db->drop($this->table)->execute();

    $c = $db->create($this->table);
    $c->addId();
    $c->addChar('name');
    $c->execute();

    $db->insert($this->table)
      ->setArray(array('id' => 1, 'name' => 'Hello'))
      ->execute();

    $db->insert($this->table)
      ->setArray(array('id' => 2, 'name' => 'World'))
      ->execute();

    $db->alter($this->table)->addColumn('surname', 'VARCHAR(250)')->execute();

    $r = $db->select($this->table)->fetchOne();
    $this->assertEquals('Hello', $r['name'], 'ID');
    $this->assertEquals(3, count($r), '3 поля');

    $db->delete($this->table)->where(1)->execute();

    $c = $db->select($this->table)->columns('count(*) as `total`')->fetchOne('total');
    $this->assertEquals(1, $c, 'Одна запись');

    $db->truncate($this->table)->execute();

    $c = $db->select($this->table)->columns('count(*) as `total`')->fetchOne('total');
    $this->assertEquals(0, $c, 'Записей нет');

    $db->drop($this->table)->execute();

    try {
      $db->select($this->table)->execute();
      $this->fail('Таблица не существует');
    } catch (Exception $e) {
      $this->assertEquals(DB::ERROR_QUERY, $e->getCode(), 'Код ошибки');
    }
  }

  function testQueryObjExecution()
  {
    $q = new DB\Query\Select('existing_table', $this->getPDO(), 'never_ever_');
    $this->assertEquals('SELECT * FROM `never_ever_existing_table`', $q->make(), 'Префикс подставился');
    $this->assertEmpty($q->getManager(), 'Нет менеджера');
    $this->assertEquals('PDO', get_class($q->getConnection()), 'Есть подключение');

    try {
      $q->execute();
      $this->fail('Должна быть ошибка выполнения запроса');
    } catch (Exception $e) {
      $this->assertEquals(DB::ERROR_QUERY, $e->getCode(), 'Код ошибки');
    }
  }

  function testPrefixAndConnection()
  {
    $pdo = $this->getPDO();
    $this->assertEquals('PDO', get_class($pdo), 'Возвращается объект PDO');

    $db1 = new DB;
    try {
      $db1->getConnection();
      $this->fail('Когда нет соединения - выкидывает исключения');
    } catch (Exception $e) {
      $this->assertEquals(DB::ERROR_NO_CONNECTION, $e->getCode(), 'Код ошибки');
    }

    $this->assertEmpty($db1->getPrefix(), 'Префикса нет');

    $db2 = new DB($pdo, 'test_');

    $db1->setConnection($pdo);
    $this->assertEquals('PDO', get_class($db1->getConnection()), 'Соединение есть 1');
    $this->assertEquals('PDO', get_class($db2->getConnection()), 'Соединение есть 2');

    $db1->setPrefix('test_');
    $this->assertEquals('test_', $db1->getPrefix(), 'Префикс есть 1');
    $this->assertEquals('test_', $db2->getPrefix(), 'Префикс есть 2');
  }

  function testNoConnectionFail()
  {
    $db = new DB;
    try {
      $db->query('SHOW TABLES');
      $this->fail('Нет подключения - должно выбрасываться исключение');
    } catch (Exception $e) {
      $this->assertEquals(DB::ERROR_NO_CONNECTION, $e->getCode(), 'Код ошибки');
    }
  }

  function testBadConnectionFail()
  {
    try {
      DB::PDO('bad', 'connection', 'details', 'given');
      $this->fail('Неверные данные для подключения - должно выбрасываться исключение');
    } catch (Exception $e) {
      $this->assertEquals(DB::ERROR_NO_CONNECTION_AVAILABLE, $e->getCode(), 'Код ошибки');
    }
  }

  function testBadQueryFail()
  {
    $db = $this->getDB();
    try {
      $db->query('SELECT * FROM `never_ever_existing_table`');
      $this->fail('Ошибки MySQL выбрасывают исключения');
    } catch (Exception $e) {
      $this->assertEquals(DB::ERROR_QUERY, $e->getCode(), 'Код ошибки');
    }
  }

  function testQuery()
  {
    $this->createTable();

    $db = $this->getDB();
    $this->assertEquals(0, $db->getQueriesCount(), 'Запросов еще не было');
    $this->assertFalse($db->getQueries(), 'Если запросов не было возвращается false');

    $q   = 'SELECT * FROM ' . $this->table;
    $res = $db->query($q);
    $this->assertEquals(1, $db->getQueriesCount(), 'Один запрос');

    $q_arr = $db->getQueries();
    $this->assertTrue(is_array($q_arr), 'Возвращается массив запросов');
    $this->assertEquals($q, current($q_arr), 'Первый запрос совпадает');

    $this->assertEquals('PDOStatement', get_class($res), 'Запрос возвращает PDOStatement');
  }

  function testQueryWithValues()
  {
    $this->createTable();

    $db = $this->getDB();
    $db->query(
      'INSERT INTO `' . $this->table . '` (`id`, `name`) VALUES (?, ?)',
      array('1', 'Hello')
    );

    $s = $db->query(
      'SELECT * FROM `' . $this->table . '` WHERE id = :id AND name = :name',
      array('id' => 1, 'name' => 'Hello')
    );

    $this->assertEquals(1, $s->rowCount(), 'Найдена 1 запись');
  }

  function createTable()
  {
    $db = $this->getDB();

    $db->query('DROP TABLE IF EXISTS `' . $this->table . '`');

    $q = 'CREATE TABLE `' . $this->table . '` ('
      . '`id` int(10) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY, '
      . '`name` varchar(250) DEFAULT NULL, '
      . '`created_at` timestamp DEFAULT CURRENT_TIMESTAMP'
      . ')';
    $db->query($q);

    $db->query('SELECT * FROM `' . $this->table . '`'); //Проверка что таблица создалась
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