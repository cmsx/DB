<?php

namespace CMSx\DB;

use CMSx\DB;
use CMSx\DB\Query\Create;
use CMSx\DB\Exception;

/** При наследовании в init() должны быть заданы имя таблицы и запрос для её создания через DB::Create()... */
abstract class Schema
{
  /** Имя создаваемой таблицы без префикса */
  protected $table;
  /** @var Create */
  protected $query;
  /** Название таблицы */
  protected $name;

  function __construct()
  {
    $this->init();

    if (empty($this->name)) {
      $a   = explode('_', $this->table);
      $res = array();
      foreach ($a as $str) {
        $res[] = ucfirst($str);
      }
      $this->name = join(' ', $res);
    }
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
      . "class {$name} extends Item\n{\n  protected static \$default_table = '{$this->table}';\n\n";

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
    if (is_null($this->table)) {
      throw new Exception(get_called_class() . ': Имя таблицы не определено');
    }
    if (is_null($this->query)) {
      throw new Exception(get_called_class() . ': SQL для создания таблицы не определен');
    }

    if ($drop) {
      DB::Drop($this->table)->execute();
    }

    return $this->query->execute();
  }

  /** Обновление структуры таблицы */
  public function updateTable()
  {
    $tbl = DB::GetPrefix() . $this->table;

    $cols     = $this->query->getDefinition('columns');
    $tbl_info = DB::Execute("DESCRIBE $tbl")->fetchAll(\PDO::FETCH_ASSOC);
    $tbl_arr  = array();
    foreach ($tbl_info as $r) {
      $tbl_arr[$r['Field']] = null;
    }

    $drop   = array_diff_key($tbl_arr, $cols);
    $create = array_diff_key($cols, $tbl_arr);

    foreach ($drop as $col => $na) {
      DB::Alter($this->table)
        ->dropColumn($col)
        ->execute();
    }

    foreach ($create as $col => $def) {
      DB::Alter($this->table)
        ->addColumn($col, $def)
        ->execute();
    }

    $prev = null;
    foreach ($cols as $col => $def) {
      DB::Alter($this->table)
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

  /** Имя таблицы в БД */
  public function getTable()
  {
    return $this->table;
  }

  /** Название таблицы */
  public function getName()
  {
    return $this->name;
  }

  /**
   * Запрос для создания таблицы
   *
   * @return Query\Create
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