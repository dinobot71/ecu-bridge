
function channels_swap(a,b) {

  console.log("[channels_swap] a: " + a + " b: " + b);
  
  /* change the filter */
  
  $.ajax({
    type:    "POST",
    url:     "/rest/ecubridge/SwapChannels.json",
    data:    {
      chan1: a,
      chan2: b
    },
    dataType: "json",

    success: function(data) {

      if(data.status != "OK") {
        console.log("[channels_swap] problem fetching channel map: " + data.error);  
        return false;
      }
       
      /* refresh the page */
      
      channels_refresh();
      
      return true;
    }
    
  }); 
  
  
  return true;
}

function channels_filterChanged(element) {
  
  /* get the parameters */
  
  var aSide    = $(element).parents('.input-group').attr('filter-side');
  var aKind    = $(element).parents('.input-group').attr('filter-kind');
  var aValue   = $(element).val();
  var aChannel = parseInt($(element).parents('.input-group').attr('filter-channel'));
  var aSrc     = parseInt($(element).parents('.input-group').attr('filter-src'));
  var aDst     = parseInt($(element).parents('.input-group').attr('filter-dst'));
  
  aValue = $.trim(aValue);
  
  var chan = aSrc;
  if(aSide == "output") {
    chan = aDst;
  }
  
  if (typeof aValue === "undefined") {
    $(element).parent('.input-group').attr('filter-value');
  }
    
  aValue = aValue.replace(/\D/g,'');
  if((!is_numeric(aValue)) || (aValue == "")) {
    aValue = 0;
  }
  
  console.log("[channels_filterChanged] c: " + aChannel + " src: " + aSrc+ " dst: " + aDst + " s: " + aSide + " k: " + aKind + " v: " + aValue + "...");

  /* change the filter */
  
  $.ajax({
    type:    "POST",
    url:     "/rest/ecubridge/SetFilter.json",
    data:    {
      channel: chan,
      side:    aSide,
      kind:    aKind,
      value:   aValue
    },
    dataType: "json",

    success: function(data) {

      if(data.status != "OK") {
        console.log("[channels_filterChanged] problem fetching channel map: " + data.error);  
        return false;
      }
       
      /* refresh the page */
      
      channels_refresh();
      
      return true;
    }
    
  }); 
  
  return true;
}

/**
 * 
 * channels_doLayout
 */

