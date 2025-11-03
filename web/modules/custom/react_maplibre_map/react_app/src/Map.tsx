import { useEffect, useRef, useState, useMemo } from 'react';
import maplibregl from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';
import { Protocol } from "pmtiles";
import { GEOJSON_ENDPOINTS } from './config/GeoJsons';
import { LAYER_STYLES } from './config/layerStyles';
import { useGeoJsonLayer } from './hooks/useGeoJsonLayer';
import { calculateBounds } from './utils/geojsonUtils';
import { pMTileLayerStyles } from './utils/layerUtils';
import { addLayerInteractivity } from './utils/layerUtils';

// Layers Control inspired from:
// @see https://blog.wxm.be/2024/01/24/maplibre-layer-visibility-control.html
class LayersControl {
    private readonly _container: HTMLDivElement;
    private readonly _ctrls: Record<string, string[]>;
    private readonly _inputs: HTMLInputElement[];
    private _map: maplibregl.Map | undefined;

    constructor(ctrls: Record<string, string[]>) {
      // This div will hold all the checkboxes and their labels
      this._container = document.createElement("div");
      this._container.classList.add(
        // Built-in classes for consistency
        "maplibregl-ctrl",
        "maplibregl-ctrl-group",
        // Custom class, see later
        "layers-control",
      );
      // Might be cleaner to deep copy these instead
      this._ctrls = ctrls;
      // Direct access to the input elements, so I can decide which should be
      // checked when adding the control to the map.
      this._inputs = [];
      // Create the checkboxes and add them to the container
      for (const key of Object.keys(this._ctrls)) {
        const labeled_checkbox = this._createLabeledCheckbox(key);
        this._container.appendChild(labeled_checkbox);
      }
    }

    // Creates one checkbox and its label
    _createLabeledCheckbox(key: string): HTMLLabelElement {
      const label = document.createElement("label");
      label.classList.add("layer-control");
      const text = document.createTextNode(key);
      const input = document.createElement("input");
      this._inputs.push(input);
      input.type = "checkbox";
      input.id = key;
      // `=>` function syntax keeps `this` to the LayersControl object
      // When changed, toggle all the layers associated with the checkbox via
      // `this._ctrls`.
      input.addEventListener("change", () => {
        const visibility = input.checked ? "visible" : "none";
        for (const layer of this._ctrls[input.id]) {
          if (this._map) {
            this._map.setLayoutProperty(layer, "visibility", visibility);
          }
        }
      });
      label.appendChild(input);
      label.appendChild(text);
      return label;
    }

    onAdd(map: maplibregl.Map): HTMLDivElement {
      this._map = map;
      // For every checkbox, find out if all its associated layers are visible.
      // Check the box if so.
      for (const input of this._inputs) {
        // List of all layer ids associated with this checkbox
        const layers = this._ctrls[input.id];
        // Check whether every layer is currently visible
        let is_visible = true;
        for (const layername of layers) {
          is_visible =
            is_visible &&
            this._map.getLayoutProperty(layername, "visibility") !== "none";
        }
        input.checked = is_visible;
      }
      return this._container;
    }

    onRemove(): void {
      // Not sure why we have to do this ourselves since we are not the ones
      // adding us to the map.
      // Copied from their example so keeping it in.
      if (this._container.parentNode) {
        this._container.parentNode.removeChild(this._container);
      }
      // This might be to help garbage collection? Also from their example.
      // Or perhaps to ensure calls to this object do not change the map still
      // after removal.
      this._map = undefined;
    }
}

