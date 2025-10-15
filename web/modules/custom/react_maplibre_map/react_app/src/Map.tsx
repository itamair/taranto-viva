import { useEffect, useRef, useState, useMemo } from 'react';
import maplibregl from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';
import { GEOJSON_ENDPOINTS } from './config/GeoJsons';
import { LAYER_STYLES } from './config/layerStyles';
import { useGeoJsonLayer } from './hooks/useGeoJsonLayer';
import { calculateBounds } from './utils/geojsonUtils';

function Map() {
  const mapContainer = useRef(null);
  const map = useRef(null);
  const [mapLoaded, setMapLoaded] = useState(false);

  // Memoize styles to prevent re-creation
  const defaultStyles = useMemo(() => LAYER_STYLES.default, []);
  const geoimagesStyles = useMemo(() => LAYER_STYLES.geoimages, []);

  // Initialize map
  useEffect(() => {
    if (map.current) return; // Initialize map only once

    // Initialize the map centered on Taranto
    map.current = new maplibregl.Map({
      container: mapContainer.current,
      style: {
        version: 8,
        sources: {
          'osm': {
            type: 'raster',
            tiles: [
              'https://a.tile.openstreetmap.org/{z}/{x}/{y}.png',
              'https://b.tile.openstreetmap.org/{z}/{x}/{y}.png',
              'https://c.tile.openstreetmap.org/{z}/{x}/{y}.png'
            ],
            tileSize: 256,
            attribution: '&copy; OpenStreetMap Contributors'
          }
        },
        layers: [
          {
            id: 'osm',
            type: 'raster',
            source: 'osm',
            minzoom: 0,
            maxzoom: 19
          }
        ]
      },
      center: [17.23, 40.47], // Taranto coordinates
      zoom: 12
    });

    map.current.on('load', () => {
      setMapLoaded(true);
    });

    // Cleanup on unmount
    return () => {
      if (map.current) {
        map.current.remove();
        map.current = null;
      }
    };
  }, []);

  // Load GeoJSON layers using the custom hook
  // Pass map.current directly and let the hook handle the loaded state
  const geoplacesLayer = useGeoJsonLayer({
    map: map.current,
    url: GEOJSON_ENDPOINTS.tarantoVivaGeoplaces,
    sourceId: 'geoplaces-data',
    layerPrefix: 'geoplaces',
    styles: defaultStyles,
    enabled: mapLoaded
  });

  const geoimagesLayer = useGeoJsonLayer({
    map: map.current,
    url: GEOJSON_ENDPOINTS.tarantoVivaGeoimages,
    sourceId: 'geoimages-data',
    layerPrefix: 'geoimages',
    styles: geoimagesStyles,
    enabled: mapLoaded
  });

  // Fit bounds when all data is loaded
  useEffect(() => {
    if (!map.current || !mapLoaded) return;

    const allData = [
      geoplacesLayer.geojsonData,
      geoimagesLayer.geojsonData
    ].filter(Boolean);

    if (allData.length === 0) return;

    // Calculate combined bounds from all datasets
    const combinedBounds = new maplibregl.LngLatBounds();
    let hasFeatures = false;

    allData.forEach(data => {
      const bounds = calculateBounds(data);
      if (bounds) {
        combinedBounds.extend(bounds);
        hasFeatures = true;
      }
    });

    if (hasFeatures) {
      map.current.fitBounds(combinedBounds, {
        padding: 50,
        maxZoom: 15
      });
    }
  }, [mapLoaded, geoplacesLayer.geojsonData, geoimagesLayer.geojsonData]);

  // Determine overall loading and error states
  const loading = geoplacesLayer.loading || geoimagesLayer.loading;
  const errors = [
    geoplacesLayer.error,
    geoimagesLayer.error
  ].filter(Boolean);

  return (
    <div style={{ position: 'relative', width: '100%', height: '100vh', padding: '50px', boxSizing: 'border-box' }}>
      <div
        ref={mapContainer}
        style={{ width: '100%', height: '100%' }}
      />
      {loading && (
        <div style={{
          position: 'absolute',
          top: '50%',
          left: '50%',
          transform: 'translate(-50%, -50%)',
          backgroundColor: 'white',
          padding: '20px',
          borderRadius: '8px',
          boxShadow: '0 2px 10px rgba(0,0,0,0.1)'
        }}>
          Caricamento mappa...
        </div>
      )}
      {errors.length > 0 && (
        <div style={{
          position: 'absolute',
          top: '20px',
          left: '50%',
          transform: 'translateX(-50%)',
          backgroundColor: '#ff4444',
          color: 'white',
          padding: '15px',
          borderRadius: '8px',
          maxWidth: '400px'
        }}>
          {errors.map((error, index) => (
            <div key={index} style={{ marginBottom: index < errors.length - 1 ? '10px' : '0' }}>
              Errore: {error}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

export default Map;
