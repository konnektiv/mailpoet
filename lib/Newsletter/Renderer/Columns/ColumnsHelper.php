<?php
namespace MailPoet\Newsletter\Renderer\Columns;

class ColumnsHelper {
  static $columns_width = array(
    1 => 660,
    2 => 330,
    3 => 220
  );

  static $columns_class = array(
    1 => 'cols-one',
    2 => 'cols-two',
    3 => 'cols-three'
  );

  static $columns_alignment = array(
    1 => null,
    2 => 'left',
    3 => 'right'
  );
}