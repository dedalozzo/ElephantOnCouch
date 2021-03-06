<?php

/**
 * @file Couch.php
 * @brief This file contains the Couch class.
 * @details
 * @author Filippo F. Fadda
 */

//! This is the main Elephant on Couch library namespace.
namespace EoC;


use Surfer\Surfer;
use Surfer\Adapter\IClientAdapter;
use Surfer\Message\Request;
use Surfer\Message\Response;
use Surfer\Hook\IChunkHook;

use stdClass;


/**
 * @brief The CouchDB's client. You need an instance of this class to interact with CouchDB.
 * @nosubgrouping
 * @todo Add Memcached support. Remember to use Memcached extension, not memcache.
 * @todo Check ISO-8859-1 because CouchDB uses it, in particular utf8_encode().
 */
final class Couch extends Surfer {

  //! The user agent name.
  const USER_AGENT_NAME = "EoC Client";

  //! Default CouchDB revisions limit number.
  const REVS_LIMIT = 1000;

  /** @name Document Paths */
  //!@{

  const STD_DOC_PATH = ""; //!< Path for standard documents.
  const LOCAL_DOC_PATH = "_local/";  //!< Path for local documents.
  const DESIGN_DOC_PATH = "_design/";  //!< Path for design documents.

  //!@}

  // A database's name prefix.
  private $prefix = '';


  /**
   * @brief Creates a Couch class instance.
   * @param[in] IClientAdapter $adapter An instance of a class that implements the IClientAdapter interface.
   */
  public function __construct(IClientAdapter $adapter) {
    parent::__construct($adapter);
  }


  /**
   * @brief Returns a CouchDB wild card.
   * @details A standard object is translated to JSON as `{}` same of a JavaScript empty object.
   * @return stdClass
   */
  public static function WildCard() {
    return new stdClass();
  }


  /**
   * @brief Sets the document class and type in case the document hasn't one.
   * @param[in] Doc\IDoc $doc A document.
   */
  private function setDocInfo(Doc\IDoc $doc) {
    // Sets the class name.
    $class = get_class($doc);
    $doc->setClass($class);

    // Sets the document type.
    if (!$doc->hasType()) {
      preg_match('/([\w]+$)/', $class, $matches);
      $type = strtolower($matches[1]);
      $doc->setType($type);
    }
  }


  /**
   * @brief Overrides the rows, adding a new row for every key hasn't been matched.
   * @details CouchDB doesn't return rows for the keys a match is not found. To make joins having a row for each key is
   * essential. The algorithm below overrides the rows, adding a new row for every key hasn't been matched. The JSON
   * encoding is necessary because we might have complex keys. A complex key is no more than an array.
   * @param array $keys An array containing the keys.
   * @param[out] array $rows An associative array containing the rows.
   */
  private function addMissingRows(array $keys, &$rows) {

    if (!empty($keys) && isset($rows)) {

      // These are the rows for the matched keys.
      $matches = [];
      foreach ($rows as $row) {
        $hash = md5(json_encode($row['key']));
        $matches[$hash] = $row;
      }

      $allRows = [];
      foreach ($keys as $key) {
        $hash = md5(json_encode($key));

        if (isset($matches[$hash])) // Match found.
          $allRows[] = $matches[$hash];
        else // No match found.
          $allRows[] = ['id' => NULL, 'key' => $key, 'value' => NULL];
      }

      // Overrides the response, replacing rows.
      $rows = $allRows;
    }

  }


  /**
   * @brief Sets a prefix which is used to compose the database's name.
   * @param string $prefix A string prefix.
   */
  public function setDbPrefix($prefix) {
    $this->prefix = $prefix;
  }


  /**
   * @brief Gets the prefix used to compose the database's name if any.
   * @return string
   */
  public function getDbPrefix() {
    return $this->prefix;
  }


  /**
   * @brief This method is used to send a Request to CouchDB.
   * @details If you want call a not supported CouchDB API, you can use this function to send your request.\n
   * You can also provide an instance of a class that implements the IChunkHook interface, to deal with a chunked
   * response.
   * @param Request $request The Request object.
   * @param IChunkHook $chunkHook (optional) A class instance that implements the IChunkHook interface.
   * @return Response
   */
  public function send(Request $request, IChunkHook $chunkHook = NULL) {
    // Sets user agent information.
    $request->setHeaderField(Request::USER_AGENT_HF, self::USER_AGENT_NAME." ".Version::getNumber());

    // We accept JSON.
    $request->setHeaderField(Request::ACCEPT_HF, "application/json");

    // We close the connection after read the response.
    // NOTE: we don't use anymore the connection header field, because we use the same socket until the end of script.
    //$request->setHeaderField(Message::CONNECTION_HF, "close");

    return parent::send($request, $chunkHook);
  }


  /** @name Validation and Encoding Methods */
  //!@{

  /**
   * @brief This method raise an exception when a user provide an invalid document path.
   * @details This method is called by any other methods that interacts with CouchDB. You don't need to call, unless
   * you are making a not supported call to CouchDB.
   * @param string $path Document path.
   * @param bool $excludeLocal Document path.
   */
  public function validateDocPath($path, $excludeLocal = FALSE) {
    if (empty($path)) // STD_DOC_PATH
      return;

    if ($path == self::DESIGN_DOC_PATH)
      return;
    elseif ($path == self::LOCAL_DOC_PATH && $excludeLocal)
      throw new \InvalidArgumentException("Local document doesn't have attachments.");
    else
      throw new \InvalidArgumentException("Invalid document path.");
  }


  /**
   * @brief Encodes the document id.
   * @details This method is called by any other methods that interacts with CouchDB. You don't need to call, unless
   * you are making a not supported call to CouchDB.
   * @param string $docId Document id.
   */
  public function validateAndEncodeDocId(&$docId) {
    if (!empty($docId))
      $docId = rawurlencode($docId);
    else
      throw new \InvalidArgumentException("\$docId must be a non-empty string.");
  }

  //!@}


  /** @name Miscellaneous Methods */
  //!@{

  /**
   * @brief Creates the admin user.
   * @todo Implement the method.
   */
  public function createAdminUser() {
    throw new \BadMethodCallException("The method `createAdminUser()` is not available.");
  }


  /**
   * @brief Restarts the server.
   * @attention Requires admin privileges.
   * @see http://docs.couchdb.org/en/latest/api/server/common.html#restart
   * @bug [COUCHDB-947](https://issues.apache.org/jira/browse/COUCHDB-947)
   */
  public function restartServer() {
    $request = new Request(Request::POST_METHOD, "/_restart");
    $request->setHeaderField(Request::CONTENT_TYPE_HF, "application/json");

    // There is a bug in CouchDB, sometimes it doesn't return the 200 Status Code because it closes the connection
    // before the client has received the entire response. To avoid problems, we trap the exception and we go on.
    try {
      $this->send($request);
    }
    catch (\Exception $e) {
      if ($e->getCode() > 0)
        throw $e;
    }
  }


