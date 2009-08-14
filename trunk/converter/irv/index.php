<?php

	/*! @ingroup WsConverterIrv */
	//@{ 

	/*! @file \ws\converter\irv\index.php
		 @brief Entry point of a query the TSV Converter web service
		 @details Each time a query is sent to this web service, this index.php script will create the web service class
		               and will process it. The resultset, or error, will be returned to the user in the HTTP header & body query.
		
		 \n\n
	 
		 @author Frederick Giasson, Structured Dynamics LLC.
		 
		 \n\n\n
	 */
	
	


error_reporting(0);
//error_reporting(E_ALL);
ini_set("memory_limit","64M");


// Database connectivity procedures
include_once("../../framework/db.php");

// Content negotion class
include_once("../../framework/Conneg.php");

// The Web Service parent class
include_once("../../framework/WebService.php");

include_once("../../framework/ProcessorXML.php");

// Loading the Named Entities Extraction web service
include_once("ConverterIrv.php");
include_once("Dataset.php");
include_once("InstanceRecord.php");
include_once("LinkageSchema.php");
include_once("StructureSchema.php");
include_once("JsonParser.php");

include_once("../../framework/Logger.php");


$document = "";

/*
	3 mime choices for the text input:
	
	(1) application/irv+json
	(2) application/rdf+xml
	(3) application/rdf+n3
*/

if(isset($_POST['document'])) 
{
    $document = str_replace('\"', '"', $_POST['document']);
}

$docmime = "application/irv+json";
if(isset($_POST['docmime'])) 
{
    $docmime = str_replace('\"', '"', $_POST['docmime']);
}

$registered_ip = "";

if(isset($_POST['registered_ip'])) 
{
    $registered_ip = $_POST['registered_ip'];
}


$mtime = microtime(); 
$mtime = explode(' ', $mtime); 
$mtime = $mtime[1] + $mtime[0]; 
$starttime = $mtime; 

$start_datetime = date("Y-m-d h:i:s");

$requester_ip = "0.0.0.0";
if(isset($_SERVER['REMOTE_ADDR']))
{
	$requester_ip = $_SERVER['REMOTE_ADDR'];
}

$parameters = "";
if(isset($_SERVER['REQUEST_URI']))
{
	$parameters = $_SERVER['REQUEST_URI'];
	
	$pos = strpos($parameters, "?");
	
	if($pos !== FALSE)
	{
		$parameters = substr($parameters, $pos, strlen($parameters) - $pos);
	}
}
elseif(isset($_SERVER['PHP_SELF']))
{
	$parameters = $_SERVER['PHP_SELF'];
}

$ws_irv = new ConverterIrv($document, $docmime, $registered_ip, $requester_ip);

$ws_irv->ws_conneg($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_ACCEPT_CHARSET'], $_SERVER['HTTP_ACCEPT_ENCODING'], $_SERVER['HTTP_ACCEPT_LANGUAGE']);

$ws_irv->process();

$ws_irv->ws_respond($ws_irv->ws_serialize());


$mtime = microtime(); 
$mtime = explode(" ", $mtime); 
$mtime = $mtime[1] + $mtime[0]; 
$endtime = $mtime; 
$totaltime = ($endtime - $starttime); 

$logger = new Logger("converter/irv", $requester_ip, "--", $_SERVER['HTTP_ACCEPT'], $start_datetime, $totaltime, $ws_irv->pipeline_getResponseHeaderStatus(), $_SERVER['HTTP_USER_AGENT']);


	//@}

?>