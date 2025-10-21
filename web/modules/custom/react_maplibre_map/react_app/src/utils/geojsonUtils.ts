import maplibregl from 'maplibre-gl';

/**
 * Fetches GeoJSON data from a URL.
 * @param {string} url - The URL to fetch GeoJSON from.
 * @returns {Promise<Object>} The GeoJSON data.
 * @throws {Error} If the fetch fails.
 */
export async function fetchGeoJson(url: string): Promise<any> {
  const response = await fetch(url);
  if (!response.ok) {
    throw new Error(`Failed to fetch GeoJSON from ${url}: ${response.status}`);
  }
  return response.json();
}

/**
 * Calculates bounds for all features in a GeoJSON object.
 * @param {Object} geojsonData - The GeoJSON feature collection.
 * @returns {maplibregl.LngLatBounds|null} The bounds or null if no features.
 */
export function calculateBounds(geojsonData: any): maplibregl.LngLatBounds | null {
  if (!geojsonData.features || geojsonData.features.length === 0) {
    return null;
  }

  const bounds = new maplibregl.LngLatBounds();

  geojsonData.features.forEach((feature: any) => {
    const { type, coordinates } = feature.geometry;

    // In case the new feature is not excluded from map bounds.
    if (feature.properties.field_exclude_from_map_bounds != 1) {
      switch (type) {
        case 'Point':
          bounds.extend(coordinates as [number, number]);
          break;

        case 'LineString':
          coordinates.forEach((coord: [number, number]) => bounds.extend(coord));
          break;

        case 'Polygon':
          coordinates.forEach((ring: [number, number][]) => {
            ring.forEach((coord: [number, number]) => bounds.extend(coord));
          });
          break;

        case 'MultiPolygon':
          coordinates.forEach((polygon: [number, number][][]) => {
            polygon.forEach((ring: [number, number][]) => {
              ring.forEach((coord: [number, number]) => bounds.extend(coord));
            });
          });
          break;

        case 'MultiPoint':
          coordinates.forEach((coord: [number, number]) => bounds.extend(coord));
          break;

        case 'MultiLineString':
          coordinates.forEach((line: [number, number][]) => {
            line.forEach((coord: [number, number]) => bounds.extend(coord));
          });
          break;

        default:
          console.warn(`Unsupported geometry type: ${type}`);
      }
    }
  });

  return bounds;
}

/**
 * Builds popup HTML content from feature properties.
 * @param {Object} properties - The feature properties.
 * @returns {string} The HTML string for the popup.
 */
export function buildPopupContent(properties: any): string {

  let content;

  if (properties.leaflet_popup_rendered_entity) {
    content = properties.leaflet_popup_rendered_entity;
  }
  else {
    content = '<div style="font-family: Arial, sans-serif;">';

    if (properties.name) {
      content += `<h3 style="margin: 0 0 10px 0; font-size: 16px; font-weight: bold;">${properties.name}</h3>`;
    }

    if (properties.field_sub_title) {
      content += `<p style="margin: 5px 0; font-size: 14px;"><strong>Sottotitolo:</strong> ${properties.field_sub_title}</p>`;
    }

    if (properties.field_category_type) {
      content += `<p style="margin: 5px 0; font-size: 14px;"><strong>Categoria:</strong> ${properties.field_category_type}</p>`;
    }

    if (properties.image_url) {
      content += `<div><img src="${ properties.image_url }" alt="properties.name"></div>`;
    }

    if (properties.weblink) {
      content += `<p style="margin: 5px 0; font-size: 12px;"><a href="${properties.weblink}" >view more</a></p>`;
    }
    content += '</div>';
  }

  return content;
}
