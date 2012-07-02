<?php
class Encomendas extends DbObject
{
	public function __construct()
	{
		parent::__construct(strtolower(__CLASS__));
	}
	
	public function get($id=NULL, $request_data=NULL, $limit=NULL, $start=NULL, $sort=NULL, $dir=NULL, $filter=NULL )
	{
		$data = parent::get($id, $request_data, $limit, $start, $sort, $dir, $filter);
		if(is_array($data))
		{
			foreach($data[$this->root_data] as $row=>$d)
			{
				$data[$this->root_data][$row]['lines'] = $this->db->GetAll("SELECT * FROM encomendas_linhas WHERE id_cod=".$this->db->qstr($d['id']));
			}
			
			/*
			 * hack was not needed!!
			// hack para despachar esta coisa
			foreach($data[$this->root_data] as $row=>$d)
			{
 				$data[$this->root_data][$row]['lines_html'] = "<table><th> <td>id_linha</td><td>id prod</td><td>code</td><td>klm code</td><td>qtd pick up</td><td>qtd to deliver</td><td>rfid</td><td>product type</td> </th>";
				foreach($d['lines'] as $row1=>$line)
				{
					$data[$this->root_data][$row]['lines_html'] .= "<td>".$line['id_linha']."</td>"."<td>".$line['id_prod']."</td>"."<td>".$line['code']."</td>"."<td>".$line['cod_klm']."</td>".
							"<td>".$line['qtd_recolha']."</td>"."<td>".$line['qtd_entrega']."</td>"."<td>".$line['rfid']."</td>"."<td>".$line['nome_tipo_prod']."</td>" ;
									
				}
				$data[$this->root_data][$row]['lines_html'] .= "</table>";
			}
			*/
		}
		
		
		return $data;
	}
}
?>