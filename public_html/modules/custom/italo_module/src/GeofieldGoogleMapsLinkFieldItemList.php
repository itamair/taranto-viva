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

                $gMapsLocation = new GMapsLocation();
                $parent_entity = NULL;
                if ($entity instanceof ParagraphInterface) {
                  $parent_entity = $entity->getParentEntity();
                }
                if ($parent_entity instanceof NodeInterface && isset($parent_entity->field_google_maps_address) && $location = $parent_entity->field_google_maps_address->value) {
                  $google_maps_link = $gMapsLocation->location($location);
                }
                else {
                  $google_maps_link = $gMapsLocation->coordinates($geom->y(), $geom->x());
                }
                $value0 = [
                  'uri' => $google_maps_link,
                  'title' => t('Google Maps'),
                ];

                $gMapsStreetView = new GMapsStreetView();
                $value1 = [
                  'uri' => $gMapsStreetView->viewpoint($geom->y(), $geom->x())->get(),
                  'title' => t('Street View'),
                ];
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

}
