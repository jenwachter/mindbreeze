<?php

namespace Mindbreeze\Constraints;

class TermConstraint extends Constraint
{
  public function create($value = [])
  {
    $this->filter['quoted_term'] = $value;
    return $this;
  }
}
