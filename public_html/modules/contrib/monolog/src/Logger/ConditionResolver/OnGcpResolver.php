<?php

declare(strict_types=1);

namespace Drupal\monolog\Logger\ConditionResolver;

/**
 * A resolver to check if we're on GCP or not.
 */
class OnGcpResolver implements ConditionResolverInterface {

  /**
   * OnGcpResolver constructor.
   *
   * @param bool $on_gcp
   *   Indicates if we're on GCP or not.
   */
  public function __construct(private readonly bool $on_gcp) {
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(): bool {
    return $this->on_gcp === TRUE;
  }

}
