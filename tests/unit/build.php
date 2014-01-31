<?php

require_once __DIR__ . '/../init.php';

use CMSx\DB;

class BuildTest extends PHPUnit_Framework_TestCase
{
  function testSelectPrefix()
  {
    $sql = $this->select('pages')->setPrefix('yeah_')->make();
    $exp = 'SELECT * FROM `yeah_pages`';
    $this->assertEquals($exp, $sql, 'Префикс подставляется если имя таблицы простое');

    $sql = $this->select('pages p')->setPrefix('yeah_')->make();
    $exp = 'SELECT * FROM `yeah_pages` `p`';
    $this->assertEquals($exp, $sql, 'Префикс подставляется если имя таблицы простое и содержит альяс');

    $sql = $this->select('`pages`')->setPrefix('yeah_')->make();
    $exp = 'SELECT * FROM `pages`';
    $this->assertEquals($exp, $sql, 'Если имя таблицы содержит "`", префикс не подставляется');

    $sql = $this->select('pages p, users u')->setPrefix('yeah_')->make();
    $exp = 'SELECT * FROM pages p, users u';
    $this->assertEquals($exp, $sql, 'Если имя таблицы содержит ",", префикс не подставляется');
  }

  function testSelectByID()
  {
    $sql = $this->select('pages')->where(12)->make();
    $exp = 'SELECT * FROM `pages` WHERE `id`=:where_id';
    $this->assertEquals($exp, $sql, 'Выборка по ID');
  }

  function testSelectByWhereArray()
  {
    $sql  = $this->select('pages')->where(array('some' => 'thing'));
    $vals = $sql->getBindedValues();
    $exp  = 'SELECT * FROM `pages` WHERE `some`=:where_some';
    $this->assertEquals($exp, $sql->make(), 'Выборка по массиву условий');
    $this->assertEquals('thing', $vals[':where_some'], 'Успешно забиндилось');

    $sql->where(array('one' => 'two'));
    $exp  = 'SELECT * FROM `pages` WHERE `some`=:where_some AND `one`=:where_one';
    $this->assertEquals($exp, $sql->make(), 'Второй where добавился к 1му');

    $sql  = $this->select('pages')->whereEqual('id', 12)->whereEqual('name', 'Hello');
    $vals = $sql->getBindedValues();
    $exp  = 'SELECT * FROM `pages` WHERE `id`=:where_id AND `name`=:where_name';
    $this->assertEquals($exp, $sql->make(), 'Метод whereEqual');
    $this->assertEquals($vals[':where_id'], 12, 'IDшник забиндился');

    $sql  = $this->select('pages p')->whereIn('p.id', array(12, 13, 14));
    $vals = $sql->getBindedValues();
    $exp  = 'SELECT * FROM `pages` `p` WHERE p.id IN (:where_p_id_1,:where_p_id_2,:where_p_id_3)';
    $this->assertEquals($exp, $sql->make(), 'Метод whereIn');
    $this->assertEquals($vals[':where_p_id_1'], 12, 'ID #1 забиндился');
    $this->assertEquals($vals[':where_p_id_2'], 13, 'ID #2 забиндился');
    $this->assertEquals($vals[':where_p_id_3'], 14, 'ID #3 забиндился');

    $sql  = $this->select('pages p')->whereBetween('p.id', 12, 24);
    $vals = $sql->getBindedValues();
    $exp  = 'SELECT * FROM `pages` `p` WHERE p.id BETWEEN :where_p_id_from AND :where_p_id_to';
    $this->assertEquals($exp, $sql->make(), 'Метод whereBetween');
    $this->assertEquals($vals[':where_p_id_from'], 12, 'ID "от" забиндилось');
    $this->assertEquals($vals[':where_p_id_to'], 24, 'ID "до" забиндилось');

    $sql = $this->select('pages')->where('`some`="thing"', '`another`>1');
    $exp = 'SELECT * FROM `pages` WHERE `some`="thing" AND `another`>1';
    $this->assertEquals($exp, $sql->make(), 'Выборка по строковым условиям');
    $this->assertFalse($sql->getBindedValues(), 'Ничего не биндилось');

    $sql = $this->select('pages p')->where(array('p.id' => 12, 'is_active' => 1));
    $exp1 = 'SELECT * FROM `pages` `p` WHERE p.id=12 AND `is_active`=1';
    $exp2 = 'SELECT * FROM `pages` `p` WHERE p.id=:where_p_id AND `is_active`=:where_is_active';
    $this->assertEquals($exp2, $sql->make(), 'При биндинге ключи с точкой заменяются на подчеркивание');
    $this->assertEquals($exp1, $sql->make(true), 'Значения подставляются в запрос');
    $this->assertEquals($exp1, (string)$sql, 'Преобразование объекта в строку');

    $sql = $this->select('pages')->where(12, true);
    $exp = 'SELECT * FROM `pages` WHERE `id`=12 AND `is_active`=1';
    $this->assertEquals($exp, $sql->make(true), 'Значения подставляются в запрос');
  }

