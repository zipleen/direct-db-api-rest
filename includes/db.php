<?php
/*
 * Este ficheiro vai tratar de inicializar as coisas
 * 
 */

class db{

	/**
	 * 
	 * @var debug
	 */
	private $debug;
	private $do_debug = false;
	
	/**
	 * @var ADOConnection
	 */
	private $dblink;
	private $is_connected = false;
	private $query_counter = 0;
	private $database_data;
	
	/**
	 * singleton
	 *
	 * @return object core
	 */
	public static function getInstance ()
	// this implements the 'singleton' design pattern.
	{
		static $instance;
	
		if (!isSet($instance)) {
			$c = __CLASS__;
			$instance = new $c;
		} // if
		return $instance;
	} // getInstance
	
	/**
	 * Este metodo inicializa a sessao e guarda umas variaveis
	 */
	public function __construct()
	{
		global $database_config;
		require_once(dirname(__FILE__)."/include/adodb/adodb.inc.php");
		$this->debug = debug::getInstance();
		$this->do_debug = $this->debug->getDebug();
		$this->database_data = $database_config;
		$this->setGetMethodByName();
	}
	
	/**
	 * fazer a ligacao ah base de dados
	 *
	 * @access public
	 * @return bool
	 */
	public function Connect() 
	{
		
		if ($this->is_connected || (is_object($this->dblink) && $this->dblink->IsConnected())) {
			$this->debug->warning("Connect acedido e ligacao ja existe ");
			return true;
		} else {
			$this->dblink = NewADOConnection($this->database_data['type']);
			if($this->dblink->Connect($this->database_data['host'], $this->database_data['user'], $this->database_data['password'], $this->database_data['dbname'])) 
			{
				$this->is_connected = true;
				$this->debug->log("Got SQL Connection");
				return true;
			} 
			else 
			{
				throw new Exception("Connection to DB failure");
				return false;
			}
		}
	}
	/**
	 * fazer disconnect da base de dados
	 * (nao usar, o core trata disto)
	 *
	 * @return unknown_type
	 */
	public function Disconnect()
	{
		if($this->is_connected)
			$this->dblink->Disconnect();
	}
	
	/**
	 * Verifica se a ligacao ao sql esta activa
	 *
	 * @return bool
	 */
	public function is_connected () 
	{
		return $this->is_connected;
	}
	/**
	 * inicia uma transaccao
	 *
	 * @return unknown_type
	 */
	public function StartTrans () 
	{
		if(!$this->is_connected) $this->Connect();
		return $this->dblink->StartTrans();
	}
	/**
	 * shortcut para CompleteTrans(false) - termina uma transacao mas aborta-a
	 * @return unknown_type
	 */
	public function FailTrans()
	{
		return $this->CompleteTrans(false);
	}
	
	/**
	 * acabar uma transacao (fazer commit)
	 * para abortar uma transacao, passar false
	 *
	 * @param bool $ok - se true, fazer commit, se falze, abortar
	 * @return bool
	 */
	public function CompleteTrans ($ok=true) 
	{
		$test = $this->dblink->CompleteTrans($ok);
		if($test)
			return true;
		else 
		{
			$this->debug->error("CompleteTrans Failed! Error msg:".$this->dblink->ErrorMsg());
			return false;
		}
	}
	
	/**
	 * verificar se uma transacao em curso falhou
	 *
	 * @return bool
	 */
	public function HasFailedTrans () 
	{
		return $this->dblink->HasFailedTrans();
	}
	
	public function Affected_Rows()
	{
		if(!$this->is_connected) $this->Connect();
		return $this->dblink->Affected_Rows();
	}
	
	public function MetaPrimaryKeys($table, $owner=false)
	{
		if(!$this->is_connected) $this->Connect();
		return $this->dblink->MetaPrimaryKeys($table, $owner);
	}
	
	public function MetaColumns($table,$notcasesensitive=true)
	{
		if(!$this->is_connected) $this->Connect();
		return $this->dblink->MetaColumns($table, $notcasesensitive);
	}
	
