<?php

class PagePatch extends BasePage {
  static function Build() {
    global $page, $curMenuPage, $P;
    $curMenuPage = 'patches';

    $func = 'Build'.ucfirst($page);
    if (method_exists(__CLASS__, $func)) {
      $header = isset($P['pageTitle']) ? '<h1>'.$P['pageTitle'].'</h1>' : '';
      return $header . self::$func(@$_REQUEST['group'], @$_REQUEST['patch']);
    } else {
      return self::InvalidReqParams();
    }
  }

  static function BuildFreshen($group, $patch) {
    global $P, $sp;

    $total = count($sp->GetPatches());

    if ($res = RequestChange('freshen')) {
      // return.
    } elseif ($total == 0) {
      $path = QuoteKbd($sp->PatchPath());
      $res = '<div class="none">'.sprintf($P['noPatches'], $path).'</div>';
    } else {
      $freshen = $sp->Freshen();

      if ($freshen === true or (is_array($freshen) and !$freshen)) {
        $res = '<p class="info">'.sprintf($P['up-to-date'], $total).'</p>';
      } elseif (is_array($freshen)) {
        $res = '<p>'.FmtNum('freshenedPatches', count($freshen)).'</p>'.
               '<ol class="entries report">';

          foreach ($freshen as $file => $error) {
            $class = $error ? 'error' : 'ok';
            $error and $error = ' &ndash; '.$error;
            $res .= '<li class="'.$class.'">'.QuoteKbd(basename($file)).$error.'</li>';
          }

        $res .= '</ol>';
      } else {
        $res = self::CannotLock();
      }
    }

    return $res;
  }

  static function BuildApply($group, $patch) {
    global $P;

    try {
      return self::PerformLockedOn($group, $patch, 'ApplyLogging');
    } catch (ESpDoublePatching $e) {
      $title = sprintf($P['doublePatching'], QuoteKbd($patch));

      $msg = QuoteHTML($e->getMessage());
      $msg = strtr($msg, array('[' => '<kbd>', ']' => '</kbd>'));

      return HtmlError("<p class=\"em\">$title</p><p>$msg</p>");
    }
  }

  static function BuildRevert($group, $patch) {
    if ($group === 'available' and empty($_REQUEST['confirm'])) {
      $url = array('revert', "confirm=1&group=$group&patch=".urlencode($patch));
      $link = '<a href="'.SafePatchHomePage.'wiki/Reverting"'.HtmlTarget().'>';
      return HtmlConfirm($url, array('revertingUnpatched', null, array($link, '</a>')));
    } else {
      return self::PerformLockedOn($group, $patch, 'RevertLogging');
    }
  }

  static function BuildObliterate($group, $patch) {
    if (empty($_REQUEST['confirm'])) {
      $url = array('obliterate', "confirm=1&group=$group&patch=".urlencode($patch));
      $link = '<a href="'.SafePatchHomePage.'wiki/Reverting"'.HtmlTarget().'>';
      return HtmlConfirm($url, array('revertingUnpatched', null, array($link, '</a>')));
    } else {
      return self::PerformLockedOn($group, $patch, 'Obliterate');
    }
  }

    protected static function PerformLockedOn($group, $patch, $func) {
      global $P, $sp;

      if ($res = RequestChange()) {
        // return.
      } elseif (!$sp->lock->Lock()) {
        $res = self::CannotLock();
      } else {
        $obj = self::GetPatch($group, $patch);

        if ($obj) {
          if (is_string($func)) {
            $return = $obj->$func();

            $patchHTML = '<kbd class="text">'.QuoteHTML($patch).'</kbd>';
            if (isset($P['legend'])) {
              $res = '<p class="em">'.sprintf($P['done'], $patchHTML).'</p>'.
                     '<p>'.$P['legend'].'</p>';
            } else {
              $res = '<p>'.sprintf($P['done'], $patchHTML).'</p>';
            }

            is_array($return) and $res .= self::HtmlDiff($return);
          } else {
            $res = call_user_func($func, $group, $patch);
          }
        } else {
          $res = self::NoPatch($group, $patch);
        }
      }

      $sp->lock->Unlock();
      return $res;
    }

  static function BuildDiff($group, $patch) {
    global $T, $P, $sp;

    $obj = self::GetPatch($group, $patch);
    if ($obj) {
      $res = '<h1>'.$P[$obj->IsApplied() ? 'revertTitle' : 'applyTitle'].'</h1>';
      $diff = $obj->Diff();

      if ($diff) {
        $res .= '<p>'.sprintf(FmtNum('patchWillAffect', count($diff)),
                              QuoteKbd($obj->RelFile())).'</p>'.
                 self::HtmlDiff($diff);
      } else {
        $res .= '<p class="none">'.sprintf($T['patchWontChange'], QuoteKbd($patch)).'</p>';
      }
    } else {
      $res = self::NoPatch($group, $patch);
    }

    return $res;
  }

  static function BuildSource($group, $patch) {
    global $T, $P, $sp;

    $obj = self::GetPatch($group, $patch);
    if ($obj) {
      $src = file_get_contents($obj->file);
      $res = '<p>'.QuoteKbd($patch).'</p>'.
             ($src ? PatchSourceToHTML($src) : HtmlError($T['unreadable']));
    } else {
      $res = self::NoPatch($group, $patch);
    }

    return $res;
  }
}
