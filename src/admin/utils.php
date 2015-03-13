<?php

function DetectUserLang() {
  global $spRoot;
  $accepts = $_SERVER['HTTP_ACCEPT_LANGUAGE'];

  if ($accepts) {
    $languages = array();

    $accepts = explode(',', $accepts);
    foreach ($accepts as $piece) {
      @list($lang, $quality) = explode(';', $piece);

      if (@$quality and substr($quality, 0, 2) == 'q=') {
        $quality = floatval(trim( substr($quality, 2) ));
      } else {
        $quality = 1.0;
      }

      $languages[ round($quality * 1000) ] = trim($lang);  // *1000 because ksort() discards float values.
    }

    krsort($languages);

    foreach ($languages as $lang) {
      $lang = substr(trim($lang), 0, 2);  // = ISO-2
      if (strlen($lang) === 2 and is_file($spRoot."lang/$lang.php")) { return $lang; }
    }
  }
}

function OutputPage(array &$vars) {
  global $mediaURL, $T, $nexus, $lang;

  extract($vars, EXTR_SKIP);

    if (!isset($languages)) {
      $languages = array();

        $url = preg_replace('/([?&])lang=[a-z]{2}(&|$)/i', '\1', $_SERVER['REQUEST_URI']);
        $url = rtrim($url, '?&');
        $url .= (strrchr($url, '?') === false ? '?' : '&').'lang=';

        foreach (scandir('lang') as $file) {
          if (strlen($file) === 6 and substr($file, -4) === '.php') {
            $code = substr($file, 0, 2);
            $languages[$code] = array('url' => $url.$code,
                                      'isCurrent' => $code === $lang);
          }
        }

      ksort($languages);
    }

    isset($sidebar) or $sidebar = array();
    $nexus->Fire('sidebar', array(&$sidebar));

    isset($header) or $header = array();
    $nexus->Fire('header', array(&$header));

    isset($footer) or $footer = array();
    $nexus->Fire('footer', array(&$footer));

  $nexus->HasAny('output') and ob_start();
  require 'display/page.php';

  if ($nexus->HasAny('output')) {
    $output = ob_get_clean();
    $nexus->Fire('output', array(&$output));
    echo $output;
  }
}

  function OutputBlocks($parentAttrs, $itemClass, $blocks) {
    if ($blocks) {
      echo '<div ', $parentAttrs, '>';

        foreach ($blocks as $item) {
          is_array($item) or $item = array('body' => $item);
          $item += array('class' => '');

          $item['class'] .= " $itemClass";
          echo '<div class="', trim($item['class']), '">', $item['body'], '</div>';
        }

      echo '</div>';
    }
  }

function PageURL($page, $query = '') {
  $page = urlencode($page);
  return "?page=$page".("$query" === '' ? '' : "&$query");
}

  // $arg_2 - either true (quotes returned HTML), false (doesn't) or a string
  //          (function name to call, e.g. 'FormPageURL').
  function PageParamToURL($page, $arg_2) {
    $func = is_string($arg_2) ? $arg_2 : 'PageURL';

      if (is_array($page)) {
        $page = $func($page[0], $page[1]);
      } else {
        $page[0] === '=' and $page = $func(substr($page, 1));
      }

    if (is_bool($arg_2) and $arg_2) {
      $page = QuoteHTML($page, ENT_QUOTES);
    }

    return $page;
  }

  function FormPageURL($page, $query = '') {
    $page = urlencode($page);
    $query = "page=$page".("$query" === '' ? '' : "&$query");
    parse_str($query, $vars);

    $query = '';

      foreach ($vars as $name => $value) {
        $name = QuoteHTML($name);
        $value = QuoteHTML($value);
        $query .= "<input type=\"hidden\" name=\"$name\" value=\"$value\" />\n";
      }

    return array('.', $query);
  }

function GlyphA($page, $image) {
  global $mediaURL;

  $page = PageParamToURL($page, true);

  $image = QuoteHTML($image);
  strrchr($image, '.') or $image .= '.png';

  return '<a href="'.$page.'" class="glyph"><img src="'.$mediaURL.$image.'" alt="" />';
}

  function GlyphLink($page, $image, $caption = '') {
    return GlyphA($page, $image).$caption.'</a>';
  }

  function DiffGlyphA($group, $patch, $affected = '') {
    is_object($patch) and $patch = $patch->file;

    $query = "group=$group&patch=".urlencode($patch);
    "$affected" === '' or $query .= '#'.urlencode($affected);

    return GlyphA(array('diff', $query), 'zoom');
  }

