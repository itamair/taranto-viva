import { useEffect, useState } from 'react';
import { fetchGeoJson } from '../utils/geojsonUtils';
import { addGeoJsonSource, addAllLayers, addLayerInteractivity, removeLayers } from '../utils/layerUtils';

/**
 * Custom hook for managing a GeoJSON layer on a MapLibre map.
 * @param {Object} params - Hook parameters.
 * @param {Object} params.map - The MapLibre map instance.
 * @param {string} params.url - The GeoJSON endpoint URL.
 * @param {string} params.sourceId - Unique ID for the GeoJSON source.
 * @param {string} params.layerPrefix - Prefix for layer IDs.
 * @param {Object} params.styles - Layer style configuration.
 * @param {boolean} params.enabled - Whether the layer should be loaded.
 * @returns {Object} Object containing geojsonData, loading state, and error.
 */
export function useGeoJsonLayer({ map, url, sourceId, layerPrefix, styles, enabled = true }) {
  const [geojsonData, setGeojsonData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [layerIds, setLayerIds] = useState([]);

  useEffect(() => {
    if (!enabled || !map || !url) {
      setLoading(false);
      return;
    }

    let isMounted = true;
    let loadHandler = null;

    async function loadGeoJson() {
      try {
        setLoading(true);
        setError(null);

        // Fetch GeoJSON data
        const data = await fetchGeoJson(url);

        if (!isMounted) return;

        setGeojsonData(data);

        // Add source to map
        addGeoJsonSource(map, sourceId, data);

        // Add all layers
        const createdLayerIds = addAllLayers(map, sourceId, layerPrefix, styles);
        setLayerIds(createdLayerIds);

        // Add interactivity
        addLayerInteractivity(map, createdLayerIds);

        setLoading(false);
      } catch (err) {
        if (!isMounted) return;

        console.error(`Error loading GeoJSON from ${url}:`, err);
        setError(err.message);
        setLoading(false);
      }
    }

    // Wait for map to be loaded
    if (map.isStyleLoaded()) {
      loadGeoJson();
    } else {
      loadHandler = () => {
        loadGeoJson();
      };
      map.once('load', loadHandler);
    }

    // Cleanup
    return () => {
      isMounted = false;
      if (loadHandler) {
        map.off('load', loadHandler);
      }
      if (map && layerIds.length > 0) {
        removeLayers(map, sourceId, layerIds);
      }
    };
  }, [enabled, map, url, sourceId, layerPrefix, styles]);

  return { geojsonData, loading, error };
}
