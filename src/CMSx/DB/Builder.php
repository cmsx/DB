<?php

namespace CMSx\DB;

abstract class Builder
{
  public static function BuildJoin($join)
  {
    if (is_array($join)) {
      $out = '';
      foreach ($join as $arr) {
        $out .= ' ' . (!empty($arr['type']) ? strtoupper($arr['type']) . ' ' : '')
          . 'JOIN ' . self::QuoteTable($arr['table']) . ' ON ' . $arr['on'];
      }
      return $out;
    } else {
      return null;
    }
  }

  public static function BuildOrderBy($orderby)
  {
    if (empty($orderby)) {
      return;
    }
    if (is_array($orderby)) {
      $orderby = self::BuildNames($orderby);
    }
    return ' ORDER BY ' . $orderby;
  }

  public static function BuildGroupBy($groupby)
  {
    if (empty($groupby)) {
      return;
    }
    if (is_array($groupby)) {
      $groupby = self::BuildNames($groupby);
    }
    return ' GROUP BY ' . $groupby;
  }

  public static function BuildLimit($limit, $offset = null)
  {
    return !empty ($limit)
      ? ' LIMIT ' . ($offset ? $offset . ', ' : '') . $limit
      : '';
  }

  public static function BuildWhere($where, $bind = true, $and = true)
  {
    if (empty($where)) {
      return null;
    }
    return ' WHERE ' . self::BuildKeyValue($where, $bind, 'where', $and);
  }

  public static function BuildHaving($having, $bind = true, $and = true)
  {
    if (empty($having)) {
      return null;
    }
    return ' HAVING ' . self::BuildKeyValue($having, $bind, 'having', $and);
  }

  public static function BuildSet($values, $bind = true)
  {
    if (empty($values)) {
      return null;
    }
    return ' SET ' . self::BuildKeyValue($values, $bind, 'set', ', ');
  }

  public static function ReplaceBindedValues($sql, $binded_values)
  {
    if ($binded_values) {
      foreach ($binded_values as $key=> $val) {
        $sql = str_replace($key, self::QuoteValue($val), $sql);
      }
    }
    return $sql;
  }

  /**
   * Построение групп ключ-значение `key`="value", `key2`=rand() OR `key3` = :where_key3
   *
   * @static
   *
   * @param             $condition массив с условиями
   * @param             $bind      true если нужно ли подставлять значения или плейсхолдеры
   * @param             $part      префикс для плейсхолдера
   * @param bool|string $glue      Строка для объединения. Сокращения: true => AND, false => OR
   */
  public static function BuildKeyValue($condition, $bind = true, $part = null, $glue = true)
  {
    if (is_array($condition)) {
      $c = array();
      if ($glue === true) {
        $glue = ' AND ';
      } elseif ($glue === false) {
        $glue = ' OR ';
      }
      foreach ($condition as $key => $val) {
        if (is_numeric($key)) {
          if (self::HasSpecChar($val)) {
            $c[] = $val;
          }
        } else {
          if ($bind) {
            $c[] = self::QuoteKeyValue($key, $val);
          } else {
            $c[] = self::QuoteKey($key) . '=:' . $part . '_' . $key;
          }
        }
      }
      $condition = join($glue, $c);
    }
    return $condition;
  }

  /** Построение списков вида `col1`, `table2` */
  public static function BuildNames($names, $prefix = null)
  {
    if (empty($names)) {
      return null;
    }
    if (is_array($names)) {
      $out = array();
      foreach ($names as $name) {
        $out[] = self::QuoteKey($name, $prefix);
      }
      $names = join(', ', $out);
    }
    return $names;
  }

  /** Построение списков вида "value", 12, NULL */
  public static function BuildValues($values, $bind = true, $part = null)
  {
    $out = array();
    foreach ($values as $key=> $val) {
      if ($bind) {
        $out[] = self::QuoteValue($val);
      } else {
        $out[] = ':' . $part . '_' . $key;
      }
    }
    return join(', ', $out);
  }

  /** Построение условий действия для внешних ключей: ON DELETE [CASCADE|RESTRICT|...] */
  public static function BuildReferenceAction($action)
  {
    switch ($action) {
      case SQL::FOREIGN_CASCADE:
        return 'CASCADE';
        break;
      case SQL::FOREIGN_SET_NULL:
        return 'SET NULL';
        break;
      default:
        return 'RESTRICT';
    }
  }

  public static function QuoteTable($table, $prefix = null)
  {
    if (strpos($table, '`') === false && strpos($table, ',') === false) {
      $table = $prefix . $table;
      if (strpos($table, ' ')) {
        $table = trim($table);
        $parts = explode(' ', $table, 2);
        return join(' ', array_map('self::QuoteKey', $parts));
      }
    }
    return self::QuoteKey($table);
  }

  public static function QuoteKey($key)
  {
    return self::HasSpecChar($key) ? $key : '`' . $key . '`';
  }

  public static function QuoteValue($value)
  {
    if (is_null($value)) {
      return 'NULL';
    }
    if (!ini_get('magic_quotes_gpc')) {
      $value = addslashes($value);
    }
    return is_numeric($value) ? $value : '"' . $value . '"';
  }

  public static function QuoteKeyValue($key, $value)
  {
    return '`' . $key . '`=' . self::QuoteValue($value);
  }

  protected static function HasSpecChar($str)
  {
    return preg_match('/[><,\.()`\s\*=:]+/is', $str);
  }
}