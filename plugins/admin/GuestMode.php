<?php
/*
  Guest mode plugin for SafePatch control panel
  by Proger_XP              http://i-forge.net/me

  This plugin will deny access to server-modifying actions of the admin panel.
  Drop this file into admin/startup/ and configure the $adminXXX properties below.
*/

SpNexus::Hook('on change', array('GuestMode', 'OnChange'));

MergeLang(array(
  'en' => array('guestModePageDenied' => 'You are not authorized to view this page (%s).',
                'guestModeReasons' => 'Wrong %s.'),
  'ru' => array('guestModePageDenied' => 'У вас нет прав для просмотра этой страницы (%s).',
                'guestModeReasons' => 'Ошибка в %s.')
));

class GuestMode {
  /*
    Properties below control identification of the user(s) who will be able to
    administer the SafePatch root; others will have view-only access.
    If a property is an empty array it isn't checked (always matches).
    You can see your IP, browser's data, headers, etc. here: http://i-tools.org/me
  */

  // list of IP address prefixes: '192.168.' (matches 192.168.0.0-192.168.255.255),
  // or exact IPs: '45.7.019.23'.
  static $adminIPs = array('192.168.');
  // list of users authenticated via HTTP Auth (AuthType .htaccess directive).
  static $adminUsers = array();
  // list of case-insensitive substrings - e.g. "chrom" matches "Chromium" and "Chrome".
  static $adminUserAgents = array();
  // 'CookieName' => 'value'/true; 'value' is case-sensitive full string;
  // 'true' matches any value as long as the named cookie is present.
  static $adminCookies = array();
  // the same as $adminCookies but looks in passed GET/POST variables.
  static $adminRequestVars = array();
  // 'Header-Name' => 'value'/true; 'value' is case-insensitive substring.
  static $adminHeaders = array();

  // if true, a visitor having any of the above properties matched will be an admin;
  // if false, only those will be admins who have all of the above properties matched.
  static $adminMatchAny = false;
  // non-admins won't be allowed to perform listed server-changing actions; values can
  // contain wildcards and are of form "page-action" (e.g. "logs-prune" or "logs-*").
  static $denyChanges = array('*');
  // if non-empty, the following pages will be denied access altogether for guests;
  // you can see the list of pages in events.php (look for "page: XXX" strings).
  static $denyPages = array();

  // if false no exact reason(s) why access was denied will be shown under the error.
  static $showReasons = true;

  static function OnChange(&$allow, &$action) {
    global $page;

    $flags = FNM_NOESCAPE | FNM_PATHNAME | FNM_PERIOD | FNM_CASEFOLD;
    foreach (self::$denyChanges as $mask) {
      if (fnmatch($mask, "$page-$action", $flags)) {
        if ($msg = self::GetErrorMsg(true, $action)) {
          return $msg;
        } else {
          break;
        }
      }
    }
  }

  static function OnPage() {
    global $P, $page;

    if (in_array($page, self::$denyPages) and $msg = self::GetErrorMsg(false)) {
      $header = isset($P['pageTitle']) ? "<h1>$P[pageTitle]</h1>" : '';
      return $header.'<div class="error access">'.$msg.'</div>';
    }
  }

    static function GetErrorMsg($isAChange, $action = null) {
      global $T, $page;

      $rights = self::CheckRights();

        $isAdmin = !self::$adminMatchAny;
        foreach ($rights as &$result) {
          $isAdmin = self::$adminMatchAny ? ($isAdmin or $result) : ($isAdmin and $result);
          $result = !$result;
        }

      if (!$isAdmin) {
        $action and $page .= "-$action";
        $msg = $isAChange ? 'requestChangeDenied' : 'guestModePageDenied';
        $res = '<p>'.sprintf($T[$msg], QuoteStrong($page)).'</p>';

          if (self::$showReasons) {
            $reasons = join(', ', array_keys(array_filter($rights)) );
            $res .= '<p class="small">'.sprintf($T['guestModeReasons'], $reasons).'</p>';
          }

        return $res;
      }
    }

  static function HttpAuthDataFrom(array $server) {
    $user = @$server['PHP_AUTH_USER'];
    $password = isset($user) ? @$server['PHP_AUTH_PW'] : null;

    return array($user, $password);
  }

  static function HeadersFrom(array $server) {
    $hdrKeys = $hdrValues = array();

      foreach ($server as $key => &$value) {
        if (substr($key, 0, 5) == 'HTTP_') {
          $hdrKeys[] = ucfirst(strtolower( substr($key, 5) ));
          $hdrValues[] = $value;
        }
      }

    if (empty($hdrKeys)) {
      return array();
    } else {
      $eval = "'\\1-'.strtoupper('\\2')";
      $hdrKeys = preg_replace('/(\w)_(\w)/e', $eval, $hdrKeys);
      return array_combine($hdrKeys, $hdrValues);
    }
  }

  static function CheckRights() {
    $headers = self::HeadersFrom($_SERVER);
    return array('IP' => self::MatchIP($_SERVER['REMOTE_ADDR']),
                 'HTTP Auth' => self::MatchHttpAuth(self::HttpAuthDataFrom($_SERVER)),
                 'User Agent' => self::MatchUserAgent($_SERVER['HTTP_USER_AGENT']),
                 'Cookies' => self::MatchVars($_COOKIE, self::$adminCookies),
                 'Request' => self::MatchVars($_POST + $_GET, self::$adminRequestVars),
                 'Headers' => self::MatchVarsLoose($headers, self::$adminHeaders));
  }

    static function MatchIP($reqIP) {
      $reqIP = long2ip(ip2long($reqIP));

      if (empty(self::$adminIPs)) {
        return true;
      } else {
        foreach (self::$adminIPs as $ip) {
          if (strpos($reqIP, $ip) === 0) { return true; }
        }
      }
    }

    static function MatchHttpAuth(array $reqAuth) {
      return empty(self::$adminUsers) or in_array($reqAuth[0], self::$adminUsers);
    }

    static function MatchUserAgent($reqAgent) {
      if (empty(self::$adminUserAgents)) {
        return true;
      } else {
        $reqAgent = strtolower($reqAgent);

        foreach (self::$adminUserAgents as $agent) {
          if (stripos($agent, $reqAgent) !== false) { return true; }
        }
      }
    }

    static function MatchVars(array $req, array $matching) {
      if (empty($matching)) {
        return true;
      } else {
        foreach ($matching as $name => &$value) {
          if ( $value === true ? isset($req[$name]) : (@$req[$name] == $value ) ) {
            return true;
          }
        }
      }
    }

    static function MatchVarsLoose(array $req, array $matching) {
      if (empty($matching)) {
        return true;
      } else {
        $normReq = array();
        foreach ($req as $name => &$value) { $normReq[strtolower($name)] = $value; }

        foreach ($matching as $name => &$value) {
          $name = strtolower($name);

          if (isset($normReq[$name]) and
              ($value === true or stripos($normReq[$name], $value) !== false)) {
            return true;
          }
        }
      }
    }
}

// this hooking goes after the class because sometimes PHP doesn't recognize classes
// if they are defined after the code using them.
foreach (GuestMode::$denyPages as $page) {
  SpNexus::HookFirst('page: '.$page, array('GuestMode', 'OnPage'));
}