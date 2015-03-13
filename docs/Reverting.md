Explains "patch state" and "position delta" and the two ways of reverting patch changes.

*Patch state* is information that *SafePatch* saves after it has successfully applied a patch. It is stored in `state/patched/<patch.sp>.php` and is used to revert changes done by the patch.

When this information is present original patch file (`.sp` or other) can be different from the one used to apply the patch or be missing altogether.

When _patch state_ is unavailable (this can be if patched files were copied from one system to another or if the changes were done manually) *SafePatch* attempts to reconstruct it using current version of the patch file during so-called *stateless revert*.

This works best for simple instructions (like find and add). Some parameters (like *regexp* modifier for *find*) make it impossible to know what changes were done exactly and such instructions are skipped. Also, or instructions are always ran even if preceding *find*/*or* has matched.

Generally, it's safe to assume that if _state information_ is *available* the patch will be properly reverted while *if there's none* it might be reverted incorrectly depending on patch complexity and the file being reversed (number of similar lines, etc.).

When reverting using _state information_ checks are made to avoid removing wrong snippets in case of file changes between the patching and reverting. This makes it more stable and less prone to software updates and manual edits.

For example, if *add* operation doesn't see the previously added snippet (`alterStr`) at the stored file position (`alterPos`) it attempts to find it somewhere near that, also looking for the old match (of the preceding *find* that worked when initially applying the patch - `matchStr`) and if none found not doing anything.

_Overall *reverting is much more tolerable* and cases that would cause an error when patching will be logged and silently skipped here._

### Position delta
When *SafePatch* applies patched it keeps track of their modifications so that already applied patches (with available _state info_) will appropriately shift their offsets.

For example, imagine two patches operating on the same file: the first adds a string, then the second adds another string - but before the snippet changed by the first patch. The second patch saves its _state_ with proper offsets but the first now has its own shifted:

>Original file:
```
<p>First paragraph.</p>
<p>Second paragraph.</p>
```

>After applying the first patch:
```
<p>First paragraph.</p>
<!--<p>Second paragraph.</p>-->
```

>The added `<!--` is located at the *offset 24*. Now the second patch which comments out the first paragraph is applied:
```
<!--<p>First paragraph.</p>-->
<!--<p>Second paragraph.</p>-->
```

>Now previously added `<!--` is located *7 bytes further* - at 31 but is still recorded as 24 in the first patch's _state_ file.

To workaround this *delta mechanism* is used: when a new patch is applied a special _delta string_ of the following form is appended to all existing _patch state files_:
```
affected/file.ext|delta@offset|file.2|...
```

>For example, the above HTML file (here named `myfile.html`) will have this _delta string_ after applying the first patch:
```
myfile.html|4@24|myfile.html|3@52
```

>`delta` can be either positive or negative; zero deltas are not written.

_Delta strings_ are more effective than updating each _state file_ because they are only updated when they are used - usually when the patch is reverted. Thus applying multiple patches has the same performance effect as applying just one patch because appending data to a file is usually very fast.