  /**
   * @brief Returns an object that contains MOTD, server and client and PHP versions.
   * @details The MOTD can be specified in CouchDB configuration files. This function returns more information
   * compared to the CouchDB standard REST call.
   * @return Info\ServerInfo
   */
  public function getServerInfo() {
    $response = $this->send(new Request(Request::GET_METHOD, "/"));
    $info = $response->getBodyAsArray();
    return new Info\ServerInfo($info["couchdb"], $info["version"]);
  }


  /**
   * @brief Returns information about the Elephant on Couch client.
   * @return Info\ClientInfo
   */
  public function getClientInfo() {
    return new Info\ClientInfo();
  }


  /**
   * @brief Returns the favicon.ico file.
   * @details The favicon is a part of the admin interface, but the handler for it is special as CouchDB tries to make
   * sure that the favicon is cached for one year. Returns a string that represents the icon.
   * @return string
   * @see http://docs.couchdb.org/en/latest/api/server/common.html#favicon-ico
   */
  public function getFavicon() {
    $response = $this->send(new Request(Request::GET_METHOD, "/favicon.ico"));

    if ($response->getHeaderFieldValue(Request::CONTENT_TYPE_HF) == "image/x-icon")
      return $response->getBody();
    else
      throw new \InvalidArgumentException("Content-Type must be image/x-icon.");
  }


  /**
   * @brief Returns server statistics.
   * @return array An associative array
   * @see http://docs.couchdb.org/en/latest/api/server/common.html#stats
   */
  public function getStats() {
    return $this->send(new Request(Request::GET_METHOD, "/_stats"))->getBodyAsArray();
  }


  /**
   * @brief Returns a list of all databases on this server.
   * @return array of string
   * @see http://docs.couchdb.org/en/latest/api/server/common.html#all-dbs
   */
  public function getAllDbs() {
    return $this->send(new Request(Request::GET_METHOD, "/_all_dbs"))->getBodyAsArray();
  }


  /**
   * @brief Returns a list of all database events in the CouchDB instance.
   * @param Opt\DbUpdatesFeedOpts $opts Additional options.
   * @return array An associative array.
   * @attention Requires admin privileges.
   * @see http://docs.couchdb.org/en/latest/api/server/common.html#db-updates
   */
  public function getDbUpdates(Opt\DbUpdatesFeedOpts $opts = NULL) {
    $request = new Request(Request::GET_METHOD, "/_db_updates");

    if (isset($opts))
      $request->setMultipleQueryParamsAtOnce($opts->asArray());

    return $this->send($request)->getBodyAsArray();
  }


  /**
   * @brief Returns a list of running tasks.
   * @attention Requires admin privileges.
   * @return array An associative array.
   * @see http://docs.couchdb.org/en/latest/api/server/common.html#active-tasks
   */
  public function getActiveTasks() {
    return $this->send(new Request(Request::GET_METHOD, "/_active_tasks"))->getBodyAsArray();
  }


  /**
   * @brief Returns the tail of the server's log file.
   * @attention Requires admin privileges.
   * @param integer $bytes How many bytes to return from the end of the log file.
   * @return string
   * @see http://docs.couchdb.org/en/latest/api/server/common.html#log
   */
  public function getLogTail($bytes = 1000) {
    if (is_int($bytes) && ($bytes > 0)) {
      $request = new Request(Request::GET_METHOD, "/_log");
      $request->setQueryParam("bytes", $bytes);
      return $this->send($request)->getBody();
    }
    else
      throw new \InvalidArgumentException("\$bytes must be a positive integer.");
  }


  /**
   * @brief Returns a list of generated UUIDs.
   * @param integer $count Requested UUIDs number.
   * @return string|array If `$count == 1` (default) returns a string else returns an array of strings.
   * @see http://docs.couchdb.org/en/latest/api/server/common.html#uuids
   */
  public function getUuids($count = 1) {
    if (is_int($count) && ($count > 0)) {
      $request = new Request(Request::GET_METHOD, "/_uuids");
      $request->setQueryParam("count", $count);

      $response = $this->send($request);

      if ($count == 1) // We don't need to use === operator because, just above, we made a type checking.
        return $response->getBodyAsArray()['uuids'][0];
      else
        return $response->getBodyAsArray()['uuids'];
    }
    else
      throw new \InvalidArgumentException("\$count must be a positive integer.");
  }

  //!@}


  /** @name Server Configuration Methods */
  //!@{

  /**
   * @brief Returns the entire server configuration or a single section or a single configuration value of a section.
   * @param string $section Requested section.
   * @param string $key Requested key.
   * @return string|array An array with the configuration keys or a simple string in case of a single key.
   * @see http://docs.couchdb.org/en/latest/api/server/configuration.html#get--_config
   * @see http://docs.couchdb.org/en/latest/api/server/configuration.html#get--_config-section
   * @see http://docs.couchdb.org/en/latest/api/server/configuration.html#config-section-key
   */
  public function getConfig($section = "", $key = "") {
    $path = "/_config";

    if (!empty($section)) {
      $path .= "/".$section;

      if (!empty($key))
        $path .= "/".$key;
    }

    return $this->send(new Request(Request::GET_METHOD, $path))->getBodyAsArray();
  }


  /**
   * @brief Sets a single configuration value in a given section to server configuration.
   * @param string $section The configuration section.
   * @param string $key The key.
   * @param string $value The value for the key.
   * @see http://docs.couchdb.org/en/latest/api/server/configuration.html#put--_config-section-key
   */
  public function setConfigKey($section, $key, $value) {
    if (!is_string($section) || empty($section))
      throw new \InvalidArgumentException("\$section must be a not empty string.");

    if (!is_string($key) || empty($key))
      throw new \InvalidArgumentException("\$key must be a not empty string.");

    if (is_null($value))
      throw new \InvalidArgumentException("\$value cannot be null.");

    $request = new Request(Request::PUT_METHOD, "/_config/".$section."/".$key);
    $request->setHeaderField(Request::CONTENT_TYPE_HF, "application/json");
    $request->setBody(json_encode(utf8_encode($value)));
    $this->send($request);
  }


  /**
   * @brief Deletes a single configuration value from a given section in server configuration.
   * @param string $section The configuration section.
   * @param string $key The key.
   * @see http://docs.couchdb.org/en/latest/api/configuration.html#delete-config-section-key
   */
  public function deleteConfigKey($section, $key) {
    if (!is_string($section) || empty($section))
      throw new \InvalidArgumentException("\$section must be a not empty string.");

    if (!is_string($key) || empty($key))
      throw new \InvalidArgumentException("\$key must be a not empty string.");

    $this->send(new Request(Request::DELETE_METHOD, "/_config/".$section."/".$key));
  }

