<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html
    xmlns="http://www.w3.org/1999/xhtml"
    xml:lang="en"
    lang="en">

  <head>
    <meta
        http-equiv="Content-Type"
        content="text/html; charset=<?php echo $this->conf['charset'] ?>"
        />

<title>
     <?php
     if (isset($this->conf['title'])) {
         $title = $this->conf['title'];
     } else {
         $title = '';
     }
     if (!empty($this->controllerTitle)) {
         $title .= ' - ' . $this->controllerTitle;
     }
     echo $title;
     ?>
</title>

<?php
if (isset($this->conf['favicon'])):
?>
    <link
    rel="icon"
    type="image/png"
    href="<?php echo $this->webroot . '/' . $this->conf['favicon'] ?>"
    />
<?php endif; ?>

<?php
if (!empty($this->conf['css'])):
     foreach ($this->conf['css'] as $css):
?>
        <link
        rel="stylesheet"
        type="text/css"
        href="<?php echo $this->webroot . '/' . $css ?>"
        />
    <?php endforeach; ?>
<?php endif; ?>

<?php
if (!empty($this->controllerCss)):
     foreach ($this->controllerCss as $css):
?>
    $for i in page.css:
        <link
        rel="stylesheet"
        type="text/css"
        href="<?php echo $css ?>"
        />
    <?php endforeach; ?>
<?php endif; ?>

<?php
if (!empty($this->appJs)):
     foreach ($this->appJs as $js):
?>
        <script
        type="text/javascript"
        src="<?php echo $js ?>">
        </script>
    <?php endforeach; ?>
<?php endif; ?>
<?php
if (!empty($this->controllerJs)):
     foreach ($this->controllerJs as $js):
?>
        <script
        type="text/javascript"
        src="<?php echo $js ?>">
        </script>
    <?php endforeach; ?>
<?php endif; ?>

  </head>

  <body>
<!--
$if (website.body_tags or page.body_tags):
    $for i in sets.Set(website.body_tags.keys() + page.body_tags.keys()):
        $var cmd: ''
        $if i in website.body_tags.keys():
            $var cmd: cmd + website.body_tags[i]
        $if i in page.body_tags.keys():
            $var cmd: cmd + page.body_tags[i]
        $i="$cmd"        
-->
    <div
       id="topbg">
    </div>

    <div 
       id="h0">
      <div 
	 id="h1">
	<div 
	   id="h11">
	  <img 
	     id="h11i"
	     src="/static/img/head_1.png" 
	     />
	</div>
      </div>

      <div 
	 id="h2">
	<div 
	   id="h12">
	  <img 
	     id="h21i"
	     src="/static/img/head_2.png" 
	     />
	</div>
      </div>
    </div>

    <div
       id="topmenu">

      <!-- MENU START -->

      <div
	 id="menubase">
<!--	  $:menus['base'] -->
      </div>
      
      <!-- MENU END -->
    </div>

      
    <div 
       id="h4">
    </div>

    <div
       class="body">

<!-- $:page.render(renderer) -->
Test
    </div>

<!-- $if 'footer' in page.elements.keys():
    $:page.elements['footer'].render(renderer)
-->

  </body>

</html>
