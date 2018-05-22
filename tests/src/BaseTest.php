<?php

namespace Mindbreeze;

abstract class BaseTest extends \PHPUnit_Framework_TestCase
{
  public function arrayToObject($array)
  {
    return json_decode(json_encode($array, false));
  }
}
