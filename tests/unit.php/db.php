<?php

require_once __DIR__ . '/../init.php';

use CMSx\DB;

class DBTest extends PHPUnit_Framework_TestCase
{
  function testSelectPrefix()
  {
    $sql = DB::Select('pages')->setPrefix('yeah_')->make();
    $exp = 'SELECT * FROM `yeah_pages`';
    $this->assertEquals($exp, $sql, 'Префикс подставляется если имя таблицы простое');

    $sql = DB::Select('pages p')->setPrefix('yeah_')->make();
    $exp = 'SELECT * FROM `yeah_pages` `p`';
    $this->assertEquals($exp, $sql, 'Префикс подставляется если имя таблицы простое и содержит альяс');

    $sql = DB::Select('`pages`')->setPrefix('yeah_')->make();
    $exp = 'SELECT * FROM `pages`';
    $this->assertEquals($exp, $sql, 'Если имя таблицы содержит "`", префикс не подставляется');

    $sql = DB::Select('pages p, users u')->setPrefix('yeah_')->make();
    $exp = 'SELECT * FROM pages p, users u';
    $this->assertEquals($exp, $sql, 'Если имя таблицы содержит ",", префикс не подставляется');
  }

  function testSelectByID()
  {
    $sql = DB::Select('pages')->where(12)->make();
    $exp = 'SELECT * FROM `pages` WHERE `id`=:where_id';
    $this->assertEquals($exp, $sql, 'Выборка по ID');
  }

  function testSelectByWhereArray()
  {
    $sql  = DB::Select('pages')->where(array('some'=> 'thing'));
    $vals = $sql->getBindedValues();
    $exp  = 'SELECT * FROM `pages` WHERE `some`=:where_some';
    $this->assertEquals($exp, $sql->make(), 'Выборка по массиву условий');
    $this->assertEquals('thing', $vals[':where_some'], 'Успешно забиндилось');

    $sql = DB::Select('pages')->where('`some`="thing"', '`another`>1');
    $exp = 'SELECT * FROM `pages` WHERE `some`="thing" AND `another`>1';
    $this->assertEquals($exp, $sql->make(), 'Выборка по строковым условиям');
    $this->assertFalse($sql->getBindedValues(), 'Ничего не биндилось');

    $sql = DB::Select('pages')->where(array('id'=> 12, 'is_active'=> 1));
    $exp = 'SELECT * FROM `pages` WHERE `id`=12 AND `is_active`=1';
    $this->assertEquals($exp, $sql->make(true), 'Значения подставляются в запрос');
    $this->assertEquals($exp, (string)$sql, 'Преобразование объекта в строку');

    $sql = DB::Select('pages')->where(12, true);
    $exp = 'SELECT * FROM `pages` WHERE `id`=12 AND `is_active`=1';
    $this->assertEquals($exp, $sql->make(true), 'Значения подставляются в запрос');
  }

  function testSelectColumns()
  {
    $sql1 = DB::Select('pages')->columns('id', '`title`', 'something');
    $sql2 = DB::Select('pages')->columns(array('id', '`title`', 'something'));
    $exp  = 'SELECT `id`, `title`, `something` FROM `pages`';
    $this->assertEquals($exp, $sql1->make(), 'Столбцы перечислением');
    $this->assertEquals($exp, $sql2->make(), 'Столбцы в массиве');
  }

  function testSelectOrderBy()
  {
    $sql1 = DB::Select('pages')->orderby('id', '`title`', 'something DESC');
    $sql2 = DB::Select('pages')->orderby(array('id', '`title`', 'something DESC'));
    $exp  = 'SELECT * FROM `pages` ORDER BY `id`, `title`, something DESC';
    $this->assertEquals($exp, $sql1->make(), 'Сортировка перечислением');
    $this->assertEquals($exp, $sql2->make(), 'Сортировка в массиве');
  }

