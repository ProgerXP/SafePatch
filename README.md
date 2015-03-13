**SafePatch lets you edit program files and track those changes creating "patches" that can later be reverted or reused in a different setup.** Standalone PHP patches can be built providing automated installation and reverting of the bundled _safepatch_.

Multiple patches are merged together gracefully. Patching is atomic (transactional) so that either it is fully completed or all files are left unchanged. <br />
Intuitive text patch file format (`.sp`) is used but others (VQMod `.xml`, FluxBB `.txt`) can be used as well if the corresponding loader is written.

**Non-PHP applications can use SafePatch** since no source code integration is required (albeit it's possible). Both **text and binary files** are supported.

This project has been migrated from Google Code.

## Features
* dependencies-free self-contained core (`safepatch.php`)
* **web admin panel** for managing installed patched, tracking their changes (**diff**), etc.
* patch builds with a web interface to revert them - all in one PHP script
* atomic patching/reverting: if an error occurs (e.g. a file could not be written) all changes are rolled back
* stability: detection of changes done to the patch or patched files after the patch has been applied
* high-quality object-oriented code using exceptions instead of `die()`

_The original idea was inspired by **[VQMod](http://code.google.com/p/vqmod/)**._ Main differences are:
* **Files are edited in-place**:
 * Independence of the target application language (it might be _Python_, _C_ or anything else) because no file redirections need to be done.
 * Changes can be done directly to program files and they won't require cache update or anything else apart from changing the file itself.
 * **SafePatch** code can be removed and all changes will be kept.
* **XML engine** is not required to parse **SafePatch** files
* There's a [plugin](http://code.google.com/p/safepatch/source/browse/plugins/core/vqmod.php) to treat **VQMod** `.xml` patches as if they were in native **SafePatch** format - see Configuration for more details

## Getting started
**Installation process** is as straightforward as it can be: download the latest version and extract it somewhere on your server. _SafePatch_ should work with default settings if you give it write permissions to `logs/` and `state/` and read-only to the rest. Default configuration (**basePath**) assumes that files to be patched are located one level above the _SafePatch_ root.

You can now open the **control panel** (`http://yourhost.com/safepatch/admin/`) and start uploading patches or do other maintenance.

If you're familiar with PHP and the application you're attaching _SafePatch_ to is written in PHP too you can make it **automatically refresh patches** appearing in `patches/` by putting the following code at the beginning of `index.php` and/or other files that user requests from the Internet:
<code language="php">require 'safepatch_root/safepatch.php';</code>

  Don't forget to **limit access to the control panel** using [HTTP Authorization](http://httpd.apache.org/docs/2.0/howto/auth.html) (bundled `.htaccess` has commented-out directives for this), GuestMode plugin or some other means.

**Patching process** is also intuitive and can be done in two ways:
* **Manual** - upload patches (usually files with `.sp` extensions but might be `.xml` or `.txt`) into `safepatch_root/patches/`; they will be applied automatically if you're put the above PHP code into your web scripts or you can apply them manually via the control panel;
* **Automatic** - use the control panel's _Patches_ page to upload patches from your computer; they will be automatically stored in `patches/` and applied thereafter.

Keep in mind that it's also possible to **create standalone patch builds** that work like mini-control panels and don't require the target server to have _SafePatch_ installed - just upload the single file and open it in a web browser.

## Control panel
[https://raw.githubusercontent.com/ProgerXP/SafePatch/master/docs/screenshot.png]

## Installing FluxBB mods video demonstration
http://www.youtube.com/watch?v=uYbu_r75fy8
