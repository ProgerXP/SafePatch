<?php
/*
  FluxBB README-mod patches support for SafePatch
  by Proger_XP              http://i-forge.net/me

  This plugin will add support for handling FluxBB (http://fluxbb.org) common-style
  modification README.txt files. After installing it you will only need to copy mods'
  new files to your FluxBB installation and SafePatch will do the code changes.
*/

SpNexus::Hook('get patch loader', array('SpFluxBBPatch', 'CanLoad'));

class SpFluxBBPatch extends SpBasePatchLoader {
  static $skipBlocks = array('upload' => 1, 'save' => 1, 'saveupload' => 1,
                             'save upload' => 1, 'save and upload' => 1,
                             'saveupload use' => 1);
  static $file, $lastFind;

  static function CanLoad($file) {
    if (substr($file, -4) === '.txt' and $fh = fopen($file, 'rb')) {
      $data = fread($fh, 3072);
      fclose($fh);

      if (strpos($data, 'FluxBB') !== false) {
        return array(__CLASS__, 'LoadFromFile');
      }
    }
  }

  static function LoadFromFile($file) {
    list($patch, $str) = self::OpenFile($file);

    list($header, $instructions) = explode('--------', $str, 2);

      $patch->Info( self::ParseInfo($header) );

      $extra = self::ParseBlocks($patch, "---$instructions", substr_count($header, "\n"));
      $patch->AppendHeadCOmment("\n\n".join( "\n\n", self::MakeHeadComment($extra) ));

    return $patch->FileCount() > 0 ? $patch : null;
  }

  static function ParseInfo($header) {
    $info = array();
    $split = preg_split('/^#*\s*([\w\d\- \t]+):/m', $header, -1, PREG_SPLIT_DELIM_CAPTURE);

      $block = null;
      $infoKeys = array_keys( SpPatch::DefaultInfo() );

      foreach ($split as $i => $piece) {
        $piece = trim($piece, "\0..\x20#");

        if ($i == 0) {
          // continue.
        } elseif ($i % 2 == 1) {
          $block = strtolower($piece);
        } else {
          foreach ($infoKeys as $key) {
            if (!isset($info[$key]) and (strpos($block, strtolower($key)) !== false or
                 ($key === 'caption' and strpos($block, 'title') !== false) or
                 ($key === 'homepage' and strpos($block, 'url') !== false) or
                 ($key === 'headComment' and strpos($block, 'description') !== false))) {
              $norm = self::NormalizeInfo($info, $key, $piece);
              "$norm" === '' or $info[$key] = $norm;
              break;
            }
          }
        }
      }

    return $info;
  }

    static function NormalizeInfo(array &$info, $field, $value) {
      if ($field === 'author') {
        @list($head, $tail) = explode('@', $value, 2);

        if (isset($tail)) {
          $user = substr($head, (int) strrpos($head, ' '));
          $value = substr($head, 0, -1 * strlen($user));

          $pos = strpos($tail, ' ');
          $pos or $pos = strlen($tail);
          $domain = substr($tail, 0, $pos - 1);

          $info['email'] = trim("$user@$domain", '[]()<> ');
        }
      } elseif ($field === 'version') {
        $value = (float) trim($value, ' v');
        $value <= 0 and $value = null;
      } elseif ($field === 'date') {
        $value = strtotime($value);
      } elseif ($field === 'headComment') {
        $value = preg_replace('/^\s*#*\s*|\s+$/m', '', $value);
      }

      return $value;
    }

