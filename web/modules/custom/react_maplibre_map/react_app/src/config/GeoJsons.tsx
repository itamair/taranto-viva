/**
 * GeoJSON endpoint configuration.
 */
export const GEOJSON_ENDPOINTS = {
  tarantoVivaGeoplaces: import.meta.env.VITE_TARANTO_VIVA_GEOPLACES_URL,
  tarantoVivaGeoimages: import.meta.env.VITE_TARANTO_VIVA_GEOIMAGES_URL,
};

// Named exports for backward compatibility
export const taranto_viva_geoplaces_geojson_url = GEOJSON_ENDPOINTS.tarantoVivaGeoplaces;
export const taranto_viva_geoimages_geojson_url = GEOJSON_ENDPOINTS.tarantoVivaGeoimages;
