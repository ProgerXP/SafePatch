<?php
  isset($windowTitle) or $windowTitle = '';
  isset($mediaURL) or $mediaURL = '../display/';
  isset($languages) or $languages = array();
  isset($sidebar) or $sidebar = array();
  isset($page) or $page = '';
  isset($header) or $header = array();
  isset($footer) or $footer = array();
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">
  <head>
    <title><?php echo $windowTitle, $windowTitle === '' ? '' : '&ndash;'?> SafePatch <?php printf('%.1f', SafePatchVersion)?></title>

    <meta content="text/html; charset=utf-8" http-equiv="Content-Type" />

    <meta name="generator" content="SafePatch <?php printf($T['panelVersion'], '', SafePatchVersion, '', SafePatchBuild)?>" />

    <link rel="icon" type="image/vnd.microsoft.icon" href="<?php echo $mediaURL?>favicon.ico" />
    <link rel="shortcut icon" type="image/x-icon" href="<?php echo $mediaURL?>favicon.ico" />

    <link rel="stylesheet" href="<?php echo $mediaURL?>admin.css" type="text/css" />
  </head>
  <body class="<?php echo "$page ", $sidebar ? 'sidebar' : 'no-sidebar'?>">
    <div id="top"></div>

    <div id="wrapper">
      <div id="header">
        <div class="inner">
          <p class="title">
            <?php
              $pf = '<a class="sp-home" href="'.SafePatchHomePage.'"'.HtmlTarget().'>';
              printf($T['panelTitle'], $pf, '</a>');
            ?>
          </p>
          <p class="version">
            <?php
              printf($T['panelVersion'], '<strong>', SafePatchVersion, '</strong>',
                     SafePatchBuild);
            ?>
          </p>
          <p class="motto"><?php printf($T['panelMotto'], '<br />')?></p>

          <p class="menu">
            <?php
              $menu = array('http://safepatch.i-forge.net/w/list' => array('Wiki'),
                            'http://safepatch.i-forge.net/issues/list' => array('Bugs'),
                            'http://safepatch.i-forge.net/source/checkout' => array('SVN'));

              foreach ($menu as $url => $item) {
                list($name) = $item;

                $isCur = strpos(basename( $_SERVER['SCRIPT_FILENAME'] ), $url) !== false;

                $class = $isCur ? ' class="cur"' : '';
                $target = HtmlTargetIf(strpos($url, '://'));

                echo '<a href="', $url, '"', $class, $target, '>',
                     $T['panelMenu'.$name], '</a> ';
              }
            ?>
          </p>

          <?php
            if (count($languages) > 1) {
              echo '<p class="languages">';

                foreach ($languages as $code => $lang) {
                  $url = $mediaURL."lang/$code.gif";
                  $class = $lang['isCurrent'] ? ' class="cur"' : '';

                  echo '<a', $class, ' href="', QuoteHTML($lang['url']), '">',
                       '<span></span><img src="'.$url.'" alt="'.$code.'" /></a>';
                }

              echo '</p>';
            }
          ?>
        </div>
      </div>
      <div id="headerBorder"></div>

      <div id="page">
        <?php OutputBlocks('id="bodyHeader"', 'header', $header)?>

        <div id="body">
          <?php echo $body?>
          <?php OutputBlocks('id="bodyFooter"', 'footer', $footer)?>
        </div>

        <?php
          $nav = '<p><a href="#top">▲</a><a href="#bottom">▼</a></p>';
          $sidebar[] = array('class' => 'pageNav', 'body' => $nav);

          OutputBlocks('id="left"', 'sidebar', $sidebar);
        ?>
      </div>
    </div>

    <div id="bottom"></div>

    <script type="text/javascript">
      function GetScrollY() {
        return self.pageYOffset || (document.documentElement && document.documentElement.scrollTop) ||
               (document.body && document.body.scrollTop);
      }

      function MoveSidebar() {
        var sidebar = document.getElementById('left');
        if (sidebar) {
          if (!sidebar._OT) { sidebar._OT = sidebar.offsetTop; }
          sidebar.style.top = Math.max(20, sidebar._OT - GetScrollY()) + 'px';
        }
      }

      var oldScroll = window.onscroll || (function () { });
      window.onscroll = function () { MoveSidebar(); return oldScroll(); }
      MoveSidebar();
    </script>
  </body>
</html>