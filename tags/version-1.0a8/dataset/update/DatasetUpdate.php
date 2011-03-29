<?php

/*! @defgroup WsDataset Dataset Management Web Service  */
//@{

/*! @file \ws\dataset\update\DatasetUpdate.php
   @brief Update a new graph for this dataset & indexation of its description
  
   \n\n
 
   @author Frederick Giasson, Structured Dynamics LLC.

   \n\n\n
 */


/*!   @brief Dataset Update Web Service. It updates description of dataset of the structWSF instance.
            
    \n
    
    @author Frederick Giasson, Structured Dynamics LLC.
  
    \n\n\n
*/

class DatasetUpdate extends WebService
{
  /*! @brief Database connection */
  private $db;

  /*! @brief Conneg object that manage the content negotiation capabilities of the web service */
  private $conneg;

  /*! @brief URL where the DTD of the XML document can be located on the Web */
  private $dtdURL;

  /*! @brief IP of the requester */
  private $requester_ip = "";

  /*! @brief URI of the dataset to update */
  private $datasetUri = "";

  /*! @brief Title of the dataset */
  private $datasetTitle = "";

  /*! @brief Description of the dataset */
  private $description = "";

  /*! @brief List of contributors to the dataset */
  private $contributors = "";

  /*! @brief Last modification date of the dataset */
  private $modified = "";

  /*! @brief Supported serialization mime types by this Web service */
  public static $supportedSerializations =
    array ("application/json", "application/rdf+xml", "application/rdf+n3", "application/*", "text/xml", "text/*",
      "*/*");

