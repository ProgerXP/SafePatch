Describes SafePatch API - relations of classes, their methods and properties.

<wiki:toc max_depth="1" />

## Classes
_All *SafePatch* class names except the main one begin with "Sp" prefix. Starting from the <u>next</u> section class names are written in `monospace` font._

*Core classes:*
* *SafePatch* - the main class - represents a single environment (application) residing under a certain _base path_ and sharing common configuration
* *SpPatch* - represents one _safepatch_ (`.sp` file or other); contains patch info (author, caption, etc.) and files to be patched (*SpAffectedFile*) with their instructions
* it is independent from any particular patch file format (native `.sp`, *[VQMod](http://code.google.com/p/vqmod/)* `.xml`, etc.)
* *SpAffectedFile* - represents a particular file that a _safepatch_ (*SpPatch*) has instructions for
* *SpOperation* - abstract class for an atomic operation (described in Syntax) - such as *find* (`SpOpFind`) or *replace* (`SpOpReplace`); operations run in given _context_ (*SpContext* object)

*System classes:*
* *SpBase* - abstract base for common classes; implements configuration methods and stubs
* *SpUtils* - standalone class containing miscellaneous functions
* *SpNexus* - a hub storing *event handlers* and registered connections between a class and its user name (e.g. "default" logger and *SpDefaultLog* class)
* *SpState* - interface to current _state_ (see Reverting) - last patch times, original file copies, etc.
* *SpContext* - system object created when _patching_ or _reverting_ a particular file; contains list of instructions to be executed, last *find* match position, etc.

*Utility classes:*
* *SpLock* - base object for exclusive locks that are used, for example, when *SafePatch* applies patches
* *SpLog* - abstract logger - formats messages, implements buffering, etc.
* *SpFileLog* - file logger writing messages to `logs/<date>.log` or any other configured location; *SpDefaultLog* inherits this class
* *SpNativePatch* - static class parsing native `.sp` patches into *SpPatch* objects; *hooks* `get patch loader` event
* *SpStdValueFilters* - static class with standard instruction parameters like `bin` and `%PATCH%`; *hooks* `filter value` event

### Exceptions
*Exceptions* are inherited from `ESafePatch` that derives from standard PHP *[Exception](http://php.net/manual/en/class.exception.php)* class. `ESafePatch` contains one extra property - `srcObj` - that if not *null* references an object that has caused the exception.

*Other exception classes:*
* `ESpPatchFile` - problem regarding patch file loading
* `ESpPatching` - problem applying a patch
* `ESpOperation` - problem performing a patch operation (`SpOperation`)
* `ESpCommit` - problem saving patched or reverted changes

## Class relations
`SafePatch` is the starting point for any operations and a user (programmer) front-end. It handles _configuration loading_, _logging_, _locking_ and _states_ (last update times, patched states, etc.). It is used to _freshen_ patches in `patches/` against the actual application files.

`SafePatch` is the only object *dealing with locking*. This means that if you're calling `SpPatch->Revert()` (which isn't recommended to do) make sure you lock current *SafePatch* root or another request can begin altering it at the same time.

Core classes (`SpPatch`, `SpAffectedFile`, `SpOperation`) and `SpContext` reference `SafePatch` in their properties; other classes (excluding event-only `SpNativePatch` and `SpStdValueFilters`) are standalone and do not know about it.

Generally when it comes to _patching_ or _reverting_ the process is top-down:
* `SafePatch` - as an environment front-end it initiates the process by calling methods of oen or more connected `SpPatch` objects
* `SpPatch` represents a _patch_ at a whole and instead of containing instructions it contains _affected files_ (to be patched or reverted) and calls methods of corresponding `SpAffectedFile` objects
* `SpAffectedFile`, in turn, contains instructions to be performed and when given execution creates an *SpContext* object, fills it with instructions and calls it
* `SpContext` simply calls each instruction (`SpOperation`)
* `SpOperation`'s do the actual job of altering the string contained within the given `SpContext`

## API
_This section lists selected class fields._

### SafePatch object
* *Freshen()* - matches patches in `PatchPath()` with current target application data; unless `$force` is given will do nothing if patch files were not modified since last run. *Returns true* if patches are up-to-date or there were no patches; *false* if couldn't get the lock; *array* with applied patch file names (absolute) as _keys_ and error message (_string_) or *null* (on success) as _values_.
* *LastFreshenTime()* - returns _timestamp_ of the last `Freshen()` call or *null* if there were none.
* *GetPatches()* - returns patches existing in `PatchPath()`; it's an array of form `array('/abs/path.sp' => <patch>, ...)` where `<patch>` is either loader _callback_ (for [call_user_func()](http://php.net/manual/en/function.call-user-func.php)) or *SpPatch* object (if `$asObjects` is given).
 * if a patch has failed to load a log message is generated.
* *AppliedPatches()* - returns array of applied patches based on current _state information_; they do not necessary exist (`GetPatches()` might not return them). *Return format* is the same as `GetPatches()` except that _array keys_ are paths to saved _state info files_ (`state/patched/*.php`).
 * if a patch has failed to load a log message is generated.
* *OriginalFiles()* - returns array of backed up original files that were affected by previously applied patches of form: `array('path/file.ext' => '/abs/path/file.ext')`.

*Configuration-related* (path functions return with trailing slash):
* *PatchPath()* - path to patch files, e.g. `SP_ROOT/patches/`.
* *StateFN()* - path to a _state_ file, e.g. `SP_ROOT/state/freshness.php`; <font color="red">errors</font> if if given file name is unsafe (containing `..`).
* *OriginalFN()* - path to saved original version of the patched (affected) file; e.g. for `a/patched.file` (relative to _base path_) it will return `SP_ROOT/state/original/a/patched/file`. <font color="red">Errors</font> if if given path is unsafe (containing `..` as one of the components).

*Other:*
* *LoadConfig()* - loads configuration from passed _array_ or _string_ (file name relative to current directory); if none given loads from `SP_ROOT/config.php`.
* *LogPhpErrors()* - sets PHP handlers for logging PHP errors (`set_exception_handler()` and `register_shutdown_function` for tracking fatal errors); isn't required by any of the operations but still handy.
* *RelFrom()* (_static_) - converts given absolute file name into relative using given base path; <font color="red">errors</font> if the file name doesn't contain it. For example: `/abs/file/name.ext` - `/abs/` (base path) = `file/name.ext`.
* *Log()* - sends a string to the _logger_; can be passed an extra argument (an object, array or some other value) that will be included in the log message.
* *Logger()* - returns configured logger object (derived from `SpLog`)

### SpLog object
_*Tip:* `SafePatch->Logger()` returns assigned logger object._

* `$onLog` property - array of handlers to be call when a new log message is about to be written; each callbacks has this form: `function (&$msg, array &$extra, &$canLog, SpLog $log)`. If any callback *returns* non-*null* value all remaining handlers are skipped. *$canLog* is initially set to *true* - if it's *false* after all handlers were called the message isn't logged
* *All()* - returns an array with existing absolute log file names
* *AllWithTimes()* - returns an array of form `'/abs/file.log' => <timestamp>`, where *timestamp* is determined in two ways:
 * If [strptime()](http://php.net/manual/en/function.strptime.php) PHP function is available (not on _Windows_) log file name is attempted to be parsed using logPath configuration option;
 * If the above fails last file modification time ([filemtime()](http://php.net/manual/en/function.filemtime.php)) is used.

#### SpFileLog object
* *File()* - returns the absolute path of the current log file (with [strftime()](http://php.net/manual/en/function.strftime.php) strings resolved in logPath)
* *BasePath()* - guesses the base path (absolute) for the log files - removes any path components containing [strftime()](http://php.net/manual/en/function.strftime.php) format character (`%`) from logPath

### SpPatch object
* *ApplyTime()* - returns _timestamp_ of when this patch was applied; *null* if it wasn't yet; *false* if it was but the time is unknown (`state/freshness.php` is missing but `state/patched/<patch.sp>.php` exists - see _patch state_).
* *IsApplied()* - returns *true* if this patch was applied; checks both `freshness.php` and the _state file_ (see `ApplyTime()`)
* *IsStateOnly()* - returns *true* if original patch file was deleted from `patches/` after applying but its _state info_ is left
 * *attention:* don't confuse `IsStateOnly() === true` with `ApplyTime() === false` - the latter means that only the patch's entry in `state/freshness.php` is missing while its original file might still be present
* *FileCount()* - count of files affected by this patch.
* *Files()* - returns array of contained *SpAffectedFile* objects.
* *WildcardFileCount()* - see also `SpAffectedFile->IsWildcard()` and Syntax
* *RelFile()* - returns patch's file name relative to `SafePatch->PatchPath()`.
* *Log()* - calls assigned (<font color="red">errors if none</font>) `SafePatch->Log()`
* *Diff()* - returns array of form `array('/abs/affected/file.ext' => 'new data', ...)` - "new data" is either patched or reverted data (see `IsApplied()`).

*Filling:*
* *Info()* - access method setting or returning patch info value(s); when called without arguments returns an array with the following _standard_ keys (others might be defined; *null* values are not defined by the patch file) - see also Syntax:
 * *skip* - value of the _skip line_, if present
 * *caption*
 * *author*
 * *version* - _float_
 * *date* - _timestamp_
 * *homepage*
 * *email*
 * *headComment* - with possible line breaks; initial `#` and spaces are removed
* *AddFile()* - adds an *SpAffectedFile* into this patch.
* *GetSP()* - returns assigned `SafePatch` object; <font color="red">errors</font> if there's none.
* *SetSP()* - sets assigned `SafePatch` but only if passed argument isn't *null*; <font color="red">errors</font> if *null* was given and there was no `SafePatch` object set.

#### Applying and reverting
These are low-level methods which means that there is *no locking used*. If you're using them make sure to use `SafePatch->lock` or your own locking object (`SpLock`) or mechanism to prevent different processes from performing such operations simultaneously on one *SafePatch* root.
* *Apply()* - apply the patch; <font color="red">errors</font> if its _state info_ exists; *returns* array of form `'/abs/affected/file.ext' => array('new' => 'written data', 'orig' => 'original data')`
* *Revert()* - revert the patch; will operate in _stateless_ mode if there's no _state info_ available; see Reverting for the exmplanation; *return format* is the same as of `Apply()`
* *ApplyLogging()*, *RevertLogging()* - the same as `Apply()` and `Revert` but catch exceptions and log them; *return* either *an array* (on success - file/data pairs) or *a string* (error message that was logged)
* *Obliterate()* - remove saved _state info_ and entries from `state/freshness.php`, if there are any

### SpAffectedFile object
*Properties:*
* `$lineIndex` - line number (0-based) of the _header_ that has started the definition of this file in a parent patch file. *-1* if unavailable.
* `$fileName` - name of target (affected) file relative to _basePath_; can contain wildcards (`*` and `?`).

*Methods:*
* *IsWildcard()* - returns *true* if this file matches affected files based on a wildcard (thus it might match more than one file) - see Syntax
* *GetMatchingFiles()* - returns an array of currently existing _absolute_ file names matching this file's `$fileName` property (wildcards are resolved).
