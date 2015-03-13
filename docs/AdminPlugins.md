# Admin Plugins

Lists control panel plugins and how to install them.

## Installation
As with most other things in *SafePatch* plugin installation is straightforward: just copy its file (most often it's just one `.php` script) into `safepatch_root/admin/startup/` and configure it if necessary.

Files from the `startup/` directory will be *automatically loaded when a control panel page is opened* - most of the time this isn't a performance issue.

For simple plugins you can avoid loading all scripts on startup (some of them might not be used) by separating their *class definition* (`class XYZ { ... }`) and *events* (`SpNexus::Hook()` and all other instructions preceding the class definition): the former is put into `admin/autoload/XYZ.php` (_case-sensitive_) and the latter is appended to `autoload/events.php`.

## GuestMode
This plugin will deny access to certain pages based on the visitor criteria specified by `$adminXXX` static class properties.

For example, if:
* *$adminIPs* is `array('192.168.', '45.7.019.23')`
* *$adminCookies* is `array('key' => 'Too secret to show')`
* *$adminMatchAny* is `true`

...then admin access will be given to users with *mask IP* of `192.168.*.*`, *exact IP* `45.7.019.23` _or_ those who *have cookie* named `key` and with value "Too secret to show".

If *$adminMatchAny* is `false` then admins are users with either of the listed IPs _and_ having the listed cookie.

## AddHead
This plugin will put given HTML code after `<head>`, before `</head>`, after `<body>` and before `</body>` tags of the control panel output. This is useful for including copyright info or counters' code.

For example, to put `Copyright &copy; 2012, Our Team` at the end of each page (that is, before closing `</body>`') change the last 2 lines of the plugin to this:
```
AddHead::$bodyFooter = <<<bodyFooter
Copyright &copy; 2012, Our Team
bodyFooter;
```

## MaskEMails
This plugin will replace all e-mails of form `X@Y.Z[.Z...]` appearing in the control panel's output with their `X_at_Y_dot_Z.Z...` versions (localizable). This is useful if your control panel is publicly available.

You can list pages where no masking should be imposed in the `$skipPages` static property - for example, this will show them as is on the _patch list_ and _log_ pages:
```
class MaskEMails {
  static $skipPages = array('patches', 'logs');
  ...
```
