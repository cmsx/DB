<?php

require_once __DIR__ . '/../init.php';

use CMSx\DB;
use CMSx\DB\Connection;
use CMSx\DB\Exception;

class ConnectionTest extends PHPUnit_Framework_TestCase
{
  function testGoodConnection()
  {
    $this->needConnection();

    $this->assertEquals('PDO', get_class(Connection::Get()), 'Получен объект PDO');
  }

  function testBadConnection()
  {
    new Connection('localhost', 'never', 'existing', 'access', null, 'bad');
    try {
      Connection::Get('bad');
    } catch (\CMSx\DB\Exception $e) {
      $this->assertEquals(DB::ERROR_CANT_CONNECT, $e->getCode(), 'Код ошибки подключения');
      $this->assertNotEmpty($e->getMessage(), 'Текст ошибки есть');
    }
  }

  function needConnection()
  {
    try {
      Connection::Get();
    } catch (Exception $e) {
      $this->assertEquals(DB::ERROR_NO_CONNECTION_AVAILABLE, $e->getCode(), 'Нет подключения');
      $this->assertNotEmpty($e->getMessage(), 'Текст ошибки есть');
      $this->markTestSkipped('Не настроено подключение к БД: см. файл tests/config.php');
    }
  }
}