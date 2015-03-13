/* -- TWO/THREE COLUMNS ADJUSTMENT -- */

function WindowWidth() {
  if (window.innerWidth) {  // Non-IE
    return window.innerWidth;
  } else if (document.documentElement && document.documentElement.clientWidth) {
    // IE 6+ in 'standards compliance mode'
    return document.documentElement.clientWidth;
  } else if (document.body) {       // IE 4 compatible
    return document.body.clientWidth;
  }
}

function Adjust() {
  function DoAdjust(id, add) {
    var node = document.getElementById(id);
    node.className = node.className.replace(/\s*three\b/, '');
    if (add) { node.className += ' three'; }
  }

  DoAdjust('appliedPatches', WindowWidth() >= 1300);
  DoAdjust('availablePatches', WindowWidth() >= 1300);
}

var oldResize = window.onresize || (function () { });
window.onresize = function () { Adjust(); return oldResize(); }
Adjust();

/* -- UPLOAD FORM FILEDROP -- */


function SetupFileDrop() {
  if (typeof fd != 'object') { return; }

  var postURL = '?page=upload&filedrop=1';
  var errorNode;

  function OnDone(resp) {
    if (resp.responseText[0] == '!') {
      if (!errorNode) {
        errorNode = document.createElement('div');
        errorNode.className = 'fd-error';

        var zone = fd.ByID('uploadZone');
            zone.parentNode.insertBefore(errorNode, zone.nextSibling);
      } else {
        errorNode.style.display = 'block';
      }

      errorNode.innerHTML = resp.responseText.substr(1);
    } else {
      if (errorNode) { errorNode.style.display = 'none'; }
      location.href = resp.responseText;
    }
  }

  fd.ByID('uploadForm').style.display = 'none';
  fd.ByID('uploadZone').style.display = 'block';

  var zone = new FileDrop('uploadZone', {iframe: {url: postURL}});
      zone.on.iframeDone.push(OnDone);

  zone.on.send.push(function (files) {
    for (var i = 0; i < files.length; i++) {
      files[i].on.done.push(OnDone);

      var url = postURL + '&preview=' + (fd.ByID('fdPreview').checked ? 1 : 0);
      files[i].SendTo(url);
    }
  });
}

if (window.fd) {
  SetupFileDrop();
} else {
  var oldLoad = window.onload || (function () { });
  window.onload = function () { SetupFileDrop(); return oldLoad(); }
}
