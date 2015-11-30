<?php

require_once("Apeform.class.php");
require_once("../MyCSV.class.php");
require_once("MyCSVAdmin.class.php");

$admin = new MyCSVAdmin();
// $admin->dir = array("../", "./", "sub", "../tests");
// $admin->charset = "UTF-8";
// $admin->priv = PRIV_ALL;

$method = $admin->GET('method');
if (empty($method) || ! method_exists($admin, $method)) $method = "tables";
$admin->$method();

?>