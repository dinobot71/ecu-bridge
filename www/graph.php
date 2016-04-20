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

<script type="text/javascript">

function graph_refresh() {

  console.log("[graph_refresh] starts...");

  var theKind   = false;
  var timeAgo   = false;
  var allSeries = false;
  
  /* 
   * disable all the buttons, so they can't mess
   * with us while we generate the graph...
   *
   */

  $('.btn-group button').attr('disabled', 'disabled');

  /* figure out the kind */
  
  theKind = $('.kindpicker .active').attr('kind');
    
  /* figure out the ago */
  
  timeAgo = $('.agopicker .active').attr('ago');
   
  /* figure out the series */

  allSeries = "";
  $(".seriespicker .active").each(function() {
    allSeries = allSeries + $(this).attr('series') + ",";
  });

  allSeries = allSeries.replace(/,$/,'');
  
  console.log("[graph_refresh] kind: " + theKind + " ago: " + timeAgo + " series: " + allSeries);
  
  /* get the graph */

  $.ajax({
    type:    "GET",
    url:     "/rest/ecubridge/Graph.json",
    data: {
      kind:   theKind, 
      ago:    timeAgo,
      series: allSeries
    },
    dataType: "json",

    success: function(data) {

      if(data.status != "OK") {
        console.log("[graph_refresh] problem fetching channel map: " + data.error);  
        return false;
      }

      /* get the URI for the image */
       
      var uri = data.data;

      console.log("[graph_refresh] loading image: " + uri);
      
      /* insert the image */
      
      var html = "<img src=\"" + uri + "\">";
      
      $('div#imagecontainer').html(html);

      /* re-enable the buttons */
      
      $('.btn-group button').removeAttr('disabled');
      
      return true;
    }
    
  }); 

}

$(document).ready(function () {

  /* setup the button click behaviors */
  
  $('#nav-item-graph').addClass('active');

  $('div.agopicker button').click(function() {

    var old = $(this).parent().find(".active");
    
    $(old).removeClass("active");
    $(old).removeClass("btn-success");
    $(old).addClass("btn-primary");
    
    $(this).addClass("active");
    $(this).addClass("btn-success");

    graph_refresh();
    
  });
  
  $('div.kindpicker button').click(function() {
    
    var old = $(this).parent().find(".active");
    
    $(old).removeClass("active");
    $(old).removeClass("btn-success");
    $(old).addClass("btn-primary");
    
    $(this).addClass("active");
    $(this).addClass("btn-success");

    graph_refresh();
    
  });

  $('div.seriespicker button').click(function() {
    
    /* toggle */
    
    if($(this).hasClass("active")) {

      /* if there is only one left, you can't turn it off */
      
      if($('div.seriespicker .active').length == 1) {
        return ;
      }
      
      /* if its already on, turn it off. */
      
      $(this).removeClass("active");
      $(this).removeClass("btn-success");
      $(this).addClass("btn-primary");
      
    } else {

      /* its not on yet, turn it on */

      $(this).addClass("active");
      $(this).addClass("btn-success");
      $(this).removeClass("btn-primary");
    }

    graph_refresh();
    
  });

  /* do the first refresh */

  graph_refresh();
  
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

<div class="col-xs-12 col-md-12">

<h3>Recent Data Capture</h3>

<p>Summary of the recent data passing through the ECU Bridge.  Use the 
options below to select what kind of data and how much to view.</p>

<div style="width: 240px; display: block; float: left;">
  <h3 style="width: 240px;">What Kind of Data?</h3>
  <div class="btn-group kindpicker" role="group">
    <button type="button" class="btn btn-primary" kind="raw">Raw</button>
    <button type="button" class="btn btn-primary" kind="normal">Normal</button>
    <button type="button" class="active btn btn-success" kind="output">Output</button>
  </div>
</div>

<div style="width: 400px; display: block; float: left;">
  <h3 style="width: 200px;">How Much Data?</h3>
  <div class="btn-group agopicker" role="group" aria-label="...">
    <button type="button" class="active btn btn-success" ago="300">5 min</button>
    <button type="button" class="btn btn-primary" ago="1800">30 min</button>
    <button type="button" class="btn btn-primary" ago="7200">2 hour</button>
    <button type="button" class="btn btn-primary" ago="14400">4 hour</button>
    <button type="button" class="btn btn-primary" ago="43200">12 hour</button>
  </div>
</div>

<div style="width: 800px; display: block; float: left;">
  <h3 style="width: 200px;">Which Series?</h3>
  <div class="btn-group seriespicker" role="group" aria-label="...">
    <button type="checkbox" class="active btn btn-success" series="rpm">RPM</button>
    <button type="checkbox" class="btn btn-primary" series="wheelspeed">Wheel Speed</button>
    <button type="checkbox" class="btn btn-primary" series="oilpressure">Oil Press.</button>
    <button type="checkbox" class="btn btn-primary" series="oiltemp">Oil Temp</button>
    <button type="checkbox" class="btn btn-primary" series="watertemp">Water Temp</button>
    <button type="checkbox" class="btn btn-primary" series="fuelpressure">Fuel Press.</button>
    <button type="checkbox" class="btn btn-primary" series="batteryvoltage">Batt Voltage</button>
    <button type="checkbox" class="btn btn-primary" series="throttleangle">Throttle Ang.</button>
    <button type="checkbox" class="btn btn-primary" series="manifoldpressure">Manif. Press.</button>
    <button type="checkbox" class="btn btn-primary" series="airchargetemp">Air Charge Temp</button>
    <button type="checkbox" class="btn btn-primary" series="exausttemp">Exaust Temp</button>
    <button type="checkbox" class="btn btn-primary" series="lambda">Lambda</button>
    <button type="checkbox" class="btn btn-primary" series="fueltemp">Fuel Temp</button>
    <button type="checkbox" class="btn btn-primary" series="gear">Gear</button>
    <button type="checkbox" class="btn btn-primary" series="errorflag">Error Flag</button>
  </div>
</div>

<div style="clear: both; height: 24px;"></div>
<div id="imagecontainer"></div>
<div style="clear: both; height: 24px;"></div>

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