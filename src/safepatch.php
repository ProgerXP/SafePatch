<?php
/*
  The SafePatch Project     http://safepatch.i-forge.net

  PHP implementation
  under BSD License         http://www.opensource.org/licenses/bsd-license.php

  by Proger_XP              http://i-forge.net/me
*/

define('SafePatchVersion', 1.0);
define('SafePatchBuild', (int) substr('$Rev: 43 $', 5, -1));
define('SafePatchHomePage', 'http://safepatch.i-forge.net/');

class ESafePatch extends Exception {          // general errors.
  public $srcObj;

  function __construct($msg, $obj = null) {
    parent::__construct($msg);
    $this->srcObj = $obj;
  }
}

  class ESpPatchFile extends ESafePatch { }   // errors regarding patch file loading.
  class ESpPatching extends ESafePatch { }    // patching process error.
    class ESpDoublePatching extends ESpPatching { }
  class ESpOperation extends ESafePatch { }   // error while performing a patch operation.
  class ESpCommit extends ESafePatch { }      // error while saving patched/reverted changes.

class SpUtils {
  static function ExpandPath($path, $cwd = null) {
    if (!is_string($path)) { return; }

      $cwd === null and $cwd = getcwd();
      $cwd = rtrim($cwd, '\\/');

    $firstIsSlash = strpbrk(@$path[0], '\\/');
    if ($path === '' or (!$firstIsSlash and @$path[1] !== ':')) {
      $path = "$cwd/$path";
    } elseif ($firstIsSlash and @$cwd[1] === ':') {
      // when a drive is specified in CWD root \ or /) refers to its root.
      $path = substr($cwd, 0, 2).$path;
    }

    $path = strtr($path, '\\', '/');

      if ($path !== '' and ($path[0] === '/' or @$path[1] === ':')) {
        list($prefix, $path) = explode('/', $path, 2);
        $prefix .= '/';
      } else {
        $prefix = '';
      }

    $expanded = array();
    foreach (explode('/', $path) as $dir) {
      if ($dir === '..') {
        array_pop($expanded);
      } elseif ($dir !== '' and $dir !== '.') {
        $expanded[] = $dir;
      }
    }

    return $prefix.join('/', $expanded);
  }

    static function ExpandPathDelim($path, $cwd = null) {
      return rtrim(self::ExpandPath($path, $cwd), '\\/').'/';
    }

  static function EnsureClass($cls, $parentClass = null, $type = '') {
    if (ltrim($cls, 'a..zA..Z0..9_') !== '' or !class_exists($cls)) {
      $type === '' or $type .= ' ';
      throw new ESafePatch("$type'$cls' isn't defined.");
    }

    if ($parentClass and !is_subclass_of($cls, $parentClass)) {
      $type === '' or $type .= ' ';
      throw new ESafePatch("$type'$cls' must inherit from $parentClass.");
    }

    return $cls;
  }

  static function EnsureDirExists($dir) {
    is_dir($dir) or mkdir($dir, 0775, true);
    if (!is_dir($dir)) { throw new ESafePatch("Cannot create directory $dir."); }
  }

    static function MkDirOf($file) { self::EnsureDirExists( dirname($file) ); }

  static function ExtOf($file) {
    $ext = strrchr($file, '.');
    return ltrim($ext, 'a..zA..Z0..9_') === '' ? null : ".$ext";
  }

  static function RemoveEmptyDirs($path) {
    $paths = explode('/', strtr($path, '\\', '/'));
    while (@rmdir( join('/', $paths) )) { array_pop($paths); }
  }

  static function VarDump($var) {
    ob_start();
    // var_export() doesn't handle recursion.
    is_scalar($var) ? var_export($var) : var_dump($var);
    return trim(ob_get_clean(), "\r\n");
  }

  static function FilterIdenticalObjectsFrom($array) {
    $id = uniqid();

    foreach ($array as &$obj) {
      if (is_object($obj)) {
        if (isset($obj->{"_SP_obj_ID_$id"})) {
          $obj = null;
        } else {
          $obj->{"_SP_obj_ID_$id"} = true;
        }
      }
    }

    return $array;
  }

  static function GetFilesRecursive($dir, $omitDirName = true) {
    $dir = rtrim($dir, '\\/');
    $omitDirName === true and $omitDirName = strlen($dir) + 1;

    $files = array();

    if (is_dir($dir)) {
      foreach (scandir($dir) as $file) {
        if (is_dir("$dir/$file")) {
          if ($file != '.' and $file != '..') {
            $files = array_merge( $files, self::GetFilesRecursive("$dir/$file", $omitDirName) );
          }
        } else {
          $files[] = substr("$dir/$file", $omitDirName);
        }
      }
    }

    return $files;
  }
}

abstract class SpBase {
  protected $config;

  function SetDefaultConfig() { $this->Config(array()); }

  function Config(array $new = null) {
    $new === null or $this->config = $this->NormalizeConfig($new);
    return $this->config;
  }

    function NormalizeConfig(array $config) { return $config; }
    function Option($opt) { return @$this->config[$opt]; }
}

class SafePatch extends SpBase {
  public $lock;                   // SpLock.
  public $state;                  // SpState.

  protected $nexus, $log;

  static function Root() { return rtrim(strtr( dirname(__FILE__), '\\', '/' ), '\\/').'/'; }

  static function RelFrom($path, $base) {
    $base = rtrim($base, '\\/').'/';
    if (substr($path, 0, strlen($base)) === $base) {
      return substr($path, strlen($base));
    } else {
      throw new ESafePatch("Cannot convert absolute path [$path] to relative".
                           " using [$base] as base path.");
    }
  }

  function __construct($config = null) {
    $this->nexus = new SpNexus('core');
    $this->lock = new SpLock('');

    $this->state = new SpState('');
    $this->state->log = &$this->log;

    $this->SetDefaultConfig();
    $this->LoadConfig($config);
  }

    function LoadConfig($config = null) {
      $config === null and $config = self::Root().'config.php';

      if (is_array($config)) {
        $this->Config($config);
      } else {
        $res = include($config);

        if (!is_array($res)) {
          throw new ESafePatch("Cannot load config from [$config] - array result".
                               ' expected but got '.SpUtils::VarDump($res), $this);
        }

        $this->Config($res);
      }
    }

    function NormalizeConfig(array $config) {
      $config += array('spRoot' => self::Root(),
                       'basePath' => '..', 'ignore' => array(), 'ignorePatchFN' => '.-',
                       'logType' => 'default', 'onError' => 'skip', 'addComments' => array());

        $config['spRoot'] = SpUtils::ExpandPathDelim($config['spRoot'], self::Root());
        $config['basePath'] = SpUtils::ExpandPathDelim($config['basePath'], $config['spRoot']);

        if (!$this->nexus->HasClass('log', $config['logType'])) {
          $config['logType'] = 'default';
          $this->Log("Unknown logType $config[logType] - using default.");
        }

        if (!in_array($config['onError'], array('skip', 'abandon', 'die'))) {
          $config['onError'] = 'skip';
          $this->Log("Unknown onError $config[onError] - using 'skip'.");
        }

      return parent::NormalizeConfig($config);
    }

  function LogPhpErrors() {
    set_exception_handler(array($this, 'OnPhpError'));
    register_shutdown_function(array($this, 'CheckFatalPhpError'));
  }

    function OnPhpError($e) {
      $info = array('file' => $e->getFile(), 'line' => $e->getLine(), 'trace' => $e->getTrace());
      $this->Log('PHP error! '.$e->getMessage(), array($this, $info));
      throw $e;
    }

    // as suggested by periklis in PHP doc's comments: http://www.php.net/manual/en/function.set-error-handler.php#99193
    function CheckFatalPhpError() {
      $error = error_get_last();

      $ignore = E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE | E_STRICT;
      defined('E_DEPRECATED') and $ignore |= E_DEPRECATED;
      defined('E_USER_DEPRECATED') and $ignore |= E_USER_DEPRECATED;

      if ($error and ($error['type'] & $ignore) == 0) {
        try {
          $msg = $error['message'];
          unset($error['message']);
          $this->Log('Fatal PHP error! '.$msg, array($this, $error));
        } catch (Exception $e) { }
      }
    }

  function Config(array $new = null) {
    if ($new !== null and $this->lock->IsSelfLocked()) {
      throw new ESafePatch('Config can\'t be changed while holding a lock.', $this);
    }

    $res = parent::Config($new);

      if ($new !== null) {
        $cls = $this->nexus->GetClass('log', 'SpLog', $this->config['logType']);
        $this->log = new $cls($this->config);

        $this->state->Path($this->config['spRoot'].'state/');
        $this->lock->File($this->state->FileOf('lock'));
      }

    return $res;
  }

  function Nexus() { return $this->nexus; }
  function PatchPath() { return $this->config['spRoot'].'patches/'; }
  function StateFN($file) { return $this->state->FileOf($file); }
  function OriginalFN($file) { return $this->state->OriginalFileOf($file); }
  function OriginalFiles() { return $this->state->OriginalFiles(); }
  function LastFreshenTime() { return $this->state->Freshness('lastFreshen'); }
  function Logger() { return $this->log; }

  function Log($msg, $extra = null) {
    return $this->log->Log($msg, is_object($extra) ? array($extra) : ((array) $extra));
  }

  function GetPatches($asObjects = false) {
    $ignore = $this->config['ignorePatchFN'];
    $res = array();

      $path = $this->PatchPath();
      foreach (scandir($path) as $baseName) {
        $file = $path.$baseName;

        if (strpbrk($baseName[0], $ignore) === false and is_file($file)) {
          try {
            $patch = $this->PatchByPath($file, $asObjects);
            $patch and $res[$file] = $patch;
          } catch (Exception $e) {
            $this->Log("Error loading patch from [$file]: {$e->getMessage()}");
          }
        }
      }

    return $res;
  }

    function AppliedPatches($asObjects = false) {
      $res = array();

        foreach ($this->state->AppliedPatches() as $relPatch => $file) {
          $absPatch = $this->PatchPath().$relPatch;

          if (is_file($absPatch)) {
            try {
              $patch = $this->PatchByPath($absPatch, $asObjects);
            } catch (Exception $e) {
              $this->Log("Error loading applied patch from [$file]: {$e->getMessage()}");
              $patch = null;
            }
          } else {
            $patch = new SpStatePatch($this, $relPatch);
          }

          $patch and $res[$file] = $patch;
        }

      return $res;
    }

    function PatchByPath($file, $asObject = false) {
      $loader = $this->nexus->Fire('get patch loader', array($file));

      if ($loader) {
        if ($asObject) {
          $obj = call_user_func($loader, $file);

          if ($obj) {
            if (! $obj instanceof SpPatch) {
              $ldr = SpUtils::VarDump($loader);
              throw new ESafePatch("Invalid return value of the patch loader [$ldr]: ".
                                   SpUtils::VarDump($obj), $this);
            }

            $obj->SetSP($this);
          }

          $loader = $obj;
        }

        return $loader;
      }
    }

  // returns true if patches are up-to-date or there were no patches;
  // false if couldn't get the lock; array - applied patch file names (absolute).
  function Freshen($force = false) {
    $freshness = $this->state->Freshness();

    if (!$force and filemtime($this->PatchPath()) <= $freshness['lastFreshen']) {
      $patches = array();
    } else {
      $patches = $this->GetPatches();
    }

      if (!$force) {
        foreach ($patches as $file => &$loader) {
          $time = &$freshness['patchFreshen'][ self::RelFrom($file, $this->PatchPath()) ];
          if ($time and filemtime($file) <= $time) { $loader = null; }
        }

        $patches = array_filter($patches);
      }

    if (!$patches) {
      return true;
    } elseif (!$this->lock->Lock()) {
      $this->Log('Cannot obtain patch lock ['.$this->LockFile().'] - exiting.');
      return false;
    } else {
      $applied = $this->Apply($patches);

      $freshness = $this->state->Freshness();
      $freshness['lastFreshen'] = time();
      $this->state->SaveFreshness($freshness);

      $this->lock->Unlock();
      return $applied;
    }
  }

