import maplibregl from 'maplibre-gl';
import { buildPopupContent } from './geojsonUtils.js';

// Function to capitalize the first letter.q
function capitalizeFirstLetter(string: string) {
  return string.charAt(0).toUpperCase() + string.slice(1)
}

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
    map.on('mouseenter', layerId, (e: any) => {
      if (e.features && e.features.length > 0) {
        const feature = e.features[0];
        if ((feature._vectorTileFeature && feature._vectorTileFeature.properties['@name']) || feature.properties.name) {
          map.getCanvas().style.cursor = 'pointer';
        }
      }
    });

    map.on('mouseleave', layerId, () => {
      map.getCanvas().style.cursor = '';
    });

    // Add click handler for pop  ups
    map.on('click', layerId, (e: any) => {
      if (e.features && e.features.length > 0) {
        const feature = e.features[0];
        if ((feature._vectorTileFeature && feature._vectorTileFeature.properties['@name']) || feature.properties.name) {
          let properties: Record<string, any> = {};
          if (feature._vectorTileFeature.properties['@name']) {
            properties = {
              'name': feature._vectorTileFeature.properties['@name'],
              'field_map_popup_disabled': 0,
              'confidence': parseFloat(feature._vectorTileFeature.properties['confidence']).toFixed(2),
            }

            // Eventually set primary category.
            const category = feature._vectorTileFeature.properties['categories'] ? JSON.parse(feature._vectorTileFeature.properties['categories']) : null;
            if (category && category.primary.length > 0) {
              properties.category = capitalizeFirstLetter(category.primary.replaceAll('_', ' '));
            }

            // Eventually set website address.
            const websites = feature._vectorTileFeature.properties['websites'] ? JSON.parse(feature._vectorTileFeature.properties['websites']) : [];
            if (websites.length > 0) {
              properties.websites = '';
              for (const website of websites) {
                properties.websites += '<a href=' + website + ' target="_blank">' + '&rarr; website' + '<a>';
              }
            }

          } else if (feature.properties) {
            properties = feature.properties;
          }

          // Check if popup should be disabled
          if (properties['field_map_popup_disabled'] != 1) {
            // Close existing popup if any
            if ((map as any)._customMarkerPopup) {
              (map as any)._customMarkerPopup.remove();
            }

            // Get feature coordinates and key
            let featureKey = null;
            let hadPermanentTooltip = false;
            if (feature.geometry && feature.geometry.type === 'Point') {
              const coords = feature.geometry.coordinates;
              featureKey = `${coords[0].toFixed(6)},${coords[1].toFixed(6)}`;

              // Close any permanent tooltip for this feature
              if (properties.field_tooltip_permanent == "1" && (map as any)._allPermanentTooltips && (map as any)._allPermanentTooltips.has(featureKey)) {
                const tooltipData = (map as any)._allPermanentTooltips.get(featureKey);
                if (tooltipData && tooltipData.tooltip) {
                  tooltipData.tooltip.remove();
                  hadPermanentTooltip = true;
                }
              }
            }

            // Close any hover tooltip
            if ((map as any)._hoverTooltip) {
              (map as any)._hoverTooltip.remove();
              (map as any)._hoverTooltip = null;
            }

            const popupContent = buildPopupContent(properties);
            const popup = new maplibregl.Popup()
              .setLngLat(e.lngLat)
              .setHTML(popupContent)
              .addTo(map);

            // Store reference to current popup and which feature it's for
            (map as any)._customMarkerPopup = popup;
            (map as any)._popupFeatureKey = featureKey;

            // Clear reference when popup is closed
            popup.on('close', () => {
              (map as any)._customMarkerPopup = null;
              (map as any)._popupFeatureKey = null;

              // Reopen permanent tooltip if it had one
              if (hadPermanentTooltip && featureKey && (map as any)._allPermanentTooltips.has(featureKey)) {
                const tooltipData = (map as any)._allPermanentTooltips.get(featureKey);
                const newTooltip = new maplibregl.Popup({
                  closeButton: false,
                  closeOnClick: false,
                  closeOnMove: false,
                  className: 'tooltip permanent-tooltip'
                })
                  .setLngLat(tooltipData.coordinates)
                  .setHTML(`<div style="font-size: 14px; padding: 2px 6px; font-weight: bold;">${tooltipData.name}</div>`)
                  .addTo(map);

                // Update reference
                tooltipData.tooltip = newTooltip;
                (map as any)._allPermanentTooltips.set(featureKey, tooltipData);
              }
            });
          }
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

          // Get feature key for tracking
          const featureKey = `${coordinates[0].toFixed(6)},${coordinates[1].toFixed(6)}`;

          // Close any permanent tooltip for this feature
          let hadPermanentTooltip = false;
          if ((map as any)._allPermanentTooltips && (map as any)._allPermanentTooltips.has(featureKey)) {
            const tooltipData = (map as any)._allPermanentTooltips.get(featureKey);
            if (tooltipData && tooltipData.tooltip) {
              tooltipData.tooltip.remove();
              hadPermanentTooltip = true;
            }
          }

          // Close any hover tooltip
          if ((map as any)._hoverTooltip) {
            (map as any)._hoverTooltip.remove();
            (map as any)._hoverTooltip = null;
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

          // Store reference to current popup and which feature it's for
          (map as any)._customMarkerPopup = popup;
          (map as any)._popupFeatureKey = featureKey;

          // Clear reference when popup is closed
          popup.on('close', () => {
            (map as any)._customMarkerPopup = null;
            (map as any)._popupFeatureKey = null;

            // Reopen permanent tooltip if it had one
            if (hadPermanentTooltip && (map as any)._allPermanentTooltips.has(featureKey)) {
              const tooltipData = (map as any)._allPermanentTooltips.get(featureKey);
              const newTooltip = new maplibregl.Popup({
                closeButton: false,
                closeOnClick: false,
                closeOnMove: false,
                className: 'tooltip permanent-tooltip',
                offset: [0, -10]
              })
                .setLngLat(coordinates)
                .setHTML(`<div style="font-size: 14px; padding: 2px 6px; font-weight: bold;">${tooltipData.name}</div>`)
                .addTo(map);

              // Update reference
              tooltipData.tooltip = newTooltip;
              (map as any)._allPermanentTooltips.set(featureKey, tooltipData);
            }
          });
        });
      }

      // Add hover tooltip for markers with field_tooltip_permanent != "1"
      if (
        Object.prototype.hasOwnProperty.call(feature.properties, "field_tooltip_permanent") &&
        feature.properties?.field_tooltip_permanent != "1" &&
        feature.properties.name
      ) {
        let hoverTooltip: maplibregl.Popup | null = null;

        containerDiv.addEventListener('mouseenter', () => {
          // Don't show hover tooltip if a popup is open for this feature
          const featureKey = `${coordinates[0].toFixed(6)},${coordinates[1].toFixed(6)}`;
          if ((map as any)._popupFeatureKey === featureKey) {
            return;
          }

          // Remove existing hover tooltip if any
          if ((map as any)._hoverTooltip) {
            (map as any)._hoverTooltip.remove();
          }

          hoverTooltip = new maplibregl.Popup({
            closeButton: false,
            closeOnClick: false,
            className: 'tooltip hover-tooltip'
          })
            .setLngLat(coordinates)
            .setHTML(`<div style="font-size: 14px; padding: 2px 6px; font-weight: bold;">${feature.properties.name}</div>`)
            .addTo(map);

          (map as any)._hoverTooltip = hoverTooltip;
        });

        containerDiv.addEventListener('mouseleave', () => {
          if (hoverTooltip) {
            hoverTooltip.remove();
            hoverTooltip = null;
          }
          if ((map as any)._hoverTooltip) {
            (map as any)._hoverTooltip = null;
          }
        });
      }

      // Add permanent tooltip for markers with field_tooltip_permanent = "1"
      if (feature.properties.field_tooltip_permanent == "1" && feature.properties.name) {
        // Initialize permanent tooltips storage if needed
        if (!(map as any)._allPermanentTooltips) {
          (map as any)._allPermanentTooltips = new Map();
        }

        const permanentTooltip = new maplibregl.Popup({
          closeButton: false,
          closeOnClick: false,
          closeOnMove: false,
          className: 'tooltip permanent-tooltip',
          offset: [0, -10] // Offset above the marker
        })
          .setLngLat(coordinates)
          .setHTML(`<div style="font-size: 14px; padding: 2px 6px; font-weight: bold;">${feature.properties.name}</div>`)
          .addTo(map);

        // Store reference on marker for potential cleanup
        (marker as any)._permanentTooltip = permanentTooltip;

        // Also store in global map for popup interaction
        const key = `${coordinates[0].toFixed(6)},${coordinates[1].toFixed(6)}`;
        (map as any)._allPermanentTooltips.set(key, {
          tooltip: permanentTooltip,
          name: feature.properties.name,
          coordinates
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
    // Remove permanent tooltip if attached to marker
    if ((marker as any)._permanentTooltip) {
      (marker as any)._permanentTooltip.remove();
    }
    marker.remove();
  });
}

/**
 * Creates permanent tooltips for point features that have field_tooltip_permanent = "1".
 * @param {maplibregl.Map} map - The map instance.
 * @param {Object} geojsonData - The GeoJSON data.
 * @returns {maplibregl.Popup[]} Array of created permanent tooltips.
 */
export function addPermanentTooltips(
  map: maplibregl.Map,
  geojsonData: any
): maplibregl.Popup[] {
  const tooltips: maplibregl.Popup[] = [];

  if (!geojsonData || !geojsonData.features) {
    return tooltips;
  }

  // Initialize permanent tooltips storage if needed
  if (!(map as any)._allPermanentTooltips) {
    (map as any)._allPermanentTooltips = new Map();
  }

  geojsonData.features.forEach((feature: any) => {
    // Only process Point features with field_tooltip_permanent = "1"
    if (
      feature.geometry.type === 'Point' &&
      feature.properties.field_tooltip_permanent == "1" &&
      feature.properties.name
    ) {
      const coordinates = feature.geometry.coordinates as [number, number];
      const name = feature.properties.name;

      // Create a permanent tooltip
      const tooltip = new maplibregl.Popup({
        closeButton: false,
        closeOnClick: false,
        closeOnMove: false,
        className: 'tooltip permanent-tooltip'
      })
        .setLngLat(coordinates)
        .setHTML(`<div style="font-size: 14px; padding: 2px 6px; font-weight: bold;">${name}</div>`)
        .addTo(map);

      // Store in both the array and the map for lookup
      const key = `${coordinates[0].toFixed(6)},${coordinates[1].toFixed(6)}`;
      (map as any)._allPermanentTooltips.set(key, { tooltip, name, coordinates });

      tooltips.push(tooltip);
    }
  });

  return tooltips;
}

/**
 * Removes permanent tooltips from the map.
 * @param {maplibregl.Popup[]} tooltips - Array of tooltips to remove.
 */
export function removePermanentTooltips(tooltips: maplibregl.Popup[]): void {
  tooltips.forEach((tooltip: maplibregl.Popup) => {
    tooltip.remove();
  });
}

/**
 * Adds hover tooltip functionality to point layers for features with field_tooltip_permanent = "0".
 * Tooltips appear on hover and disappear when mouse leaves.
 * @param {maplibregl.Map} map - The map instance.
 * @param {string} layerId - The layer ID to add hover tooltips to.
 */
export function addHoverTooltips(
  map: maplibregl.Map,
  layerId: string
): void {
  // Store hover tooltip reference on the map object
  if (!(map as any)._hoverTooltip) {
    (map as any)._hoverTooltip = null;
  }

  // Handle mouse move to show tooltip
  map.on('mousemove', layerId, (e: any) => {
    if (e.features && e.features.length > 0) {
      const feature = e.features[0];

      // Only show tooltip for features with field_tooltip_permanent = "0"
      if (
        Object.prototype.hasOwnProperty.call(feature.properties, "field_tooltip_permanent") &&
        feature.properties.field_tooltip_permanent != "1" &&
        feature.properties.name
      ) {
        // Don't show hover tooltip if a popup is open for this feature
        let featureKey = null;
        if (feature.geometry && feature.geometry.type === 'Point') {
          const coords = feature.geometry.coordinates;
          featureKey = `${coords[0].toFixed(6)},${coords[1].toFixed(6)}`;
        }

        if (featureKey && (map as any)._popupFeatureKey === featureKey) {
          return;
        }

        map.getCanvas().style.cursor = 'pointer';

        // Remove existing hover tooltip if any
        if ((map as any)._hoverTooltip) {
          (map as any)._hoverTooltip.remove();
        }

        // Create new hover tooltip
        const tooltip = new maplibregl.Popup({
          closeButton: false,
          closeOnClick: false,
          className: 'tooltip hover-tooltip'
        })
          .setLngLat(e.lngLat)
          .setHTML(`<div style="font-size: 14px; padding: 2px 6px; font-weight: bold;">${feature.properties.name}</div>`)
          .addTo(map);

        (map as any)._hoverTooltip = tooltip;
      }
    }
  });

  // Handle mouse leave to remove tooltip
  map.on('mouseleave', layerId, () => {
    map.getCanvas().style.cursor = '';

    // Remove hover tooltip
    if ((map as any)._hoverTooltip) {
      (map as any)._hoverTooltip.remove();
      (map as any)._hoverTooltip = null;
    }
  });
}

/**
 * Removes hover tooltip event listeners from a layer.
 * @param {maplibregl.Map} map - The map instance.
 * @param {string} _layerId - The layer ID (unused, kept for API consistency).
 */
export function removeHoverTooltips(
  map: maplibregl.Map,
  _layerId: string
): void {
  // Remove hover tooltip if exists
  if ((map as any)._hoverTooltip) {
    (map as any)._hoverTooltip.remove();
    (map as any)._hoverTooltip = null;
  }

  // Note: We don't explicitly remove the event listeners here because
  // they will be removed when the layer is removed from the map
}
