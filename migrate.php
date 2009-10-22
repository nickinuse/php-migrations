<?php
/**
 *  Ruby-like abstract syntax migrations for PHP
 * (c) 2009 nickinuse@ofmy.info
 *
 * this file is auto-formatted with NetBeans 6.7, tab=1
 */


/**
 * returns corresponding type mapping for current $config['adapter']
 */
function _map($type='') {
 global $config,$_map;
 if (empty($_map[$config['adapter']][trim($type)]))
  die("Can't map ".$type." for ".$config['adapter']);
 return $_map[$config['adapter']][trim($type)];
}
/**
 *  wraps table/column names in escape char ($config['escape'])
 * < 'text' >'`text`'
 * < 'text.text' >'`text`.`text`'
 * < array('text','text') >'`text`.`text`'
 */
function _wrap($fields) {
 global $config;
 if (is_array($fields)) {
  array_walk($fields,'_wrap');
  $result= join(".",$fields);
 }
 else
  $result= $config['escape'].trim(
      str_replace('.', $config['escape'].".".$config['escape'], $fields)," ".$config['escape']
      ).$config['escape'];
 return $result;
}
//
function _escape(&$val) {
 if (is_null($val))
  $val="NULL";
 elseif(is_bool($val))
  $val=$val ? 1 : "NULL";
 elseif(is_array($val))
  $val=implode(",",$val);
 elseif(is_string($val)) {
  if ($val[0]=='\\')
   $val=substr($val,1);
  else
   $val="'".str_replace("'","\'",$val)."'";
 }
 return $val;
}
/**
 * moves the default value (if set) to an insert trigger
 * for column types that can't have defaults on their own
 */
function _move_default($column,$opt=array()) {
 global $config;
 if (isset($opt['default'])) {
  $config['trigger'][$column]=$opt['default'];
  unset($opt['default']);
 }
 return $opt;
}

/* "public" functionality */


/**
 *  shorthand function, see t_column()
 */
function t_integer($column,$opt=array()) {
 return t_column($column,'integer',$opt);
}
/**
 *  shorthand function, see t_column()
 */
function t_datetime($column,$opt=array()) {
 $opt=_move_default($column, $opt);
 return t_column($column,'datetime',$opt);
}
/**
 *  shorthand function, see t_column()
 */
function t_timestamp($column,$opt=array()) {
 return t_column($column,'timestamp',$opt);
}
/**
 *  shorthand function, see t_column()
 */
function t_text($column,$opt=array()) {
 $opt=_move_default($column,$opt);
 return t_column($column,'text',$opt);
}
/**
 *  shorthand function, see t_column()
 */
function t_string($column,$opt=array()) {
 return t_column($column,'string',$opt);
}
/**
 *  shorthand function, see t_column()
 */
function t_boolean($column,$opt=array()) {
 return t_column($column,'boolean',$opt);
}
/**
 *  shorthand function, see t_column()
 */
function t_binary($column,$opt=array()) {
 $opt=_move_default($column, $opt);
 return t_column($column,'binary',$opt);
}
/**
 *  shorthand function, see t_column()
 */
function t_decimal($column,$opt=array()) {
 return t_column($column,'decimal',$opt);
}
/**
 *  shorthand function, see t_column()
 */
function t_float($column,$opt=array()) {
 return t_column($column,'float',$opt);
}
/**
 * generates created_at and updated_at columns and default/update trigger for them
 */
function t_timestamps() {
 t_datetime('created_at',array("default"=>"\NOW()"));
 t_timestamp('updated_at',array('null'=>false,'default'=>"\CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"));
}
/* end of shorthand functions for common types */

