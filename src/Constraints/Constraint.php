<?php

namespace Mindbreeze\Constraints;

abstract class Constraint
{
  protected $filter = [];

  public function __construct($label)
  {
    $this->filter['label'] = $label;
  }

  public function compile()
  {
    return ['filter_base' => $this->filter];
  }
}
