<?php

/**
 * @file AbstractClient.php
 * @brief This file contains the AbstractClient class.
 * @details
 * @author Filippo F. Fadda
 */


//! The HTTP clients namespace.
namespace ElephantOnCouch\Client;


use ElephantOnCouch\Message\Request;
use ElephantOnCouch\Hook;


/**
 * @brief An abstract HTTP client.
 */
abstract class AbstractClient {

  //! Default server.
  const DEFAULT_SERVER = "127.0.0.1:5984";

  const SCHEME_HOST_PORT_URI = '/^
	        (?P<scheme>tcp:\/\/|ssl:\/\/|tls:\/\/)?          # Scheme
	        # Authority
	        (?P<host>[a-z0-9\-._~%]+                         # Named host
	        |     \[[a-f0-9:.]+\]                            # IPv6 host
	        |     \[v[a-f0-9][a-z0-9\-._~%!$&\'()*+,;=:]+\]) # IPvFuture host
	        (?P<port>:[0-9]+)?                               # Port
	        $/ix';

  // Used to know if the constructor has been already called.
  protected static $initialized = FALSE;

  protected $scheme;
  protected $host;
  protected $port;

  protected $userName;
  protected $password;


  // URI specifying address of proxy server. (e.g. tcp://proxy.example.com:5100).
  //protected $proxy = NULL;

  // When set to TRUE, the entire URI will be used when constructing the request. While this is a non-standard request
  // format, some proxy servers require it.
  //protected $requestFullUri = FALSE;


  /**
   * @brief Creates a Couch class instance.
   * @param[in] string $server Server must be expressed as host:port as defined by RFC 3986. It's also possible specify
   * a scheme like tcp://, ssl:// or tls://; if no scheme is present, tcp:// will be used.
   * @param[in] string $userName (optional) User name.
   * @param[in] string $password (optional) Password.
   * @param[in] string $persistent (optional) When `true` the client uses a persistent connection.
   * @see http://www.ietf.org/rfc/rfc3986.txt
   */
  public function __construct($server = self::DEFAULT_SERVER, $userName = "", $password = "") {

    // Parses the URI string '$server' to retrieve scheme, host and port and assigns matches to the relative class members.
    if (preg_match(self::SCHEME_HOST_PORT_URI, $server, $matches)) {
      $this->scheme = isset($matches['scheme']) ? $matches['scheme'] : "tcp://";
      $this->host = isset($matches['host']) ? $matches['host'] : "localhost";
      $this->port = isset($matches['port']) ? substr($matches['port'], 1) : "80";
    }
    else // Match attempt failed.
      throw new \InvalidArgumentException(sprintf("'%s' is not a valid URI.", $server));

    $this->userName = (string)$userName;
    $this->password = (string)$password;
  }


  /**
   * @brief Initializes the client.
   * @details This method is called just once, when the first object instance is created. It's used to execute one time
   * operations due to initialize the client. Even if you create many instance of this client, this method is executed
   * just once, keep it in mind.
   */
  abstract public function initialize();


  /**
   * @brief This method is used to send an HTTP Request.
   * @details You can also provide an instance of a class that implements the IChunkHook interface, to deal with a chunked
   * response.
   * @param[in] Request $request The Request object.
   * @param[in] IChunkHook $chunkHook (optional) A class instance that implements the IChunkHook interface.
   * @return Response
   */
  abstract public function send(Request $request, Hook\IChunkHook $chunkHook = NULL);

} 