  //!@}


  /** @name Cookie Authentication */
  //!@{

  /**
   * @brief Returns cookie based login user information.
   * @return Response
   * @see http://docs.couchdb.org/en/latest/api/server/authn.html#get--_session
   */
  public function getSession() {
    return $this->send(new Request(Request::GET_METHOD, "/_session"));
  }


  /**
   * @brief Makes cookie based user login.
   * @return Response
   * @see http://docs.couchdb.org/en/latest/api/server/authn.html#post--_session
   */
  public function setSession($userName, $password) {
    if (!is_string($userName) || empty($userName))
      throw new \InvalidArgumentException("\$userName must be a not empty string.");

    if (!is_string($password) || empty($password))
      throw new \InvalidArgumentException("\$password must be a not empty string.");

    $request = new Request(Request::POST_METHOD, "/_session");

    $request->setHeaderField(Request::X_COUCHDB_WWW_AUTHENTICATE_HF, "Cookie");
    $request->setHeaderField(Request::CONTENT_TYPE_HF, "application/x-www-form-urlencoded");

    $request->setQueryParam("name", $userName);
    $request->setQueryParam("password", $password);

    return $this->send($request);
  }


  /**
   * @brief Makes user logout.
   * @return Response
   * @see http://docs.couchdb.org/en/latest/api/server/authn.html#delete--_session
   */
  public function deleteSession() {
    return $this->send(new Request(Request::DELETE_METHOD, "/_session"));
  }

  //!@}


  /** @name OAuth Authentication */
  //! @see http://docs.couchdb.org/en/latest/api/server/authn.html#oauth-authentication
  //!@{

  /**
   * @brief
   * @todo To be implemented and documented.
   */
  public function getAccessToken() {
    return $this->send(new Request(Request::GET_METHOD, "/_oauth/access_token"));
  }


  /**
   * @brief
   * @todo To be implemented and documented.
   */
  public function getAuthorize() {
    return $this->send(new Request(Request::GET_METHOD, "/_oauth/authorize"));
  }


  /**
   * @brief
   * @todo To be implemented and documented.
   */
  public function setAuthorize() {
    return $this->send(new Request(Request::POST_METHOD, "/_oauth/authorize"));
  }


  /**
   * @brief
   * @todo To be implemented and documented.
   */
  public function requestToken() {
    return $this->send(new Request(Request::GET_METHOD, "/_oauth/request_token"));
  }

  //!@}


  /**
   * @brief Creates a new database and selects it.
   * @param string $name The database name. A database must be named with all lowercase letters (a-z),
   * digits (0-9), or any of the _$()+-/ characters and must end with a slash in the URL. The name has to start with a
   * lowercase letter (a-z).
   * @see http://docs.couchdb.org/en/latest/api/database/common.html#put--db
   */
  public function createDb($name) {
    # \A[a-z][a-z\d_$()+-/]++\z
    #
    # Assert position at the beginning of the string «\A»
    # Match a single character in the range between “a” and “z” «[a-z]»
    # Match a single character present in the list below «[a-z\d_$()+-/]++»
    #    Between one and unlimited times, as many times as possible, without giving back (possessive) «++»
    #    A character in the range between “a” and “z” «a-z»
    #    A single digit 0..9 «\d»
    #    One of the characters “_$()” «_$()»
    #    A character in the range between “+” and “/” «+-/»
    # Assert position at the very end of the string «\z»
    if (preg_match('%\A[a-z][a-z\d_$()+-/]++\z%', $name) === FALSE)
      throw new \InvalidArgumentException("Invalid database name.");

    $this->send(new Request(Request::PUT_METHOD, "/".rawurlencode($this->prefix.$name)."/"));
  }


  /**
   * @brief Deletes an existing database.
   * @param string $name The database name.
   * @see http://docs.couchdb.org/en/latest/api/database/common.html#delete--db
   */
  public function deleteDb($name) {
    $this->send(new Request(Request::DELETE_METHOD, "/".rawurlencode($this->prefix.$name)));
  }


  /**
   * @brief Returns information about the selected database.
   * @param string $name The database name.
   * @return Info\Dbinfo
   * @see http://docs.couchdb.org/en/latest/api/database/common.html#get--db
   */
  public function getDbInfo($name) {
    return new Info\Dbinfo($this->send(new Request(Request::GET_METHOD, "/".rawurlencode($this->prefix.$name)."/"))->getBodyAsArray());
  }


  /**
   * @brief Obtains a list of the changes made to the database. This can be used to monitor for update and modifications
   * to the database for post processing or synchronization.
   * @param string $name The database name.
   * @param Opt\ChangesFeedOpts $opts Additional options.
   * @return Response
   * @see http://docs.couchdb.org/en/latest/api/database/changes.html
   */
  public function getDbChanges($name, Opt\ChangesFeedOpts $opts = NULL) {
    $request = new Request(Request::GET_METHOD, "/".rawurlencode($this->prefix.$name)."/_changes");

    if (isset($opts))
      $request->setMultipleQueryParamsAtOnce($opts->asArray());

    return $this->send($request);
  }


  /**
   * @brief Starts a compaction for the current selected database.
   * @details Writes a new version of the database file, removing any unused sections from the new version during write.
   * Because a new file is temporary created for this purpose, you will need twice the current storage space of the
   * specified database in order for the compaction routine to complete.\n
   * Removes old revisions of documents from the database, up to the per-database limit specified by the `_revs_limit`
   * database setting.\n
   * Compaction can only be requested on an individual database; you cannot compact all the databases for a CouchDB
   * instance.\n
   * The compaction process runs as a background process. You can determine if the compaction process is operating on a
   * database by obtaining the database meta information, the `compact_running` value of the returned database
   * structure will be set to true. You can also obtain a list of running processes to determine whether compaction is
   * currently running, using getActiveTasks().
   * @param string $name The database name.
   * @attention Requires admin privileges.
   * @see http://docs.couchdb.org/en/latest/api/database/compact.html
   */
  public function compactDb($name) {
    $request = new Request(Request::POST_METHOD, "/".rawurlencode($this->prefix.$name)."/_compact");

    // A POST method requires Content-Type header.
    $request->setHeaderField(Request::CONTENT_TYPE_HF, "application/json");

    $this->send($request);
  }


  /**
   * @brief Compacts the specified view.
   * @details If you have very large views or are tight on space, you might consider compaction as well. To run compact
   * for a particular view on a particular database, use this method.
   * @param string $dbName The database name.
   * @param string $designDocName Name of the design document where is stored the view.
   * @see http://docs.couchdb.org/en/latest/api/database/compact.html#db-compact-design-doc
   */
  public function compactView($dbName, $designDocName) {
    $path = "/".rawurlencode($this->prefix.$dbName)."/_compact/".$designDocName;

    $request = new Request(Request::POST_METHOD, $path);

    // A POST method requires Content-Type header.
    $request->setHeaderField(Request::CONTENT_TYPE_HF, "application/json");

    $this->send($request);
  }