  function testSelectColumns()
  {
    $sql1 = $this->select('pages')->columns('id', '`title`', 'something');
    $sql2 = $this->select('pages')->columns(array('id', '`title`', 'something'));
    $exp  = 'SELECT `id`, `title`, `something` FROM `pages`';
    $this->assertEquals($exp, $sql1->make(), 'Столбцы перечислением');
    $this->assertEquals($exp, $sql2->make(), 'Столбцы в массиве');
  }

  function testSelectOrderBy()
  {
    $sql1 = $this->select('pages')->orderby('id', '`title`', 'something DESC');
    $sql2 = $this->select('pages')->orderby(array('id', '`title`', 'something DESC'));
    $exp  = 'SELECT * FROM `pages` ORDER BY `id`, `title`, something DESC';
    $this->assertEquals($exp, $sql1->make(), 'Сортировка перечислением');
    $this->assertEquals($exp, $sql2->make(), 'Сортировка в массиве');
  }

  function testSelectLimit()
  {
    $sql = $this->select('pages')->limit(5)->make();
    $exp = 'SELECT * FROM `pages` LIMIT 5';
    $this->assertEquals($exp, $sql, 'Выборка с ограничением 5 штук');

    $sql = $this->select('pages')->limit(5, 10)->make();
    $exp = 'SELECT * FROM `pages` LIMIT 10, 5';
    $this->assertEquals($exp, $sql, 'Выборка с ограничением 5 штук и отступом 10');

    $sql = $this->select('pages')->page(3, 5)->make();
    $exp = 'SELECT * FROM `pages` LIMIT 10, 5';
    $this->assertEquals($exp, $sql, 'Постраничная выборка 3-я страница, по 5 штук на странице');

    $sql = $this->select('pages')->page(null, null)->make();
    $exp = 'SELECT * FROM `pages`';
    $this->assertEquals($exp, $sql, 'Пустой вызов page()');

    $sql = $this->select('pages')->page(null, 20)->make();
    $exp = 'SELECT * FROM `pages` LIMIT 20';
    $this->assertEquals($exp, $sql, 'Вызов page() только с onpage');
  }

  function testSelectJoin()
  {
    $sql = $this->select('pages p')
      ->join('users u', 'u.id=p.user_id', 'left')
      ->join('non_users nu', 'nu.id=p.user_id', 'right')
      ->make();
    $exp = 'SELECT * FROM `pages` `p` LEFT JOIN `users` `u` ON u.id=p.user_id '
      . 'RIGHT JOIN `non_users` `nu` ON nu.id=p.user_id';
    $this->assertEquals($exp, $sql, 'Выборка по трем таблицам');
  }