function SingleBtn($page, $caption, $class = '') {
  $class === true and $class = 'em';
  $class === '' or $class = " class=\"$class\"";

  list($url, $fields) = PageParamToURL($page, 'FormPageURL');
  return '<form action="'.$url.'" method="get" class="btn">'.$fields.
           '<button'.$class.' type="submit">'.$caption.'</button>'.
         '</form>';
}

  function DisabledBtn($caption) {
    return '<span class="disabled-btn">'.$caption.'</span>';
  }

function HtmlConfirm($page, $lang) {
  global $T, $P;

  $lang = (array) $lang;

  $usePageLang = isset($P[$lang[0]]);
  $msg =    $usePageLang ? $P[$lang[0]] : $T[$lang[0]];
  $legend = $usePageLang ? @$P[$lang[0].'Legend'] : @$T[$lang[0].'Legend'];
  $btn =    $usePageLang ? @$P[$lang[0].'Yes'] : @$T[$lang[0].'Yes'];
  $btn or $btn = $T['confirmBtn'];

    if ($fmt = &$lang[1]) {
      array_unshift($fmt, $msg);
      $msg = call_user_func_array('sprintf', $fmt);
    }

    if ($fmt = &$lang[2]) {
      array_unshift($fmt, $legend);
      $legend = call_user_func_array('sprintf', $fmt);
    }

  return '<p class="em warning">'.$msg.'</p>'.($legend ? "<p>$legend</p>" : '').
         '<div>'.SingleBtn($page, $btn, true).'</div>';
}

function QuoteHTML($str, $quotes = ENT_COMPAT, $doubleEncode = true) {
  return htmlspecialchars($str, $quotes, 'ISO-8859-1', $doubleEncode);
}

  function Quote8($str, $quotes = ENT_COMPAT, $doubleEncode = true) {
    return htmlspecialchars($str, $quotes, 'utf-8', $doubleEncode);
  }

function QuoteAndWrapIn($tag, $str) { return "<$tag>".QuoteHTML($str)."</$tag>"; }
function QuoteKbd($str) { return QuoteAndWrapIn('kbd', $str); }
function QuoteStrong($str) { return QuoteAndWrapIn('strong', $str); }

function HtmlCheckedIf($cond) { return $cond ? ' checked="checked"' : ''; }
function HtmlSelectedIf($cond) { return $cond ? ' selected="selected"' : ''; }
function HtmlTarget() { return HtmlTargetIf(true); }

  function HtmlTargetIf($cond) {
    global $linkTarget;
    return ($cond and $linkTarget) ? " target=\"$linkTarget\"" : '';
  }

function LogMsgToHTML($msg) {
  static $replaces = array('[' => '[<kbd class="text">', ']' => '</kbd>]');
  return strtr(Quote8($msg), $replaces);
}

  function PatchSourceToHTML($str) {
    static $regexps = array(
      '/(&lt;\/?\w+)(.*?)(&gt;)/' =>
        '<span class="tag"><strong>\1</strong>\2<strong>\3</strong></span>',
      '/^##+.*$/m' => '<span class="comment em">\0</span>',
      '/^#.*$/m' => '<span class="comment">\0</span>');

    $str = str_replace("\r\n", "\n", QuoteHTML($str, ENT_NOQUOTES));
    $str = preg_replace(array_keys($regexps), array_values($regexps), $str);

    $pf = '<code><b></b>';
    $sf = '</code>';
    $str = $pf.str_replace("\n", "$sf\n$pf", $str, $lineCount).$sf;

    $class = $lineCount < 100 ? ' few' : ($lineCount >= 1000 ? ' many' : '');
    return '<pre class="light patch count prewrap'.$class.'">'.$str.'</pre>';
  }

  function PatchCommentToHTML($str) {
    static $regexps = array('/\*(\S[^*]*\S)\*/' => '*<strong>\1</strong>*');

    $str = str_replace("\r\n", "\n", QuoteHTML($str, ENT_NOQUOTES));
    $str = preg_replace(array_keys($regexps), array_values($regexps), $str);

    return '<p>'.str_replace("\n\n", "</p>\n<p>", $str).'</p>';
  }

function HtmlError($msg, $arg_1 = null) {
  $args = func_get_args();
  return '<div class="error">'.call_user_func_array('sprintf', $args).'</div>';
}

function LastRequestsFN() { return dirname(__FILE__).'/requests.log'; }