  /**
   * @brief Removes all outdated view indexes.
   * @details Old views files remain on disk until you explicitly run cleanup.
   * @param string $dbName The database name.
   * @attention Requires admin privileges.
   * @see http://docs.couchdb.org/en/latest/api/database/compact.html#db-view-cleanup
   */
  public function cleanupViews($dbName) {
    $request =  new Request(Request::POST_METHOD, "/".rawurlencode($this->prefix.$dbName)."/_view_cleanup");

    // A POST method requires Content-Type header.
    $request->setHeaderField(Request::CONTENT_TYPE_HF, "application/json");

    $this->send($request);
  }


  /**
   * @brief Makes sure all uncommited database changes are written and synchronized to the disk.
   * @details Default CouchDB configuration use delayed commit to improve performances. So CouchDB allows operations to
   * be run against the disk without an explicit fsync after each operation. Synchronization takes time (the disk may
   * have to seek, on some platforms the hard disk cache buffer is flushed, etc.), so requiring an fsync for each update
   * deeply limits CouchDB's performance for non-bulk writers.\n
   * Delayed commit should be left set to true in the configuration settings. Anyway, you can still tell CouchDB to make
   * an fsync, calling the this method.
   * @param string $dbName The database name.
   * @return string The timestamp of the last time the database file was opened.
   * @see http://docs.couchdb.org/en/latest/api/database/compact.html#db-ensure-full-commit
   */
  public function ensureFullCommit($dbName) {
    $request = new Request(Request::POST_METHOD, "/".rawurlencode($this->prefix.$dbName)."/_ensure_full_commit");

    // A POST method requires Content-Type header.
    $request->setHeaderField(Request::CONTENT_TYPE_HF, "application/json");

    return $this->send($request)->getBodyAsArray()["instance_start_time"];
  }

  //!@}


  /**
   * @name Security Methods
   * @details The security object consists of two compulsory elements, admins and members, which are used to specify
   * the list of users and/or roles that have admin and members rights to the database respectively:
   * - members: they can read all types of documents from the DB, and they can write (and edit) documents to the
   * database except for design documents.
   * - admins: they have all the privileges of members plus the privileges: write (and edit) design documents, add/remove
   * database admins and members, set the database revisions limit and execute temporary views against the database.
   * They can not create a database nor delete a database.
   *
   * Both members and admins objects are contains two array-typed fields:
   * - users: List of CouchDB user names
   * - roles: List of users roles
   *
   * Any other additional fields in the security object are optional. The entire security object is made available to
   * validation and other internal functions so that the database can control and limit functionality.
   * If both the names and roles fields of either the admins or members properties are empty arrays, it means the
   * database has no admins or members.\n
   * Having no admins, only server admins (with the reserved _admin role) are able to update design document and make
   * other admin level changes.\n
   * Having no members, any user can write regular documents (any non-design document) and read documents from the database.
   * If there are any member names or roles defined for a database, then only authenticated users having a matching name
   * or role are allowed to read documents from the database.
   */
  //!@{

  /**
   * @brief Returns the special security object for the database.
   * @param string $dbName The database name.
   * @return array An associative array.
   * @see http://docs.couchdb.org/en/latest/api/database/security.html#get--db-_security
   */
  public function getSecurityObj($dbName) {
    return $this->send(new Request(Request::GET_METHOD, "/".rawurlencode($this->prefix.$dbName)."/_security"))->getBodyAsArray();
  }


  /**
   * @brief Sets the special security object for the database.
   * @param string $dbName The database name.
   * @see http://docs.couchdb.org/en/latest/api/database/security.html#put--db-_security
   */
  public function setSecurityObj($dbName) {
    return $this->send(new Request(Request::PUT_METHOD, "/".rawurlencode($this->prefix.$dbName)."/_security"));
  }

  //!@}


  /**
   * @name Database Replication Methods
   * @details The replication is an incremental one way process involving two databases (a source and a destination).
   * The aim of the replication is that at the end of the process, all active documents on the source database are also
   * in the destination database and all documents that were deleted in the source databases are also deleted (if
   * exists) on the destination database.\n
   * The replication process only copies the last revision of a document, so all previous revisions that were only on
   * the source database are not copied to the destination database.\n
   * Changes on the master will not automatically replicate to the slaves. To make replication continuous, you must set
   * `$continuous = true`. At this time, CouchDB does not remember continuous replications over a server restart.
   * Specifying a local source database and a remote target database is called push replication and a remote source and
   * local target is called pull replication. As of CouchDB 0.9, pull replication is a lot more efficient and resistant
   * to errors, and it is suggested that you use pull replication in most cases, especially if your documents are large
   * or you have large attachments.
   */
  //!@{

  /**
   * @brief Starts replication.
   @code
     startReplication("sourcedbname", "http://example.org/targetdbname", TRUE, TRUE);
   @endcode
   * @param string $sourceDbUrl Source database URL.
   * @param string $targetDbUrl Destination database URL.
   * @param string $proxy (optional) Specify the optional proxy used to perform the replication.
   * @param bool $createTargetDb (optional) The target database has to exist and is not implicitly created. You
   * can force the creation setting `$createTargetDb = true`.
   * @param bool $continuous (optional) When `true` CouchDB will not stop after replicating all missing documents
   * from the source to the target.\n
   * At the time of writing, CouchDB doesn't remember continuous replications over a server restart. For the time being,
   * you are required to trigger them again when you restart CouchDB. In the future, CouchDB will allow you to define
   * permanent continuous replications that survive a server restart without you having to do anything.
   * @param string|array $filter (optional) Name of a filter function that can choose which revisions get replicated.
   * You can also provide an array of document identifiers; if given, only these documents will be replicated.
   * @param Opt\ViewQueryOpts $opts (optional) Query options to get additional information, grouping results, include
   * docs, etc.
   * @return Response
   * @see http://docs.couchdb.org/en/latest/api/server/common.html#post--_replicate
   */
  public function startReplication($sourceDbUrl, $targetDbUrl, $proxy = NULL, $createTargetDb = TRUE,
                                     $continuous = FALSE, $filter = NULL, Opt\ViewQueryOpts $opts = NULL) {
    // Sets source and target databases.
    if (is_string($sourceDbUrl) && !empty($sourceDbUrl) &&
      is_string($targetDbUrl) && !empty($targetDbUrl)) {
      $body["source"] = $sourceDbUrl;
      $body["target"] = $targetDbUrl;
    }
    else
      throw new \InvalidArgumentException("\$source_db_url and \$target_db_url must be non-empty strings.");

    if (!is_bool($continuous))
      throw new \InvalidArgumentException("\$continuous must be a bool.");
    elseif ($continuous)
      $body["continuous"] = $continuous;

    // Uses the specified proxy if any set.
    if (isset($proxy))
      $body["proxy"] = $proxy;

    // create_target option
    if (!is_bool($createTargetDb))
      throw new \InvalidArgumentException("\$createTargetDb must be a bool.");
    elseif ($createTargetDb)
      $body["create_target"] = $createTargetDb;

    if (!empty($filter)) {
      if (is_string($filter)) // filter option
        $body["filter"] = $filter;
      elseif (is_array($filter)) // doc_ids option
        $body["doc_ids"] = array_values($filter);
      else
        throw new \InvalidArgumentException("\$filter must be a string or an array.");
    }

    // queryParams option
    if (!is_null($opts)) {
      if ($opts instanceof Opt\ViewQueryOpts)
        $body["query_params"] = get_object_vars($opts);
      else
        throw new \InvalidArgumentException("\$queryParams must be an instance of ViewQueryOpts class.");
    }

    $request = new Request(Request::POST_METHOD, "/_replicate");
    $request->setBody($body);

    return $this->send($request);
  }


