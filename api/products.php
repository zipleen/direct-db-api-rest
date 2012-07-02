<?php
class Products extends DbObject
{
	public function __construct()
	{
		$this->relations_tables = array('id_tipo_product'=>array('tipo_product'=>'id'));
		$this->relations_fields = array('tipo_product.name'=>'nome_tipo_prod');
		$this->additional_get_fields_name = "md_ax";
		$this->additional_get_fields = array('cod_klm','code');
		parent::__construct(strtolower(__CLASS__));
	}
}
?>
