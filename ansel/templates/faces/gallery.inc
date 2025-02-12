<h1 class="header"><?php echo $title ?></h1>
<?php echo $pager->render() ?>
<table class="striped" style="width: 100%">
 <tr>
    <th><?php echo _("Filename") ?></th>
    <th><?php echo _("Photo") ?></th>
    <th><?php echo _("Faces") ?></th>
 </tr>
 <?php
 $imageids = array();
 foreach ($images as $image):
    $imageids[$image->id] = $image->id;
 ?>
    <tr>
     <td style="text-align: center">
     <?php echo $image->filename; ?><br /><br />
     <?php if ($autogenerate): ?>
      <a href="javascript:void(0)" onclick="proccessImageFaces(<?php echo $image->id ?>, 1)">
      <img src="<?php echo $reloadimage ?>" alt="[icon]" /><?php echo _("Reget faces for this photo") ?></a><br />
     <?php endif; ?>

     <a href="<?php echo Horde_Util::addParameter($customurl, array('image' => $image->id, 'gallery' => $image->gallery)) ?>">
     <img src="<?php echo $customimage ?>" alt="[icon]" /><?php echo _("Add a custom face range") ?></a><br />
    </td>
    <td style="text-align: center">
    <a href="<?php echo Ansel::getUrlFor('view', array('view' => 'Image', 'image' => $image->id, 'gallery' => $image->gallery)); ?>" target="_blank">
    <img src="<?php echo Ansel::getImageUrl($image->id, 'thumb'); ?>" alt="<?php echo htmlspecialchars($image->filename)?>" /></a></td>
    <td><div id="processface<?php echo $image->id ?>" name="processface<?php echo $image->id ?>"><?php echo _("Loading...") ?></div></td>
    </tr>
 <?php endforeach; ?>
</table>
<?php echo $pager->render() ?>
<script type="text/javascript">
//<![CDATA[
var imgids = <?php echo Horde_Serialize::serialize($imageids, Horde_Serialize::JSON, Horde_Nls::getCharset()); ?>;

function proccessImageFaces(image_id, reget) {
    var url = '<?php echo Horde::applicationUrl('faces/image.php'); ?>';
    $('processface' + image_id).innerHTML = '<?php echo _("Loading...") ?>';
    var params = new Object();
    params.reload = reget;
    params.image = image_id;
    new Ajax.Updater({success:'processface' + image_id},
                     url,
                     {
                        method: 'post',
                        parameters: params
                      });

}

function deleteFace(image_id, face_id) {
    var url = '<?php echo Horde_Util::addParameter(Horde::applicationUrl('faces/delete.php'), 'face', null, false); ?>';
    new Ajax.Request(url + face_id + '&image=' + image_id);
    $('face' + face_id).remove();
}

function setFaceName(image_id, face_id) {
    var params = 'action=setname/face=' + face_id + '/image=' + image_id + '/facename=' + encodeURIComponent($F('facename' + face_id));
    var url = '<?php echo Horde::applicationUrl(Horde_Util::addParameter('xrequest.php', array('requestType' => 'EditFaces'))); ?>';
    new Ajax.Updater({success: 'face' + face_id}, url + '/' + params);
}

Event.observe(window, 'load', function() {
    for (i in imgids) {
        proccessImageFaces(i, 0);
    };
});

//]]>
</script>
