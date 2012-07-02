<?php

abstract class DbObject
{
	/**
	 *
	 * @var db
	 */
	protected $db;
	protected $table_name;
	private $fields = array();
	private $pkey;

	/**
	 *
	 * relation tables and relation fields specifies fields that need to be added to the sql query
	 * so they appear to the get response. for example, you want to show the department name that matches the 
	 * department_id from a table, and the name is in another table. You can then use:
	 *  // this associates id_user key from my table to the id of users table. Also associates id_departamento key
	 *  //  FK to the id PK from departamentos table
	 *  $this->relations_tables = array('id_user'=>array('users'=>'id'),
	 *									'id_department'=>array('department'=>'id'));
	 *  // I then want to see a users.name and a department name
	 *	$this->relations_fields = array('users.name'=>'nome_user', 'department.nome'=>'nome_dep');
	 */
	protected $relations_tables = null;
	protected $relations_fields = null;
	
	// this is the char that will be uses to concatenate fields
	protected $joinpkeys_str = "-";
	protected $root_data = "records";
	
	/*
	 * you may want to add 2 fields in 1 column - this is usefull in ext because making multiple PK in a ext grid was 
	 * becoming a problem that I wanted to solve quickly. This only works for ONE field, and it's used like
	 *  $this->additional_get_fields_name = "md_ax";
	 *	$this->additional_get_fields = array('cod_klm','code');
	 */
	protected $additional_get_fields = null;
	protected $additional_get_fields_name = "";

	/**
	 *
	 * @var debug
	 */
	protected $debug;

	/**
	 * Needs table_name!!!
	 * 
	 * @param strings $table_name
	 */
	public function __construct($table_name)
	{
		$this->db = db::getInstance();
		$this->debug = debug::getInstance();
		
		$this->table_name = $table_name;
		
		// initialize fields
		$this->fields = $this->getTableFields();

		// initialize PK
		$pk = $this->getPK();
		if(count($pk)==1)
		{
			$this->pkey = array_pop($pk);
			$this->debug->log(__METHOD__."($this->table_name) $this->pkey = ".$this->pkey);
		}
		else
		{
			$this->pkey = array_values($pk);
			$this->debug->logArray(__METHOD__."($this->table_name) this->pkey", $this->pkey);
		}

		$this->debug->logArray(__METHOD__."($this->table_name) this->fields for ".$this->table_name, $this->fields);
	}
	
	public function getMetaRootData()
	{
		return $this->root_data;
	}
	
	/**
	 * get primary keys, this comes from adodb! usefull =)
	 */
	public function getPK()
	{
		return $this->db->MetaPrimaryKeys($this->table_name);
	}
	
	/**
	 * also from adodb, get table fields in a structured way. this has a lot of usefull information
	 * like pkeys, default values, field type 
	 */
	public function getTableFields()
	{
		return $this->db->MetaColumns($this->table_name, true);
	}

	/**
	 * json answer, only used in get!
	 * 
	 * @param unknown_type $success
	 * @param unknown_type $msg
	 */
	private function _response($success, $msg)
	{
		return array(
				'success' => $success,
				'message' => $msg
		);
	}
	
	/**
	 * given a field type, return if it's a string match or a number match, to be used in WHERE condition 
	 * 
	 * 
	 * @param unknown_type $field
	 * @param unknown_type $val
	 * @param unknown_type $type
	 */
	private function _sqlcompare($field, $val, $type=NULL)
	{
		switch($type)
		{
			case "varchar":
			case "string":
				return $this->table_name . "." . $this->fields[strtoupper($field)]->name . " LIKE " . $this->db->qstr("%".$val."%");
				break;
		
			default:
				return $this->table_name . "." . $this->fields[strtoupper($field)]->name . " = " . $this->db->qstr($val);
		
			break;
		}
	}

	/**
	 * unfortunaly, the concat values are myself specific! but it's possible to modify this to be able to use this in 
	 * postgresql as well as other db types!
	 */
	private function _getConcatValues($field_as, $columns)
	{
		if(is_array($columns))
		{
			$str = "";
			foreach($columns as $c)
			{
				$str .= ",".$c;
			}
			return "CONCAT_WS('".$this->joinpkeys_str."'".$str.") as $field_as,";
		}
		else
		{
			return "";
		}
	}
	
	/**
	 * again, only for mysql. i could have made a generic function on how to concatenate values based on db type =)
	 */
	private function _generatePKvalueFromMulti()
	{
		if(is_array($this->pkey))
		{
			$str = "";
			foreach($this->pkey as $ids)
			{
				$str .= ",".$ids;
			}
			return "CONCAT_WS('".$this->joinpkeys_str."'".$str.") as id,";
			
		}
		else 
		{
			return "";
		}
	}
	
