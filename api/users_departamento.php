<?php
class Users_departamento extends DbObject
{
	public function __construct()
	{
		$this->relations_tables = array('id_user'=>array('users'=>'id'),
										'id_departamento'=>array('departamentos'=>'id'));
		$this->relations_fields = array('users.nome'=>'nome_user', 'departamentos.nome'=>'nome_dep');
		parent::__construct(strtolower(__CLASS__));
	}
}
?>