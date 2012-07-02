<?php
#add restler to include path
set_include_path(get_include_path() . PATH_SEPARATOR . '../includes/restler');
set_time_limit(200);

#set autoloader
#do not use spl_autoload_register with out parameter
#it will disable the autoloading of formats
spl_autoload_register('spl_autoload');
error_reporting(E_ALL);

// inicializar db!
require_once(dirname(__FILE__)."/../cnf.php");
require_once(dirname(__FILE__)."/../includes/utils.class.php");
require_once(dirname(__FILE__)."/../includes/db.php");
require_once(dirname(__FILE__)."/../includes/debug.php");
require_once(dirname(__FILE__)."/../includes/adodb/adodb-errorhandler.inc.php");
date_default_timezone_set($timezone);
$d = debug::getInstance();
$d->init($debug);

$r = new Restler();
$r->setSupportedFormats('JsonFormat', 'PlistFormat', 'XmlFormat');
$r->addAPIClass('Dep_stock');
$r->addAPIClass('Dep_stock_view');
$r->addAPIClass('Departamentos');
$r->addAPIClass('Moradas');
$r->addAPIClass('Plane_sets');
$r->addAPIClass('Planes');
$r->addAPIClass('Planes_sets_products');
$r->addAPIClass('Products');
$r->addAPIClass('Tipo_product');
$r->addAPIClass('Users');
$r->addAPIClass('Users_departamento');
$r->addAPIClass('User_dep_view');
$r->addAPIClass('Reports');
$r->addAPIClass('Encomendas');

$r->handle();

?>