  static function ParseBlocks(SpPatch $patch, $instructions, $startLine) {
    $blocks = array();
    $blockI = $block = $contents = null;
    $lines = explode("\n", $instructions);

      foreach ($lines as $i => $line) {
        if (!isset($lines[$i + 1]) or (substr(ltrim($line, '# '), 0, 3) === '---' and
             preg_match('/\[([^\]]+)\]---+\s*$/m', $line, $match))) {
            // multilanguage READMEs have multiple consequent "---[ ]----" headers.
          if ($blockI !== $i - 1) {
            $block and $blocks[] = array($blockI, $block, substr($contents, 0, -1));

            $block = $match[1];
            $blockI = $i;
            $contents = '';
          }
        } elseif ($block) {
          $contents .= "$line\n";
        }
      }

    $unkBlocks = $manual = $prereq = array();
    self::$file = null;
    self::$lastFind = array();

      foreach ($blocks as $item) {
        list($i, $block, $value) = $item;

          $block = trim($block);
          $type = trim( preg_replace('/[^a-z ]/', '', strtolower($block)) );
          $type = preg_replace('/  +/', ' ', $type);

          while (substr($value, 0, 2) === "#\n") { $value = substr($value, 2); }
          while (substr($value, -2) === "\n#") { $value = substr($value, 0, -2); }

        $i += $startLine;
        $wasHandled = self::InstallBlock($patch, $i, $type, $value);

        if (!$wasHandled and !isset(self::$skipBlocks[$type])) {
          $value = trim($value);

          if (substr($type, 0, 6) === 'prereq') {
            $prereq[] = array($i, $value);
          } elseif ($type === 'run' or $type === 'delete') {
            $manual[] = array($i, $type, $value);
          } else {
            $unkBlocks[] = array($i, $block);
          }
        }
      }

    return compact('unkBlocks', 'manual', 'prereq');
  }

    static function InstallBlock(SpPatch $patch, $lineI, $type, $value) {
      $value = trim($value, "\r\n");
      $valueTrim = trim($value);

      if ($type === 'open') {
        self::$file = new SpAffectedFile($valueTrim);
        self::$file->lineIndex = $lineI;

        $patch->AddFile(self::$file);
        self::$lastFind = array();
      } elseif ($type === 'find' or $type === 'find line') {    // e.g. "FIND (line XX)"
        $value = trim($value);
        self::$file->AddInstruction('find', $value, $lineI);
        self::$lastFind = array($value, true);
      } elseif (($type[0] === 'o' or $type[0] === 'i') and
                preg_match('/^[oi]n (the )?(same|this) line find$/', $type) and
                $last = &self::$lastFind) {
        $pos = strpos($last[0], $value);

        if ($pos === false) {
          return trim($value) !== $value and
                 self::InstallBlock($patch, $lineI, $type, trim($value));
        } else {
          $head = substr($last[0], 0, $pos);
          $tail = substr($last[0], $pos + strlen($value));
          $regexp = '~'.preg_quote($head, '~')."($value)".preg_quote($tail, '~').'~';

          $func = $last[1] ? 'LastInstruction' : 'AddInstruction';
          self::$file->$func('find regexp', $regexp, $lineI);
          $last[1] = false;
        }
      } elseif ($type === 'add' or $type === 'after add' or $type === 'add after') {
        self::$file->AddInstruction('add', "\n$value", $lineI);
        self::$lastFind[1] = false;
      } elseif ($type === 'before add' or $type === 'add before') {
        self::$file->AddInstruction('add before', "$value\n", $lineI);
        self::$lastFind[1] = false;
      } elseif ($type === 'replace' or $type === 'replace with') {
        self::$file->AddInstruction('replace', $value, $lineI);
        self::$lastFind[1] = false;
      } else {
        return false;
      }

      return true;
    }

  static function MakeHeadComment(array $extra) {
    $res = array();
    extract($extra, EXTR_SKIP);

      $str = '';

        foreach ($prereq as $i => $item) {
          list($line, $req) = $item;
          $str .= "\n\n".++$i.". ".rtrim($req, '.').' (line '.++$line.')';
        }

      $str and $res[] = '*Prerequirments:*'.$str;

      $str = '';

        foreach ($manual as $i => $item) {
          list($line, $operation, $value) = $item;
          $str .= "\n\n".++$i.'. '.ucfirst($operation)." $value (line ".++$line.')';
        }

      $str and $res[] = '*Extra operations:*'.$str;

      $str = '';

        foreach ($unkBlocks as $i => $item) {
          list($line, $block) = $item;

          $str .= "\n\n• ".rtrim($block, '.').' (line '.++$line.')';
          isset($unkBlocks[$i + 1]) and $str .= ', ';
        }

      $str === '' or $res[] = "*Unrecognized instructions:*\n\n$str.";

    return $res;
  }
}