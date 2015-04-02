#!/usr/bin/php -q
<?php
// --generateconfig
// functionality to Create Config File Dump with list of databases and tables from source 
// ask for destination db host name and credentials


// functionality to read a config file and start executing 
// the lines (mysqldump accordingly ) and executing on destination server
// drop and copy all views and stored procedures and triggers from a database

$view_dependency_hierarchy_length = 4 ;

function parseArguments(){
	// copied from https://gist.github.com/magnetik/2959619 
	global $argv ;

	array_shift($argv);
	$out = array();

	foreach($argv as $arg){
		if(substr($arg, 0, 2) == '--'){
			$eqPos = strpos($arg, '=');
			if($eqPos === false){
				$key = substr($arg, 2);
				$out[$key] = isset($out[$key]) ? $out[$key] : true;
			}else{
				$key = substr($arg, 2, $eqPos - 2);
				$out[$key] = substr($arg, $eqPos + 1);
			}

		}else if(substr($arg, 0, 1) == '-'){

			if(substr($arg, 2, 1) == '=')
			{
				$key = substr($arg, 1, 1);
				$out[$key] = substr($arg, 3);
			}
			else
			{
				$chars = str_split(substr($arg, 1));
				foreach($chars as $char)
				{
					$key = $char;
					$out[$key] = isset($out[$key]) ? $out[$key] : true;
				}
			}
		}
		else
		{
			$out[] = $arg;
		}
	}
	return $out;
}



class CFWK_DB {
	var $DB_HOST = '' ;
	var $DB_USERNAME = '' ;
	var $DB_PASSWORD = '' ;	
	var $DB_NAME = '' ;	
	var $DB_LINK ;
	var $query_count = 0;
	var $DB_RESULT ;
	var $DB_LAST_INSERT_ID ;
	var $SQODC = 0 ; // should_quit_on_db_connectionFail
	var $effectedRows = 0;
	
	public function db_init(){
		try {
			if( $this->DB_NAME != '' ){
				$str = "mysql:host={$this->DB_HOST};" ;
			}else{
				$str = "mysql:host={$this->DB_HOST};dbname={$this->DB_NAME}" ;
			}
			$this->DB_LINK = new PDO( $str , $this->DB_USERNAME , $this->DB_PASSWORD );
		} catch (PDOException $e) {
			echo 'Connection failed: ' . $e->getMessage();
			if( $this->SQODC ){
				exit();
			}else{
				return false;
			}
		}
		
		return true;
	}
	
	public function exequery($query){
		$this->effectedRows = 0 ;
		
		if( $this->query_count == 0 ){
			$this->db_init();
		}
		$this->query_count++ ;
		try{
			$this->DB_RESULT = $this->DB_LINK->query( $query ) ;
			if($this->DB_RESULT){
				$this->effectedRows = $this->DB_RESULT->rowCount();
			}else{
				$this->effectedRows = 0;
			}
		} catch (PDOException $e) {
			print "Error!: " . $e->getMessage() . "\n Query: {$query} \n";
		}
		
		return $this->DB_RESULT ;
	}
	
	public function exequery_return_single_val($query){
		$res_array = $this->exequery_return_strict_array($query);
		return (count($res_array)==1 ) ? $res_array[0] : '' ;
	}
	
	public function exequery_return_strict_array($query){
		$vars = array();
		$this->exequery( $query );
		$result = $this->DB_RESULT->setFetchMode( PDO::FETCH_ASSOC );
		while( $row = $this->DB_RESULT->fetch() ){
			foreach($row as $key => $val){
				array_push($vars, $val);
			}
		}
		return $vars;
	}

	public function exequery_return_MultiAssocArray($query){
		$vars = array();		
		$this->exequery( $query );
		$result = $this->DB_RESULT->setFetchMode( PDO::FETCH_ASSOC );
		while( $row = $this->DB_RESULT->fetch() ){
			$vars[] = $row ;
		}
		return $vars;
	}
	
	public function exequery_return_single_AssocArray_select_pairs_of_key_vals($query){
		// ex: select username , emailid from tbl
		// returns array('chandu' => 'chandus@emailId' , 'sagar' => 'sagars@emailId'  )
		$result = $this->exequery_return_MultiAssocArray($query);
		if(!count($result)){ return array(); }
		
		$toReturn = array();
		$heads = array();
		if( count($result[0]) !=1 && count($result[0]) !=2  ){ return array(); } // ALERT: INVALID USE		
		
		$heads = array_keys($result[0]);
		if( count($heads) == 1 ){
			$heads[] = $heads[0] ;
		}

		foreach( $result as $this_row ){
			$toReturn[ $this_row[$heads[0]] ] = $this_row[ $heads[1] ] ;
		}
		return $toReturn ;
	}
	
