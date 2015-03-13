<?php

class PageChanges extends BasePage {
  static function Build() {
    global $P, $sp;

    $req = $_REQUEST;
    empty($req['reset']) or $req = array();

    $res = "<h1>$P[pageTitle]</h1>".
           "<p>$P[legend]</p>";

      $applied = $sp->AppliedPatches(true);
      if ($applied) {
        $res .= self::SettingsForm($req).self::HtmlChanges($applied, $req);
      } else {
        $res .= self::NoFilesChanged();
      }

    return $res;
  }

    static function HtmlChanges(array $patches, array $req) {
      global $T, $P;

      $hasStateOnlyPatches = false;
      $res = '';

        if (@$req['group'] === 'patches') {
          foreach ($patches as $patch) {
            $caption = self::PatchCaption($patch);
            $res .= '<h2>'.$caption.'</a></h2>';

            $files = $patch->AppliedFiles();

            if ($files) {
              self::SortFiles($files, $req);
              $res .= '<ol class="entries report">';

                foreach ($files as $relFile => $time) {
                  $link = DiffGlyphA('applied', $patch, $relFile);
                  $diff = sprintf($P['diff'], $link, '</a>');

                  $class = self::LiPatchClass($patch, $hasStateOnlyPatches);
                  $res .= "<li$class>".QuoteHTML($relFile).$diff.'</li>';
                }

              $res .= '</ol>';
            } else {
              $res .= '<p class="none">'.$P['noPatchChanges'].'</p>';
            }
          }
        } else {
          $files = array();

            foreach ($patches as $patch) {
              foreach ($patch->AppliedFiles() as $relFile => $time) {
                $files[$relFile][] = array($patch, $time);
              }
            }

          $files = array_filter($files);
          self::SortFiles($files, $req);

          if ($files) {
            foreach ($files as $relFile => $filePatches) {
              $res .= '<h2>'.QuoteHTML($relFile).'</h2>'.
                      '<ol class="entries report">';

                foreach ($filePatches as $item) {
                  list($patch, $time) = $item;

                  $caption = self::PatchCaption($patch, $time, $relFile);

                  $class = self::LiPatchClass($patch, $hasStateOnlyPatches);
                  $res .= "<li$class>$caption</li>";
                }

              $res .= '</ol>';
            }
          } else {
            $res .= self::NoFilesChanged();
          }
        }

      if ($hasStateOnlyPatches) {
        $msg = sprintf($P['stateOnlyPatchesMsg'], '<span class="error">', '</span>');
        $res = '<p class="info">'.$msg.'</p>'.$res;
      }

      return $res;
    }

      static function LiPatchClass(SpPatch $patch, &$hasStateOnlyPatches) {
        $class = '';

          if ($patch->IsStateOnly()) {
            $class = 'error';
            $hasStateOnlyPatches = true;
          }

        return $class ? " class=\"$class\"" : '';
      }

      static function PatchCaption(SpPatch $patch, $time = null, $relFile = null) {
        global $P;

        $time or $time = $patch->ApplyTime();
        $time = $time ? ShortDate($time) : '&mdash;';

        return sprintf($P['patchCaption'],
          '<strong>'.Quote8($patch->Info('caption')).'</strong>',
          '<em>'.Quote8($patch->Info('author')).'</em>',
          '<span class="time">'.$time.'</span>',
          QuoteKbd($patch->RelFile()),
          DiffGlyphA('applied', $patch, $relFile), '</a>');

      }

    static function SettingsForm(array $req) {
      global $T, $P;

      list($url, $fields) = FormPageURL('changes');
      return '<form class="gen block" action="'.$url.'" method="get">'.
               '<div class="ctl">'.$fields.
                 '<strong>'.$P['formGroup'].'</strong> '.
                 '<input type="radio" name="group" id="groupFiles" value=""'.
                         HtmlCheckedIf(empty($req['group'])).' />'.
                 '<label for="groupFiles">'.$P['formGroupFiles'].'</label> '.
                 '<input type="radio" name="group" id="groupPatches" value="patches"'.
                         HtmlCheckedIf(@$req['group'] === 'patches').' />'.
                 '<label for="groupPatches">'.$P['formGroupPatches'].'</label> '.
               '</div>'.
               '<div class="ctl">'.
                 '<span class="ctl">'.
                   '<strong>'.$P['formFileSort'].'</strong> '.
                   '<input type="radio" name="fileSort" id="fileSortName" value=""'.
                           HtmlCheckedIf(empty($req['fileSort'])).' />'.
                   '<label for="fileSortName">'.$P['formFileSortName'].'</label> '.
                   '<input type="radio" name="fileSort" id="fileSortNatural" value="natural"'.
                           HtmlCheckedIf(@$req['fileSort'] === 'natural').' />'.
                   '<label for="fileSortNatural">'.$P['formFileSortNatural'].'</label> '.
                   '<input type="radio" name="fileSort" id="fileSortTime" value="time"'.
                           HtmlCheckedIf(@$req['fileSort'] === 'time').' />'.
                   '<label for="fileSortTime">'.$P['formFileSortTime'].'</label> '.
                 '</span>'.
                 '<span class="ctl">'.
                   '<input type="checkbox" name="fileSortDesc" id="fileSortDesc" value="1"'.
                           HtmlCheckedIf(!empty($req['fileSortDesc'])).' />'.
                   '<label for="fileSortDesc">'.$P['formSortDesc'].'</label> '.
                 '</span>'.
               '</div>'.
               '<div class="ctl">'.
                 '<input class="ctl btn em" type="submit" value="'.$T['updateViewBtn'].'" />'.
                 '<input class="ctl btn" type="submit" name="reset"'.
                         ' value="'.$T['resetViewBtn'].'" />'.
               '</div>'.
             '</form>';
    }

  static function NoFilesChanged() {
    global $P;
    return '<p class="none">'.$P['noFilesChanged'].'</p>';
  }

  static function SortFiles(array &$files, array $req) {
    switch (@$req['fileSort']) {
    case 'time':      uksort($files, array(__CLASS__, 'SortByTime')); break;
    case 'natural':   uksort($files, 'strnatcasecmp'); break;
    default:          ksort($files); break;
    }

    empty($req['fileSortDesc']) or $files = array_reverse($files);
  }

    static function SortByTime($a, $b) {
      global $sp;

      $timeA = filemtime($sp->Option('basePath').$a);
      $timeB = filemtime($sp->Option('basePath').$b);
      return $timeA > $timeB ? +1 : ($timeA < $timeB ? -1 : 0);
    }
}