<?php

namespace CMSx\DB\Query;

use CMSx\DB\Query;
use CMSx\DB\Builder;

class Truncate extends Query
{
  public function make($bind_values = false)
  {
    $this->sql = 'TRUNCATE TABLE ' . Builder::QuoteTable($this->table, $this->getPrefix());

    return $this->sql;
  }
}