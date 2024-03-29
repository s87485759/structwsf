<?php

/*! @ingroup WsSparql */
//@{

/*! @file \ws\sparql\Sparql.php
   @brief Define the Sparql web service
  
   \n\n
 
   @author Frederick Giasson, Structured Dynamics LLC.

   \n\n\n
 */


/*!   @brief SPARQL Web Service. It sends SPARQL queries to datasets indexed in the structWSF instance.
            
    \n
    
    @author Frederick Giasson, Structured Dynamics LLC.
  
    \n\n\n
*/

class Sparql extends WebService
{
  /*! @brief Database connection */
  private $db;

  /*! @brief Conneg object that manage the content negotiation capabilities of the web service */
  private $conneg;

  /*! @brief URL where the DTD of the XML document can be located on the Web */
  private $dtdURL;

  /*! @brief Sparql query */
  private $query = "";

  /*! @brief Dataset where t send the query */
  private $dataset = "";

  /*! @brief IP of the requester */
  private $requester_ip = "";

  /*! @brief Limit of the number of results to return in the resultset */
  private $limit = "";

  /*! @brief Offset of the "sub-resultset" from the total resultset of the query */
  private $offset = "";

  /*! @brief Requested IP */
  private $registered_ip = "";

  /*! @brief SPARQL query content resultset */
  private $sparqlContent = "";

  /*! @brief Instance records from the query where the object of the triple is a literal */
  private $instanceRecordsObjectLiteral = array();

  /*! @brief Instance records from the query where the object of the triple is a resource */
  private $instanceRecordsObjectResource = array();

  /*! @brief Namespaces/Prefixes binding */
  private $namespaces =
    array ("http://www.w3.org/2002/07/owl#" => "owl", "http://www.w3.org/1999/02/22-rdf-syntax-ns#" => "rdf",
      "http://www.w3.org/2000/01/rdf-schema#" => "rdfs", "http://purl.org/ontology/wsf#" => "wsf");

  /*! @brief Determine if this is a CONSTRUCT SPARQL query */
  private $isConstructQuery = FALSE;
  
  /*! @brief Determine if this is a CONSTRUCT SPARQL query */
  private $isDescribeQuery = FALSE;

  /*! @brief Supported MIME serializations by this web service */
  public static $supportedSerializations =
    array ("application/rdf+json", "text/rdf+n3", "application/json", "text/xml", "application/sparql-results+xml", "application/sparql-results+json",
      "text/html", "application/rdf+xml", "application/rdf+n3", "application/*", "text/plain", "text/*", "*/*");