  protected function Apply(array $patches) {
    $applied = array();

      foreach ($patches as $file => $loader) {
        $error = null;

        try {
          $patch = call_user_func($loader, $file);
        } catch (ESpPatchFile $e) {
          $from = strpos($e->getMessage(), $file) === false ? "  from [$file]" : '';
          $error = $this->Log("Error loading patch$from: ".$e->getMessage(), $e->srcObj);
        }

        if (!$error) {
          if ($patch) {
            $res = $patch->ApplyLogging($this);

            if (is_array($res)) {
              $applied[$file] = null;
            } else {
              $error = $res;
              $applied[$file] = $res;
            }
          } else {
            $error = $this->Log("File [$file] looks like a patch but can't be loaded".
                                " for some reason - skipping.");
          }
        }

        if ($error !== null and $this->BreakOnError($error)) {
          break;
        }
      }

    return $applied;
  }

    function BreakOnError($error) {
      switch ($this->config['onError']) {
      case 'abandon':
        return true;

      case 'die':
        while (ob_get_level()) { ob_end_flush(); }
        die("<h1>SafePatch error</h1>\n\n<pre>".QuoteHTML($error)."</pre>");
      }
    }
}

class SpLock {
  const StaleTimeout = 60;    // seconds

  protected $file, $hasLocked = false;
  protected $regShutdown = false;

  function __construct($file) {
    $this->file = $file;
  }

  function File($new = null) {
    if ($new !== null) {
      if ($this->IsSelfLocked()) {
        throw new ESafePatch("Attempted to change lock file name to [$new] while".
                             " holding a lock.");
      }

      $this->file = $new;
    }

    return $this->file;
  }

  function Lock() {
    if (!$this->IsSelfLocked()) {
      $this->Wait();

      if (!$this->IsLocked() and touch($this->file) and is_file($this->file)) {
        if (!$this->regShutdown) {
          register_shutdown_function(array($this, 'Unlock'));
          $this->regShutdown = true;
        }

        return $this->hasLocked = true;
      }
    }
  }

  function Unlock() {
    if ($this->hasLocked) {
      unlink($this->file) and clearstatcache();
      $this->hasLocked = false;
    }
  }

  function Wait($msec = 5000) {
    while ($this->IsLocked() and ($msec -= 500) > 0) { usleep(500000); }
  }

  function IsLocked() {
    $file = $this->file;
    // PHP manual: "PHP doesn't cache information about non-existent files".
    $isLocked = !is_file($file) ? false : (clearstatcache() or is_file($file));

    if ($isLocked and filemtime($file) + self::StaleTimeout < time()) {
      unlink($file) and clearstatcache();
      $isLocked = false;
    }

    $this->hasLocked &= $isLocked;
    return $isLocked;
  }

  function IsSelfLocked() { return $this->IsLocked() and $this->hasLocked; }
}

  class SpLockStub extends SpLock {
    protected $hasLocked = true;

    function __construct() { }
    function Lock() { return true; }
    function Unlock() { }
    function IsLocked() { return false; }
  }

class SpState {
  const DeltaSeparator = '|';

  public $log;          // SpLog.
  public $ext = '.php';

  protected $path, $freshness;

  function __construct($path) {
    $this->path = $path;
  }

  function Path($new = null) {
    $new and $this->path = rtrim($new, '\\/').'/';
    return $this->path;
  }

  function FileOf($file) {
    if (ltrim($file) === '' or strpbrk($file[0], '\\/') !== false or strpos($file, '..') !== false) {
      throw new ESafePatch("Unsafe base file name to get state path for: [$file].");
    }

    return $this->path.$file;
  }

    function OriginalFileOf($file) {
      if (ltrim($file) === '' or strpbrk($file[0], '\\/') !== false or
          strpos(strtr("/$file/", '\\', '/'), '/../') !== false) {
        throw new ESafePatch("Unsafe base file name to get original path for: [$file].");
      }

      return $this->FileOf("original/$file");
    }

    function PatchedFileOf($file, $full = true) {
      $file = 'patched/'.$file.$this->ext;
      return $full ? $this->FileOf($file) : $file;
    }

  function OriginalFiles() {
    $path = rtrim(dirname($this->OriginalFileOf('.')), '\\/');

    $files = array_flip( SpUtils::GetFilesRecursive($path) );
    foreach ($files as $rel => &$abs) { $abs = "$path/$rel"; }
    return $files;
  }

  function AppliedPatches() {
    $path = dirname($this->PatchedFileOf('')).'/';
    $extLen = -1 * strlen($this->ext);

    $res = array();

      if (is_dir($path)) {
        foreach (scandir($path) as $file) {
          if (substr($file, $extLen) === $this->ext and is_file($path.$file)) {
            $res[ substr($file, 0, $extLen) ] = $path.$file;
          }
        }
      }

    return $res;
  }

  function Freshness($field = null) {
    if (!$this->freshness) {
      $default = array('lastFreshen' => null, 'patchFreshen' => array(),
                       'fileByPatchFreshen' => array());
      $this->freshness = $this->Read('freshness'.$this->ext, $default);
    }

    return $field === null ? $this->freshness : @$this->freshness[$field];
  }

    function SaveFreshness(array $freshness) {
      $this->Write('freshness'.$this->ext, $freshness);
      $this->freshness = $freshness;
    }

  protected function Read($file, array $default = array()) {
    $file = $this->FileOf($file);
    $state = is_file($file) ? include($file) : array();

    return $state + $default;
  }

    protected function Write($file, array $state) {
      $file = $this->FileOf($file);
      SpUtils::MkDirOf($file);

      $export = "<?php\nreturn ".var_export($state, true).";?>\n";
      if (!file_put_contents($file, $export, LOCK_EX)) {
        is_writable($file) and unlink($file) and clearstatcache();
        throw new ESafePatch("Cannot write current state file [$file].");
      }
    }

  function Log($msg, $extra = null) {
    if ($this->log) {
      $this->log->Log($msg, is_object($extra) ? array($extra) : (array) $extra);
    } else {
      echo '<p><code>', get_class($this), ': ', $msg, '</code></p>';
    }
  }

  // $delta - array( 'affected.fn' => array(<pos> => <delta>, ...), ... )
  function FilesDeltaToStr(array $deltas) {
    $str = '';
    foreach ($deltas as $file => $item) { $str .= $this->FileDeltasToStr($file, $item); }
    return $str;
  }

    function FileDeltaToStr($file, array $deltas) {
      if (strpos($file, self::DeltaSeparator) !== false) {
        throw new ESafePatch("Invalid affected file name [$file] for position deltas.");
      }

      $str = '';

        foreach ($deltas as $pos => $delta) {
          $delta == 0 or $str .= self::DeltaSeparator.$file.self::DeltaSeparator.$delta.'@'.$pos;
        }

      return $str;
    }

    // $skip - relative path to the patch file.
    function AppendDelta($deltas, $skip = null) {
      $error = null;
      $written = array();

      if ("$deltas" !== '') {
        $applied = $this->AppliedPatches();

        unset($applied[$skip]);
        unset($applied[$skip.$this->ext]);

          foreach ($applied as $file) {
            $written[$file] = file_get_contents($file);

            if (!is_string($written[$file]) or
                !file_put_contents($file, $deltas, FILE_APPEND | LOCK_EX)) {
              $error = "Cannot append position deltas to [$file].";
            }
          }
      }

      if ($error) {
        foreach ($written as $file => &$data) {
          file_put_contents($file, $data, LOCK_EX);
        }

        throw new ESpCommit($error);
      }
    }

  function Patched($file) {
    return $this->Read( $this->PatchedFileOf($file, false) );
  }

    function SavePatched($file, array $state) {
      $this->Write($this->PatchedFileOf($file, false), $state);
    }

  // returns array( 'rel/file.ext' => array(<pos> => <delta>, ...), ... )
  function PatchedDelta($file) {
    $file = $this->PatchedFileOf($file);
    if (is_file($file)) {
      $lines = file($file, FILE_SKIP_EMPTY_LINES | FILE_IGNORE_NEW_LINES);

      $last = array_pop($lines);
      @list($a, $b) = explode('?>', $last, 2);
      isset($b) and $last = $b;

      return $this->ParseDelta($last);
    }
  }

    function ParseDelta($deltas) {
      $res = array();

        $fn = null;
        foreach (explode(self::DeltaSeparator,  $deltas) as $item) {
          if (($item = trim($item)) !== '') {
            if ($fn === null) {
              $fn = $item;
            } else {
              list($delta, $pos) = explode('@', $item);

              if ($delta == 0 or !is_numeric($pos) or $pos < 0) {
                $this->Log("Invalid patched state pos delta item '$item' - skipping.");
              }

              $res[$fn][] = array($pos, $delta);
              $fn = null;
            }
          }
        }

      return $res;
    }
}

abstract class SpLog extends SpBase {
  // expects case-insensitive regexp.
  static $strFmtToRegExp = array('e' => '\s*\d\d?', 'd' => '\d\d?', 'j' => '\d{1,3}',
                                 'u' => '\d', 'w' => '\d', 'U' => '\d\d?',
                                 'V' => '\d\d?', 'W' => '\d\d?', 'b' => '[a-z]{1,9}',
                                 'b' => '[a-z]{1,3}', 'h' => '[a-z]{1,3}',
                                 'm' => '\d\d?', 'C' => '\d\d?', 'g' => '\d\d',
                                 'G' => '\d{4}', 'y' => '\d\d', 'Y' => '\d{4}',
                                 'H' => '\d\d?', 'I' => '\d\d?', 'l' => '\d\d?',
                                 'M' => '\d\d?', 'p' => '[AP]M', 'P' => '[ap]m',
                                 'r' => '\d\d?:\d\d?:\d\d?\s+[AP]M',
                                 'R' => '\d\d?:\d\d?', 'S' => '\d\d?',
                                 'T' => '\d\d?:\d\d?:\d\d?', 'D' => '\d\d?/\d\d?/\d\d?',
                                 'F' => '\d{4}-\d\d?-\d\d?', 's' => '\d+',
                                 /* SP extensions: */ 'o' => '\d+', 'O' => '\d+');

  public $eoln = "\n";

  // each member is a callback:  function (&$msg, array &$extra, &$canLog, SpLog $log)
  // if it returns !== null all remaining handlers are skipped; $canLog is initially
  // true - if it's false after all $onLog were called the message isn't logged.
  public $onLog = array();

  protected $messages = array();
  protected $isFiringOnLog = false;

  function __construct(array $config) {
    $this->SetDefaultConfig();
    $this->Config($config);
  }

  function __destruct() { $this->Flush(); }

  function NormalizeConfig(array $config) {
    $config += array('logFlush' => false);
    return parent::NormalizeConfig($config);
  }

  function Log($msg, array $extra = array()) {
    if ($this->isFiringOnLog) {
      $canLog = true;
    } else {
      $this->isFiringOnLog = true;
      try {
        $canLog = $this->OnLog($msg, $extra);
        $this->isFiringOnLog = false;
      } catch (Exception $e) {
        $this->isFiringOnLog = false;
        throw $e;
      }
    }

    $msg = $this->MakeMessageFrom($msg, $extra);

      if ($canLog) {
        if ($this->config['logFlush']) {
          $this->WriteMessage($msg);
        } else {
          $this->messages[] = $msg;
        }
      }

    return $msg;
  }

