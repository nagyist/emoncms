<?php
    /*
    All Emoncms code is released under the GNU Affero General Public License.
    See COPYRIGHT.txt and LICENSE.txt.

    ---------------------------------------------------------------------
    Emoncms - open source energy visualisation
    Part of the OpenEnergyMonitor project:
    http://openenergymonitor.org
    */

    defined('EMONCMS_EXEC') or die('Restricted access');
    global $path, $embed, $vis_version;
    
?>

<!--[if IE]><script language="javascript" type="text/javascript" src="<?php echo $path;?>Lib/flot/excanvas.min.js"></script><![endif]-->
<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Lib/flot/jquery.flot.merged.js"></script>
<script language="javascript" type="text/javascript" src="<?php echo $path;?>Modules/feed/feed.js?v=<?php echo $vis_version; ?>"></script>
<script language="javascript" type="text/javascript" src="<?php echo $path;?>Lib/vis.helper.js?v=<?php echo $vis_version; ?>"></script>

<div id="vis-title"></div>
<style>
    .stats-container{
        position: absolute;
        bottom: 0.3em;
        width: 100%;
        text-align: center;
        text-shadow: -1px -1px 0 #fff, 1px -1px 0 #fff, -1px 1px 0 #fff, 1px 1px 0 #fff;
        font-size: 1.3rem;
    }
</style>
<div id="placeholder_bound" style="position: relative; height: 75vh">
    <div id="placeholder" style="position:absolute; top:0px;"></div>
    <div id="graph-buttons" style="position:absolute; top:18px; right:32px; opacity:0.5;">
        <div class='btn-group'>
            <button class='btn graph-time' type='button' time='1'>D</button>
            <button class='btn graph-time' type='button' time='7'>W</button>
            <button class='btn graph-time' type='button' time='30'>M</button>
            <button class='btn graph-time' type='button' time='365'>Y</button>
        </div>
        <div class='btn-group'>
            <button class='btn graph-interval' type='button' interval='d'><span id="textunitD"></span>/D</button>
            <button class='btn graph-interval' type='button' interval='m'><span id="textunitM"></span>/M</button>
            <button class='btn graph-interval' type='button' interval='y'><span id="textunitY"></span>/Y</button>
        </div>
        <div class='btn-group' id='graph-navbar' style='display: none;'>
            <button class='btn graph-nav' id='zoomin'>+</button>
            <button class='btn graph-nav' id='zoomout'>-</button>
            <button class='btn graph-nav' id='left'><</button>
            <button class='btn graph-nav' id='right'>></button>
        </div>
    </div>
    <h3 class="stats-container"><span id="stats"></span></h3>
</div>

<script id="source" language="javascript" type="text/javascript">

var feedid = <?php echo $feedid; ?>;
var feedname = "<?php echo $feedidname; ?>";
var apikey = "<?php echo $apikey; ?>";
var embed = <?php echo $embed; ?>;
var valid = "<?php echo $valid; ?>";

feed.apikey = apikey;

var interval = urlParams.interval;
if (interval==undefined || interval=='') interval = 3600*24;

var plotColour = urlParams.colour;
if (plotColour==undefined || plotColour=='') plotColour = "EDC240";

var backgroundColour = urlParams.colourbg;
if (backgroundColour==undefined || backgroundColour=='') backgroundColour = "ffffff";
$("body").css("background-color","#"+backgroundColour);

var units = urlParams.units;
if (units==undefined || units=='') units = "";

var dp = urlParams.dp;
if (dp==undefined || dp=='') dp = 1;

var scale = urlParams.scale;
if (scale==undefined || scale=='') scale = 1;

var average = urlParams.average;
if (average==undefined || average=='') average = 0;

var delta = urlParams.delta;
if (delta==undefined || delta=='') delta = 0;

var initzoom = urlParams.initzoom;
if (initzoom==undefined || initzoom=='' || initzoom < 1) initzoom = '7'; // Initial zoom default to 7 days (1 week)

document.getElementById("textunitD").innerHTML=units;
document.getElementById("textunitM").innerHTML=units;
document.getElementById("textunitY").innerHTML=units;

// Some browsers want the colour codes to be prepended with a "#". Therefore, we
// add one if it's not already there
if (plotColour.indexOf("#") == -1) {
    plotColour = "#" + plotColour;
}

var top_offset = 0;
var placeholder_bound = $('#placeholder_bound');
var placeholder = $('#placeholder');
var previousPoint = false;

