# Admin API

Explains how admin panel works and how it can be extended.

## Structure
_SafePatch_ contains the following files and directories:
* *admin/* - files related to the _control panel_ only
* *display/* - CSS and images used in the _control panel_; they're separated from `admin/` because some of them can be used in standalone patch builds
* *lang/* - language strings; the reason why they're not part of `admin/` is the same as above
* *logs/* - log files are stored here
* *patches/* - `.sp`, `.xml` or other patches that are available for installation/reverting
* *state/* - current engine _Reverting state_
* *config.php* - core Configuration file
* *safepatch.php* - standalone _SafePatch_ core

*admin/* structure:
* *autoload/* - [autoloading](http://php.net/manual/en/language.oop5.autoload.php) PHP scripts, one file per script
* *startup/* - PHP scripts that are automatically loaded on each _control panel_ page after initialization was completed by `core.php` (that is, after loading `admin/config.php`, `utils.php`, `events.php` and setting up global variables)
* *requests.log* - if `trackRequests` is enabled in `admin/config.php` new page requests are written here; one line represents one request in this form:
```
2012-03-09T12:39:48+03:00 127.0.0.1 Anonymous 	/sp/trunk/admin/?page=patches&lang=ru
^ date in Atom format     ^ IP      ^ HTTP user ^ requested URL
```
* *config.php* - _control panel_ configuration
* *core.php* - prepares request and environment when a page is opened
* *events.php* - system event hooks are set up here
* *index.php* - serves actual _control panel_ pages
* *utils.php* - standalone script with useful functions

## Events
_SafePatch_'s _control panel_ uses *event-driven approach*. This means that most actions are performed by _firing_ a specific event instead of calling a function directly. This allows more clean, flexible and extendable code.

SafePatch classes include `SpNexus` which is a hub for dispatching events. To add a new handler for an event one of its _static_ `Hook...()` methods is called; to perform an event `Fire()` is used:
```
$nexus = new SpNexus('myApp');    // $id argument is currently unused.
// prints "Result":
$result = $nexus->Fire('my event', array('arg 1', 2));

SpNexus::Hook('my event', array('MyClass', 'HandleEvent'));
class MyClass {
  static HandleEvent($arg1, $arg2) {
    echo "$arg1, $arg2";          // prints "arg 1, 2".
    return 'Result';
  }
}
```

>_More theory on the event-driven approach can be found in [this post](http://proger.i-forge.net/Omniplugin_system/cWj)._

_Control panel_ uses [class autoloading](http://php.net/manual/en/language.oop5.autoload.php) - scripts with one class per file are put into `admin/autoload/` and get included only when called. This means that for them to hook any events they must be listed somewhere but in their class file (because it's not loaded on each page request) - and for this `admin/events.php` is used.

### Standard events
_Control panel_ initiates the following _events_:
* *on log* (`core.php`) - called on each new message logged by `SafePatch->$log` (an SpLog instance)
* *page: XXX* (`index.php`), e.g. `page: patches` - is called to get requested page body, title, etc.
* *diff: html* (`autoload/BasePage.php`) - is called to get the HTML diff between two strings

*utils.php* fires these:
* *sidebar* - collects sidebar boxes to be displayed on the page's left side
* *header* - collects header items displayed between the _control panel_'s header and the page's body/left sidebar
* *footer* - collects footer items displayed under the page body but on the sidebar's right
* *output* - is called when a formatted page (with `<htmL>` tags) is about to be sent to the user
* *on change* - called when some action like applying a patch or deleting a log file is about to be performed

## Global variables
`admin/core.php` defines most of the global variables - they're marked as `global $XXX` in its code (even though it runs in global namespace):
* *$config* - configuration array loaded from `admin/config.php`
* *$spRoot* - absolute path to _SafePatch_ root (`safepatch.php`)
* *$mediaURL* - URL to `display/` directory containing page styles and images (absolute or relative); _this should not be used to determine its filesystem location_
* *$nexus* - an instance of SpNexus - the *event manager*
* *$sp* - an instance of SafePatch - the *core object*

The rest is defined in `admin/index.php`:
* *$reqPage* - name of originally requested page; see *$page* for why it is "original"
* *$page* - at first this is a string, then it's an array:
 * _string_ - name of currently serving page; this might be different from the one requested if it doesn't exist - then *$page* is `404` (but *$reqPage* still holds the original page name)
 * _array_ - becomes so after *page: XXX* event was called and is set to its return value, that is containing keys accepted by `display/page.php` (_page_, _windowTitle_, _body_, etc.)

*Language variables:*
* *$lang* - 2-characer language code (`en`, `ru`, etc.) of the control panel; depends on visitor's `Accept-Language` header and/or `language` cookie
* *$allT* - full set of current language strings; includes not only the _panel_'s strings but all others found in `lang/XX.php` (e.g. those of a single patch build)
* *$T* - _control panel_ language texts (equals to `$allT['panel'])
* *$P* - texts of the current page (equals to `$T['currentPage']) or _an empty array_ if there are no texts for current page

Standard PHP [superglobals](http://php.net/manual/en/language.variables.superglobals.php) (`$_REQUEST`, `$_SERVER`, etc.) are also safe to use

## Examples
### Creating a page
As everything else pages are added using an event - `page: XXX` where _XXX_ is the name of the page. Below is the code for outputting some information about the visitor and a form for pinging one of its ports:
```PHP
<?php
// hooking the page event.
SpNexus::Hook('page: stats', array('PageStats', 'Build'));

// adding language strings.
$T['stats'] = array(
  'pageTitle' => 'Statistics',

  'statLog' => '<strong>Current log file:</strong> %s',
  'statTime' => '<strong>Server datetime:</strong> <span class="time">%s</span>',
  'statApplied' => '<strong>Number of applied patches:</strong> %s',
  'statIP' => '<strong>Your IP address:</strong> <kbd>%s</kbd>',
  'statBrowser' => '<strong>Your browser:</strong> <em>%s</em>',
  'statLanguage' => '<strong>Control panel language:</strong> <em>%s</em>',

  'pingPortLabel' => 'Port number:',
  'pingBtn' => 'Check if open',

  'pingTitle' => 'Port checker',
  'portIsOpened' => 'Port <strong>%s</strong> on <strong>%s</strong> is opened and accepting connections.',
  'portIsClosed' => 'Port <strong>%s</strong> on <strong>%s</strong> is closed (<strong>#%s</strong>):',
);

class PageStats {
  static function Build() {
    global $P;

    if ($port = @$_REQUEST['ping']) {
      return '<h1>'.$P['pingTitle'].'</h1>'.self::Ping($_SERVER['REMOTE_ADDR'], $port);
    } else {
      return '<h1>'.$P['pageTitle'].'</h1>'.self::Info().
             '<h2>'.$P['pingTitle'].'</h2>'.self::Form();
    }
  }

  static function Info() {
    global $P, $sp, $lang;
    $info = array();

      $info[] = sprintf($P['statLog'], QuoteKbd( $sp->Logger()->File() ));
      $info[] = sprintf($P['statTime'], date('l jS \\o\\f F Y (\\a\\t H:i)'));
      $info[] = sprintf($P['statApplied'], count($sp->AppliedPatches()));
      $info[] = sprintf($P['statIP'], $_SERVER['REMOTE_ADDR']);

      if (!preg_match('/\b(Firefox|Opera|MSIE|Safari|Chrome|Lynx)\b/',
                      $_SERVER['HTTP_USER_AGENT'], $browser)) {
        $browser[1] = 'unknown';
      }
      $info[] = sprintf($P['statBrowser'], $browser[1]);

      $info[] = sprintf($P['statLanguage'], $lang);

    return '<ul><li>'.join('</li><li>', $info).'</li></ul>';
  }

  static function Form() {
    global $P;

    list($url, $fields) = FormPageURL('stats');
    return '<form action="'.$url.'" method="get">'.$fields.
             $P['pingPortLabel'].
             ' <input type="text" name="ping" value="'.$_SERVER['REMOTE_PORT'].'" />'.
             ' <input type="submit" class="btn em" value="'.$P['pingBtn'].'" />'.
           '</form>';
  }

  static function Ping($ip, $port) {
    global $P;

    if (!@fsockopen($ip, $port, $errno, $error, 1)) {
      return '<p class="info">'.sprintf($P['portIsOpened'], $port, $ip).'</p>';
    } else {
      return '<div class="error">'.
               '<p class="em">'.sprintf($P['portIsClosed'], $port, $ip, $errno).'</p>'.
               "<p>$error</p>".
             '</div>';
    }
  }
}
```

> By convention, _control panel_ pages are handled by static method `Build...()` of the class named `PageXXX` where _XXX_ is the page's name.

The above code can be installed either by saving the entire script into `admin/startup/PageStats.php` (or with any other name) or by putting class code into `admin/autoload/PageStats.php` and the code before - into `events.php` as described on AdminPlugins#Installation.

> New page *won't appear in the menu automatically* - you need to add it to the menu option of `admin.config.php` like this:
```
'menu' => array(
  '=stats' => array('Statistics'),
  ...
```

### Adding a sidebar
The following script will add a sidebar saying something like "Hello! You're now on page patches." when put in `admin/startup/sample-sidebar.php` (the name doesn't really matter since all files are autoloaded):
```
?php
SpNexus::Hook('sidebar', array('SamplePlugin', 'AddSidebarTo'));
$T += array('sampleSidebar' => '<strong>Hello!</strong> You\'re now on page <em>%s</em>.');

class SamplePlugin {
  static function AddSidebarTo(array &$sidebars) {
    global $T, $reqPage;

    $body = sprintf($T['sampleSidebar'], $reqPage);
    array_unshift($sidebars, array('class' => 'alert', 'body' => $body));
  }
}
```

The above code adds the language string "sampleSidebar" regardless of the user's language. If you want to make it localizable you can use `MergeLang()` function to supply strings for multiple languages:
```
MergeLang(array(
  'en' => array('sampleSidebar' => '<strong>Hello!</strong> You\'re now on page <em>%s</em>.'),
  'ru' => array('sampleSidebar' => '<strong>Привет!</strong> Ты сейчас находишься на странице <em>%s</em>.')
));
```

The above code shows the message in either _English_ or _Russian_ depending on the visitor's locale; if it's different configured `defaultLang` ("en" by default) is used or, if this language wasn't passed to `MergeLang()`, the first language (here "en") will be used.

> *Warning:* when using non-Latin characters make sure to save your script *in UTF-8 encoding* (without _BOM_ as it will be output by PHP).