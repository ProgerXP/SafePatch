SafePatch configuration options.

*SafePatch* configuration is set in `config.php`. This file returns a PHP array and looks like this:
```
<?php
include_once 'vqmod.php';
...    // other includes

return array(
  'basePath' => '...',
  ...  // other options
);
```

*Includes*, if present, load *SafePatch* extensions such as *[VQMod](http://code.google.com/p/vqmod/)* `.xml` patches support or extra *parameters* for *patch instructions*. Included files are located in *SafePatch* root directory.

## Options

### basePath
Sets base path for most operations; usually points to the directory of the application being patched (e.g. web engine root). Is relative to *SafePatch* root (`safepatch.php`); you can use [getcwd()](http://php.net/manual/en/function.getcwd.php) to refer to the startup directory.

*Defaults to* `..` (parent folder).

#### ignore
Sets files and folders that are not allowed modifications by patches and are excluded from recursive _saving_ (diffing) of current application state.

Is relative to *basePath*. If an item has trailing path delimiter (`/` or `\`) it's considered a directory, otherwise it's a file. _Wildcards_ (`*` and `?`) are supported.

*Defaults to* `array('.svn/', '_svn/')`.

#### ignorePatchFN
Makes *SafePatch* skip `patches/` file names starting with listed characters.

For example, if this is `._!` then files starting with a period, underscore or exclamation mark will be ignored even if they appear like proper safepatches (e.g. `patches/_my_patch.sp`).

*Defaults to* `.-`.

#### logType
Sets the name of logging interface; `false` disables logging. There is one default logger ("default") which logs messages in standard form to *logPath* and using *logMerge* to generate the log file name.

*Defaults to* `default`.

#### logPath
Sets file mask for log files; can contain `%X` sequences supported by PHP [strftime()](http://php.net/manual/en/function.strftime.php). Is relative to *SafePatch* root plus the following (case-sensitive; can't be escaped like `%%O`):
* *%O* - count of files in the log directory plus one;
* *%o* - calculates maximum ("order") plus one from file names in the log directory by taking all numeric part in the beginning and discarding names not starting with a digit. *For example*, for files `index.html`, `2012-02-05.log` and `file 5.dat` *%o* will return `2013`:
 * `index.html` - starts with "I' which is taken as "0"
 * `2012-02-05.log` = "2012" (the maximum)
 * `file 5.dat` = "0" (there's a digit "5" but the name starts with a letter)

*Defaults to* `logs/%Y-%m-%d.log`. *See also* logMerge.

#### logMerge
Sets the period in seconds logged to a single log file (similar to _log rotation_ but without cleaning old logs). For example, if this is 604800 (7 days) one log file will contain messages from Monday to Sunday and then a new file will be created.

*Defaults to* `3600 * 24 * 7` (604800, 1 week). *See also* logPath.

#### logFlush
Toggles log flushing: if disabled log file is written at the end of page request (on PHP `__destruct`) - this is faster but in case of post-request errors PHP will fail immediately and not reach the log writing function; enabling *logFlush* will write new messages immediately to the disk.

*Defaults to* `false`. *See also* logPath.

#### onError
Specifies what to do when patching fails; this only has an effect when *SafePatch* is called from the running application isntead of direct request (e.g. via web interface).

*Values* (partial patching is always rolled back):
* *abandon* - stop processing current patch and don't pick up the next - let the application run
* *skip* - similar to *abandon* but continues to the next patch (if any)
* *die* - terminate host application with an error screen and 500 HTTP status

*Defaults to* `skip`.

#### addComments
Enables adding of comments into the patched files. Each comment looks like `/* SP my-patch.sp */` (syntax depends on the target file).

This is an array of form:
```
'php' => '/* $ */',
'!html' => '<!-- $ -->',
...
```
...where *key* is target file extension _without leading period_ and *value* is comment syntax (single `$` is replaced by the comment text, `$$` - by single `$`, `$$$` - by `$$` followed by the comment text, etc.).

*Key* can start with _exclamation mark_ (`!`) - such files won't be autocommented unless explicitly done so by the *comment:XXX* _operation parameter_.
