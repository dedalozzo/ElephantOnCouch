<?php

/**
 * @file ViewHandler.php
 * @brief This file contains the ViewHandler class.
 * @details
 * @author Filippo F. Fadda
 */


namespace EoC\Handler;


use Lint\Lint;


/**
 * @brief This handler let you create a CouchDB view.
 * @details Views are the primary tool used for querying and reporting on CouchDB databases. Views are managed by a
 * special server. Default server implementation uses JavaScript, that's why you have to write views in JavaScript
 * language. This handler instead let you write your views directly in PHP.\n
 * If you have specified 'php' as your design document language, this handler makes a syntax check on your map and
 * reduce functions.\n
 * To create a permanent view, map and reduce functions must first be saved into special design document. Every design
 * document has a special `views` attribute, that stores mandatory map function and an optional reduce function. Using
 * this handler you can write these functions directly in PHP.\n
 * All the views in one design document are indexed whenever any of them gets queried.
 * @nosubgrouping
 *
 * @cond HIDDEN_SYMBOLS
 *
 * @property string $name     // The view handler name.
 * @property string $language // The programming language used to write map and reduce functions.
 * @property string $mapFn    // Stores the map function.
 * @property string $reduceFn // Stores the reduce function.
 *
 * @endcond
 *
 * @todo: Add support for seq_indexed option.
 */
final class ViewHandler extends DesignHandler {
  const MAP_REGEX = '/function\s*\(\s*\$doc\)\s*use\s*\(\$emit\)\s*\{[\W\w]*\};\z/m';
  const MAP_DEFINITION = "function(\$doc) use (\$emit) { ... };";

  const REDUCE_REGEX = '/function\s*\(\s*\$keys\s*,\s*\$values\,\s*\$rereduce\)\s*\{[\W\w]*\};\z/m';
  const REDUCE_DEFINITION = "function(\$keys, \$values, \$rereduce) { ... };";

  private $options = [];

  /** @name Properties */
  //!@{

  //! The view handler name.
  private $name;

  /**
   * @brief The programming language used to write map and reduce functions.
   * @details This property is used mainly by the Couch::queryTempView() method. In fact, the language is taken from
   * the design document where your map and reduce functions have been stored.
   */
  private $language = "";

  /**
   * @brief Stores the map function.
   * @details Contains the function implementation provided by the user. You can have multiple views in a design document
   * and for every single view you can have only one map function. The map function is a closure.
   * The closure must be declared like:
   @code
     function($doc) use ($emit) {
       ...

       $emit($key, $value);
     };
   @endcode
   * To emit your record you must call the `$emit` closure.
   */
  private $mapFn = "";

  /**
   * @brief Stores the reduce function.
   * @details Contains the function implementation provided by the user. You can have multiple views in a design document
   * and for every single view you can have only one reduce function. The reduce function is a closure.
   * The closure must be declared like:
   @code
     function($keys, $values, $rereduce) {
       ...

     };
   @endcode
   */
  private $reduceFn = "";

  //!@}


  /**
   * @brief Creates a ViewHandler class instance.
   * @param[in] string $name Handler name.
   * @param[in] string $language (optional) The map/reduce functions' language.
   */
  public function __construct($name, $language = "php") {
    $this->setName($name);
    $this->setLanguage($language);
  }


  //! @cond HIDDEN_SYMBOLS

  public function getName() {
    return $this->name;
  }


  public function setName($value) {
    $this->name = (string)$value;
  }


  public function setLanguage($value) {
    $this->language = (string)$value;
  }


  public static function getSection() {
    return 'views';
  }

  //! @endcond


  /**
   * @brief Resets the options.
   */
  public function reset() {
    unset($this->options);
    $this->options = [];

    $this->mapFn = "";
    $this->reduceFn = "";
  }


  public function isConsistent() {
    return (!empty($this->name) && !empty($this->mapFn)) ? TRUE : FALSE;
  }


  /**
   * @brief Checks the function definition against a regular expression and use PHP lint to find syntax errors.
   * @param[in] string $fnImpl The function's implementation.
   * @param[in] string $fnDef The function prototype.
   * @param[in] string $fnRegex The regular expression to check the function correctness.
   */
  public static function checkFn($fnImpl, $fnDef, $fnRegex) {
    Lint::checkSourceCode($fnImpl);

    if (!preg_match($fnRegex, $fnImpl))
      throw new \Exception("The \$closure must be defined like: $fnDef");
  }


  public function asArray() {
    $view = [];
    $view['map'] = $this->mapFn;

    if (!empty($this->language))
      $view['language'] = $this->language;

    if (!empty($this->reduceFn))
      $view['reduce'] = $this->reduceFn;

    if (!empty($this->options))
      $view['options'] = $this->options;

    return $view;
  }


  /**
   * @brief Makes documents' local sequence numbers available to map functions as a '_local_seq' document property.
   */
  public function includeLocalSeq() {
    $this->options['local_seq'] = 'true';
  }


  /**
   * @brief Causes map functions to be called on design documents as well as regular documents.
   */
  public function includeDesignDocs() {
    $this->options['include_design'] = 'true';
  }


  /**
   * @brief Sets the reduce function to the built-in `_count` function provided by CouchDB.
   * @details The built-in `_count` reduce function will be probably the most common reduce function you'll use.
   * This function returns the number of mapped values in the set.
   */
  public function useBuiltInReduceFnCount() {
    $this->reduceFn = "_count";
  }


  /**
   * @brief Sets the reduce function to the built-in `_sum` function provided by CouchDB.
   * @details The built-in `_sum` reduce function will return a sum of mapped values. As with all reductions, you
   * can either get a sum of all values grouped by keys or part of keys. You can control this behaviour when you query
   * the view, using an instance of ViewQueryOpts class, in particular with methods ViewQueryOpts::groupResults() and
   * ViewQueryOpts::setGroupLevel().
   * @warning The built-in `_sum` reduce function requires all mapped values to be numbers.
   */
  public function useBuiltInReduceFnSum() {
    $this->reduceFn = "_sum";
  }


  /**
   * @brief Sets the reduce function to the built-in `_stats` function provided by CouchDB.
   * @details The built-in `_stats` reduce function returns an associative array containing the sum, count, minimum,
   * maximum, and sum over all square roots of mapped values.
   */
  public function useBuiltInReduceFnStats() {
    $this->reduceFn = "_stats";
  }


  //! @cond HIDDEN_SYMBOLS

  public function getMapFn() {
    return $this->mapFn;
  }


  public function setMapFn($value) {
    $fn = stripslashes((string)$value);

    if ($this->language == "php")
      self::checkFn($fn, self::MAP_DEFINITION, self::MAP_REGEX);

    $this->mapFn = $fn;
  }


  public function getReduceFn() {
    return $this->reduceFn;
  }


  public function setReduceFn($value) {
    $fn = stripslashes((string)$value);

    if ($this->language == "php")
      self::checkFn($fn, self::REDUCE_DEFINITION, self::REDUCE_REGEX);

    $this->reduceFn = $fn;
  }

  //! @endcond

}