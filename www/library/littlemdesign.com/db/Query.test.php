<?php

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

autorequire('littlemdesign\db\Query');
autorequire('littlemdesign\db\SQLFactory');

/*
 * test code 
 * 
 */

echo "Initializing...\n";
 
$dsn = "mysql://rolleradmin:roller@localhost/rollermarathon";
if(SQLFactory::connect($dsn) === false) {
  echo "Can't connect to database.\n";	
  exit(1);
}

echo "making empty query...\n";

$q = Query::create('result');

if(!$q->isReady()) {
  echo "Can't make good query.\n";
  exit(1);
}

/*
$q->_not()->where('first_name', 'is false', '3');
*/

/*
$q->where('first_name', 'like', 'mike')
  ->where('last_name', 'regexp', '[G]arvin')
  ->_xor()
  ->where('age_class_high', '>', '30')
  ->rawWhere('1=1');
*/

/*
$q
  ->rawWhere('2=2')
  ->_or()
  ->_not()
  ->startGroup()
  ->rawWhere('1=1')
  ->endGroup();
*/

/*
$q
  ->rawWhere('2=2')
  ->_or()
  ->startGroup()
  ->rawWhere('1=1')
  ->rawWhere('2=2')
  ->endGroup();
*/

/*
$q
  ->startGroup()
  ->rawWhere('2=2')
  ->_or()
  ->startGroup()
  ->_not()
  ->rawWhere('1=1')
  ->endGroup()
  ->endGroup();
*/

/*
$q->startGroup()
  ->where('first_name', 'like', 'mike')
  ->where('age_class_high', '>', '30')
  ->_or()
  ->_not()
  ->startGroup()
  ->rawWhere('1=1')
  ->rawWhere('2=2')
  ->rawWhere('3=3')
  ->endGroup()
  ->endGroup();
*/

/*
$q
  ->startGroup()
  ->startGroup()
  ->where('first_name', 'like', 'mike')
  ->where('age_class_high', '>', '30')
  ->endGroup()
  ->_or()
  ->startGroup()
  ->_not()
  ->startGroup()
  ->rawWhere('1=1')
  ->rawWhere('2=2')
  ->rawWhere('3=3')
  ->endGroup()
  ->endGroup()
  ->endGroup();
*/

/*
$q->startGroup()
  ->rawWhere('3=3')
  ->_or()
  ->endGroup();
*/

/*
$result = $q
  ->comment('sample query')
  ->distinct()
  ->where('first_name', 'like', 'mike')
  ->where('distance', '=', 42)
  ->where('name', 'is not null')
  ->extend('race')
  ->extend('event')
  ->orderBy('result.id')
  ->asObjects()
  ->includeTable('event')
  ->find(1, 10);
  
echo "Listing results...\n";

foreach($result as $pkey => $object) {
	
  echo "Object $pkey...\n";
 
  echo "$pkey => ".print_r($object,true)."\n";

  echo "linked event object: \n";
  
  $events = $result->related($object, 'event');
  $event = $events[0];
  
  echo ". ".print_r($event,true)."\n";
  
  echo "results for event...\n";
  
  echo " .. ".print_r($result->related($event, 'result'),true)."\n";
  
  break;
}
*/
 
/*
$result = $q
  ->comment('sample query')
  ->distinct()
  ->where('first_name', 'like', 'mike')
  ->where('distance', '=', 42)
  ->where('name', 'is not null')
  ->extend('race')
  ->extend('event')
  ->orderBy('result.id')
  ->asArray()
  ->select('result.first_name')
  ->select('result.last_name')
  ->select('event.name')
  ->select('event.id')
  ->find(1, 10);
  
echo "Listing results...\n";

foreach($result as $pkey => $object) {
	
  echo "Object $pkey...\n";
  echo ".. ".print_r($object,true)."\n";
  
}

*/

/*
$result = $q
  ->comment('sample query')
  ->distinct()
  ->where('first_name', 'like', 'mike')
  ->where('distance', '=', 42)
  ->where('name', 'is not null')
  ->extend('race')
  ->extend('event')
  ->orderBy('result.id')
  ->asIDs()
  ->find(1, 10);
  
echo "Listing results...\n";

foreach($result as $idx => $pk) {
	
  echo "#$idx - pk $pk...\n";
  
  echo ".. ".print_r($result->fetch(),true)."\n";
}
*/

/*
$result = $q
  ->comment('sample query')
  ->distinct()
  ->where('first_name', 'like', "'mike'")
  ->where('last_name', 'like', "'miller'")
  ->where('distance', '=', 42)
  ->where('name', 'like', "'%Apo%'")
  ->where('bib', '=', 454)
  ->extend('race')
  ->extend('event')
  ->asObjects()
  ->includeTable('event')
  ->find();
  
echo "X: result: ".print_r($result,true)."\n";
*/

/*
echo "Updating...\n";

$result = $q
  ->comment('update')
  ->where('first_name', 'like', "'mike'")
  ->where('last_name', 'like', "'miller'")
  ->where('bib', '=', 454)
  ->update(array('last_name'=>"'miller'",'first_name'=>"'MIKE'"));
  
echo "X: result: ".print_r($result,true)."\n";
*/

/*
echo "Deleting...\n";

$result = $q
  ->comment('sample query')
  ->where('id', '=', 155672)
  ->delete();

echo "X: result: ".print_r($result,true)."\n";
*/

?>