  function testSelectBind()
  {
    $sql = $this->select('pages')
      ->where('id > :min', 'status = :status')
      ->bind('min', 12)
      ->bind(':status', 'new')
      ->setWhereJoinByAnd(false);
    $exp = 'SELECT * FROM `pages` WHERE id > :min OR status = :status';
    $this->assertEquals($exp, $sql->make(), 'Запрос с подстановкой');

    $exp = array(':min' => 12, ':status' => 'new');
    $this->assertEquals($exp, $sql->getBindedValues(), 'Пробиндилось корректно');

    $exp = 'SELECT * FROM `pages` WHERE id > 12 OR status = "new"';
    $this->assertEquals($exp, $sql->make(true), 'Забинденые параметры подставились в запрос');
  }

  function testSelectHaving()
  {
    $sql = $this->select('pages')
      ->columns('id', 'SUM(price) as `total`')
      ->groupby('parent_id')
      ->having('`total` > 0')
      ->make();
    $exp = 'SELECT `id`, SUM(price) as `total` FROM `pages` GROUP BY `parent_id` HAVING `total` > 0';
    $this->assertEquals($exp, $sql, 'Запрос с условием Having');

    $sql  = $this->select('pages')
      ->columns('id', 'SUM(price) as `total`')
      ->groupby('parent_id')
      ->having(array('total' => 0));
    $exp1 = 'SELECT `id`, SUM(price) as `total` FROM `pages` GROUP BY `parent_id` HAVING `total`=:having_total';
    $exp2 = 'SELECT `id`, SUM(price) as `total` FROM `pages` GROUP BY `parent_id` HAVING `total`=0';
    $this->assertEquals($exp1, $sql->make(), 'Запрос с условием Having');
    $this->assertEquals($exp2, $sql->make(true), 'Запрос с условием Having с подставленными значениями');
  }

  function testSelectAll()
  {
    $sql = $this->select('pages')
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
    $sql = $this->update('pages')
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
    $sql  = $this->delete('pages')
      ->where(12, '`created_at` > now()')
      ->limit(3);
    $exp1 = 'DELETE FROM `pages` WHERE `id`=:where_id AND `created_at` > now() LIMIT 3';
    $exp2 = 'DELETE FROM `pages` WHERE `id`=12 OR `created_at` > now() LIMIT 3';
    $this->assertEquals($exp1, $sql->make(), 'Delete с плейсхолдерами');
    $sql->setWhereJoinByAnd(false);
    $this->assertEquals($exp2, $sql->make(true), 'Delete с подставленными значениями');
  }

  function testDrop()
  {
    $sql = $this->drop('pages');
    $this->assertEquals('DROP TABLE IF EXISTS `pages`', $sql->make(), 'Drop if exists таблицы');

    $sql->setIfExists(false);
    $this->assertEquals('DROP TABLE `pages`', $sql->make(), 'Drop таблицы');
  }

  function testTruncate()
  {
    $sql = $this->truncate('pages');
    $this->assertEquals('TRUNCATE TABLE `pages`', $sql->make(), 'Truncate таблицы');
  }

  function testInsert()
  {
    $exp1    = 'INSERT INTO `pages` `p` (p.countme, `foo`, `another`) '
      . 'VALUES (:insert_p_countme, :insert_foo, :insert_another)';
    $exp2    = 'INSERT INTO `pages` `p` (p.countme, `foo`, `another`) VALUES (12, "bar", NULL)';
    $exp_arr = array(
      ':insert_p_countme' => 12,
      ':insert_foo'     => 'bar',
      ':insert_another' => null
    );

    $sql = $this->insert('pages p')
      ->setArray(
      array(
        'p.countme' => 12,
        'foo'     => 'bar',
        'another' => null
      )
    );
    $this->assertEquals($exp1, $sql->make(), 'Insert с плейсхолдерами №1');
    $this->assertEquals($exp2, $sql->make(true), 'Insert с подставленными значениями №1');
    $this->assertEquals($exp_arr, $sql->getBindedValues(), 'Значения пробиндились корректно №1');

    $sql = $this->insert('pages p')
      ->set('p.countme', 12)
      ->set('foo', 'bar')
      ->set('another', null);
    $this->assertEquals($exp1, $sql->make(), 'Insert с плейсхолдерами №2');
    $this->assertEquals($exp2, $sql->make(true), 'Insert с подставленными значениями №2');
    $this->assertEquals($exp_arr, $sql->getBindedValues(), 'Значения пробиндились корректно №2');
  }