  /*! @brief Error messages of this web service */
  private $errorMessenger =
    '{
                        "ws": "/ws/dataset/update/",
                        "_200": {
                          "id": "WS-DATASET-UPDATE-200",
                          "level": "Warning",
                          "name": "No unique identifier specified for this dataset",
                          "description": "No URI defined for this new dataset"
                        },
                        "_201": {
                          "id": "WS-DATASET-UPDATE-201",
                          "level": "Fatal",
                          "name": "Can\'t check if the dataset is existing",
                          "description": "An error occured when we tried to check if the dataset was existing"
                        },
                        "_202": {
                          "id": "WS-DATASET-UPDATE-202",
                          "level": "Warning",
                          "name": "This dataset doesn\'t exist in this WSF",
                          "description": "The target dataset is not existing in the web service framework"
                        },
                        "_300": {
                          "id": "WS-DATASET-UPDATE-300",
                          "level": "Fatal",
                          "name": "Can\'t update the title of the dataset in the triple store",
                          "description": "An error occured when we tried to update the title of the dataset in the triple store"
                        },
                        "_301": {
                          "id": "WS-DATASET-UPDATE-301",
                          "level": "Fatal",
                          "name": "Can\'t update the description of the dataset in the triple store",
                          "description": "An error occured when we tried to update the description of the dataset in the triple store"
                        },
                        "_302": {
                          "id": "WS-DATASET-UPDATE-302",
                          "level": "Fatal",
                          "name": "Can\'t update the last modification date of the dataset in the triple store",
                          "description": "An error occured when we tried to update the last modification date of the dataset in the triple store"
                        },
                        "_303": {
                          "id": "WS-DATASET-UPDATE-303",
                          "level": "Fatal",
                          "name": "Can\'t update the contributors of the dataset in the triple store",
                          "description": "An error occured when we tried to update the contributors of the dataset in the triple store"
                        }  
                      }';


  /*!   @brief Constructor
       @details   Initialize the Auth Web Service
        
      @param[in] $uri Unique identifier used to refer to the dataset to update
      @param[in] $title (optional).  Title of the dataset to update
      @param[in] $description (optional).Description of the dataset to update
      @param[in] $contributors (optional).List of contributor URIs seperated by ";"
      @param[in] $modified (optional).Date of the modification of the dataset
      @param[in] $registered_ip Target IP address registered in the WSF  
      @param[in] $requester_ip IP address of the requester
              
      \n
      
      @return returns NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  function __construct($uri, $title, $description, $contributors, $modified, $registered_ip, $requester_ip)
  {
    parent::__construct();

    $this->db = new DB_Virtuoso($this->db_username, $this->db_password, $this->db_dsn, $this->db_host);

    $this->datasetUri = $uri;
    $this->datasetTitle = $title;
    $this->description = $description;
    $this->contributors = $contributors;
    $this->modified = $modified;
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

    $this->uri = $this->wsf_base_url . "/wsf/ws/dataset/update/";
    $this->title = "Dataset Update Web Service";
    $this->crud_usage = new CrudUsage(FALSE, FALSE, TRUE, FALSE);
    $this->endpoint = $this->wsf_base_url . "/ws/dataset/update/";

    $this->dtdURL = "dataset/datasetUpdate.dtd";

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

  /*! @brief Validate a query to this web service
      
      @details If a user wants to update information about a dataset on a given structWSF web service endpoint,
      he has to have access to the "http://.../wsf/datasets/" graph with Update privileges, or to have
      Update privileges on the dataset URI itself. If the users doesn't have these permissions, 
      then he won't be able to update the description of the dataset on that instance.
      
      By default, the administrators, and the creator of the dataset, have such an access on a structWSF instance. 
      However a system administrator can choose to make the "http://.../wsf/datasets/" world updatable,
      which would mean that anybody could update information about the datasets on the instance.      
              
      \n
      
      @return TRUE if valid; FALSE otherwise
    
      @note This function is not used by the authentication validator web service
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  protected function validateQuery()
  {
    // Check if the requester has access to the main "http://.../wsf/datasets/" graph.
    $ws_av = new AuthValidator($this->requester_ip, $this->wsf_graph . "datasets/", $this->uri);

    $ws_av->pipeline_conneg($this->conneg->getAccept(), $this->conneg->getAcceptCharset(),
      $this->conneg->getAcceptEncoding(), $this->conneg->getAcceptLanguage());

    $ws_av->process();

    if($ws_av->pipeline_getResponseHeaderStatus() != 200)
    {
      // If he doesn't, then check if he has access to the dataset itself
      $ws_av2 = new AuthValidator($this->requester_ip, $this->datasetUri, $this->uri);

      $ws_av2->pipeline_conneg($this->conneg->getAccept(), $this->conneg->getAcceptCharset(),
        $this->conneg->getAcceptEncoding(), $this->conneg->getAcceptLanguage());

      $ws_av2->process();

      if($ws_av2->pipeline_getResponseHeaderStatus() != 200)
      {
        $this->conneg->setStatus($ws_av2->pipeline_getResponseHeaderStatus());
        $this->conneg->setStatusMsg($ws_av2->pipeline_getResponseHeaderStatusMsg());
        $this->conneg->setStatusMsgExt($ws_av2->pipeline_getResponseHeaderStatusMsgExt());
        $this->conneg->setError($ws_av2->pipeline_getError()->id, $ws_av2->pipeline_getError()->webservice,
          $ws_av2->pipeline_getError()->name, $ws_av2->pipeline_getError()->description,
          $ws_av2->pipeline_getError()->debugInfo, $ws_av2->pipeline_getError()->level);

        return;
      }
    }
    
    // If the system send a query on the behalf of another user, we validate that other user as well
    if($this->registered_ip != $this->requester_ip)
    {
      // Check if the requester has access to the main "http://.../wsf/datasets/" graph.
      $ws_av = new AuthValidator($this->registered_ip, $this->wsf_graph . "datasets/", $this->uri);

      $ws_av->pipeline_conneg($this->conneg->getAccept(), $this->conneg->getAcceptCharset(),
        $this->conneg->getAcceptEncoding(), $this->conneg->getAcceptLanguage());

      $ws_av->process();

      if($ws_av->pipeline_getResponseHeaderStatus() != 200)
      {
        // If he doesn't, then check if he has access to the dataset itself
        $ws_av2 = new AuthValidator($this->registered_ip, $this->datasetUri, $this->uri);

        $ws_av2->pipeline_conneg($this->conneg->getAccept(), $this->conneg->getAcceptCharset(),
          $this->conneg->getAcceptEncoding(), $this->conneg->getAcceptLanguage());

        $ws_av2->process();

        if($ws_av2->pipeline_getResponseHeaderStatus() != 200)
        {
          $this->conneg->setStatus($ws_av2->pipeline_getResponseHeaderStatus());
          $this->conneg->setStatusMsg($ws_av2->pipeline_getResponseHeaderStatusMsg());
          $this->conneg->setStatusMsgExt($ws_av2->pipeline_getResponseHeaderStatusMsgExt());
          $this->conneg->setError($ws_av2->pipeline_getError()->id, $ws_av2->pipeline_getError()->webservice,
            $ws_av2->pipeline_getError()->name, $ws_av2->pipeline_getError()->description,
            $ws_av2->pipeline_getError()->debugInfo, $ws_av2->pipeline_getError()->level);

          return;
        }
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
  public function pipeline_getResultset() { return ""; }

  /*!   @brief Inject the DOCType in a XML document
              
      \n
      
      @param[in] $xmlDoc The XML document where to inject the doctype
      
      @return a XML document with a doctype
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function injectDoctype($xmlDoc) { return ""; }

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
      new Conneg($accept, $accept_charset, $accept_encoding, $accept_language, DatasetUpdate::$supportedSerializations);

    // Validate query
    $this->validateQuery();

    // If the query is still valid
    if($this->conneg->getStatus() == 200)
    {
      // Check for errors
      if($this->datasetUri == "")
      {
        $this->conneg->setStatus(400);
        $this->conneg->setStatusMsg("Bad Request");
        $this->conneg->setStatusMsgExt($this->errorMessenger->_200->name);
        $this->conneg->setError($this->errorMessenger->_200->id, $this->errorMessenger->ws,
          $this->errorMessenger->_200->name, $this->errorMessenger->_200->description, "",
          $this->errorMessenger->_200->level);

        return;
      }

      // Check if the dataset is existing
      $query .= "  select ?dataset 
                from <" . $this->wsf_graph . "datasets/>
                where
                {
                  <$this->datasetUri> a ?dataset .
                }";

      $resultset = @$this->db->query($this->db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), " ", $query),
        array( "dataset" ), FALSE));

      if(odbc_error())
      {
        $this->conneg->setStatus(500);
        $this->conneg->setStatusMsg("Internal Error");
        $this->conneg->setStatusMsgExt($this->errorMessenger->_201->name);
        $this->conneg->setError($this->errorMessenger->_201->id, $this->errorMessenger->ws,
          $this->errorMessenger->_201->name, $this->errorMessenger->_201->description, odbc_errormsg(),
          $this->errorMessenger->_201->level);

        return;
      }
      elseif(odbc_fetch_row($resultset) === FALSE)
      {
        $this->conneg->setStatus(400);
        $this->conneg->setStatusMsg("Bad Request");
        $this->conneg->setStatusMsgExt($this->errorMessenger->_202->name);
        $this->conneg->setError($this->errorMessenger->_202->id, $this->errorMessenger->ws,
          $this->errorMessenger->_202->name, $this->errorMessenger->_202->description, "",
          $this->errorMessenger->_202->level);

        unset($resultset);
      }

      unset($resultset);
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
  public function pipeline_serialize() { return ""; }

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
  public function ws_serialize() { return ""; }

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


  /*!   @brief Update information about a dataset of the WSF
              
      \n
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function process()
  {
    // Make sure there was no conneg error prior to this process call
    if($this->conneg->getStatus() == 200)
    {
/*    
      $query = "modify <".$this->wsf_graph."datasets/>
              delete
              { 
                ".($this->datasetTitle != "" ? "<$this->datasetUri> <http://purl.org/dc/terms/title> ?datasetTitle ." : "")."
                ".($this->description != "" ? "<$this->datasetUri> <http://purl.org/dc/terms/description> ?description ." : "")."
                ".($this->modified != "" ? "<$this->datasetUri> <http://purl.org/dc/terms/modified> ?modified ." : "")."
                ".(count($this->contributors) > 0 && isset($contributor[0]) ? "<$this->datasetUri> <http://purl.org/dc/terms/contributor> ?contributors ." : "")."
              }
              insert
              {
                ".($this->datasetTitle != "" ? "<$this->datasetUri> <http://purl.org/dc/terms/title> \"\"\"$this->datasetTitle\"\"\" ." : "")."
                ".($this->description != "" ? "<$this->datasetUri> <http://purl.org/dc/terms/description> \"\"\"$this->description\"\"\" ." : "")."
                ".($this->modified != "" ? "<$this->datasetUri> <http://purl.org/dc/terms/modified> \"\"\"$this->modified\"\"\" ." : "")."";
                
      foreach($this->contributors as $contributor)
      {
        $query .=   ($this->contributor != "" ? "<$this->datasetUri> <http://purl.org/dc/terms/contributor> <$contributor> ." : "");
      }                
                
      $query .= "}                  
              where
              {
                graph <".$this->wsf_graph."datasets/>
                {
                  <$this->datasetUri> a <http://rdfs.org/ns/void#Dataset> .
                  ".($this->datasetTitle != "" ? "<$this->datasetUri> <http://purl.org/dc/terms/title> ?datasetTitle ." : "")."
                  ".($this->description != "" ? "<$this->datasetUri> <http://purl.org/dc/terms/description> ?description ." : "")."
                  ".($this->modified != "" ? "<$this->datasetUri> <http://purl.org/dc/terms/modified> ?modified ." : "")."
                  ".(count($this->contributors) > 0 ? "<$this->datasetUri> <http://purl.org/dc/terms/contributor> ?contributors ." : "")."
                }
              }";
*/