    function MakeMessageFrom($msg, array $extra = array()) {
      static $padding = '-- %25s  ';

      $separ = $this->eoln.str_repeat('_', 79);
      $separ2 = $this->eoln.str_repeat('-', strlen($separ));

      $date = date('G:i:s__j M Y (D)').'__';
      $header = sprintf('__ SafePatch v%.1f (R%s) ', SafePatchVersion, SafePatchBuild);
      $header = str_pad($header, strlen($separ) - strlen($this->eoln) - strlen($date), '_').
                $date.$this->eoln;

      $extra = $this->NormalizeMessageExtra($extra);
      $this->FormatMessageExtra($extra, $padding, $info, $largeInfo);

      $info = $info ? join($this->eoln, $info).$separ.$this->eoln : '';
      $large = $largeInfo ? join($separ.$this->eoln, $largeInfo).$separ.$this->eoln : '';

      return $header.trim($msg, "\r\n").$separ2.$this->eoln.$info.$large;
    }

      // gathers objects from known object classes, removes duplicated objects and reorders
      // the result so that objects come before other $extra types.
      function NormalizeMessageExtra(array $extra) {
        $objects = $other = array();

          foreach ($extra as &$obj) {
            if (is_object($obj)) {
              $patch = $file = $operation = null;

                if ($obj instanceof SafePatch) {
                  $objects[] = $obj;
                } elseif ($obj instanceof SpPatch) {
                  $patch = $obj;
                } elseif ($obj instanceof SpAffectedFile) {
                  $file = $obj;
                } elseif ($obj instanceof SpOperation) {
                  $operation = $obj;
                }

                $operation and $file = $operation->File();
                $file and $patch = $file->patch;

              $objects[] = $patch;
              $objects[] = $file;
              $objects[] = $operation;
            } else {
              $other[] = &$obj;
            }
          }

        $objects = SpUtils::FilterIdenticalObjectsFrom($objects);
        return array_merge(array_filter($objects), $other);
      }

      function FormatMessageExtra(array $extra, $padding, &$info, &$largeInfo) {
        $info = array();
        $largeInfo = array();

          foreach ($extra as &$obj) {
            if (is_object($obj)) {
              if ($obj instanceof SafePatch) {
                $info[] = sprintf($padding, 'Base path:').$obj->Option('basePath');
                $info[] = sprintf($padding, 'SafePatch root:').$obj->Option('spRoot');
              } elseif ($obj instanceof SpPatch) {
                $info[] = sprintf($padding, 'Patch:').$obj->file;

                foreach ($obj->Info() as $field => $value) {
                  if ($field !== 'skip' and trim($value) !== '') {
                    $field === 'date' and $value = date('d F Y', $value);

                    $field = preg_replace('/[A-Z]/', ' \0', $field);
                    $value = strtr($value, "\r\n", '  ');

                    $info[] = sprintf($padding, ucfirst(strtolower($field)).':').$value;
                  }
                }
              } elseif ($obj instanceof SpAffectedFile) {
                $info[] = sprintf($padding, 'Affected file:').$obj->fileName;
                $info[] = sprintf($padding, 'Defined on line:').($obj->lineIndex + 1);
              } elseif ($obj instanceof SpOperation) {
                $params = array_filter($obj->params);
                $params = $params ? ' ['.join(' ', array_keys($params)).']' : '';

                $info[] = sprintf($padding, 'Instruction:').$obj->Name().$params;
                $info[] = sprintf($padding, 'Defined on line:').($obj->lineIndex + 1);

                $value = &$obj->value;
                if (strpbrk($value, "\r\n") === false) {
                  $info[] = sprintf($padding, 'Value (converted):').$value;
                } else {
                  $largeInfo[] = '-- Instruction value (converted):'.$this->eoln.$value;
                }
              }
            } elseif (is_array($obj)) {
              foreach ($obj as $key => $value) {
                is_string($value) or $value = SpUtils::VarDump($value);

                if (strpbrk($value, "\r\n") === false) {
                  $info[] = sprintf($padding, "* $key:").$value;
                } else {
                  $largeInfo[] = "-- * $key:".$this->eoln.$value;
                }
              }
            } else {
              $value = is_string($obj) ? $obj : SpUtils::VarDump($obj);

              if (strpbrk($value, "\r\n") === false) {
                $info[] = '-- * '.$value;
              } else {
                $largeInfo[] = "-- *:".$this->eoln.$value;
              }
            }
          }
      }

    protected function OnLog(&$msg, array &$extra) {
      $canLog = true;

        foreach ($this->onLog as $callback) {
          SpNexus::Call($callback, array(&$msg, &$extra, &$canLog, $this));
        }

      return $canLog;
    }

  function Clear() { $this->messages = array(); }

  function Flush() {
    $this->WriteMessages($this->messages);
    $this->Clear();
  }

    protected function WriteMessages(array $msgs) {
      foreach ($msgs as $msg) { $this->WriteMessage($msg); }
    }

    abstract protected function WriteMessage($msg);


  abstract function AllWithTimes();
  function All() { return array_keys($this->AllWithTimes()); }

    protected function GetFilesIn($path, $regexp) {
      $res = array();

        foreach (SpUtils::GetFIlesRecursive($path, false) as $file) {
          if (is_file($file) and preg_match($regexp, "/$file")) { $res[] = $file; }
        }

      return $res;
    }

    protected function StrFmtToRegExp($fmt) {
      $func = array($this, 'StrFmtToRegExpCallback');
      $fmt = preg_replace_callback('/%([a-zA-Z])/', $func, preg_quote($fmt, '/'));
      return '/[\\/]'.$fmt.'$/i';
    }

      function StrFmtToRegExpCallback($match) {
        $pattern = &self::$strFmtToRegExp[$match[1]];
        return isset($pattern) ? $pattern : '.+?';
      }
}

  class SpFileLog extends SpLog {
    const DefaultMerge  = 604800;     // 1 week.
    const MinMerge      = 600;        // seconds.

    static function BasePathFrom($logPath) {
      $path = explode( '/', strtr(dirname($logPath), '\\', '/') );

        while (isset($path[0]) and strrchr($path[count($path) - 1], '%') !== false) {
          array_pop($path);
        }

      return join('/', $path).'/';
    }

    static function MaxFileOrderIn($logPath) {
      $base = strftime(dirname($logPath));

      if (is_dir($base) and $files = scandir($base)) {
        foreach ($files as &$file) { $file = (int) $file; }
        return max($files);
      } else {
        return 0;
      }
    }

    function File() {
      $file = &$this->config['_logPath'];
      $file or $file = $this->GetPathFrom($this->config);
      return $file;
    }

      protected function GetPathFrom(array $config) {
        $date = (int) (time() / $config['logMerge']) * $config['logMerge'];

        $path = $config['logPath'];

          if (strpos($path, '%o') !== false) {
            $path = str_replace('%o', self::MaxFileOrderIn($path) + 1, $path);
          }

          if (strpos($path, '%O') !== false) {
            $base = strftime(dirname($path));
            $files = is_dir($base) ? scandir(strftime($base)) : array();
            $path = str_replace('%O', max(0, count($files) - 2) + 1, $path);
          }

        return strftime($path, $date);
      }

    function NormalizeConfig(array $config) {
      $config += array('spRoot' => SafePatch::Root(), 'logPath' => 'logs/%Y-%m-%d.log',
                       'logMerge' => self::DefaultMerge);

        $path = SpUtils::ExpandPath($config['logPath'], $config['spRoot']);

          $len = strlen($config['logPath']);
          if (substr($path, -1 * $len) === $config['logPath']) {
            $base = substr($path, 0, -1 * $len);
            $path = str_replace('%', '%%', $base).substr($path, -1 * $len);
          }

          $config['logPath'] = $path;

        if ($config['logMerge'] < self::MinMerge) {
          $this->Log("Too small logMerge ($config[logMerge]) - setting to ".self::MinMerge.'.');
        }

      return parent::NormalizeConfig($config);
    }

    protected function WriteMessage($msg) {
      return $msg === '' or
             file_put_contents($this->File(), $msg.$this->eoln, LOCK_EX | FILE_APPEND) > 0;
    }

    function BasePath() { return self::BasePathFrom($this->config['logPath']); }

    function All() {
      $regexp = $this->StrFmtToRegExp($this->config['logPath']);
      return $this->GetFilesIn($this->BasePath(), $regexp);
    }

      function AllWithTimes() {
        $canPTime = function_exists('strptime');
        $files = array_flip($this->All());

          foreach ($files as $file => &$time) {
            $time = 0;

              if ($canPTime and $parsed = strptime($file, $this->config['logPath'])) {
                ++$parsed['tm_mon'];
                $parsed['tm_year'] += 1900;

                if (checkdate($parsed['tm_mon'], $parsed['tm_mday'], $parsed['tm_year'])) {
                  $time = mktime($parsed['tm_hour'], $parsed['tm_min'], $parsed['tm_sec'],
                                 $parsed['tm_mon'], $parsed['tm_mday'], $parsed['tm_year']);
                }
              }

              $time or $time = filemtime($file);
          }

        return $files;
      }
  }

    class SpDefaultLog extends SpFileLog { }

class SpNexus {
  // meant to separate events/classs for patching, admin panel, etc.; currently unused.
  protected $id;

  protected static $classes = array();    // array( 'userName' => 'className', ... )
  protected static $events = array();     // array( 'event' => array(<callback>, ...), ... )

  static function Register($group, $name, $class) {
    self::$classes[$group][$name] = $class;
  }

  static function Hook($event, $callback, $name = null) {
    $evt = &self::$events[$event];
    $evt or $evt = array();

    $name === null ? ($evt[] = $callback) : ($evt[$name] = $callback);
  }

    static function HookFirst($event, $callback) {
      $evt = &self::$events[$event];
      $evt ? array_unshift($evt, $callback) : ($evt = array($callback));
    }

  static function Unhook($event, $callback) {
    unset(self::$events[$event][ array_search($callback, self::$events[$event], true) ]);
  }

    static function UnhookBy($name, $event) {
      unset(self::$events[$event][$name]);
    }

  static function Call($callback, array $args) {
    return call_user_func_array($callback, $args);
  }

  function __construct($id) { $this->id = $id; }
  function ID() { return $this->id; }

  function HasClass($group, $name) {
    return !empty(self::$classes[$group][$name]);
  }

    function GetClass($group, $parentClass, $name) {
      $cls = self::$classes[$group][$name];
      return SpUtils::EnsureClass($cls, $parentClass, ucfirst($group));
    }

  function &Fire($event, array $args = array()) {
    $res = null;
    $evt = &self::$events[$event];

      if ($evt) {
        foreach ($evt as $callback) {
          $res = self::Call($callback, $args);
          if ($res !== null) { break; }
        }
      }

    return $res;
  }

    function HasAny($event) { return !empty(self::$events[$event]); }
}

class SpPatch {
  public $file;

  protected $sp;              // SafePatch.
  protected $info;
  protected $files;           // array of SpAffectedFile.

  static function DefaultInfo() {
    return array('skip' => null, 'caption' => 'Unnamed patch', 'author' => 'Anonymous',
                 'version' => null, 'date' => null, 'homepage' => null, 'email' => null,
                 'headComment' => null);
  }

  function __construct(SafePatch $sp = null) {
    $this->sp = $sp;
    $this->Clear();
  }

  function Clear() {
    $this->file = null;

    $this->Info(array());
    $this->files = array();
  }

  function SetSP(SafePatch $sp = null) {
    $sp and $this->sp = $sp;
    if (!$this->sp) { throw new ESafePatch('SpPatch->$sp not initialized.', $this); }
  }

  function GetSP() {
    $this->SetSP();
    return $this->sp;
  }

