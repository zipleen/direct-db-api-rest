<?php
class Planes_sets_products extends DbObject
{
	public function __construct()
	{
		$this->relations_tables = array('id_plane_set'=>array('plane_sets'=>'id'),
										'id_produto'=>array('products'=>'id'));
		$this->relations_fields = array('plane_sets.name'=>'nome_plane',
										'products.code'=>'code_product');
		parent::__construct(strtolower(__CLASS__));
	}
}
?>