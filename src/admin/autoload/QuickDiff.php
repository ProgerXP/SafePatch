<?php
/*
  QuickDiff           by Proger_XP
  in public domain    http://proger.i-forge.net
*/

class QuickDiff {
  static $htmlReplaces = array(' ' => '<i>&middot;</i>', "\n" => '<br />', "\t" => '  ');

  static function Quote($str, $htmlize = true) {
    $str = htmlspecialchars($str);
    $str = strtr($str, self::$htmlReplaces);
    $htmlize or $str = strip_tags($str);

    return $str;
  }

  static function IsBinary(&$data) {
    return preg_match('/[\x00-\x08\x0C\x0E-\x1F]/', substr($data, 0, 512));
  }

  static function Inline($got, $must) {
    static $lookAhead = 10;
    static $testAhead = 5;
    static $okAhead = 20;

    $result = array(array(null));  // in reverse order for quick [0] element reference.

      $gotLen = strlen($got);
      $mustLen = strlen($must);
      $minLen = $gotLen > $mustLen ? $mustLen : $gotLen;

      $gotI = $mustI = 0;
      do {
        $toBreak = ($gotI >= $gotLen or $mustI >= $mustLen);
        $toAdd = array();

        if ($toBreak) {
          if (isset($got[$gotI])) {
            $toAdd = array(array('+', substr($got, $gotI)));
          } elseif (isset($must[$mustI])) {
            $toAdd = array(array('-', substr($must, $mustI)));
          }
        } else {
          if ($got[$gotI] === $must[$mustI]) {
            $toAdd = array(array('=', $got[$gotI]));
            ++$gotI;
            ++$mustI;
          } else {
            for ($la = $lookAhead; $la > 0; --$la) {
              $offsets = array();

                for ($ta = 1; $ta <= $testAhead; ++$ta) {
                  //var_dump(substr($got, $gotI + $ta, $la));
                  $pos = strpos(substr($must, $mustI, $okAhead), substr($got, $gotI + $ta, $la));
                  if ($pos !== false and !isset($offsets[$pos])) {
                    $offsets[$pos + ($ta << 16)] = compact('pos', 'la', 'ta');
                  }
                }

              if ($offsets) {
                ksort($offsets);
                $pos = array_shift($offsets);

                if ($pos['pos'] === 0) {
                  $toAdd = array('+', substr($got, $gotI, $pos['ta']));
                } else {
                  $toAdd = array('change', substr($must, $mustI, $pos['pos']), substr($got, $gotI, $pos['ta']));
                }

                $gotI += $pos['ta'];
                $mustI += $pos['pos'];

                //$toAdd = array(array('=', substr($got, $gotI - $pos['la'], $pos['la'])), $toAdd);
                $toAdd = array($toAdd);
                break;
              }
            }

            if (!$toAdd) {
              $toAdd = array(array('-', substr($must, $mustI, 1)));
              ++$mustI;
            }
          }
        }

        while ($item = array_pop($toAdd)) {
          if ($result[0][0] === $item[0] and strlen($item[0]) == 1) {
            $result[0][1] .= $item[1];
          } else {
            array_unshift($result, $item);
          }
        }
      } while (!$toBreak);

    $result = array_reverse($result);
    array_shift($result);

    return $result;
  }

  static function InlineHTML($got, $must) {
    static $classes = array('-' => 'del', '+' => 'add', '=' => 'equ');

    $result = '';
    $diff = self::Inline($got, $must);

      foreach ($diff as $piece) {
        if ($piece[0] === 'change') {
          $str = self::Quote($piece[2]);
          $extra = ' title="'.self::Quote($piece[1], false).'"';
        } else {
          $str = self::Quote($piece[1]);
          $extra = '';
        }

        $class = isset($classes[$piece[0]]) ? $classes[$piece[0]] : $piece[0];
        $result .= '<span class="'.$class.'"'.$extra.'>'.$str.'</span>';
      }

    return $result;
  }

  static function Full($oldStr, $newStr) {
    static $lookAhead = 75;
    static $minLookAheadStrLen = 7;
    static $lookAheadLikeness = 80;
    static $lineMatchLikeness = 70;

    $result = array();

      $oldLines = explode("\n", str_replace("\r", '', $oldStr));
      $newLines = explode("\n", str_replace("\r", '', $newStr));

      for ($oldI = $newI = 0; isset($oldLines[$oldI]); ) {
        $res = self::CompareLine($oldLines, $newLines, $oldI, $newI, $lineMatchLikeness);
        if ($res === null) {
          break;
        } elseif ($res !== false) {
          ++$oldI;
          ++$newI;
          $result[] = $res;
        } else {
          for ($la = 1; $la <= $lookAhead and isset($oldLines[$oldI + $la]); ++$la) {
            if (strlen(trim( $oldLines[$oldI + $la] )) >= $minLookAheadStrLen) {
              for ($ta = 0; $ta < $lookAhead; ++$ta) {
                $res = self::CompareLine($oldLines, $newLines, $oldI + $la,
                                         $newI + $ta, $lookAheadLikeness);
                if ($res or $res === null) { break; }
              }
            }

            if ($res) { break; }
          }

          if ($res) {
            do {
              $result[] = array('-', $oldLines[$oldI], $oldI, $newI);
              ++$oldI;
            } while (--$la > 0);

            while (--$ta >= 0) {
              $result[] = array('+', $newLines[$newI], $oldI, $newI);
              ++$newI;
            }

            $result[] = $res;

            ++$oldI;
            ++$newI;
          } else {
            $result[] = array('+', $newLines[$newI], $oldI, $newI);
            ++$newI;
          }
        }
      }

    while (isset($newLines[$newI])) {
      $result[] = array('+', $newLines[$newI++], $oldI, $newI);
    }

    return $result;
  }