  // set array:   function (array $newInfo)
  // set item:    function ($infoKey, $newValue)
  // get item:    function ($infoKey)
  // get array:   function ()
  function Info($new = null, $value = null) {
    if (is_array($new)) {
      $this->info = $new + self::DefaultInfo();
      $this->info['headComment'] = trim($this->info['headComment'], "\r\n");
    } elseif (isset($value)) {
      $new === 'headComment' and $value = trim($value, "\r\n");
      $this->info[$new] = $value;
    }

    return (isset($new) and !is_array($new)) ? $this->info[$new] : $this->info;
  }

    function HeadComment($new = null) {
      return $this->Info('headComment', $new);
    }

    function AppendHeadComment($str) {
      return $this->Info('headComment', $this->info['headComment'].$str);
    }

  function AddFile(SpAffectedFile $file) {
    $file->patch = $this;
    $this->files[] = $file;
  }

    function FileCount() { return count($this->files); }
    function Files() { return $this->files; }

    function WildcardFileCount() {
      $res = 0;
      foreach ($this->Files() as $file) { $file->IsWildcard() and ++$res; }
      return $res;
    }

  function RelFile() {
    if (!$this->file) {
      throw new ESpPatching('Cannot get relative patch file name - patch was\'t loaded from a file.', $this);
    }

    return SafePatch::RelFrom($this->file, $this->GetSP()->PatchPath());
  }

    function PatchedStateFN() {
      return $this->GetSP()->state->PatchedFileOf( $this->RelFile() );
    }

  function ApplyTime() {
    $freshness = $this->sp->state->Freshness('patchFreshen');
    $time = &$freshness[$this->RelFile()];

      $stateFN = $this->PatchedStateFN();
      if (!isset($time) and is_file($stateFN)) {
        $this->Log("Patch was likely applied (state file [$stateFN] exists) but is".
                   " missing from freshness.php.");

        $time = false;
      }

    return $time;
  }

    function IsApplied() { return $this->ApplyTime() !== null; }
    function IsStateOnly() { return !is_file($this->file) and $this->IsApplied(); }

    function AppliedFiles() {
      $freshness = $this->sp->state->Freshness('fileByPatchFreshen');
      $relFile = $this->RelFile();
      return @$freshness[$relFile];
    }

  function Log($msg, $extra = null) { $this->GetSP()->Log($msg, $extra ? $extra : $this); }
  // a public function for removing state info:
  function Obliterate() { $this->RemoveState(); }

  function &Apply(SafePatch $sp = null) {
    $this->SetSP($sp);

    $stateFN = $this->PatchedStateFN();
    if (is_file($stateFN)) {
      $msg = "Patch [{$this->file}] has a state file [$stateFN] saved - it might".
             " be a leftover from some crash (then it's safe to delete) or you're".
             " attempting to double-patch (then first revert existing patch).";
      throw new ESpDoublePatching($msg, $this);
    }

    $freshness = $this->sp->state->Freshness('fileByPatchFreshen');
    $newData = &$this->PatchFiles($freshness);

    $this->SaveState($newData);
    try {
      $this->Commit($newData);
    } catch (Exception $e) {
      $this->RemoveState();
      throw $e;
    }

    return $this->DataFromCommit($newData, true);
  }

    protected function &PatchFiles(array $freshenTimes) {
      $basePath = $this->sp->Option('basePath');
      $relPatch = $this->RelFile();

      $newData = array();

        foreach ($this->files as $fileObj) {
          $paths = $fileObj->GetMatchingFiles($basePath);

          if (!$paths) {
            throw new ESpPatching("Couldn't find any matching files to patch".
                                  " [{$fileObj->fileName}] in base path [$basePath].", $fileObj);
          }

          foreach ($paths as $path) {
            if (!is_file($path)) {
              throw new ESpPatching("File to patch [$path] doesn't exist.", $fileObj);
            }

            $relPatched = SafePatch::RelFrom($path, $this->sp->Option('basePath'));

            if (empty( $freshenTimes[$relPatch][$relPatched] )) {
              $data = $this->PatchFile($path, $fileObj);
              $data === null or $newData[$path] = $data;
            }
          }
        }

      return $newData;
    }

    // returns array with keys 'orig', 'new', 'state', 'delta', 'count' (ran instruction count).
    protected function PatchFile($path, SpAffectedFile $file) {
      $orig = file_get_contents($path);
      if (!is_string($orig)) {
        throw new ESpPatching("Cannot read file to patch [$path].");
      }

      $res = $file->ApplyInstructionsTo(array($orig, $path), $this->sp);
      $res['orig'] = &$orig;

      if ($res['count'] == 0) {
        $this->sp->Log("The patch contained no instructions to apply to file [$path].", $file);
      } elseif ("$orig" === "$res[new]") {
        $this->sp->Log("File [$path] not changed by the patch - skipping writing.", $file);
      } else {
        return $res;
      }
    }

  function Revert(SafePatch $sp = null) {
    $this->SetSP($sp);

    $state = $this->sp->state->Patched( $this->RelFile() );
    $delta = $this->sp->state->PatchedDelta( $this->RelFile() );
    $newData = &$this->RevertFiles($state, $delta);

    $this->RemoveState();
    try {
      $this->Commit($newData, true);
    } catch (Exception $e) {
      $this->RollbackRemovingState();
      throw $e;
    }

    return $this->DataFromCommit($newData, true);
  }

    protected function &RevertFiles(array $states = null, array $delta = null) {
      $basePath = $this->sp->Option('basePath');
      $objByPath = array();

        foreach ($this->files as $fileObj) {
          $paths = $fileObj->GetMatchingFiles($basePath);
          foreach ($paths as $path) { $objByPath[$path] = $fileObj; }
        }

      if (!$states) {
        $states = array();

        foreach ($objByPath as $absPatched => $fileObj) {
          $states[ SafePatch::RelFrom($absPatched, $this->sp->Option('basePath')) ] = null;
        }
      }

      $newData = array();

        foreach ($states as $relPatched => $state) {
          if ($relPatched[0] !== '*') {
            $absPatched = $this->sp->Option('basePath').$relPatched;

            $fileObj = &$objByPath[$absPatched];
            if (!$fileObj and $state !== null) {
              $this->Log("Previous version of the patch file had patched [$absPatched]".
                         " and although it's not contained in the current version the".
                         " file will be reverted based on the saved state data.", $this);

              $fileObj = new SpAffectedStateFile($relPatched);
              $fileObj->patch = $this;
            }

            if ($fileObj) {
              $data = $this->RevertFile($absPatched, $fileObj, $state, @$delta[$relPatched]);
            } else {
              $data = null;
            }

            $data === null or $newData[$absPatched] = $data;
          }
        }

      return $newData;
    }

    protected function RevertFile($path, SpAffectedFile $file, array $state = null, array $delta = null) {
      $orig = file_get_contents($path);

      if (is_string($orig)) {
        $res = $file->RevertInstructionsOn(array($orig, $path), $this->sp, $state, $delta);
        $res['orig'] = &$orig;

        if ("$orig" === "$res[new]") {
          $this->sp->Log("File [$path] not changed after reverting it - skipping writing.", $file);
        } else {
          return $res;
        }
      } else {
        $this->Log("Cannot read file to revert [$path].");
      }
    }

  // $newData = array( 'destFile' => <file>, ... ), where <file> is array:
  // 'orig' => <str>, 'new' => <str>, 'delta' => array(<pos> => <delta>, ...)
  protected function Commit(array &$newData, $isReverting = false) {
    foreach ($newData as $file => &$data) {
      if (!is_writable($file) or !is_file($file)) {
        throw new ESpCommit("File to commit [$file] is not writable.", $this);
      }
    }

    $error = null;
    $written = array();
    $basePath = $this->sp->Option('basePath');
    $delta = '';

      foreach ($newData as $file => &$data) {
        $relFile = substr($file, strlen($basePath));

        if (!$error and substr($file, 0, strlen($basePath)) !== $basePath) {
          $error = "Attempted to commit file [$file] that is outside".
                   " of the base path [$basePath].";
        }

        if (!$error and !$isReverting) {
          $origFN = $this->sp->state->OriginalFileOf($relFile);
          SpUtils::MkDirOf($origFN);

          if (!is_file($origFN) and (!copy($file, $origFN) or !is_file($origFN))) {
            $error = "Cannot make original copy of the file being patched [$file]".
                     " in [$origFN].";
          }
        }

        if (!$error) {
          $orig = file_get_contents($file);
          if (is_string($orig) and $orig === $data['orig']) {
            $written[$file] = $orig;
          } else {
            $action = $isReverting ? 'reverted' : 'patched';
            $error = "File [$file] has changed while it was being $action.";
          }
        }

        if (!$error and
            strlen($data['new']) != file_put_contents($file, $data['new'], LOCK_EX)) {
          $error = "Cannot commit data to [$file].";
        }

        if (!$error and $data['delta']) {
          try {
            $delta .= $this->sp->state->FileDeltaToStr($relFile, $data['delta']);
          } catch (Exception $e) {
            $error = "FileDeltaToStr() on Commit(): {$e->getMessage()}";
          }
        }

        if ($error) { break; }
      }

    if (!$error) {
      try {
        $this->sp->state->AppendDelta($delta, $this->RelFile());
      } catch (Exception $e) {
        $action = $isReverting ? 'reverted' : 'patched';
        $error = "Commit $action deltas: {$e->getMessage()}";
      }
    }

    if ($error) {
      foreach ($written as $file => &$data) {
        file_put_contents($file, $data, LOCK_EX);
      }

      throw new ESpCommit($error, $this);
    }
  }

  protected function SaveState(array &$files) {
    try {
      $state = $this->GetSP()->state;

      $states = array('*patch' => $this->info);
      $freshness = $state->Freshness();

        $relPatch = $this->RelFile();
        foreach ($files as $path => &$data) {
          $relPatched = SafePatch::RelFrom($path, $this->sp->Option('basePath'));

          $freshness['patchFreshen'][$relPatch] = time();
          $freshness['fileByPatchFreshen'][$relPatch][$relPatched] = time();

          $states[$relPatched] = $data['state'];
        }

      $state->SavePatched($this->RelFile(), $states);
      $state->SaveFreshness($freshness);
    } catch (Exception $e) {
      $this->RemoveState();
      throw $e;
    }
  }

  protected function RemoveState() {
    try {
      $state = $this->GetSP()->state;

      $stateFN = $this->PatchedStateFN();
      is_file("$stateFN-") and unlink("$stateFN-");
      is_file($stateFN) and rename($stateFN, "$stateFN-");

      clearstatcache();
      if (is_file($stateFN)) {
        throw new ESpCommit("Cannot rename patched state [$stateFN] to [$stateFN-].");
      }

      $freshness = $state->Freshness();

        $patchFN = $this->RelFile();
        unset($freshness['lastFreshen']);
        unset($freshness['patchFreshen'][$patchFN]);
        unset($freshness['fileByPatchFreshen'][$patchFN]);

      $state->SaveFreshness($freshness);
    } catch (Exception $e) {
      $this->RollbackRemovingState();
      throw $e;
    }
  }

    // this doesn't restore freshness because it doesn't seem important but adds complexity.
    protected function RollbackRemovingState() {
      if ($this->file) {
        $stateFN = $this->PatchedStateFN();
        clearstatcache();
        is_file("$stateFN-") and rename("$stateFN-", $stateFN) and clearstatcache();
      }
    }

  function Diff(SafePatch $sp = null) {
    $this->SetSP($sp);

    try {
      if ($this->IsApplied()) {
        $state = $this->sp->state->Patched( $this->RelFile() );
        $delta = $this->sp->state->PatchedDelta( $this->RelFile() );
        $newData = &$this->RevertFiles($state, $delta);
      } else {
        $freshness = $this->sp->state->Freshness('fileByPatchFreshen');
        $newData = &$this->PatchFiles($freshness);
      }

      return $this->DataFromCommit($newData);
    } catch (ESafePatch $e) {
      return $this->Log("Error diffing patch [{$this->file}]: ".$e->getMessage(), $e->srcObj);
    } catch (Exception $e) {
      $action = $func[0] === 'A' ? 'applying' : 'reverting';
      return $this->Log('Exception <'.get_class($e)."> while diffing".
                        " patch [{$this->file}]: ".$e->getMessage());
    }
  }