  function testSelectLimit()
  {
    $sql = DB::Select('pages')->limit(5)->make();
    $exp = 'SELECT * FROM `pages` LIMIT 5';
    $this->assertEquals($exp, $sql, 'Выборка с ограничением 5 штук');

    $sql = DB::Select('pages')->limit(5, 10)->make();
    $exp = 'SELECT * FROM `pages` LIMIT 10, 5';
    $this->assertEquals($exp, $sql, 'Выборка с ограничением 5 штук и отступом 10');

    $sql = DB::Select('pages')->page(3, 5)->make();
    $exp = 'SELECT * FROM `pages` LIMIT 10, 5';
    $this->assertEquals($exp, $sql, 'Постраничная выборка 3-я страница, по 5 штук на странице');
  }

  function testSelectJoin()
  {
    $sql = DB::Select('pages p')
      ->join('users u', 'u.id=p.user_id', 'left')
      ->join('non_users nu', 'nu.id=p.user_id', 'right')
      ->make();
    $exp = 'SELECT * FROM `pages` `p` LEFT JOIN `users` `u` ON u.id=p.user_id '
      . 'RIGHT JOIN `non_users` `nu` ON nu.id=p.user_id';
    $this->assertEquals($exp, $sql, 'Выборка по трем таблицам');
  }

  function testSelectBind()
  {
    $sql = DB::Select('pages')
      ->where('id > :min', 'status = :status')
      ->bind('min', 12)
      ->bind(':status', 'new')
      ->setWhereJoinByAnd(false);
    $exp = 'SELECT * FROM `pages` WHERE id > :min OR status = :status';
    $this->assertEquals($exp, $sql->make(), 'Запрос с подстановкой');

    $exp = array(':min'=> 12, ':status'=> 'new');
    $this->assertEquals($exp, $sql->getBindedValues(), 'Пробиндилось корректно');

    $exp = 'SELECT * FROM `pages` WHERE id > 12 OR status = "new"';
    $this->assertEquals($exp, $sql->make(true), 'Забинденые параметры подставились в запрос');
  }

  function testSelectHaving()
  {
    $sql = DB::Select('pages')
      ->columns('id', 'SUM(price) as `total`')
      ->groupby('parent_id')
      ->having('`total` > 0')
      ->make();
    $exp = 'SELECT `id`, SUM(price) as `total` FROM `pages` GROUP BY `parent_id` HAVING `total` > 0';
    $this->assertEquals($exp, $sql, 'Запрос с условием Having');

    $sql  = DB::Select('pages')
      ->columns('id', 'SUM(price) as `total`')
      ->groupby('parent_id')
      ->having(array('total'=> 0));
    $exp1 = 'SELECT `id`, SUM(price) as `total` FROM `pages` GROUP BY `parent_id` HAVING `total`=:having_total';
    $exp2 = 'SELECT `id`, SUM(price) as `total` FROM `pages` GROUP BY `parent_id` HAVING `total`=0';
    $this->assertEquals($exp1, $sql->make(), 'Запрос с условием Having');
    $this->assertEquals($exp2, $sql->make(true), 'Запрос с условием Having с подставленными значениями');
  }

  function testSelectAll()
  {
    $sql = DB::Select('pages')
      ->where(12)
      ->join('users u', 'u.id = p.user_id')
      ->columns('id', 'title')
      ->orderby('created_at', 'deleted_at DESC')
      ->groupby('name')
      ->having('id > 0')
      ->limit(10, 20);
    $exp = 'SELECT `id`, `title` FROM `pages` JOIN `users` `u` ON u.id = p.user_id WHERE `id`=:where_id '
      . 'GROUP BY `name` HAVING id > 0 ORDER BY `created_at`, deleted_at DESC LIMIT 20, 10';
    $this->assertEquals($exp, $sql->make(), 'Порядок следования конструкций SQL');
  }

  function testUpdate()
  {
    $sql = DB::Update('pages')
      ->where(12)
      ->set('id', 15)
      ->set('name', 'John')
      ->setExpression('`date`=now()')
      ->setExpression('`some`=thing(:me)')
      ->bind('me', 'igor')
      ->limit(10);
    $exp1
          =
      'UPDATE `pages` SET `id`=:set_id, `name`=:set_name, `date`=now(), `some`=thing(:me) WHERE `id`=:where_id LIMIT 10';
    $exp2 = 'UPDATE `pages` SET `id`=15, `name`="John", `date`=now(), `some`=thing("igor") WHERE `id`=12 LIMIT 10';
    $this->assertEquals($exp1, $sql->make(), 'Update с плейсхолдерами');
    $this->assertEquals($exp2, $sql->make(true), 'Update с подставленными значениями');
  }