// Note: here we can't create a single SPARUL query to update everything because if one of the clause is not existing in the "delete" pattern,
//          then nothing will be updated. Also, the problem come from the fact that "OPTIONAL" clauses only happen at the level of the "where" clause
//          and can't be used in the "delete" clause.

// Updating the title if it exists in the description
      if($this->datasetTitle != "")
      {

        $query = "delete from <" . $this->wsf_graph . "datasets/>
                { 
                  <$this->datasetUri> <http://purl.org/dc/terms/title> ?datasetTitle .
                }
                where
                {
                  graph <" . $this->wsf_graph . "datasets/>
                  {
                    <$this->datasetUri> a <http://rdfs.org/ns/void#Dataset> .
                    <$this->datasetUri> <http://purl.org/dc/terms/title> ?datasetTitle .
                  }
                }
                " . ($this->datasetTitle != "-delete-" ? "
                insert into <" . $this->wsf_graph . "datasets/>
                {
                  <$this->datasetUri> <http://purl.org/dc/terms/title> \"\"\"" . str_replace("'", "\'", $this->datasetTitle) . "\"\"\" .
                }" : "");
      }

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

      // Updating the description if it exists in the description
      if($this->description != "")
      {
        $query = "delete from <" . $this->wsf_graph . "datasets/>
                { 
                  <$this->datasetUri> <http://purl.org/dc/terms/description> ?description .
                }
                where
                {
                  graph <" . $this->wsf_graph . "datasets/>
                  {
                    <$this->datasetUri> a <http://rdfs.org/ns/void#Dataset> .
                    <$this->datasetUri> <http://purl.org/dc/terms/description> ?description .
                  }
                }
                " . ($this->description != "-delete-" ? "
                insert into <" . $this->wsf_graph . "datasets/>
                {
                  <$this->datasetUri> <http://purl.org/dc/terms/description> \"\"\"" . str_replace("'", "\'", $this->description) . "\"\"\" .
                }" : "");
      }

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

      // Updating the modification date if it exists in the description
      if($this->modified != "")
      {
        $query = "delete from <" . $this->wsf_graph . "datasets/>
                { 
                  <$this->datasetUri> <http://purl.org/dc/terms/modified> ?modified .
                }
                where
                {
                  graph <" . $this->wsf_graph . "datasets/>
                  {
                    <$this->datasetUri> a <http://rdfs.org/ns/void#Dataset> .
                    <$this->datasetUri> <http://purl.org/dc/terms/modified> ?modified .
                  }
                }
                " . ($this->modified != "-delete-" ? "
                insert into <" . $this->wsf_graph . "datasets/>
                {
                  <$this->datasetUri> <http://purl.org/dc/terms/modified> \"\"\"" . str_replace("'", "\'", $this->modified) . "\"\"\" .
                }" : "");
      }

      @$this->db->query($this->db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), " ", $query), array(),
        FALSE));

      if(odbc_error())
      {
        $this->conneg->setStatus(500);
        $this->conneg->setStatusMsg("Internal Error");
        $this->conneg->setStatusMsgExt($this->errorMessenger->_302->name);
        $this->conneg->setError($this->errorMessenger->_302->id, $this->errorMessenger->ws,
          $this->errorMessenger->_302->name, $this->errorMessenger->_302->description, odbc_errormsg(),
          $this->errorMessenger->_302->level);

        return;
      }

      // Updating the contributors list if it exists in the description
      if($this->contributors != "")
      {
        $query = "delete from <" . $this->wsf_graph . "datasets/>
                { 
                  <$this->datasetUri> <http://purl.org/dc/terms/contributor> ?contributor .
                }
                where
                {
                  graph <"
          . $this->wsf_graph
          . "datasets/>
                  {
                    <$this->datasetUri> a <http://rdfs.org/ns/void#Dataset> .
                    <$this->datasetUri> <http://purl.org/dc/terms/contributor> ?contributor .
                  }
                }";

        if($this->contributors != "-delete-")
        {
          $cons = array();

          if(strpos($this->contributors, ";") !== FALSE)
          {
            $cons = explode(";", $this->contributors);
          }

          $query .= "insert into <" . $this->wsf_graph . "datasets/>
                  {";

          foreach($cons as $contributor)
          {
            $query .= "<$this->datasetUri> <http://purl.org/dc/terms/contributor> <$contributor> .";
          }

          if(count($cons) == 0)
          {
            $query .= "<$this->datasetUri> <http://purl.org/dc/terms/contributor> <$this->contributors> .";
          }
          $query .= "}";
        }
      }

      @$this->db->query($this->db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), " ", $query), array(),
        FALSE));

      if(odbc_error())
      {
        $this->conneg->setStatus(500);
        $this->conneg->setStatusMsg("Internal Error");
        $this->conneg->setStatusMsgExt($this->errorMessenger->_303->name);
        $this->conneg->setError($this->errorMessenger->_303->id, $this->errorMessenger->ws,
          $this->errorMessenger->_303->name, $this->errorMessenger->_303->description, odbc_errormsg(),
          $this->errorMessenger->_303->level);

        return;
      }
    }
  }
}

//@}

?>