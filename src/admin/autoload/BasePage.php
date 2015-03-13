<?php

abstract class BasePage {
  static function InvalidReqParams() {
    global $T, $page;
    return HtmlError($T['invalidReqParams'], QuoteStrong($page));
  }

  static function CannotLock() {
    global $T;
    return '<p class="error">'.sprintf($T['cannotLock']).'</p>';
  }

  static function NoPatch($group, $patch = null) {
    global $T;

    if ($patch and $msg = &$T['nonExistingPatch'.$group]) {
      return HtmlError($msg, QuoteKbd($patch));
    } else {
      return HtmlError($T['nonExistingPatch'], QuoteKbd($patch ? $patch : $group));
    }
  }

  static function GetPatch($group, $patch) {
    global $sp;

    if ($group === 'applied') {
      $patches = $sp->AppliedPatches(true);

      if (!isset($patches[$patch])) {
        try {
          $patch = $sp->state->PatchedFileOf( SafePatch::RelFrom($patch, $sp->PatchPath()) );
        } catch (Exception $e) {
          // ignore.
        }
      }
    } else {
      $patches = $sp->GetPatches(true);
    }

    return @$patches[$patch];
  }

  static function HtmlDiff(array $newData, $headerTag = 'h3') {
    global $sp, $nexus;

    $res = '';

      foreach ($newData as $file => &$data) {
        $error = null;

          if (!is_array($data)) {
            $data = array('new' => $data, 'orig' => file_get_contents($file));
            if (!is_string($data['orig'])) {
              $error = "Cannot read [$file].";
            }
          }

        try {
          $relFile = SafePatch::RelFrom($file, $sp->Option('basePath'));
        } catch (Exception $e) {
          $relFile = $file;
        }

        $id = ' id="'.QuoteHTML($relFile).'"';
        $res .= "<$headerTag$id>".QuoteHTML($relFile)."</$headerTag>";

        if ($error) {
          $res .= HtmlError($error);
        } else {
          $res .= $nexus->Fire('diff: html', array(&$data['orig'], &$data['new']));
        }
      }

    return $res;
  }
}
