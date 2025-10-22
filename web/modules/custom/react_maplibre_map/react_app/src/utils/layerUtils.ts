import maplibregl from 'maplibre-gl';
import { buildPopupContent } from './geojsonUtils.js';

/**
 * Adds a GeoJSON source to the map.
 * @param {maplibregl.Map} map - The map instance.
 * @param {string} sourceId - The ID for the source.
 * @param {Object} geojsonData - The GeoJSON data.
 */
export function addGeoJsonSource(map: maplibregl.Map, sourceId: string, geojsonData: any): void {
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
export function addPolygonLayers(map: maplibregl.Map, sourceId: string, layerPrefix: string, style: any): string[] {
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
export function addLineStringLayer(map: maplibregl.Map, sourceId: string, layerPrefix: string, style: any): string {
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
 * Only renders points that don't have custom marker icons.
 * @param {maplibregl.Map} map - The map instance.
 * @param {string} sourceId - The ID of the source.
 * @param {string} layerPrefix - Prefix for layer IDs.
 * @param {Object} style - The point style configuration.
 */
export function addPointLayer(map: maplibregl.Map, sourceId: string, layerPrefix: string, style: any): string {
  const layerId = `${layerPrefix}-points`;

  if (!map.getLayer(layerId)) {

    map.addLayer({
      id: layerId,
      type: 'circle',
      source: sourceId,
      // Filter to only show points without custom marker icons
      filter: import.meta.env.VITE_CUSTOM_MARKERS == 1 ? [
        'all',
        ['==', '$type', 'Point'],
        ['!has', 'geomarker_icon_url']
      ] : [
        'all',
        ['==', '$type', 'Point']
      ],
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
export function addAllLayers(map: maplibregl.Map, sourceId: string, layerPrefix: string, styles: any): string[] {
  const layerIds: string[] = [];

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
export function addLayerInteractivity(map: maplibregl.Map, layerIds: string[]): void {
  // Initialize popup reference if not exists
  if (!(map as any)._customMarkerPopup) {
    (map as any)._customMarkerPopup = null;
  }

  layerIds.forEach((layerId: string) => {
    // Change cursor on hover
    map.on('mouseenter', layerId, () => {
      map.getCanvas().style.cursor = 'pointer';
    });

    map.on('mouseleave', layerId, () => {
      map.getCanvas().style.cursor = '';
    });

    // Add click handler for popups
    map.on('click', layerId, (e: any) => {
      if (e.features && e.features.length > 0) {
        const feature = e.features[0];
        const properties = feature.properties;

        // Check if popup should be disabled
        if (properties.field_map_popup_disabled != 1) {
          // Close existing popup if any
          if ((map as any)._customMarkerPopup) {
            (map as any)._customMarkerPopup.remove();
          }

          const popupContent = buildPopupContent(properties);
          const popup = new maplibregl.Popup()
            .setLngLat(e.lngLat)
            .setHTML(popupContent)
            .addTo(map);

          // Store reference to current popup
          (map as any)._customMarkerPopup = popup;

          // Clear reference when popup is closed
          popup.on('close', () => {
            (map as any)._customMarkerPopup = null;
          });
        }
      }
    });
  });
}

/**
 * Creates custom HTML markers for features with geomarker_icon_url property.
 * @param {maplibregl.Map} map - The map instance.
 * @param {Object} geojsonData - The GeoJSON data.
 * @param {number} containerWidth - Width of the marker container (30px or 80px).
 * @returns {maplibregl.Marker[]} Array of created markers.
 */
export function addCustomMarkers(
  map: maplibregl.Map,
  geojsonData: any,
  containerWidth: number
): maplibregl.Marker[] {
  const markers: maplibregl.Marker[] = [];

  if (!geojsonData || !geojsonData.features) {
    return markers;
  }

  // Store reference to current popup on the map object to manage single popup
  if (!(map as any)._customMarkerPopup) {
    (map as any)._customMarkerPopup = null;
  }

  geojsonData.features.forEach((feature: any) => {
    // Only process Point features with geomarker_icon_url
    if (
      feature.geometry.type === 'Point' &&
      feature.properties.geomarker_icon_url &&
      feature.properties.geomarker_icon_url !== null &&
      feature.properties.geomarker_icon_url !== ''
    ) {
      const coordinates = feature.geometry.coordinates as [number, number];
      const iconUrl = feature.properties.geomarker_icon_url;
      const title = feature.properties.name || '';

      // Create a container div with the specified width
      const containerDiv = document.createElement('div');
      containerDiv.style.width = `${containerWidth}px`;
      containerDiv.style.display = 'flex';
      containerDiv.style.justifyContent = 'center';
      containerDiv.style.alignItems = 'flex-end';
      containerDiv.style.cursor = 'pointer';

      // Create the image element
      const img = document.createElement('img');
      img.src = iconUrl;
      img.alt = title;
      img.title = title;
      img.style.maxWidth = '100%';
      img.style.height = 'auto';
      img.style.display = 'block';

      // Handle image load errors
      img.onerror = () => {
        console.error(`Failed to load marker icon: ${iconUrl}`);
        // You could set a fallback icon here if desired
      };

      containerDiv.appendChild(img);

      // Create the marker
      const marker = new maplibregl.Marker({
        element: containerDiv,
        anchor: 'bottom'
      })
        .setLngLat(coordinates)
        .addTo(map);

      // Add click handler for popup if enabled
      if (feature.properties.field_map_popup_disabled != 1) {
        containerDiv.addEventListener('click', (e) => {
          e.stopPropagation(); // Prevent event from bubbling to map

          // Close existing popup if any
          if ((map as any)._customMarkerPopup) {
            (map as any)._customMarkerPopup.remove();
          }

          const popupContent = buildPopupContent(feature.properties);
          const popup = new maplibregl.Popup({
            closeButton: true,
            closeOnClick: true,
            maxWidth: '300px'
          })
            .setLngLat(coordinates)
            .setHTML(popupContent)
            .addTo(map);

          // Store reference to current popup
          (map as any)._customMarkerPopup = popup;

          // Clear reference when popup is closed
          popup.on('close', () => {
            (map as any)._customMarkerPopup = null;
          });
        });
      }

      markers.push(marker);
    }
  });

  return markers;
}

/**
 * Removes layers and source from the map.
 * @param {maplibregl.Map} map - The map instance.
 * @param {string} sourceId - The ID of the source to remove.
 * @param {string[]} layerIds - Array of layer IDs to remove.
 */
export function removeLayers(map: maplibregl.Map, sourceId: string, layerIds: string[]): void {
  layerIds.forEach((layerId: string) => {
    if (map.getLayer(layerId)) {
      map.removeLayer(layerId);
    }
  });

  if (map.getSource(sourceId)) {
    map.removeSource(sourceId);
  }
}

/**
 * Removes custom markers from the map.
 * @param {maplibregl.Marker[]} markers - Array of markers to remove.
 */
export function removeCustomMarkers(markers: maplibregl.Marker[]): void {
  markers.forEach((marker: maplibregl.Marker) => {
    marker.remove();
  });
}