/**
 * General purpose column generator<br>
 *<br>
 * <var>$column</var> column name<br>
 * <var>$type</var> lowercase general type name (see mappings and t_* helper functions)<br>
 * <var>$opt</var> array of options for the column where appliable:<br>
 * <var>$sql</var> whether to return the generated sql
 * <tt>limit</tt> maximum length<br>
 * <tt>null</tt> boolean whether to allow empty values<br>
 * <tt>default</tt> default value for the column<br>
 * * <b>note</b> to pass an SQL value/function, prepend it with "\"
 * e.g. 'default'=>"\NOW()" passes the result of the SQL function, 'default'=>"Text" passes the (escaped) string<br>
 * on some occasions an insert trigger is generated automatically to provide the defaults<br>
 * <tt>precision</tt> range start; e.g. maximum number of digits, M for DECIMAL(M,N)<br>
 * <tt>scale</tt> range end; e.g. floating point precision, N for DECIMAL(M,N)<br>
 * <tt>primary, unique, index (or with _key)</tt> boolean key aliases for a single column<br>
 * <tt>unsigned, zerofill, autoincrement</tt> aliases<br>
 * <tt>references</tt> foreign key declaration<br>
 * <tt>options</tt> for advanced column options<br>
 * <tt>after,before,first</tt> used for add_column() to denote column order in the table<br>
 * <br>
 * <code><pre>
 *  // same as t_integer ...
 *  t_column('name','integer',
 *   array('null'=>false,'default'=>'J.Doe','limit'=>50));
 *  // same as t_decimal ...
 *  t_column('price','decimal',
 *   array('precision'=>8,'scale'=>2,'default'=>0));
 *  // special case, should rather not be used, since create_table() already provides
 *  t_column('pkey','integer',
 * array('unsigned'=>true,'autoincrement'=>true,'primary'=>true) );
 * </pre></code>
 */
function t_column($column='',$type='',$opt=array(),$sql=false) {
 global $config;
 $glue=array();
 $glue[]=_wrap($column);
 $type=_map($type);
 if (!empty($opt['limit'])) {
  if (preg_match('@\(\d+\)$@',$type))
   $type=preg_replace('@\(\d+\)$@','',$type);
  $type.="(".$opt['limit'].")";
 }
 if (isset($opt['precision']))
  $type=preg_replace('@\([^,]+@','('.$opt['precision'],$type);
 if (isset($opt['scale']))
  $type=preg_replace('@,[^,]\)+@',','.$opt['scale'].')',$type);
 $glue[]=$type;
 $glue[]=!empty($opt['unsigned']) ? "UNSIGNED" : "";
 $glue[]=!empty($opt['zerofill']) ? "ZEROFILL" :"";
 $glue[]=isset($opt['null']) && !$opt['null']? "NOT" : "";
 $glue[]="NULL";
 $glue[]=!empty($opt['autoincrement']) ? "AUTO_INCREMENT" :"";
 foreach($config['indexes'] as $val=>$key) {
  if ( !empty($opt[$key]) || !empty($opt[$key.'_key']) )
   $config['fields'][]=strtoupper($key)." KEY ("._wrap($column).")";
 }
 $glue[]=isset($opt['default']) ? "DEFAULT "._escape($opt['default']) :"";
 $glue[]=isset($opt['references']) ? "REFEREBCES ".$opt['references'] : "";

 $glue[]=!empty($opt['first']) ? "FIRST" : "";
 $glue[]=!empty($opt['before']) ? "BEFORE "._wrap($opt['before']) : "";
 $glue[]=!empty($opt['after']) ? "AFTER "._wrap($opt['after']) : "";

 $glue[]=isset($opt['options']) ? $opt['options'] : "";
 array_walk($glue,'trim');
 foreach($glue as $key=>$val)
  if ($val=="") unset($glue[$key]);
 $config['fields'][$column]=join(" ", $glue);
}
/**
 * Create table magic
 * <pre>
 * create_table($table,[$options])
 * <var>$table</var> name of table to create
 * <var>$options</var> array of arbitrary t_* column definitions and options:
 *  <tt>force</tt> whether to drop the table if already exists
 *  <tt>primary_key</tt> what column name to use for primary key instead of the default `id`
 *  <tt>temporary</tt> whether the table is temporary
 *  <tt>options</tt> user-defined SQL options for the table.
 *   <b>note:</b> you loose the default TYPE=InnoDB and utf-8 encoding statement if specified
 *  <tt>id</tt> if false will opt to create a primary key-less table
 *               (unless specifically defined for a column)
 *  <tt>comment</tt> comment for the table
 * </pre>
 * <b>note</b> a special helper t_timestamps() is used to generate auto-tracking
 * columns for when the record was created and updated.
 * </pre>
 * <p><pre>
 * Create table with primary key named customer_id starting from 1000,
 * short text column of max 50 chars that can't be NULL and has default value <i>J.Doe</i>
 * boolean-like column to store whether premium is applied
 * binary column to store photo of max 2mb
 * integer column to store age
 * longer text column with the default value <i>No notes recorded</i>
 * created_at column which will store time when the record was created
 * updated_at column which will store time when the record was last edited
 * </pre>
 * <pre><code>
 * create_table('customers',
 *  array('force'=>true,"primary_key"=>"customer_id",'options'=>"auto_increment=1000",
 *   t_integer('customer_id'),//actually not needed
 *   t_string('name',array('limit'=>50,'null'=>false,'default'=>"'J.Doe'")),
 *   t_boolean('premium',array('default'=>null)),
 *   t_binary('photo',array('limit'=>2*1024*1024)),
 *   t_integer('age'),
 *   t_text('notes',array('default'=>"'No notes recorded'")),
 *   t_timestamps()
 * ));
 * </code>
 * </pre></p>
 */