    protected function &DataFromCommit(array $newData, $origAndNew = false) {
      $origAndNew === true and $origAndNew = array('orig' => 1, 'new' => 1);

        foreach ($newData as $affected => &$data) {
          $data = $origAndNew ? array_intersect_key($data, $origAndNew) : $data['new'];
        }

      return $newData;
    }

  function ApplyLogging(SafePatch $sp = null) {
    if (!$this->FileCount()) {
      // this might happen if patch contains no files/instructions.
      return $this->Log("Patch file [{$this->file}] is empty - skipping.");
    } else {
      return $this->PerformLogging('Apply', $sp);
    }
  }

  function RevertLogging(SafePatch $sp = null) {
    return $this->PerformLogging('Revert', $sp);
  }

    function PerformLogging($func, SafePatch $sp = null) {
      try {
        $files = $res = $this->$func($sp);

          $count = count($files);
          $files and $files = array_combine(range(1, $count), array_keys($files));

          $action = $func[0] === 'A' ? 'Applied' : 'Reverted';
          $s = $count == 1 ? '' : 's';
          $this->Log("$action patch [{$this->file}] - written $count file$s.",
                     array($this, 'files' => $files));
      } catch (ESafePatch $e) {
        $res = $this->Log($e->getMessage(), $e->srcObj);
      } catch (Exception $e) {
        $action = $func[0] === 'A' ? 'applying' : 'reverting';
        $res = $this->Log('Exception <'.get_class($e)."> while $action".
                          " patch [{$this->file}]: ".$e->getMessage());
      }

      return $res;
    }
}

  // used when a patch is missing from SP_ROOT/patches/ but is listed in previously
  // saved patch state info (state/patched/); see SafePatch->AppliedPatches().
  class SpStatePatch extends SpPatch {
    function __construct(SafePatch $sp, $file) {
      parent::__construct($sp);

      $this->file = $sp->PatchPath().$file;
      $this->LoadFromState($sp->state->Patched($file));
    }

    function LoadFromState(array $states) {
      $info = &$states['*patch'];
      $this->Info( is_array($info) ? $info : array() );

      foreach ($states as $relPatched => $state) {
        if ($relPatched[0] !== '*') {
          $absPatched = $this->sp->Option('basePath').$relPatched;
          $this->AddFile( new SpAffectedStateFile($relPatched) );
        }
      }
    }
  }

class SpAffectedFile {
  public $patch;            // SpPatch.
  public $lineIndex = -1;   // 0-based; -1 means "unknown".
  public $fileName;         // relative, with possible wildcards - as appears in the patch file.

  protected $instructions = array();

  function __construct($fileName) {
    $this->fileName = $fileName;
  }

  function AddInstruction($operation, $value, $lineIndex = -1) {
    $params = $this->ParseParams($operation);
    $this->instructions[] = compact('operation', 'params', 'value', 'lineIndex');
  }

    function &ParseParams($str) {
      $res = array();

        foreach (explode(' ', $str) as $param) {
          if ($param !== '') {
            $key = strtok($param, ':');
            $value = strtok(null);

            $value === false ? ($res[strtolower($param)] = true)
                             : ($res[strtolower($key)] = $value);
          }
        }

      return $res;
    }

    function LastInstruction($operation = null, $value = null, $lineIndex = -1) {
      if ($this->instructions) {
        if (isset($value)) {
          array_pop($this->instructions);
          $this->AddInstruction($operation, $value, $lineIndex);
        }

        return $this->instructions[count($this->instructions) - 1];
      }
    }

  function Instructions() { return $this->instructions; }
  function IsWildcard() { return strpbrk($this->fileName, '*?') !== false; }

  function GetMatchingFiles($basePath) {
    $fn = $this->fileName;

      if ($fn[0] === '/' or $fn[0] === '\\' or strrchr($fn, ':') !== false) {
        throw new ESpPatching("File name to patch [$fn] is absolute while it must".
                              " be relative.", $this);
      }

      $fn = rtrim($basePath, '\\/')."/$fn";

    if (!$this->IsWildcard()) {
      return is_file($fn) ? array($fn) : array();
    } else {
      // glob() uses '[charclass]' which can't be escaped using '\' (at least not on Windows).
      if (strrchr($fn, '[') !== false) {
        throw new ESpPatching("Due to PHP glitch if file name to patch [$fn] contains a".
                              " wildcard it can't contain opening square bracket ('[').", $this);
      }

      $files = glob($fn, GLOB_MARK | GLOB_NOESCAPE);

        foreach ($files as &$file) {
          if ($file[strlen($file) - 1] === '/' or !is_file($file)) { $file = null; }
        }

      return array_filter($files);
    }
  }

  function ApplyInstructionsTo($target, SafePatch $sp) {
    list($str, $path) = ((array) $target) + array(null, null);

    $context = new SpContext($this, $sp, $str);
    $context->destFile = $path;
    $context->AddOperationsFromInstructions($this->instructions);

    $state = $context->PatchAll();
    return array('new' => &$context->outStr, 'state' => &$state,
                 'delta' => $context->outPosDelta,
                 'count' => count($context->operations));
  }

  function RevertInstructionsOn($target, SafePatch $sp, array $state = null, array $delta = null) {
    list($str, $path) = ((array) $target) + array(null, null);

    $context = new SpContext($this, $sp, $str);
    $context->destFile = $path;

    if ($state === null) {
      $context->AddOperationsFromInstructions($this->instructions);
      $context->state = $context->MakeState();
    } else {
      $context->SetStateFrom($state);
      $delta and $context->inPosDelta = $delta;
    }

    $context->RevertAll();
    return array('new' => $context->outStr, 'delta' => $context->outPosDelta);
  }
}

  // used when a file is missing from the current patch version but is listed
  // in previously saved patch state info; see SpPatch->RevertFiles().
  class SpAffectedStateFile extends SpAffectedFile { }

class SpContext {
  public $file;             // SpAffectedFile.
  public $destFile;         // null or full path to the file being patched.
  public $sp, $nexus;

  public $inStr, $outStr;
  public $operations;
  public $state;            // array.

  // null or array of array of matched pockets of form array('substr', <pos>);
  // example: array( array(array('pct0', 0), array('pct1', 5)),
  //                 array(array('pct0 of second occurrence', 23), ...), ... )
  public $pos;
  public $posState;       // null, 'found', 'skip', 'try'.
  public $posMbString;
  // Note that these two are array but have different formats:
  // $inPosDelta = array( array(<pos>, <delta>), ... )
  // $outPosDelta = array(<pos> => <delta>, ...)
  public $inPosDelta, $outPosDelta;

  protected $curOp, $posUsed, $posVars;
  protected $posSubstIndex;

  function __construct(SpAffectedFile $file, SafePatch $sp, $str) {
    $this->Clear();

    $this->file = $file;
    $this->sp = $sp;
    $this->nexus = $sp->Nexus();

    $this->inStr = &$str;
    $this->outStr = $str;
  }

  function Clear() {
    $this->operations = array();
    $this->state = array();
    $this->inPosDelta = $this->outPosDelta = array();

    $this->curOp = 0;
    $this->ResetPos();
  }

  function Log($msg, $extra = null) {
    $extra = (isset($extra) and !is_object($extra)) ? ((array) $extra) : array($extra);
    $extra[] = $this->file;
    $this->sp->Log($msg, $extra);
  }

  function AddOperationsFromInstructions(array $instructions) {
    foreach ($instructions as $inst) {
      $opCls = $this->sp->Nexus()->Fire('get operation class', array(&$inst['params']));

      SpUtils::EnsureClass($opCls, 'SpOperation', 'Operation');
      $op = new $opCls($this->sp->Config(), $this);

      $op->lineIndex = $inst['lineIndex'];
      $op->params = $inst['params'];

      // setting a meaningful value beforehand - it'll be shown if SetValueFiltering() errors.
      $op->value = $inst['value'];
      $op->SetValueFiltering($inst['value']);

      $this->operations[] = $op;
    }
  }

  function PatchAll() {
    $states = array();

      for (; $op = @$this->operations[$this->curOp]; ++$this->curOp) {
        $state = $op->Patch();
        $states[] = array(get_class($op), $op->params, $state);
      }

    $this->ValidateOnFinish();
    return $states;
  }

    function ValidateOnFinish() {
      $state = $this->posState;
      if (($state === 'found' and !$this->WasPosUsed()) or $state === 'try') {
        throw new ESpPatching('Orphan FIND at the end of instruction block.', $this->file);
      }
    }

  function RevertAll() {
    for (; $op = @$this->operations[$this->curOp]; ++$this->curOp) {
      $op->Revert($op->state);
    }
  }

  function MakeState() {
    $state = array();

      foreach ($this->operations as $op) {
        $opCls = $this->sp->Nexus()->Fire('get operation class', array(&$op->params));
        SpUtils::EnsureClass($opCls, 'SpOperation', 'Operation');

        $state[] = array($opCls, $op->params, null);
      }

    return $state;
  }

    function SetStateFrom(array $states) {
      $this->Clear();

      foreach ($states as $key => $item) {
        if (is_int($key) and $item) {
          list($cls, $params, $state) = $item;

          SpUtils::EnsureClass($cls, 'SpOperation', 'Operation');
          $op = new $cls($this->sp->Config(), $this);

          $op->params = $params;
          $op->state = $state;

          $this->operations[] = $op;
        }
      }
    }

  function ResetPos() {
    $this->pos = $this->posVars = array();
    $this->posState = null;
    $this->posMbString = false;

    $this->MarkPosAsUsed(false);
  }

    function SetPos($state, array $pos = null, $mbstring = null) {
      isset($pos) and $this->pos = $pos;
      ksort($this->pos, SORT_NUMERIC);

      $this->posState = $state;
      $mbstring === null or $this->posMbString = $mbstring;
    }

  function AdjustPosAt($atPos, $delta) {
    if ($delta != 0) {
      $value = &$this->outPosDelta[$atPos];
      $value += $delta;

      foreach ($this->posVars as &$var) { $var >= $atPos and $var += $delta; }
    }
  }

    function AdjustPosVar(&$var) {
      if (!is_scalar($var)) {
        throw new ESafePatch('Wrong $var type ('.gettype($var).') for SpContext->AdjustPosVar(): '.
                             SpUtils::VarDump($var));
      }

      $this->posVars[] = &$var;
    }

  function AdjustInPos(&$var) {
    $var = $this->InPosDeltaAt($var, $this->inPosDelta);
  }

    function AdjustedInPos($var) {
      return $this->InPosDeltaAt($var, $this->inPosDelta);
    }

    function InPosDeltaAt($atPos, array $deltas) {
      foreach ($deltas as $item) { $atPos >= $item[0] and $atPos += $item[1]; }
      return $atPos;
    }

  function AdjustOutPos(&$var) {
    $var += $this->OutPosDeltaAt($var, $this->outPosDelta);
  }

    function AdjustedOutPos($var) {
      return $var + $this->OutPosDeltaAt($var, $this->outPosDelta);
    }

    function OutPosDeltaAt($atPos, array $deltas) {
      $sum = null;
      foreach ($deltas as $pos => $delta) { $atPos >= $pos and $sum += $delta; }
      return $sum;
    }

  function OperationAt($delta = 0) {
    return @$this->operations[$this->curOp + $delta];
  }

  function OperationIndex() { return $this->curOp; }
  function MarkPosAsUsed($state = true) { $this->posUsed = $state; }
  function WasPosUsed() { return $this->posUsed; }