var width = placeholder_bound.width();
var height = placeholder_bound.height();

placeholder.width(width);

placeholder_bound.height(height);
placeholder.height(height-top_offset);

if (embed) placeholder.height($(window).height()-top_offset);

var intervalcode=interval;
if (intervalcode==0 || intervalcode=='y' || intervalcode=='m' || intervalcode=='d') {
    interval = 3600*24;
}

var intervalms = interval * 1000;

if (intervalcode=='y') {
   timeWindow = 3600000*24*365*5;
} else if (intervalcode=='m') {
   timeWindow = 3600000*24*365;
} else if (intervalcode=='d') {
   timeWindow = 3600000*24*10;
} else {
   timeWindow = 3600000*24*initzoom;
}

view.start = +new Date - timeWindow;
view.end = +new Date;

var data = [];

$(function() {

    if (embed==false) $("#vis-title").html("<br><h2><?php echo tr("Bar graph:") ?> "+feedname+"<h2>");
    draw();
    
    $("#zoomout").click(function () {view.zoomout(); draw();});
    $("#zoomin").click(function () {view.zoomin(); draw();});
    $('#right').click(function () {view.panright(); draw();});
    $('#left').click(function () {view.panleft(); draw();});
    
    $('.graph-time').click(function () {view.timewindow($(this).attr("time")); draw();});
    
    $('.graph-interval').click(function () {
        intervalcode=$(this).attr("interval");

        if (intervalcode==0 || intervalcode=='y' || intervalcode=='m' || intervalcode=='d') {
            interval = 3600*24;
        }

        intervalms = interval * 1000;

        if (intervalcode=='y') {
           timeWindow = 3600000*24*365*5;
        } else if (intervalcode=='m') {
           timeWindow = 3600000*24*365;
        } else if (intervalcode=='d') {
           timeWindow = 3600000*24*10;
        } else {
           timeWindow = 3600000*24*31;
        }
        
        view.start = +new Date - timeWindow;
        view.end = +new Date;

        draw();
    });
    
    placeholder.bind("plotselected", function (event, ranges)
    {
        view.start = ranges.xaxis.from;
        view.end = ranges.xaxis.to;
        draw();
    });


    placeholder.bind("plotclick", function (event, pos, item)
    {
        if (!item){
        if (intervalcode=='d') {
            intervalcode='m';
            timeWindow = 3600000*24*365;
            view.start = view.start-timeWindow/2;
            view.end = view.start+timeWindow;
            draw();
        }

        else if (intervalcode=='m') {
            intervalcode='y';
            timeWindow = 3600000*24*365*5;
            view.start = view.start-timeWindow/2;
            view.end = view.start+timeWindow;
            draw();
        }
        }else{
        if (intervalcode=='m') {
            intervalcode='d';
             var ndaysofthemonth= 31;
             var monthid=new Date(item.datapoint[0]).getMonth();
            if (monthid==1) 
                ndaysofthemonth=28;
            if (monthid==3 || monthid==5 || monthid==8 || monthid==10) 
                ndaysofthemonth=30;
            timeWindow = 3600000*24*(1+ndaysofthemonth);
            view.start = item.datapoint[0]-3600000*24/2;
            view.end = view.start+timeWindow;
            draw();
        }

        else if (intervalcode=='y') {
            intervalcode="m";
            timeWindow = 3600000*24*365;
            view.start = item.datapoint[0]-3600000*24*31/2;
            view.end = view.start+timeWindow;
            draw();
        }
    }

   });
    
    placeholder.bind("plothover", function (event, pos, item)
    {
        if (item) {
            if (previousPoint != item.datapoint){
                previousPoint = item.datapoint;
                
                var datestr;
                if (intervalcode=='y')
                    datestr = new Date(item.datapoint[0]).format("yyyy");
                else if (intervalcode=='m')
                    datestr = new Date(item.datapoint[0]).format("mmm, yyyy");
                else if (intervalcode=='d')
                    datestr = new Date(item.datapoint[0]).format("ddd, mmm dS, yyyy");
                else
                    datestr = (new Date(item.datapoint[0])).format("ddd, mmm dS, yyyy");

                $("#tooltip").remove();
                var itemTime = item.datapoint[0];
                var itemVal = item.datapoint[1];
                tooltip(item.pageX, item.pageY, datestr+"<br>"+feedname+": "+itemVal.toFixed(dp)+" "+units,  "#fff", "#000");
            }
        } else {
            $("#tooltip").remove();
            previousPoint = null;
        }
    });

    function draw()
    {
        var d = new Date()
        var n = d.getTimezoneOffset();
        var offset = n / -60;
    
        var datastart = Math.floor(view.start / intervalms) * intervalms;
        var dataend = Math.ceil(view.end / intervalms) * intervalms;
        datastart -= offset * 3600000;
        dataend -= offset * 3600000;
        
        if (interval==86400) {
            data = feed.getdata(feedid,datastart,dataend,"daily",average,delta,0,0,false);
        } else {
            data = feed.getdata(feedid,datastart,dataend,interval,average,delta,0,0,false);
        }
        
        var out = [];
        
        if (scale!=1) {
            for (var z=0; z<data.length; z++) {
                var val = data[z][1] * scale;
                out.push([data[z][0],val]);
            }
            data = out;
        } 
       
        /* to align the day bar and the day text (not needed if if the ajax request is fixed)
        for (var x=0;x<data.length;x++){
           offset= new Date (data[x][0]).getTimezoneOffset();
           data[x][0]=Math.floor(data[x][0] / intervalms) * intervalms + offset*60000;
        }*/
       
       out = [];
       if (data.length) {
           var year = new Date (data[0][0]).getFullYear();
           var month= new Date (data[0][0]).getMonth();
           var sumtime=0;
           var sum=0;
     
            if (intervalcode=='y'){
               sumtime= new Date (year,0,1);
               for (var x=0;x<data.length;x++){
                  if (new Date (data[x][0]).getFullYear() == year)
                     sum+=data[x][1];
                  else {
                     out.push([sumtime,sum]);
                     year = new Date (data[x][0]).getFullYear();
                     sumtime= new Date (year,0,1);
                     sum=data[x][1];
                  }
               }
               out.push([sumtime,sum]);
               data=out;
            }

            else if (intervalcode=='m'){
               sumtime= new Date (year,month,1);
               for (var x=0;x<data.length;x++){
                  if (new Date (data[x][0]).getMonth() == month)
                     sum+=data[x][1];
                  else {
                     out.push([sumtime,sum]);
                     month= new Date (data[x][0]).getMonth();
                     year = new Date (data[x][0]).getFullYear();
                     sumtime= new Date (year,month,1);
                     sum=data[x][1];
                  }
               }
               out.push([sumtime,sum]);
               data=out;
            }
        }
        plot();
    }
    
    function plot()
    {

        if (intervalcode=='y')
            intervalrange=interval*365;
        else if (intervalcode=='m')
            intervalrange=interval*30;
        else
            intervalrange=interval;

        var options = {
            canvas: true,
            bars: { show: true, align: "center", barWidth: 0.75*intervalrange*1000, fill: true},
            xaxis: { mode: "time", timezone: "browser",
            min: view.start, max: view.end, minTickSize: [intervalrange, "second"] },
            //yaxis: { min: 0 },
            grid: {hoverable: true, clickable: true},
            selection: { mode: "x" },
            touch: { pan: "x", scale: "x" }
        }

        $.plot(placeholder, [{data:data,color: plotColour}], options);
    }


    // Graph buttons and navigation efects for mouse and touch
    placeholder.mouseenter(function(){
        $("#graph-navbar").show();
        $("#graph-buttons").stop().fadeIn();
        $("#stats").stop().fadeIn();
    });
    placeholder_bound.mouseleave(function(){
        $("#graph-buttons").stop().fadeOut();
        $("#stats").stop().fadeOut();
    });
    placeholder.bind("touchstarted", function (event, pos)
    {
        $("#graph-navbar").hide();
        $("#graph-buttons").stop().fadeOut();
        $("#stats").stop().fadeOut();
    });
    
    placeholder.bind("touchended", function (event, ranges)
    {
        $("#graph-buttons").stop().fadeIn();
        $("#stats").stop().fadeIn();
        view.start = ranges.xaxis.from;
        view.end = ranges.xaxis.to;
        draw();
    });
    
    $(document).on('window.resized hidden.sidebar.collapse shown.sidebar.collapse',vis_resize);
    
    function vis_resize() {
        var width = placeholder_bound.width();
        var height = placeholder_bound.width();

        placeholder.width(width);
        // placeholder_bound.height(height);
        // placeholder.height(height-top_offset);
        placeholder.height('75vh');

        if (embed) placeholder.height($(window).height()-top_offset);
        plot();
    }
});
</script>

