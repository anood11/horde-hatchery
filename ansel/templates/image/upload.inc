<div class="header" id="galleryHeader"><span class="breadcrumbs"><?php echo $breadcrumbs ?></span></div>
<table width="100%" cellspacing="0">
<tr><td style="vertical-align:top;padding:0;">
<?php
$form->renderActive(null, null, 'upload.php', 'post', 'multipart/form-data');
if ($browser->getPlatform() == 'win' && Horde::showService('help')) {
    echo '<div class="box" style="margin:8px; padding:8px"><h3>' . Horde_Help::link('ansel', 'xppublish') . ' ' . _("Learn how to publish photos directly from Windows.") . '</h3></div>';
}

if (!empty($haveImages)) {
    Horde::addScriptFile('effects.js', 'horde');
    Horde::addScriptFile('carousel.js', 'ansel');
}

$graphic_dir = Horde::applicationUrl($GLOBALS['registry']->getImageDir(), true);
$lbcssurl = Horde::url($GLOBALS['registry']->get('themesuri', 'ansel') . '/lightbox.css', true);
?>
</td>
<td style="vertical-align:top;width:40%;padding:0;">
  <div class="header">&nbsp;</div>
  <div class="anselWidgets">
    <div id="horizontal_carousel">
     <div class="anselWidget" style="height:150px;">
      <h2 class="header tagTitle"><?php echo _("Photos in this gallery") . ' (' . $gallery->countImages() . ')' ?></h2>
      <br />
      <div class="previous_button"></div>
      <div class="container">
       <ul></ul>
       <div id="spinner" style="display: none;"><?php echo _("Loading ...") ?></div>
      </div>
      <div class="next_button"></div>
     </div>
    </div>
  </div>
</td>
</tr></table>
<?php if (!empty($haveImages)):?>
<script type="text/javascript">
// <![CDATA[
// Delay response
Ajax.Request.prototype.originalInitialize = Ajax.Request.prototype.initialize;
Ajax.Request.prototype.initialize = function(url, options) {
    options.onSuccess = options.onSuccess.wrap(function(proceed, request, json) {
        proceed.curry(request, json).delay(1);
    });
    this.originalInitialize(url, options);
}

// Mock ajax response
Ajax.Response.prototype._getHeaderJSON = function() {
    var nbElements = <?php echo $gallery->countImages() ?>;
    var from = this.request.parameters.from;
    var to   = Math.min(nbElements, this.request.parameters.to);
    return {html: this.responseText, from: from, to: to, more: to != nbElements};
}

var carousel = null;

function runTest() {
    updateCarouselSize();
    carousel = new UI.Ajax.Carousel("horizontal_carousel", {url: "<?php echo Horde_Util::addParameter(Horde::applicationUrl('img/upload_preview.php'), 'gallery', $gallery->id) ?>", elementSize: 90})
        .observe("request:started", function() {
            $('spinner').show().morph("opacity:0.8", {duration:0.5});
        })
        .observe("request:ended", function() {
            $('spinner').morph("opacity:0", {duration:0.5, afterFinish: function(obj) { obj.element.hide(); }});
        });
}

function resized() {
    updateCarouselSize();
    if (carousel)
        carousel.updateSize();
}

function updateCarouselSize() {
    // Get the width of the viewport, then match the percentage of the
    // table cell the carousel appears in. We can't do this by referencing the
    // carousel's parent because that won't be sized correctly until the
    // carousel is sized correctly. Classic chicken and egg....
    var dim = document.viewport.getDimensions();
    dim.width *= .4;

    // size the carousel
    $("horizontal_carousel").style.width = (Math.floor(dim.width/90) * 90) + "px";
    $$("#horizontal_carousel .container").first().style.width =  (Math.floor((dim.width - 100) / 90)) * 90 +  "px";
}
Event.observe(window, "load", runTest);
Event.observe(window, "resize", resized);
// ]]>
</script>

<style type="text/css">
/* Horizontal Carousel */
#horizontal_carousel {
  float: left;
  width: 100%;
  height: 50px;
  padding: 3px;
  position: relative;
}

#horizontal_carousel .container {
  float: left;
  width: 100%;
  position: relative;
  overflow: hidden;
}

#horizontal_carousel ul {
  margin: 0;
  padding: 0;
  width: 100000px;
  position: relative;
  top: 0;
  left: 0;
}

#horizontal_carousel ul li {
  width:90px;
  text-align: center;
  list-style: none;
  float: left;
}

#horizontal_carousel .previous_button {
  float:left;
  margin: 5px;
  padding: 2px;
  width: 16px;
  height: 16px;
  background: url(<?php echo $registry->getImageDir() ?>/slideshow_prev.png);
  background-repeat: no-repeat;
  z-index: 100;
  cursor: pointer;
}

#horizontal_carousel .previous_button_over {
  background-color: #e9e9e9;
}

#horizontal_carousel .previous_button_disabled {
  cursor: crosshair;
}

#horizontal_carousel .next_button {
  float:left;
  margin: 5px;
  padding: 2px;
  width: 16px;
  height: 16px;
  background: url(<?php echo $registry->getImageDir() ?>/slideshow_next.png);
  background-repeat: no-repeat;
  z-index: 100;
  cursor: pointer;
}

#horizontal_carousel .next_button_over {
  background-color: #e9e9e9;
}

#horizontal_carousel .next_button_disabled {
  cursor: crosshair;
}

#spinner {
  position: absolute;
  top: 0px;
  left: 50px;
  width: 100%;
  background: #FFF;
  z-index: 10;
  text-align: center;
  font-size: 150%;
  opacity: 0;
}

</style>
<?php endif;?>
