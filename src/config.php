<?php
//include_once SafePatch::Root().'vqmod.php';
//include_once SafePatch::Root().'fluxbb.php';

return array(
  'basePath' => '..',
  'ignore' => array('.svn/', '_svn/'),
  'ignorePatchFN' => '.-',
  'logType' => 'default',
  'logPath' => 'logs/%Y-%m-%d.log',
  'logMerge' => 3600 * 24 * 7,
  'onError' => 'skip',
  'addComments' => array('!php' => '/* $ */', '!html' => '<!-- $ -->'),
);