  /*! @brief Error messages of this web service */
  private $errorMessenger =
    '{
                        "ws": "/ws/sparql/",
                        "_200": {
                          "id": "WS-SPARQL-200",
                          "level": "Warning",
                          "name": "No query specified for this request",
                          "description": "No query specified for this request"
                        },
                        "_201": {
                          "id": "WS-SPARQL-201",
                          "level": "Warning",
                          "name": "No dataset specified for this request",
                          "description": "No dataset specified for this request"
                        },
                        "_202": {
                          "id": "WS-SPARQL-202",
                          "level": "Warning",
                          "name": "The maximum number of records returned within the same slice is 2000. Use multiple queries with the OFFSET parameter to build-up the entire resultset.",
                          "description": "The maximum number of records returned within the same slice is 2000. Use multiple queries with the OFFSET parameter to build-up the entire resultset."
                        },
                        "_203": {
                          "id": "WS-SPARQL-203",
                          "level": "Warning",
                          "name": "SPARUL not permitted.",
                          "description": "No SPARUL queries are permitted for this sparql endpoint."
                        },
                        "_204": {
                          "id": "WS-SPARQL-204",
                          "level": "Warning",
                          "name": "CONSTRUCT not permitted.",
                          "description": "The SPARQL CONSTRUCT clause is not permitted for this sparql endpoint. Please change you mime type if you want to get the resultset in a specific format."
                        },
                        "_205": {
                          "id": "WS-SPARQL-205",
                          "level": "Warning",
                          "name": "GRAPH not permitted without FROM NAMED clauses.",
                          "description": "The SPARQL GRAPH clause is not permitted for this sparql endpoint. GRAPH clauses are only permitted when you bound your SPARQL query using one, or a series of FROM NAMED clauses."
                        },                        
                        "_206": {
                          "id": "WS-SPARQL-206",
                          "level": "Warning",
                          "name": "Dataset not accessible.",
                          "description": "You don\' have access to the dataset URI you specified in the dataset parameter of this query."
                        },                        
                        "_300": {
                          "id": "WS-SPARQL-300",
                          "level": "Warning",
                          "name": "Connection to the sparql endpoint failed",
                          "description": "Connection to the sparql endpoint failed"
                        },
                        "_301": {
                          "id": "WS-SPARQL-301",
                          "level": "Notice",
                          "name": "No instance records found",
                          "description": "No instance records found for this query"
                        }  
                      }';


  /*!   @brief Constructor
       @details   Initialize the Sparql Web Service
        
      @param[in] $query SPARQL query to send to the triple store of the WSF
      @param[in] $dataset Dataset URI where to send the query
      @param[in] $limit Limit of the number of results to return in the resultset
      @param[in] $offset Offset of the "sub-resultset" from the total resultset of the query
      @param[in] $registered_ip Target IP address registered in the WSF
      @param[in] $requester_ip IP address of the requester
              
      \n
      
      @return returns NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  function __construct($query, $dataset, $limit, $offset, $registered_ip, $requester_ip)
  {
    parent::__construct();

    $this->db = new DB_Virtuoso($this->db_username, $this->db_password, $this->db_dsn, $this->db_host);

    $this->query = $query;
    $this->limit = $limit;
    $this->offset = $offset;
    $this->dataset = $dataset;
    $this->requester_ip = $requester_ip;

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

    $this->uri = $this->wsf_base_url . "/wsf/ws/sparql/";
    $this->title = "Sparql Web Service";
    $this->crud_usage = new CrudUsage(FALSE, TRUE, FALSE, FALSE);
    $this->endpoint = $this->wsf_base_url . "/ws/sparql/";

    $this->dtdURL = "sparql/sparql.dtd";

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
    
      @note This function is not used by the authentication validator web service
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  protected function validateQuery()
  {
    // Validating the access of the dataset specified as input parameter if defined.
    if($this->dataset != "")
    {
      $ws_av = new AuthValidator($this->registered_ip, $this->dataset, $this->uri);

      $ws_av->pipeline_conneg("*/*", $this->conneg->getAcceptCharset(), $this->conneg->getAcceptEncoding(),
        $this->conneg->getAcceptLanguage());

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
    $labelProperties =
      array (Namespaces::$dcterms . "title", Namespaces::$foaf . "name", Namespaces::$foaf . "givenName",
        Namespaces::$foaf . "family_name", Namespaces::$rdfs . "label", Namespaces::$skos_2004 . "prefLabel",
        Namespaces::$skos_2004 . "altLabel", Namespaces::$skos_2008 . "prefLabel",
        Namespaces::$skos_2008 . "altLabel");

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

    $subject;

    foreach($this->instanceRecordsObjectResource as $uri => $result)
    {
      // Assigning types
      if(isset($result["http://www.w3.org/1999/02/22-rdf-syntax-ns#type"]))
      {
        foreach($result["http://www.w3.org/1999/02/22-rdf-syntax-ns#type"] as $key => $type)
        {
          if($key > 0)
          {
            $pred = $xml->createPredicate("http://www.w3.org/1999/02/22-rdf-syntax-ns#type");
            $object = $xml->createObject("", $type);
            $pred->appendChild($object);
            $subject->appendChild($pred);
          }
          else
          {
            $subject = $xml->createSubject($type, $uri);
          }
        }
      }
      else
      {
        $subject = $xml->createSubject("http://www.w3.org/2002/07/owl#Thing", $uri);
      }

      // Assigning object resource properties
      foreach($result as $property => $values)
      {
        if($property != "http://www.w3.org/1999/02/22-rdf-syntax-ns#type")
        {
          foreach($values as $value)
          {
            $label = "";

            foreach($labelProperties as $labelProperty)
            {
              if($this->instanceRecordsObjectLiteral[$value])
              {
                // The object resource is part of the resultset
                // This mainly occurs when we export complete datasets

                if(isset($this->instanceRecordsObjectLiteral[$value][$labelProperty]))
                {
                  $label = $this->instanceRecordsObjectLiteral[$value][$labelProperty][0];
                  break;
                }
              }
              else
              {
              // The object resource is not part of the resultset
              // In the future, we can send another sparql query to get its label.
              }
            }

            $pred = $xml->createPredicate($property);
            $object = $xml->createObject("", $value, ($label != "" ? $label : ""));
            $pred->appendChild($object);

            $subject->appendChild($pred);
          }
        }
      }

      // Assigning object literal properties
      if(isset($this->instanceRecordsObjectLiteral[$uri]))
      {
        foreach($this->instanceRecordsObjectLiteral[$uri] as $property => $values)
        {
          if($property != "http://www.w3.org/1999/02/22-rdf-syntax-ns#type")
          {
            foreach($values as $value)
            {
              $pred = $xml->createPredicate($property);
              $object = $xml->createObjectContent($value);
              $pred->appendChild($object);
              $subject->appendChild($pred);
            }
          }
        }
      }
      
      $resultset->appendChild($subject);
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
      . "\n<!DOCTYPE resultset PUBLIC \"-//Structured Dynamics LLC//SPARQL DTD 0.1//EN\" \"" . $this->dtdBaseURL
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
      new Conneg($accept, $accept_charset, $accept_encoding, $accept_language, Sparql::$supportedSerializations);

    // Validate query
    $this->validateQuery();

    // If the query is still valid
    if($this->conneg->getStatus() == 200)
    {
      // Check for errors
      if($this->query == "")
      {
        $this->conneg->setStatus(400);
        $this->conneg->setStatusMsg("Bad Request");
        $this->conneg->setStatusMsgExt($this->errorMessenger->_200->name);
        $this->conneg->setError($this->errorMessenger->_200->id, $this->errorMessenger->ws,
          $this->errorMessenger->_200->name, $this->errorMessenger->_200->description, "",
          $this->errorMessenger->_200->level);

        return;
      }

      if($this->limit > 2000)
      {
        $this->conneg->setStatus(400);
        $this->conneg->setStatusMsg("Bad Request");
        $this->conneg->setStatusMsgExt($this->errorMessenger->_202->name);
        $this->conneg->setError($this->errorMessenger->_202->id, $this->errorMessenger->ws,
          $this->errorMessenger->_202->name, $this->errorMessenger->_202->description, "",
          $this->errorMessenger->_202->level);

        return;
      }
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

  /*!   @brief Get the namespace of a URI
              
      @param[in] $uri Uri of the resource from which we want the namespace
              
      \n
      
      @return returns the extracted namespace      
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  private function getNamespace($uri)
  {
    $pos = strrpos($uri, "#");

    if($pos !== FALSE)
    {
      return array (substr($uri, 0, $pos) . "#", substr($uri, $pos + 1, strlen($uri) - ($pos + 1)));
    }
    else
    {
      $pos = strrpos($uri, "/");

      if($pos !== FALSE)
      {
        return array (substr($uri, 0, $pos) . "/", substr($uri, $pos + 1, strlen($uri) - ($pos + 1)));
      }
      else
      {
        $pos = strpos($uri, ":");

        if($pos !== FALSE)
        {
          $nsUri = explode(":", $uri, 2);

          foreach($this->namespaces as $uri2 => $prefix2)
          {
            $uri2 = urldecode($uri2);

            if($prefix2 == $nsUri[0])
            {
              return (array ($uri2, $nsUri[1]));
            }
          }

          return explode(":", $uri, 2);
        }
      }
    }

    return (FALSE);
  }


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

        $nsId = 0;

        foreach($subjects as $subject)
        {
          $subjectURI = $xml->getURI($subject);
          $subjectType = $xml->getType($subject);

          $ns = $this->getNamespace($subjectType);

          if(!isset($this->namespaces[$ns[0]]))
          {
            $this->namespaces[$ns[0]] = "ns" . $nsId;
            $nsId++;
          }

          $json_part .= "      { \n";
          $json_part .= "        \"uri\": \"" . parent::jsonEncode($subjectURI) . "\", \n";
          $json_part .= "        \"type\": \"" . parent::jsonEncode($this->namespaces[$ns[0]] . ":" . $ns[1])
            . "\", \n";

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
                $json_part .= "        \"predicates\": [ \n";
              }

              $objectType = $xml->getType($object);
              $predicateType = $xml->getType($predicate);

              if($objectType == "rdfs:Literal")
              {
                $objectValue = $xml->getContent($object);

                $ns = $this->getNamespace($predicateType);

                if(!isset($this->namespaces[$ns[0]]))
                {
                  $this->namespaces[$ns[0]] = "ns" . $nsId;
                  $nsId++;
                }

                $json_part .= "          { \n";
                $json_part .= "            \"" . parent::jsonEncode($this->namespaces[$ns[0]] . ":" . $ns[1]) . "\": \""
                  . parent::jsonEncode($objectValue) . "\" \n";
                $json_part .= "          },\n";
              }
              else
              {
                $objectURI = $xml->getURI($object);

                $ns = $this->getNamespace($predicateType);

                if(!isset($this->namespaces[$ns[0]]))
                {
                  $this->namespaces[$ns[0]] = "ns" . $nsId;
                  $nsId++;
                }

                $json_part .= "          { \n";
                $json_part .= "            \"" . parent::jsonEncode($this->namespaces[$ns[0]] . ":" . $ns[1])
                  . "\": { \n";
                $json_part .= "                \"uri\": \"" . parent::jsonEncode($objectURI) . "\",\n";

                // Check if there is a reification statement for this object.
                $reifies = $xml->getReificationStatementsByType($object, "wsf:objectLabel");

                $nbReification = 0;

                foreach($reifies as $reify)
                {
                  $nbReification++;

                  if($nbReification > 0)
                  {
                    $json_part .= "               \"reifies\": [\n";
                  }

                  $json_part .= "                 { \n";
                  $json_part .= "                     \"type\": \"wsf:objectLabel\", \n";
                  $json_part .= "                     \"value\": \"" . parent::jsonEncode($xml->getValue($reify))
                    . "\" \n";
                  $json_part .= "                 },\n";
                }

                if($nbReification > 0)
                {
                  $json_part = substr($json_part, 0, strlen($json_part) - 2) . "\n";

                  $json_part .= "               ]\n";
                }
                else
                {
                  $json_part = substr($json_part, 0, strlen($json_part) - 2) . "\n";
                }

                $json_part .= "              } \n";
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

        $json_header .= "  \"prefixes\": [ \n";
        $json_header .= "    {\n";

        foreach($this->namespaces as $ns => $prefix)
        {
          $json_header .= "      \"$prefix\": \"$ns\",\n";
        }

        if(strlen($json_header) > 0)
        {
          $json_header = substr($json_header, 0, strlen($json_header) - 2) . "\n";
        }

        $json_header .= "    } \n";
        $json_header .= "  ],\n";
        $json_header .= "  \"resultset\": {\n";
        $json_header .= "    \"subject\": [\n";
        $json_header .= $json_part;
        $json_header .= "    ]\n";
        $json_header .= "  }\n";

        return ($json_header);
      break;

      case "application/rdf+n3":

        $xml = new ProcessorXML();
        $xml->loadXML($this->pipeline_getResultset());

        $subjects = $xml->getSubjects();

        foreach($subjects as $subject)
        {
          $subjectURI = $xml->getURI($subject);
          $subjectType = $xml->getType($subject, FALSE);

          $rdf_part .= "\n    <$subjectURI> a <$subjectType> ;\n";

          $predicates = $xml->getPredicates($subject);

          foreach($predicates as $predicate)
          {
            $objects = $xml->getObjects($predicate);

            foreach($objects as $object)
            {
              $objectType = $xml->getType($object);
              $predicateType = $xml->getType($predicate, FALSE);
              $objectContent = $xml->getContent($object);

              if($objectType == "rdfs:Literal")
              {
                $objectValue = $xml->getContent($object);
                $rdf_part .= "        <$predicateType> \"\"\"" . str_replace(array( "\\" ), "\\\\", $objectValue)
                  . "\"\"\" ;\n";
              }
              else
              {
                $objectURI = $xml->getURI($object);
                $rdf_part .= "        <$predicateType> <$objectURI> ;\n";
              }
            }
          }

          if(strlen($rdf_part) > 0)
          {
            $rdf_part = substr($rdf_part, 0, strlen($rdf_part) - 2) . ".\n";
          }
        }

        return ($rdf_part);
      break;

      case "application/rdf+xml":
        $xml = new ProcessorXML();
        $xml->loadXML($this->pipeline_getResultset());

        $subjects = $xml->getSubjects();

        $nsId = 0;

        foreach($subjects as $subject)
        {
          $subjectURI = $xml->getURI($subject);
          $subjectType = $xml->getType($subject);

          $ns1 = $this->getNamespace($subjectType);

          if(!isset($this->namespaces[$ns1[0]]))
          {
            $this->namespaces[$ns1[0]] = "ns" . $nsId;
            $nsId++;
          }

          $rdf_part .= "\n    <" . $this->namespaces[$ns1[0]] . ":" . $ns1[1] . " rdf:about=\"".
                                                                                  $this->xmlEncode($subjectURI)."\">\n";

          $predicates = $xml->getPredicates($subject);

          foreach($predicates as $predicate)
          {
            $objects = $xml->getObjects($predicate);

            foreach($objects as $object)
            {
              $objectType = $xml->getType($object);
              $predicateType = $xml->getType($predicate);

              if($objectType == "rdfs:Literal")
              {
                $objectValue = $xml->getContent($object);

                $ns = $this->getNamespace($predicateType);

                if(!isset($this->namespaces[$ns[0]]))
                {
                  $this->namespaces[$ns[0]] = "ns" . $nsId;
                  $nsId++;
                }

                $rdf_part .= "        <" . $this->namespaces[$ns[0]] . ":" . $ns[1] . ">"
                  . $this->xmlEncode($objectValue) . "</" . $this->namespaces[$ns[0]] . ":" . $ns[1] . ">\n";
              }
              else
              {
                $objectURI = $xml->getURI($object);

                $ns = $this->getNamespace($predicateType);

                if(!isset($this->namespaces[$ns[0]]))
                {
                  $this->namespaces[$ns[0]] = "ns" . $nsId;
                  $nsId++;
                }

                $rdf_part .= "        <" . $this->namespaces[$ns[0]] . ":" . $ns[1]
                  . " rdf:resource=\"".$this->xmlEncode($objectURI)."\" />\n";
              }
            }
          }

          $rdf_part .= "    </" . $this->namespaces[$ns1[0]] . ":" . $ns1[1] . ">\n";
        }

        $rdf_header = "<rdf:RDF ";

        foreach($this->namespaces as $ns => $prefix)
        {
          $rdf_header .= " xmlns:$prefix=\"$ns\"";
        }

        $rdf_header .= ">\n\n";

        $rdf_part = $rdf_header . $rdf_part;

        return ($rdf_part);
      break;

      case "text/xml":
      case "application/sparql-results+xml":
      case "application/sparql-results+json":
        return $this->pipeline_getResultset();
      break;
    }
  }

  /*!   @brief Non implemented method (only defined)
              
      \n
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function pipeline_serialize_reification() { }

  /*!   @brief Serialize the web service answer.
              
      \n
      
      @return returns the serialized content
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function ws_serialize()
  {
    if($this->conneg->getMime() == "application/sparql-results+xml"
      || $this->conneg->getMime() == "application/sparql-results+json"
      || $this->isDescribeQuery === TRUE
      || $this->isConstructQuery === TRUE)
    {
      return $this->sparqlContent;
    }
    else
    {    
      switch($this->conneg->getMime())
      {
        case "application/rdf+n3":
          $rdf_document = "";
          $rdf_document .= "@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .\n";
          $rdf_document .= "@prefix wsf: <http://purl.org/ontology/wsf#> .\n";

          $rdf_document .= $this->pipeline_serialize();

          $rdf_document .= $this->pipeline_serialize_reification();

          return $rdf_document;
        break;

        case "application/rdf+xml":
          $rdf_document = "";
          $rdf_document .= "<?xml version=\"1.0\"?>\n";

          $rdf_document .= $this->pipeline_serialize();

          $rdf_document .= $this->pipeline_serialize_reification();

          $rdf_document .= "</rdf:RDF>";

          return $rdf_document;
        break;

        case "application/json":
          $json_document = "";
          $json_document .= "{\n";
          $json_document .= $this->pipeline_serialize();
          $json_document .= "}";

          return ($json_document);
        break;

        default:
          return $this->pipeline_getResultset();
        break;
      }
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


  /*!   @brief Send the SPARQL query to the triple store of this WSF
              
      \n
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function process()
  {           
    // Make sure there was no conneg error prior to this process call
    if($this->conneg->getStatus() == 200)
    {
      $ch = curl_init();
      
      // Normalize the query to remove the return carriers and line feeds
      // This is performed to help matching the regular expressions patterns.
      $this->query = str_replace(array("\r", "\n"), " ", $this->query);
      
      // remove the possible starting "sparql"
      $this->query = preg_replace("/^[\s\t]*sparql[\s\t]*/Uim", "", $this->query);
      
      // Check if there is a prolog to this SPARQL query.
      
      // First check if there is a "base" declaration
      
      preg_match("/^[\s\t]*base[\s\t]*<.*>/Uim", $this->query, $matches, PREG_OFFSET_CAPTURE);
      
      $baseOffset = -1;
      if(count($matches) > 0)
      {
        $baseOffset = $matches[0][1] + strlen($matches[0][0]);
      }
      
      // Second check for all possible "prefix" clauses
      preg_match_all("/[\s\t]*prefix[\s\t]*.*:.*<.*>/Uim", $this->query, $matches, PREG_OFFSET_CAPTURE);       

      $lastPrefixOffset = -1;
      
      if(count($matches) > 0)
      {
        $lastPrefixOffset = $matches[0][count($matches[0]) - 1][1] + strlen($matches[0][count($matches[0]) - 1][0]);
      }
      
      $prologEndOffset = -1;
      
      if($lastPrefixOffset > -1)
      {
        $prologEndOffset = $lastPrefixOffset;
      }
      elseif($baseOffset > -1)
      {
        $prologEndOffset = $baseOffset;
      }

      $noPrologQuery = $this->query;
      if($prologEndOffset != -1)
      {
        $noPrologQuery = substr($this->query, $prologEndOffset);
      }
      
      // Now extract prefixes references
      $prefixes = array();
      preg_match_all("/[\s\t]*prefix[\s\t]*(.*):(.*)<(.*)>/Uim", $this->query, $matches, PREG_OFFSET_CAPTURE);       
      
      if(count($matches[0]) > 0)
      {
        for($i = 0; $i < count($matches[1]); $i++)
        {
          $p = str_replace(array(" ", " "), "", $matches[1][$i][0]).":".str_replace(array(" ", " "), "", $matches[2][$i][0]);
          $iri = $matches[3][$i][0];
          
          $prefixes[$p] = $iri;
        }
      }
      
      // Drop any SPARUL queries
      // Reference: http://www.w3.org/Submission/SPARQL-Update/
      if(preg_match_all("/^[\s\t]*modify[\s\t]*/Uim",$noPrologQuery , $matches) > 0 ||
         preg_match_all("/^[\s\t]*delete[\s\t]*/Uim", $noPrologQuery, $matches) > 0 ||
         preg_match_all("/^[\s\t]*insert[\s\t]*/Uim", $noPrologQuery, $matches) > 0 ||
         preg_match_all("/^[\s\t]*load[\s\t]*/Uim", $noPrologQuery, $matches) > 0 ||
         preg_match_all("/^[\s\t]*clear[\s\t]*/Uim", $noPrologQuery, $matches) > 0 ||
         preg_match_all("/^[\s\t]*create[\s\t]*/Uim", $noPrologQuery, $matches) > 0 ||
         preg_match_all("/^[\s\t]*drop[\s\t]*/Uim", $noPrologQuery, $matches) > 0)
      {
        $this->conneg->setStatus(400);
        $this->conneg->setStatusMsg("Bad Request");
        $this->conneg->setStatusMsgExt($this->errorMessenger->_203->name);
        $this->conneg->setError($this->errorMessenger->_203->id, $this->errorMessenger->ws,
          $this->errorMessenger->_203->name, $this->errorMessenger->_203->description, "",
          $this->errorMessenger->_203->level);

        return;               
      }

      // Detect any CONSTRUCT clause
      $this->isConstructQuery = FALSE;
      if(preg_match_all("/^[\s\t]*construct[\s\t]*/Uim", $noPrologQuery, $matches) > 0)
      {
        $this->isConstructQuery = TRUE;
        /*
        $this->conneg->setStatus(400);
        $this->conneg->setStatusMsg("Bad Request");
        $this->conneg->setStatusMsgExt($this->errorMessenger->_204->name);
        $this->conneg->setError($this->errorMessenger->_204->id, $this->errorMessenger->ws,
          $this->errorMessenger->_204->name, $this->errorMessenger->_204->description, "",
          $this->errorMessenger->_204->level);

        return;               
        */
      }
      
      // Drop any SPARQL query with a GRAPH clause which are not bound by one, or a series, of FROM NAMED clauses

      if((preg_match_all("/[\s\t]*graph[\s\t]*</Uim", $noPrologQuery, $matches) > 0 ||
          preg_match_all("/[\s\t]*graph[\s\t]*\?/Uim", $noPrologQuery, $matches) > 0 ||
          preg_match_all("/[\s\t]*graph[\s\t]*\$/Uim", $noPrologQuery, $matches) > 0 ||
          preg_match_all("/[\s\t]*graph[\s\t]*[a-zA-Z0-9\-_]*:/Uim", $noPrologQuery, $matches) > 0) &&
         (preg_match_all("/([\s\t]*from[\s\t]*named[\s\t]*<(.*)>[\s\t]*)/Uim", $noPrologQuery, $matches) <= 0 &&
          preg_match_all("/[\s\t]*(from[\s\t]*named)[\s\t]*([^\s\t<]*):(.*)[\s\t]*/Uim", $noPrologQuery, $matches) <= 0))
      {
        $this->conneg->setStatus(400);
        $this->conneg->setStatusMsg("Bad Request");
        $this->conneg->setStatusMsgExt($this->errorMessenger->_205->name);
        $this->conneg->setError($this->errorMessenger->_205->id, $this->errorMessenger->ws,
          $this->errorMessenger->_205->name, $this->errorMessenger->_205->description, "",
          $this->errorMessenger->_205->level);

        return;               
      }
      
      $graphs = array();   
      
      // Validate DESCRIBE query.
      // The only thing we have to check here, is to get the graph IRI if the DESCRIBE is immediately using
      // IRIRef clause. Possibilities are:
      // "DESCRIBE <test>" -- IRI_REF
      // "DESCRIBE a:" -- PrefixedName
      
      $this->isDescribeQuery = FALSE;
      if(preg_match("/^[\s\t]*describe[\s\t]*/Uim", $noPrologQuery, $matches) > 0)
      {
        $this->isDescribeQuery = TRUE;
      }    
      
      preg_match_all("/^[\s\t]*describe[\s\t]*<(.*)>/Uim", $noPrologQuery, $matches);  
      
      if(count($matches[0]) > 0)
      {
        array_push($graphs, $matches[1][0]);    
      }
      
      preg_match_all("/^[\s\t]*describe[\s\t]*([^<\s\t]*):(.*)[\s\t]*/Uim", $noPrologQuery, $matches);
      
      if(count($matches[0]) > 0)
      {
        for($i = 0; $i < count($matches[0]); $i++)
        {
          $p = $matches[1][$i].":";
          
          if(isset($prefixes[$p]))
          {
            $d = $prefixes[$p].$matches[2][$i];
            array_push($graphs, $d);
          }
        }
      }       
      
      
      // Get all the "from" and "from named" clauses so that we validate if the user has access to them.

      // Check for the clauses that uses direct IRI_REF
      preg_match_all("/([\s\t]*from[\s\t]*<(.*)>[\s\t]*)/Uim", $noPrologQuery, $matches);

      foreach($matches[2] as $match)
      {
        array_push($graphs, $match);
      }

      preg_match_all("/([\s\t]*from[\s\t]*named[\s\t]*<(.*)>[\s\t]*)/Uim", $noPrologQuery, $matches);

      foreach($matches[2] as $match)
      {
        array_push($graphs, $match);
      }
      
      // Check for the clauses that uses PrefixedName
      
      preg_match_all("/[\s\t]*(from|from[\s\t]*named)[\s\t]*([^\s\t<]*):(.*)[\s\t]*/Uim", $noPrologQuery, $matches);

      if(count($matches[0]) > 0)
      {
        for($i = 0; $i < count($matches[0]); $i++)
        {
          $p = $matches[2][$i].":";
          
          if(isset($prefixes[$p]))
          {
            $d = $prefixes[$p].$matches[3][$i];
            array_push($graphs, $d);
          }
        }
      }   
      
      
      if($this->dataset == "" && count($graphs) <= 0)
      {
        $this->conneg->setStatus(400);
        $this->conneg->setStatusMsg("Bad Request");
        $this->conneg->setStatusMsgExt($this->errorMessenger->_201->name);
        $this->conneg->setError($this->errorMessenger->_201->id, $this->errorMessenger->ws,
          $this->errorMessenger->_201->name, $this->errorMessenger->_201->description, "",
          $this->errorMessenger->_201->level);

        return;
      }      

      // Validate all graphs of the query. If one of the graph is not accessible to the user, we just return
      // and error for this SPARQL query.
      foreach($graphs as $graph)
      {
        if(substr($graph, strlen($graph) - 12, 12) == "reification/")
        {
          $graph = substr($graph, 0, strlen($graph) - 12);
        }

        $ws_av = new AuthValidator($this->registered_ip, $graph, $this->uri);

        $ws_av->pipeline_conneg("*/*", $this->conneg->getAcceptCharset(), $this->conneg->getAcceptEncoding(),
          $this->conneg->getAcceptLanguage());

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
      
      /*
        if registered_ip != requester_ip, this means that the query is sent by a registered system
        on the behalf of someone else. In this case, we want to make sure that that system 
        (the one that send the actual query) has access to the same datasets. Otherwise, it means that
        it tries to personificate that registered_ip user.
        
        Validate all graphs of the query. If one of the graph is not accessible to the syste, we just return
        and error for this SPARQL query.  
      */
      foreach($graphs as $graph)
      {
        if(substr($graph, strlen($graph) - 12, 12) == "reification/")
        {
          $graph = substr($graph, 0, strlen($graph) - 12);
        }

        $ws_av = new AuthValidator($this->requester_ip, $graph, $this->uri);

        $ws_av->pipeline_conneg("*/*", $this->conneg->getAcceptCharset(), $this->conneg->getAcceptEncoding(),
          $this->conneg->getAcceptLanguage());

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

      // Determine the query format
      $queryFormat = "";

      if($this->conneg->getMime() == "application/sparql-results+json" || 
         $this->conneg->getMime() == "application/sparql-results+xml" || 
         $this->conneg->getMime() == "text/html" ||
         $this->isDescribeQuery === TRUE ||
         $this->isConstructQuery === TRUE)
      {
        $queryFormat = $this->conneg->getMime();
      }
      elseif($this->conneg->getMime() == "text/xml" || $this->conneg->getMime() == "application/json"
        || $this->conneg->getMime() == "application/rdf+xml" || $this->conneg->getMime() == "application/rdf+n3")
      {
        $queryFormat = "application/sparql-results+xml";
      }      
      
      
      
      // Add a limit to the query

      // Disable limits and offset for now until we figure out what to do (not limit on triples, but resources)
      //      $this->query .= " limit ".$this->limit." offset ".$this->offset;

      curl_setopt($ch, CURLOPT_URL,
        $this->db_host . ":" . $this->triplestore_port . "/sparql?default-graph-uri=" . urlencode($this->dataset) . "&query="
        . urlencode($this->query) . "&format=" . urlencode($queryFormat));

      //curl_setopt($ch, CURLOPT_HTTPHEADER, array( "Accept: " . $queryFormat ));
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_HEADER, TRUE);

      $xml_data = curl_exec($ch);

      $header = substr($xml_data, 0, strpos($xml_data, "\r\n\r\n"));

      $data =
        substr($xml_data, strpos($xml_data, "\r\n\r\n") + 4, strlen($xml_data) - (strpos($xml_data, "\r\n\r\n") - 4));

      curl_close($ch);

      // check returned message

      $httpMsgNum = substr($header, 9, 3);
      $httpMsg = substr($header, 13, strpos($header, "\r\n") - 13);

      if($httpMsgNum == "200")
      {
        $this->sparqlContent = $data;
      }
      else
      {
        $this->conneg->setStatus($httpMsgNum);
        $this->conneg->setStatusMsg($httpMsg);
        $this->conneg->setStatusMsgExt($this->errorMessenger->_300->name);
        $this->conneg->setError($this->errorMessenger->_300->id, $this->errorMessenger->ws,
          $this->errorMessenger->_300 > name, $this->errorMessenger->_300->description, $data,
          $this->errorMessenger->_300->level);

        $this->sparqlContent = "";
        return;
      }

      // If a DESCRIBE query as been requested by the user, then we simply returns what is returned by
      // the triple store. We don't have any convertion to do here.
      if($this->isDescribeQuery === TRUE)
      {
         return;
      }

      // If a CONSTRUCT query as been requested by the user, then we simply returns what is returned by
      // the triple store. We don't have any convertion to do here.
      if($this->isConstructQuery === TRUE)
      {
         return;
      }
      
      if($this->conneg->getMime() == "text/xml" || $this->conneg->getMime() == "application/rdf+n3"
        || $this->conneg->getMime() == "application/rdf+xml" || $this->conneg->getMime() == "application/json")
      {
        // Read the XML file and populate the recordInstances variables

        $xml = $this->xml2ary($this->sparqlContent);

        if(isset($xml["sparql"]["_c"]["results"]["_c"]["result"]))
        {
          foreach($xml["sparql"]["_c"]["results"]["_c"]["result"] as $result)
          {
            $s = "";
            $p = "";
            $o = "";

            foreach($result["_c"]["binding"] as $binding)
            {
              $boundVariable = $binding["_a"]["name"];

              $keys = array_keys($binding["_c"]);

              $boundType = $keys[0];
              $boundValue = $binding["_c"][$boundType]["_v"];

              switch($boundVariable)
              {
                case "s":
                  $s = $boundValue;
                break;

                case "p":
                  $p = $boundValue;
                break;

                case "o":
                  $o = $boundValue;
                break;
              }
            }

            // process URI
            if($boundType == "uri")
            {
              if(!isset($this->instanceRecordsObjectResource[$s][$p]))
              {
                $this->instanceRecordsObjectResource[$s][$p] = array( $o );
              }
              else
              {
                array_push($this->instanceRecordsObjectResource[$s][$p], $o);
              }
            }

            // Process Literal
            if($boundType == "literal")
            {
              if(!isset($this->instanceRecordsObjectLiteral[$s][$p]))
              {
                $this->instanceRecordsObjectLiteral[$s][$p] = array( $o );
              }
              else
              {
                array_push($this->instanceRecordsObjectLiteral[$s][$p], $o);
              }
            }
            
            // Process BNode
            if($boundType == "bnode")
            {
              if(!isset($this->instanceRecordsObjectResource[$s][$p]))
              {
                $this->instanceRecordsObjectResource[$s][$p] = array( $o );
              }
              else
              {
                array_push($this->instanceRecordsObjectResource[$s][$p], $o);
              }
            }
          }
        }

        if(count($this->instanceRecordsObjectResource) <= 0)
        {
          $this->conneg->setStatus(400);
          $this->conneg->setStatusMsg("Bad Request");
          $this->conneg->setStatusMsgExt($this->errorMessenger->_301->name);
          $this->conneg->setError($this->errorMessenger->_301->id, $this->errorMessenger->ws,
            $this->errorMessenger->_301->name, $this->errorMessenger->_301->description, "",
            $this->errorMessenger->_301->level);
        }
      }
    }
  }

  /*
      Working with XML. Usage: 
      $xml=xml2ary(file_get_contents('1.xml'));
      $link=&$xml['ddd']['_c'];
      $link['twomore']=$link['onemore'];
      // ins2ary(); // dot not insert a link, and arrays with links inside!
      echo ary2xml($xml);
      
      from: http://mysrc.blogspot.com/2007/02/php-xml-to-array-and-backwards.html
  */

  // XML to Array
  private function xml2ary(&$string)
  {
    $parser = xml_parser_create();
    xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
    xml_parse_into_struct($parser, $string, $vals, $index);
    xml_parser_free($parser);

    $mnary = array();
    $ary = &$mnary;

    foreach($vals as $r)
    {
      $t = $r['tag'];

      if($r['type'] == 'open')
      {
        if(isset($ary[$t]))
        {
          if(isset($ary[$t][0]))$ary[$t][] = array();
          else $ary[$t] = array ($ary[$t], array());
          $cv = &$ary[$t][count($ary[$t]) - 1];
        }
        else $cv = &$ary[$t];

        if(isset($r['attributes']))
        {
          foreach($r['attributes'] as $k => $v)$cv['_a'][$k] = $v;
        }
        $cv['_c'] = array();
        $cv['_c']['_p'] = &$ary;
        $ary = &$cv['_c'];
      }
      elseif($r['type'] == 'complete')
      {
        if(isset($ary[$t]))
        { // same as open
          if(isset($ary[$t][0]))$ary[$t][] = array();
          else $ary[$t] = array ($ary[$t], array());
          $cv = &$ary[$t][count($ary[$t]) - 1];
        }
        else $cv = &$ary[$t];

        if(isset($r['attributes']))
        {
          foreach($r['attributes'] as $k => $v)$cv['_a'][$k] = $v;
        }
        $cv['_v'] = (isset($r['value']) ? $r['value'] : '');
      }
      elseif($r['type'] == 'close')
      {
        $ary = &$ary['_p'];
      }
    }

    $this->_del_p($mnary);
    return $mnary;
  }

  // _Internal: Remove recursion in result array
  private function _del_p(&$ary)
  {
    foreach($ary as $k => $v)
    {
      if($k === '_p')unset($ary[$k]);
      elseif(is_array($ary[$k]))$this->_del_p($ary[$k]);
    }
  }

  // Array to XML
  private function ary2xml($cary, $d = 0, $forcetag = '')
  {
    $res = array();

    foreach($cary as $tag => $r)
    {
      if(isset($r[0]))
      {
        $res[] = ary2xml($r, $d, $tag);
      }
      else
      {
        if($forcetag)$tag = $forcetag;
        $sp = str_repeat("\t", $d);
        $res[] = "$sp<$tag";

        if(isset($r['_a']))
        {
          foreach($r['_a'] as $at => $av)$res[] = " $at=\"$av\"";
        }
        $res[] = ">" . ((isset($r['_c'])) ? "\n" : '');

        if(isset($r['_c']))$res[] = ary2xml($r['_c'], $d + 1);
        elseif(isset($r['_v']))$res[] = $r['_v'];
        $res[] = (isset($r['_c']) ? $sp : '') . "</$tag>\n";
      }
    }
    return implode('', $res);
  }

  // Insert element into array
  private function ins2ary(&$ary, $element, $pos)
  {
    $ar1 = array_slice($ary, 0, $pos);
    $ar1[] = $element;
    $ary = array_merge($ar1, array_slice($ary, $pos));
  }
}


//@}

?>