// oldest first.
function ParseLastRequests() {
  $file = LastRequestsFN();
  $file = is_file($file) ? file($file) : array();

    foreach ($file as &$line) {
      if (($line = trim($line)) === '') {
        $line = null;
      } else {
        list($time, $ip, $user, $url) = explode(' ', $line, 4);
        is_numeric($time) or $time = strtotime($time);
        $line = compact('time', 'ip', 'user', 'url');
      }
    }

  return array_filter($file);
}

function LastLogin() {
  global $config;
  $logins = ParseLastRequests();

  do {
    $last = array_pop($logins);
  } while ($last and $last['time'] + $config['sessionLength'] >= time());

  return $last;
}

function TrackRequest() {
  $user = @$_SERVER['HTTP_AUTH_USER'];
  $user = isset($user) ? strtr($user, ' ', '_') : 'Anonymous';

  $line = date(DATE_ATOM)." $_SERVER[REMOTE_ADDR] $user $_SERVER[REQUEST_URI]";
  $line = strtr($line, "\r\n", '  ');

  file_put_contents(LastRequestsFN(), "$line\n", FILE_APPEND | LOCK_EX);
}

function Cookie($name, $value = null, $expire = 0) {
  if ($value === false) {
    setcookie("sp-admin[$name]", '', time() - 3600 * 24);
  } elseif ($value !== null) {
    $expire or $expire = 3600 * 24 * 30;
    setcookie("sp-admin[$name]", $value, time() + $expire);
  } else {
    return @$_COOKIE['sp-admin'][$name];
  }
}

function RequestChange($action = null) {
  global $nexus, $T, $page;

  $allow = true;
  $msg = $nexus->Fire('on change', array(&$allow, &$action));

  if (!$allow and !$msg) {
    $msg = sprintf($T['requestChangeDenied'], QuoteStrong($page));
  }

  if ($msg) {
    return "<div class=\"error access\">$msg</div>";
  }
}

function SetLangFrom(array $strings) {
  global $reqPage, $allT, $T, $P;

  $allT = $strings;
  $T = $allT['panel'];
  $P = isset($T[$reqPage]) ? $T[$reqPage] : array();
}

  function MergeLang(array $new) {
    global $lang, $allT, $config;

    $newLang = $lang;

      isset($new[$newLang]) or $newLang = $config['defaultLang'];

      if (!isset($new[$newLang])) {
        $newLang = array_keys($new);
        $newLang = $newLang[0];
      }

    $allT['panel'] += $new[$newLang];
    SetLangFrom($allT);
  }

function DateTime($timestamp) {
  global $T;
  return date($T['datetime'], $timestamp);
}

function ShortDate($timestamp) {
  global $T;
  return date($T['date'], $timestamp);
}

function ShortTime($timestamp, $format = null) {
  global $T;
  return date($T['time'] ? $T['time'] : $format, $timestamp);
}

function FmtNum($langName, $number) {
  global $allT, $T, $P;

  $where = &$P;
  $sentense = @$P[$langName];

    $sentense or ($sentense = @$T[$langName] and $where = &$T);
    $sentense or ($sentense = @$allT[$langName] and $where = &$allT);

  if (!$sentense) {
    throw new Exception("Cannot FmtNum($langName) - no language string found.");
  }

    $numLang = @$where[$langName.'Num'];
    if (!isset($numLang)) {
      $numLang = $sentense;
      $sentense = '$';
    }

  $rolls = substr($numLang, -1) === '*';
  $infl = explode( ',', rtrim($numLang, '*') );  // $ хит, ов, , а, ов*
  foreach ($infl as &$str) { $str = trim($str); }

  $stem = array_shift($infl);
  $word = FmtNumUsing($stem, $infl, $rolls, $number);

  return str_replace('$', str_replace('$', $number, $word), $sentense);
}

  function FmtNumUsing($stem, $inflections, $numberRolls, $number) {
    $inflection = '';

    if ($number == 0) {
      $inflection = $inflections[0];
    } elseif ($number == 1) {
      $inflection = $inflections[1];
    } elseif ($number <= 4) {
      $inflection = $inflections[2];
    } elseif ($number <= 20 or !$numberRolls) {
      $inflection = $inflections[3];
    } else {  // 21 and over
      return FmtNumUsing( $stem, $inflections, $numberRolls, substr($number, 1) );
    }

    return $stem.$inflection;
  }

function FmtSize($size) {
  $suffix = 'B';

    if ($size > 1024) {
      $suffix = 'K';
      $size /= 1024;
    }

    if ($size > 1024) {
      $suffix = 'M';
      $size /= 1024;
    }

    if ($size > 1024) {
      $suffix = 'G';
      $size /= 1024;
    }

  return $size == 0 ? '0' : ((int) $size)." $suffix";
}
