import { useEffect, useRef, useState, useMemo } from 'react';
import maplibregl from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';
import { Protocol } from "pmtiles";
import { GEOJSON_ENDPOINTS } from './config/GeoJsons';
import { LAYER_STYLES } from './config/layerStyles';
import { useGeoJsonLayer } from './hooks/useGeoJsonLayer';
import { calculateBounds } from './utils/geojsonUtils';
import { addLayerInteractivity } from './utils/layerUtils';

// Layers Control inspired from:
// @see https://blog.wxm.be/2024/01/24/maplibre-layer-visibility-control.html
class LayersControl {
  private readonly _container: HTMLDivElement;
  private readonly _headerContainer: HTMLDivElement;
  private readonly _layersContainer: HTMLDivElement;
  private readonly _collapseButton: HTMLButtonElement;
  private readonly _collapsedText: HTMLSpanElement;
  private readonly _ctrls: Record<string, string[]>;
  private readonly _inputs: HTMLInputElement[];
  private _map: maplibregl.Map | undefined;
  private _isCollapsed: boolean = false;

  constructor(ctrls: Record<string, string[]>) {
    // Main container
    this._container = document.createElement("div");
    this._container.classList.add(
      // Built-in classes for consistency
      "maplibregl-ctrl",
      "maplibregl-ctrl-group",
      // Custom class, see later
      "layers-control",
    );

    // Header container for button and collapsed text
    this._headerContainer = document.createElement("div");
    this._headerContainer.classList.add("layers-control-header");

    // Collapsed text ("Layers")
    this._collapsedText = document.createElement("span");
    this._collapsedText.classList.add("layers-control-collapsed-text");
    this._collapsedText.textContent = "Layers";
    this._collapsedText.style.display = "none";
    this._headerContainer.appendChild(this._collapsedText);

    // Collapse button
    this._collapseButton = document.createElement("button");
    this._collapseButton.classList.add("layers-control-collapse-button");
    this._collapseButton.innerHTML = "×";
    this._collapseButton.setAttribute("aria-label", "Toggle layers control");
    this._collapseButton.addEventListener("click", () => this._toggleCollapse());
    this._headerContainer.appendChild(this._collapseButton);

    this._container.appendChild(this._headerContainer);

    // Layers container for checkboxes
    this._layersContainer = document.createElement("div");
    this._layersContainer.classList.add("layers-control-layers");

    // Might be cleaner to deep copy these instead
    this._ctrls = ctrls;
    // Direct access to the input elements, so I can decide which should be
    // checked when adding the control to the map.
    this._inputs = [];
    // Create the checkboxes and add them to the layers container
    for (const key of Object.keys(this._ctrls)) {
      const labeled_checkbox = this._createLabeledCheckbox(key);
      this._layersContainer.appendChild(labeled_checkbox);
    }

    this._container.appendChild(this._layersContainer);
  }

