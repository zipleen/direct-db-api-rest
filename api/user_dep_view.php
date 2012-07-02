<?php
class User_dep_view extends DbObject
{
	public function __construct()
	{
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
			$this->debug->log(__METHOD__."() going users ..".$node);
			if(is_int($node))
			{
				$prod = $this->get(NULL, array('id_dep'=>$node), NULL, NULL, 'nome_user');
				foreach($prod['records'] as $id=>$data)
				{
					$nodes[] = array(
							'text'=>$data['nome_user'] . " (". $data['username'].")",
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