	/**
	 * sql_query()
	 * Executa uma query sql. Caso falhe, regista o erro.
	 * Todos os metodos que necessitem de usar cache, meter o valor da cache a true.
	 * Nao sera executado o metodo com cache se a cache for forcada a off.
	 * Tambem acontece que se a cache for forcada a off, a cache serah flushed.
	 *
	 * @param 	string	$sql
	 * @return 	ADORecordSet class
	 */
	public function sql_query($sql) 
	{
		if(!$this->is_connected) $this->Connect();

		$this->query_counter++;
		if($this->do_debug == true)
			$start = utils::getTime();
		
		$result = $this->dblink->_Execute($sql);
		
		if (!$result)
		{
			$this->db_error($sql,$this->dblink->ErrorMsg(),"sql_query");
			return false;
		}
		
		if($this->do_debug == true && $result)
		{
			if($result->RecordCount()>1)
			{
				$data = array();
				while(!$result->EOF)
				{
					$data[] = $result->fields;
					$result->MoveNext();
				}
				$result->MoveFirst();
				$this->debug->sql_query("SQL_query(".$this->query_counter.") [".$result->RecordCount()."] ".utils::htmlentities($sql),$data,$start);
			}else
				$this->debug->sql_query("SQL_query(".$this->query_counter.") [".$result->RecordCount()."] ".utils::htmlentities($sql),$result->fields,$start);
		}
		
		
		return $result;
	}
	/**
	 * SQL_limitquery()
	 * Executa uma query limitada por um offset
	 *
	 * @param 	string	$sql
	 * @param 	int	$numrows
	 * @param	int $offset = -1
	 * @param	bool $log = true
	 * @param	bool $cache = false
	 * @param	int	$time = 15
	 * @return 	ADORecordSet class
	 */
	public function sql_limitquery($sql, $numrows, $offset=-1) 
	{
		if(!$this->is_connected) $this->Connect();
		
		$this->query_counter++;
		if($this->do_debug == true)
			$start = utils::getTime();
		
		$result = $this->dblink->SelectLimit($sql, $numrows, $offset);
		
		if (!$result)
		{
			$this->db_error($sql,$this->dblink->ErrorMsg(),"sql_query");
			return false;
		}
		
		if($this->do_debug == true)
		{
			if($result && $result->RecordCount()>1)
			{
				$data = array();
				while(!$result->EOF)
				{
					$data[] = $result->fields;
					$result->MoveNext();
				}
				$result->MoveFirst();
				$this->debug->sql_query("sql_limitquery(".$this->query_counter.") [".$result->RecordCount()."] numrows: $numrows offset $offset ".utils::htmlentities($sql),$data,$start);
			}else
				$this->debug->sql_query("sql_limitquery(".$this->query_counter.") numrows: $numrows offset $offset ".utils::htmlentities($sql),$result->fields,$start);
		}
		return $result;
	}
	/**
	 * Replace
	 *
	 * ex:
	 * $values['id'] = $id;
	 * $values['bla'] = $bla;
	 * $this->dblink->Replace('engine_pagetemplates', $values, array('id'), true);
	 *
	 * @param unknown_type $table
	 * @param unknown_type $arrFields
	 * @param unknown_type $keyCols
	 * @param unknown_type $autoQuote
	 * @return unknown
	 */
	public function Replace($table, $arrFields, $keyCols, $autoQuote=false) {
	
		if(!$this->is_connected) $this->Connect();
	
		$this->query_counter++;
		if($this->do_debug == true)
			$start = utils::getTime();

		$result = $this->dblink->Replace($table, $arrFields, $keyCols, $autoQuote);
		if ($result===false)
			$this->db_error("SQL ".__METHOD__." error<br>sql: $sql", $this->dblink->ErrorMsg(), __METHOD__);
		if($this->do_debug == true)
			$this->debug->sql_query("SQL Replace! (".$this->query_counter." / ".date("i:s:u").")", $arrFields, $start);
		return $result;
		
	}
	
	/**
	 * Autoexecute - insert/update
	 * -
	 * @param $table
	 * @param $arrFields
	 * @param $mode - 1:insert 2: update
	 * @param $where
	 * @param $forceUpdate
	 * @param $magicq
	 * @return unknown_type
	 */
	public function autoExecute($table, $arrFields, $mode, $where=false, $forceUpdate=true,$magicq=true){
	
		if($this->do_debug == true)
			$start = utils::getTime();

		$result =  $this->dblink->AutoExecute($table, $arrFields, $mode, $where, $forceUpdate, $magicq);
		if ($this->do_debug == true) 
		{
			if ($result===false) 
			{
				$this->debug->sql_query("Autoexecute $table ERROR: (".$this->dblink->ErrorMsg().")", false ,$start);
			} 
			else  
			{
				$this->debug->sql_query("Autoexecute  $table $where [".count($result)." record(s)]", $arrFields, $start);
			}
		}
		return $result;
	}
		
