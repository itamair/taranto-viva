# Taranto Viva MapLibre Application

A React application that visualizes GeoJSON data from multiple Drupal endpoints using MapLibre GL.

## Features

- Interactive map with OpenStreetMap tiles
- Multiple GeoJSON data layers with different styling
- Popup information for map features
- Responsive design
- Modular and scalable architecture

## Project Structure

```
src/
├── config/
│   ├── GeoJsons.jsx        # GeoJSON endpoint URL configuration
│   └── layerStyles.js      # Layer styling configurations
├── hooks/
│   └── useGeoJsonLayer.js  # Custom hook for GeoJSON layer management
├── utils/
│   ├── geojsonUtils.js     # GeoJSON data manipulation utilities
│   └── layerUtils.js       # MapLibre layer management utilities
├── App.jsx
├── Map.jsx                 # Main map component
└── main.jsx
```

## Data Sources

The application displays data from three Drupal GeoJSON endpoints:

1. **Taranto Viva** (`taranto_viva_geojson`)
   - URL: https://taranto-viva.ddev.site/it/taranto_viva_geojson
   - Style: Red points with black border, orange polygons with black border

2. **Geoplaces** (`taranto_viva_geoplaces_geojson`)
   - URL: https://taranto-viva.ddev.site/en/taranto_viva_geoplaces_geojson
   - Style: Red points with black border, orange polygons with black border

3. **Geoimages** (`taranto_viva_geoimages_geojson`)
   - URL: https://taranto-viva.ddev.site/it/taranto_viva_geoimages_geojson
   - Style: Green points with blue border, green polygons with blue border

## Architecture

### Configuration (`config/`)

- **GeoJsons.jsx**: Centralized endpoint URL management
- **layerStyles.js**: Reusable style configurations for different layer types

### Custom Hooks (`hooks/`)

- **useGeoJsonLayer**: Manages the lifecycle of a GeoJSON layer including:
  - Data fetching
  - Source creation
  - Layer rendering
  - Interactivity setup
  - Cleanup on unmount

### Utilities (`utils/`)

- **geojsonUtils.js**:
  - `fetchGeoJson()`: Fetch GeoJSON data from URLs
  - `calculateBounds()`: Calculate map bounds from GeoJSON features
  - `buildPopupContent()`: Generate HTML for feature popups

- **layerUtils.js**:
  - `addGeoJsonSource()`: Add GeoJSON source to map
  - `addPolygonLayers()`: Create polygon fill and outline layers
  - `addLineStringLayer()`: Create LineString layer
  - `addPointLayer()`: Create Point layer
  - `addAllLayers()`: Add all geometry type layers at once
  - `addLayerInteractivity()`: Setup click handlers and cursors
  - `removeLayers()`: Clean up layers and sources

## Adding New GeoJSON Endpoints

To add a new GeoJSON endpoint:

1. **Add the URL** in `src/config/GeoJsons.jsx`:
```javascript
export const GEOJSON_ENDPOINTS = {
  // ... existing endpoints
  newEndpoint: 'https://example.com/geojson'
};
```

2. **Define styling** in `src/config/layerStyles.js` (or use existing style):
```javascript
export const LAYER_STYLES = {
  // ... existing styles
  newStyle: {
    point: { /* ... */ },
    polygon: { /* ... */ },
    linestring: { /* ... */ }
  }
};
```

3. **Use the hook** in `src/Map.jsx`:
```javascript
const newLayer = useGeoJsonLayer({
  map: mapLoaded ? map.current : null,
  url: GEOJSON_ENDPOINTS.newEndpoint,
  sourceId: 'new-data',
  layerPrefix: 'new',
  styles: LAYER_STYLES.newStyle
});
```

4. **Include in bounds calculation** (optional):
```javascript
const allData = [
  // ... existing layers
  newLayer.geojsonData
].filter(Boolean);
```

## Development

```bash
# Install dependencies
npm install

# Start development server
npm run dev

# Build for production
npm run build

# Preview production build
npm preview

# Run linter
npm run lint
```

The application will be available at `http://localhost:5173/`

## Technologies

- React 19.1.1
- MapLibre GL 5.9.0
- Vite 7.1.7
- ESLint for code quality

## Browser Support

Modern browsers with ES6+ support.