  function testCreate()
  {
    $sql = $this->create('pages')
      ->addId()
      ->addChar('title')
      ->addForeignId()
      ->addForeignKey('parent_id', 'pages', 'id', DB::FOREIGN_CASCADE)
      ->addEnum('type', array('abc', 'cde'))
      ->addPrice()
      ->addText()
      ->addIndex('title', 'parent_id')
      ->addUniqueIndex('title');
    $exp = 'CREATE TABLE `pages` (' . "\n"
      . '  `id` INT UNSIGNED AUTO_INCREMENT,' . "\n"
      . '  `title` VARCHAR(250) DEFAULT NULL,' . "\n"
      . '  `parent_id` INT UNSIGNED DEFAULT NULL,' . "\n"
      . '  `type` ENUM ("abc", "cde") NOT NULL,' . "\n"
      . '  `price` FLOAT(10,2) UNSIGNED,' . "\n"
      . '  `text` TEXT,' . "\n"
      . '  INDEX `i_title_parent_id` (`title`, `parent_id`),' . "\n"
      . '  UNIQUE INDEX `u_title` (`title`),' . "\n"
      . '  PRIMARY KEY (`id`),' . "\n"
      . '  FOREIGN KEY `fk_parent_id` (`parent_id`) REFERENCES `pages`(`id`) ON DELETE CASCADE ON UPDATE CASCADE' . "\n"
      . ') ENGINE=InnoDB';
    $this->assertEquals($exp, $sql->make(), 'Создание таблицы с индексами');
  }

  function testCreateFulltext()
  {
    $sql = $this->create('pages')
      ->addId()
      ->addText()
      ->addFulltextIndex('title', 'text');

    $exp = 'CREATE TABLE `pages` (' . "\n"
      . '  `id` INT UNSIGNED AUTO_INCREMENT,' . "\n"
      . '  `text` TEXT,' . "\n"
      . '  FULLTEXT `f_title_text` (`title`, `text`),' . "\n"
      . '  PRIMARY KEY (`id`)' . "\n"
      . ') ENGINE=MyISAM';
    $this->assertEquals($exp, $sql->make(), 'Создание полнотекстового индекса');

    try {
      $sql->setType(DB::TYPE_InnoDB);
      $this->fail('Полнотекстовый поиск доступен только для MyISAM');
    } catch (\CMSx\DB\Exception $e) {
      $this->assertEquals(DB::ERROR_FULLTEXT_ONLY_MYISAM, $e->getCode(), 'Код ошибки соответствует');
    }
  }

