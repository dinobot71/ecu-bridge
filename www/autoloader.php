<?php
{   
  $DS = "\\";   
  if(strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    $DS = "\\";
  } else {
    $DS = "/";
  }
  
  $autoloaders = array(
    array('library', 'littlemdesign.com', 'autoloader.php'),
    array('library', 'chumpcar',          'autoloader.php')
  );
  
  foreach($autoloaders as $spec) {
    $path = implode($DS, $spec);
    require_once($path);
  }
}
?>