  /**
   * @brief Cancels replication.
   * @param string $replicationId The replication ID.
   * @return Response
   * @see http://docs.couchdb.org/en/latest/api/server/common.html#canceling-continuous-replication
   */
  public function stopReplication($replicationId) {
    if (is_null($replicationId))
      throw new \InvalidArgumentException("You must provide a replication id.");

    $body["replication_id"] = $replicationId;
    $body["cancel"] = TRUE;

    $request = new Request(Request::POST_METHOD, "/_replicate");
    $request->setBody($body);

    return $this->send($request);
  }


  /**
   * @brief
   * @details
   * @see http://wiki.apache.org/couchdb/Replication#Replicator_database
   * @see http://docs.couchbase.org/couchdb-release-1.1/index.html#couchb-release-1.1-replicatordb
   * @see https://gist.github.com/832610
   * @todo To be implemented and documented.
   */
  public function getReplicator() {
    throw new \BadMethodCallException("The method `getReplicator()` is not available.");
  }

  //!@}


  /** @name Query Documents Methods
   * @details Queries are the primary mechanism for retrieving a result set from a view. The result of a query is an
   * instance of `QueryResult`, a class that implements the [IteratorAggregate](http://php.net/manual/en/class.iteratoraggregate.php),
   * [Countable](http://php.net/manual/en/class.countable.php) and [ArrayAccess](http://php.net/manual/en/class.arrayaccess.php)
   * interfaces, so you can use the result set as an array.
   */
  //!@{

  /**
   * @brief Returns a built-in view of all documents in this database. If keys are specified returns only certain rows.
   * @param string $dbName The database name.
   * @param array $keys (optional) Used to retrieve just the view rows matching that set of keys. Rows are returned
   * in the order of the specified keys. Combining this feature with Opt\ViewQueryOpts.includeDocs() results in the so-called
   * multi-document-fetch feature.
   * @param Opt\ViewQueryOpts $opts (optional) Query options to get additional information, grouping results, include
   * docs, etc.
   * @param IChunkHook $chunkHook (optional) A class instance that implements the IChunkHook interface.
   * @return Result\QueryResult The result of the query.
   * @see http://docs.couchdb.org/en/latest/api/database/bulk-api.html#get--db-_all_docs
   * @see http://docs.couchdb.org/en/latest/api/database/bulk-api.html#post--db-_all_docs
   */
  public function queryAllDocs($dbName, array $keys = NULL, Opt\ViewQueryOpts $opts = NULL, IChunkHook $chunkHook = NULL) {
    if (is_null($keys))
      $request = new Request(Request::GET_METHOD, "/".rawurlencode($this->prefix.$dbName)."/_all_docs");
    else {
      $request = new Request(Request::POST_METHOD, "/".rawurlencode($this->prefix.$dbName)."/_all_docs");
      $request->setBody(json_encode(['keys' => $keys]));
    }

    if (isset($opts))
      $request->setMultipleQueryParamsAtOnce($opts->asArray());

    $result = $this->send($request, $chunkHook)->getBodyAsArray();

    return new Result\QueryResult($result);
  }


  /**
   * @brief Executes the given view and returns the result.
   * @param string $dbName The database name.
   * @param string $designDocName The design document's name.
   * @param string $viewName The view's name.
   * @param array $keys (optional) Used to retrieve just the view rows matching that set of keys. Rows are returned
   * in the order of the specified keys. Combining this feature with Opt\ViewQueryOpts.includeDocs() results in the so-called
   * multi-document-fetch feature.
   * @param Opt\ViewQueryOpts $opts (optional) Query options to get additional information, grouping results, include
   * docs, etc.
   * @param IChunkHook $chunkHook (optional) A class instance that implements the IChunkHook interface.
   * @return Result\QueryResult The result of the query.
   * @attention Multiple keys request to a reduce function only supports `group=true` (identical to `group-level=exact`,
   * but it doesn't support `group_level` > 0.
   * CouchDB raises "Multi-key fetchs for reduce view must include `group=true`" error, in case you use `group_level`.
   * @see http://docs.couchdb.org/en/latest/api/ddoc/views.html#get--db-_design-ddoc-_view-view
   * @see http://docs.couchdb.org/en/latest/api/ddoc/views.html#post--db-_design-ddoc-_view-view
   */
  public function queryView($dbName, $designDocName, $viewName, array $keys = NULL, Opt\ViewQueryOpts $opts = NULL, IChunkHook $chunkHook = NULL) {
    $this->validateAndEncodeDocId($designDocName);

    if (empty($viewName))
      throw new \InvalidArgumentException("You must provide a valid \$viewName.");

    if (empty($keys))
      $request = new Request(Request::GET_METHOD, "/".rawurlencode($this->prefix.$dbName)."/_design/".$designDocName."/_view/".$viewName);
    else {
      $request = new Request(Request::POST_METHOD, "/".rawurlencode($this->prefix.$dbName)."/_design/".$designDocName."/_view/".$viewName);
      $request->setBody(json_encode(['keys' => $keys]));
    }

    // Required since CouchDB 2.0.
    $request->setHeaderField(Request::CONTENT_TYPE_HF, "application/json");

    if (isset($opts)) {
      $request->setMultipleQueryParamsAtOnce($opts->asArray());
      $includeMissingKeys = $opts->issetIncludeMissingKeys();
    }
    else
      $includeMissingKeys = FALSE;

    $result = $this->send($request, $chunkHook)->getBodyAsArray();

    if ($includeMissingKeys)
      $this->addMissingRows($keys, $result['rows']);

    return new Result\QueryResult($result);
  }


