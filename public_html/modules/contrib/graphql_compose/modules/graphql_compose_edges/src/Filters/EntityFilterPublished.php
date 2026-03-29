<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_edges\Filters;

use Drupal\Core\Entity\Query\QueryInterface;

/**
 * Filter the query to published only.
 */
class EntityFilterPublished extends EdgeFilterBase {

  /**
   * {@inheritdoc}
   */
  public function apply(QueryInterface $query): void {
    $published_field = $this->getQueryHelper()->getEntityType()->getKey('published');

    if ($published_field && $this->shouldApply()) {
      $query->condition($published_field, TRUE);
    }
  }

  /**
   * Check if only published entities should be returned.
   *
   * @return bool
   *   Whether only published entities should be returned.
   */
  protected function shouldApply(): bool {

    // Explicitly exclude unpublished entities.
    $exclude = $this->configFactory()
      ->get('graphql_compose.settings')
      ->get('settings.exclude_unpublished');

    // Check user permissions.
    if (!$exclude) {
      $permissions = [
        'view any unpublished content',
        'view any unpublished ' . $this->getQueryHelper()->getEntityTypeId(),
      ];

      $permissions_check = array_filter(
        $permissions,
        $this->currentUser()->hasPermission(...)
      );

      $exclude = empty($permissions_check);
    }

    return $exclude;
  }

}