  function PosSubstitutions($posIndex, $str, $utf8) {
    if (!isset($this->pos[$posIndex])) {
      throw new ESpOperation("SpContext->PosSubstitutions(): position index".
                             " $posIndex doesn't exist.");
    }

    if (strrchr($str, '\\') !== false) {
      $this->posSubstIndex = $posIndex;
      $utf8 and $utf8 = 'u';
      $str = preg_replace_callback('~(\\\\+)(\d+)~'.$utf8, array($this, 'PosSubstCallback'), $str);

      if ($e = (preg_last_error()) != 0) {
        throw new ESpOperation("SpContext->PosSubstitutions(): PCRE error #$e.");
      }
    }

    return $str;
  }

    function PosSubstCallback($match) {
      return strlen($match[1]) % 2 == 0 ? substr($match[1], strlen($match[1]) / 2)
                                        : $this->pos[$this->posSubstIndex][$match[2]][0];
    }
}

abstract class SpOperation extends SpBase {
  static $std = array('find' => 'SpOpFind', 'or' => 'SpOpOr', 'add' => 'SpOpAdd',
                      'replace' => 'SpOpReplace');

  public $lineIndex = -1;
  public $params, $value;
  public $state;              // set by Patch().

  protected $name = '???';    // to be overriden.
  protected $finds = false;   // to be overriden.
  protected $alters = false;  // to be overriden.

  protected $context;

  static function GetStdClass(array &$params) {
    foreach (self::$std as $name => $class) {
      if (!empty($params[$name])) { return $class; }
    }
  }

  function __construct(array $config, SpContext $context) {
    $this->context = $context;

    $this->SetDefaultConfig();
    $this->Config($config);
  }

  function SetValueFiltering($value) {
    $this->context->nexus->Fire('filter value', array(&$value, $this));
    if (!is_string($value)) {
      $this->Error('A string is expected from \'filter value\' event but got '.
                   SpUtils::VarDump($value));
    }

    $this->value = &$value;
    return $value;
  }

  function Error($msg) { throw new ESpOperation($this->Name().": $msg", $this); }

  function Log($msg, $extra = null) {
    $extra = (isset($extra) and !is_object($extra)) ? ((array) $extra) : array($extra);
    $extra[] = $this;
    $this->context->Log($this->Name().": $msg", $extra);
  }

  function Name() { return strtoupper($this->name); }
  function Finds() { return $this->finds; }
  function Alters() { return $this->alters; }

  function File() { return $this->context->file; }
  function DestFile() { return $this->context->destFile; }

  function Has($param) { return !empty($this->params[$param]); }

  function IntParamValue($param, $default = 0) {
    $value = &$this->params[$param];

    if (isset($value)) {
      if (!is_numeric($value)) {
        $this->Error("parameter $param must be an integer, '$value' given.");
      }

      return (int) $value;
    } else {
      return $default;
    }
  }

    function IntParam($default = 0) {
      foreach ($this->params as $key => $value) {
        if (ltrim($key, '0..9') === '' and isset($value)) { return (int) $key; }
      }

      return $default;
    }

  function Patch() {
    $res = $this->Prepare($this->context);

    if ($res === null or $res) {
      $this->state = array();
      $this->DoPatch($this->context);
      return $this->state;
    }
  }

    // if returns non-null and ==false value theen the operation isn't performed.
    protected function Prepare(SpContext $context) { }

    abstract protected function DoPatch(SpContext $context);

  function Revert(array $state = null) {
    $this->state = $state;
    $this->DoRevert($this->context, $state);
  }

