<?php

// Script to be loaded offline to create the classes and properties structures.


include_once("../framework/Conneg.php");
include_once("../framework/WebService.php");
include_once("../framework/ProcessorXML.php");
include_once("../framework/db.php");
include_once("../framework/Namespaces.php");
include_once("../auth/validator/AuthValidator.php");
include_once("../framework/ClassHierarchy.php");
include_once("../framework/PropertyHierarchy.php");
include_once("../framework/RdfClass.php");
include_once("../framework/RdfProperty.php");
include_once("../framework/WebService.php");
include_once("../framework/WebServiceQuerier.php");

include_once("create/OntologyCreate.php");

// Init the conneg structure to communicate with the ontologyCreate web service endpoint.
$_SERVER['HTTP_ACCEPT'] = "application/rdf+xml;q=1; text/*, text/html, text/html;level=1";
$_SERVER['HTTP_ACCEPT_CHARSET'] = "iso-8859-5, unicode-1-1;q=0.8, utf-8;q=1";
$_SERVER['HTTP_ACCEPT_ENCODING'] = "gzip;q=1.0, identity; q=1, *;q=0.5";
$_SERVER['HTTP_ACCEPT_LANGUAGE'] = "da, en-gb;q=0.8, en;q=0.7";


$data_ini = parse_ini_file(WebService::$data_ini."data.ini", TRUE);		

// Properly setup the connection to the virtuoso server
$db = new DB_Virtuoso($data_ini["triplestore"]["username"], $data_ini["triplestore"]["password"], $data_ini["triplestore"]["dsn"], $data_ini["triplestore"]["host"]);

$ontologiesFilesPath = $data_ini["ontologies"]["ontologies_files_folder"];

IndexOntologiesDirectory($ontologiesFilesPath);

function IndexOntologiesDirectory($dir)
{
	global $db;

	if($handler = opendir($dir)) 
	{
		while (($sub = readdir($handler)) !== FALSE) 
		{
			if ($sub != "." && $sub != "..") 
			{
				if(is_file($dir."/".$sub)) 
				{
					// Read the AMF file
					$handle = fopen($dir."/".$sub, "r");
					$ontologyFileContent = fread($handle, filesize($dir."/".$sub));
					fclose($handle);

					echo "Processing ontology file $sub\n";


					$wsq = new WebServiceQuerier(	"http://localhost/ws/ontology/create/", "post", "application/rdf+xml", 
																	"ontology=".urlencode($ontologyFileContent).
																	"&mime=".urlencode("application/rdf+xml").
																	"&action=recreate_inference".
																	"&registered_ip=".urlencode("127.0.0.1"));
					if($wsq->getStatus() != 200)
					{						
						echo "Web service error: (status: ".strip_tags($wsq->getStatus()).") ".strip_tags($wsq->getStatusMessage())." - ".strip_tags($wsq->getStatusMessageDescription())."\n----------\n$records\n----------\n"; 
					}

					unset($wsq);


/*
					$ows = new OntologyCreate($ontologyFileContent, "application/rdf+xml", "recreate_inference", "", "self");
					
					$ows->ws_conneg($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_ACCEPT_CHARSET'], $_SERVER['HTTP_ACCEPT_ENCODING'], $_SERVER['HTTP_ACCEPT_LANGUAGE']);

					$ows->process();

					if($ows->pipeline_getResponseHeaderStatus() != 200)
					{
						print_r($ows->pipeline_getError());
						
						echo "(".$ows->pipeline_getResponseHeaderStatus().") (".$ows->pipeline_getResponseHeaderStatusMsg().") (".$ows->pipeline_getResponseHeaderStatusMsgExt().") \n";		
					}
					
					unset($ows);
*/					

				}
				elseif(is_dir($dir."/".$sub))
				{
					IndexOntologiesDirectory($dir."/".$sub);
				}
			}
		}   
		closedir($handler);
	}
} 

?>