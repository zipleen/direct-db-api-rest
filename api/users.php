<?php
class Users extends DbObject
{
	public function __construct()
	{
		parent::__construct(strtolower(__CLASS__));
	}
}
?>