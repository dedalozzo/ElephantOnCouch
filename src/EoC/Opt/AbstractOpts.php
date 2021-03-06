<?php

/**
 * @file AbstractOpts.php
 * @brief This file contains the AbstractOpts class.
 * @details
 * @author Filippo F. Fadda
 */


//! The CouchDB's options namespace.
namespace EoC\Opt;


/**
 * @brief Superclass of all options classes.
 * @nosubgrouping
 */
abstract class AbstractOpts {

  protected $options = [];


  /**
   * @brief Resets the options.
   */
  public function reset() {
    unset($this->options);
    $this->options = [];
  }


  /**
   * @brief Returns an associative array of the chosen options.
   * @return array An associative array.
   */
  public function asArray() {
    return $this->options;
  }

}