    // null $state means that no state info is available - the operation must use "guess mode".
    abstract protected function DoRevert(SpContext $cx, array $state = null);
}

  class SpOpFind extends SpOperation {
    public $autoRegExpChars = '/~!';

    protected $name = 'FIND';
    protected $finds = true;

    protected function Prepare(SpContext $cx) {
      switch ($cx->posState) {
      case null:      return true;
      case 'found':   $cx->WasPosUsed() or $this->Error('multiple consequent FINDs.');
      case 'skip':    return true;
      case 'try':     return true;
      }
    }

    protected function DoPatch(SpContext $cx) {
      $cx->ResetPos();

      $offset = $this->IntParamValue('offset');
      if ($offset < 0) {
        $offset += $this->Has('utf8') ? mb_strlen($cx->inStr, 'utf-8') : strlen($cx->inStr);
      }

      $shift = $this->IntParamValue('shift');
      $indexes = $this->GetIndexes($maxIndex);

      $offsets = array();

        do {
          $pos = $this->Locate($cx, $offset, $shift);
          if ($pos) {
            $offset = $pos[0][1] + 1;
            $offsets[] = $pos;
          }
        } while ( $pos and ($maxIndex === false or !isset($offsets[$maxIndex])) );

      $found = false;
      $pos = array();

        foreach ($indexes as $index) {
          list($start, $end) = $index;

          $start < 0 and $start += count($offsets);
          $end < 0   and $end   += count($offsets);

          if ($start >= 0 and $start <= $end) {
            $found = true;
            for (; $start <= $end; ++$start) { $pos[$start] = &$offsets[$start]; }
          }
        }

      if ($found) {
        $mbstring = (!$this->Has('regexp') and $this->Has('utf8'));
        $cx->SetPos('found', $pos, $mbstring);
      } elseif ($this->Has('try')) {
        $cx->SetPos('try');
      } else {
        foreach ($indexes as &$one) { $one = ++$one[0].'..'.++$one[1]; }
        $indexes = join(', ', $indexes);
        $indexes = $indexes === '1..0' ? '' : " at any of [$indexes] indexes";

        if ($indexes === '') {
          $offsets = '';
        } elseif ($offsets) {
          foreach ($offsets as &$one) {
            $one = $one[1].':'.($this->value === $one[0] ? '' : $one[0]);
          }

          $offsets = " - found it on [".join(', ', $offsets)."] offsets";
        } else {
          $offsets = ' - found no occurrences';
        }

        $type = $this->Has('regexp') ? 'regular expression' : 'substring';
        $this->Error("cannot locate given $type$indexes$offsets.");
      }
    }

      function GetIndexes(&$maxIndex) {
        $maxIndex = false;
        $res = array(array(0, -1));

        if ($this->Has('last')) {
          $res = array(array(-1, -1));
        } elseif (!$this->Has('*')) {
          foreach ($this->params as $param => $value) {
            if ($value and ltrim($param, '0..9,.-') === '') {
              $res = explode(',', $param);
              $maxIndex = 0;

                foreach ($res as &$item) {
                  @list($start, $end) = explode('..', $item);
                  isset($end) or $end = $start;

                    if (!is_numeric($start) or $start == 0 or $end == 0) {
                      $which = (is_numeric($start) and $start != 0) ? 'end' : 'start';
                      $value = (is_numeric($start) and $start != 0) ? $end  : $start;

                      $this->Error("non-numeric or zero $which value '$value' of".
                                   " the given search index '$param'.");
                    }

                  $start > 0 and --$start;
                  $end > 0   and --$end;

                  $maxIndex = $start < 0 ? false : max($maxIndex, $start);
                  $maxIndex = $end < 0   ? false : max($maxIndex, $end);

                    if (($start < 0) == ($end < 0) and $start > $end) {
                      ++$start;
                      ++$end;
                      $this->Error("start search index $start is greater than end".
                                   " index $end of the given index '$param'.");
                    }

                  $item = array($start, $end);
                }

              break;
            }
          }
        }

        return $res;
      }

      protected function Locate(SpContext $cx, $offset, $shift) {
        $res = null;

          $isAnyCase = $this->Has('anycase');
          $isUTF8 = $this->Has('utf8');

          if ($this->Has('regexp')) {
            $regexp = $this->value;
            if (strpbrk($regexp[0], $this->autoRegExpChars) === false) {
              $regexp = '/'.str_replace('/', '\\/', $regexp).'/';
            }

              $isAnyCase and $regexp .= 'i';
              $isUTF8 and $regexp .= 'u';

            $pregRes = preg_match($regexp, $cx->inStr, $res, PREG_OFFSET_CAPTURE, $offset);

            if (is_int($pregRes)) {
              $pregRes == 1 or $res = null;
            } else {
              $tip = '';
              if (preg_last_error() == PREG_BAD_UTF8_ERROR or preg_last_error() == PREG_BAD_UTF8_OFFSET_ERROR) {
                $tip = ' - make sure the file being patched and your regexp are really'.
                       ' encoded in UTF-8';
              }

              $this->Error('preg_match() has failed; last error = '.preg_last_error()."$tip.");
            }
          } else {
            if ($isUTF8) {
              if (!function_exists('mb_strpos')) {
                $this->Error('UTF-8 mode requires php_mbstring module.');
              }

              if ($isAnyCase) {
                if (function_exists('mb_stripos')) {
                  $pos = mb_stripos($cx->inStr, $this->value, $offset, 'utf-8');
                } else {
                  $pos = mb_strpos(mb_strtolower($cx->inStr, 'utf-8'),
                                   mb_strtolower($this->value, 'utf-8'), $offset, 'utf-8');
                }
              } else {
                $pos = mb_strpos($cx->inStr, $this->value, $offset, 'utf-8');
              }
            } else {
              $pos = $isAnyCase ? stripos($cx->inStr, $this->value, $offset)
                                : strpos($cx->inStr, $this->value, $offset);
            }

            $res = $pos === false ? null : array(array($this->value, $pos));
          }

        if ($shift and $res) {
          if ($shift < 0) {
            $pos = $res[0][1];
            while ($pos > 0 and ++$shift >= 0) {
              $str = $isUTF8 ? mb_substr($cx->inStr, 0, $pos, 'utf-8')
                            : substr($cx->inStr, 0, $pos);
              $pos = ($isUTF8 ? mb_strrpos($str, "\n", 'utf-8')
                              : strrpos($str, "\n")) - 1;
            }

            $pos = max(0, $pos - 1);
            $res[0][0] = $isUTF8 ? mb_substr($cx->inStr, $pos, $res[0][1] - $pos, 'utf-8')
                                 : substr($cx->inStr, $pos, $res[0][1] - $pos);

            if ($res[0][0][0] === "\r") {
              $res[0][0] = substr($res[0][0], 1);
              $res[0][1] = $pos + 1;
            } else {
              $res[0][1] = $pos;
            }
          } else {
            $pos = $res[0][1];
            while ($pos and --$shift >= -1) {
              $pos = ($isUTF8 ? mb_strpos($cx->inStr, "\n", $pos, 'utf-8')
                              : strpos($cx->inStr, "\n", $pos)) + 1;
            }

            $res[0][0] = $isUTF8 ? mb_substr($cx->inStr, $res[0][1], $pos - $res[0][1], 'utf-8')
                                 : substr($cx->inStr, $res[0][1], $pos - $res[0][1]);
            $res[0][1] = $pos;
          }
        }

        return $res;
      }

    protected function DoRevert(SpContext $cx, array $state = null) {
      if ($state === null) {
        try {
          $this->DoPatch($cx);
        } catch (Exception $e) {
          $cx->SetPos(null);

          $class = $e instanceof ESafePatch ? '' : '<'.get_class($e).'> ';
          $this->Log("note: $class".$e->getMessage(), $e instanceof ESafePatch ? $e->srcObj : null);
        }
      }
    }
  }

    class SpOpOr extends SpOpFind {
      protected $name = 'OR';

      protected function Prepare(SpContext $cx) {
        switch ($cx->posState) {
        case null:      $this->Error('no preceding FIND.');
        case 'found':
          $cx->WasPosUsed() and $cx->SetPos('skip');
          return false;
        case 'skip':    return false;
        case 'try':     return true;
        }
      }
    }

  class SpOpAdd extends SpOperation {
    protected $name = 'ADD';
    protected $alters = true;

    function NormalizeConfig(array $config) {
      $config += array('revertStrLookAround' => 150, 'revertStrOldMatchLookAround' => 30,
                       'revertSkipIfNoOldMatch' => true);
      return parent::NormalizeConfig($config);
    }

    protected function Prepare(SpContext $cx) {
      switch ($cx->posState) {
      case null:      $this->Error('no preceding FIND.');
      case 'found':   return true;
      case 'skip':    return false;
      case 'try':     return false;
      }
    }

    protected function DoPatch(SpContext $cx) {
      $pocket = $this->GetPocketIndex();

      foreach ($cx->pos as $i => $pos) {
        $pocket < 0 and $pocket += count($pos[$i]);

          if ($pocket < 0 or $pocket >= count($pos)) {
            $count = count($pos) + 1;
            $re = $count == 1 ? 's' : 're';
            $this->Error("Pocket #$pocket (0 - whole match, 1 - first, etc.) wasn't".
                         " matched by the preceding FIND - only $count we$re.");
          }

        if ($this->Has('regexp')) {
          $new = $cx->PosSubstitutions($i, $this->value, $this->Has('utf8'));
        } else {
          $new = $this->value;
        }

        // $pos contains offsets in $cx->inStr. $state must refer to outStr.
        list($substr, $inPos) = $pos[$pocket];

        $state = $this->AlterPos($cx, $new, $substr, $inPos) +
                 array('mbstring' => $cx->posMbString, 'alterStr' => $new,
                       'origStr' => '', 'matchStr' => $substr);

        $cx->AdjustPosAt($state['alterPos'], $state['delta']);

        $cx->AdjustPosVar($state['matchPos']);
        $cx->AdjustPosVar($state['alterPos']);

        $this->state[] = $state;
      }

      $cx->MarkPosAsUsed();
    }

      function GetPocketIndex() { return $this->IntParam(); }

      protected function AlterPos(SpContext $cx, $new, $substr, $inPos) {
        $matchPos = $outPos = $cx->AdjustedOutPos($inPos);

        if ($cx->posMbString) {
          $this->Has('before') or $outPos += mb_strlen($substr, 'utf-8');

          $cx->outStr = mb_substr($cx->outStr, 0, $outPos, 'utf-8').$new.
                        mb_substr($cx->outStr, $outPos, strlen($cx->outStr), 'utf-8');

          $delta = mb_strlen($new, 'utf-8');
        } else {
          $this->Has('before') or $outPos += strlen($substr);
          $cx->outStr = substr($cx->outStr, 0, $outPos).$new.substr($cx->outStr, $outPos);

          $delta = strlen($new);
        }

        $this->Has('before') and $matchPos += $delta;
        return array('matchPos' => $matchPos, 'alterPos' => $outPos, 'delta' => $delta);
      }

    protected function DoRevert(SpContext $cx, array $states = null) {
      if ($states === null) {
        $this->StatelessRevert($cx);
      } else {
        foreach ($states as $state) {
          $cx->AdjustInPos($state['matchPos']);
          $cx->AdjustInPos($state['alterPos']);

          $this->RevertStatePos($cx, $state);
        }
      }
    }

      protected function StatelessRevert(SpContext $cx) {
        if ($cx->posState !== 'found') {
          $this->Log("previous FIND didn't succeed - skipping this instruction.");
        } elseif ($this->Has('regexp')) {
          $this->Log('regular expression requires knowledge of the original patched'.
                     ' file contents which is unavailable in stateless reverting mode.');
        } else {
          $state = array('mbstring' => $cx->posMbString, 'alterStr' => $this->value, 'origStr' => '');
          $pocket = $this->GetPocketIndex();

          foreach ($cx->pos as $pos) {
            $pocket < 0 and $pocket += count($pos[$i]);

            if (isset($pos[$pocket])) {
              list($matchStr, $matchPos) = $pos[$pocket];

              $alterPos = $matchPos;
              if ($this->Has('before')) {
                $alterPos -= $state['mbstring'] ? mb_strlen($this->value, 'utf-8') : strlen($this->value);
              } else {
                $alterPos += $state['mbstring'] ? mb_strlen($matchStr, 'utf-8') : strlen($matchStr);
              }

              $this->RevertStatePos( $cx, compact('matchStr', 'matchPos', 'alterPos') + $state );
            } else {
              $count = count($pos) + 1;
              $this->Log("pocket #$pocket doesn't exist, only $count captured.");
            }
          }
        }
      }

      // $state has keys: mbstring, matchStr, matchPos, alterStr, alterPos.
      protected function RevertStatePos(SpContext $cx, array $state) {
        $cx->AdjustOutPos($state['alterPos']);
        $cx->AdjustOutPos($state['matchPos']);

        extract($state, EXTR_SKIP);

        $state['lookAround'] = $this->config['revertStrLookAround'];
        $state['oldMatchLookAround'] = $this->config['revertStrOldMatchLookAround'];

        $alterPos = $this->FindStatePos($alterPos, $alterStr, $mbstring);
        if ($alterPos === null) {
          $this->Log("cannot locate added substring '$alterStr' anywhere near byte offset".
                     " $state[alterPos] - skipping.", array($state));
        } else {
          if ($alterPos === $state['alterPos']) {
            $delta = 0;
          } else {
            $delta = $alterPos - $state['alterPos'];
            $matchPos += $delta;

            $this->Log("patched file was changed after patching and added substring '$alterStr' is now".
                       ' shifted '.abs($delta).' bytes '.($delta < 0 ? 'back.' : 'further.'), array($state));
          }

          $matchPos = $this->FindStatePos($matchPos, $matchStr, $mbstring, 'revertStrOldMatchLookAround');
          if ($matchPos === null) {
            $skip = $this->config['revertSkipIfNoOldMatch'];
            $skipping = $skip ? 'skipping' : 'reverting anyway';

            $matchPos = $state['matchPos'] + $delta;
            $this->Log("old match is no more around the added text ('$alterStr' at $alterPos) -".
                       " expected to be located near $matchPos; $skipping.", array($state));

            $skip and $matchPos = null;
          } elseif ($matchPos !== $state['matchPos'] + $delta) {
            $matchDelta = $matchPos - $state['matchPos'];
            $shift = abs($matchDelta).' bytes '.($matchDelta < 0 ? 'back' : 'further');

            $this->Log("old match was moved $shift relative to the added text ('$alterStr' at $alterPos)".
                       " but it's within the look-around distance ($state[oldMatchLookAround]) so".
                       " reverting anyway.", array($state));
          }

          if ($matchPos !== null) {
            $this->RevertCheckedStatePos($cx, compact('alterPos') + $state);
            return true;
          }
        }
      }

        protected function FindStatePos($pos, $substr, $mbstring, $setting = 'revertStrLookAround') {
          $lookAround = $this->config[$setting];

          if ($mbstring) {
            $len = mb_strlen($substr, 'utf-8');
            $lookAround = (int) ($lookAround / 2);

            if (mb_substr($this->context->outStr, $pos, $len, 'utf-8') !== $substr) {
              $str = mb_substr($this->context->outStr, max(0, $pos - $lookAround),
                               $lookAround * 2 + $len, 'utf-8');
              $pos = mb_strpos($str, $substr, 'utf-8');
            }
          } elseif (substr($this->context->outStr, $pos, strlen($substr)) !== $substr) {
            $str = substr($this->context->outStr, max(0, $pos - $lookAround),
                          $lookAround * 2 + strlen($substr));
            $pos = strpos($str, $substr);
          }

          return $pos === false ? null : $pos;
        }

        protected function RevertCheckedStatePos(SpContext $cx, array $state) {
          if ($state['mbstring']) {
            $len = mb_strlen($state['alterStr'], 'utf-8');
            $cx->outStr = mb_substr($cx->outStr, 0, $state['alterPos'], 'utf-8').$state['origStr'].
                          mb_substr($cx->outStr, $state['alterPos'] + $len, 'utf-8');

            $cx->AdjustPosAt($state['alterPos'], mb_strlen($state['origStr'], 'utf-8') - $len);
          } else {
            $len = strlen($state['alterStr']);
            $cx->outStr = substr($cx->outStr, 0, $state['alterPos']).$state['origStr'].
                          substr($cx->outStr, $state['alterPos'] + $len);

            $cx->AdjustPosAt($state['alterPos'], strlen($state['origStr']) - $len);
          }
        }
  }

    class SpOpReplace extends SpOpAdd {
      protected $name = 'REPLACE';

      function NormalizeConfig(array $config) {
        $config += array('revertReplaceCheckStrLength' => 20);
        return parent::NormalizeConfig($config);
      }

      protected function AlterPos(SpContext $cx, $new, $substr, $inPos) {
        $matchPos = $outPos = $cx->AdjustedOutPos($inPos);

        if ($cx->posMbString) {
          $len = mb_strlen($substr, 'utf-8');
          $afterPos = $outPos + $len;
          $cx->outStr = mb_substr($cx->outStr, 0, $outPos, 'utf-8').$new.
                        mb_substr($cx->outStr, $afterPos, strlen($cx->outStr), 'utf-8');

          $delta = mb_strlen($new, 'utf-8') - $len;
        } else {
          $afterPos = $outPos + strlen($substr);
          $cx->outStr = substr($cx->outStr, 0, $outPos).$new.substr($cx->outStr, $afterPos);

          $delta = strlen($new) - strlen($substr);
        }

        $checkPos = $afterPos + $delta;
        $checkLen = $this->config['revertReplaceCheckStrLength'];
        $cx->posMbString and $checkLen = (int) $checkLen / 2;

          if ($cx->posMbString) {
            $checkStr = mb_substr($cx->outStr, $checkPos, $checkLen, 'utf-8');
          } else {
            $checkStr = substr($cx->outStr, $checkPos, $checkLen);
          }

          if ($checkStr === false) {
            $checkPos = $matchPos - $checkLen;

            if ($cx->posMbString) {
              $checkStr = mb_substr($cx->outStr, $checkPos, $checkLen, 'utf-8');
            } else {
              $checkStr = substr($cx->outStr, $checkPos, $checkLen);
            }
          }

        return array('alterPos' => $outPos, 'alterStr' => $new, 'matchPos' => $checkPos,
                     'matchStr' => $checkStr, 'origStr' => $substr, 'delta' => $delta);
      }

      protected function StatelessRevert(SpContext $cx) {
        if ($cx->posState === null) {
          $replacedStr = null;

            $i = $cx->OperationIndex();
            while ($op = $cx->OperationAt(--$i)) {
              if ($op->Finds()) {
                $replacedStr = $op->value;
                break;
              }
            }

          if ($replacedStr === null) {
            $this->Log('cannot locate preceding FIND to get the replaced string.');
          } elseif ($op->Has('regexp')) {
            $this->Log('preceding FIND operates on a regular expression - cannot revert'.
                       ' it because the exact matched string is unknown.');
          } else {
            $state = array('mbstring' => $cx->posMbString, 'alterStr' => $this->value,
                           'matchPos' => 0, 'matchStr' => $cx->outStr[0]);

            $offset = 0;
            while (true) {
              $pos = strpos($cx->outStr, $this->value, $offset);

              if ($pos === false) {
                break;
              } else {
                $cx->outStr = substr($cx->outStr, 0, $pos).$replacedStr.
                              substr($cx->outStr, $pos + strlen($this->value));

                $offset = $pos + 1;
              }
            }

            if ($offset === 0) {
              $this->Log("cannot find any occurrences of the replaced string ('{$this->value}') - skipping.");
            }
          }
        } else {
          $this->Log("previous FIND succeeded - it seems like this occurrence wasn't replaced, skipping it.");
        }
      }
    }

