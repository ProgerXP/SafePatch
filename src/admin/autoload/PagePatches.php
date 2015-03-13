<?php

class PagePatches extends BasePage {
  static function Build() {
    global $mediaURL, $P, $sp;

    return '<h1>'.$P['pageTitle'].'</h1>'.
           self::Form().
           '<h2>'.$P['appliedTitle'].'</h2>'.
           self::Boxes($sp->AppliedPatches(true), 'applied').
           '<h2>'.$P['availableTitle'].'</h2>'.
           self::Boxes($sp->GetPatches(true), 'available').
           '<script type="text/javascript" src="http://proger.i-forge.net/filedrop-min.js"></script>'.
           '<script type="text/javascript" src="'.$mediaURL.'admin-patches.js"></script>';
  }

    static function Boxes(array $patches, $group) {
      global $P;

      foreach ($patches as &$one) {
        if ($group === 'available' and $one->IsApplied()) { $one = null; }
      }

      $res = '<div class="boxes two" id="'.$group.'Patches">';

        if ($patches = array_filter($patches)) {
          foreach ($patches as $name => $patch) {
            $res .= self::BoxFor($patch, $name, $group);
          }
        } else {
          $res .= '<p class="none">'.$P['noPatches'].'</p>';
        }

      return $res.'</div>';
    }

      static function BoxFor(SpPatch $patch, $name, $group) {
        global $T, $P, $mediaURL;

        $info = $patch->Info();
        $query = "group=$group&patch=".$name;

        $class = $patch->IsStateOnly() ? ' state-only' : '';
        $res = '<div class="box patch-info'.$class.'">';

          $author = "<em>$info[author]</em>";

            if ($url = $info['homepage']) {
              $author = '<a href="'.$url.'" rel="nofollow"'.HtmlTarget().'>'.$author.'</a>';
            }

            if ($email = $info['email']) {
              $url = "mailto:$email?subject=".rawurlencode($info['caption']);
              $author .= ' '.GlyphLink($url, 'mail');
            }

          $file = QuoteHTML($patch->RelFile(), ENT_QUOTES);
          $caption = '<strong title="'.$file.'">'.QuoteHTML($info['caption']).'</strong>';
          $res .= '<p class="title">'.sprintf($T['by'], $caption, $author);

            $version = $info['version'];
            $date = $info['date'];
            if ($version or $date) {
              $version and $version = sprintf('%1.1f', $version);

              if ($version and $date) {
                $str = sprintf($T['versionDate'], $version, ShortDate($date));
              } else {
                $str = $version ? sprintf($T['version'], $version) : ShortDate($date);
              }

              $res .= '<span class="version">'.$str.'</span>';
            }

          $res .= '</p>';

          if ($comment = $info['headComment']) {
            $res .= '<div class="desc">'.PatchCommentToHTML($comment).'</div>';
          }

          $img = '<img src="'.$mediaURL.'source.png" alt="Source" />';
          $res .= '<div>'.SingleBtn(array('source', $query), $img, 'one-icon').'</div>';

          $lang = $patch->WildcardFileCount() > 0 ? 'effectWildcard' : 'effect';
          $link = DiffGlyphA($group, $name);
          $res .= '<p class="effect">'.
                    sprintf(FmtNum($lang, $patch->FileCount()), $link, '</a>').
                  '</p>';

          $res .= '<div class="btn">';

            $time = $patch->ApplyTime();
            if ($time === null) {
              $res .= SingleBtn(array('apply', $query), $P['apply'], true).
                      SingleBtn(array('revert', $query), $P['tryReverting']);
            } elseif ($time === false) {
              $res .= DisabledBtn($P['missingFreshness']).
                      DisabledBtn($P['revert']);
            } else {
              $res .= DisabledBtn( sprintf($P['applied'], ShortDate($time)) ).
                      SingleBtn(array('revert', $query), $P['revert'], true);
            }

            if ($group === 'applied') {
              $res .= SingleBtn(array('obliterate', $query), $P['obliterate']);
            }

          $res .= '</div>';

          if ($patch->IsStateOnly()) {
            $msg = sprintf($P['stateOnly'], QuoteKbd( $patch->RelFile() ));
            $res .= '<p class="state-msg">'.$msg.'</p>';
          }

        return $res.'</div>';
      }

  static function Form() {
    global $T, $P;

    list($url, $fields) = FormPageURL('upload');
    return '<h2>'.$P['uploadTitle'].'</h2>'.
           '<form class="gen" id="uploadForm" action="'.$url.'" method="post"'.
                  ' enctype="multipart/form-data">'.$fields.
             '<input type="file" name="fd-file" />'.
             '<input type="submit" class="btn" name="preview" value="'.$P['uploadPreviewBtn'].'" />'.
             '<input type="submit" class="btn" value="'.$P['uploadApplyBtn'].'" />'.
           '</form>'.
           '<fieldset style="display: none" id="uploadZone">'.
             '<legend><span>'.$T['filedropLegend'].'</span></legend>'.
             '<input type="hidden" name="filedrop" value="1" />'.
             '<iframe id="fdIFrame" name="fdIFrame" src="javascript:false;"></iframe>'.
             '<form action="" target="fdIFrame" method="post" enctype="multipart/form-data">'.
               '<input type="file" class="fd-file" name="fd-file" />'.
               '<div class="flags">'.
                 '<input type="checkbox" name="preview" id="fdPreview" value="1"'.
                         HtmlCheckedIf(Cookie('up-patch-preview')).' />'.
                 '<label for="fdPreview">'.$P['uploadPreview'].'</label>'.
               '</div>'.
             '</form>'.
             '<p>'.$T['filedropButton'].'</p>'.
           '</fieldset>';
  }
}
