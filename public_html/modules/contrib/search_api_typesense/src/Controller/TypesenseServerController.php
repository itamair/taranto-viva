<?php

declare(strict_types=1);

namespace Drupal\search_api_typesense\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\search_api\ServerInterface;
use Drupal\search_api_typesense\Api\SearchApiTypesenseException;
use Drupal\search_api_typesense\Api\SearchApiTypesenseRequestUnauthorizedException;
use Drupal\search_api_typesense\Plugin\search_api\backend\SearchApiTypesenseBackend;

/**
 * Controller for Typesense server operations.
 */
class TypesenseServerController extends ControllerBase {

  /**
   * Render metrics information.
   *
   * @param \Drupal\search_api\ServerInterface $search_api_server
   *   The server.
   *
   * @return array
   *   The render array.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function metrics(ServerInterface $search_api_server): array {
    $backend = $search_api_server->getBackend();
    if (!$backend instanceof SearchApiTypesenseBackend) {
      throw new SearchApiTypesenseException('The server must use the Typesense backend.');
    }

    if (!$backend->isAvailable()) {
      $this->messenger()->addError(
        $this->t('The Typesense server is not available.'),
      );

      return [];
    }

    try {
      $metrics = $backend->getTypesenseClient()->retrieveMetrics();
    }
    catch (SearchApiTypesenseRequestUnauthorizedException $e) {
      $this->messenger()->addError($e->getMessage());

      return [];
    }

    $header = [];
    $row = [];

    $cpu_cores = $this->countCpuCores($metrics);
    for ($i = 1; $i <= $cpu_cores; $i++) {
      $header[] = $this->t('CPU Core @core', ['@core' => $i]);
      $row[] = $metrics['system_cpu' . $i . '_active_percentage'];
    }

    $header[] = $this->t('Cumulate CPU');
    $row[] = $metrics['system_cpu_active_percentage'];

    $build = [];

    $build['cpu'] = [
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => [$row],
    ];

    $header = [];
    $row = [];
    $header[] = $this->t('Memory');
    $row[] = $this->readableBytes(\intval($metrics['system_memory_used_bytes'])) . ' / ' . $this->readableBytes(\intval($metrics['system_memory_total_bytes']));

    $header[] = $this->t('Memory active');
    $row[] = $this->readableBytes(\intval($metrics['typesense_memory_active_bytes']));

    $header[] = $this->t('Memory allocated');
    $row[] = $this->readableBytes(\intval($metrics['typesense_memory_allocated_bytes']));

    $header[] = $this->t('Memory fragmentation');
    $row[] = $this->readableBytes(\intval($metrics['typesense_memory_fragmentation_ratio']));

    $header[] = $this->t('Memory mapped');
    $row[] = $this->readableBytes(\intval($metrics['typesense_memory_mapped_bytes']));

    $header[] = $this->t('Memory metadata');
    $row[] = $this->readableBytes(\intval($metrics['typesense_memory_metadata_bytes']));

    $header[] = $this->t('Memory resident');
    $row[] = $this->readableBytes(\intval($metrics['typesense_memory_resident_bytes']));

    $header[] = $this->t('Memory retained');
    $row[] = $this->readableBytes(\intval($metrics['typesense_memory_retained_bytes']));

    $build['memory'] = [
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => [$row],
    ];

    $header = [];
    $row = [];
    $header[] = $this->t('Disk');
    $row[] = $this->readableBytes(\intval($metrics['system_disk_used_bytes'])) . ' / ' . $this->readableBytes(\intval($metrics['system_disk_total_bytes']));

    $header[] = $this->t('Network received');
    $row[] = $this->readableBytes(\intval($metrics['system_network_received_bytes']));

    $header[] = $this->t('Network sent');
    $row[] = $this->readableBytes(\intval($metrics['system_network_sent_bytes']));

    $build['other'] = [
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => [$row],
    ];

    return $build;
  }

  /**
   * Count the number of CPU cores.
   *
   * @param array $metrics
   *   The metrics.
   *
   * @return int
   *   The number of CPU cores.
   */
  private function countCpuCores(array $metrics): int {
    $cores = 0;
    foreach ($metrics as $key => $value) {
      if (\str_starts_with($key, 'system_cpu')) {
        $cores++;
      }
    }

    return $cores - 1;
  }

  /**
   * Convert bytes to human-readable format.
   *
   * @param int $bytes
   *   The bytes.
   *
   * @return string
   *   The human-readable format.
   */
  private function readableBytes(int $bytes): string {
    $unitDecimalsByFactor = [
      ['B', 0],
      ['kB', 0],
      ['MB', 2],
      ['GB', 2],
      ['TB', 3],
      ['PB', 3],
    ];

    $factor = $bytes > 0 ? \floor(\log($bytes, 1024)) : 0;
    $factor = \min($factor, \count($unitDecimalsByFactor) - 1);

    $value = \round($bytes / \pow(1024, $factor),
      $unitDecimalsByFactor[$factor][1]);
    $units = $unitDecimalsByFactor[$factor][0];

    return $value . $units;
  }

}