abstract class SpBasePatchLoader {
  static function OpenFile($file) {
    $str = file_get_contents($file);

    if ("$str" === '' and (!is_file($file) or filesize($file) > 0)) {
      throw new ESpPatchFile("Cannot read patch file [$file].");
    }

    $patch = new SpPatch;
    $patch->file = $file;

    $str = preg_replace('/[ \t]+$/m', '', str_replace("\r\n", "\n", $str));
    return array($patch, $str);
  }
}

class SpNativePatch extends SpBasePatchLoader {
  static $commentChars = '#;';
  static $dateMonths = array('jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5,
                             'jun' => 6, 'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10,
                             'nov' => 11, 'dec' => 12);

  static function CanLoad($file) {
    return substr($file, -3) === '.sp' ? array(__CLASS__, 'LoadFromFile') : null;
  }

  static function LoadFromFile($file) {
    list($patch, $str) = self::OpenFile($file);
    return self::LoadInto($patch, $str);
  }

    static function LoadInto(SpPatch $patch, $str) {
      $pfn = $patch->file;

      $step = 's';    // (s)kip, (c)aption, (i)nfo, (f)iles.
      $headComment = '';
      $lines = explode("\n", $str);

        foreach ($lines as $lineI => $line) {
          $line = trim($line);
          if (!isset($line[0])) { continue; }

          if ($step === 's') {
            $step = 'c';

            if (substr($line, -5) === ' skip') {
              $patch->Info('skip', rtrim( substr($line, 0, -5) ));
              continue;
            }
          }

          if ($step === 'c') {
            $pos = strrpos($line, ' by ');

              $caption = rtrim(substr($line, 0, $pos));
              $author = ltrim(substr($line, $pos + 4));

            if ($caption === '') {
              ++$lineI;
              throw new ESpPatchFile("Patch caption not specified on line $lineI in [$pfn].");
            }
            if ($author === '') {
              ++$lineI;
              throw new ESpPatchFile("Patch author not specified on line $lineI in [$pfn].");
            }

            $patch->Info('caption', $caption);
            $patch->Info('author', $author);

            $step = 'i';
            continue;
          }

          if (strpbrk($line[0], self::$commentChars) !== false) {
            $headComment .= ltrim($line, '# ')."\n";
            continue;
          }

          if ($step === 'i') {
            if (substr($line, 0, 2) === '==') {
              $step = 'f';
            } else {
              $infoType = strpbrk($line, '@:/') ? 'c' : 'v';   // (c)ontacts, (v)ersion.

              $left = strtok($line, ' ');
              $right = (string) strtok(null);

              if ($infoType === 'c') {
                $email = strrchr($left, '@') ? $left  : $right;
                $home  = strrchr($left, '@') ? $right : $left;

                $email and $patch->Info('email', trim($email));
                $home  and $patch->Info('homepage', trim($home));
              } else {
                $version = $date = '';
                if ($left === 'from') {
                  $date = $right;
                } elseif ($right === '') {
                  $version = $left;
                } elseif (substr($right, 0, 5) !== 'from ') {
                  ++$lineI;
                  throw new ESpPatchFile("Patch date isn't prefixed with 'from'".
                                         " on line $lineI in [$pfn]: $line");
                } else {
                  $version = $left;
                  $date = substr($right, 5);
                }

                if ($version !== '') {
                  $verNum = (float) substr($version, 1);

                  if (strtolower(rtrim($version, '0..9.')) !== 'v' or !$verNum) {
                    ++$lineI;
                    throw new ESpPatchFile("Wrong patch version format - must be like 'v1.23'".
                                           " but found '$version' on line $lineI in [$pfn].");
                  }

                  $patch->Info('version', $verNum);
                }

                if ($date !== '') {
                  list($day, $month, $year) = explode(' ', trim($date), 3);
                  $month = self::$dateMonths[strtolower($month)];

                  $timestamp = gmmktime(0, 0, 0, $month, $day, $year);
                  if (!$timestamp) {
                    $example = date('%j %M %Y');
                    ++$lineI;
                    throw new ESpPatchFile("Patch date has invalid format - must be like '$example'".
                                           " but found '$date' on line $lineI in [$pfn].");
                  }

                  if (!checkdate($month, $day, $year)) {
                    ++$lineI;
                    throw new ESpPatchFile("Patch date (day $day, month $month, year $year)".
                                           " is invalid on line $lineI in [$pfn].");
                  }

                  $patch->Info('date', $timestamp);
                }
              }

              continue;
            }
          }

          if ($step === 'f') {
            break;
          }
        }

      $patch->AppendHeadComment("\n".$headComment);

      if ($step === 'f') {
        self::LoadFilesInto($patch, $lines, $lineI);
        return $patch;
      }
    }

    static function LoadFilesInto(SpPatch $patch, array &$lines, $lineI) {
      $pfn = $patch->file;
      $curFile = null;

      for (; isset($lines[$lineI]); ++$lineI) {
        $line = rtrim( $lines[$lineI] );

        if (!isset($line[0]) or strpbrk($line[0], self::$commentChars) !== false) {
          // Skip.
        } elseif ($line[0] === '=' and $line[1] === '=') {
          $curFile = new SpAffectedFile(trim($line, '= '));
          $curFile->lineIndex = $lineI;

          $patch->AddFile($curFile);
        } elseif (!$curFile and isset($line)) {
          throw new ESpPatchFile("No file header before patch instructions".
                                 " (line $lineI in [$pfn]): $line");
        } else {
          list($operation, $value) = explode('=', $line, 2);

          if (!isset($value)) {
            ++$lineI;
            throw new ESpPatchFile("Patch instruction contains no '=' on line".
                                   " $lineI in [$pfn]: $line");
          }

            $operation = rtrim($operation);
            $value = ltrim($value);
            $instLineI = $lineI;

          if ($value === '') {
            $value = '';

            while (isset($lines[$lineI + 1]) and (substr($lines[$lineI + 1], 0, 2) === '  '
                     or $lines[$lineI + 1] === '')) {
                $value .= rtrim(substr($lines[++$lineI], 2))."\n";
              }

            $value = rtrim($value, "\n");
          } elseif ($value === '{') {
            $value = '';

              while (true) {
                $line = rtrim($lines[++$lineI]);

                if ($line === '}') {
                  break;
                } elseif (!isset($lines[$lineI + 1])) {
                  throw new ESpPatchFile("Unterminated {multiline} value on line".
                                         " $lineI in [$pfn]: $line");
                } elseif ($line[0] === '}' and ltrim($line, '}') === '') {
                  if (strlen($line) % 2 == 0) {
                    $value .= substr($line, 0, (int) (strlen($line) / 2));
                  } else {
                    ++$lineI;
                    throw new ESpPatchFile("Multiline value on line $lineI in [$pfn]".
                                           " contains uneven escaped number of closing '}': $line");
                  }
                } else {
                  $value .= $line;
                }

                $value .= "\n";
              }

            isset($value[0]) and $value = substr($value, 0, -1);
          }

          $curFile->AddInstruction($operation, $value, $instLineI);
        }
      }
    }
}

class SpStdValueFilters {
  static function Substitute(&$str, SpOperation $op) {
    $replaces = array();
    $root = $op->Option('spRoot');

      foreach ($op->params as $key => &$value) {
        if ($key[0] === '%' and $value) {
          if ($key === '%PATCH%') {
            $path = $op->File()->patch->file;

            if (SpUtils::ExtOf($path) === null) {
              throw new ESpOperation("%PATCH%: patch file name [$path] doesn't".
                                     " contain any extension.", $op);
            }

            $path = substr($path, 0, -1 * strlen($ext));
            if (substr($path, 0, strlen($root)) !== $root) {
              throw new ESpOperation("%PATCH%: patch file directory [$path] doesn't".
                                     " reside under SafePatch root [$root].", $op);
            }

            $path = rtrim( substr($path, strlen($root)), '\\/' );
            if (!is_dir($path)) {
              throw new ESpOperation("%PATCH%: patch directory [$path] doesn't exist.");
            }

            $replaces[$key] = $path;
          }
        }
      }

    $replaces and $str = strtr($str, $replaces);
  }

  static function ConvertRaw(&$str, SpOperation $op) {
    $count = 0;

      if (!empty($op->params['bin'])) {
        ++$count;
        $str = pack('H*', $str);
      }

      if (!empty($op->params['base64'])) {
        ++$count;
        $str = base64_decode($str);
      }

      if (!empty($op->params['escaped'])) {
        ++$count;
        $str = stripcslashes($str);
      }

    if ($count > 1) {
      $params = join(', ', array_keys($op->params));
      throw new ESpOperation("Multiple ($count) value convertions of the same phase".
                             " in [$params].", $op);
    }
  }

  static function ConvertEncoding(&$str, SpOperation $op) {
    if ($enc = @$op->params['enc'] and isset($str[0])) {
      if (!function_exists('iconv')) {
        throw new ESpOperation('enc:XXX: php_iconv module is required.', $op);
      }

      $str = iconv('UTF-8', "$enc//IGNORE", $str);
      if ("$str" === '') {
        throw new ESpOperation("enc:XXX: iconv() has failed to convert value from".
                               " UTF-8 to $enc.", $op);
      }
    }
  }

  static function AddComment(&$str, SpOperation $op) {
    $comment = $option = @$op->params['comment'];
    $dest = $op->DestFile();

      if (!$op->Alters() or $comment === 'off' or !$dest) {
        $fmt = null;
      } else {
        $comments = $op->Option('addComments');
        $comment or $comment = ltrim(SpUtils::ExtOf($dest), '.');

        $fmt = &$comments[$comment];
        $option and $fmt = &$comments["!$comment"];
      }

    if (isset($fmt)) {
      $base = $op->Option('basePath');
      $patchFile = $op->File()->patch->RelFile();
      $nl = strpbrk($str, "\r\n") === false ? '' : "\n";

      $str = $nl.self::FormatComment($fmt, "v [SP $patchFile]", $comment, $op).
             $nl.trim($str, "\r\n").
             $nl.self::FormatComment($fmt, "^ [SP $patchFile]", $comment, $op).$nl;
    }
  }

    static function FormatComment($fmt, $comment, $lang, SpOperation $op) {
      if (strpos($fmt, '$$') === false) {
        $res = str_replace('$', $comment, $fmt, $count);
      } else {
        $comment = addcslashes($comment, '\\\'');
        $res = preg_replace('/\$(\$?)/e', '\'\1\' ? \'\1\' : \''.$comment.'\'', $fmt);
        $count = strpos($res, $comment) === false ? 0 : 1;
      }

      if (!$count) {
        throw new ESpOperation("Comment definition [$fmt] of .".strtoupper($lang).
                               " contains no single '$'.", $op);
      }

      return $res;
    }
}

SpNexus::Register('log', 'default', 'SpDefaultLog');
SpNexus::Register('patch', 'native', 'SpNativePatch');

SpNexus::Register('operation', 'find', 'SpOpFind');
SpNexus::Register('operation', 'or', 'SpOpOr');
SpNexus::Register('operation', 'add', 'SpOpAdd');
SpNexus::Register('operation', 'replace', 'SpOpReplace');

SpNexus::Hook('get patch loader', array('SpNativePatch', 'CanLoad'));
SpNexus::Hook('get operation class', array('SpOperation', 'GetStdClass'));

SpNexus::Hook('filter value', array('SpStdValueFilters', 'Substitute'));
SpNexus::Hook('filter value', array('SpStdValueFilters', 'ConvertRaw'));
SpNexus::Hook('filter value', array('SpStdValueFilters', 'ConvertEncoding'));
SpNexus::Hook('filter value', array('SpStdValueFilters', 'AddComment'));

if (empty($justLoadSafePatch)) {
  $GLOBALS['SP'] = new SafePatch;
  $GLOBALS['SP']->LogPhpErrors();
  $GLOBALS['SP']->Freshen();
}
