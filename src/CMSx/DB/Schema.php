<?php

namespace CMSx\DB;

use CMSx\DB;
use CMSx\DB\Query\Create;
use CMSx\DB\Exception;

/** При наследовании в init() должны быть заданы имя таблицы и запрос для её создания через DB\Query\Create */
abstract class Schema
{
  /** @var Create */
  protected $query;
  /** Название таблицы */
  protected $name;

  /** @var DB */
  protected $manager;

  abstract public function getTable();
  
  function __construct(DB $manager)
  {
    $this->setManager($manager);
    $this->query = $this->getManager()->create($this->getTable());

    $this->init();

    if (empty($this->name)) {
      $a   = explode('_', $this->getTable());
      $res = array();
      foreach ($a as $str) {
        $res[] = ucfirst($str);
      }
      $this->name = join(' ', $res);
    }
  }

  /**
   * @param \CMSx\DB $manager
   */
  public function setManager($manager)
  {
    $this->manager = $manager;

    return $this;
  }

  /**
   * @return \CMSx\DB
   */
  public function getManager()
  {
    return $this->manager;
  }

  /** Формирует код класс модели, возвращает в виде string */
  public function buildModel($name, $namespace = null)
  {
    $def = $this->getDefinition('columns');

    $out  = "<?php\n\n";
    $func = array();

    if ($namespace) {
      $out .= "namespace {$namespace};\n\n";
    }

    $out .= "use CMSx\\DB\\Item;\n\n"
      . "/** Этот класс был создан автоматически " . date('d.m.Y H:i') . " по схеме " . get_called_class() . " */\n"
      . "class {$name} extends Item\n{\n  public function getTable() {\n    return '{$this->getTable()}';\n  }\n\n"
      . "  /** @return \\CMSx\\DB */\n  public function getManager() {\n    //TODO: Указать менеджер БД\n  }\n\n";

    foreach ($def as $col => $def) {
      $a = explode('_', $col);
      array_walk(
        $a, function (&$part) {
          $part = ucfirst($part);
        }
      );
      $col_name = join('', $a);
      if (false !== mb_stripos($def, 'FLOAT', null, 'utf8')) {
        $get = "  public function get{$col_name}(\$decimals = null, \$point = null, \$thousands = null)\n  {\n    "
          . "return \$this->getAsFloat('{$col}', \$decimals, \$point, \$thousands);\n  }";
        $set = "  public function set{$col_name}(\${$col})\n  {\n    return \$this->set('{$col}', \${$col});\n  }";
      } elseif (false !== mb_stripos($def, 'TIMESTAMP', null, 'utf8')) {
        $get = "  public function get{$col_name}(\$format = null)\n  {\n    "
          . "return \$this->getAsDate('{$col}', \$format);\n  }";
        $set = "  public function set{$col_name}(\${$col})\n  {\n    "
          . "return \$this->setAsDate('{$col}', \${$col});\n  }";
      } else {
        $get = "  public function get{$col_name}()\n  {\n    return \$this->get('{$col}');\n  }";
        $set = "  public function set{$col_name}(\${$col})\n  {\n    return \$this->set('{$col}', \${$col});\n  }";
      }
      $func[] = $get . "\n\n" . $set . "\n";
    }

    return $out . join("\n", $func) . "}\n";
  }

  /** Создание таблицы */
  public function createTable($drop = false)
  {
    if (is_null($this->getTable())) {
      throw new Exception(get_called_class() . ': Имя таблицы не определено');
    }
    if (is_null($this->query)) {
      throw new Exception(get_called_class() . ': SQL для создания таблицы не определен');
    }

    if ($drop) {
      $this->getManager()->drop($this->getTable())->execute();
    }

    return $this->query->execute();
  }

  /** Обновление структуры таблицы */
  public function updateTable()
  {
    $tbl = $this->getManager()->getPrefix() . $this->getTable();

    $cols     = $this->query->getDefinition('columns');
    $tbl_info = $this->getManager()->query("DESCRIBE $tbl")->fetchAll(\PDO::FETCH_ASSOC);
    $tbl_arr  = array();
    foreach ($tbl_info as $r) {
      $tbl_arr[$r['Field']] = null;
    }

    $drop   = array_diff_key($tbl_arr, $cols);
    $create = array_diff_key($cols, $tbl_arr);

    foreach ($drop as $col => $na) {
      $this->getManager()->alter($this->getTable())
        ->dropColumn($col)
        ->execute();
    }

    foreach ($create as $col => $def) {
      $this->getManager()->alter($this->getTable())
        ->addColumn($col, $def)
        ->execute();
    }

    $prev = null;
    foreach ($cols as $col => $def) {
      $this->getManager()->alter($this->getTable())
        ->modifyColumn($col, $def, $prev)
        ->execute();
      $prev = $col;
    }
  }

  /** Забивание таблицы стартовым контентом */
  public function fillTable()
  {
    return true;
  }

  /** Название таблицы */
  public function getName()
  {
    return $this->name;
  }

  /**
   * Запрос для создания таблицы
   *
   * @return Create
   */
  public function getQuery()
  {
    return $this->query;
  }

  /**
   * Получение компонентов запроса
   *
   * * type - тип таблицы DB::TYPE_*
   *
   * * columns - столбцы для создания
   *
   * * index - массив имя индекса => набор столбцов
   *
   * * unique - массив имя уникального индекса => набор столбцов
   *
   * * fulltext - полнотекстовый индекс (только для MyISAM)
   *
   * * primary_key - столбцы для первичного ключа
   */
  public function getDefinition($component = null)
  {
    return $this->query->getDefinition($component);
  }

  /** Настройка схемы */
  protected function init()
  {
  }
}