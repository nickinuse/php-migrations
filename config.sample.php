<?php
$config=array(
    'directory'=>'migrate/',//where migrations are stored. don't forget the trailing slash!
/* possible values (column-type mappings known):
 *  db2, mysql, mysqli, openbase, oracle, postgresql, sqlite, sqlserver, sybase
 * only mysql hook provided/checked for now */
    'adapter'=>'mysql',
    'host'=>'localhost',
    'database'=>'test',
    'user'=>'root',
    'password'=>'',
    'escape'=>'`',//character used to escape table/column names
    'execute'=>true, //whether to actually execute sql or return the dump
/* name of the function that performs connection/login to your database
  	 and accepts sql to either execute (default) or echo depending on $config['execute'] */
    'hook'=>'_sql_hook',
);

date_default_timezone_set('UTC');
error_reporting(E_ALL);
//these will translate to e.g. mysql_connect()
$config['adapter_connect']=$config['adapter'].'_connect';
$config['adapter_error']=$config['adapter'].'_error';
$config['adapter_query']=$config['adapter'].'_query';
$config['adapter_select_db']=$config['adapter'].'_select_db';
//generic _sql_hook imlementation
if (!empty($config['execute'])) {
 $config['link'] = $config['adapter_connect']($config['host'], $config['user'], $config['password']);
 if (!$config['link'])
  die('Could not connect: ' . $config['adapter_error']() );
 $db_selected = $config['adapter_select_db']($config['database'], $config['link']);
 if (!$db_selected)
  die ('Can\'t use database : ' . $config['adapter_error']());
}
function _sql_hook($sql) {
 global $config;
 if ($config['rollback'])
  return FALSE;
 if (empty($config['execute']))
  return "\n".$sql;
 $result = $config['adapter_query']($sql);
 if (!$result) {
  echo("\n\nInvalid query: ".$config['adapter_error']()."\n");
  $config['rollback']=true;
 }
}
?>

