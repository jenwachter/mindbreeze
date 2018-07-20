<?php

namespace Mindbreeze\Constraints;

abstract class Constraint
{
  protected $constraint = [];
  protected $filters = [];

  public function __construct($label)
  {
    $this->label = $label;
    $this->constraint = ['label' => $label];
  }

  public function compile()
  {
    $this->constraint['filter_base'] = $this->filters;
    return $this->constraint;
  }
}
