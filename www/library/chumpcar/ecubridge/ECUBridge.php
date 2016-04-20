<?php

/**
 * 
 * chumpcar \ ecubridge \ ECUBridge - this it the main API (PHP) to 
 * the ECU Bridge daemon.
 * 
 * @package chumpcar
 * 
 * @author Little m Design (Michael Garvin)
 * @copyright Copyright (c) 2016-, Little m Design
 * 
 */

/* make sure we can auto-load */

{   
  $DS = "\\";   
  if(strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    $DS = "\\";
  } else {
    $DS = "/";
  }
    
  $path = dirname(__FILE__);
  while(!empty($path)) {
    if(is_readable($path.$DS."autoloader.php")) {
      require_once($path.$DS."autoloader.php");
      break;
    }
    $path = dirname($path);
  }
}

autorequire('littlemdesign_util_Object');
autorequire('littlemdesign_util_Error');

class chumpcar_ecubridge_ECUBridge
  extends littlemdesign_util_Object {

  private $imageFolder = "/var/www/html/rrd-image-cache";
  private $imageURI    = "/rrd-image-cache";
  private $rrdCache    = "/var/lib/rrdcached/db";
  /**
   * 
   * Standard constructor
   * 
   * @param $dsn - the database connection pseudo URL
   * 
   * @param $logger - optionally proivde a specific logger to use.
   * 
   */
  
  public function __construct() {

    parent::__construct('ECUBridge', 'chumpcar_ecubride', true);

    $this->unReady();
  
    $this->info("Constructing...");
    
    /* test the daemon */
    
    $lines = $this->doCommand("echo,1");
    
    if(!$lines) {
      
      /* maybe the daemon isn't running? */
      
      return ;
    }
    
    if($lines[0] != "1") {
      $this->error("Consructing: wrong output from ECU Bridge: {$lines[0]}");
      return ;  
    }
    
    /* ok, we seem to be ready to import data */
    
    $this->makeReady();
    
    $this->info("Constructed.");  
  }
  
  /**
   * 
   * status() - return a simplified PHP object that has
   * the status details.
   * 
   * @return mixed - exactly false on error, or an object
   * with various status properties.
   * 
   */
  
  public function status() {
    
    if(!$this->isReady()) {
      $this->error("status() - not ready.");
      return false;
    }
    
    /* get the status */
    
    $details = $this->doCommand("status");
    
    if(!$details) {
      $this->error("status() - could not get status: ".$this->getError());
      return false;
    }
    
    if(count($details) < 7) {
      $this->error("status() - missing status lines.");
      return false;
    }
    
    /* we want the 'value' of each line */
    
    for($i=0; $i<7; $i++) {
      
      $item    = trim($details[$i]);
      $matches = array();
      
      if(empty($item)) {
        continue;
      }
      
      if(!preg_match('/^[^:]+:\s+(.*)$/', $item, $matches)) {
        $this->error("status() - can't match status line: $item");
        return false;
      }
      $details[$i] = $matches[1];
    } 
    
    /* convert to a PHP object */
    
    $result = (object)array(
      "status"  => $details[0],
      "reads"   => $details[1],
      "writes"  => $details[2],
      "uptime"  => $details[3],
      "uphours" => sprintf("%02d hours %02d min %02d sec", floor($details[3]/3600), ($details[3]/60)%60, $details[3]%60),
      "cmds"    => $details[4],
      "config"  => $details[5],
      "log"     => $details[6],
    );
    
    /* all done */
    
    return $result;
  }
  
  /**
   * 
   * graph() - generate an RRD graph image and save it to our
   * image cache folder.
   * 
   * @param $kind string - the kind of graph, must be raw, 
   * normal, or output.  These are the main three sampling
   * data taps you cna use.  Raw is straignt from the DL-32
   * and output is what actually gets sent to the Solo DL.
   * Nornal is the standard values, i.e. 1 is 1.
   * 
   * @param $ago integer - number of seconds ago to start at
   * and end at now.
   * 
   * @param series mixed - the name of the series to show, 
   * or an array of series names. If you don't specify all 
   * series will be graphed.
   * 
   * @return mixed - exactly false on error.  Otherwise the 
   * URI of where the image can be found.
   * 
   */
  
  public function graph($kind="ouput", $ago=300, $series=false) {
    
    $this->info("graph() - starts...");
    
    /* check the parameters */
    
    if(!$this->isReady()) {
      $this->error("graph() - not ready.");
      return false;
    }

    $uri = false;
    
    $allSeries = array(
      "rpm",
      "wheelspeed",
      "oilpressure",
      "oiltemp",
      "watertemp",
      "fuelpressure",
      "batteryvoltage",
      "throttleangle",
      "manifoldpressure",
      "airchargetemp",
      "exausttemp",
      "lambda",
      "fueltemp",
      "gear",
      "errorflag"
    );
    
    $titles = array(
      "rpm"              => "RPM",
      "wheelspeed"       => "Wheel Speed",
      "oilpressure"      => "Oil Press.", 
      "oiltemp"          => "Oil Temp",
      "watertemp"        => "Water Temp",
      "fuelpressure"     => "Fuel Press.",
      "batteryvoltage"   => "Batt Voltage",
      "throttleangle"    => "Throttle Ang.",
      "manifoldpressure" => "Manif. Press.",
      "airchargetemp"    => "Air Charge Temp",
      "exausttemp"       => "Exaust Temp",
      "lambda"           => "Lambda",    
      "fueltemp"         => "Fuel Temp",
      "gear"             => "Gear",
      "errorflag"        => "Error Flag"
    );
    
    $colors = array(
      "rpm"              => "#000000",
      "wheelspeed"       => "#FF0000",
      "oilpressure"      => "#800000", 
      "oiltemp"          => "#E5720D",
      "watertemp"        => "#00FF00",
      "fuelpressure"     => "#008000",
      "batteryvoltage"   => "#0000FF",
      "throttleangle"    => "#000080",
      "manifoldpressure" => "#FF00FF",
      "airchargetemp"    => "#800080",
      "exausttemp"       => "#C0C0C0",
      "lambda"           => "#808080",    
      "fueltemp"         => "#D2B48C",
      "gear"             => "#A52A2A",
      "errorflag"        => "#00BFFF"    
    );
    
    if(is_string($series)) {
      
      $series = explode(",", $series);
      
      foreach($series as $idx => $item) {
        
        if(!in_array($item,$allSeries)) {
          $this->error("graph() - bad series name: $item");
          return false;      
        }
      }
      
    } else if(is_array($series)) {
      
      foreach($series as $idx => $item) {
        
        if(!in_array($item,$allSeries)) {
          $this->error("graph() - bad series name: $item");
          return false;      
        }
      }
      
    } else {
      
      $this->error("graph() - unrecognized kind of series names.");
      return false;    
    }
    
    $kind = trim(strtolower($kind));
    
    if(($kind != "raw") && ($kind != "normal") && ($kind != "output")) {
      $this->error("graph() - unrecognized kind of data: $kind");
      return false; 
    }
    
    $ago = floor(trim($ago));
    
    if(!is_numeric($ago) || ($ago<0)) {
      $this->error("graph() - bad ago value: $ago");
      return false; 
    }
    
    $timeAgo = sprintf("%02d hours %02d min %02d sec", floor($ago/3600), ($ago/60)%60, $ago%60);
    
    /* make the file name */
    
    $signature = "$kind-$ago-".implode("-", $series).".png";
    
    /* build up the rrd graph command */
    
    $cmd  = "/usr/bin/rrdtool graph ";
    $cmd .= "--daemon unix:/var/run/rrdcached.sock ";
    $cmd .= "--width 800 --height 500 --title 'Data For Last $timeAgo' ";
    $cmd .= "--start end-$ago"."s --end now ";
    $cmd .= "{$this->imageFolder}/$signature ";
    
    foreach($series as $idx => $item) {
      
      $cmd .= "DEF:$item={$this->rrdCache}/ecu-data-$kind.rrd:$item:AVERAGE ";
      $cmd .= "LINE3:$item{$colors[$item]}:\"{$titles[$item]}\\l\" ";
      
    }
   
    $this->info("graph() - cmd: $cmd");
    
    /* run the command to generate the graph */
    
    $output = `$cmd 2>&1`;
    
    /* make sure we go the graph */
    
    if(!file_exists("{$this->imageFolder}/$signature")) {
      $this->error("graph() - failed to generate image: {$this->imageFolder}/$signature");
      return false; 
    }
    
    /* pass back the URI */
    
    $uri = "{$this->imageURI}/$signature";
    
    /* all done */
    
    return $uri;
  }
  
  /**
   * 
   * map() - fetch a mapping of the channel setup, there are 
   * 15 channels and each "row" has its own column:
   * 
   *   input transform << DL-32
   *   input filter
   *   output filter
   *   output transform >> Solo DL
   * 
   * We give back an array of rows, each with those columns 
   * in that order.
   * 
   * The name of the input/output transform indidcates how 
   * the channel is mapped. For example:
   * 
   *   DL-32 1, Passthrough, Passthrough,  RPM
   *   
   * means that the DL-32 channel 1 data is passed through unharmed
   * to the RPM channel of the Solo DL.
   * 
   * If you see a "Manual" filter instead of Passthrough, it 
   * means that an override value is assigned and that value 
   * will always be used, until the fitler is changed to passthrough
   * or null.  A null filter obviously uses "0" as the value.
   * 
   * @return mixed - exactly false on error, otherwise the table
   * that details the channel mapping.
   * 
   */
  
  public function map() {
    
    if(!$this->isReady()) {
      $this->error("map() - not ready.");
      return false;
    }
    
    $details = $this->doCommand("channels,map,terse");
    
    if(!$details) {
      $this->error("map() - could not get channel mapping: ".$this->getError());
      return false;
    }
    
    if(count($details) < 15) {
      $this->error("map() - not enough mapping lines.");
      return false;
    }
    
    $results = array();
    
    for($i=0; $i<15; $i++) {
      
      $line = trim($details[$i]);
      
      if(empty($line)) {
        continue;
      }
      
      $cols = explode(',', $line);
      
      $it   = trim($cols[0]);
      $if   = trim($cols[1]);
      $of   = trim($cols[2]);
      $ot   = trim($cols[3]);
      
      $bits = explode(":", trim($cols[4]));
      
      $src  = $bits[0];
      $dst  = $bits[1];
      
      $results[] = array($it, $if, $src, $dst, $of, $ot);
    }
    
    /* pass it back */
    
    return $results;
  }
  
  /**
   * 
   * resetPatch() - reset the patch ording to whatever it was
   * when the ECU Bridge was started.
   * 
   * @return boolean - exactly false on error.
   * 
   */
  
  public function resetPatch() {

    // TODO:
    
  }
  
  /**
   * 
   * patch() - change the order of channel mappings.  Normally channel 
   * 1 input goes to channel 2 output, but you can swap any two channels
   * to change the ordering. If you swap 1 and 2, the what normally goes 
   * to Solo DOL RPM channel will go to Solo DL Wheel Speed and vice 
   * versa. Both channels must be in the range 1..15.
   * 
   * 
   * @param $chan1 integer - the first channel to swap
   * @param $chan2 integer - the other channel to swap with
   * 
   * 
   * @return boolean - exactly false on error.
   * 
   */
  
  public function patch($chan1, $chan2) {
    
    /* check parameters */
    
    if(!$this->isReady()) {
      $this->error("patch() - not ready.");
      return false;
    }
    
    if(empty($chan1)||!is_numeric($chan1)) {
      $this->error("patch() - invalid channel #1 value: $chan1");
      return false;
    }
    
    if(empty($chan2)||!is_numeric($chan2)) {
      $this->error("patch() - invalid channel #1 value: $chan2");
      return false;
    }
      
    $cmd = "patch,swap,$chan1,$chan2";
    
    $details = $this->doCommand($cmd);
    
    if(!$details) {
      $this->error("patch() - could not patch: ".$this->getError());
      return false;
    }
    
    /* make sure it went ok */
    
    $status = trim($details[0]);
    
    if(!preg_match('/^OK/', $status)) {
      $this->error("patch() - did not complete ($status).");
      return false;
    }
    
    /* all done */

    return true;
  }
  
  /**
   * 
   * getFilter() - get the current filter in play for the 
   * input or output of a given channel.
   * 
   * @param $side string - must be input or output
   * @param $channel integer - channel #, must be in range 1..15
   * 
   * @return mixed - exactly false on error, otherwise the filter
   * description.
   * 
   */
  
  public function getFilter($side, $channel) {

    /* check parameters */
    
    $side = trim(strtolower($side));
    
    if(($side != "input") && ($side != "output")) {
      $this->error("getFilter() - side must be input or output: $side");
      return false;
    }
    
    if($side == "input") {
      $side = 1;
    } else {
      $side = 2;
    }
    
    if(!is_numeric($channel)) {
      $this->error("getFilter() - channel must be a number: $channel");
      return false;
    }    
    
    if(($channel < 1) || ($channel > 15)) {
      $this->error("getFilter() - channel must be in the range 1..15: $channel");
      return false;
    }
    $channel = (int)$channel;
    $channel--;
    
    /* try to get the complete channel mapping */
    
    $map = $this->map();
    
    if(!$map) {
      return ;
    }
    
    $filter = $map[$channel][$side];
    
    /* all done */
    
    return $filter;
  }
  
  /**
   * 
   * setFilter() - set the input or output filter on a
   * given channel as requested.
   * 
   * @param $side string which side to modify, must be 
   * input or output.
   * 
   * @param $channel integer which channel to modify, 
   * must be 1-15.
   * 
   * @param $kind string which kind of filter to set.
   * must be passthrough, null or manual. In the case
   * of manual, you can provide a $value to use.
   * 
   * @param $value integer value - (Optional) the value ot use
   * when setting a manual filter.
   * 
   * @return boolean return exactly false on error.
   * 
   */
  
  public function setFilter($side, $channel, $kind, $value=0) {
    
    if(!$this->isReady()) {
      $this->error("setFilter() - not ready.");
      return false;
    }
    
    $side = trim(strtolower($side));
    
    if(($side != "input") && ($side != "output")) {
      $this->error("setFilter() - side must be input or output: $side");
      return false;
    }
    
    if(!is_numeric($channel)) {
      $this->error("setFilter() - channel must be a number: $channel");
      return false;
    }    
    
    if(($channel < 1) || ($channel > 15)) {
      $this->error("setFilter() - channel must be in the range 1..15: $channel");
      return false;
    }
    $channel = (int)floor($channel);
    
    $kind = trim(strtolower($kind));
    
    if(($kind != "passthrough") && ($kind != "null") && ($kind != "manual")) {
      $this->error("setFilter() - filter must be manual, null or passthrough: $kind");
      return false;
    }
    
    if($kind == "manual") {
      if(!is_numeric($value)) {
        if($value != 0) {
          $this->error("setFilter() - manual filter value must be a number: $value");
          return false;
        }
      }
    }
    
    if(empty($value)) {
      $value = 0;
    }
    
    /* do the command */
    
    $cmd = "filter,$side,$channel,$kind,$value";
    
    $details = $this->doCommand($cmd);
    
    if(!$details) {
      $this->error("setFilter() - could not set filter: ".$this->getError());
      return false;
    }
    
    /* make sure it went ok */
    
    $status = trim($details[0]);
    
    if($status != "OK. Filter set.") {
      $this->error("setFilter() - bad status from ECU Bridge: $status");
      return false;
    }
    
    /* all done */
    
    return true;
  }
  
  /**
   * 
   * doCommnand() - send the given command to the ECU Bridge daemon, and
   * gather the response.  
   * 
   * @param $cmd string - the command to send
   * 
   * @return mixed - exactly false on error, otherwise the array of lines
   * that came back.
   * 
   */
  
  private function doCommand($cmd, $timeout=10.0) {
    
    /* try to open the command port */
    
    $errno  = 0;
    $errstr = "";
    $handle = fsockopen("tcp://localhost", 5999, $errno, $errstr, $timeout); 

    if(!$handle) {
      
      $this->error("doCommnand() - can't open command port ($errno): $errstr");
      return false;
    }
    
    /* send the command */
    
    $cmd = trim($cmd)."\n";
    
    if(!fwrite($handle, $cmd)) {
      $this->error("doCommnand() - can't send command.");
      return false;
    }
    
    /* get the output */
    
    $output = "";
    
    while(!feof($handle)) {
  
      $output .= fgets($handle, 2048);
    }
    
    fclose($handle);    

    /* pass back the lines of output as an array */
    
    return explode("\n", $output);
  }
}

/* test */

/*
echo "Setting up...\n";

$cmdr = new chumpcar_ecubridge_ECUBridge;

if(!$cmdr->isReady()) {
  echo "Can't make commander: {$cmdr->getError()}\n";
  exit(1);
}

echo "Commander is ready.\n";
*/

/*
echo "graphing...\n";

$uri = $cmdr->graph("output", 300, "rpm,wheelspeed");
if($uri == false) {
  echo "Can't make graph: {$cmdr->getError()}\n";
  exit(1);
}

echo "X: URI: $uri\n";
*/

/*

echo "Getting channel mapping...\n";

$data = $cmdr->map();

echo "map: ".print_r($data,true)."\n";
*/

/*
echo "Setting filter...\n";

$side    = "output";
$channel = 1;
$kind    = "manual";
$value   = "43";

$data = $cmdr->setFilter($side, $channel, $kind, $value);

echo "status: ".print_r($data,true)."\n";
*/

/*
echo "Setting filter...\n";

$side    = "input";
$channel = 1;

$data = $cmdr->getFilter($side, $channel);

echo "filter: ".print_r($data,true)."\n";
*/
?>