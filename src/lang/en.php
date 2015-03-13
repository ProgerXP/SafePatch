<?php
return array(
  'locale' => 'en_US.UTF-8',

  'panel' => array(
    /* -- COMMON -- */

      'datetime' => 'j/m/Y \a\t H:i',
      'date' => 'j M Y',
      'time' => 'H:i',

      'by' => '%s by %s',
      'versionDate' => 'v%s, %s',
      'version' => 'v%s',

      'updateViewBtn' => 'Update',    // for example, a button to apply form filter.
      'resetViewBtn' => 'Reset',
      'confirmBtn' => 'Proceed',      // warning/info message confirmation button.

      'filedropLegend' => 'Drag & drop a file inside&hellip;',
      'filedropButton' => 'Or click here to <em>Browse</em>.',

      'nonExistingPatch' => 'Patch %s doesn\'t exist.',
      'nonExistingPatchApplied' => 'Patch %s doesn\'t exist or it wasn\'t applied.',

      'requestChangeDenied' => 'You are not authorized to perform this action (%s).',
      'cannotLock' => 'Cannot lock the <strong>SafePatch</strong> root &ndash; not proceeding.',

      'patchWillAffect' => 'Patch %s will change <strong>$</strong>.',
      'patchWillAffectNum' => '$ file, s, , s, s',
      'patchWontChange' => 'Patch %s won\'t change any file.',

    /* -- SYSTEM -- */

      'panelTitle' => '%sSafePatch%s control panel',
      'panelVersion' => '%sv%.1f%s r%s',
      'panelMotto' => 'Managable editing of program files%sregardless of language, platform and design',

      'panelMenuWiki' => 'Wiki',
      'panelMenuBugs' => 'Bugs',
      'panelMenuSVN' => 'SVN',

      'exceptionTitle' => 'An exception has occurred',
      'exception' => '<strong>%s</strong> in <kbd>%s</kbd> on line <strong>%s</strong> (page %s):%s',
      'exceptionTrace' => '<h2>Call trace</h2>%s',
      'exceptionBug' => 'If you believe this is a bug please %sfill in a report%s.',

      'invalidReqParams' => 'Invalid request parameters for this page (%s).',

      'statsFreshen' => '<strong>Freshen:</strong> %s.',
      'statsFreshenNever' => 'do now',
      'statsPatches' => '%s, %s.',
      'statsPatchesApplied' => '$ applied %spatch, es, , es, es',
      'statsPatchesAvailable' => '$ available, , , , ',
      'statsHome' => '<strong>%sHome%s:</strong> %s',
      'statsLastLogin' => '<strong>Last login:</strong> %s %s by %s from %s.',

      '404' => 'Requested page %s  (%s) doesn\'t exist.',

      'spBadFilePerms' => 'Invalid file permissions &ndash; might be insecure or cause malfunction:',
      'spNonReadable' => '%s must be <strong>read-only</strong> (current perms are %s)',
      'spNonWritable' => '%s must be <strong>readable and writable</strong> (current perms are %s)',

      'newLogTitle' => '$ new log message, s, , s, s',
      'newLogLegend' => 'More info is available in the %slog file%s.',

    /* -- PAGES -- */

    'patches' => array(
      'pageTitle' => 'Patches',
      'appliedTitle' => 'Applied patches',
      'availableTitle' => 'Availble patches',

      'noPatches' => 'No patches to list.',

      'uploadTitle' => 'Upload a new patch',
      'uploadApplyBtn' => 'Upload &amp; patch',
      'uploadPreviewBtn' => 'Preview',
      'uploadPreview' => 'Preview before patching.',

      'effectWildcard' => 'Affects <em>at least</em> $ &ndash; %sdiff%s.',
      'effectWildcardNum' => '$ file, s, , s, s',
      'effect' => 'Affects $ &ndash; %sdiff%s.',
      'effectNum' => '$ file, s, , s, s',

      'apply' => 'Patch',
      'missingFreshness' => 'Patched but the time is missing',
      'applied' => 'Patched on %s',
      'revert' => 'Revert',
      'tryReverting' => 'Try reverting',
      'obliterate' => 'Remove state',

      'stateOnly' => '%s was deleted from the patch directory.',
    ),

    'freshen' => array(
      'pageTitle' => 'Freshen patches',

      'noPatches' => 'There are no patches in patch directory %s.',
      'up-to-date' => 'All patches (%s) are already up-to-date.',
      'freshenedPatches' => 'Successfully freshened <strong>$</strong>:',
      'freshenedPatchesNum' => '$ patch, es, , es, es',
    ),

    'apply' => array(
      'pageTitle' => 'Applying patch',
      'done' => 'Successfully applied %s.',
      'doublePatching' => 'Attempted to double patch %s.',
    ),

    'revert' => array(
      'pageTitle' => 'Reverting patch',

      'revertingUnpatched' => 'You are trying to revert an unapplied patch.',
      'revertingUnpatchedLegend' => 'Usually this is safe but since there\'s no %sstate information%s available (it\'s created after patching) the reverting might be inaccurate or impossible - check the log after you\'re done.',

      'done' => 'Successfully reverted %s.',
    ),

    'obliterate' => array(
      'pageTitle' => 'Removing patch state',

      'done' => 'Successfully obliterated %s.',
      'legend' => 'Now this patch will appear as non-applied although its changes were not reverted.',

      'revertingUnpatched' => 'This will delete patch-time information only.',
      'revertingUnpatchedLegend' => 'The patch will be unregistered but its changes of the program files will be kept, not reverted.',
    ),

    'diff' => array(
      'applyTitle' => 'Patch diff after applying',
      'revertTitle' => 'Patch diff after reverting',
    ),

    'source' => array(
      'pageTitle' => 'Patch source',
      'unreadable' => 'Cannot read the patch file.',
    ),

    'upload' => array(
      'pageTitle' => 'Uploading new patch',

      'noUpload' => 'No upload data has been received.',
      'unsafeName' => 'Unsafe upload file name %s &ndash; not proceeding.',
      'destExists' => 'File %s already exists.',
      'cannotWrite' => 'Cannot write patch to %s.',
      'invalidFile' => 'The file you have uploaded doesn\'t look like a proper patch or it matches configured <kbd>ignorePatchFN</kbd>. It\'s been stored in %s.',
      'doApply' => 'Apply this patch',
    ),

    'changes' => array(
      'pageTitle' => 'Changed files',

      'legend' => 'This page either lists files changed by your patches or lists applied patches with their respective changes.',
      'noFilesChanged' => 'No files have been changed yet.',
      'noPatchChanges' => 'This patch didn\'t change any files.',

      'formGroup' => 'Group by:',
      'formGroupFiles' => 'files',
      'formGroupPatches' => 'patches',
      'formFileSort' => 'Sort files by:',
      'formFileSortTime' => 'last change time',
      'formFileSortNatural' => 'numeric name',
      'formFileSortName' => 'regular name',
      'formSortDesc' => 'Reversed order',

      'patchCaption' => '%s by %s on %s (%s) &ndash; %sdiff%s',
      'diff' => ' &ndash; %sdiff%s',
      'stateOnlyPatchesMsg' => '%sSome patches%s were deleted from patch directory without reverting their changes.',
    ),

    'logs' => array(
      'pageTitle' => 'Log files',

      'noLogs' => 'No log files to show. Log path is %s.',
      'noLogsCurrent' => 'New log messages will be written to %s.',
      'firstMsg' => ' (%s) &ndash; first message on %s &ndash; %sdelete this and older%s.',
      'lastMsg' => ' (%s) &ndash; last message on %s &ndash; %sdelete this and older%s.',

      'formSort' => 'Sort by:',
      'formSortTime' => 'last change time',
      'formSortNatural' => 'numeric file name',
      'formSortName' => 'regular file name',
      'formSortAsc' => 'Oldest go first',

      'viewTitle' => 'Log file view',
      'nonExistingLog' => 'Log file %s doesn\'t exist.',
      'emptyLog' => 'This log file is empty.',
      'msgOrder' => 'Old messages go first &ndash; %sscroll to the bottom%s.',

      'pruneTitle' => 'Pruning logs',
      'pruneError' => ' &ndash; <strong>error deleting file</strong>',
    ),
  )
);