	public function qstr($text) 
	{
		if(!$this->is_connected)
			$this->Connect();
		return $this->dblink->qstr($text);
	}
	public function DBDate($date) 
	{
		return $this->dblink->DBDate($date);
	}
	
	/**
	 * Executes the SQL and returns the all the rows as a 2-dimensional array. The recordset is discarded for you automatically. If an error occurs, false is returned. GetArray is a synonym for GetAll.
	 *
	 * @param unknown_type $sql
	 * @param unknown_type $inputarr
	 * @return unknown_type
	 */
	public function GetAll($sql,$inputarr=false)
	{
		if(!$this->is_connected)
			$this->Connect();
	
		$this->query_counter++;
		if($this->do_debug == true)
			$start = utils::getTime();
	
		$result = $this->dblink->GetAll($sql,$inputarr);
	
		if ($result===false)
			$this->db_error("SQL ".__METHOD__." error<br>sql: $sql", $this->dblink->ErrorMsg(), __METHOD__);
		if($this->do_debug == true)
			$this->debug->sql_query(__METHOD__.": $sql (".$this->query_counter.")", $result, $start);
		return $result;
	}
	
	public function GetAllLimit($sql, $limit=-1, $offset=-1)
	{
		if(!$this->is_connected)
			$this->Connect();
		
		if(is_null($offset))
			$offset = -1;
		if(is_null($limit))
			$limit = -1;
		
		$this->query_counter++;
		if($this->do_debug == true)
			$start = utils::getTime();
		
		$res = $this->sql_limitquery($sql, $limit, $offset);
		
		if ($res===false)
		{
			$this->db_error($sql, $this->dblink->ErrorMsg(), __METHOD__);
			return false;
		}
		
		// vamos emular o getall
		$data = array();
		while(!$res->EOF)
		{
			$data[] = $res->fields;
			$res->MoveNext();
		}
		if($this->do_debug == true)
			$this->debug->sql_query(__METHOD__.": $sql (".$this->query_counter.")", $data, $start);
		return $data;
	}
	
	/**
	 * Executes the SQL and returns all elements of the first column as a 1-dimensional array. The recordset is discarded for you automatically. If an error occurs, false is returned.
	 *
	 * @param unknown_type $sql
	 * @param unknown_type $inputarr
	 * @return unknown_type
	 */
	public function GetCol($sql,$inputarr=false)
	{
		if(!$this->is_connected)
			$this->Connect();
	
		$this->query_counter++;
		if($this->do_debug == true)
			$start = utils::getTime();
	
		$result = $this->dblink->GetCol($sql,$inputarr);
	
		if ($result===false)
			$this->db_error("SQL ".__METHOD__." error<br>sql: $sql", $this->dblink->ErrorMsg(), __METHOD__);
		if($this->do_debug == true)
			$this->debug->sql_query(__METHOD__.": $sql (".$this->query_counter.")", $result, $start);
		return $result;
	}
	
	/**
	 * Executes the SQL and returns the first row as an array. The recordset and remaining rows are discarded for you automatically. If no records are returned, an empty array is returned. If an error occurs, false is returned.
	 *
	 * @param unknown_type $sql
	 * @param unknown_type $inputarr
	 * @return unknown_type
	 */
	public function GetRow($sql,$inputarr=false){
		if(!$this->is_connected)
			$this->Connect();
	
		$this->query_counter++;
		if($this->do_debug == true)
			$start = utils::getTime();
	
		$result = $this->dblink->GetRow($sql,$inputarr);
	
		if ($result===false)
			$this->db_error("SQL ".__METHOD__." error<br>sql: $sql", $this->dblink->ErrorMsg(), __METHOD__);
		if($this->do_debug == true)
			$this->debug->sql_query(__METHOD__.": $sql (".$this->query_counter.")", $result, $start);
		return $result;
	}
	