function create_table($table="",$opt=array(),$fields=array()) {
 global $config;
 $glue=array();
 if (!empty($opt['force'])) {
  $glue[]="DROP";
  $glue[]=!empty($opt['temporary']) ? "TEMPORARY" :"";
  $glue[]="TABLE IF EXISTS "._wrap($table).";\n";
  execute(trim(join(" ",$glue)));
  $glue=array();
 }
 $glue[]="CREATE";
 $glue[]=!empty($opt['temporary']) ? "TEMPORARY":"";
 $glue[]="TABLE "._wrap($table);
 if (!(isset($opt['id']) && !$opt['id']) ) //no primary key option
 {
  $pkey=!empty($opt['primary_key']) ? _wrap($opt['primary_key']) : 'id';
  if (!isset($config['fields'][$pkey]))
   $config['fields']=array_merge(array($pkey=>""),$config['fields']);
  t_column($pkey,'integer',array('unsigned'=>true,'autoincrement'=>true,'primary_key'=>true) );
 }
 $glue[]="\n(\n".join(",\n",$config['fields'])."\n)";
 $glue[]=!empty($opt['comment']) ? 'COMMENT="'.$opt['comment'].'"' : "";
 $glue[]=!empty($opt['options']) ? $opt['options'] : "TYPE = InnoDB /*!40100 DEFAULT CHARSET utf8 COLLATE utf8_general_ci */";
 array_walk($glue,'trim');
 $glue[]=";";
 execute(trim(join(" ",$glue)));
 if (!empty($config['trigger'])) {
  foreach($config['trigger'] as $field=>$value)
   $config['trigger'][$field]="NEW."._wrap($field)."="._escape($value);
  execute("CREATE TRIGGER event_insert BEFORE INSERT ON "._wrap($table).
      "\nFOR EACH ROW SET\n\t".implode(",\n\t",$config['trigger']));
 }
 $config['fields']=$config['trigger']=$config['indexes']=array();
}//create table


/**
 * method to delete table
 */
function drop_table($table) {
 execute("DROP TABLE "._wrap($table).";");
}
/**
 * method to rename table
 */
function rename_table($old_name,$new_name) {
 execute("RENAME TABLE "._wrap($old_name)." TO "._wrap($new_name));
}
/**
 * method to add a column.<pre>
 * you can use t_column()-like syntax
 *  add_column('table','column_name','integer',array('default'=>25));
 * or helper functions, e.g.
 *  add_column('table',t_integer('column_name',array('default'=>25)) );
 * </pre>
 */
function add_column($table,$column=null,$type="string",$opt=array()) {
 global $config;
 if ($column!==null)
  t_column($column,$type,$opt);
 else
  $column=key($config['fields']);
 execute('ALTER TABLE '._wrap($table).' ADD '. $config['fields'][$column]);
 unset($config['fields'][$column]);
}
/**
 * params similar to add_column()
 */
function change_column($table,$column=null,$type="string",$opt=array()) {
 global $config;
 $ary=func_get_args();
 $table=array_shift($ary);
 if ($column!==null)
  t_column($column,$type,$opt);
 else
  $column=key($config['fields']);
 execute('ALTER TABLE '._wrap($table).' CHANGE '._wrap($column)." ".$config['fields'][$column]);
 unset($config['fields'][$column]);
}
/**
 * sadly, you need to specify the column definition.
 * params starting at $new_name can be replaced by the according t_* helper
 * [see add_column() syntax]
 */
