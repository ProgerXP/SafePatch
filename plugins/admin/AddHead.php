<?php
/*
  HTML header/footer plugin for SafePatch control panel
  by Proger_XP              http://i-forge.net/me

  This plugin will put given HTML code after <head>, before </head>, after <body>
  and before </body> - see 4 string assignments at the end of this file.
*/

SpNexus::Hook('output', array('AddHead', 'OnOutput'));

class AddHead {
  static $headHeader, $headFooter, $bodyHeader, $bodyFooter;
  protected static $addedTo;

  static function OnOutput(&$html) {
    self::$addedTo = array();

    $regexp = '~<(/?)(head|body)([^>]*)>~u';
    $html = preg_replace_callback($regexp, array(__CLASS__, 'Add'), $html);
  }

    static function Add($match) {
      list($full, $isEndTag, $tag, $attrs) = $match;

      if ($isEndTag and ltrim($attrs) !== '') {  // </head attrs...> - wrong form.
        return $full;
      } else {
        $prop = $tag.($isEndTag ? 'Footer' : 'Header');
        if (empty(self::$addedTo[$prop])) {
          self::$addedTo[$prop] = true;
          return $isEndTag ? self::$$prop.$full : $full.self::$$prop;
        }
      }
    }
}

AddHead::$headHeader = <<<headHeader
This will be put after <head>; similar with others.
headHeader;

AddHead::$headFooter = <<<headFooter
headFooter;

AddHead::$bodyHeader = <<<bodyHeader
bodyHeader;

AddHead::$bodyFooter = <<<bodyFooter
bodyFooter;
