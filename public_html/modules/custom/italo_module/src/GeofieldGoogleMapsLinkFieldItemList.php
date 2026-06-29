<?php

namespace Drupal\italo_module;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use pschocke\GoogleMapsLinks\GMapsLocation;
use pschocke\GoogleMapsLinks\GMapsStreetView;

/**
 * Generates a GeofieldGoogleMapsLinkFieldItemList.
 */
class GeofieldGoogleMapsLinkFieldItemList extends FieldItemList {

  use ComputedItemListTrait;

  /**
   * Whether the value has been calculated.
   *
   * @var bool
   */
  protected bool $isCalculated = FALSE;

  /**
   * {@inheritdoc}
   *
   * Generate the Value for the Geo Marker Icon Url Path.
   */
  protected function computeValue(): void {
    if (!$this->isCalculated) {
      $entity = $this->getEntity();
      $value0 = '';
      $value1 = '';
      if ($entity instanceof ParagraphInterface || $entity instanceof MediaInterface) {
        $entity_type = $entity->bundle();
        switch ($entity_type) {
          case "geoimage":
          case "location":
            /** @var \Drupal\geofield\GeoPHP\GeoPHPWrapper $geo_php_wrapper */
            $geo_php_wrapper = \Drupal::service('geofield.geophp');
            $geofield = isset($entity->field_geofield) ? $entity->field_geofield->value : NULL;
            if ($geofield) {
              /** @var \Geometry|null $geom */
              $geom = $geo_php_wrapper->load($geofield);
              if ($geom) {
                // If the geometry is not a point, get the centroid.
                if ($geom->getGeomType() != 'Point') {
                  $geom = $geom->centroid();
                }

                $lat = $geom->y();
                $lng = $geom->x();

                $gMapsLocation = new GMapsLocation();
                $parent_entity = NULL;
                if ($entity instanceof ParagraphInterface) {
                  $parent_entity = $entity->getParentEntity();
                }
                if ($parent_entity instanceof NodeInterface && isset($parent_entity->field_google_maps_address) && $location = $parent_entity->field_google_maps_address->value) {
                  $google_maps_link = $gMapsLocation->location($location);
                }
                else {
                  $google_maps_link = $gMapsLocation->coordinates($lat, $lng);
                }
                $value0 = [
                  'uri' => $google_maps_link,
                  'title' => t('Google Maps'),
                ];

                if ($this->hasStreetViewCoverage($lat, $lng)) {
                  $gMapsStreetView = new GMapsStreetView();
                  $value1 = [
                    'uri' => $gMapsStreetView->viewpoint($lat, $lng)->get(),
                    'title' => t('Street View'),
                  ];
                }
              }
            }
            break;
        }
      }
      $this->list[0] = $this->createItem(0, $value0);
      $this->list[1] = $this->createItem(1, $value1);
      $this->isCalculated = TRUE;
    }
  }

  /**
   * Checks Street View Metadata API for coverage at the given coordinates.
   *
   * Results are cached for 30 days to avoid redundant API calls on repeated
   * entity loads. Coordinates are rounded to 5 decimal places (~1 m) so that
   * near-identical points share the same cache entry.
   *
   * @param float $lat
   *   Latitude.
   * @param float $lng
   *   Longitude.
   *
   * @return bool
   *   TRUE if Street View imagery is available at this location.
   */
  private function hasStreetViewCoverage(float $lat, float $lng): bool {
    $lat_r = round($lat, 5);
    $lng_r = round($lng, 5);
    $cid = 'italo_module:streetview:' . $lat_r . ':' . $lng_r;

    $cached = \Drupal::cache()->get($cid);
    if ($cached !== FALSE) {
      return (bool) $cached->data;
    }

    $api_key = \Drupal::config('geofield_map.settings')->get('gmap_api_key');
    $available = FALSE;

    try {
      $response = \Drupal::httpClient()->get(
        'https://maps.googleapis.com/maps/api/streetview/metadata',
        [
          'query' => [
            'location' => $lat_r . ',' . $lng_r,
            'key' => $api_key,
          ],
          'timeout' => 1000,
        ]
      );
      $data = json_decode((string) $response->getBody(), TRUE);
      $available = ($data['status'] ?? '') === 'OK';
    }
    catch (\Throwable $e) {
      \Drupal::logger('italo_module')->warning(
        'Street View metadata check failed for @lat,@lng: @msg',
        ['@lat' => $lat_r, '@lng' => $lng_r, '@msg' => $e->getMessage()]
      );
    }

    // Cache for 30 days — Street View coverage changes infrequently.
    \Drupal::cache()->set($cid, $available, time() + 86400 * 30);

    return $available;
  }

}