  /**
   * @brief Executes the given view, both map and reduce functions, for all documents and returns the result.
   * @details Map and Reduce functions are provided by the programmer.
   * @attention Requires admin privileges.
   * @param string $dbName The database name.
   * @param string $mapFn The map function.
   * @param string $reduceFn The reduce function.
   * @param array $keys (optional) Used to retrieve just the view rows matching that set of keys. Rows are returned
   * in the order of the specified keys. Combining this feature with Opt\ViewQueryOpts.includeDocs() results in the so-called
   * multi-document-fetch feature.
   * @param Opt\ViewQueryOpts $opts (optional) Query options to get additional information, grouping results, include
   * docs, etc.
   * @param string $language The language used to implement the map and reduce functions.
   * @param IChunkHook $chunkHook (optional) A class instance that implements the IChunkHook interface.
   * @return Result\QueryResult The result of the query.
   * @see http://docs.couchdb.org/en/latest/api/database/temp-views.html#post--db-_temp_view
   */
  public function queryTempView($dbName, $mapFn, $reduceFn = "", array $keys = NULL, Opt\ViewQueryOpts $opts = NULL, $language = 'php', IChunkHook $chunkHook = NULL) {
    $handler = new Handler\ViewHandler('temp');
    $handler->language = $language;
    $handler->mapFn = $mapFn;
    if (!empty($reduce))
      $handler->reduceFn = $reduceFn;

    $request = new Request(Request::POST_METHOD, "/".rawurlencode($this->prefix.$dbName)."/_temp_view");
    $request->setHeaderField(Request::CONTENT_TYPE_HF, "application/json");

    $array = $handler->asArray();

    if (!empty($keys))
      $array['keys'] = $keys;

    $request->setBody(json_encode($array));

    if (isset($opts)) {
      $request->setMultipleQueryParamsAtOnce($opts->asArray());
      $includeMissingKeys = $opts->issetIncludeMissingKeys();
    }
    else
      $includeMissingKeys = FALSE;

    $result = $this->send($request, $chunkHook)->getBodyAsArray();

    if ($includeMissingKeys)
      $this->addMissingRows($keys, $result['rows']);

    return new Result\QueryResult($result);
  }

  //!@}


  /** @name Revisions Management Methods */
  //!@{

  /**
   * @brief Given a list of document revisions, returns the document revisions that do not exist in the database.
   * @param string $dbName The database name.
   * @return Response
   * @see http://docs.couchdb.org/en/latest/api/database/misc.html#db-missing-revs
   */
  public function getMissingRevs($dbName) {
    $request = new Request(Request::POST_METHOD, "/".rawurlencode($this->prefix.$dbName)."/_missing_revs");

    return $this->send($request);
  }


  /**
   * @brief Given a list of document revisions, returns differences between the given revisions and ones that are in
   * the database.
   * @param string $dbName The database name.
   * @return Response
   * @see http://docs.couchdb.org/en/latest/api/database/misc.html#db-revs-diff
   */
  public function getRevsDiff($dbName) {
    $request = new Request(Request::POST_METHOD, "/".rawurlencode($this->prefix.$dbName)."/_missing_revs");

    return $this->send($request);
  }


  /**
   * @brief Gets the limit of historical revisions to store for a single document in the database.
   * @param string $dbName The database name.
   * @return integer The maximum number of document revisions that will be tracked by CouchDB.
   * @see http://docs.couchdb.org/en/latest/api/database/misc.html#get--db-_revs_limit
   */
  public function getRevsLimit($dbName) {
    $request = new Request(Request::GET_METHOD, "/".rawurlencode($this->prefix.$dbName)."/_revs_limit");

    return (integer)$this->send($request)->getBody();
  }


  /**
   * @brief Sets the limit of historical revisions for a single document in the database.
   * @param string $dbName The database name.
   * @param integer $revsLimit (optional) Maximum number historical revisions for a single document in the database.
   * Must be a positive integer.
   * @see http://docs.couchdb.org/en/latest/api/database/misc.html#put--db-_revs_limit
   */
  public function setRevsLimit($dbName, $revsLimit = self::REVS_LIMIT) {
    if (!is_int($revsLimit) || ($revsLimit <= 0))
      throw new \InvalidArgumentException("\$revsLimit must be a positive integer.");

    $request = new Request(Request::PUT_METHOD, "/".rawurlencode($this->prefix.$dbName)."/_revs_limit");
    $request->setHeaderField(Request::CONTENT_TYPE_HF, "application/json");
    $request->setBody(json_encode($revsLimit));

    $this->send($request);
  }

  //!@}


  /** @name Documents Management Methods */
  //!@{

  /**
   * @brief Returns the document's entity tag, that can be used for caching or optimistic concurrency control purposes.
   * The ETag Header is simply the document's revision in quotes.
   * @details This function is not available for special documents. To get information about a design document, use
   * the special function getDesignDocInfo().
   * @param string $dbName The database name.
   * @param string $docId The document's identifier.
   * @return string The document's revision.
   * @see http://docs.couchdb.org/en/latest/api/document/common.html#db-doc
   * @bug CouchDB ETag is
   */
  public function getDocEtag($dbName, $docId) {
    $this->validateAndEncodeDocId($docId);

    $path = "/".rawurlencode($this->prefix.$dbName)."/".$docId;

    $request = new Request(Request::HEAD_METHOD, $path);

    // CouchDB ETag is included between quotation marks.
    return trim($this->send($request)->getHeaderFieldValue(Response::ETAG_HF), '"');
  }


  /**
   * @brief Returns the latest revision of the document.
   * @details Since CouchDB uses different paths to store special documents, you must provide the document type for
   * design and local documents.
   * @param string $dbName The database name.
   * @param string $docId The document's identifier.
   * @param string $path The document's path.
   * @param string $rev (optional) The document's revision.
   * @param Opt\DocOpts $opts Query options to get additional document information, like conflicts, attachments, etc.
   * @return object|Response An instance of Doc, LocalDoc, DesignDoc or any subclass of Doc.
   * @see http://docs.couchdb.org/en/latest/api/document/common.html#get--db-docid
   */
  public function getDoc($dbName, $path, $docId, $rev = NULL, Opt\DocOpts $opts = NULL) {
    $this->validateDocPath($path);
    $this->validateAndEncodeDocId($docId);

    $requestPath = "/".rawurlencode($this->prefix.$dbName)."/".$path.$docId;

    $request = new Request(Request::GET_METHOD, $requestPath);

    // Retrieves the specific revision of the document.
    if (!empty($rev))
      $request->setQueryParam("rev", (string)$rev);

    // If there are any options, add them to the request.
    if (isset($opts)) {
      $request->setMultipleQueryParamsAtOnce($opts->asArray());
      $ignoreClass = $opts->issetIgnoreClass();
    }
    else
      $ignoreClass = FALSE;

    $response = $this->send($request);
    $body = $response->getBodyAsArray();

    // We use `class` metadata to store an instance of a specialized document class. We can have Article and Book classes,
    // both derived from Doc, with special properties and methods. Instead to convert them, we store the class name in a
    // special attribute called 'class' within the others metadata. So, once we retrieve the document, the client creates
    // an instance of the class we provided when we saved the document; we never need to convert it.
    if (!$ignoreClass && isset($body['class'])) { // Special document class inherited from Doc or LocalDoc.
      $class = "\\".$body['class'];
      $doc = new $class;
    }
    elseif ($path == self::DESIGN_DOC_PATH)
      $doc = new Doc\DesignDoc;
    else
      $doc = NULL;

    if (is_object($doc)) {
      $doc->assignArray($body);
      return $doc;
    }
    else
      return $response;
  }


