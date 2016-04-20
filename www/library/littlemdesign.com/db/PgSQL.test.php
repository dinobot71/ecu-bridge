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

/*
 * test code, for testing us a sample database:
 * 
 *   http://launchpad.net/test-db
 *   http://www.commandprompt.com/ppbook/booktown.sql
 *   
 * 
 */

/* connect to database */

/*
echo "Initializing...\n";

$dsn = "pgsql://rolleradmin:roller@localhost/rollermarathon";
$db  = new littlemdesign_db_PgSQL($dsn); 
if(!$db->isReady()) {
  echo "Can not connect to database: ".$db->getError()."\n";
  exit(1);
}

echo "Connected.\n";

echo "doing query...\n";

$result = $db->query("SELECT * FROM books;");
//$result = $db->query("DELETE FROM departments WHERE dept_name='Sales';");
//$result = $db->query("INSERT INTO departments (dept_n, dept_name) VALUES ('d007', 'Sales');");

if($result === false) {
  echo "can not do query: ".$db->getError()."\n";
  exit(1);
}
echo "result: ".print_r($result,true)."\n";
*/

/*
echo "Initializing...\n";

$dsn = "pgsql://rolleradmin:roller@localhost/rollermarathon";
$db  = new littlemdesign_db_PgSQL($dsn); 
if(!$db->isReady()) {
  echo "Can not connect to database: ".$db->getError()."\n";
  exit(1);
}

echo "Connected.\nCreating table...\n";

$result = $db->createTable('ANewTable', array(
  "a_col" => array(
    "type"    => "integer",
    "length"  => 4,
    "default" => 3
  ),
  "b_col" => array(
    "type"    => "text",
    "length"  => 32,
    "default" => 'mmm'
  )
));

if($result === false) {
  echo "Can not create table: ".$db->getError()."\n";
  exit(1);
}
*/

/*
echo "Initializing...\n";

$dsn = "pgsql://rolleradmin:roller@localhost/rollermarathon";
$db  = new littlemdesign_db_PgSQL($dsn); 
if(!$db->isReady()) {
  echo "Can not connect to database: ".$db->getError()."\n";
  exit(1);
}

echo "Connected.\nlisting databases...\n";

$result = $db->getDatabases();
if($result === false) {
  echo "Can not list databases: ".$db->getError()."\n";
  exit(1);
}

echo "X: ".print_r($result,true)."\n";

*/

/*
echo "Initializing...\n";

$dsn = "pgsql://rolleradmin:roller@localhost/rollermarathon";
$db  = new littlemdesign_db_PgSQL($dsn); 
if(!$db->isReady()) {
  echo "Can not connect to database: ".$db->getError()."\n";
  exit(1);
}

echo ". creating...\n";
$result = $db->createDatabase("anewdb");
if($result === false) {
  echo "Can not create databases: ".$db->getError()."\n";
  exit(1);
}

echo ". listing...\n";

$dbs = $db->getDatabases();
if(!in_array("anewdb", $dbs)) {
  echo "Can not find created database.\n";
  exit(1);	
}

echo ". dropping...\n";

$reuslt = $db->dropDatabase("anewdb");
if($result === false) {
  echo "Can not drop databases: ".$db->getError()."\n";
  exit(1);
}

echo "Done.\n";
*/

/*
echo "Initializing...\n";

$dsn = "pgsql://rolleradmin:roller@localhost/rollermarathon";
$db  = new littlemdesign_db_PgSQL($dsn); 
if(!$db->isReady()) {
  echo "Can not connect to database: ".$db->getError()."\n";
  exit(1);
}

echo ". query...\n";
$result = $db->getCurrentDatabase();
if($result === false) {
  echo "Can not get current databases name: ".$db->getError()."\n";
  exit(1);
}

echo "X: $result\n";
*/

/*
echo "Initializing...\n";

$dsn = "pgsql://rolleradmin:roller@localhost/rollermarathon";
$db  = new littlemdesign_db_PgSQL($dsn); 
if(!$db->isReady()) {
  echo "Can not connect to database: ".$db->getError()."\n";
  exit(1);
}

echo ". setting database...\n";
$result = $db->listTables();
if($result === false) {
  echo "Can not set current databases: ".$db->getError()."\n";
  exit(1);
}

echo "X: ".print_r($result,true)."\n";
*/

/*
echo "Initializing...\n";

$dsn = "pgsql://rolleradmin:roller@localhost/rollermarathon";
$db  = new littlemdesign_db_PgSQL($dsn); 
if(!$db->isReady()) {
  echo "Can not connect to database: ".$db->getError()."\n";
  exit(1);
}

echo ". checking...\n";
$result = $db->tableExists('daily_inventory');

echo "X: ".print_r($result,true)."\n";
*/

/*
echo "Initializing...\n";

$dsn = "pgsql://rolleradmin:roller@localhost/rollermarathon";
$db  = new littlemdesign_db_PgSQL($dsn); 
if(!$db->isReady()) {
  echo "Can not connect to database: ".$db->getError()."\n";
  exit(1);
}

echo ". checking...\n";
$result = $db->databaseExists('rollermarathon');

echo "X: ".print_r($result,true)."\n";
*/

/*

echo "Initializing...\n";

$dsn = "pgsql://rolleradmin:roller@localhost/rollermarathon";
$db  = new littlemdesign_db_PgSQL($dsn); 
if(!$db->isReady()) {
  echo "Can not connect to database: ".$db->getError()."\n";
  exit(1);
}

echo "Creating table...\n";

$result = $db->createTable('ANewTable', array(
  "a_col" => array(
    "type"    => "integer",
    "length"  => 4,
    "default" => 3
  ),
  "b_col" => array(
    "type"    => "text",
    "length"  => 32,
    "default" => 'mmm'
  )
));

if($result === false) {
  echo "Can not create table: ".$db->getError()."\n";
  exit(1);
}

echo "Dropping that table...\n";

$result = $db->dropTable('ANewTable');
if($result === false) {
  echo "Can not drop table: ".$db->getError()."\n";
  exit(1);
}

echo "done.\n";
*/

/*
echo "Initializing...\n";

$dsn = "pgsql://rolleradmin:roller@localhost/rollermarathon";
$db  = new littlemdesign_db_PgSQL($dsn); 
if(!$db->isReady()) {
  echo "Can not connect to database: ".$db->getError()."\n";
  exit(1);
}

echo "Connected.\nlisting columns...\n";

$result = $db->columnsOfTable('event');
if($result === false) {
  echo "Can not list columns: ".$db->getError()."\n";
  exit(1);
}

echo "X: ".print_r($result,true)."\n";

*/

echo "Initializing...\n";

$dsn = "pgsql://rolleradmin:roller@localhost/rollermarathon";
$db  = new littlemdesign_db_PgSQL($dsn); 
if(!$db->isReady()) {
  echo "Can not connect to database: ".$db->getError()."\n";
  exit(1);
}

/*
echo "Connected.\n";

echo "doing query...\n";

$result = $db->query("INSERT INTO event (name,web,description) VALUES ('aname' ,'', '');");

if($result === false) {
  echo "can not do query: ".$db->getError()."\n";
  exit(1);
}
$id = $db->getLastId();

echo "result ($id): ".print_r($result,true)."\n";
*/


?>