  function testAlter()
  {
    $exp = 'ALTER TABLE `pages` RENAME TO `not_pages`';
    $sql = $this->alter('pages')->rename('not_pages');
    $this->assertEquals($exp, $sql->make(), 'Переименование таблицы');

    $exp0 = 'ALTER TABLE `pages` ADD COLUMN `name` VARCHAR(20)';
    $exp1 = 'ALTER TABLE `pages` ADD COLUMN `name` VARCHAR(20) FIRST';
    $exp2 = 'ALTER TABLE `pages` ADD COLUMN `name` VARCHAR(20) AFTER `id`';
    $sql0 = $this->alter('pages')->addColumn('name', 'VARCHAR(20)');
    $sql1 = $this->alter('pages')->addColumn('name', 'VARCHAR(20)', true);
    $sql2 = $this->alter('pages')->addColumn('name', 'VARCHAR(20)', 'id');
    $this->assertEquals($exp0, $sql0->make(), 'Создание столбца name');
    $this->assertEquals($exp1, $sql1->make(), 'Создание столбца name первым');
    $this->assertEquals($exp2, $sql2->make(), 'Создание столбца name после id');

    $exp = 'ALTER TABLE `pages` ADD INDEX `i_id_title` (`id`, `title`)';
    $sql = $this->alter('pages')->addIndex('id', 'title');
    $this->assertEquals($exp, $sql->make(), 'Добавление индекса');

    $exp = 'ALTER TABLE `pages` ADD PRIMARY KEY (`id`, `title`)';
    $sql = $this->alter('pages')->addPrimaryKey('id', 'title');
    $this->assertEquals($exp, $sql->make(), 'Создание первичного ключа');

    $exp = 'ALTER TABLE `pages` ADD FULLTEXT `f_id_title` (`id`, `title`)';
    $sql = $this->alter('pages')->addFulltextIndex('id', 'title');
    $this->assertEquals($exp, $sql->make(), 'Создание полнотекстового индекса');

    $exp = 'ALTER TABLE `pages` ADD UNIQUE `u_id_title` (`id`, `title`)';
    $sql = $this->alter('pages')->addUniqueIndex('id', 'title');
    $this->assertEquals($exp, $sql->make(), 'Создание уникального индекса');

    $exp0 = 'ALTER TABLE `pages` MODIFY COLUMN `id` INT UNSIGNED';
    $exp1 = 'ALTER TABLE `pages` MODIFY COLUMN `id` INT UNSIGNED FIRST';
    $exp2 = 'ALTER TABLE `pages` MODIFY COLUMN `id` INT UNSIGNED AFTER `title`';
    $sql0 = $this->alter('pages')->modifyColumn('id', 'INT UNSIGNED');
    $sql1 = $this->alter('pages')->modifyColumn('id', 'INT UNSIGNED', true);
    $sql2 = $this->alter('pages')->modifyColumn('id', 'INT UNSIGNED', 'title');
    $this->assertEquals($exp0, $sql0->make(), 'Изменение столбца id');
    $this->assertEquals($exp1, $sql1->make(), 'Изменение столбца id, перемещение в начало');
    $this->assertEquals($exp2, $sql2->make(), 'Изменение столбца id, перемещение после title');

    $exp = 'ALTER TABLE `pages` DROP COLUMN `title`';
    $sql = $this->alter('pages')->dropColumn('title');
    $this->assertEquals($exp, $sql->make(), 'Сброс столбца title');

    $exp = 'ALTER TABLE `pages` DROP INDEX `i_title`';
    $sql = $this->alter('pages')->dropIndex('i_title');
    $this->assertEquals($exp, $sql->make(), 'Сброс индекса i_title');

    $exp = 'ALTER TABLE `pages` DROP PRIMARY KEY';
    $sql = $this->alter('pages')->dropPrimaryKey();
    $this->assertEquals($exp, $sql->make(), 'Сброс первичного ключа');

    $exp = 'ALTER TABLE `pages` ORDER BY title DESC';
    $sql = $this->alter('pages')->setOrderBy('title DESC');
    $this->assertEquals($exp, $sql->make(), 'Установка порядка сортировки по умолчанию');
  }

  protected function select($table)
  {
    return new DB\Query\Select($table);
  }

  protected function update($table)
  {
    return new DB\Query\Update($table);
  }

  protected function delete($table)
  {
    return new DB\Query\Delete($table);
  }

  protected function drop($table)
  {
    return new DB\Query\Drop($table);
  }

  protected function truncate($table)
  {
    return new DB\Query\Truncate($table);
  }

  protected function insert($table)
  {
    return new DB\Query\Insert($table);
  }

  protected function create($table)
  {
    return new DB\Query\Create($table);
  }

  protected function alter($table)
  {
    return new DB\Query\Alter($table);
  }
}