function rename_column($table,$column,$new_name,$type="string",$opt=array()) {
 global $config;
 $ary=func_get_args();
 $table=array_shift($ary);
 $column=array_shift($ary);
 if ($new_name!==null)
  t_column($new_name,$type,$opt);
 else
  $new_name=key($config['fields']);
 execute('ALTER TABLE '._wrap($table).' CHANGE '._wrap($column)." ".$config['fields'][$new_name]);
 unset($config['fields'][$new_name]);
}
/**
 * remove 1 or more columns from $table<pre>
 * remove_column('table','column1','column2');
 * remove_column('table',array('column1','column2'));
 * </pre>
 */
function remove_column($table,$column="*column(s) or array") {
 $ary=func_get_args();
 $table=array_shift($ary);
 if (is_array($column))
  $ary=$column;
 foreach($ary as $column)
  execute('ALTER TABLE '._wrap($table).' DROP '._wrap($column));
}
/**
 * inserts data to $table from one array at a time.
 * prepend value with "\" to denote SQL, e.g. 'current_time'=>'\NOW()'
 * <br>
 * create('table',array('name'=>'John'),array('name'=>'Jane','surname'=>'Doe'), ...);
 */
function create() {
 global $config;
 $params=func_get_args();
 $table=array_shift($params);
 while ( ($ary=array_shift($params)) !==false) {
  foreach($ary as $key=>$value) {
   $value=_escape($value);
   $ary[]="VALUES(".implode(',',$ary).")";
  }
  execute("INSERT INTO "._wrap($table)." (".$config['escape'].implode(_wrap(','),array_keys($ary)).
      $config['escape'].") VALUES(".implode(",",array_values($ary)).");");
 }
}
function add_index($table,$index) {
 execute('ALTER TABLE '._wrap($table).' ADD INDEX '._wrap($index));
}
function remove_index($table,$index) {
 execute('ALTER TABLE '._wrap($table).' DROP INDEX '._wrap($index));
}
function delete_all($table) {
 execute("DELETE FROM "._wrap($table));
}
/**
 * for running some sql directly
 */
function execute($sql) {
 global $config;
 echo str_replace("\n","\n\t","\n$sql\n");
 $config['hook']($sql);
}
/**
 * raise error if migration can't be undone
 */
function IrreversibleMigration($message) {
 die("Irreversible change: $message");
}

function create_db($database="") {
 global $config;
 if (!$database) $database=$config['database'];
 execute("CREATE DATABASE "._wrap($database)." /*!40100 CHARACTER SET utf8 COLLATE utf8_general_ci */");
}
function drop_db($database="") {
 global $config;
 if (!$database) $database=$config['database'];
 execute("DROP DATABASE "._wrap($database));
}

/**
 * generate migration
 */
function _generate($name) {
 global $config;
 $name=_to_filename($name);
 if (!$name)
  die("check migration name, resulted empty");
 @mkdir($config['directory']);
 $ary=glob($config['directory'].'*'.$name.'.php');
 if ($ary)
  die("name is ambiguous:\n\t".implode("\n\t",$ary));
 $file=$config['directory'].date('YmdHis').'_'.$name.'.php';
 if (file_exists($file))
  die("version already exists as $file");
 $fp=fopen($file,'w');
 fwrite($fp,"<?php\nclass "._camelize($name)."\n{\n\tfunction up(){//add changes\n\t\t".
     "\n\t}\n\tfunction down(){//revert changes\n\t\t\n\t}\n}\n?>");
 fclose($fp);
 echo "\n generated $file";
}
/* *
 * internal function to convert provided string to filename
 */
function _to_filename($name) {
 $name=preg_replace('@([A-Z])@','_$1',$name);
 $name=preg_replace('@[^a-z0-9_]@i', '_',$name);
 $name=preg_replace('@(_+)@','_',trim($name,"_"));
 $name=strtolower($name);
 return $name;
}
function _camelize($name) {
 $name=ucwords(str_replace('_',' ',$name));
 return str_replace(' ','',$name);
}

