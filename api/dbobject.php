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

	protected $relations_tables = null;
	protected $relations_fields = null;
	
	protected $joinpkeys_str = "-";
	protected $root_data = "records";
	
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
		
		// inicializar os campos
		$this->fields = $this->getTableFields();

		// inicializar a pk
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
	
	public function getPK()
	{
		return $this->db->MetaPrimaryKeys($this->table_name);
	}

	public function getTableFields()
	{
		return $this->db->MetaColumns($this->table_name, true);
	}

	/**
	 * resposta em "tipo json" - so usado no get
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
	 * dado um tipo de campo, retorna se fica LIKE ou = , para usar no WHERE
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
					// podemos ter mais que um filtro!!!
					// verificar se o campo existe mesmo
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
				// verificar se a property eh um campo valido
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
		
		// fazer o filtro!
		$filter_sql = "";
		if(!is_null($filter))
		{
			if(is_string($filter))
			{
				$filter = json_decode($filter);
				$this->debug->logArray(__METHOD__."($this->table_name) FILTER fields", $filter);
				foreach($filter as $f)
				{
					// podemos ter mais que um filtro!!!
					// verificar se o campo existe mesmo
					if(isSet($this->fields[strtoupper($f->field)]))
					{
						// multiplos valores
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
							// um valor
							$filter_sql = " AND ".$this->_sqlcompare($this->fields[strtoupper($f->field)]->name, $f->value, $f->type);
							
						}
					}
				}
			}else throw RestException(417, "Filter parameter is not a json object");
			
		}
		
		// controla os relation fields - objectos que necessitem de dados de outros objectos
		// isto esta aqui para superar um problema no renderer do grid, que por causa da concorrencia nao mostra bem os valores "related"
		// isto nunca deveria estar aqui ja agora!
		$table = $this->table_name;
		$where = "";
		$select_fields = "";
		// se houver uma ligacoes no sql...
		if(!is_null($this->relations_fields) && !is_null($this->relations_tables))
		{
			// vamos ter de fazer uns inner joins..
			foreach($this->relations_tables as $field_this_table=>$rel_data)
			{
				// $this->relations_tables['id_produto'] = array('produtos'=>'id'); 
				list($table_rel, $rel_field_pk) = each($rel_data);
				$table = " $table INNER JOIN $table_rel ON $table_rel.$rel_field_pk=$this->table_name.$field_this_table ";
			}
			
			// os fields
			foreach($this->relations_fields as $f=>$a)
			{
				$select_fields .= ",$f AS $a";
			}
			$this->debug->log(__METHOD__."($this->table_name) RELATION FIELDS: adicionei novas tabelas ($table) e novos fields ($select_fields)");
		}
		
		if( !is_null($id) )
		{
			// o id pode ser mais que uma pkey!
			$iid = $this->_getPKvaluesFromMulti($id);
			// da 1 numero - 1 registo tem apenas 1 registo sempre
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
		
		// se houver limites, temos de fazer paginacao! - isto eh so para varios registos
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
		
		// validar dados
		$data = $this->_validate($request_data);

		// validar PK
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
		
		// valida os tipos de campos
		$data = $this->_validate($request_data);
		// valida se o insert nao vai falhar por algum erro parvo de falta de campos
		$this->_validateInsert($data);
		
		$this->debug->logArray(__METHOD__."($this->table_name) inserting data", $data);
		
		if(!$this->db->Replace($this->table_name, $data, $this->pkey, true))
			throw new RestException(417, "db error inserting record - ".$this->db->ErrorMsg());
		
		if(!is_array($this->pkey))
			$pk = array( $this->pkey => $this->db->Insert_ID($this->table_name) );
		else 
		{
			// se o pkey eh uma array, significa que isto nunca tera insert_id, tem de ser um conjunto de ids...
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
	 * junta campos=valor para ser adicionado ao WHERE do sql
	 * requer dados no tipo array('campo'=>'valor')
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
	 * verifica se os campos recebidos no vetor sao validos para insercao, retirando null's de primary keys
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
			
			// se o valor recebido existe, EH NULO mas eh uma primary key que contem um auto increment, entao vamos fazer unset pq o mysql nao gosta de receber esse nulo...
			if(isSet($data_recv[$data->name]) && is_null($data_recv[$data->name]) && $data->not_null==true && $data->auto_increment==true && $data->primary_key==true)
				unset($data_recv[$data->name]);
		}
		return true;
	}

	/**
	 * retorna um array com as primary keys ja verificadas - verifica por falta de alguma primary key!
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
	 * valida todos os campos do array, em conformidade com a tabela actual, pelo tipo de campo
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
			// validation a partir do tipo de field!
			if( isSet($this->fields[$field_upper]) )
			{
				// fix para quando ha coisas null, se houver coisas null vamos eh ignorar o campo!
				if(is_null($val))
					continue;
				
				// campo existe! vamos fazer uma verificacao para o tipo de campo!
				switch($this->fields[$field_upper]->type)
				{
					case "bigint":
					case "int":
					case "decimal":
						// se recebemos nada e isto pode ser null, vamos ignorar o valor
						if($val==="" && $this->fields[$field_upper]->not_null==false)
							continue;

						// numero, verificar se eh um numero
						if(!is_numeric($val))
							throw new RestException(417, "$field hasn't got a numeric value");

						// numero ta ok! vamos ver tamanho!
						if(strlen($val)>$this->fields[$field_upper]->max_length)
							throw new RestException(417, "$field is a numeric value, but is to big for ".$this->fields[$field_upper]->max_length);

						// adicionar ao vector
						$data_send[ $this->fields[$field_upper]->name ] = $val;
						break;
						
					case "tinyint":
					case "bit":
						// os tinyint vamos usar para boolean types
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

						// adicionar ao vector
						$data_send[ $this->fields[$field_upper]->name ] = $val;
						break;

					case "text":
						$data_send[ $this->fields[$field_upper]->name ] = $val;
						break;

					default:
						throw new RestException(417, "what kind of field is this that I dont know about?! -> ".$this->fields[$field_upper]->type);
					break;
				}

			}// else nao ha campo, vamos ignorar!
		}

		return $data_send;
	}
}
?>
