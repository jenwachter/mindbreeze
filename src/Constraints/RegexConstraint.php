<?php

namespace Mindbreeze\Constraints;

class RegexConstraint extends Constraint
{
  public function create($values = [])
  {
    if (!is_array($values)) {
      $values = (array) $values;
    }

    foreach ($values as $value) {
      $this->filters[] = [
        'label' => $this->label,
        'regex' => '^\Q' . $value . '\E$',
        'value' => ['str' => $value]
      ];
    }

    return $this;
  }
}
