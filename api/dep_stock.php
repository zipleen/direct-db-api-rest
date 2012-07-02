<?php
class Dep_stock extends DbObject
{
	public function __construct()
	{
		$this->relations_tables = array('id_dep'=>array('departamentos'=>'id'),
				'id_prod'=>array('products'=>'id'));
		$this->relations_fields = array('departamentos.nome'=>'nome_dep',
				'products.cod_klm'=>'code_product');
		parent::__construct(strtolower(__CLASS__));
	}
	
	public function getTree($node=NULL)
	{
		$nodes = array();
		if(is_null($node) || $node=="root")
		{
			$this->debug->log(__METHOD__."() going departamentos..");
			$deps = $this->get(NULL, NULL, NULL, NULL, "nome_dep");
			$this->debug->logArray("!!!",$deps);
			foreach($deps['records'] as $id=>$data)
			{
				if(!array_search($data['nome_dep'], $nodes))
				{
					$nodes[] = array(
							'text'=>$data['nome_dep'],
							'id'=>$data['id_dep'],
							'cls'=>'folder'
					);
				}//else $this->debug->logArray("ja encontrei ".$data['nome_dep'], $nodes);
			}
			
		}
		else
		{
			$node = (int)$node;
			$this->debug->log(__METHOD__."() going produtos ..".$node);
			if(is_int($node))
			{
				$prod = $this->get(NULL, array('id_dep'=>$node), NULL, NULL, 'code_product');
				foreach($prod['records'] as $id=>$data)
				{
					$nodes[] = array(
							'text'=>$data['ax_md']." : ".$data['qtd'],
							'id'=>$data['id'],
							'cls'=>'file',
							'leaf'=>'true',
							'checked'=>false
							);
				}
			}
		}
		
		return $nodes;
	}
}
?>
