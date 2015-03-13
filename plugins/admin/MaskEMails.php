<?php
/*
  E-mail masker plugin for SafePatch control panel
  by Proger_XP              http://i-forge.net/me

  This plugin will replace all e-mails of form "X@Y.Z[.Z...]" appearing in
  the control panel's output with their "X_at_Y_dot_Z.Z..." versions.
  This is useful if your control panel is publicly available.
*/

SpNexus::Hook('output', array('MaskEMails', 'OnOutput'));

MergeLang(array('en' => array('emailMask' => '\1_at_\2_dot_\3'),
                'ru' => array('emailMask' => '\1_собака_\2_точка_\3')));

class MaskEMails {
  // listed pages will have verbatim e-mails; e.g. array('patches', 'logs').
  static $skipPages = array();

  static function OnOutput(&$html) {
    global $reqPage, $T;

    if (!in_array($reqPage, self::$skipPages)) {
      $regexp = '/\b([a-z0-9\-_\.]+)@([a-z0-9\-\.]+)\.([a-z]{2,5})\b/iu';
      $html = preg_replace($regexp, $T['emailMask'], $html);
    }
  }
}