	public function exequery_return_single_row_as_AssocArray($query){
		$result = $this->exequery_return_MultiAssocArray($query);
		return (count($result) == 1 ) ? $result[0] : array();
	}

}




$arguments = parseArguments();

if( count($arguments) == 1 &&  array_key_exists('help', $arguments) ){
	
	echo "\n--help Prints this help\n";

	echo "\n--generateconfig --h=HOST --u=USER --p=PASSWORD {--databases=db1,db2,db3}
	connects to mysql server HOST with USER and PASSWORD
	and generates initial config file outputs to stdout
	use output redirection to save into specific config file.
	You can later edit this file to add specific conditions against each table or database.
	";

	echo "\n--useconfig=FILE --sh=HOST --su=USER --sp=PASSWORD {--dh=HOST --du=USER --dp=PASSWORD} {--opf=dump.sql} --locktables='Y'
	Parses config FILE and connects to source host 'sh'
	and restores tables into destination host 'dh'
	while applying conditions configured in FILE
	\n";

}


if( array_key_exists('generateconfig', $arguments) ){
	$CONFIG_FILE = array();
	$MYSQLCONN = new CFWK_DB();
	$MYSQLCONN->DB_HOST = $arguments['h'];
	$MYSQLCONN->DB_USERNAME = $arguments['u'];
	$MYSQLCONN->DB_PASSWORD = $arguments['p'];
	$MYSQLCONN->db_init();

	if( array_key_exists('databases', $arguments ) ){
		$ListOfDatabases = explode(",", $arguments['databases'] );
	}else{
		$ListOfDatabases = $MYSQLCONN->exequery_return_strict_array("show databases");	
	}
	
	foreach( $ListOfDatabases as $this_db ){
		$CONFIG_FILE[ $this_db ] = array();

		$MYSQLCONN->exequery( "use {$this_db}" );
		$tables = $MYSQLCONN->exequery_return_MultiAssocArray( "show full tables where Table_Type != 'VIEW'" );

		$validTableNames = array();
		foreach($tables as $thisTbl ){
			$this_tbl_column = '';
			foreach($thisTbl as $colname => $colval ){
				if($colname != 'Table_type'){
					$this_tbl_column = $colname ;
				}
			}
			
			$validTableNames[] = $thisTbl[$this_tbl_column]  ;
		}

		$tables_sizes_in_MB = $MYSQLCONN->exequery_return_MultiAssocArray(" SELECT table_name, round(((data_length + index_length) / 1024 / 1024), 2) as `Size_in_MB`  FROM information_schema.TABLES  WHERE table_schema = '{$this_db}' order by data_length desc  ") ;

		foreach($tables_sizes_in_MB as $this_table_row ){
			if( in_array($this_table_row['table_name'] , $validTableNames ) ){

				$CONFIG_FILE[ $this_db ][] = array(
					'tableName' => $this_table_row['table_name'] ,
					'whereCondition' => "" ,
					'size' => $this_table_row['Size_in_MB'],
					'exportTable' => 'Y'
				);
			} 
		}

	}

	$CONFIG_FILE = json_encode($CONFIG_FILE, JSON_PRETTY_PRINT );

	echo $CONFIG_FILE;
}




