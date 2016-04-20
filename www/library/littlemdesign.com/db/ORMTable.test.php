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

autorequire('littlemdesign\db\SQLFactory');
autorequire('littlemdesign\db\ORMTable');

/* test belongsTo() */

echo "Initializing...\n";
 
$dsn = "mysql://rolleradmin:roller@localhost/rollermarathon";
if(SQLFactory::connect($dsn) === false) {
  echo "Can't connect to database.\n";	
  exit(1);
}

/* testing belongs/has */

$db = SQLFactory::createManaged();
if(($db === false)||(!$db->isReady())) {
  echo "Can't create database.\n";
  exit(1);
}

/*
echo "Make tables...\n";

$fields = array(
  "name" => array(
    "type"   => "text",
  	"length" => 255
  ),
  "customer_id" => array(
    "type" => "integer", 
  )
);


$result = $db->createTable('orders', $fields);
if($result === false) {
  echo("Can't create table: ".$db->getError()."\n");
  exit(1);
}

$fields = array(
  "name" => array(
    "type"   => "text",
  	"length" => 255
  ),
);

$result = $db->createTable('customer', $fields);
if($result === false) {
  echo("Can't create table: ".$db->getError()."\n");
  exit(1);
}


echo "Filling table...\n";

$db->query("INSERT INTO customer (name) VALUES ('a');");
$db->query("INSERT INTO customer (name) VALUES ('b');");
$db->query("INSERT INTO customer (name) VALUES ('c');");
$db->query("INSERT INTO customer (name) VALUES ('d');");
 	
$db->query("INSERT INTO orders (name,customer_id) VALUES ('order_1', '1');");
$db->query("INSERT INTO orders (name,customer_id) VALUES ('order_2', '2');");
$db->query("INSERT INTO orders (name,customer_id) VALUES ('order_3', '3');");
$db->query("INSERT INTO orders (name,customer_id) VALUES ('order_4', '4');");

echo "fetch order...\n";

$obj = ORMTable::create('orders', 2);
*/

/*
echo "fetching belongsTo()...\n";

$customer = $obj->belongsTo('customer');

//echo "X: customer: ".print_r($customer,true)."\n";

echo "fetching hasOne()...\n";

$order = $customer->hasMany('orders');

echo "X: order: ".print_r($order,true)."\n";

echo "Ok.\n";
*/

/*
$customer = ORMTable::create('customer', 2);
$customer2 = ORMTable::create('customer', 3);

$order = array_shift($customer->related('orders'));

if($customer2->isLinked($order)) {
  echo "yes linked\n";
} else {
  echo "no not linked\n";
}

$customer2->link($order);
*/

/*
$order = ORMTable::create('orders', 2);
$customer = $order->related('customer');
$order2 = ORMTable::create('orders', 3);

//echo "X: customer: ".print_r($customer,true)."\n";
if($order2->isLinked($customer)) {
  echo "yes linked\n";
} else {
  echo "no not linked\n";
}

$order2->link($customer);
*/

/*
echo "doing unlink...\n";

$customer = ORMTable::create('customer', 3);
$order    = array_shift($customer->related('orders'));

if($customer->isLinked($order)) {
  echo "yes linked\n";
} else {
  echo "no not linked\n";
}

$order->unlink($customer);
*/

/* testing 'through' */

/*
$db = SQLFactory::createManaged();
if(($db === false)||(!$db->isReady())) {
  echo "Can't create database.\n";
  exit(1);
}
*/