  _toggleCollapse(): void {
    this._isCollapsed = !this._isCollapsed;

    if (this._isCollapsed) {
      this._layersContainer.style.display = "none";
      this._collapsedText.style.display = "inline-block";
      this._container.classList.add("collapsed");
    } else {
      this._layersContainer.style.display = "block";
      this._collapsedText.style.display = "none";
      this._container.classList.remove("collapsed");
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
  const layerControlRef = useRef<LayersControl | null>(null);
  const layerSetupComplete = useRef<boolean>(false);
  const [mapLoaded, setMapLoaded] = useState(false);

  // Define & Memo Start Zoom and Start Location.
  const start_location = useMemo(() => [17.240046, 40.472317] as [number, number], []); // Taranto coordinates
  const start_zoom = useMemo(() => 15, []);

  // Memoize styles to prevent re-creation
  const defaultStyles = useMemo(() => LAYER_STYLES.default, []);
  const geoimagesStyles = useMemo(() => LAYER_STYLES.geoimages, []);

  // Set up the dictionary
  const label_to_layer_ids = useMemo(() => ({
    'Satellite': ['satellite'],
    'OSM': ['osm'],
    'Overture Places': ['places', 'places-transparent'],
    '3D Buildings': ['3d-buildings'],
    // 'OSM Land Use': ['landuse'],
    // 'OSM Roads': ['roads'],
    'Drupal Places': ['geoplaces-points'],
    'Drupal Images': ['geoimages-points'],
    'Urban Areas': ['geoplaces-polygon-fill', 'geoplaces-polygon-outline', 'geoplaces-linestring', 'geoimages-polygon-fill', 'geoimages-polygon-outline', 'geoimages-linestring'],
  }), []);

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
          },
          'overturemaps_places': {
            type: 'vector' as const,
            url: 'pmtiles://https://overturemaps-tiles-us-west-2-beta.s3.amazonaws.com/2025-04-23/places.pmtiles',
            attribution: '© <a href="https://openstreetmap.org/copyright">OpenStreetMap</a>'
          },
          'osm_layers': {
            type: 'vector' as const,
            url: 'pmtiles://https://demo-bucket.protomaps.com/v4.pmtiles',
            attribution: '© <a href="https://openstreetmap.org/copyright">OpenStreetMap</a>'
          },
          'openfreemap': {
            type: 'vector' as const,
            url: 'https://tiles.openfreemap.org/planet',
            attribution: '© <a href="https://openstreetmap.org/copyright">OpenStreetMap</a>'
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
          },
          {
            'id': 'places',
            'source': 'overturemaps_places',
            'source-layer': 'place',
            'type': 'circle' as const,
            'paint': {
              'circle-radius': 4,
              'circle-color': '#0033ff',
              'circle-opacity':  [
                'interpolate',
                ['linear'],
                ["get", "confidence"],
                0.7,
                0,
                0.98,
                1
              ],
              'circle-stroke-color': '#000000',
              'circle-stroke-width': 0
            },
            "filter": [
              "all",
              [
                "has",
                "@name"
              ],
              [
                ">",
                [
                  "get",
                  "confidence"
                ],
                0.7
              ]
            ],
          },
          {
            'id': 'places-transparent',
            'source': 'overturemaps_places',
            'source-layer': 'place',
            'type': 'circle' as const,
            'paint': {
              'circle-radius': 8,
              'circle-color': '#0033ff',
              'circle-opacity':  0,
              'circle-stroke-width': 0,
            },
            "filter": [
              "all",
              [
                "has",
                "@name"
              ],
              [
                ">",
                [
                  "get",
                  "confidence"
                ],
                0.7
              ]
            ],
          },
          /*{
            'id': 'landuse',
            'source': 'osm_layers',
            'source-layer': 'landuse',
            'type': 'fill' as const,
            'paint': {
              'fill-color': 'orange',
              'fill-opacity': 0.4,
            }
          },
          {
            'id': 'roads',
            'source': 'osm_layers',
            'source-layer': 'roads',
            'type': 'line' as const,
            'paint': {
              'line-color': 'black'
            }
          },*/
          {
            'id': '3d-buildings',
            'source': 'openfreemap',
            'source-layer': 'building',
            'type': 'fill-extrusion',
            'minzoom': 12,
            'filter': ['!=', ['get', 'hide_3d'], true],
            'paint': {
              'fill-extrusion-color': [
                'interpolate',
                ['linear'],
                ['get', 'render_height'], 0, 'lightgray', 30, 'royalblue', 100, 'lightblue'
              ],
              'fill-extrusion-height': [
                'interpolate',
                ['linear'],
                ['zoom'],
                11,
                0,
                16,
                ["*", ['get', 'render_height'], 2.5]
              ],
              'fill-extrusion-base': ['case',
                ['>=', ['get', 'zoom'], 16],
                ['get', 'render_min_height'], 0
              ],
              'fill-extrusion-opacity': 0.7
            }
          }
        ]
      },
      center: start_location,
      zoom: start_zoom,
      maxZoom: 18,
      pitch: 85,
      bearing: -20,
      canvasContextAttributes: {antialias: true}
    });

    map.current.on('load', () => {
      setMapLoaded(true);

      if (map.current) {
        // Add Navigation Controls to the Map.
        map.current.addControl(new maplibregl.NavigationControl(), 'top-left');

        // Add Locate user Control
        map.current.addControl(
          new maplibregl.GeolocateControl({
            positionOptions: {
              enableHighAccuracy: true
            },
            trackUserLocation: true
          }), 'top-left');

        // Add Full Screen Control
        map.current.addControl(new maplibregl.FullscreenControl(), 'top-left');

        // Add Globe Control
        // map.current.addControl(new maplibregl.GlobeControl(), 'top-left');

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
      // Remove layer control if it exists
      if (layerControlRef.current && map.current) {
        map.current.removeControl(layerControlRef.current);
        layerControlRef.current = null;
      }

      if (map.current) {
        map.current.remove();
        map.current = null;
      }

      // Reset setup flag
      layerSetupComplete.current = false;

      // Remove pmtiles protocol.
      maplibregl.removeProtocol("pmtiles");
    };
  }, [label_to_layer_ids, start_location, start_zoom]);

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

      //console.log(map.current.getStyle().layers);
      map.current.moveLayer('osm', 'satellite');

      // Add click handler for popups
      // Add interactivity / click handler for popups
      addLayerInteractivity(map.current, ['places', 'places-transparent']);

      // Add the layerControl and set its initial checkboxes state.
      if (map.current && !layerSetupComplete.current) {
        // Perform layer setup operations only once
        layerSetupComplete.current = true;

        // Add the Layer Control.
        // @see https://blog.wxm.be/2024/01/24/maplibre-layer-visibility-control.html
        const layerControl = new LayersControl(label_to_layer_ids);
        map.current.addControl(layerControl);
        layerControlRef.current = layerControl;

        // Click (to uncheck) specific Layers initially.
        const unchecked_layers = [
          'Satellite',
          'Overture Places',
          //'OSM Land Use',
          //'OSM Roads',
          'Urban Areas'
        ];
        for (const layer_label of unchecked_layers) {
          const Chkinput = document.getElementById(layer_label);
          if (Chkinput) {
            Chkinput.click();
          }
        }
      }
    }
  }, [mapLoaded, geoplacesLayer.geojsonData, geoimagesLayer.geojsonData, label_to_layer_ids]);

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
          Urban Community Data Loading ...
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
