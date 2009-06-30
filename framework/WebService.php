<?php

/*! @defgroup WsFramework Framework for the Web Services */
//@{ 

/*! @file \ws\framework\WebService.php
	 @brief An abstract atomic web service class
	
	 \n\n
 
	 @author Frederick Giasson, Structured Dynamics LLC.
	 
	 \n\n\n
*/

/*!	 @brief A Web Service abstract class
		 @details This abstract class is used to define a web service that can interact with external webservices, or web services in a pipeline (compound), in a RESTful way.

		\n

		@todo Creating a DTD for creating structured error reports
		@todo Extension of the web service framework to enable the integration of a caching system (like memcached)
						
		\n

		@author Frederick Giasson, Structured Dynamics LLC.

		\n\n\n
*/

abstract class WebService
{
	/*! @brief Database user name */
	protected static $db_username = "username";

	/*! @brief Database password */
	protected static $db_password = "password";

	/*! @brief Database DSN connection */
	protected static $db_dsn = "dsn";

	/*! @brief Database host */
	protected static $db_host = "localhost";

	/*! @brief DTD URL of the web service */
	protected static $dtdBaseURL = "http://domain.com/ws/dtd/";

	/*! @brief The graph where the Web Services Framework description has been indexed */
	protected static $wsf_graph = "http://domain.com/wsf/";	

	/*! @brief Base URL of the WSF */
	protected static $wsf_base_url = "http://domain.com";	

	/*! @brief Local server path of the WSF files */
	protected static $wsf_base_path = "/.../ws";	

	/*! @brief Local server path of the WSF files */
	protected static $wsf_local_ip = "127.0.0.1";	

	/*! @brief The core to use for Solr; "" for no core */
	protected static $wsf_solr_core = "";	

	/*! @brief The URI of the Authentication Registrar web service */
	protected $uri;	
	
	/*! @brief The Title of the Authentication Registrar web service */
	protected $title;	
	
	/*! @brief The CRUD usage of the Authentication Registrar web service */
	protected $crud_usage;	
	
	/*! @brief The endpoint of the Authentication Registrar web service */
	protected $endpoint;		
	
	function __construct(){}
	function __destruct(){}
	
	
	/*!	 @brief does the content negotiation for the queries that come from the Web (when this class acts as a Web Service)
							
			\n
			
			@param[in] $accept Accepted mime types (HTTP header)
			
			@param[in] $accept_charset Accepted charsets (HTTP header)
			
			@param[in] $accept_encoding Accepted encodings (HTTP header)
	
			@param[in] $accept_language Accepted languages (HTTP header)

			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/		
	abstract public function ws_conneg($accept, $accept_charset, $accept_encoding, $accept_language);

	/*!	 @brief Output the content generated by the class in some serialization format
							
			\n
			
			@return returns the serialized content
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/		
	abstract public function ws_serialize();
	
	/*!	 @brief Sends the respond to the user. The $content should come from ws_serialize() to be valid according to the conneg with the user.
							
			\n
			
			@param[in] $content The content (body) of the response.
			
			@return NULL
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/			
	abstract public function ws_respond($content);

	/*!	 @brief Process the functionality of the web service
							
			\n
			
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/			
	abstract public function process();


	/*!	 @brief Propagate the conneg to the nodes that belong to the current pipeline of web services.
							
			\n
			
			@param[in] $accept Accepted mime types (HTTP header)
			
			@param[in] $accept_charset Accepted charsets (HTTP header)
			
			@param[in] $accept_encoding Accepted encodings (HTTP header)
	
			@param[in] $accept_language Accepted languages (HTTP header)
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/	
	abstract public function pipeline_conneg($accept, $accept_charset, $accept_encoding, $accept_language);

	/*!	 @brief Returns the response HTTP header status
							
			\n
			
			@return returns the response HTTP header status
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/			
	abstract public function pipeline_getResponseHeaderStatus();
	
	/*!	 @brief Returns the response HTTP header status message
							
			\n
			
			@return returns the response HTTP header status message
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/			
	abstract public function pipeline_getResponseHeaderStatusMsg();
	
	/*!	 @brief Returns the response HTTP header status message extension
							
			\n
			
			@return returns the response HTTP header status message extension
		
			@note The extension of a HTTP status message is
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/		
	abstract public function pipeline_getResponseHeaderStatusMsgExt();

	
	/*!	@brief Create a resultset in a pipelined mode based on the processed information by the Web service.
							
			\n
			
			@return a resultset XML document
			
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/		
	abstract public function pipeline_getResultset();

	/*!	 @brief Serialize content into different serialization formats
							
			\n
			
			@return returns the serialized content
			
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/				
	abstract public function pipeline_serialize();
	
	/*!	 @brief Returns the description of the reification of some triples defined by pipeline_serialize()
							
			\n
			
			@note most of the web services won't implement this procedure.
			
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/			
	abstract public function pipeline_serialize_reification();
	
	/*!	 @brief Inject the DOCType in a XML document
							
			\n
			
			@param[in] $xmlDoc The XML document where to inject the doctype
			
			@return a XML document with a doctype
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/		
	abstract public function injectDoctype($xmlDoc);
	
	/*!	 @brief Validate a query to this web service
							
			\n
			
			@return TRUE if valid; FALSE otherwise
		
			@note Usually, this function sends a query to the Authentication web service in order to be validated.
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/		
	abstract protected function validateQuery();	
	
	/*!	 @brief Encode content to be included in XML files
							
			\n
			
			@param[in] $string The content string to be encoded
			
			@return returns the encoded string
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/			
	public function xmlEncode($string)
	{
		return str_replace(array("\\", "&", "<", ">"), array("%5C", "&amp;", "&lt;", "&gt;"), $string);
	}
}

/*!	 @brief CRUD usage data structure of a web service
						
		\n

		@author Frederick Giasson, Structured Dynamics LLC.
	
		\n\n\n
*/

class CrudUsage
{
	/*! @brief Create permissions (TRUE or FALSE) */
	public $create;

	/*! @brief Read permissions (TRUE or FALSE) */
	public $read;

	/*! @brief Update permissions (TRUE or FALSE) */
	public $update;

	/*! @brief Delete permissions (TRUE or FALSE) */
	public $delete;
	
	function __construct($create, $read, $update, $delete)
	{
		$this->create = $create;	
		$this->read = $read;	
		$this->update = $update;	
		$this->delete = $delete;	
	}
}

//@} 

?>