function channels_doLayout(data) {
  
  console.log("[channels_doLayout] starts...");
  
  var container = $('div#channelmap');
  
  /* clear the old stuff (if any) */
  
  $(container).html('');
  
  /* build channel map table */
  
  var html = "";
  
  html = html + "<table class=\"channeltable\" cellspacing=\"0\" cellpadding=\"0\">\n";
  
  for(var i=0; i<data.length; i++) {
  
    var row = data[i];
   
    row.src = $.trim(row.src);
    row.src = parseInt(row.src);
    
    row.dst = $.trim(row.dst);
    row.dst = parseInt(row.dst);
    
    /* row wrapper */
    
    html = html + "<tr>\n";
    
    /* channel # */
    
    html = html + "<td>";
    html = html + "<button title=\"Raw Input\" class=\"btn btn-primary\" type=\"button\">";
    html = html + "<span class=\"badge\">" + row.channel + "</span>";
    html = html + "</button>";
    html = html + "</td>";
    
    /* input transform (no modify) */
    
    html = html + "<td>";
    html = html + "<h3><span class=\"label label-primary\">" +  row.inputtransform + "</span></h3>";  
    html = html + "</td>";
    
    /* input filter */
    
    html = html + "<td>";
    
    var passSelect  = "";
    var nullSelect  = "";
    var manuSelect  = "";
    var actionName  = "";
    var inputClass  = "";
    var manualValue = "";
    
    if(/Passthrough/.test(row.inputfilter)) {
      
      passSelect = "selected=\"selected\"";
      actionName = "Passthrough";
      inputClass = "noinput";
      
    } else if(/Null/.test(row.inputfilter)) {
      
      nullSelect = "selected=\"selected\"";
      actionName = "Null";
      inputClass = "noinput";
    
    } else if(/Manual/.test(row.inputfilter)) {
      
      manSelect="selected=\"selected\"";
      actionName = "Manual";
      inputClass = "hasinput";
      
      /* yank out the manual value */
      
      var regex = /\((\d+)\)/;
      var match = regex.exec(row.inputfilter);
      
      manualValue = match[1];
    }
    
    html = html + "<div class=\"input-group inputfilter\" filter-channel=\"" + row.channel + "\" filter-src=\"" + row.src + "\" filter-dst=\"" + row.dst + "\" filter-side=\"input\" filter-kind=\"" + actionName + "\" filter-value=\"" + manualValue + "\">";
    html = html + "<div class=\"input-group-btn\">";
    html = html + "<button title=\"Input Filter, use Manual to set value.\"  type=\"button\" class=\"filterbtn btn btn-primary dropdown-toggle\" data-toggle=\"dropdown\" aria-haspopup=\"true\" aria-expanded=\"false\">" + actionName + " <span class=\"caret\"></span></button>";
    html = html + "<ul class=\"dropdown-menu\">";
    html = html + "<li><a class=\"dropaction\" href=\"#\" " + passSelect + ">Passthrough</a></li>";
    html = html + "<li><a class=\"dropaction\" href=\"#\" " + nullSelect + ">Null</a></li>";
    html = html + "<li><a class=\"dropaction\" href=\"#\" " + manuSelect + ">Manual</a></li>";
    html = html + "</ul>";
    html = html + "</div>";
    html = html + "<input title=\"Press <RETURN> to enter value\" type=\"text\" class=\"" + inputClass + " form-control\" aria-label=\"...\" value=\"" + manualValue + " \">";
    html = html + "</div>";
    
    html = html + "</td>";
    
    /* source channel (can swap) */
    
    html = html + "<td>";
    
    html = html + "<div class=\"dropdown\">";
    html = html + "<button title=\"Swap with another channel\" class=\"swapbtn btn btn-primary dropdown-toggle\" type=\"button\" id=\"dropdownMenu" + row.src + "\" data-toggle=\"dropdown\" aria-haspopup=\"true\" aria-expanded=\"true\">";
    html = html + row.src
    html = html + "<span class=\"caret\"></span>";
    html = html + "</button>";
    html = html + "<ul class=\"dropdown-menu\" aria-labelledby=\"dropdownMenu" + row.src + "\">";

    for(var zz=1; zz<=15; zz++) {
  
      if(zz == row.src) {
        continue;
      }
  
      html = html + "<li><a class=\"swapaction\" a=\"" + row.src + "\" b=\"" + zz + "\" href=\"#\">" + zz + "</a></li>";
    }

    html = html + "</ul>";
    html = html + "</div>";
    
    
    html = html + "</td>";
    
    /* destination channel */
    
    html = html + "<td>";
    html = html + "<button title=\"Solo DL output channel\" class=\"btn btn-success\" type=\"button\">";
    html = html + "<span class=\"badge\">" + row.dst + "</span>";
    html = html + "</button>";
    html = html + "</td>";
    
    /* output filter */
    
    html = html + "<td>";
    
    passSelect  = "";
    nullSelect  = "";
    manuSelect  = "";
    actionName  = "";
    inputClass  = "";
    manualValue = "";
    
    if(/Passthrough/.test(row.outputfilter)) {
      
      passSelect = "selected=\"selected\"";
      actionName = "Passthrough";
      inputClass = "noinput";
      
    } else if(/Null/.test(row.outputfilter)) {
      
      nullSelect = "selected=\"selected\"";
      actionName = "Null";
      inputClass = "noinput";
    
    } else if(/Manual/.test(row.outputfilter)) {
      
      manSelect="selected=\"selected\"";
      actionName = "Manual";
      inputClass = "hasinput";
      
      /* yank out the manual value */
      
      var regex = /\((\d+)\)/;
      var match = regex.exec(row.outputfilter);
      
      manualValue = match[1];
    }
    
    html = html + "<div class=\"input-group outputfilter\" filter-channel=\"" + row.channel + "\" filter-src=\"" + row.src + "\" filter-dst=\"" + row.dst + "\" filter-side=\"output\" filter-kind=\"" + actionName + "\" filter-value=\"" + manualValue + "\">";
    html = html + "<div class=\"input-group-btn\">";
    html = html + "<button title=\"Output Filter, use Manual to set value.\" type=\"button\" class=\"filterbtn btn btn-success dropdown-toggle\" data-toggle=\"dropdown\" aria-haspopup=\"true\" aria-expanded=\"false\">" + actionName + " <span class=\"caret\"></span></button>";
    html = html + "<ul class=\"dropdown-menu\">";
    html = html + "<li><a class=\"dropaction\" href=\"#\" " + passSelect + ">Passthrough</a></li>";
    html = html + "<li><a class=\"dropaction\" href=\"#\" " + nullSelect + ">Null</a></li>";
    html = html + "<li><a class=\"dropaction\" href=\"#\" " + manuSelect + ">Manual</a></li>";
    html = html + "</ul>";
    html = html + "</div>";
    html = html + "<input title=\"Press <RETURN> to enter value\" type=\"text\" class=\"" + inputClass + " form-control\" aria-label=\"...\" value=\"" + manualValue + " \">";
    html = html + "</div>";
    
    html = html + "</td>";
    
    /* output transform (no modify) */
    
    html = html + "<td>";
    html = html + "<h3 title=\"Actual Output\"><span class=\"label label-success\">" +  row.outputtransform + "</span></h3>";
    html = html + "</td>";
      
    html = html + "</tr>\n";
    
  }
  
  html = html + "</table>\n";
  
  /* render */
  
  $(container).html(html);
  
  /* disable the inputs that don't take a manual value */
  
  $('div#channelmap .noinput').attr('disabled', 'disabled');
  
  /* add the change behavior for the fields that have manual values */
  
  $('div#channelmap input').each(function () {
    $(this).data("previousValue", $(this).val());
  });

  $('div#channelmap input').keyup(function (event) {
    
    if(event.keyCode != 13){
      return ;
    }
    
    if($(this).data("previousValue") == $(this).val()) {
      
      /* no actual change */
      
      return ;
    } 

    /* new value */
    
    $(this).data("previousValue", $(this).val());
    
    /* fire chang event on this filter */
  
    channels_filterChanged($(this));
  
  });
  
  /* add the change behavior for the drop downs */
  
  $('div#channelmap a.dropaction').click(function (event) {
    
    /* don't reload the page */
    
    event.preventDefault();
    
    var action = $(this).text();
    var button = $(this).parents('.input-group').find('button');
    var input  = $(this).parents('.input-group').find('input');
    var ig     = $(this).parents('.input-group');
    var value  = $(input).val();
    
    if(value == "") {
      value = 0;
    }
    
    /* if we are going to manual we must enable the input */

    if(action == "Manual") {
      
      $(input).removeAttr('disabled');
      $(input).addClass('hasinput');
      $(input).removeClass('noinput');
      value = $(input).val();
      $(button).val(value);
      
    } else {
      
      $(input).attr('disabled', 'disabled');
      $(input).addClass('noinput');
      $(input).removeClass('hasinput');
      value = 0;
    }
    
    /* update dropdown name */
    
    $(button).html(action + " <span class=\"caret\"></span>");
    
    /* update the attributes of the input group as needed */
    
    $(ig).attr('filter-value', value);
    $(ig).attr('filter-kind',  action);
    
    /* trigger an update on this filter */
    
    channels_filterChanged($(button));
    
  });
  
  /* add the change behavior for the swap action */

  $('div#channelmap a.swapaction').click(function (event) {
    
    /* don't reload the page */
    
    event.preventDefault();
    
    /* get the channels being swapped */
    
    var a = $(this).attr('a');
    var b = $(this).attr('b');
    
    channels_swap(a, b);
    
  });
  
  /* all done */
  
  return true;
}

/**
 * 
 * channels_refresh() - build the control panel (or replace it) on
 * the channels page.
 * 
 */

function channels_refresh() {
  
  console.log("[channels_refresh] starts...");
  
  /* fetch the map */
  
  $.ajax({
    type:    "GET",
    url:     "/rest/ecubridge/Map.json",
    data:    {
    },
    dataType: "json",

    success: function(data) {

      if(data.status != "OK") {
        console.log("[channels_refresh] problem fetching channel map: " + data.error);  
        return false;
      }
       
      data = data.data;
      
      /* do the layout */
    
      channels_doLayout(data);
      
      return true;
    }
    
  });  
   
  return true;
}
