Describes standard .sp patch file syntax.

<wiki:toc max_depth="1" />

## Structure

SafePatch uses plain text files for creating patches. Each file...
* must use *UTF-8 encoding* (unless it contains only [ASCII](http://en.wikipedia.org/wiki/ASCII) symbols)
* may use Unix (LF) or Windows (CR/LF) *line feeds*
* consists of *patch info header* and *patching instructions*

*`.sp` file structure* (content in square brackets is optional):
```
[... skip]
CAPTION by AUTHOR
[http://HOME]     [E@MA.IL]
[v1.0]   [from 23 Feb 2012]

# comment
== relative/path.php
instruction = value
another = instruction

== second/file.*
; alternative comment symbol
second file = instruction
...
```

*Comments* are lines appearing anywhere after the last line of the _patch info header_. They cannot have *leading whitespace*.
* comment lines appearing before the first _file block_ (`== file`) are part of *head comment* that describes the patch and appears on the patch info page.

**Blank lines** are ignored unless they appear in _instruction values_.

## Example
The following _safepatch_ hides "Silent edit" checkbox of the *FluxBB* engine:
```
Hide "Silent edit" by Proger_XP
http://proger.i-forge.net

== edit.php
find = <label><input type="checkbox" name="silent" value="1" tabindex="'.($cur_index++).'" />'.$lang_post['Silent edit'].'<br /></label>
add before = <!--
add = -->
```

## Header
*skip* - the first line might end on `skip` (note the leading space) - in this case the entire line is ignored. This can be used to turn on highlighting in text editors - for example, a PHP code patch can use `<?php skip`.

*CAPTION* is the name of this _safepatch_. It may contain any symbols except line feeds.

*AUTHOR* is the name of patch author. It may contain any symbols *except spaces* and line feeds.

After this optional lines follow; they can be given in any order (for example, _version_ might come before _homepage_).

* *http:**//HOME* defines patch webpage or some other related resource.
* *E@MA.IL* specifies related address. It can be written before or after the homepage as long as it's _space-separated_.
* *v1.0* defines patch version number, e.g. `v1.003` or `v13.5`.
* *from DATE* specifies the release date of this patch file of form `d[d] <short month> yyyy`, e.g. `11 Jan 2012` (numeric month is not used to avoid confusions between `DDMMYYYY`, `MMDDYYYY` and other national standards).

## Instruction blocks
_Patch info header_ is followed by actual _patch instructions_ grouped into _file blocks_. Each such block edits exactly one file which name is written after two or more _equals signs_ (`=`) - *the block header*. File name may contain *wildcard characters* (`*` and `?`).

After the _block header_ any number of *instructions* follow. They are defined in `key=value` form where *key* defines what to do and *value* defines which content to use for this.

### Value syntax
Instruction *value* can be _single line_ (specified after the equals sign) or _multi line_ that has *two forms*:

#### First
```
key = {
Multiline value follows
up to standalone "}".
}
```
* value begins with `{` followed by a line break and continues until the first `}` on a line by its own
* `}` can be referred to as-is by doubling (`}}`); the same works for `}}` (becomes `}}}}`) and so on:
```
key = {
Multiline value containing "}":
}}
...but still continuing
}
second key = ...
```

#### Second
```
key =
  Braceless value requires
  indentation. First _two_
    spaces are removed.
second key = ...
```
* value begins with a line break immediately following the _equals sign_
* blank lines are fine without indentation
* _trailing_ blank lines are removed; it's only possible to have them using the `{...}` form (on the left)

## Instructions
An _instruction_ is a `key=value` pair under the _file block_ (see above). *Key* is a set of space-separated _words_ (given in any order) one of which determines *operation to perform* and others are *parameters*.

```
try find anycase regexp = STRiNG.+FiND
add escaped = \x20to\x20append
```

The above example defines two instructions:
* the first attempts (_try_) to *find* the string matching case-insensitive (_anycase_) regular expression (_regexp_) `STRing.+FiND`
* the second adds string " to append" to the preceding *find* result - but only if it was found (without _try_ *find* will fail and the patching will be rolled back)

### find
This operation locates a block of text (or binary snippet) on which consequent *add*/*replace* instruction(s) operate. *Supported parameters:*
* *try* - unless specified *find* will fail if it couldn't locate given substring; this wil cause the patch to be rolled back
* *n,...* - search index(es) - comma-separated (`,`) integers with possible ranges (`start..end`); negative numbers means searching from the end of file; *zero (0)* is invalid and **will cause an error**. Examples:
 * `find 2 = hello` - locates _second_ occurrence of substring "hello"
 * `find anycase 1,3..-1 = xyz` - locates _first, third and all later_ occurrences of _caseless_ "xyz"
 * without *try*, *find* doesn't require all indexes to exist and won't error if at least one location was found
* *last* - alias for *-1* (one match from the end)
* *`*` (asterisk)* - alias for `1..-1` - finds all occurrences of given _string_ or *regexp*. _This is the default_
* *offset:n* - starts searching at given absolute (non-*utf8*) or character (*utf-8*) position; *n* can be _negative_ counting from the file's end. For example, `find offset:10 = hello` starts searching from the 11th byte. _Defaults to 0_
* *shift:n* - extends the match _n_ lines further (if positive) or backwards (if negative); for example, if a file contains lines "first", "second", "third" then `find shift:-1 = third` will match from the line feed _after_ the line "first" and until the "d" character of the line "third"
 * if _n_ is below 0 or exceeds the total lines it's set to the nearest valid value (file start or file end respectively).
* *regexp* - enables regular expression search ([PCRE](http://php.net/manual/en/book.pcre.php)); *value* must contain delimiters which can be: `/ ~ !` - if first char differs all *value* slashes are escaped (`\/`) and `/` delimiter is used. *For example*: `/reg+exp/si` (_DOT_ALL_ and _CASELESS_), `path/(sub/)+/file` (equals to `/path\/(sub\/)+\/file/`), `~path/[^/]*\.jpg~u` (_UTF8_ with _tilde_ as delimiter)
* *anycase*, *utf8* - affect the way _string_ (default) or *regexp* searches are done (in case-insensitive/UTF-8 modes respectively)

All *Substitutions* and *Value-converting parameters* are allowed as well.

### or
This operation is identical to *find* but is executed in chain:
* if there was a *successful match* all remaining *or* instructions (if any) are skipped;
* if there was *no match* in any *find*/*or* instructions and first *find* didn't include *try* **an error occurs** (patching is rolled back).

*or* accepts *all parameters* of *find* including *try*.

*add*/*replace* operations can be omitted after the initial *find* or following *or* (but not the last) - in this case once an item has matched first block of *add*/*replace* is executed, then remaining *or*'s are skipped.

*For example:*
```
find = Smart
or = Child
add = ish
or = ing
add before = Ski
or regexp = Power(less|ing)
replace = Kindness
```
* *4 clauses are defined* (the first must always be *find*, the rest - *or*): "Smart", "Child", "ing" and (as _regexp_) `Power(less|ing)`;
* if _the first_ or second is matched "ish" is appended;
* if _the third_ is matched "Ski" is prepended;
* if _the fourth_ is matched it's replaced with "Kindness";
* if *none have matched* **an error occurs**.

### add
This operation (one or more) follows *find*/*or* and does the actual file editing - adds intruction *value* (after the _equals sign_) before or after the located substring. *Supported parameters:*
* *before* - by default, *add* puts its _value_ after the located string; *before* puts it in front
* *first* - if there are multiple patches on the same snippet they are put in order they're ran (i.e. undetermined) one after another; *first* makes *add* put its _value_ before others in the same place
 * the order is undetermined if there are two matches running *add first* on the same snippet
 * **currently unimplemented, see issue #1**
* *last* - the same as *first* but works when *before* is given - puts _value_ after all patches of the same location
 * **currently unimplemented, see issue #1**
* *regexp* - if preceding *find*/*or* used *regexp* *add*'s value can contain _match pockets_ (`\0` - full match, `\1` - first match, etc.); _double backslash_ (`\\`) represents itself
 * _Value-converting parameters_ (such as *escaped*) are applied before *regexp*
* *utf8* - when *regexp* is used specifies that the operator's value is in UTF-8 encoding
* _positive number_ - if preceding *find*/*or* operation used *regexp* mode this specifies captured _pocket_ index (first pocket has index 1). For example, `find regexp = a(b|c)d` and `add 1 = !` results in initial "a" being followed by "!" ("a!bd" or "a!cd")
* _negative number_ - the same as above but counts from the end of all captured _pockets_ (`-1` = last one)

All *Substitutions* and *Value-converting parameters* are allowed as well.

### replace
This operation is very similar to *add* except that it replaces located substring with a different one. *Supported parameters:*
* _positive/negative number_ - specify captured pocket for a *regexp* *find*/*or* - see their description from the *add* operation. For example, `find regexp = a(b|c)d` and `replace 1 = !` results in "abd" and "acd" being replaced with "a!d"; without `1` both will be replaced with just "!".
* *regexp*, *utf8* - control usage of _captured pockets_ in the operator's value (`\0` - full match, etc.); see *add*'s description

All *Substitutions* and *Value-converting parameters* are allowed as well.

## Parameters

### Substitutions
These parameters replace substrings (usually of form `%VAR%`) in instruction *value* with something else. Unless specific `%VAR%` is listed in _instruction parameters_ no substitution occurs.

* *%PATCH%* - is replaced with relative path to the patch directory _without trailing path delimiter_ (`/` or `\`); usually this is the path to *SafePatch* root plus `patches/` plus the name of patch file without extension (`.sp` or other) - e.g. `/home/mysite/pub/safepatch/patches/my-patch`
 * when *%PATCH%* is used and this directory doen't exist **an error occurs**

*Example* that replaces loading of `utils.php` (in PHP) with loading of `fixed-utils.php` from the directory of the patch:
```
find regexp = include +'utils.php';
replace %PATCH% = include %PATCH%\fixed-utils.php;
```

### Value convertions
The following parameters can appear in any *operation* changing the way instruction *value* (after the _equals sign_) is treated.

_Value_ transformation is done in several phases, each may have no or just one _parameter_ of the same phase (e.g. `base64 enc:cp1251` is allowed but `bin base64` isn't).

* First phase:
 * *bin* - value is binary data in hexadecimal form, e.g.: `0D 0A 61 CD FF` - allowed symbols are `0-9 a-f A-F`; spaces are ignored
 * *base64* - value is in [Base64](http://en.wikipedia.org/wiki/Base64) format (62 = `+`, 63 = `/`)- useful for large chunks of binary data. Inappropriate symbols are ignored
 * *escaped* - value is a text string containing _C-style escape sequences_, e.g. `\x20` (ASCII space), `\32` (the same), `\n` (Line Feed). Note that *each backspace must be escaped* (`\\`)
* Next phase:
 * *enc:XXX* - converts string from UTF-8 into given charset (`XXX`); for PHP this needs *[php_iconv](http://php.net/manual/en/book.iconv.php)*
* Next phase:
 * *comment:XXX* - where `XXX` is *off* or file extension; if not *off* and this extension is defined in *addComments* configuration option *value* will be wrapped in the chosen comment style. For example, `add comment:php = echo 'hi';` will add something like `/* SP file.ext */` before and after `echo 'hi';`