	private function _getPKarrayFromMulti($id)
	{
		$array = array();
		if(is_array($this->pkey))
		{
			$i = 0;
			$ids = explode($this->joinpkeys_str, $id);
			foreach($this->pkey as $id1)
			{
				$array[$id1] = $ids[$i++];
			}
			return $array;
		}
		else
		{
			$array[$this->pkey] = $id;
			return $array;
		}
	}
	
	private function _getPKvaluesFromMulti($id)
	{
		if(is_array($this->pkey))
		{
			$where = "";
			$i = 0;
			$ids = explode($this->joinpkeys_str, $id);
			foreach($this->pkey as $id1)
			{
				$where .= " ".$id1."=".$this->db->qstr($ids[$i++])." AND ";
			}
			return substr($where, 0, -4);
		}
		else
			return $this->pkey."=".$this->db->qstr($id);
	}
	
	/**
	 * select - read
	 *
	 * @param unknown_type $id
	 */
	public function get($id=NULL, $request_data=NULL, $limit=NULL, $start=NULL, $sort=NULL, $dir=NULL, $filter=NULL )
	{
		//$this->debug->logArray(__METHOD__."($this->table_name) id: $id limit: $limit start: $start", $request_data);
				
		// sorting and ordering
		$order_sql = "";
		if(!is_null($sort))
		{
			$inverted_rels = array_flip($this->relations_fields);
			if(isSet($sort[0]) && $sort[0]=="[")
			{
				// json crap
				$sorting = json_decode($sort);
				//$this->debug->logArray("sort", $sorting);
				foreach($sorting as $s)
				{
					// we can have more than one filter!!!
					// verify if the field really exists
					if(isSet($this->fields[strtoupper($s->property)]) || isSet($inverted_rels[$s->property]))
					{
						$order_sql .= ",". $s->property;
						switch($s->direction)
						{
							case "ASC":
							case "asc":
								$order_sql .= " ASC";
								break;
							case "DESC":
							case "desc":
								$order_sql .= " DESC";
								break;
						}
					}
				}
				if(isSet($order_sql[1]))
					$order_sql = " ORDER BY ".substr($order_sql, 1);
			}
			else
			{
				// verify if the property really exists
				if(isSet($this->fields[strtoupper($sort)]))
				{
					$order_sql = " ORDER BY ".$this->table_name . "." . $this->fields[strtoupper($sort)]->name;
					
					if(!is_null($dir))
					{
						switch($dir)
						{
							case "ASC":
							case "asc":
								$order_sql .= " ASC";
								break;
							case "DESC":
							case "desc":
								$order_sql .= " DESC";
								break;
						}
					}
				}
				elseif(isSet($inverted_rels[$sort]))
				{
					$order_sql = " ORDER BY ". $sort;
					
					if(!is_null($dir))
					{
						switch($dir)
						{
							case "ASC":
							case "asc":
								$order_sql .= " ASC";
								break;
							case "DESC":
							case "desc":
								$order_sql .= " DESC";
								break;
						}
					}
				}
			}
			$this->debug->log(__METHOD__."() Order sql: ".$order_sql);
		}
		
		// make the filter!
		$filter_sql = "";
		if(!is_null($filter))
		{
			if(is_string($filter))
			{
				$filter = json_decode($filter);
				$this->debug->logArray(__METHOD__."($this->table_name) FILTER fields", $filter);
				foreach($filter as $f)
				{
					// we can have more than one filter, verify if it really exists
					if(isSet($this->fields[strtoupper($f->field)]))
					{
						// multiple values
						if(is_array($f->value))
						{
							foreach($f->value as $val)
							{
								$filter_sql .= $this->_sqlcompare($this->fields[strtoupper($f->field)]->name, $val, $f->type) . " OR ";
							}
							$filter_sql = " AND ".substr($filter_sql, 0, -4);
						}
						else
						{
							// for 1 value only
							$filter_sql = " AND ".$this->_sqlcompare($this->fields[strtoupper($f->field)]->name, $f->value, $f->type);
							
						}
					}
				}
			}else throw RestException(417, "Filter parameter is not a json object");
			
		}
		
		// this contrls the relation fields - objects that need data from another data table
		// this exists to overcome a problem on concurrency, related to ext js not showing up the related values in a correct way
		// this should never be here btw (i don't remember why lol)
		$table = $this->table_name;
		$where = "";
		$select_fields = "";
		// if there's a relation fields, we need to build it
		if(!is_null($this->relations_fields) && !is_null($this->relations_tables))
		{
			// let's do some inner joins...
			foreach($this->relations_tables as $field_this_table=>$rel_data)
			{
				// $this->relations_tables['id_produto'] = array('produtos'=>'id'); 
				list($table_rel, $rel_field_pk) = each($rel_data);
				$table = " $table INNER JOIN $table_rel ON $table_rel.$rel_field_pk=$this->table_name.$field_this_table ";
			}
			
			// the fields
			foreach($this->relations_fields as $f=>$a)
			{
				$select_fields .= ",$f AS $a";
			}
			$this->debug->log(__METHOD__."($this->table_name) RELATION FIELDS: adicionei novas tabelas ($table) e novos fields ($select_fields)");
		}
		
		if( !is_null($id) )
		{
			// id can be a multiple PK value! we need to "decode it"
			$iid = $this->_getPKvaluesFromMulti($id);
			// only 1 number, 1 record can only have 1 record always
			return $this->db->GetAllLimit("SELECT ".$this->_generatePKvalueFromMulti().$this->table_name.".* FROM ".$this->table_name." WHERE $iid", $limit, $start);
		}
		elseif(is_array($request_data))
		{
			$pkey = $this->_validate($request_data);
			//$this->debug->logArray(__METHOD__, $pkey);
			
			$where = $this->_joinAnd($pkey);
			if($where!="")
				$where = " WHERE ".$where.$filter_sql;
			elseif($filter_sql!="")
				$where = " WHERE ".substr($filter_sql, 4);
			
			// additional fields!
			$add_fields = "";
			if(!is_null($this->additional_get_fields) && count($this->additional_get_fields))
			{
				$add_fields = $this->_getConcatValues($this->additional_get_fields_name, $this->additional_get_fields);
			}

			$data = $this->db->GetAllLimit("SELECT ".$this->_generatePKvalueFromMulti().$add_fields.$this->table_name.".*$select_fields FROM ".$table.$where.$order_sql, $limit, $start);
		}
		else
			$data = $this->db->GetAll("SELECT ".$this->_generatePKvalueFromMulti().$add_fields.$this->table_name.".*$select_fields FROM ".$table.$order_sql);
		
		// if there's limits we need to do paging
		// i think i removed this because the answers were not being consistent, if only 1 record the array was given
		// and if more than 1 record, a message was given - this was not good, so i removed it
		//if(is_null($limit) && is_null($start))
		//	return $data;
		//else
		//{
			$count = $this->db->GetOne("SELECT COUNT(*) as c FROM ".$this->table_name.$where);
			$response_array = array(
					'total' => $count,
					'success' => true,
					$this->root_data => $data
					);
			return $response_array;
		//}
	}