if( array_key_exists('useconfig', $arguments) ){
// --useconfig=FILE -sh=HOST -su=USER -sp=PASSWORD {-dh=HOST -du=USER -dp=PASSWORD} {-opf=op.txt} --locktables='Y'
	$pwd = dirname(__FILE__)."/";
	$ipfile = $arguments['useconfig'] ;
	$sh = $arguments['sh'] ;
	$su = $arguments['su'] ;
	$sp = $arguments['sp'] ;

	// by default we use mysqldump in skip-lock-tables mode
	$lock_string = (array_key_exists('locktables', $arguments) && $arguments['locktables'] =='Y' ) ? " --lock-tables " : " --skip-lock-tables " ;

	$MYSQLCONN = new CFWK_DB();
	$MYSQLCONN->DB_HOST = $sh;
	$MYSQLCONN->DB_USERNAME = $su ;
	$MYSQLCONN->DB_PASSWORD = $sp;
	$MYSQLCONN->db_init();

	if( array_key_exists('opf', $arguments) ) {
		$opf = $pwd.$arguments['opf'] ;	
		$pipe_destination = "" ;
	}else{
		$opf = '' ;
		$dh = $arguments['dh'] ;
		$du = $arguments['du'] ;
		$dp = $arguments['dp'] ;
		$pipe_destination = " mysql -u {$du} -p{$dp} -h {$dh} " ;

		$MYSQLCONN_DEST = new CFWK_DB();
		$MYSQLCONN_DEST->DB_HOST = $dh;
		$MYSQLCONN_DEST->DB_USERNAME = $du ;
		$MYSQLCONN_DEST->DB_PASSWORD = $dp;
		$MYSQLCONN_DEST->db_init();
	}
	
	$config = json_decode(file_get_contents( $ipfile ) , true );
	
	if($opf){
		file_put_contents( $opf , "\n" ); // reset file	
	}

	foreach( $config as $dbname => $tables  ){
		echo "\n********************";
		echo "\nexporting database {$dbname}" ;
		if($opf){
			file_put_contents( $opf , "\ndrop database if exists `{$dbname}` ; " , FILE_APPEND );
			file_put_contents( $opf , "\ncreate database `{$dbname}` ; \n\n " , FILE_APPEND );
		}else{
			exec( "echo 'drop database if exists `{$dbname}`' |  {$pipe_destination} ");
			exec( "echo 'create database `{$dbname}`' |  {$pipe_destination} ");
		}

		// TODO : any new tables on source dbhost which are not in config file must be exported also 
		foreach($tables as $this_tableName ){
			$where_string = (trim($this_tableName['whereCondition'])) ? " --where=\"{$this_tableName['whereCondition']}\" " : "" ; 
			if( $this_tableName['exportTable'] == 'Y' ){
				echo "\nexporting table {$this_tableName['tableName']}\n" ;
				//echo "mysqldump --routines --triggers -u {$su} -p{$sp} -h {$sh} {$dbname} {$this_tableName['tableName']} {$where_string} > {$opf}"."\n";
				$dump_exec_cmd = "mysqldump {$lock_string} --triggers -u {$su} -p{$sp} -h {$sh} {$dbname} {$this_tableName['tableName']} {$where_string}" ;
				if($opf){
					exec( " {$dump_exec_cmd} >> {$opf}" );	
				}else{
					exec( " {$dump_exec_cmd} | {$pipe_destination} {$dbname} " );	
				}
			}
		}

		// export all views from source database 
		$MYSQLCONN->exequery( "use {$dbname}" );
		$tables = $MYSQLCONN->exequery_return_MultiAssocArray( "show full tables where Table_Type = 'VIEW' " );
		$listOfViews = array();
		foreach( $tables as $thisTbl ){
			$this_tbl_column = '' ;
			foreach( $thisTbl as $colname => $colval ){
				if( $colname != 'Table_type' ){
					$this_tbl_column = $colname ;
				}
			}
			$listOfViews[] = $thisTbl[$this_tbl_column]  ;
		}

		$create_view_queries = array();
		foreach( $listOfViews as $this_view_name ){
			$res = $MYSQLCONN->exequery_return_single_row_as_AssocArray( " SHOW CREATE VIEW `{$this_view_name}` " );
			if( !$res || !count($res) ){
				continue;
			}
			$create_view_queries[] = $res['Create View'] ;
		}


		// I have views that depends on other views. Creation of the child views are failing when trying to 
		// create before the parent view. But i do not have time to figure out the order in which the views
		// have to be created. Until then this ugly hack should do. change $view_dependency_hierarchy_length to your requirement.
		
		for($v =0 ; $v < $view_dependency_hierarchy_length ; $v++){
			foreach( $create_view_queries as $this_view_create_string ){
				if($opf){
					file_put_contents( $opf , "\n {$this_view_create_string} ; " , FILE_APPEND );
				}else{
					echo "\nCreating view {$this_view_name}";
					$MYSQLCONN_DEST->exequery( "use {$dbname}" );
					$MYSQLCONN_DEST->exequery( $this_view_create_string );				
				}
			}
		}

		echo "\n*** Exporting Stored Procedures \n";
		// export all functions from source database
		$dump_exec_cmd = "mysqldump {$lock_string} --routines --triggers=false --no-create-info --no-data --no-create-db --skip-opt  -u {$su} -p{$sp} -h {$sh} {$dbname} " ;
		if( $opf ){
			exec( " {$dump_exec_cmd} >> {$opf}" );	
		}else{
			exec( " {$dump_exec_cmd} | {$pipe_destination} {$dbname} " );	
		}
	}

}

?>