/*
echo "Make tables...\n";

$fields = array(
  "name" => array(
    "type"   => "text",
  	"length" => 255
  )
);

$result = $db->createTable('supplier', $fields);
if($result === false) {
  echo("Can't create table: ".$db->getError()."\n");
  exit(1);
}

$fields = array(
  "account_id" => array(
    "type" => "integer"
  ),
  "credit_rating" => array(
    "type" => "integer"
  )
);

$result = $db->createTable('accounthistory', $fields);
if($result === false) {
  echo("Can't create table: ".$db->getError()."\n");
  exit(1);
}

$fields = array(
  "supplier_id" => array(
    "type" => "integer"
  ),
  "account_number" => array(
    "type" => "integer"
  )
);

$result = $db->createTable('account', $fields);
if($result === false) {
  echo("Can't create table: ".$db->getError()."\n");
  exit(1);
}

echo "Filling table...\n";

$db->query("INSERT INTO supplier (name) VALUES ('supplier #1');");
$db->query("INSERT INTO supplier (name) VALUES ('supplier #2');");
$db->query("INSERT INTO supplier (name) VALUES ('supplier #3');");
$db->query("INSERT INTO supplier (name) VALUES ('supplier #4');");

$db->query("INSERT INTO accounthistory (credit_rating,account_id) VALUES ('30','1');");
$db->query("INSERT INTO accounthistory (credit_rating,account_id) VALUES ('40','2');");
$db->query("INSERT INTO accounthistory (credit_rating,account_id) VALUES ('50','3');");
$db->query("INSERT INTO accounthistory (credit_rating,account_id) VALUES ('60','4');");

$db->query("INSERT INTO account (account_number,supplier_id) VALUES ('1001','1');");
$db->query("INSERT INTO account (account_number,supplier_id) VALUES ('1002','2');");
$db->query("INSERT INTO account (account_number,supplier_id) VALUES ('1003','3');");
$db->query("INSERT INTO account (account_number,supplier_id) VALUES ('1004','4');");
*/

/*
$supplier  = ORMTable::create('supplier', 1);
$supplier2 = ORMTable::create('supplier', 5);
$account   = array_shift($supplier->related('account'));
$history   = array_shift($supplier->related('accounthistory'));
$history2  = ORMTable::create('accounthistory', 2);
$history3  = ORMTable::create('accounthistory', 5);

//echo "X: history: ".print_r($history2,true)."\n";

if($supplier2->isLinked($history3)) {
  echo "Yes its linked\n";
} else {
  echo "no its not linked\n";
}

echo "linking...\n";

$supplier2->link($history3, ORMTable::create('account', 5));

echo "Ok.\n";

*/

/*
echo "unlinking (has one through)...\n";

$supplier  = ORMTable::create('supplier', 1);
$history   = array_shift($supplier->related('accounthistory'));

if($supplier->isLinked($history)) {
  echo "Yes its linked\n";
} else {
  echo "no its not linked\n";
}

$supplier->unlink($history);
*/

/*
echo "Make tables...\n";

$fields = array(
  "name" => array(
    "type"   => "text",
  	"length" => 255
  )
);

$result = $db->createTable('patient', $fields);
if($result === false) {
  echo("Can't create table: ".$db->getError()."\n");
  exit(1);
}

$fields = array(
  "name" => array(
    "type"   => "text",
  	"length" => 255
  )
);

$result = $db->createTable('physician', $fields);
if($result === false) {
  echo("Can't create table: ".$db->getError()."\n");
  exit(1);
}

$fields = array(
  "name" => array(
    "type"   => "text",
  	"length" => 255
  ),
  "patient_id" => array(
    "type" => "integer"
  ),
  "physician_id" => array(
    "type" => "integer"
  )
);

$result = $db->createTable('appointment', $fields);
if($result === false) {
  echo("Can't create table: ".$db->getError()."\n");
  exit(1);
}

echo "Filling table...\n";

$db->query("INSERT INTO patient (name) VALUES ('mike smith');");
$db->query("INSERT INTO patient (name) VALUES ('jane doe');");
$db->query("INSERT INTO patient (name) VALUES ('mathew lowe');");
$db->query("INSERT INTO patient (name) VALUES ('sary parker');");

$db->query("INSERT INTO physician (name) VALUES ('dr bob');");
$db->query("INSERT INTO physician (name) VALUES ('dr weinstein');");
$db->query("INSERT INTO physician (name) VALUES ('dr phil');");
$db->query("INSERT INTO physician (name) VALUES ('dr biggy');");
 
$db->query("INSERT INTO appointment (name, patient_id, physician_id) VALUES ('one',1,1);");
$db->query("INSERT INTO appointment (name, patient_id, physician_id) VALUES ('two',2,2);");
$db->query("INSERT INTO appointment (name, patient_id, physician_id) VALUES ('three',3,3);");
$db->query("INSERT INTO appointment (name, patient_id, physician_id) VALUES ('four',4,4);");

$obj = ORMTable::create('patient', 3);
if(!$obj->isReady()) {
  echo "Could not make patient: ".$obj->getError()."\n";
  exit(1);
}
*/

