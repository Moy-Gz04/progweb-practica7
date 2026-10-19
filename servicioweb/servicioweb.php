<?php
// Publicacion del servicio web SOAP (sin WSDL).
// Cada metodo publico de la clase clsservicios queda disponible como
// operacion SOAP: sp_Acceso, sp_InsUsuario, sp_UpdUsuario, sp_DelUsuario,
// vwRptUsuarios y rptRoles.
include 'clsservicios.php';

$soap = new SoapServer(null, array('uri' => 'http://localhost/'));
$soap->setClass('clsservicios');
$soap->handle();