function _version($version="") {
 global $config;
 if ($version) {
  $config['version']=$version;
  $fp=fopen('version.php','w');
  fwrite($fp,"<?php \$config['version']='$version'; ?>");
  fclose($fp);
 }
 return @$config['version'];
}

function _migrate($param=null) {
 global $versions,$config;
 //move to current version index
 $version=_version();
 if ($version) {
  if (!in_array($version,$versions))
   exit("\n$version doesn't match existing migrations");
  reset($versions);
  //go to current version
  while (($tmp=current($versions))!==false) {
   if ($tmp==$version) {
    echo "\ncurrent version: $tmp";
    break;
   }
   else
    next($versions);
  }
 }
 //process param
 if ($param===null)
  $param=count($versions);//migrate up while can
 elseif(is_string($param)) {
  if (!in_array($param,$versions))
   die("\nno version '$param' matched");
  echo "\ntarget migration $param";
  $param=array_search($param,$versions)-($version ? array_search($version,$versions) : 0);
  echo " ($param)";
 }
 //set direction and steps
 $step='next';
 if ($param<0) {
  $step='prev';
  $param=-$param;
  if (!$version)
   die("\ncan't migrate down");
 }
 elseif (current($versions)==$version)
  next($versions);
 execute("SET autocommit=0;");
 while($param-->0) {
  $tmp=current($versions);
  $step($versions);
  if ($tmp!==false) {
   $version=$tmp;
   $migration=substr($version,strpos($version,'_')+1);
   $migration=str_replace('.php','',$migration);
   $migration=_camelize($migration);
   $at=array_sum(explode(" ",microtime()));
   echo "\n-- ".($step=='next' ? "apply" :"revert")." $migration\n";
   include $config['directory'].$version;
   $migration=new $migration;
   execute("START TRANSACTION;");
   if ($step=='next') {
    $migration->up();
    _version($version);
   }
   else {
    $migration->down();
    if (current($versions)===false)
     @unlink('version.php');
    else
     _version(current($versions));
   }
   execute("COMMIT;");
   echo "\n-- completed in ".(array_sum(explode(" ",microtime()))-$at)." --\n";
  }
  else
   exit();
 }
}

error_reporting(E_ALL);
require 'config.php';
$config['adapter']=strtolower($config['adapter']);
$config['fields']=$config['trigger']=$config['indexes']=array();
$config['indexes']=array('primary','unique','index');
$mapping="
Rails	db2	mysql	openbase	Oracle
:binary	blob(32678)	blob	object	blob
:boolean	decimal(1)	tinyint(1)	boolean	number(10)
:date	date	date	date	date
:datetime	timestamp	datetime	datetime	date
:decimal	decimal	decimal	decimal	decimal
:float	float	float	float	number
:integer	int	int(11)	integer	number(38)
:string	varchar(255)	varchar(255)	char(4096)	varchar2(255)
:text	clob(32768)	text	text	clob
:time	time	time	time	date
:timestamp	timestamp	timestamp	timestamp	date
Rails	postgresql	sqlite	sqlserver	Sybase
:binary	bytea	blob	image	image
:boolean	boolean	boolean	bit	bit
:date	date	date	datetime	datetime
:datetime	timestamp	datetime	datetime	datetime
:decimal	decimal	decimal	decimal	decimal
:float	float	float	float(8)	float(8)
:integer	integer	integer	int	int
:string	*	varchar(255)	varchar(255)	varchar(255)
:text	text	text	text	text
:time	time	datetime	datetime	time
:timestamp	timestamp	datetime	datetime	timestamp";
//parse type mappings for different adapters
$_map=array();
$mapping=explode("\r\n",trim($mapping));
foreach($mapping as $line) {
 $line=preg_replace('@\s+@','_',$line);
 if ($line[0]!=':') {
  $adapters=explode('_',strtolower($line));
  array_shift($adapters);
 }
 else {
  $types=explode('_',strtoupper($line));
  list($type,$ary)=array(array_shift($types),$types);
  $type=strtolower(str_replace(':','',$type));
  foreach($adapters as $adapter) {
   if (!isset($_map[$adapter]))
    $_map[$adapter]=array();
   $_map[$adapter][$type]=array_shift($ary);
 }//each adapter
}//else
}//each line
$_map['mysqli']=$_map['mysql'];//alias

