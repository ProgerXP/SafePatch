<?php
  set_time_limit(60);
  ignore_user_abort(true);
  header('Content-Type: text/html; charset=utf-8');

  if (function_exists('spl_autoload_register')) {
    spl_autoload_register('Autoload');
  } else {
    function __autoload($class) { Autoload($class); }
  }

    function Autoload($class) {
      if (strpbrk($class, './\\') === false) {
        $real = realpath( dirname(__FILE__)."/autoload/$class.php" );
        if ($real) { require_once $real; }
      }
    }

  function ChToRoot() { global $spRoot; chdir($spRoot); }
  register_shutdown_function('ChToRoot');

  $config = (include(dirname(__FILE__).'/config.php'));
  is_array($config) or $config = array();
  $config += array(
    'spRoot' => '../', 'spConfig' => 'config.php', 'homeURL' => '../../',
    'homeName' => '', 'mediaURL' => '../display/', 'linkTarget' => '_blank',
    'defaultLang' => 'en', 'index' => 'patches', 'sessionLength' => 3600,
    'showErrors' => false, 'trackRequests' => true, 'compressOutput' => true);
  extract($config, EXTR_SKIP);

    global $config, $spRoot, $mediaURL;
    $spRoot = rtrim(realpath($spRoot), '\\/').'/';
    $mediaURL = rtrim($mediaURL, '\\/').'/';

    $compressOutput and ob_start('ob_gzhandler');

    ini_set('display_errors', (int) $showErrors);
    error_reporting($showErrors ? -1 : 0);

  chdir($spRoot);

  $justLoadSafePatch = true;
  require 'safepatch.php';

    global $nexus;
    $nexus = new SpNexus('admin');

    global $sp;
    $sp = new SafePatch($spConfig);
    $sp->LogPhpErrors();
    $sp->Logger()->onLog[] = 'FireOnLog';

      function FireOnLog(&$msg, array &$extra, &$canLog, SpLog $log) {
        global $nexus;
        return $nexus->Fire('on log', array(&$msg, &$extra, &$canLog, $log));
      }

  require 'utils.php';
  require 'events.php';

  global $lang;
  if ($lang = &$_REQUEST['lang']) {
    isset($_GET['lang']) and Cookie('language', $lang);
  } elseif (($lang = Cookie('language')) == false) {
    $lang = DetectUserLang();
  }

    is_file("lang/$lang.php") or $lang = $defaultLang;
    is_file("lang/$lang.php") or $lang = 'en.php';

    global $allT;
    SetLangFrom( include("lang/$lang.php") );
    setlocale(LC_ALL, $allT['locale']);

  $trackRequests and TrackRequest();

  foreach (scandir('admin/startup') as $file) {
    if (substr($file, -4) === '.php') { include_once "startup/$file"; }
  }