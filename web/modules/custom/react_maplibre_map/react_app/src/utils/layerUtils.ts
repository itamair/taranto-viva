import maplibregl from 'maplibre-gl';
import { buildPopupContent } from './geojsonUtils.js';

/**
 * Adds a GeoJSON source to the map.
 * @param {maplibregl.Map} map - The map instance.
 * @param {string} sourceId - The ID for the source.
 * @param {Object} geojsonData - The GeoJSON data.
 */
export function addGeoJsonSource(map, sourceId, geojsonData) {
  if (!map.getSource(sourceId)) {
    map.addSource(sourceId, {
      type: 'geojson',
      data: geojsonData
    });
  }
}

/**
 * Adds polygon layers (fill and outline) to the map.
 * @param {maplibregl.Map} map - The map instance.
 * @param {string} sourceId - The ID of the source.
 * @param {string} layerPrefix - Prefix for layer IDs.
 * @param {Object} style - The polygon style configuration.
 */
export function addPolygonLayers(map, sourceId, layerPrefix, style) {
  const fillLayerId = `${layerPrefix}-polygon-fill`;
  const outlineLayerId = `${layerPrefix}-polygon-outline`;

  // Add fill layer
  if (!map.getLayer(fillLayerId)) {
    map.addLayer({
      id: fillLayerId,
      type: 'fill',
      source: sourceId,
      filter: ['==', '$type', 'Polygon'],
      paint: style.fill
    });
  }

  // Add outline layer
  if (!map.getLayer(outlineLayerId)) {
    map.addLayer({
      id: outlineLayerId,
      type: 'line',
      source: sourceId,
      filter: ['==', '$type', 'Polygon'],
      paint: style.outline
    });
  }

  return [fillLayerId, outlineLayerId];
}

/**
 * Adds a LineString layer to the map.
 * @param {maplibregl.Map} map - The map instance.
 * @param {string} sourceId - The ID of the source.
 * @param {string} layerPrefix - Prefix for layer IDs.
 * @param {Object} style - The linestring style configuration.
 */
export function addLineStringLayer(map, sourceId, layerPrefix, style) {
  const layerId = `${layerPrefix}-linestring`;

  if (!map.getLayer(layerId)) {
    map.addLayer({
      id: layerId,
      type: 'line',
      source: sourceId,
      filter: ['==', '$type', 'LineString'],
      paint: style
    });
  }

  return layerId;
}

/**
 * Adds a Point layer to the map.
 * @param {maplibregl.Map} map - The map instance.
 * @param {string} sourceId - The ID of the source.
 * @param {string} layerPrefix - Prefix for layer IDs.
 * @param {Object} style - The point style configuration.
 */
export function addPointLayer(map, sourceId, layerPrefix, style) {
  const layerId = `${layerPrefix}-points`;

  if (!map.getLayer(layerId)) {
    map.addLayer({
      id: layerId,
      type: 'circle',
      source: sourceId,
      filter: ['==', '$type', 'Point'],
      paint: style
    });
  }

  return layerId;
}

/**
 * Adds all layers for a GeoJSON source.
 * @param {maplibregl.Map} map - The map instance.
 * @param {string} sourceId - The ID of the source.
 * @param {string} layerPrefix - Prefix for layer IDs.
 * @param {Object} styles - The layer styles configuration.
 * @returns {string[]} Array of created layer IDs.
 */
export function addAllLayers(map, sourceId, layerPrefix, styles) {
  const layerIds = [];

  // Add polygon layers
  const polygonLayers = addPolygonLayers(map, sourceId, layerPrefix, styles.polygon);
  layerIds.push(...polygonLayers);

  // Add linestring layer
  const linestringLayer = addLineStringLayer(map, sourceId, layerPrefix, styles.linestring);
  layerIds.push(linestringLayer);

  // Add point layer
  const pointLayer = addPointLayer(map, sourceId, layerPrefix, styles.point);
  layerIds.push(pointLayer);

  return layerIds;
}

/**
 * Adds interactive handlers (cursor and popups) to layers.
 * @param {maplibregl.Map} map - The map instance.
 * @param {string[]} layerIds - Array of layer IDs to add handlers to.
 */
export function addLayerInteractivity(map, layerIds) {
  layerIds.forEach(layerId => {
    // Change cursor on hover
    map.on('mouseenter', layerId, () => {
      map.getCanvas().style.cursor = 'pointer';
    });

    map.on('mouseleave', layerId, () => {
      map.getCanvas().style.cursor = '';
    });

    // Add click handler for popups
    map.on('click', layerId, (e) => {
      if (e.features && e.features.length > 0) {
        const feature = e.features[0];
        const properties = feature.properties;

        // Check if popup should be disabled
        if (properties.field_map_popup_disabled != 1) {
          const popupContent = buildPopupContent(properties);

          new maplibregl.Popup()
            .setLngLat(e.lngLat)
            .setHTML(popupContent)
            .addTo(map);
        }
      }
    });
  });
}

/**
 * Removes layers and source from the map.
 * @param {maplibregl.Map} map - The map instance.
 * @param {string} sourceId - The ID of the source to remove.
 * @param {string[]} layerIds - Array of layer IDs to remove.
 */
export function removeLayers(map, sourceId, layerIds) {
  layerIds.forEach(layerId => {
    if (map.getLayer(layerId)) {
      map.removeLayer(layerId);
    }
  });

  if (map.getSource(sourceId)) {
    map.removeSource(sourceId);
  }
}