  function testDelete()
  {
    $sql  = DB::Delete('pages')
      ->where(12, '`created_at` > now()')
      ->limit(3);
    $exp1 = 'DELETE FROM `pages` WHERE `created_at` > now() AND `id`=:where_id LIMIT 3';
    $exp2 = 'DELETE FROM `pages` WHERE `created_at` > now() OR `id`=12 LIMIT 3';
    $this->assertEquals($exp1, $sql->make(), 'Delete с плейсхолдерами');
    $sql->setWhereJoinByAnd(false);
    $this->assertEquals($exp2, $sql->make(true), 'Delete с подставленными значениями');
  }

  function testDrop()
  {
    $sql = DB::Drop('pages');
    $this->assertEquals('DROP TABLE IF EXISTS `pages`', $sql->make(), 'Drop if exists таблицы');

    $sql->setIfExists(false);
    $this->assertEquals('DROP TABLE `pages`', $sql->make(), 'Drop таблицы');
  }

  function testTruncate()
  {
    $sql = DB::Truncate('pages');
    $this->assertEquals('TRUNCATE TABLE `pages`', $sql->make(), 'Truncate таблицы');
  }

  function testInsert()
  {
    $exp1    = 'INSERT INTO `pages` (`countme`, `foo`, `another`) VALUES (:insert_countme, :insert_foo, :insert_another)';
    $exp2    = 'INSERT INTO `pages` (`countme`, `foo`, `another`) VALUES (12, "bar", NULL)';
    $exp_arr = array(
      ':insert_countme' => 12,
      ':insert_foo'     => 'bar',
      ':insert_another' => null
    );

    $sql = DB::Insert('pages')
      ->setArray(
      array(
        'countme' => 12,
        'foo'     => 'bar',
        'another' => null
      )
    );
    $this->assertEquals($exp1, $sql->make(), 'Insert с плейсхолдерами №1');
    $this->assertEquals($exp2, $sql->make(true), 'Insert с подставленными значениями №1');
    $this->assertEquals($exp_arr, $sql->getBindedValues(), 'Значения пробиндились корректно №1');

    $sql = DB::Insert('pages')
      ->set('countme', 12)
      ->set('foo', 'bar')
      ->set('another', null);
    $this->assertEquals($exp1, $sql->make(), 'Insert с плейсхолдерами №2');
    $this->assertEquals($exp2, $sql->make(true), 'Insert с подставленными значениями №2');
    $this->assertEquals($exp_arr, $sql->getBindedValues(), 'Значения пробиндились корректно №2');
  }

  function testCreate()
  {
    $sql = DB::Create('pages')
      ->addId()
      ->addChar('title')
      ->addForeignId()
      ->addEnum('type', array('abc', 'cde'))
      ->addText()
      ->addIndex('title', 'parent_id')
      ->addUniqueIndex('title')
      ->addFulltextIndex('title', 'text');
    $exp = 'CREATE TABLE `pages` ('."\n"
      . '  `id` INT UNSIGNED AUTO_INCREMENT,'."\n"
      . '  `title` VARCHAR(250) DEFAULT NULL,'."\n"
      . '  `parent_id` INT UNSIGNED DEFAULT NULL,'."\n"
      . '  `type` ENUM ("abc", "cde") NOT NULL,'."\n"
      . '  `text` TEXT,'."\n"
      . '  INDEX `i_title_parent_id` (`title`, `parent_id`),'."\n"
      . '  UNIQUE INDEX `u_title` (`title`),'."\n"
      . '  FULLTEXT `f_title_text` (`title`, `text`),'."\n"
      . '  PRIMARY KEY (`id`)'."\n"
      . ') TYPE=MyISAM';
    $this->assertEquals($exp, $sql->make(), 'Создание таблицы с индексами');
  }

