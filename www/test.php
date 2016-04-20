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

<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
<meta http-equiv="Pragma" content="no-cache" />
<meta http-equiv="Expires" content="0" />

<script type="text/javascript" src="/js/3rdparty/jquery-knob-1.2.13/js/jquery.knob.js"></script>

<script type="text/javascript">

function test_setfilter(element) {

  /* get the parameters */
  
  var aSide    = $(element).parents('.dialcontent').attr('filter-side');
  var aKind    = $(element).parents('.dialcontent').attr('filter-kind');
  var aValue   = $(element).val();
  var aChannel = parseInt($(element).parents('.dialcontent').attr('filter-channel'));
  var aSrc     = parseInt($(element).parents('.dialcontent').attr('filter-src'));
  var aDst     = parseInt($(element).parents('.dialcontent').attr('filter-dst'));

  /* change the filter */
  
  $.ajax({
    type:    "POST",
    url:     "/rest/ecubridge/SetFilter.json",
    data:    {
      channel: aSrc,
      side:    aSide,
      kind:    aKind,
      value:   aValue
    },
    dataType: "json",

    success: function(data) {

      if(data.status != "OK") {
        console.log("[test_setfilter] problem fetching channel map: " + data.error);  
        return false;
      }
      
      return true;
    }
    
  }); 
   
}

$(document).ready(function () {
  
  $('#nav-item-test').addClass('active');

  /* enable the dials */

  $(".knob").each(function(index) {

    var item = $(this);
    
    $(this).knob({
      
      'release' : function (v) { 
        $(item).parent().attr('filter-value', v);
        test_setfilter(item);
      },
      
      'format' : function (value) {
        return value;
      }
      
    });
    
  });
  
});

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

<h3>Output Test</h3>

<p>Use the controls below to change (real-time) the outputs going to the Solo DL.</p>

<p><strong>NOTE: any changes you make here will only last until the next restart of the ECU Bridge.</strong></p>


<?php 

/* 
 * do the inital layout of the dials.  Once the dials on the page,
 * then we just respond to changes by manually setting the input 
 * fitler on the ECU Bridge for that dial (channel).
 * 
 */

autorequire('chumpcar\ecubridge\ECUBridge');

$cmdr = new ECUBridge;

if(!$cmdr->isReady()) {

  echo "<h3>ERROR: can not talk to ECU Bridge, please consult logs.</h3>\n";

} else {
  
  $map = $cmdr->map();
  
  if(!$map) {
    
    echo "<h3>ERROR: can not get channel map, please consult logs.</h3>\n";
    
  } else {
    
    /* walk through the channels and layout the dials as needed. */
    
    $i = 0;
    foreach($map as $idx => $row) {
      
      $i++;
      
      $it  = $row[0];
      $if  = $row[1];
      $src = $row[2];
      $dst = $row[3];
      $of  = $row[4];
      $ot  = $row[5];
      
      $value = 0;
      $min   = 0;
      $max   = 100;
      
      if(($ot == "rpm")||($ot == "RPM")) {
        $min   = 1000;
        $max   = 10000;
        $value = 1000;
      }
      
      $matches = array();
      if(preg_match('/\((\d+)\)/', $if, $matches)) {
        $value = $matches[1];
      }
      
      $html  = "";
      $html .= "<div class=\"dialblock\">\n";
      
      $html .= "<div class=\"diallabel\">\n";
      $html .= "<label>$ot ($it)</label>\n";
      $html .= "</div>\n";
      
      $html .= "<div class=\"dialcontent\" filter-side=\"input\" filter-channel=\"$i\" filter-src=\"$src\" filter-dst=\"$dst\" filter-kind=\"Manual\" filter-value=\"0\" >\n";
      $html .= "<input class=\"knob\" data-min=\"$nin\" data-max=\"$max\" data-fgColor=\"#E86F0C\" data-displayInput=\"true\" data-thickness=\".3\" data-width=\"200\" value=\"$value\">\n";
      $html .= "</div>\n";
      
      $html .= "</div>\n";
      
      echo $html;
    }
    
  }
  
}

?>

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