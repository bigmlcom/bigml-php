<?php

namespace BigML;

function assign_dir($path) {
   /*
     Silently checks the path for existence or creates it.
     Returns either the path or Null.
   */
   if ($path == null || !is_string($path)) {
      return null;
   }
      
   return check_dir($path);
}

function check_dir($path) {
   /*
     Creates a directory if it doesn't exist
   */
   if (file_exists($path)){
      if (!is_dir($path)) {
         throw new \Exception("The given path is not a directory");
      }
   } elseif (count($path) > 0) {
      if(!mkdir($path, 0777, true)) {
         throw new \Exception("Cannot create a directory");
      }   
   }
   return $path;
}

function maybe_save($resource, $path, $code, $location)
{
   /*
     Builds the resource dict response and saves it if a path is provided.
     The resource is saved in a local repo json file in the given path
   */
   if ($path != null &&  $resource["resource"] != null) {
      $resource_file_name = $path . DIRECTORY_SEPARATOR . str_replace('/','_',$resource["resource"]);

      $fp = fopen($resource_file_name, 'w');
      fwrite($fp, json_encode($resource));
      fclose($fp);

   }
}

function compareFiles($file_a, $file_b)
{
   if (filesize($file_a) == filesize($file_b) && 
       md5_file($file_a) == md5_file($file_b) ) {
      return true;
   }
   return false;
}

?>