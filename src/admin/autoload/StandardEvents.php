<?php

class StandardEvents {
  static $perms = array('basePath' => true, 'logs/' => true, 'state/' => true,
                        'admin/requests.log' => true);
  static $logFile;
  static $messages = array();

  static function Page404() {
    global $T, $reqPage;

    $url = '<kbd>'.QuoteHTML($_SERVER['REQUEST_URI']).'</kbd>';
    $page = '<strong>'.QuoteHTML($reqPage).'</strong>';
    return array('body' => '<div class="error">'.sprintf($T['404'], $url, $page).'</div>');
  }

  static function PermsSidebar(array &$res) {
    global $T, $sp;
    $errors = array();

      foreach (self::$perms as $path => $isWritable) {
        $path === 'basePath' and $path = $sp->Option($path);

        if (!is_readable($path) or ( $isWritable ^ is_writable($path) )) {
          $access = fileperms($path);
          $access = ($access & 0400 ? 'r' : '-').($access & 0200 ? 'w' : '-').
                    ($access & 0040 ? 'r' : '-').($access & 0020 ? 'w' : '-');

          $msg = $isWritable ? 'spNonWritable' : 'spNonReadable';
          $errors[] = '<li>'.sprintf($T[$msg], QuoteKbd($path), QuoteKbd($access)).'</li>';
        }
      }

    if ($errors) {
      $body = '<p>'.$T['spBadFilePerms'].'</p><ul>'.join($errors).'</ul>';
      $res[] = array('class' => 'alert', 'body' => $body);
    }
  }

  static function StatsSidebar(array &$res) {
    global $T, $config, $mediaURL, $homeURL, $spRoot, $sp;
    $body = array();

      if ($name = $config['homeName']) {
        $body[] = "<span class=\"home-name\">$name</span>";
      }

      $info = QuoteHTML(php_uname(), ENT_QUOTES);
      $system = QuoteHTML($spRoot).' &nbsp; '.
                '<abbr title="'.$info.'">'.QuoteHTML(PHP_OS).'</abbr>';

      $link = GlyphA($homeURL, 'home').QuoteHTML(rtrim($homeURL, '/')).'</a>';
      $body[] = sprintf($T['statsHome'], '<a href=".">', '</a>', "<kbd>$link</kbd>").
                '<br /> &nbsp; <span class="gray">'.$system.'</span>';

      $time = $sp->LastFreshenTime();
      $date = $time ? DateTime($time) : "<em>$T[statsFreshenNever]</em>";
      $date = GlyphA('=freshen', 'refresh').$date.'</a>';

      $applied = FmtNum( 'statsPatchesApplied', count($sp->AppliedPatches()) );

      $available = 0;
      foreach ($sp->GetPatches(true) as $obj) { $obj->IsApplied() or ++$available; }
      $available = FmtNum( 'statsPatchesAvailable', $available);

      $body[] = sprintf($T['statsFreshen'], $date).'<br /> &nbsp; '.
                sprintf($T['statsPatches'],
                        sprintf($applied, '<a href="'.PageURL('patches').'">').'</a>',
                        $available);

      $last = LastLogin();
      if ($last) {
        $user = '<strong>'.QuoteHTML($last['user']).'</strong>';
        $url = 'http://www.maxmind.com/app/locate_demo_ip?ips='.$last['ip'];
        $body[] = sprintf($T['statsLastLogin'], DateTime($last['time']), '<br /> &nbsp;',
                          $user, GlyphA($url, 'map').$last['ip'].'</a>');
      }

    $res[] = array('class' => 'light', 'body' => '<p>'.join('</p><p>', $body).'</p>');
  }

  static function MenuSidebar(array &$res) {
    global $config, $T, $reqPage, $curMenuPage;

    $menu = '';

      foreach ($config['menu'] as $url => $item) {
        @list($name, $classes) = (array) $item;

        if ($url[0] === '=') {
          $url = substr($url, 1);
          $classes .= " page $url";

          $isCur = $curMenuPage === $url;
          $url = PageURL($url);
        } else {
          $isCur = strpos(basename( $_SERVER['SCRIPT_FILENAME'] ), $url) !== false;
        }

        $isCur and $classes .= ' cur';
        $class = $classes ? ' class="'.trim($classes).'"' : '';
        $target = HtmlTargetIf(strpos($url, '://'));

        $caption = &$T['menu'.$name];
        isset($caption) or $caption = $name;

        $menu .= '<li'.$class.'><a href="'.$url.'"'.$target.'>'.$caption.'</a>'.
                 ($isCur ? ' <span>&raquo;</span>' : '').'</li>';
      }

    $res[] = array('class' => 'big box menu', 'body' => "<ul>$menu</ul>");
  }

  static function OnLog(&$msg, array &$extra, &$canLog, SpLog $log) {
    self::$messages[] = $msg;

    if ($log instanceof SpFileLog) {
      $relFile = substr($log->File(), strlen($log->BasePath()));
      self::$logFile = array($log->File(), $relFile);
    }
  }

  static function LogsToFooter(array &$footer) {
    global $T;

    if (self::$messages) {
      $query = self::$logFile ? 'log='.urlencode(self::$logFile[0]).'#bottom' : '';
      $link = '<a href="'.PageURL('logs', $query).'"'.HtmlTarget().'>';

      $file = self::$logFile ? ' '.QuoteKbd(self::$logFile[1]) : '';

      $body = '<h2>'.FmtNum('newLogTitle', count(self::$messages)).'</h2>'.
              '<p>'.sprintf($T['newLogLegend'], $link, '</a>'.$file).'</p>'.
              '<ol class="entries">';

        foreach (self::$messages as $msg) {
          $body .= '<li>'.LogMsgToHTML($msg).'</li>';
        }

      $body .= '</ol>';
      $footer[] = array('class' => 'log', 'body' => $body);
    }
  }
}