    static function CompareLine(array &$oldLines, array &$newLines, $oldI, $newI, $hhreshold) {
      // see http://www.php.net/manual/ru/function.similar-text.php#72448
      static $simLimit = 10000;

      $old = &$oldLines[$oldI];
      $new = &$newLines[$newI];

      if (!isset($new)) {
        return;
      } elseif ($old === $new or trim($old) === trim($new)) {
        return array('=', $old, $oldI, $newI);
      //} elseif (trim($old) === trim($new)) {
      //  return array('change', $new, $old, $oldI, $newI);
      } elseif (strlen($old) < $simLimit and strlen($new) < $simLimit) {
        similar_text($old, $new, $likeness);
        $isLike = $likeness >= $hhreshold;

          if (!$isLike) {
            $oldSpc = preg_replace('/\s+/', ' ', $old);
            $newSpc = preg_replace('/\s+/', ' ', $new);

            similar_text($oldSpc, $newSpc, $likeness);
            $isLike = $likeness >= $hhreshold;
          }

        if ($isLike) {
          return array('change', $new, $old, $oldI, $newI);
        } else {
          return false;
        }
      }
    }

  static function FullHTML($oldStr, $newStr, array $options = array()) {
    $options += array('classes' => array('-' => 'del', '+' => 'add', '=' => 'equ'),
                      'lineNumbers' => true, 'collapseLines' => 2);
    extract($options, EXTR_SKIP);

    $diff = self::Full($oldStr, $newStr);
    $lineNumLen = strlen((string) count($diff));
    $collapseLines and $diff = self::CollapseEqualLines($diff, $collapseLines);

    $result = '';
    $prevOldI = $prevNewI = null;

      foreach ($diff as $line) {
        $class = $line[0];

        if ($class === 'cut') {
          $rep = str_repeat('-', $lineNumLen * 2 + 1);
          $result .= '   <code class="cut">'.$rep.' '.str_repeat('-', 70).'</code>';
        } else {
          $symbol = ' ';

            if ($class === 'change') {
              $res = self::InlineHTML($line[1], $line[2]);
            } else {
              $symbol = $class[0];
              $class = $classes[$class];
              $res = self::Quote($line[1]);
            }

          $result .= "<i>$symbol</i> ";

            if ($lineNumbers) {
              $oldI = $line[count($line) - 2];
              is_int($oldI) and ++$oldI;
              $oldI === $prevOldI ? ($oldI = '') : ($prevOldI = $oldI);

              $newI = $line[count($line) - 1];
              is_int($newI) and ++$newI;
              $newI === $prevNewI ? ($newI = '') : $prevNewI = $newI;

              $result .= '<kbd>'.sprintf(" %{$lineNumLen}s", $oldI).'</kbd>'.
                         '<kbd>'.sprintf(" %{$lineNumLen}s", $newI).'</kbd>';
            }

          $result .= '  <code class="'.$class."\"><span>$res</span></code>";
        }

        $result .= '<br />';
      }

    return $result;
  }

    static function CollapseEqualLines(array $diff, $maxConsequentLines = 2) {
      for ($i = count($diff); --$i >= 0; ) {
        $diff[$i][0] === '=' and $diff[$i][1] === '' and $diff[$i] = null;
      }

      $diff = array_values(array_filter($diff));
      $prevChange = -1;

        foreach ($diff as $index => &$line) {
          if ($line[0] !== '=') {
            $i = $index - $maxConsequentLines - 1;

            if ($i - $maxConsequentLines > $prevChange) {
              $diff[$i] = array('cut', null, null);

              for ($i = $prevChange + 1; $i < $index - $maxConsequentLines - 1; ++$i) {
                $diff[$i] = null;
              }
            }

            $prevChange = $index;
          }
        }

      $prevChange += $maxConsequentLines;
      while (count($diff) > ++$prevChange) { $diff[$prevChange] = null; }

      return array_values(array_filter($diff));
    }

    static function FullHtmlWrapped($oldStr, $newStr, array $options = array()) {
      $options += array('tag' => 'pre', 'tagClasses' => 'qdiff',
                        'binMsg' => 'Diff is unavailable for binary data.');
      extract($options, EXTR_SKIP);

      $isBin = self::IsBinary($oldStr);
      $isBin and $tagClasses .= ' bin';
      $tagClasses and $tagClasses = ' class="'.trim($tagClasses).'"';

      if ($isBin) {
        return "p$tagClasses>$binMsg</p>";
      } else {
        return "<$tag$tagClasses>".self::FullHTML($oldStr, $newStr, $options)."</$tag>";
      }
    }

  static function Test($path = 'qdiff') {
    $texts = array();

      foreach (glob(rtrim($path, '\\/').'/* new.txt') as $new) {
        $old = substr($new, 0, -8).' old.txt';
        $diff = substr($new, 0, -8).' diff.html';

        if (is_file($old) and is_file($diff)) {
          $texts[$old] = array('old' => file_get_contents($old), 'new' => file_get_contents($new),
                               'diff' => file_get_contents($diff),
                               'isFull' => strpos($old, 'inline') === false);
        }
      }

    foreach ($texts as $name => $text) {
      $func = $text['isFull'] ? 'FullHTML' : 'InlineHTML';
      $res = self::$func($text['old'], $text['new']);

      if ($res !== $text['diff'] and
          str_replace('<br />', "<br />\n", $res) !== $text['diff']) {
        echo "<h2>$name</h2>\n", $res, "\n\n";
      }
    }
  }
}

//QuickDiff::Test();