	/**
	 * Executes the SQL and returns the first field of the first row. The recordset and remaining rows are discarded for you automatically. If an error occur, false is returned; use ErrorNo() or ErrorMsg() to get the error details. Since 4.96/5.00, we return null if no records were found. And since 4.991/5.06, you can have change the return value if no records are found using the global variable $ADODB_GETONE_EOF: $ADODB_GETONE_EOF = false;
	 *
	 * @param unknown_type $sql
	 * @return unknown_type
	 */
	public function GetOne($sql)
	{
		if(!$this->is_connected)
			$this->Connect();
	
		$this->query_counter++;
		if($this->do_debug == true)
			$start = utils::getTime();
	
		$result = $this->dblink->GetOne($sql);
	
		if ($result===false)
			$this->db_error("SQL ".__METHOD__." error<br>sql: $sql", $this->dblink->ErrorMsg(), __METHOD__);
		if($this->do_debug == true)
			$this->debug->sql_query(__METHOD__.": $sql (".$this->query_counter.")", $result, $start);
		return $result;
	}
	
	/**
	 * Returns an associative array for the given query $sql with optional bind parameters in $inputarr. If the number of columns returned is greater to two, a 2-dimensional array is returned, with the first column of the recordset becomes the keys to the rest of the rows. If the columns is equal to two, a 1-dimensional array is created, where the the keys directly map to the values (unless $force_array is set to true, when an array is created for each value).
	 *
	 * @param unknown_type $sql
	 * @param unknown_type $inputarr
	 * @param unknown_type $force_array
	 * @param unknown_type $first2cols
	 * @return unknown_type
	 */
	public function GetAssoc($sql,$inputarr=false,$force_array=false,$first2cols=false)
	{
		if(!$this->is_connected)
			$this->Connect();
	
		$this->query_counter++;
		if($this->do_debug == true)
			$start = utils::getTime();
	
		$result = $this->dblink->GetAssoc($sql,$inputarr,$force_array,$first2cols);
	
		if ($result===false)
			$this->db_error("SQL ".__METHOD__." error<br>sql: $sql", $this->dblink->ErrorMsg(), __METHOD__);
		if($this->do_debug == true)
			$this->debug->sql_query(__METHOD__.": $sql (".$this->query_counter.")", $result, $start);
		return $result;
	
	}
	
	/**
	 * Generate a 2-dimensional array of records from the current cursor position, indexed from 0 to $number_of_rows - 1. If $number_of_rows is undefined, till EOF.
	 *
	 * @param ADORecordSet $rs
	 * @return array
	 */
	public function GetArray($rs)
	{
		return $this->dblink->GetArray($rs);
	}
	
	public function Insert_ID($table, $column=null)
	{
		return $this->dblink->Insert_ID($table,$column);
	}
	
	/**
	 * definir o metodo de obtencao de dados sql por APENAS nome
	 * @return void
	 */
	public function setGetMethodByName()
	{
		global $ADODB_FETCH_MODE;
		$ADODB_FETCH_MODE = ADODB_FETCH_ASSOC;
	}
	
	/**
	 * definir o metodo de obtencao de dados sql por nome e numero
	 *
	 * @return void
	 */
	public function setGetMethodByBoth()
	{
		global $ADODB_FETCH_MODE;
		$ADODB_FETCH_MODE = ADODB_FETCH_BOTH;
	}
	
	/**
	 * Devolve erro ocorrido da base de dados
	 *
	 * @return void
	 */
	public function ErrorMsg()
	{
		return $this->dblink->ErrorMsg();
	}
	
	/**
	 * db_error()
	 * Error log written to db
	 *
	 * @access 	private
	 * @param 	string $sql
	 * @param  	string $sqlerror
	 * @param  	string $funcname
	 */
	public function db_error($sql1='', $sqlerror, $funcname='') 
	{
		if($sql1!=""){
			$sql1 = str_replace(array("\n","\r","\t")," ",$sql1);
		}
		
		$data = array(
				"ip"=>$_SERVER['REMOTE_ADDR'],
				"browser"=>$_SERVER['HTTP_USER_AGENT'],
				"datahora"=>date("Y-m-d H:i:s"),
				"funcname"=>$funcname."-".$sql1,
				"errormsg"=>$sqlerror,
				"server_var"=>print_r($_SERVER, true),
				"post_var"=>print_r($_POST, true),
				"get_var"=>print_r($_GET, true)
			);
		
		$this->dblink->Replace("logdb", $data, array('id'), true);
		$this->debug->error("[DB ERROR] SQL ERROR: $sqlerror | sql: $sql1");
	
	}
}
?>
