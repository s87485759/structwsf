<?php

/*! @defgroup WsAuth Authentication / Registration Web Service */
//@{

/*! @file \ws\auth\lister\AuthLister.php
   @brief Lists registered web services and available datasets
  
   \n\n
 
   @author Frederick Giasson, Structured Dynamics LLC.

   \n\n\n
 */


/*!   @brief AuthLister Web Service. It lists registered web services and available dataset
            
    \n
    
    @author Frederick Giasson, Structured Dynamics LLC.
  
    \n\n\n
*/

class AuthLister extends WebService
{
  /*! @brief Database connection */
  private $db;

  /*! @brief Conneg object that manage the content negotiation capabilities of the web service */
  private $conneg;

  /*! @brief URL where the DTD of the XML document can be located on the Web */
  private $dtdURL;

  /*! @brief IP of the requester */
  private $requester_ip = "";

  /*! @brief Requested IP (ex: a node wants to see all web services or datasets accessible for one of its user) */
  private $registered_ip = "";

  /*! @brief Target dataset URI if action = "access_dataset" */
  private $dataset = "";

  /*! @brief Type of the thing to list */
  private $mode = "";

  /*! @brief List of datasets being listed */
  private $datasets = array();

  /*! @brief List of webservices being listed */
  private $webservices = array();

  /*! @brief List of accesses being listed */
  private $accesses = array();

  /*! @brief Error messages of this web service */
  private $errorMessenger =
    '{
                        "ws": "/ws/auth/lister/",
                        "_200": {
                          "id": "WS-AUTH-LISTER-200",
                          "level": "Warning",
                          "name": "Unknown Listing Mode",
                          "description": "The mode you specified for the \'mode\' parameter is unknown. Please check the documentation of this web service endpoint for more information"
                        },
                        "_201": {
                          "id": "WS-AUTH-LISTER-201",
                          "level": "Warning",
                          "name": "No Target Dataset URI",
                          "description": "No target dataset URI defined for this request. A target dataset URI is needed for the mode \'ws\' and \'dataset\'"
                        },
                        "_300": {
                          "id": "WS-AUTH-LISTER-300",
                          "level": "Fatal",
                          "name": "Can\'t get the list of datasets",
                          "description": "An error occured when we tried to get the list of datasets available to the user"
                        },
                        "_301": {
                          "id": "WS-AUTH-LISTER-301",
                          "level": "Fatal",
                          "name": "Can\'t get the list of web services",
                          "description": "An error occured when we tried to get the list of web services endpoints registered to this web service network"
                        },
                        "_302": {
                          "id": "WS-AUTH-LISTER-302",
                          "level": "Fatal",
                          "name": "Can\'t get the list of accesses for that dataset",
                          "description": "An error occured when we tried to get the list of accesses defined for this dataset"
                        },
                        "_303": {
                          "id": "WS-AUTH-LISTER-303",
                          "level": "Fatal",
                          "name": "Can\'t get the list of accesses an datasets available to that user",
                          "description": "An error occured when we tried to get the list of accesses and datasets accessible to that user"
                        },  
                        "_304": {
                          "id": "WS-AUTH-LISTER-304",
                          "level": "Fatal",
                          "name": "Can\'t get access information for this web service",
                          "description": "An error occured when we tried to get the information for the access to that web service."
                        }  
                      }';

  /*! @brief Supported serialization mime types by this Web service */
  public static $supportedSerializations =
    array ("application/json", "application/rdf+xml", "application/rdf+n3", "application/*", "text/xml", "text/*",
      "*/*");

