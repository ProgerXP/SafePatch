<?php
  require 'core.php';

  $page = strtolower(@$_REQUEST['page']);
  $page or $page = strtolower($index);

    global $reqPage, $curMenuPage;
    $reqPage = $curMenuPage = $page;

  $nexus->HasAny("page: $page") or $page = '404';

    global $P;
    if (isset($T[$page]) and is_array($T[$page])) {
      $P = &$T[$page];
    } else {
      $P = array();
    }

  try {
    $page = $nexus->Fire("page: $page");
    is_array($page) or $page = array('body' => $page);
    $page += array('page' => $reqPage, 'windowTitle' => @$P['pageTitle']);
  } catch (Exception $e) {
    $bugLink = GlyphA('http://safepatch.i-forge.net/issues/list', 'bug');

    $body = '<h1>'.$T['exceptionTitle'].'</h1>'.
            '<div class="error">'.
              sprintf($T['exception'], get_class($e), $e->getFile(), $e->getLine(),
                      QuoteStrong($reqPage),
                      '<p><em>'.QuoteHTML($e->getMessage()).'</em></p>').
            '</div>'.
            '<p class="em">'.sprintf($T['exceptionBug'], $bugLink, '</a>').'</p>';

      if ($trace = $e->getTraceAsString()) {
        $trace = '<pre class="gen">'.QuoteHTML($trace).'</pre>';
        $body .= sprintf($T['exceptionTrace'], $trace);
      }

    $page = array('body' => $body);
  }

  OutputPage($page);