	/**
	 * update
	 */
	public function put($id=NULL, $request_data=NULL)
	{
		if(!is_null($id))
		{
			$iid = $this->_getPKarrayFromMulti($id);
		}
		else
			throw new RestException(410, "need id to update a record");
		
		// validate dados
		$data = $this->_validate($request_data);

		// validate PK
		$pk = $this->_validatePK($iid);
		$sql_pk = $this->_joinAnd($pk);

		if(!$this->db->autoExecute($this->table_name, $data, 2, $sql_pk))
			throw new RestException(410, "db error inserting record - ".$this->db->ErrorMsg());

		return $this->get(NULL, $data);
	}

	/**
	 * insert - create
	 */
	public function post($id=NULL, $request_data=NULL)
	{
		if(!is_null($id))
		{
			$iid = $this->_getPKarrayFromMulti($id);
			$request_data = array_merge($iid, $request_data);
		}
		
		// validate field types!
		$data = $this->_validate($request_data);
		// validates if insert is possible ! - maybe because a field doesn't exist or it's an incorrect type
		$this->_validateInsert($data);
		
		$this->debug->logArray(__METHOD__."($this->table_name) inserting data", $data);
		
		if(!$this->db->Replace($this->table_name, $data, $this->pkey, true))
			throw new RestException(417, "db error inserting record - ".$this->db->ErrorMsg());
		
		if(!is_array($this->pkey))
			$pk = array( $this->pkey => $this->db->Insert_ID($this->table_name) );
		else 
		{
			// if the pkey is an array, it means that it will never have an insert_id, but it will be a concatenation of ids.
			// we need to get this =)
			$pk = $this->_validatePK($data);
		}
		
		$this->debug->logArray(__METHOD__."($this->table_name) vou retornar o novo registo..", $pk);
		return $this->get( NULL, $pk );
	}

	/**
	 * delete
	 */
	public function delete($id=NULL, $request_data=NULL)
	{
		if(!is_null($id))
		{
			$request_data = array_merge($this->_getPKarrayFromMulti($id), $request_data);
		}
		$this->debug->logArray("to delete, pkey: ",$request_data);
		$pk = $this->_validatePK($request_data);
		$sql_pk = $this->_joinAnd($pk);

		if($sql_pk=="")
			throw new RestException(417, "not going to delete ALL the table data.. pkeys not valid!");
		
		$sql = "DELETE from $this->table_name WHERE $sql_pk";
		$this->debug->log(__METHOD__."($this->table_name) sql: $sql");
		$this->db->sql_query($sql);

		if($this->db->Affected_Rows()>0)
			return $this->_response(true, "deleted record");
		else
			return $this->_response(false, "deleted record");;
	}

