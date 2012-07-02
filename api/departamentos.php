<?php
class Departamentos extends DbObject
{
	public function __construct()
	{
		$this->relations_tables = array('id_morada'=>array('moradas'=>'id'));
		$this->relations_fields = array('moradas.name'=>'nome_morada');
		parent::__construct(strtolower(__CLASS__));
	}
}
?>