  function testAlter()
  {
    $exp = 'ALTER TABLE `pages` RENAME TO `not_pages`';
    $sql = DB::Alter('pages')->rename('not_pages');
    $this->assertEquals($exp, $sql->make(), 'Переименование таблицы');

    $exp0 = 'ALTER TABLE `pages` ADD COLUMN `name` VARCHAR(20)';
    $exp1 = 'ALTER TABLE `pages` ADD COLUMN `name` VARCHAR(20) FIRST';
    $exp2 = 'ALTER TABLE `pages` ADD COLUMN `name` VARCHAR(20) AFTER `id`';
    $sql0 = DB::Alter('pages')->addColumn('name', 'VARCHAR(20)');
    $sql1 = DB::Alter('pages')->addColumn('name', 'VARCHAR(20)', true);
    $sql2 = DB::Alter('pages')->addColumn('name', 'VARCHAR(20)', 'id');
    $this->assertEquals($exp0, $sql0->make(), 'Создание столбца name');
    $this->assertEquals($exp1, $sql1->make(), 'Создание столбца name первым');
    $this->assertEquals($exp2, $sql2->make(), 'Создание столбца name после id');

    $exp = 'ALTER TABLE `pages` ADD INDEX `i_id_title` (`id`, `title`)';
    $sql = DB::Alter('pages')->addIndex('id', 'title');
    $this->assertEquals($exp, $sql->make(), 'Добавление индекса');

    $exp = 'ALTER TABLE `pages` ADD PRIMARY KEY (`id`, `title`)';
    $sql = DB::Alter('pages')->addPrimaryKey('id', 'title');
    $this->assertEquals($exp, $sql->make(), 'Создание первичного ключа');

    $exp = 'ALTER TABLE `pages` ADD FULLTEXT `f_id_title` (`id`, `title`)';
    $sql = DB::Alter('pages')->addFulltextIndex('id', 'title');
    $this->assertEquals($exp, $sql->make(), 'Создание полнотекстового индекса');

    $exp = 'ALTER TABLE `pages` ADD UNIQUE `u_id_title` (`id`, `title`)';
    $sql = DB::Alter('pages')->addUniqueIndex('id', 'title');
    $this->assertEquals($exp, $sql->make(), 'Создание уникального индекса');

    $exp0 = 'ALTER TABLE `pages` MODIFY COLUMN `id` INT UNSIGNED';
    $exp1 = 'ALTER TABLE `pages` MODIFY COLUMN `id` INT UNSIGNED FIRST';
    $exp2 = 'ALTER TABLE `pages` MODIFY COLUMN `id` INT UNSIGNED AFTER `title`';
    $sql0 = DB::Alter('pages')->modifyColumn('id', 'INT UNSIGNED');
    $sql1 = DB::Alter('pages')->modifyColumn('id', 'INT UNSIGNED', true);
    $sql2 = DB::Alter('pages')->modifyColumn('id', 'INT UNSIGNED', 'title');
    $this->assertEquals($exp0, $sql0->make(), 'Изменение столбца id');
    $this->assertEquals($exp1, $sql1->make(), 'Изменение столбца id, перемещение в начало');
    $this->assertEquals($exp2, $sql2->make(), 'Изменение столбца id, перемещение после title');

    $exp = 'ALTER TABLE `pages` DROP COLUMN `title`';
    $sql = DB::Alter('pages')->dropColumn('title');
    $this->assertEquals($exp, $sql->make(), 'Сброс столбца title');

    $exp = 'ALTER TABLE `pages` DROP INDEX `i_title`';
    $sql = DB::Alter('pages')->dropIndex('i_title');
    $this->assertEquals($exp, $sql->make(), 'Сброс индекса i_title');

    $exp = 'ALTER TABLE `pages` DROP PRIMARY KEY';
    $sql = DB::Alter('pages')->dropPrimaryKey();
    $this->assertEquals($exp, $sql->make(), 'Сброс первичного ключа');

    $exp = 'ALTER TABLE `pages` ORDER BY title DESC';
    $sql = DB::Alter('pages')->setOrderBy('title DESC');
    $this->assertEquals($exp, $sql->make(), 'Установка порядка сортировки по умолчанию');
  }
}