  /**
   * @brief Inserts or updates a document into the selected database.
   * @details Whether the `$doc` has an id we use a different HTTP method. Using POST CouchDB generates an id for the doc,
   * using PUT instead we need to specify one. We can still use the function getUuids() to ask CouchDB for some ids.
   * This is an internal detail. You have only to know that CouchDB can generate the document id for you.
   * @param string $dbName The database name.
   * @param Doc\IDoc $doc The document you want insert or update.
   * @param bool $batchMode (optional) You can write documents to the database at a higher rate by using the batch
   * option. This collects document writes together in memory (on a user-by-user basis) before they are committed to
   * disk. This increases the risk of the documents not being stored in the event of a failure, since the documents are
   * not written to disk immediately.
   * @see http://docs.couchdb.org/en/latest/api/document/common.html#put--db-docid
   */
  public function saveDoc($dbName, Doc\IDoc $doc, $batchMode = FALSE) {
    // Whether the document has an id we use a different HTTP method. Using POST CouchDB generates an id for the doc
    // using PUT we need to specify one. We can still use the function getUuids() to ask CouchDB for some ids.
    if (!$doc->issetId())
      $doc->setId(Generator\UUID::generate(Generator\UUID::UUID_RANDOM, Generator\UUID::FMT_STRING));

    $this->setDocInfo($doc);

    // We never use the POST method.
    $method = Request::PUT_METHOD;

    $path = "/".rawurlencode($this->prefix.$dbName)."/".$doc->getPath().$doc->getId();

    $request = new Request($method, $path);
    $request->setHeaderField(Request::CONTENT_TYPE_HF, "application/json");
    $request->setBody($doc->asJson());

    // Enables batch mode.
    if ($batchMode)
      $request->setQueryParam("batch", "ok");

    $this->send($request);
  }


  /**
   * @brief Deletes the specified document.
   * @details To delete a document you must provide the document identifier and the revision number.
   * @param string $dbName The database name.
   * @param string $docId The document's identifier you want delete.
   * @param string $rev The document's revision number you want delete.
   * @param string $path The document path.
   * @see http://docs.couchdb.org/en/latest/api/document/common.html#delete--db-docid
   */
  public function deleteDoc($dbName, $path, $docId, $rev) {
    $this->validateDocPath($path);
    $this->validateAndEncodeDocId($docId);

    $path = "/".rawurlencode($this->prefix.$dbName)."/".$path.$docId;

    $request = new Request(Request::DELETE_METHOD, $path);
    $request->setQueryParam("rev", (string)$rev);

    // We could use another technique to send the revision number. Here just for documentation.
    // $request->setHeader(Request::IF_MATCH_HEADER, (string)$rev);

    $this->send($request);
  }


  /**
   * @brief Makes a duplicate of the specified document. If you want to overwrite an existing document, you need to
   * specify the target document's revision with a `$rev` parameter.
   * @details If you want copy a special document you must specify his type.
   * @param string $dbName The database name.
   * @param string $sourceDocId The source document id.
   * @param string $targetDocId The destination document id.
   * @param string $rev Needed when you want override an existent document.
   * @param string $path The document path.
   * @see http://docs.couchdb.org/en/latest/api/document/common.html#copy--db-docid
   */
  public function copyDoc($dbName, $path, $sourceDocId, $targetDocId, $rev = NULL) {
    $this->validateDocPath($path);

    $this->validateAndEncodeDocId($sourceDocId);
    $this->validateAndEncodeDocId($targetDocId);

    $path = "/".rawurlencode($this->prefix.$dbName)."/".$path.$sourceDocId;

    // This request uses the special method COPY.
    $request = new Request(Request::COPY_METHOD, $path);

    if (empty($rev))
      $request->setHeaderField(Request::DESTINATION_HF, $targetDocId);
    else
      $request->setHeaderField(Request::DESTINATION_HF, $targetDocId."?rev=".(string)$rev);

    $this->send($request);
  }


  /**
   * @brief The purge operation removes the references to the deleted documents from the database.
   * @details A database purge permanently removes the references to deleted documents from the database. Deleting a
   * document within CouchDB does not actually remove the document from the database, instead, the document is marked
   * as deleted (and a new revision is created). This is to ensure that deleted documents are replicated to other
   * databases as having been deleted. This also means that you can check the status of a document and identify that
   * the document has been deleted.\n
   * The purging of old documents is not replicated to other databases. If you are replicating between databases and
   * have deleted a large number of documents you should run purge on each database.\n
   * Purging documents does not remove the space used by them on disk. To reclaim disk space, you should run compactDb().\n
   * @param string $dbName The database name.
   * @param array $refs An array of references used to identify documents and revisions to delete. The array must
   * contains instances of the DocRef class.
   * @return Response
   * @see http://docs.couchdb.org/en/latest/api/database/misc.html#post--db-_purge
   * @see http://wiki.apache.org/couchdb/Purge_Documents
   */
  public function purgeDocs($dbName, array $refs) {
    $path = "/".rawurlencode($this->prefix.$dbName)."/_purge";

    $request = new Request(Request::POST_METHOD, $path);

    $purge = [];
    foreach ($refs as $ref)
      $purge[] = $ref->asArray();

    $request->setBody(json_encode($purge));

    return $this->send($request);
  }


