<?php
class Reports 
{
	/**
	 *
	 * @var db
	 */
	private $db;
	
	/**
	 *
	 * @var debug
	 */
	private $debug;
	
	public function __construct()
	{
		$this->db = db::getInstance();
		$this->debug = debug::getInstance();
	}
	
	public function getEncomendas($request_data=NULL)
	{
		$this->debug->logArray(__METHOD__."() ", $request_data);
		
		$where = "WHERE encomendas.old_system='0'";
		if(isSet($request_data['old_system']) && $request_data['old_system']=="1")
			$where = "WHERE 1=1";
		
		if(isSet($request_data['data_de']))
		{
			$d = explode("-", $request_data['data_de']);
			if( checkdate($d[1], $d[2], $d[0])==true )
			{
				$where .= " AND encomendas.`data` >= STR_TO_DATE(".$this->db->qstr($d[0]."-".$d[1]."-".$d[2]).",'%Y-%m-%d') ";
			}else $this->debug->error(__METHOD__."() data de nao valida: ".$request_data['data_de']);
		}

		if(isSet($request_data['data_ate']))
		{
			$d = explode("-", $request_data['data_ate']);
			if( checkdate($d[1], $d[2], $d[0])==true )
			{
				$where .= " AND encomendas.`data` <= STR_TO_DATE(".$this->db->qstr($d[0]."-".$d[1]."-".$d[2]).",'%Y-%m-%d') ";
			}else $this->debug->error(__METHOD__."() data ate nao valida: ".$request_data['data_ate']);
		}

		$sql = "SELECT substring(encomendas.`data`,1,10) as data, encomendas.nome_departamento, e.`code`, e.cod_klm, e.qtd_entrega, e.qtd_recolha, e.rfid, e.nome_tipo_prod
FROM encomendas INNER JOIN encomendas_linhas as e ON encomendas.id=e.id_cod 
$where
ORDER by e.id_cod, e.cod_klm";
		
		$data = $this->db->GetAll($sql);
		
		return $data;
	}
	
	public function getStockPerDep()
	{
		// sacar todos os deps
		$deps = new Departamentos();
		$dep_data = $deps->get(null, array('use_planes'=>'0'));
		//$this->debug->logArray("dep_data", $dep_data);
		
		$prod = new Products();
		$prod_data = $prod->get(null, array('deleted'=>'0'));
		//$this->debug->logArray("prod_data", $prod_data);
		
		$stock_dep_prod = new Dep_stock();
		
		
		$data = array();
		foreach($prod_data[$prod->getMetaRootData()] as $pdata)
		{
			//$data[$pdata['cod_klm']] = array();
			foreach($dep_data[$deps->getMetaRootData()] as $ddata)
			{
				// para ser melhor, o 'id' deveria ir buscar a PK do objecto, e o id_prod e o id_dep deveriam ser do PK do outro objecto - nao sei eh bem como relacionar que o id_prod era em relacao ao objecto produtos...
				$temp = $stock_dep_prod->get(null, array('id_prod'=>$pdata['id'],'id_dep'=>$ddata['id']));
				if(isSet($temp[ $stock_dep_prod->getMetaRootData() ][0]['qtd']))
					$data[] = array("Product"=>$pdata['cod_klm'], "Department"=>$ddata['nome'], "Qtd"=>$temp[ $stock_dep_prod->getMetaRootData() ][0]['qtd']);
				else
					$data[] = array("Product"=>$pdata['cod_klm'], "Department"=>$ddata['nome'], "Qtd"=>"-1");
			}
		}
		return $data;
	}
}

?>
