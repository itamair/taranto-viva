import { useEffect, useState } from 'react';
import maplibregl from 'maplibre-gl';
import { fetchGeoJson } from '../utils/geojsonUtils';
import { addGeoJsonSource, addAllLayers, addLayerInteractivity, removeLayers, addCustomMarkers, removeCustomMarkers } from '../utils/layerUtils';

interface UseGeoJsonLayerParams {
  map: maplibregl.Map | null;
  url: string;
  sourceId: string;
  layerPrefix: string;
  styles: any;
  enabled?: boolean;
  markerContainerWidth?: number;
}

interface UseGeoJsonLayerReturn {
  geojsonData: any;
  loading: boolean;
  error: string | null;
}

/**
 * Custom hook for managing a GeoJSON layer on a MapLibre map.
 * @param {Object} params - Hook parameters.
 * @param {Object} params.map - The MapLibre map instance.
 * @param {string} params.url - The GeoJSON endpoint URL.
 * @param {string} params.sourceId - Unique ID for the GeoJSON source.
 * @param {string} params.layerPrefix - Prefix for layer IDs.
 * @param {Object} params.styles - Layer style configuration.
 * @param {boolean} params.enabled - Whether the layer should be loaded.
 * @param {number} params.markerContainerWidth - Width for custom marker containers.
 * @returns {Object} Object containing geojsonData, loading state, and error.
 */
export function useGeoJsonLayer({ map, url, sourceId, layerPrefix, styles, enabled = true, markerContainerWidth = 30 }: UseGeoJsonLayerParams): UseGeoJsonLayerReturn {
  const [geojsonData, setGeojsonData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [layerIds, setLayerIds] = useState<string[]>([]);
  const [customMarkers, setCustomMarkers] = useState<maplibregl.Marker[]>([]);

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

        if (!isMounted || !map) return;

        setGeojsonData(data);

        // Add source to map
        addGeoJsonSource(map, sourceId, data);

        // Add all layers
        const createdLayerIds = addAllLayers(map, sourceId, layerPrefix, styles);
        setLayerIds(createdLayerIds);

        // Add interactivity
        addLayerInteractivity(map, createdLayerIds);

        const custom_markers_data = import.meta.env.VITE_CUSTOM_MARKERS == 1 ? data : {};

        // Add custom markers for points with geomarker_icon_url
        const markers = addCustomMarkers(map, custom_markers_data, markerContainerWidth);
        setCustomMarkers(markers);

        setLoading(false);
      } catch (err) {
        if (!isMounted) return;

        console.error(`Error loading GeoJSON from ${url}:`, err);
        setError(err instanceof Error ? err.message : String(err));
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
      if (customMarkers.length > 0) {
        removeCustomMarkers(customMarkers);
      }
    };
  }, [enabled, map, url, sourceId, layerPrefix, styles, markerContainerWidth]);

  return { geojsonData, loading, error };
}