/*!   @brief Constructor
     @details   Initialize the Auth Web Service
            
    \n
    
    @param[in] $mode One of:  (1) "dataset (default)": List all datasets URI accessible by a user, 
                        (2) "ws": List all Web services registered in a WSF
                          (3) "access_dataset": List all the registered IP addresses and their CRUD permissions for a given dataset URI
                          (4) "access_user": List all datasets URI and CRUD permissions accessible by a user 
    @param[in] $dataset URI referring to a target dataset. Needed when param1 = "dataset" or param1 = "access_datase". Otherwise this parameter as to be ommited.
    @param[in] $registered_ip Target IP address registered in the WSF
    @param[in] $requester_ip IP address of the requester
    
    @return returns NULL
  
    @author Frederick Giasson, Structured Dynamics LLC.
  
    \n\n\n
*/
  function __construct($mode, $dataset, $registered_ip, $requester_ip)
  {
    parent::__construct();

    $this->db = new DB_Virtuoso($this->db_username, $this->db_password, $this->db_dsn, $this->db_host);

    $this->requester_ip = $requester_ip;
    $this->mode = $mode;
    $this->dataset = $dataset;

    if($registered_ip == "")
    {
      $this->registered_ip = $requester_ip;
    }
    else
    {
      $this->registered_ip = $registered_ip;
    }

    if(strtolower(substr($this->registered_ip, 0, 4)) == "self")
    {
      $pos = strpos($this->registered_ip, "::");

      if($pos !== FALSE)
      {
        $account = substr($this->registered_ip, $pos + 2, strlen($this->registered_ip) - ($pos + 2));

        $this->registered_ip = $requester_ip . "::" . $account;
      }
      else
      {
        $this->registered_ip = $requester_ip;
      }
    }

    $this->uri = $this->wsf_base_url . "/wsf/ws/auth/lister/";
    $this->title = "Authentication Lister Web Service";
    $this->crud_usage = new CrudUsage(FALSE, TRUE, FALSE, FALSE);
    $this->endpoint = $this->wsf_base_url . "/ws/auth/lister/";

    $this->dtdURL = "auth/authLister.dtd";

    $this->errorMessenger = json_decode($this->errorMessenger);
  }

  function __destruct()
  {
    parent::__destruct();

    if(isset($this->db))
    {
      @$this->db->close();
    }
  }

  /*!   @brief Validate a query to this web service
              
      \n
      
      @return TRUE if valid; FALSE otherwise
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  protected function validateQuery()
  {
    // publicly accessible users)
    if($this->mode != "dataset" && $this->mode != "access_user")
    {
      $ws_av = new AuthValidator($this->requester_ip, $this->wsf_graph, $this->uri);

      $ws_av->pipeline_conneg($this->conneg->getAccept(), $this->conneg->getAcceptCharset(),
        $this->conneg->getAcceptEncoding(), $this->conneg->getAcceptLanguage());

      $ws_av->process();

      if($ws_av->pipeline_getResponseHeaderStatus() != 200)
      {
        $this->conneg->setStatus($ws_av->pipeline_getResponseHeaderStatus());
        $this->conneg->setStatusMsg($ws_av->pipeline_getResponseHeaderStatusMsg());
        $this->conneg->setStatusMsgExt($ws_av->pipeline_getResponseHeaderStatusMsgExt());
        $this->conneg->setError($ws_av->pipeline_getError()->id, $ws_av->pipeline_getError()->webservice,
          $ws_av->pipeline_getError()->name, $ws_av->pipeline_getError()->description,
          $ws_av->pipeline_getError()->debugInfo, $ws_av->pipeline_getError()->level);
        return;
      }
    }
  }

  /*!   @brief Returns the error structure
              
      \n
      
      @return returns the error structure
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function pipeline_getError() { return ($this->conneg->error); }

  /*!  @brief Create a resultset in a pipelined mode based on the processed information by the Web service.
              
      \n
      
      @return a resultset XML document
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function pipeline_getResultset()
  {
    $xml = new ProcessorXML();

    // Creation of the RESULTSET
    $resultset = $xml->createResultset();

    // Creation of the prefixes elements.
    $void = $xml->createPrefix("owl", "http://www.w3.org/2002/07/owl#");
    $resultset->appendChild($void);
    $rdf = $xml->createPrefix("rdf", "http://www.w3.org/1999/02/22-rdf-syntax-ns#");
    $resultset->appendChild($rdf);
    $dcterms = $xml->createPrefix("rdfs", "http://www.w3.org/2000/01/rdf-schema#");
    $resultset->appendChild($dcterms);
    $dcterms = $xml->createPrefix("wsf", "http://purl.org/ontology/wsf#");
    $resultset->appendChild($dcterms);

    if(strtolower($this->mode) != "access_dataset" && strtolower($this->mode) != "access_user")
    {
      // Creation of the SUBJECT of the RESULTSET
      $subject = $xml->createSubject("rdf:Bag", "");

      if(strtolower($this->mode) == "ws")
      {
        foreach($this->webservices as $ws)
        {
          // Creation of the RDF:LI predicate
          $pred = $xml->createPredicate("rdf:li");

          // Creation of the OBJECT of the predicate
          $object = $xml->createObject("wsf:WebService", "$ws");

          $pred->appendChild($object);
          $subject->appendChild($pred);
        }
      }
      elseif(strtolower($this->mode) == "dataset")
      {
        foreach($this->datasets as $dataset)
        {
          // Creation of the RDF:LI predicate
          $pred = $xml->createPredicate("rdf:li");

          // Creation of the OBJECT of the predicate
          $object = $xml->createObject("void:Dataset", "$dataset");

          $pred->appendChild($object);
          $subject->appendChild($pred);
        }
      }

      $resultset->appendChild($subject);
    }
    else
    {
      /*
        Array
        (
          [0] => Array
            (
              [0] => /wsf/access/auth/validator/2
              [1] => /wsf/
              [2] => True
              [3] => True
              [4] => True
              [5] => True
              [6] => /wsf/ws/auth/lister/
              [7] => /wsf/ws/auth/registrar/ws/
              [8] => /wsf/ws/auth/registrar/access/
            )
        
        )
      */

      // Creation of the SUBJECT of the RESULTSET

      foreach($this->accesses as $access)
      {
        $subject = $xml->createSubject("wsf:Access", $access[0]);

        if(strtolower($this->mode) == "access_user")
        {
          $pred = $xml->createPredicate("wsf:datasetAccess");
          $object = $xml->createObject("void:Dataset", $access[1]);
          $pred->appendChild($object);
          $subject->appendChild($pred);

          $pred = $xml->createPredicate("wsf:registeredIP");
          $object = $xml->createObjectContent($access[6]);
          $pred->appendChild($object);
          $subject->appendChild($pred);
        }
        else
        {
          $pred = $xml->createPredicate("wsf:registeredIP");
          $object = $xml->createObjectContent($access[1]);
          $pred->appendChild($object);
          $subject->appendChild($pred);
        }

        $pred = $xml->createPredicate("wsf:create");
        $object = $xml->createObjectContent($access[2]);
        $pred->appendChild($object);
        $subject->appendChild($pred);

        $pred = $xml->createPredicate("wsf:read");
        $object = $xml->createObjectContent($access[3]);
        $pred->appendChild($object);
        $subject->appendChild($pred);

        $pred = $xml->createPredicate("wsf:update");
        $object = $xml->createObjectContent($access[4]);
        $pred->appendChild($object);
        $subject->appendChild($pred);

        $pred = $xml->createPredicate("wsf:delete");
        $object = $xml->createObjectContent($access[5]);
        $pred->appendChild($object);
        $subject->appendChild($pred);

        $nbWS = count($access) - 7;

        for($i = 0; $i < $nbWS; $i++)
        {
          $pred = $xml->createPredicate("wsf:webServiceAccess");
          $object = $xml->createObject("wsf:WebService", $access[(7 + $i)]);
          $pred->appendChild($object);
          $subject->appendChild($pred);
        }

        $resultset->appendChild($subject);
      }
    }

    return ($this->injectDoctype($xml->saveXML($resultset)));
  }

  /*!   @brief Inject the DOCType in a XML document
              
      \n
      
      @param[in] $xmlDoc The XML document where to inject the doctype
      
      @return a XML document with a doctype
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function injectDoctype($xmlDoc)
  {
    $posHeader = strpos($xmlDoc, '"?>') + 3;
    $xmlDoc = substr($xmlDoc, 0, $posHeader)
      . "\n<!DOCTYPE resultset PUBLIC \"-//Structured Dynamics LLC//Auth Lister DTD 0.1//EN\" \"" . $this->dtdBaseURL
        . $this->dtdURL . "\">" . substr($xmlDoc, $posHeader, strlen($xmlDoc) - $posHeader);

    return ($xmlDoc);
  }

  /*!   @brief Do content negotiation as an external Web Service
              
      \n
      
      @param[in] $accept Accepted mime types (HTTP header)
      
      @param[in] $accept_charset Accepted charsets (HTTP header)
      
      @param[in] $accept_encoding Accepted encodings (HTTP header)
  
      @param[in] $accept_language Accepted languages (HTTP header)
    
      @return returns NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function ws_conneg($accept, $accept_charset, $accept_encoding, $accept_language)
  {
    $this->conneg =
      new Conneg($accept, $accept_charset, $accept_encoding, $accept_language, AuthLister::$supportedSerializations);

    // Check for errors
    if(strtolower($this->mode) != "ws" && strtolower($this->mode) != "dataset"
      && strtolower($this->mode) != "access_dataset" && strtolower($this->mode) != "access_user")
    {
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt("Unknown listing type");
      $this->conneg->setError($this->errorMessenger->_200->id, $this->errorMessenger->ws,
        $this->errorMessenger->_200->name, $this->errorMessenger->_200->description, odbc_errormsg(),
        $this->errorMessenger->_200->level);
      return;
    }

    // Check for errors
    if(strtolower($this->mode) != "access_dataset" && $dataset = "")
    {
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt($this->errorMessenger->_201->name);
      $this->conneg->setError($this->errorMessenger->_201->id, $this->errorMessenger->ws,
        $this->errorMessenger->_201->name, $this->errorMessenger->_201->description, odbc_errormsg(),
        $this->errorMessenger->_201->level);
      return;
    }
  }

  /*!   @brief Do content negotiation as an internal, pipelined, Web Service that is part of a Compound Web Service
              
      \n
      
      @param[in] $accept Accepted mime types (HTTP header)
      
      @param[in] $accept_charset Accepted charsets (HTTP header)
      
      @param[in] $accept_encoding Accepted encodings (HTTP header)
  
      @param[in] $accept_language Accepted languages (HTTP header)
    
      @return returns NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function pipeline_conneg($accept, $accept_charset, $accept_encoding, $accept_language)
    { $this->ws_conneg($accept, $accept_charset, $accept_encoding, $accept_language); }

  /*!   @brief Returns the response HTTP header status
              
      \n
      
      @return returns the response HTTP header status
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function pipeline_getResponseHeaderStatus() { return $this->conneg->getStatus(); }

  /*!   @brief Returns the response HTTP header status message
              
      \n
      
      @return returns the response HTTP header status message
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function pipeline_getResponseHeaderStatusMsg() { return $this->conneg->getStatusMsg(); }

  /*!   @brief Returns the response HTTP header status message extension
              
      \n
      
      @return returns the response HTTP header status message extension
    
      @note The extension of a HTTP status message is
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function pipeline_getResponseHeaderStatusMsgExt() { return $this->conneg->getStatusMsgExt(); }

  /*!   @brief Serialize the web service answer.
              
      \n
      
      @return returns the serialized content
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function pipeline_serialize()
  {

    $rdf_part = "";

    switch($this->conneg->getMime())
    {
      case "application/json":
        $json_part = "";
        $xml = new ProcessorXML();
        $xml->loadXML($this->pipeline_getResultset());

        $subjects = $xml->getSubjects();

        foreach($subjects as $subject)
        {
          $subjectURI = $xml->getURI($subject);
          $subjectType = $xml->getType($subject);

          $json_part .= "      { \n";
          $json_part .= "        \"uri\": \"" . parent::jsonEncode($subjectURI) . "\", \n";
          $json_part .= "        \"type\": \"" . parent::jsonEncode($subjectType) . "\", \n";

          $predicates = $xml->getPredicates($subject);

          $nbPredicates = 0;

          foreach($predicates as $predicate)
          {
            $objects = $xml->getObjects($predicate);

            foreach($objects as $object)
            {
              $nbPredicates++;

              if($nbPredicates == 1)
              {
                $json_part .= "        \"predicate\": [ \n";
              }

              $objectType = $xml->getType($object);
              $predicateType = $xml->getType($predicate);

              if($objectType == "rdfs:Literal")
              {
                $objectValue = $xml->getContent($object);

                $json_part .= "          { \n";
                $json_part .= "            \"" . parent::jsonEncode($predicateType) . "\": \""
                  . parent::jsonEncode($objectValue) . "\" \n";
                $json_part .= "          },\n";
              }
              else
              {
                $objectURI = $xml->getURI($object);
                $rdf_part .= "          <$predicateType> <$objectURI> ;\n";

                $json_part .= "          { \n";
                $json_part .= "            \"" . parent::jsonEncode($predicateType) . "\": { \n";
                $json_part .= "                \"uri\": \"" . parent::jsonEncode($objectURI) . "\" \n";
                $json_part .= "                } \n";
                $json_part .= "          },\n";
              }
            }
          }

          if(strlen($json_part) > 0)
          {
            $json_part = substr($json_part, 0, strlen($json_part) - 2) . "\n";
          }

          if($nbPredicates > 0)
          {
            $json_part .= "        ]\n";
          }

          $json_part .= "      },\n";
        }

        if(strlen($json_part) > 0)
        {
          $json_part = substr($json_part, 0, strlen($json_part) - 2) . "\n";
        }

        return ($json_part);
      break;

      case "application/rdf+n3":

        $xml = new ProcessorXML();
        $xml->loadXML($this->pipeline_getResultset());

        if(strtolower($this->mode) != "access_dataset" && strtolower($this->mode) != "access_user")
        {
          $subjects = $xml->getSubjectsByType("rdf:Bag");

          foreach($subjects as $subject)
          {
            $predicates = $xml->getPredicatesByType($subject, "rdf:li");

            foreach($predicates as $predicate)
            {
              if(strtolower($this->mode) == "dataset")
              {
                $objects = $xml->getObjectsByType($predicate, "void:Dataset");
              }
              else
              {
                $objects = $xml->getObjectsByType($predicate, "wsf:WebService");
              }

              foreach($objects as $object)
              {
                $rdf_part .= "    rdf:li <" . $xml->getURI($object) . "> ;\n";
              }
            }
          }

          if(strlen($rdf_part) > 0)
          {
            $rdf_part = substr($rdf_part, 0, strlen($rdf_part) - 2) . ".\n";
          }
        }
        else
        {
          $xml = new ProcessorXML();
          $xml->loadXML($this->pipeline_getResultset());

          $accesses = $xml->getSubjectsByType("wsf:Access");

          foreach($accesses as $access)
          {
            $access_uri = $xml->getURI($access);

            $rdf_part .= "<$access_uri> a wsf:Access ;\n";

            // Get webServiceAccess
            $predicates = $xml->getPredicatesByType($access, "wsf:datasetAccess");
            $objects = $xml->getObjectsByType($predicates->item(0), "void:Dataset");

            $rdf_part .= "wsf:datasetAccess <" . $xml->getURI($objects->item(0)) . "> ;\n";


            // Get crud
            $predicates = $xml->getPredicatesByType($access, "wsf:create");
            $objects = $xml->getObjectsByType($predicates->item(0), "rdfs:Literal");
            $rdf_part .= "wsf:create \"" . $xml->getContent($objects->item(0)) . "\" ;\n";

            $predicates = $xml->getPredicatesByType($access, "wsf:read");
            $objects = $xml->getObjectsByType($predicates->item(0), "rdfs:Literal");
            $rdf_part .= "wsf:read \"" . $xml->getContent($objects->item(0)) . "\" ;\n";

            $predicates = $xml->getPredicatesByType($access, "wsf:update");
            $objects = $xml->getObjectsByType($predicates->item(0), "rdfs:Literal");
            $rdf_part .= "wsf:update \"" . $xml->getContent($objects->item(0)) . "\" ;\n";

            $predicates = $xml->getPredicatesByType($access, "wsf:delete");
            $objects = $xml->getObjectsByType($predicates->item(0), "rdfs:Literal");
            $rdf_part .= "wsf:delete \"" . $xml->getContent($objects->item(0)) . "\" ;\n";

            // Get webServiceAccess(es)
            $webservices = $xml->getXPath('//predicate/object[attribute::type="wsf:WebService"]', $access);

            foreach($webservices as $element)
            {
              $rdf_part .= "wsf:webServiceAccess <" . $xml->getURI($element) . "> ;\n";
            }

            if(strlen($rdf_part) > 0)
            {
              $rdf_part = substr($rdf_part, 0, strlen($rdf_part) - 2) . ".\n";
            }
          }
        }

        return ($rdf_part);
      break;

      case "application/rdf+xml":

        $xml = new ProcessorXML();
        $xml->loadXML($this->pipeline_getResultset());

        if(strtolower($this->mode) != "access_dataset" && strtolower($this->mode) != "access_user")
        {
          $subjects = $xml->getSubjectsByType("rdf:Bag");

          foreach($subjects as $subject)
          {
            $predicates = $xml->getPredicatesByType($subject, "rdf:li");

            foreach($predicates as $predicate)
            {
              if(strtolower($this->mode) == "dataset")
              {
                $objects = $xml->getObjectsByType($predicate, "void:Dataset");
              }
              else
              {
                $objects = $xml->getObjectsByType($predicate, "wsf:WebService");
              }

              foreach($objects as $object)
              {
                $rdf_part .= "    <rdf:li rdf:resource=\"" . $this->xmlEncode($xml->getURI($object)) . "\" />\n";
              }
            }
          }
        }
        else
        {
          $xml = new ProcessorXML();
          $xml->loadXML($this->pipeline_getResultset());

          $accesses = $xml->getSubjectsByType("wsf:Access");

          foreach($accesses as $access)
          {
            $access_uri = $xml->getURI($access);

            $rdf_part .= "<wsf:Access rdf:about=\"$access_uri\">\n";

            // Get webServiceAccess
            $predicates = $xml->getPredicatesByType($access, "wsf:datasetAccess");
            $objects = $xml->getObjectsByType($predicates->item(0), "void:Dataset");

            $rdf_part .= "<wsf:datasetAccess rdf:resource=\"" . $this->xmlEncode($xml->getURI($objects->item(0))) . "\" />\n";


            // Get crud
            $predicates = $xml->getPredicatesByType($access, "wsf:create");
            $objects = $xml->getObjectsByType($predicates->item(0), "rdfs:Literal");
            $rdf_part .= "<wsf:create>" . $xml->getContent($objects->item(0)) . "</wsf:create>\n";

            $predicates = $xml->getPredicatesByType($access, "wsf:read");
            $objects = $xml->getObjectsByType($predicates->item(0), "rdfs:Literal");
            $rdf_part .= "<wsf:read>" . $xml->getContent($objects->item(0)) . "</wsf:read>\n";

            $predicates = $xml->getPredicatesByType($access, "wsf:update");
            $objects = $xml->getObjectsByType($predicates->item(0), "rdfs:Literal");
            $rdf_part .= "<wsf:update>" . $xml->getContent($objects->item(0)) . "</wsf:update>\n";

            $predicates = $xml->getPredicatesByType($access, "wsf:delete");
            $objects = $xml->getObjectsByType($predicates->item(0), "rdfs:Literal");
            $rdf_part .= "<wsf:delete>" . $xml->getContent($objects->item(0)) . "</wsf:delete>\n";

            // Get webServiceAccess(es)
            $webservices = $xml->getXPath('//predicate/object[attribute::type="wsf:WebService"]', $access);

            foreach($webservices as $element)
            {
              $rdf_part .= "<wsf:webServiceAccess rdf:resource=\"" . $xml->getURI($element) . "\" />\n";
            }

            $rdf_part .= "</wsf:Access>\n";
          }
        }

        return ($rdf_part);
      break;
    }
  }

  /*!   @brief Non implemented method (only defined)
              
      \n
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function pipeline_serialize_reification() { return ""; }

  /*!   @brief Serialize the web service answer.
              
      \n
      
      @return returns the serialized content
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function ws_serialize()
  {
    switch($this->conneg->getMime())
    {
      case "application/rdf+n3":
        $rdf_document = "";
        $rdf_document .= "@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .\n";
        $rdf_document .= "@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .\n";
        $rdf_document .= "@prefix owl: <http://www.w3.org/2002/07/owl#> .\n";
        $rdf_document .= "@prefix void: <http://rdfs.org/ns/void#> .\n";
        $rdf_document .= "@prefix wsf: <http://purl.org/ontology/wsf#> .\n";

        if(strtolower($this->mode) != "access_dataset" && strtolower($this->mode) != "access_user")
        {
          $rdf_document .= "_:bnode0 rdf:type rdf:Bag ;\n";
        }

        $rdf_document .= $this->pipeline_serialize();

        $rdf_document = substr($rdf_document, 0, strlen($rdf_document) - 2) . ".\n";

        return $rdf_document;
      break;

      case "application/rdf+xml":
        $rdf_document = "";
        $rdf_document .= "<?xml version=\"1.0\"?>\n";
        $rdf_document
          .= "<rdf:RDF xmlns:bibo=\"http://purl.org/ontology/bibo/\" xmlns:void=\"http://rdfs.org/ns/void#\" xmlns:wsf=\"http://purl.org/ontology/wsf#\" xmlns:owl=\"http://www.w3.org/2002/07/owl#\" xmlns:xsd=\"http://www.w3.org/2001/XMLSchema#\" xmlns:rdfs=\"http://www.w3.org/2000/01/rdf-schema#\" xmlns:rdf=\"http://www.w3.org/1999/02/22-rdf-syntax-ns#\">\n\n";

        if(strtolower($this->mode) != "access_dataset" && strtolower($this->mode) != "access_user")
        {
          $rdf_document .= "<rdf:Bag>\n";
        }

        $rdf_document .= $this->pipeline_serialize();

        if(strtolower($this->mode) != "access_dataset" && strtolower($this->mode) != "access_user")
        {
          $rdf_document .= "</rdf:Bag>\n\n";
        }

        $rdf_document .= "</rdf:RDF>";

        return $rdf_document;
      break;

      case "application/json":
        $json_document = "";
        $json_document .= "{\n";

        $json_document .= "  \"prefixes\": [ \n";
        $json_document .= "    {\n";
        $json_document .= "      \"rdf\": \"http://www.w3.org/1999/02/22-rdf-syntax-ns#\",\n";
        $json_document .= "      \"owl\": \"http://www.w3.org/2002/07/owl#\",\n";
        $json_document .= "      \"rdfs\": \"http://www.w3.org/2000/01/rdf-schema#\",\n";
        $json_document .= "      \"wsf\": \"http://purl.org/ontology/wsf#\"\n";
        $json_document .= "    } \n";
        $json_document .= "  ],\n";

        $json_document .= "  \"resultset\": {\n";
        $json_document .= "    \"subject\": [\n";
        $json_document .= $this->pipeline_serialize();
        $json_document .= "    ]\n";
        $json_document .= "  }\n";
        $json_document .= "}";

        return ($json_document);
      break;

      case "text/xml":
        return $this->pipeline_getResultset();
      break;
    }
  }

  /*!   @brief Sends the HTTP response to the requester
              
      \n
      
      @param[in] $content The content (body) of the response.
      
      @return NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function ws_respond($content)
  {
    // First send the header of the request
    $this->conneg->respond();

    // second, send the content of the request

    // Make sure there is no error.
    if($this->conneg->getStatus() == 200)
    {
      echo $content;
    }

    $this->__destruct();
  }


  /*!   @brief Aggregates information about the Accesses available to the requester.
              
      \n
      
      @return NULL      
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function process()
  {
    // Make sure there was no conneg error prior to this process call
    if($this->conneg->getStatus() == 200)
    {
      $this->validateQuery();

      // If the query is still valid
      if($this->conneg->getStatus() == 200)
      {
        if(strtolower($this->mode) == "dataset")
        {
          $query =
            "  select distinct ?dataset 
                  from <" . $this->wsf_graph
            . "> 
                  where 
                  { 
                    { 
                      ?access <http://purl.org/ontology/wsf#registeredIP> \"$this->registered_ip\" ; 
                            <http://purl.org/ontology/wsf#datasetAccess> ?dataset . 
                    } 
                    UNION 
                    { 
                      ?access <http://purl.org/ontology/wsf#registeredIP> \"0.0.0.0\" ; 
                            <http://purl.org/ontology/wsf#create> ?create ; 
                            <http://purl.org/ontology/wsf#read> ?read ; 
                            <http://purl.org/ontology/wsf#update> ?update ; 
                            <http://purl.org/ontology/wsf#delete> ?delete ; 
                            <http://purl.org/ontology/wsf#datasetAccess> ?dataset .
                      filter( str(?create) = \"True\" or str(?read) = \"True\" or str(?update) = \"True\" or str(?delete) = \"True\").
                    }
                  }";

          $resultset =
            @$this->db->query($this->db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), " ", $query), array(),
              FALSE));

          if(odbc_error())
          {
            $this->conneg->setStatus(500);
            $this->conneg->setStatusMsg("Internal Error");
            $this->conneg->setStatusMsgExt($this->errorMessenger->_300->name);
            $this->conneg->setError($this->errorMessenger->_300->id, $this->errorMessenger->ws,
              $this->errorMessenger->_300->name, $this->errorMessenger->_300->description, odbc_errormsg(),
              $this->errorMessenger->_300->level);
            return;
          }

          while(odbc_fetch_row($resultset))
          {
            $dataset = odbc_result($resultset, 1);

            array_push($this->datasets, $dataset);
          }
        }
        elseif(strtolower($this->mode) == "ws")
        {
          $query =
            "  select distinct ?ws from <" . $this->wsf_graph
            . ">
                  where
                  {
                    ?wsf a <http://purl.org/ontology/wsf#WebServiceFramework> ;
                          <http://purl.org/ontology/wsf#hasWebService> ?ws .
                  }";

          $resultset =
            @$this->db->query($this->db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), " ", $query), array(),
              FALSE));

          if(odbc_error())
          {
            $this->conneg->setStatus(500);
            $this->conneg->setStatusMsg("Internal Error");
            $this->conneg->setStatusMsgExt($this->errorMessenger->_301->name);
            $this->conneg->setError($this->errorMessenger->_301->id, $this->errorMessenger->ws,
              $this->errorMessenger->_301->name, $this->errorMessenger->_301->description, odbc_errormsg(),
              $this->errorMessenger->_301->level);
            return;
          }

          while(odbc_fetch_row($resultset))
          {
            $ws = odbc_result($resultset, 1);

            array_push($this->webservices, $ws);
          }
        }
        else
        {
          if(strtolower($this->mode) == "access_user")
          {
            $query = "  select ?access ?datasetAccess ?create ?read ?update ?delete ?registeredIP
                    from <" . $this->wsf_graph
              . ">
                    where
                    {
                      {
                        ?access a <http://purl.org/ontology/wsf#Access> ;
                              <http://purl.org/ontology/wsf#registeredIP> \"$this->registered_ip\" ;
                              <http://purl.org/ontology/wsf#create> ?create ;
                              <http://purl.org/ontology/wsf#read> ?read ;
                              <http://purl.org/ontology/wsf#update> ?update ;
                              <http://purl.org/ontology/wsf#delete> ?delete ;
                              <http://purl.org/ontology/wsf#datasetAccess> ?datasetAccess ;
                              <http://purl.org/ontology/wsf#registeredIP> ?registeredIP .
                      }
                      union
                      {
                        ?access a <http://purl.org/ontology/wsf#Access> ;
                              <http://purl.org/ontology/wsf#registeredIP> \"0.0.0.0\" ;
                              <http://purl.org/ontology/wsf#create> ?create ;
                              <http://purl.org/ontology/wsf#read> ?read ;
                              <http://purl.org/ontology/wsf#update> ?update ;
                              <http://purl.org/ontology/wsf#delete> ?delete ;
                              <http://purl.org/ontology/wsf#datasetAccess> ?datasetAccess ;                      
                              <http://purl.org/ontology/wsf#registeredIP> ?registeredIP .
                              
                        filter( str(?create) = \"True\" or str(?read) = \"True\" or str(?update) = \"True\" or str(?delete) = \"True\").
                      }
                    }";
          }
          else // access_dataset
          {
            $query = "  select ?access ?registeredIP ?create ?read ?update ?delete  
                    from <" . $this->wsf_graph
              . ">
                    where
                    {
                      ?access a <http://purl.org/ontology/wsf#Access> ;
                            <http://purl.org/ontology/wsf#registeredIP> ?registeredIP ;
                            <http://purl.org/ontology/wsf#create> ?create ;
                            <http://purl.org/ontology/wsf#read> ?read ;
                            <http://purl.org/ontology/wsf#update> ?update ;
                            <http://purl.org/ontology/wsf#delete> ?delete ;
                            <http://purl.org/ontology/wsf#datasetAccess> <$this->dataset> .
                    }";
          }

          $resultset =
            @$this->db->query($this->db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), " ", $query), array(),
              FALSE));

          if(odbc_error())
          {
            $this->conneg->setStatus(500);
            $this->conneg->setStatusMsg("Internal Error");

            if(strtolower($this->mode) == "access_user")
            {
              $this->conneg->setStatusMsgExt($this->errorMessenger->_302->name);
              $this->conneg->setError($this->errorMessenger->_302->id, $this->errorMessenger->ws,
                $this->errorMessenger->_302->name, $this->errorMessenger->_302->description, odbc_errormsg(),
                $this->errorMessenger->_302->level);
            }
            else
            {
              $this->conneg->setStatusMsgExt($this->errorMessenger->_303->name);
              $this->conneg->setError($this->errorMessenger->_303->id, $this->errorMessenger->ws,
                $this->errorMessenger->_303->name, $this->errorMessenger->_303->description, odbc_errormsg(),
                $this->errorMessenger->_303->level);
            }

            return;
          }

          while(odbc_fetch_row($resultset))
          {
            $lastElement = "";

            if(strtolower($this->mode) == "access_user")
            {
              $lastElement = odbc_result($resultset, 7);
            }

            array_push($this->accesses,
              array (odbc_result($resultset, 1), odbc_result($resultset, 2), odbc_result($resultset, 3),
                odbc_result($resultset, 4), odbc_result($resultset, 5), odbc_result($resultset, 6), $lastElement));
          }

          foreach($this->accesses as $key => $access)
          {
            $query = "select ?webServiceAccess  from <" . $this->wsf_graph . ">
                    {
                      <" . $access[0] . "> <http://purl.org/ontology/wsf#webServiceAccess> ?webServiceAccess .
                    }";

            $resultset =
              @$this->db->query($this->db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), " ", $query),
                array(), FALSE));

            if(odbc_error())
            {
              $this->conneg->setStatus(500);
              $this->conneg->setStatusMsg("Internal Error");
              $this->conneg->setStatusMsgExt($this->errorMessenger->_304->name);
              $this->conneg->setError($this->errorMessenger->_304->id, $this->errorMessenger->ws,
                $this->errorMessenger->_304->name, $this->errorMessenger->_304->description, odbc_errormsg(),
                $this->errorMessenger->_304->level);
              return;
            }

            while(odbc_fetch_row($resultset))
            {
              array_push($this->accesses[$key], odbc_result($resultset, 1));
            }
          }
        }
      }
    }
  }
}

//@}

?>