<?php

namespace Drupal\warmer_cdn\Plugin\warmer;

use Drupal\warmer\Plugin\WarmerPluginBase;
use Drupal\warmer\Plugin\WarmerPluginManager;

trait CdnDependencyTrait {

  /**
   * The CDN warmer.
   *
   * @var \Drupal\warmer_cdn\Plugin\warmer\CdnWarmer
   */
  protected $warmer;

  /**
   * The warmer manager.
   *
   * @var \Drupal\warmer\Plugin\WarmerPluginManager
   */
  protected $warmerManager;

  /**
   * Set the warmer manager.
   *
   * @param \Drupal\warmer\Plugin\WarmerPluginManager $warmer_manager
   *   The warmer manager.
   */
  public function setWarmerManager(WarmerPluginManager $warmer_manager) {
    $this->warmerManager = $warmer_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(array $ids = []) {
    return $this->cdnWarmer()->loadMultiple($ids);
  }

  /**
   * {@inheritdoc}
   */
  public function warmMultiple(array $items = []) {
    return $this->cdnWarmer()->warmMultiple($items);
  }

  /**
   * Lazily get the CDN warmer.
   *
   * @return \Drupal\warmer_cdn\Plugin\warmer\CdnWarmer
   *   The CDN warmer.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function cdnWarmer() {
    if ($this->warmer instanceof CdnWarmer) {
      return $this->warmer;
    }
    $configuration = $this->getConfiguration();
    $warmer = $this->warmerManager->createInstance('cdn', [
      'headers' => $configuration['headers'],
      'verify' => $configuration['verify'],
      'maxConcurrentRequests' => $configuration['maxConcurrentRequests'],
    ]);
    assert($warmer instanceof CdnWarmer);
    $this->warmer = $warmer;
    return $warmer;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'verify' => TRUE,
      'maxConcurrentRequests' => 10,
    ] + WarmerPluginBase::defaultConfiguration();
  }

}
