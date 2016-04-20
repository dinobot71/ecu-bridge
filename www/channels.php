<?php 

/**
 * 
 * index.php - home page for chump car.
 * 
 * @package chumpcar
 * 
 * @author Little m Design (Michael Garvin)
 * @copyright Copyright (c) 2016-, Littl m Design
 * 
 */

?>

<?php 

/* system config */

require_once("configure.php");

/* document start */

include("html-leader.html");

/* begin of <head> */

include(TMPLT_PARTS.DS."/header-begin.html");

/* vvv enqueued scripts/css etc. vvv */

?>

<!-- Custom <head> contents starts -->

<!- include our own general utility code -->

<script type="text/javascript" src="/js/channels.js"></script>

<script type="text/javascript">

$(document).ready(function () {
  $('#nav-item-channels').addClass('active');
});

channels_refresh();

</script>



<!-- Custom <head> contents ends -->

<?php

  /* end <head> */

  include(TMPLT_PARTS.DS."/header-end.html");
  
  /* start the page */
  
  include(TMPLT_PARTS.DS."/page-begin.html");
?>

  <!-- vvvv start of custom content vvv -->

<div class="col-xs-10 col-md-10">

<h3>Channel Magagement</h3>

<p>Use controls below to manually configure channel filters or swap the order of
inputs to output.</p>

<p><strong>NOTE: any changes you make here will only last until the next restart of the ECU Bridge.</strong></p>

<div id="channelmap"></div>

<br/>
<br/>
<br/>
<br/>
<br/>
<br/>
<br/>
<br/>
<br/>
<br/>
<br/>
<br/>
<br/>
<br/>
<br/>
<br/>
<br/>
<br/>
</div>

  <!-- ^^^^ end of custom content ^^^^ -->
  
<?php

  /* end the page */

  include(TMPLT_PARTS.DS."/page-end.html");

?>