function Map() {
  const mapContainer = useRef<HTMLDivElement | null>(null);
  const map = useRef<maplibregl.Map | null>(null);
  const [mapLoaded, setMapLoaded] = useState(false);

  // Define & Memo Start Zoom and Start Location.
  const start_location = useMemo(() => [17.23044, 40.47586] as [number, number], []); // Taranto coordinates
  const start_zoom = useMemo(() => 15, []);

  // Memoize styles to prevent re-creation
  const defaultStyles = useMemo(() => LAYER_STYLES.default, []);
  const geoimagesStyles = useMemo(() => LAYER_STYLES.geoimages, []);

  // Set up the dictionary
  const label_to_layer_ids = useMemo(() => ({
    'Satellite': ['satellite'],
    'Openstreet Map': ['osm'],
    // 'Buildings': ['buildings'],
    'PM Places': ['places'],
    'Buildings': ['buildings'],
    'Buildings 3D': ['3d-buildings'],
    'Places': ['geoplaces-points'],
    'Images': ['geoimages-points'],
    'Areas': ['geoplaces-polygon-fill', 'geoplaces-polygon-outline', 'geoplaces-linestring', 'geoimages-polygon-fill', 'geoimages-polygon-outline', 'geoimages-linestring'],
    // labelcheckboxwithmultiplelayers: ["layerid2", "layerid3", "layerid4"],
  }), []);

  // Create control with useMemo to prevent recreation on every render
  const layerControl = useMemo(() => new LayersControl(label_to_layer_ids), [label_to_layer_ids]);

  // Initialize map
  useEffect(() => {

    if (map.current || !mapContainer.current) return; // Initialize map only once

    // add the PMTiles plugin to the maplibregl global.
    const protocol = new Protocol();
    maplibregl.addProtocol("pmtiles",protocol.tile);

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
          },
          'satellite': {
            type: 'raster',
            tiles: [
              'https://tiles.maps.eox.at/wmts/1.0.0/s2cloudless-2020_3857/default/g/{z}/{y}/{x}.jpg'
            ],
            tileSize: 256
          }
        },
        layers: [
          {
            id: 'satellite',
            type: 'raster',
            source: 'satellite',
            minzoom: 0,
            maxzoom: 20
          },
          {
            id: 'osm',
            type: 'raster',
            source: 'osm',
            minzoom: 0,
            maxzoom: 20
          }
        ]
      },
      center: start_location,
      zoom: start_zoom,
      pitch: 40,
      bearing: 90,
      canvasContextAttributes: {antialias: true}
    });

    map.current.on('load', () => {
      setMapLoaded(true);

      // Add Navigation Controls to the Map.
      if (map.current) {
        map.current.addControl(new maplibregl.NavigationControl(), 'top-left');
      }

      // Add Locate user Control
      if (map.current) {
        map.current.addControl(
          new maplibregl.GeolocateControl({
            positionOptions: {
              enableHighAccuracy: true
            },
            trackUserLocation: true
          }), 'top-right');
      }


      if (map.current) {
        // Add Full Screen Control
        map.current.addControl(new maplibregl.FullscreenControl(), 'top-left');

        // Add Globe Control
        map.current.addControl(new maplibregl.GlobeControl(), 'top-left');

        // Add Terrain Control
/*        map.current.addControl(new maplibregl.TerrainControl(
          {
            source: 'terrain'
          }
        ), 'top-right');*/
      }

    });

    // Cleanup on unmount
    return () => {
      if (map.current) {
        map.current.remove();
        map.current = null;
      }

      // Remove pmtiles protocol.
      maplibregl.removeProtocol("pmtiles");
    };
  }, [start_location, start_zoom]);

  // Load GeoJSON layers using the custom hook
  // Pass map.current directly and let the hook handle the loaded state
  const geoplacesLayer = useGeoJsonLayer({
    map: map.current,
    url: GEOJSON_ENDPOINTS.geoplaces_geojson_url,
    sourceId: 'geoplaces-data',
    layerPrefix: 'geoplaces',
    styles: defaultStyles,
    enabled: mapLoaded,
    markerContainerWidth: 60 // Width for geoplaces markers
  });

  const geoimagesLayer = useGeoJsonLayer({
    map: map.current,
    url: GEOJSON_ENDPOINTS.geoimages_geojson_url,
    sourceId: 'geoimages-data',
    layerPrefix: 'geoimages',
    styles: geoimagesStyles,
    enabled: mapLoaded,
    markerContainerWidth: 40 // Width for geoimages markers
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
      // Extend existing bounds with not empty new ones.
      if (bounds && !bounds.isEmpty()) {
        combinedBounds.extend(bounds);
        hasFeatures = true;
      }
    });

    if (hasFeatures) {
/*      map.current.fitBounds(combinedBounds, {
        padding: 50,
        maxZoom: 15
      });*/
    }

    if (map.current) {

      map.current.addSource('overturemaps_places', pMTileLayerStyles.sources['overturemaps_places'] as maplibregl.VectorSourceSpecification);
      map.current.addSource('osm_places', pMTileLayerStyles.sources['osm_places'] as maplibregl.VectorSourceSpecification);
      map.current.addSource('openfreemap', pMTileLayerStyles.sources['openfreemap'] as maplibregl.VectorSourceSpecification);
      const places_layer = pMTileLayerStyles.layers[0];
      const buildings_layer = pMTileLayerStyles.layers[1];
      const buildings_3d_layer = pMTileLayerStyles.layers[2];
      map.current.addLayer(places_layer as maplibregl.CircleLayerSpecification, 'geoimages-points');
      map.current.addLayer(buildings_layer as maplibregl.FillLayerSpecification, 'geoimages-points');
      map.current.addLayer(buildings_3d_layer as maplibregl.FillLayerSpecification, 'geoimages-points');
      map.current.moveLayer('geoplaces-points', 'geoimages-points');
      map.current.moveLayer('osm', 'satellite');

      // Add click handler for popups
      // Add interactivity / click handler for popups
      addLayerInteractivity(map.current, ['places']);

      console.log(map.current.getStyle().layers);

      // Add the Layer Control.
      // @see https://blog.wxm.be/2024/01/24/maplibre-layer-visibility-control.html
      map.current.addControl(layerControl);

      // Click (to uncheck) specific Layers intiially.
      const unchecked_layers = [
        'Satellite',
        'PM Places',
        'Buildings',
        'Buildings 3D',
        'Areas'
      ];
      for (const layer_label of unchecked_layers) {
        const Chkinput = document.getElementById(layer_label);
        if (Chkinput) {
          Chkinput.click();
        }
      }
    }

  }, [mapLoaded, geoplacesLayer.geojsonData, geoimagesLayer.geojsonData, layerControl]);

  // Add PM Tile Layer using the custom hook
/*  usePMTileLayer({
    map: map.current,
    sourceId: 'overturemaps_places',
  });*/

  // Determine overall loading and error states
  const loading = geoplacesLayer.loading || geoimagesLayer.loading;
  const errors = [
    geoplacesLayer.error,
    geoimagesLayer.error
  ].filter(Boolean);

  return (
    <div style={{ position: 'relative', width: '100%', height: '80vh' }}>
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
          Map loading ...
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
              Error: {error}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

export default Map;
