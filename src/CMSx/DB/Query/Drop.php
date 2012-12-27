<?php

namespace CMSx\DB\Query;

use CMSx\DB\Query;
use CMSx\DB\Builder;

class Drop extends Query
{
  protected $if_exists = true;

  public function make($bind_values = false)
  {
    $this->sql = 'DROP TABLE '
      . ($this->if_exists ? 'IF EXISTS ' : '')
      . Builder::QuoteTable($this->table, $this->prefix);

    return $this->sql;
  }

  /** Нужно ли добавлять IF EXISTS */
  public function setIfExists($on)
  {
    $this->if_exists = $on;

    return $this;
  }
}