  /**
   * @brief Inserts, updates and deletes documents in a bulk.
   * @details Documents that are updated or deleted must contain the `rev` number. To delete a document, you should
   * call delete() method on your document. When creating new documents the document ID is optional. For updating existing
   * documents, you must provide the document ID and revision.
   * @param string $dbName The database name.
   * @param array $docs An array of documents you want insert, delete or update.
   * @param bool $fullCommit (optional) Makes sure all uncommited database changes are written and synchronized
   * to the disk immediately.
   * @param bool $allOrNothing (optional) In all-or-nothing mode, either all documents are written to the database,
   * or no documents are written to the database, in the event of a system failure during commit.\n
   * You can ask CouchDB to check that all the documents pass your validation functions. If even one fails, none of the
   * documents are written. If all documents pass validation, then all documents will be updated, even if that introduces
   * a conflict for some or all of the documents.
   * @param bool $newEdits (optional) When `false` CouchDB pushes existing revisions instead of creating
   * new ones. The response will not include entries for any of the successful revisions (since their rev IDs are
   * already known to the sender), only for the ones that had errors. Also, the conflict error will never appear,
   * since in this mode conflicts are allowed.
   * @return Response
   * @see http://docs.couchdb.org/en/latest/api/database/bulk-api.html#db-bulk-docs
   * @see http://docs.couchdb.org/en/latest/json-structure.html#bulk-documents
   * @see http://wiki.apache.org/couchdb/HTTP_Bulk_Document_API
   */
  public function performBulkOperations($dbName, array $docs, $fullCommit = FALSE, $allOrNothing = FALSE, $newEdits = TRUE) {
    if (count($docs) == 0)
      throw new \InvalidArgumentException("The \$docs array cannot be empty.");
    else
      $operations = [];

    $path = "/".rawurlencode($this->prefix.$dbName)."/_bulk_docs";

    $request = new Request(Request::POST_METHOD, $path);
    $request->setHeaderField(Request::CONTENT_TYPE_HF, "application/json");

    if ($fullCommit)
      $request->setHeaderField(Request::X_COUCHDB_FULL_COMMIT_HF, "full_commit");
    else
      $request->setHeaderField(Request::X_COUCHDB_FULL_COMMIT_HF, "delay_commit");

    if ($allOrNothing)
      $operations['all_or_nothing'] = 'true';

    if (!$newEdits)
      $operations['new_edits'] = 'false';

    foreach ($docs as $doc) {
      $this->setDocInfo($doc);
      $operations['docs'][] = $doc->asArray();
    }

    $request->setBody(json_encode($operations));

    return $this->send($request);
  }

  //!@}


  /** @name Attachments Management Methods */
  //!@{


  /**
   * @brief Returns the attachment revision.
   * @param string $dbName The database name.
   * @param string $fileName The attachment's name.
   * @param string $docId The document's identifier.
   * @param string $path The document's path.
   * @param string $rev (optional) The document's revision.
   * @return array An associative array.
   * @see http://docs.couchdb.org/en/latest/api/document/attachments.html#db-doc-attachment
   */
  public function getAttachmentInfo($dbName, $fileName, $path, $docId, $rev = NULL) {
    $this->validateDocPath($path, TRUE);
    $this->validateAndEncodeDocId($docId);

    $path = "/".rawurlencode($this->prefix.$dbName)."/".$path.$docId."/".$fileName;

    $request = new Request(Request::HEAD_METHOD, $path);

    // In case we want retrieve a specific document revision.
    if (!empty($rev))
      $request->setQueryParam("rev", (string)$rev);

    return $this->send($request)->getHeaderAsArray();
  }


  /**
   * @brief Retrieves the attachment from the specified document.
   * @param string $dbName The database name.
   * @param string $fileName The attachment's name.
   * @param string $docId The document's identifier.
   * @param string $path The document's path.
   * @param string $rev (optional) The document's revision.
   * @see http://docs.couchdb.org/en/latest/api/document/attachments.html#get--db-docid-attname
   * @see http://docs.couchdb.org/en/latest/api/document/attachments.html#http-range-requests
   * @todo Add support for Range request, using header "Range: bytes=0-12".
   */
  public function getAttachment($dbName, $fileName, $path, $docId, $rev = NULL) {
    $this->validateDocPath($path, TRUE);
    $this->validateAndEncodeDocId($docId);

    $path = "/".rawurlencode($this->prefix.$dbName)."/".$path.$docId."/".$fileName;

    $request = new Request(Request::GET_METHOD, $path);

    // In case we want retrieve a specific document revision.
    if (!empty($rev))
      $request->setQueryParam("rev", (string)$rev);

    return $this->send($request)->getBody();
  }


  /**
   * @brief Inserts or updates an attachment to the specified document.
   * @param string $dbName The database name.
   * @param string $fileName The attachment's name.
   * @param string $docId The document's identifier.
   * @param string $path The document's path.
   * @param string $rev (optional) The document's revision.
   * @see http://docs.couchdb.org/en/latest/api/document/attachments.html#put--db-docid-attname
   */
  public function putAttachment($dbName, $fileName, $path, $docId, $rev = NULL) {
    $this->validateDocPath($path, TRUE);
    $this->validateAndEncodeDocId($docId);

    $attachment = Doc\Attachment\Attachment::fromFile($fileName);

    $path = "/".rawurlencode($this->prefix.$dbName)."/".$path.$docId."/".rawurlencode($attachment->getName());

    $request = new Request(Request::PUT_METHOD, $path);
    $request->setHeaderField(Request::CONTENT_LENGTH_HF, $attachment->getContentLength());
    $request->setHeaderField(Request::CONTENT_TYPE_HF, $attachment->getContentType());
    $request->setBody(base64_encode($attachment->getData()));

    // In case of adding or updating an existence document.
    if (!empty($rev))
      $request->setQueryParam("rev", (string)$rev);

    return $this->send($request);
  }


  /**
   * @brief Deletes an attachment from the document.
   * @param string $dbName The database name.
   * @param string $fileName The attachment's name.
   * @param string $docId The document's identifier.
   * @param string $path The document's path.
   * @param string $rev The document's revision.
   * @see http://docs.couchdb.org/en/latest/api/document/attachments.html#delete--db-docid-attname
   */
  public function deleteAttachment($dbName, $fileName, $path, $docId, $rev) {
    $this->validateDocPath($path, TRUE);
    $this->validateAndEncodeDocId($docId);

    $path = "/".rawurlencode($this->prefix.$dbName)."/".$path.$docId."/".rawurlencode($fileName);

    $request = new Request(Request::DELETE_METHOD, $path);
    $request->setQueryParam("rev", (string)$rev);

    return $this->send($request);
  }

  //!@}


  /** @name Special Design Documents Management Methods */
  //!@{


  /**
   * @brief Returns basic information about the design document and his views.
   * @param string $dbName The database name.
   * @param string $docName The design document's name.
   * @return array An associative array
   * @see http://docs.couchdb.org/en/latest/api/ddoc/common.html#get--db-_design-ddoc-_info
   */
  public function getDesignDocInfo($dbName, $docName) {
    $this->validateAndEncodeDocId($docName);

    $path = "/".rawurlencode($this->prefix.$dbName)."/".self::DESIGN_DOC_PATH.$docName."/_info";

    $request = new Request(Request::GET_METHOD, $path);

    return $this->send($request)->getBodyAsArray();
  }

  //!@}

}