/*
echo "unlinking (has many through)...\n";

$patient = ORMTable::create('patient', 2);
$doctor  = array_shift($patient->related('physician'));

if($doctor->isLinked($patient)) {
  echo "Yes its linked\n";
} else {
  echo "no its not linked\n";
}

$doctor->unlink($patient);
*/

/*
echo "fetching hasOneThrough()...\n";

$physician = $obj->hasOneThrough('physician');

//echo "X: physician: ".print_r($physician,true)."\n";

echo "fetching hasManyThrough()...\n";

$patient = $physician->hasManyThrough('patient');

echo "X: order: ".print_r($patient,true)."\n";

echo "Ok.\n";
*/

/*
$patient  = ORMTable::create('patient', 1);
$patient2 = ORMTable::create('patient', 2);
$doctor   = ORMTable::create('physician', 1);
$apt      = ORMTable::create('appointment');
$apt->name = "a new appointment";

$patient  = array_shift($doctor->related('patient'));

if($doctor->isLinked($patient2)) {
  echo "Yes its linked\n";
} else {
  echo "no its not linked\n";
}
$doctor->link($patient2, $apt);

*/

/*
echo "Make tables...\n";

$fields = array(
  "name" => array(
    "type"   => "text",
  	"length" => 255
  )
);

$result = $db->createTable('assembly', $fields);
if($result === false) {
  echo("Can't create table: ".$db->getError()."\n");
  exit(1);
}

$fields = array(
  "name" => array(
    "type"   => "text",
  	"length" => 255
  )
);

$result = $db->createTable('part', $fields);
if($result === false) {
  echo("Can't create table: ".$db->getError()."\n");
  exit(1);
}

$fields = array(
  "assembly_id" => array(
    "type"   => "integer"
  ),
  "part_id" => array(
    "type"   => "integer"
  )
);

$result = $db->createTable('assembly_part', $fields);
if($result === false) {
  echo("Can't create table: ".$db->getError()."\n");
  exit(1);
}

echo "Filling table...\n";

$db->query("INSERT INTO assembly (name) VALUES ('assembly a');");
$db->query("INSERT INTO assembly (name) VALUES ('assembly b');");
$db->query("INSERT INTO assembly (name) VALUES ('assembly c');");
$db->query("INSERT INTO assembly (name) VALUES ('assembly d');");

$db->query("INSERT INTO part (name) VALUES ('1 part');");
$db->query("INSERT INTO part (name) VALUES ('2 part');");
$db->query("INSERT INTO part (name) VALUES ('3 part');");
$db->query("INSERT INTO part (name) VALUES ('4 part');");

$db->query("INSERT INTO assembly_part (assembly_id, part_id) VALUES (1,1);");
$db->query("INSERT INTO assembly_part (assembly_id, part_id) VALUES (1,2);");
$db->query("INSERT INTO assembly_part (assembly_id, part_id) VALUES (1,3);");
$db->query("INSERT INTO assembly_part (assembly_id, part_id) VALUES (2,4);");
$db->query("INSERT INTO assembly_part (assembly_id, part_id) VALUES (3,1);");
$db->query("INSERT INTO assembly_part (assembly_id, part_id) VALUES (3,3);");
$db->query("INSERT INTO assembly_part (assembly_id, part_id) VALUES (4,4);");
*/

/*
$obj = ORMTable::create('assembly', 1);
if(!$obj->isReady()) {
  echo "Could not make assembly: ".$obj->getError()."\n";
  exit(1);
}

echo "fetching hasAndBelongsToMany()...\n";

$parts = $obj->related('part');

echo "X: parts: ".print_r($parts,true)."\n";

echo "Ok.\n";
*/

/*
$assembly  = ORMTable::create('assembly', 1);
$part      = ORMTable::create('part', 2);
$part4     = ORMTable::create('part', 4);

if($assembly->isLinked($part4,false)) {
  echo "Yes its linked\n";
} else {
  echo "no its not linked\n";
}

$assembly->link($part4);

if($part4->isLinked($assembly)) {
  echo "Yes its reverse linked\n";
} else {
  echo "no its not reverse linked\n";
}
*/

/*
echo "Unlinking assembly...\n";

$assembly  = ORMTable::create('assembly', 1);
$part      = array_shift($assembly->related('part'));

if($assembly->isLinked($part)) {
  echo "Yes its linked\n";
} else {
  echo "no its not linked\n";
}

$assembly->unlink($part);
*/

?>