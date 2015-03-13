<?php

class PageLogs extends BasePage {
  static function Build() {
    if (!empty($_REQUEST['log']) or !empty($_REQUEST['prune'])) {
      return self::BuildLog();
    } else {
      return self::BuildLogs();
    }
  }

  static function BuildLogs() {
    global $P, $sp;

    $req = $_REQUEST;
    empty($req['reset']) or $req = array();

    return '<h1>'.$P['pageTitle'].'</h1>'.
           self::HtmlList($sp->Logger()->AllWithTimes(), $req);
  }

    static function SettingsForm(array $req) {
      global $T, $P;

      list($url, $fields) = FormPageURL('logs');
      return '<form class="gen" action="'.$url.'" method="get">'.$fields.
               '<span class="ctl">'.
                 '<strong>'.$P['formSort'].'</strong> '.
                 '<input type="radio" name="sort" id="sortTime" value=""'.
                         HtmlCheckedIf(empty($req['sort'])).' />'.
                 '<label for="sortTime">'.$P['formSortTime'].'</label> '.
                 '<input type="radio" name="sort" id="sortNatural" value="natural"'.
                         HtmlCheckedIf(@$req['sort'] === 'natural').' />'.
                 '<label for="sortNatural">'.$P['formSortNatural'].'</label> '.
                 '<input type="radio" name="sort" id="sortName" value="name"'.
                         HtmlCheckedIf(@$req['sort'] === 'name').' />'.
                 '<label for="sortName">'.$P['formSortName'].'</label> '.
               '</span>'.
               '<span class="ctl">'.
                 '<input type="checkbox" name="sortAsc" id="sortAsc" value="1"'.
                         HtmlCheckedIf(!empty($req['sortAsc'])).' />'.
                 '<label for="sortAsc">'.$P['formSortAsc'].'</label> '.
               '</span>'.
               '<input class="ctl btn em" type="submit" value="'.$T['updateViewBtn'].'" />'.
               '<input class="ctl btn" type="submit" name="reset"'.
                       ' value="'.$T['resetViewBtn'].'" />'.
             '</form>';
    }

    static function HtmlList(array $files, array $req) {
      global $P, $sp;

      if ($files) {
        $basePath = $sp->Logger() instanceof SpFileLog ? $sp->Logger()->BasePath() : '';
        self::Sort($files, @$req['sort'], @$req['sortAsc']);

        $res = self::SettingsForm($req).'<ol class="entries">';

          $recent = $files ? max($files) : 0;

          foreach ($files as $file => $time) {
            $page = array('logs', 'log='.urlencode($file).'#bottom');
            $caption = QuoteHTML( substr($file, strlen($basePath)) );
            $view = GlyphLink($page, 'zoom', $caption);

            $prune = GlyphA(array('logs', 'prune='.urlencode($file)), 'prune');
            $caption = function_exists('strptime') ? 'firstMsg' : 'lastMsg';
            $caption = sprintf($P[$caption], '<strong>'.FmtSize(filesize($file)).'</strong>',
                               '<span class="time">'.DateTime($time).'</span>',
                               $prune, '</a>');

            $class = $time == $recent ? ' class="hili"' : '';
            $res .= '<li'.$class.'>'.$view.$caption.'</li>';
          }

        return $res.'</ol>';
      } else {
        if ($sp->Logger() instanceof SpFileLog) {
          $file = QuoteKbd( $sp->Logger()->File() );
          $current = '<p>'.sprintf($P['noLogsCurrent'], $file).'</p>';
        } else {
          $current = '';
        }

        $path = QuoteKbd($sp->Option('logPath'));
        return '<div class="none"><p>'.sprintf($P['noLogs'], $path)."</p>$current</div>";
      }
    }

  static function Sort(array &$files, $sort, $asc) {
    $times = $files;
    $files = array_keys($files);

      switch ($sort) {
      case 'natural':   natcasesort($files); break;
      case 'name':      sort($files, SORT_LOCALE_STRING); break;
      default:          $files = $times; asort($files); break;
      }

    $asc or $files = array_reverse($files);

    if (isset($files[0])) {
      $files = array_flip($files);
      foreach ($files as $file => &$time) { $time = $times[$file]; }
    }
  }

  static function BuildLog() {
    global $P, $sp;

    if ($log = &$_REQUEST['log']) {
      $res = '<h1>'.$P['viewTitle'].'</h1>';

      if (is_file($log)) {
        $data = QuoteHTML( trim(file_get_contents($log), "\r\n") );
        $isEmpty = ltrim($data) === '';

        $data = $isEmpty ? '<p class="none">'.$P['emptyLog'].'</p>'
                         : "<pre class=\"gen prewrap\">$data</pre>";

        $link = '<a href="#bottom">';
        $order = $isEmpty ? '' : '<p>'.sprintf($P['msgOrder'], $link, '</a>').'</p>';

        return array('windowTitle' => $P['viewTitle'],
                     'body' => $res.'<p>'.QuoteKbd($log).$order.'</p>'.$data);
      } else {
        return array('windowTitle' => $P['viewTitle'],
                     'body' => $res.HtmlError($P['nonExistingLog'], QuoteKbd($log)));
      }
    } elseif ($log = &$_REQUEST['prune']) {
      $logs = $sp->Logger()->AllWithTimes();
      $fromTime = $logs[$log];

      $res = '<h1>'.$P['pruneTitle'].'</h1>';

        if ($msg = RequestChange('prune')) {
          return $res.$msg;
        } elseif ($fromTime) {
          $res .= '<ol class="report entries">';

            foreach ($logs as $file => $time) {
              if ($time <= $fromTime) {
                $ok = unlink($file) ? 'ok' : 'error';
                $ok === 'ok' and SpUtils::RemoveEmptyDirs(dirname($file));
                $res .= '<li class="'.$ok.'">'.QuoteKbd($file).
                        ($ok === 'ok' ? '' : $P['pruneError']).'</li>';
              }
            }

          return array('windowTitle' => $P['pruneTitle'], 'body' => $res.'</ol>');
        } else {
          return array('windowTitle' => $P['pruneTitle'],
                       'body' => $res.HtmlError($P['nonExistingLog'], QuoteKbd($log)));
        }
    } else {
      return self::InvalidReqParams();
    }
  }
}