	/**
	 * joins fields=value to be added on WHERE sql clause.
	 * needs data like array('campo'=>'valor')
	 * 
	 * @param array $array
	 * @return string
	 */
	private function _joinAnd($array)
	{
		//$this->debug->logArray(__METHOD__."($this->table_name) ", $array);
		$sql = "";
		if(count($array)<=0)
			return "";

		foreach($array as $id=>$od)
		{
			$sql .= " ". $this->table_name . ".$id=".$this->db->qstr($od)." AND";
		}
		return substr($sql, 0, -4);
	}

	/**
	 * verify if received fields are valid for insertion, removes nulls from primary keys
	 * 
	 * @param array $data_recv
	 * @throws RestException
	 */
	private function _validateInsert($data_recv)
	{
		$this->debug->logArray(__METHOD__."($this->table_name) data", $data_recv);

		// vamos verificar se ha campos q nao podem estar vazios
		foreach($this->fields as $field=>$data)
		{
			if($data->not_null==true && $data->auto_increment==false)
			{
				// se o campo tiver um default value, nao precisamos de valores nele
				if($data->has_default==false)
				{
					// se este campo nao pode ser null e nao existir no vector, erro
					if( !isSet($data_recv[$data->name]) )
						throw new RestException(417, "field ".$data->name." needs a value, none given");
				}
			}
			
			// if the value received is NULL, but it's a primary key with an auto increment, then we'll unset this
			// so we can get an autoincremented value - guess mysql does not like received NULL for inserted autoincrement data
			if(isSet($data_recv[$data->name]) && is_null($data_recv[$data->name]) && $data->not_null==true && $data->auto_increment==true && $data->primary_key==true)
				unset($data_recv[$data->name]);
		}
		return true;
	}

	/**
	 * returns an array with primary keys, already verified (this validates pkeys)
	 *
	 * @param unknown_type $data_recv
	 * @throws RestException
	 */
	private function _validatePK($data_recv)
	{
		$pkeys = array();
		// vamos verificar agora pelas primary keys!
		foreach($this->fields as $field=>$data)
		{
			if($data->primary_key==true)
			{
				if( !isSet($data_recv[$data->name]) )
					throw new RestException(417, "primary key field ".$data->name." needs a value, none given");
				else
					$pkeys[$data->name] = $data_recv[$data->name];
			}
		}
		return $pkeys;
	}

	/**
	 * validates all fields in the table
	 * 
	 * @param array $data_recv
	 * @throws RestException
	 * @return array
	 */
	private function _validate($data_recv)
	{
		$data_send=array();
		foreach ($data_recv as $field=>$val)
		{
			$field_upper = strtoupper($field);
			// validation from field type
			if( isSet($this->fields[$field_upper]) )
			{
				// fix for when things are null, if it's null let's just skip it
				if(is_null($val))
					continue;
				
				// there's a field, lets examin it!
				switch($this->fields[$field_upper]->type)
				{
					case "bigint":
					case "int":
					case "decimal":
						// if we received nothing and this can be null, let it be
						if($val==="" && $this->fields[$field_upper]->not_null==false)
							continue;

						// number, verify if it's a number
						if(!is_numeric($val))
							throw new RestException(417, "$field hasn't got a numeric value");

						// lets check size
						if(strlen($val)>$this->fields[$field_upper]->max_length)
							throw new RestException(417, "$field is a numeric value, but is to big for ".$this->fields[$field_upper]->max_length);

						// add it!
						$data_send[ $this->fields[$field_upper]->name ] = $val;
						break;
						
					case "tinyint":
					case "bit":
						// tinyints i use them for boolean types
						if($val=="1" || $val===true)
						{
							$data_send[ $this->fields[$field_upper]->name ] = 1;
						}
						else
						{
							$data_send[ $this->fields[$field_upper]->name ] = 0;
						}
						break;

					case "varchar":
						if(strlen($val)>$this->fields[$field_upper]->max_length)
							throw new RestException(417, "$field is a string value, but is to big for ".$this->fields[$field_upper]->max_length);

						// add it!
						$data_send[ $this->fields[$field_upper]->name ] = $val;
						break;

					case "text":
						$data_send[ $this->fields[$field_upper]->name ] = $val;
						break;

					default:
						throw new RestException(417, "what kind of field is this that I dont know about?! -> ".$this->fields[$field_upper]->type);
					break;
				}

			}// else no field, lets ignore it
		}

		return $data_send;
	}
}
?>
