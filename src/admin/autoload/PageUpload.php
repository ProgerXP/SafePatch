<?php

class PageUpload extends BasePage {
  static function Build() {
    global $P, $sp;

    Cookie('up-patch-preview', !empty($_REQUEST['preview']));

    $res = '';

      if ($msg = RequestChange()) {
        $res .= $msg;
      } elseif ($group = &$_REQUEST['group'] and $patch = &$_REQUEST['patch'] and
                !empty($_REQUEST['preview'])) {
        $obj = self::GetPatch($group, $patch);
        if ($patch) {
          $res .= self::ProceedOn($obj);
        } else {
          $res .= self::NoPatch($group, $patch);
        }
      } elseif (!self::GetUpload($name, $data)) {
        $res .= HtmlError($P['noUpload']);
      } elseif (substr($name, -4) === '.php') {
        $res .= HtmlError($P['unsafeName'], QuoteKbd($name));
      } else {
        $destFN = $sp->PatchPath().$name;

        if (is_file($destFN)) {
          $res .= HtmlError($P['destExists'], QuoteKbd($destFN));
        } elseif (!file_put_contents($destFN, $data)) {
          $res .= HtmlError($P['cannotWrite'], QuoteKbd($destFN));
        } else {
          $res .= self::ProceedOn($destFN);
        }
      }

    if (empty($_REQUEST['filedrop'])) {
      return "<h1>$P[pageTitle]</h1>".$res;
    } else {
      self::SendFileDrop('!'.$res);
      exit;
    }
  }

    static function GetUpload(&$name, &$data) {
      if (!empty($_FILES['fd-file']) and is_uploaded_file($_FILES['fd-file']['tmp_name'])) {
        $name = $_FILES['fd-file']['name'];
        $data = file_get_contents($_FILES['fd-file']['tmp_name']);
      } else {
        $name = urldecode($_SERVER['HTTP_X_FILE_NAME']);
        $data = file_get_contents("php://input");
      }

      return strlen($data) > 0;
    }

    static function ProceedOn($file) {
      global $P;

      $preview = !empty($_REQUEST['preview']);

      if ($preview and is_object($file) and $file instanceof SpPatch) {
        $patch = $file;
      } else {
        $patch = self::GetPatch('available', $file);
      }

      if (!$patch) {
        return HtmlError($P['invalidFile'], QuoteKbd($file));
      } else {
        $query = 'group=available&patch='.urlencode($patch->file).'&preview='.$preview;

        if (empty($_REQUEST['filedrop'])) {
          if ($preview) {
            return self::HtmlPreview($patch, $query);
          } else {
            header('Location: '.PageURL('apply', $query));
            exit;
          }
        } else {
          self::SendFileDrop( PageURL($preview ? 'upload' : 'apply', $query) );
          exit;
        }
      }
    }

      static function HtmlPreview(SpPatch $obj, $query) {
        global $T, $P;

        $fileHTML = QuoteKbd($obj->file);
        $diff = $obj->Diff();

        if ($diff) {
          $apply = '<div>'.SingleBtn(array('apply', $query), $P['doApply'], true).'</div>';

          return '<p>'.sprintf(FmtNum('patchWillAffect', count($diff)), $fileHTML).'</p>'.
                 $apply.
                 self::HtmlDiff($diff).
                 $apply;
        } else {
          return '<p class="none">'.sprintf($T['patchWontChange'], $fileHTML).'</p>';
        }
      }

      static function SendFileDrop($output) {
        $callback = &$_REQUEST['fd-callback'];

        if ($callback) {
          header('Content-Type: text/html; charset=utf-8');
          $output = addcslashes($output, "\\\"\0..\x1F");
          echo '<!DOCTYPE html><html><head></head><body><script type="text/javascript">',
                 "try { window.top.$callback(\"$output\"); } catch (e) { }".
               "</script></body></html>";
        } else {
          header('Content-Type: text/plain; charset=utf-8');
          echo $output;
        }
      }
}