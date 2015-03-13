<?php
return array(
  'spRoot' => '../',              // relative to admin/
  'spConfig' => 'config.php',
  'homeURL' => '../../',          // relative to admin/
  // any short text - it's shown on the left sidebar's top; HTML is allowed.
  'homeName' => '',
  'mediaURL' => '../display/',
  // a string; _blank will open some links in new tab, other (e.g. 'new') - open
  // links in one but not more new tabs; '' will open all links in current tab.
  'linkTarget' => '_blank',
  'defaultLang' => 'en',          // must exist as lang/*.php.
  'index' => 'patches',           // page to open as the panel main page.
  'sessionLength' => 3600,        // sec.
  'showErrors' => false,
  'trackRequests' => true,        // adding of new entries to admin/requests.log.
  // GZ output compression (ob_gzhandler()); when 'showErrors' is true and page
  // yields PHP errors/warnings browser may report unsupported page compression.
  'compressOutput' => true,

  'menu' => array(
    '=patches' => array('Patches'),
    '=changes' => array('Changed files'),
    '=logs' => array('View logs'),
  ),
);