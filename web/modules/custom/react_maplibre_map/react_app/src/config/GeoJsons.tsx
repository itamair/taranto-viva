/**
 * GeoJSON endpoint configuration.
 */

export const GEOJSON_ENDPOINTS = {
  tarantoVivaGeoplaces: 'https://taranto-viva.ddev.site/en/taranto_viva_geoplaces_geojson',
  tarantoVivaGeoimages: 'https://taranto-viva.ddev.site/it/taranto_viva_geoimages_geojson'
};

// Named exports for backward compatibility
export const taranto_viva_geoplaces_geojson_url = GEOJSON_ENDPOINTS.tarantoVivaGeoplaces;
export const taranto_viva_geoimages_geojson_url = GEOJSON_ENDPOINTS.tarantoVivaGeoimages;
