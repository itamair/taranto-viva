/**
 * GeoJSON endpoint configuration.
 */

export const GEOJSON_ENDPOINTS = {
  tarantoVivaGeoplaces: 'https://www.taranto-viva.com/en/taranto_viva_geoplaces_geojson',
  tarantoVivaGeoimages: 'https://www.taranto-viva.com/it/taranto_viva_geoimages_geojson'
};

// Named exports for backward compatibility
export const taranto_viva_geoplaces_geojson_url = GEOJSON_ENDPOINTS.tarantoVivaGeoplaces;
export const taranto_viva_geoimages_geojson_url = GEOJSON_ENDPOINTS.tarantoVivaGeoimages;