function _newline() { echo "\n"; }
register_shutdown_function("_newline");

@include 'version.php';
@mkdir($config['directory']);
$ary=glob($config['directory']. '*.php');
$versions=array();
foreach($ary as $key=>$item) {
 $info=pathinfo($item);
 $versions[]=$info['basename'];
}
array_shift($argv);//filename
function _argv($key="") {
 global $argv,$config;
 $tmp=array_shift($argv);
 if (!$key)
  return $tmp;
 if (!$tmp) return $tmp;
 return $config[$key]=$tmp;
}
if (empty($argv) || array_intersect(array('--help','help','/?'), $argv)) {
 echo "
  db [command] [options]

 commands:

  help or /?                this message

  create [db_name]          creates database specified in params or config

  drop [db_name]            drops the specified database in params or config

  up|down                   run one migration up/down

  migrate [option]            empty:    migrate all unapplied versions up
                            up/down:    migrate one version up/down
                              delta:    see roll
                               zero:    revert all migrations

  version [name]            show current version or migrate to specific version
                            where name is (the beginning of) a timestamp or
                            \"zero\" to revert all migrations

  generate name             generate a migration with the specified descriptive name
                            name should be CamelCased and/or underscore_separated

  roll [+/-delta]           delta specifies number of up/down migrate steps to apply

  methods                   lists methods to use in migrations

  reset                     recreate database completely

  backup                    create backup of the database using current timestamp

  unspecified command       defaults to [version]
";
}
else
 while($argv) {
  $key=strtolower(array_shift($argv));
  switch($key) {
   case 'create':
    _argv('database');
    create_db();
    break;
   case 'drop':
    _argv('database');
    drop_db();
    break;
   case 'up':
    _migrate(+1);
    break;
   case 'down':
    _migrate(-1);
    break;
   case 'migrate':
    $param=_argv();
    if ($param=="zero")
     $param=-count($versions);
    switch($param) {
     case 'up':
      _migrate(+1);
      break;
     case 'down':
      _migrate(-1);
      break;
     default:
      if (!$param)
       $param=null;
      elseif ((string)(int)$param==$param)
       $param=(int)$param;
      _migrate($param);
      break;
    }//switch migrate params
    break;//migrate
   case 'version':
    $param=_argv();
    if (!$param)
     die("\ncurrent version: ".(_version() ? _version() : "none"));
    if ($param!="zero") {
     $ary=glob($config['directory'].$param.'*');
     if ($param=="")//from zero
      $ary=array($ary[0]);
     if (!count($ary) || count($ary)>1)
      die("\nmultiple or none versions matched:\n\t".implode("\n\t",$ary));
     $param=array_shift($ary);
     $param=substr($param,strlen($config['directory']));
    }
    else
     $param=-count($versions);
    _migrate($param);
    break;
   case 'reset':
    drop_db();
    create_db();
    @unlink('version.php');
    _migrate();
    break;
   case   'roll':
    $param=_argv();
    if (!$param || (string)(int)$param!=$param)
     die("roll +/-step count, e.g. roll -4 or roll +2");
    _migrate((int)$param);
    break;
   case   'generate':
    $param=_argv();
    if (!$param)
     die("expecting generate [CamelCased or under_scored name]");
    _generate($param);
    break;
   case 'backup':
    $param=_argv();
    $param=_to_filename($param);
    if (!$param)
     $param=$config['database'].'_'.date('ymdhis').'.sql';
    echo "\ncreating $param";
    if (substr($config['adapter'],0,5)=='mysql')
     shell_exec('mysqldump --user "'.$config['user'].'" --password="'.$config['password'].'" "'.$config['database'].'" > "'.$param.'"');
    else
     die("not implemented yet");
    break;
   case 'methods':
    $ary=get_defined_functions();
    $ary=$ary['user'];
    foreach($ary as $key=>$val) {
     if ($val[0]=='_')
      unset($ary[$key]);
    }
    sort($ary);
    echo "\n  available methods:\n\n\t".implode("\n\t",$ary);
    break;
   default:
    echo "\n"._version();
    break;
 }//switch argv
}//while argv
?>