<?php

error_reporting (E_ALL & ~(E_NOTICE | E_STRICT));

$start = microtime(true);


//////////////////////////////////////////////////////////////////////////
// Stereotypes
//////////////////////////////////////////////////////////////////////////
//define('ARTICLE_DRAFT', 0); // pink
//define('ARTICLE', 2); // no square
//define('INFORMATIVE', 1); // white
//define('ERROR', 3); // red
//define('DOWNLOAD', 133); // red
//define('BOOK_DRAFT', 10); // purple
//define('BOOK', 11); // no square
//define('DISCUSSION_DRAFT', 30); // brown
//define('DISCUSSION', 31); // no square

//phpinfo(INFO_GENERAL);

$loader = require_once __DIR__ . "/../vendor/autoload.php";

use ElephantOnCouch\ElephantOnCouch;
use ElephantOnCouch\ResponseException;
use ElephantOnCouch\Docs\DesignDoc;
use ElephantOnCouch\Handlers\ViewHandler;
use ElephantOnCouch\ViewQueryArgs;


const FIRST_RUN = FALSE;

function arrayToObject($array) {
  return is_array($array) ? (object) array_map(__FUNCTION__, $array) : $array;
}


try {
  $couch = new ElephantOnCouch(ElephantOnCouch::DEFAULT_SERVER, "pippo", "calippo");

  $couch->useCurl();
  //$couch->useSocket();
  $couch->selectDb("programmazione");

  // ===================================================================================================================
  // FIRST DESIGN DOCUMENT
  // ===================================================================================================================
  if (FIRST_RUN)
    $doc = new DesignDoc("articles");
  else {
    $doc = DesignDoc::fromArray($couch->getDoc(ElephantOnCouch::DESIGN_DOC, "articles"));
    $doc->resetHandlers();
  }

  // -------------------------------------------------------------------------------------------------------------------
  // FIRST HANDLER
  // -------------------------------------------------------------------------------------------------------------------
  $map = "function(\$doc) use (\$emit) {
            if (\$doc->stereotype == 2)
              \$emit(\$doc->idItem, NULL);
          };";

  $reduce = "function(\$keys, \$values, \$rereduce) {
               if (\$rereduce)
                 return array_sum(\$values);
               else
                 return sizeof(\$values);
             };";

  $handler = new ViewHandler("articles_by_id");
  $handler->mapFn = $map;
  $handler->reduceFn = $reduce;
  //$handler->useBuiltInReduceFnCount();
  $doc->addHandler($handler);

  // -------------------------------------------------------------------------------------------------------------------
  // SECOND HANDLER
  // -------------------------------------------------------------------------------------------------------------------
  $map = "function(\$doc) use (\$emit) {
                if (\$doc->contributorName == \"Luca Domenichini\")
                  \$emit(\$doc->contributorName, \$doc->idItem);
               };";

  $handler = new ViewHandler("domenichini");
  $handler->mapFn = $map;
  $handler->useBuiltInReduceFnCount();
  $doc->addHandler($handler);

  // -------------------------------------------------------------------------------------------------------------------
  // THIRD HANDLER
  // -------------------------------------------------------------------------------------------------------------------
  $map = "function(\$doc) use (\$emit) {
            \$emit(\$doc->stereotype);
          };";

  $reduce = "function(\$keys, \$values, \$rereduce) {
               return sizeof(\$values);
             };";

  $handler = new ViewHandler("items_by_stereotype");
  $handler->mapFn = $map;
  $handler->reduceFn = $reduce;
  //$handler->useBuiltInReduceFnCount();
  $doc->addHandler($handler);

  // Saves the document.
  $couch->saveDoc($doc);

  // ===================================================================================================================
  // SECOND DESIGN DOCUMENT
  // ===================================================================================================================
  if (FIRST_RUN)
    $doc = new DesignDoc("books");
  else {
    $doc = DesignDoc::fromArray($couch->getDoc(ElephantOnCouch::DESIGN_DOC, "books"));
    $doc->resetHandlers();
  }

  // -------------------------------------------------------------------------------------------------------------------
  // FIRST HANDLER
  // -------------------------------------------------------------------------------------------------------------------
  $map = "function(\$doc) use (\$emit) {
                if (\$doc->stereotype == 11)
                  \$emit(\$doc->idItem, NULL);
               };";

  $handler = new ViewHandler("books");
  $handler->mapFn = $map;
  //$handler->reduceFn = $reduce;
  $handler->useBuiltInReduceFnCount();
  $doc->addHandler($handler);

  // -------------------------------------------------------------------------------------------------------------------
  // SECOND HANDLER
  // -------------------------------------------------------------------------------------------------------------------
  $map = "function(\$doc) use (\$emit) {
                if (\$doc->stereotype == 1)
                  \$emit(\$doc->idItem, NULL);
               };";

  $handler = new ViewHandler("draft_books");
  $handler->mapFn = $map;
  //$handler->reduceFn = $reduce;
  $handler->useBuiltInReduceFnCount();
  $doc->addHandler($handler);

  // Saves the document.
  $couch->saveDoc($doc);


  // ===================================================================================================================
  // QUERY THE VIEWS
  // ===================================================================================================================
  $couch->queryView("articles", "articles_by_id");
  $couch->queryView("articles", "domenichini");
  $couch->queryView("books", "books");
  $couch->queryView("books", "draft_books");

  $queryArgs = new ViewQueryArgs();
  $queryArgs->groupResults();
  $couch->queryView("articles", "items_by_stereotype", $queryArgs);
}
catch (Exception $e) {
  echo ">>> Code: ".$e->getCode()."\r\n";
  echo ">>> Message: ".$e->getMessage()."\r\n";

  if ($e instanceof ResponseException) {
    echo ">>> CouchDB Error: ".$e->getError()."\r\n";
    echo ">>> CouchDB Reason: ".$e->getReason()."\r\n";
  }
}

$stop = microtime(true);
$time = round($stop - $start, 3);

echo "\r\n\r\nElapsed time: $time";

?>

