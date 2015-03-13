<?php
/*
  XQMod XML patches support for SafePatch
  by Proger_XP              http://i-forge.net/me

  This plugin will add support for VQMod .xml patches.
*/

SpNexus::Hook('get patch loader', array('SpVQModPatch', 'CanLoad'));

class SpVQModPatch extends SpBasePatchLoader {
  static function CanLoad($file) {
    if (substr($file, -4) === '.xml' and $fh = fopen($file, 'rb')) {
      $data = fread($fh, 256);
      fclose($fh);

      if (strpos($data, '<modification') !== false) {
        return array(__CLASS__, 'LoadFromFile');
      }
    }
  }

  static function LoadFromFile($file) {
    $xml = new DOMDocument;
    if ($xml->load($file) and $root = $xml->firstChild and
        $root->nodeName === 'modification') {
      $patch = new SpPatch;
      $patch->file = $file;

      $patch->Info( self::InfoFrom($root) );

      foreach ($root->getElementsByTagName('file') as $node) {
        if ($name = $node->getAttribute('name')) {
          $file = new SpAffectedFile($name);
          $file->lineIndex = $node->getLineNo() - 1;

          self::FillFileFrom($node, $file, $patch->file) and $patch->AddFile($file);
        }
      }

      return $patch->FileCount() > 0 ? $patch : null;
    }
  }

    static function InfoFrom(DOMNode $node) {
      $res = array();

        foreach ($node->childNodes as $child) {
          if ($child instanceof DOMElement) {
            switch ($child->tagName) {
            case 'id':        $res['caption'] = $child->nodeValue; break;
            case 'version':   $res['version'] = (float) $child->nodeValue; break;
            case 'author':    $res['author'] = $child->nodeValue; break;
            }
          }
        }

      return $res;
    }

    static function FillFileFrom(DOMNode $node, SpAffectedFile $file, $pfn) {
      foreach ($node->getElementsByTagName('operation') as $opNode) {
        $search = null;
        if ($opNode->childNodes->length >= 2) {
          $search = $opNode->getElementsByTagName('search');
          $add = $opNode->getElementsByTagName('add');

          if ($search->length == 1 and $add->length == 1 and
              $search->item(0)->getLineNo() < $add->item(0)->getLineNo()) {
            $search = $search->item(0);
            $add = $add->item(0);
          } else {
            $search = null;
          }
        }

        if ($search) {
          $value = trim($search->nodeValue);
          $line = $opNode->getLineNo() - 1;

          $attrs = '';

            $regexp = $search->getAttribute('regexp');
            if ($regexp and strtolower($regexp) !== 'false') {
              $attrs .= ' regexp';
            }

            $index = $search->getAttribute('index');
            if ($index and strtolower($index) !== 'false') {
              $attrs .= " $index";
            }

            $offset = $search->getAttribute('offset');
            $offset > 0 or $offset = null;

          $pos = $search->getAttribute('position');

            if ($pos === 'before') {
              $action = 'add before';
              $offset and $attrs .= ' shift:-'.$offset;
              $file->AddInstruction('find'.$attrs, $value, $line);
            } elseif ($pos === 'after') {
              $action = 'add';
              $offset and $attrs .= ' shift:'.$offset;
              $file->AddInstruction('find'.$attrs, $value, $line);
            } elseif ($pos === 'top') {
              $action = 'add';
              $offset and $attrs .= ' shift:'.$offset;
              $file->AddInstruction('find regexp'.$attrs, '/^/', $line);
            } elseif ($pos === 'bottom') {
              $action = 'add before';
              $offset and $attrs .= ' shift:-'.$offset;
              $file->AddInstruction('find regexp'.$attrs, '/$/', $line); self::$before = true;
            } elseif ($pos === 'all') {
              $action = 'replace';
              $file->AddInstruction('find regexp'.$attrs, '/^.*$/', $line);
            } else {
              $action = 'replace';
              $offset and $attrs .= ' shift:'.$offset;
              $file->AddInstruction('find'.$attrs, $value, $line);
            }

          $value = trim($add->nodeValue);
          $line = $add->getLineNo() - 1;
          $file->AddInstruction($action, "\n$value\n", $line);
          $ok = true;
        }
      }

      return isset($ok);
    }
}