<?php

declare(strict_types=1);

namespace Drupal\jsonrpc\Object;

/**
 * Request object to help implement the JSON RPC spec for request objects.
 */
class Request {

  /**
   * The JSON-RPC version.
   *
   * @var string
   */
  protected $version;

  /**
   * The RPC service method id.
   *
   * @var string
   */
  protected $method;

  /**
   * The request parameters, if any.
   *
   * @var \Drupal\jsonrpc\Object\ParameterBag|null
   */
  protected $params;

  /**
   * A string, number or NULL ID. FALSE when an ID was not provided.
   *
   * @var mixed
   */
  protected $id;

  /**
   * Indicates if the request is part of a batch or not.
   *
   * @var bool
   */
  protected $inBatch;

  /**
   * Constructs a JSON-RPC Request object.
   *
   * @param string $version
   *   The JSON-RPC version.
   * @param string $method
   *   The RPC service method id.
   * @param bool $in_batch
   *   Indicates if the request is part of a batch or not.
   * @param mixed $id
   *   A string, number or NULL ID. FALSE for notification requests.
   * @param \Drupal\jsonrpc\Object\ParameterBag|null $params
   *   The request parameters, if any.
   */
  public function __construct($version, $method, $in_batch = FALSE, $id = FALSE, ParameterBag $params = NULL) {
    $this->assertValidRequest($version, $method, $id);
    $this->version = $version;
    $this->method = $method;
    $this->inBatch = $in_batch;
    $this->params = $params;
    $this->id = $id;
  }

  /**
   * Gets the ID.
   *
   * @return mixed
   *   The request id.
   */
  public function id() {
    return $this->id;
  }

  /**
   * Gets the method's name.
   *
   * @return string
   *   The name of the method to execute.
   */
  public function getMethod() {
    return $this->method;
  }

  /**
   * Gets the parameters.
   *
   * @return \Drupal\jsonrpc\Object\ParameterBag|null
   *   The parameter bag.
   */
  public function getParams() {
    return $this->params;
  }

  /**
   * Checks if this is a batched request.
   *
   * @return bool
   *   True if it's a batched request.
   */
  public function isInBatch() {
    return $this->inBatch;
  }

  /**
   * Gets a parameter by key.
   *
   * @param string $key
   *   The key.
   *
   * @return mixed
   *   The parameter or NULL.
   */
  public function getParameter($key) {
    if ($this->hasParams() && ($param_value = $this->getParams()->get($key))) {
      return $param_value;
    }
    return NULL;
  }

  /**
   * Checks if the request has parameters.
   *
   * @return bool
   *   True if it has parameters.
   */
  public function hasParams() {
    return !(is_null($this->params) || $this->params->isEmpty());
  }

  /**
   * Checks if this is a notification request.
   *
   * @return bool
   *   True if it's a notification.
   */
  public function isNotification() {
    return $this->id === FALSE;
  }

  /**
   * Asserts this is a valid request.
   *
   * @param string $version
   *   The JSON-RPC version.
   * @param string $method
   *   The RPC service method id.
   * @param mixed $id
   *   A string, number or NULL ID. FALSE for notification requests.
   */
  protected function assertValidRequest($version, $method, $id) {
    assert($version === "2.0", 'A String specifying the version of the JSON-RPC protocol. MUST be exactly "2.0".');
    assert(!str_starts_with($method, 'rpc.'), 'Method names that begin with the word rpc followed by a period character (U+002E or ASCII 46) are reserved for rpc-internal methods and extensions and MUST NOT be used for anything else.');
    assert($id === FALSE || is_string($id) || is_numeric($id) || is_null($id), 'An identifier established by the Client that MUST contain a String, Number, or NULL value if included.');
  }

}
