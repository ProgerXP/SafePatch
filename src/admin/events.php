<?php
  SpNexus::Hook('page: 404', array('StandardEvents', 'Page404'));
  SpNexus::Hook('page: patches', array('PagePatches', 'Build'));
  SpNexus::Hook('page: upload', array('PageUpload', 'Build'));
  SpNexus::Hook('page: changes', array('PageChanges', 'Build'));
  SpNexus::Hook('page: logs', array('PageLogs', 'Build'));

  SpNexus::Hook('page: freshen', array('PagePatch', 'Build'));
  SpNexus::Hook('page: apply', array('PagePatch', 'Build'));
  SpNexus::Hook('page: revert', array('PagePatch', 'Build'));
  SpNexus::Hook('page: obliterate', array('PagePatch', 'Build'));
  SpNexus::Hook('page: diff', array('PagePatch', 'Build'));
  SpNexus::Hook('page: source', array('PagePatch', 'Build'));

  SpNexus::Hook('sidebar', array('StandardEvents', 'PermsSidebar'));
  SpNexus::Hook('sidebar', array('StandardEvents', 'StatsSidebar'));
  SpNexus::Hook('sidebar', array('StandardEvents', 'MenuSidebar'));

  SpNexus::Hook('on log', array('StandardEvents', 'OnLog'));
  SpNexus::Hook('footer', array('StandardEvents', 'LogsToFooter'));

  SpNexus::Hook('diff: html', array('QuickDiff', 'FullHtmlWrapped'));