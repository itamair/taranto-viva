# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Context

This is the React/Vite/TypeScript frontend for the `react_maplibre_map` Drupal module. It builds to a single ES module (`dist/index.js`) consumed by the Drupal block plugin at `../src/Plugin/Block/ReactMaplibreBlock.php`, which renders `<div id="react_maplibre_map"></div>` and attaches the built assets via `../react_maplibre_map.libraries.yml`.

The app does **not** use `window.drupalSettings` — all runtime config comes from `.env` variables baked in at build time.

## Commands

```bash
npm install           # install dependencies
npm run build         # tsc type-check + vite build → dist/
npm run dev           # HMR dev server (does NOT embed in Drupal)
npm run lint          # ESLint
```

Build output: `dist/index.js` (ES module, unminified) + `dist/assets/taranto_viva_maplibre.css`.

## Architecture

### Entry & component tree
- `src/main.tsx` → mounts `<App>` on `#react_maplibre_map`
- `src/App.tsx` → thin wrapper, renders `<Map>`
- `src/Map.tsx` → all map orchestration (~550 lines); this is the core component

### Configuration
- `src/config/GeoJsons.tsx` — two GeoJSON endpoint URLs from `VITE_*` env vars
- `src/config/layerStyles.ts` — per-layer style objects (point/polygon/linestring) passed to `useGeoJsonLayer`

### Custom hook
`src/hooks/useGeoJsonLayer.ts` manages the full lifecycle of one GeoJSON layer:
1. Waits for map style to load
2. Fetches GeoJSON from URL
3. Adds source + layers (circle, fill, line) to the map
4. Wires up interactivity (click popups, hover cursor, permanent/hover tooltips, optional custom markers)
5. Cleans up everything on unmount

`Map.tsx` calls this hook twice — once for `geoplaces` and once for `geoimages`.

### Utilities
- `src/utils/geojsonUtils.ts`: `fetchGeoJson()`, `calculateBounds()`, `buildPopupContent()` (reads Drupal field properties from GeoJSON feature properties)
- `src/utils/layerUtils.ts`: helpers for adding MapLibre sources/layers and setting up prioritized click interactivity

### Inline LayersControl
`Map.tsx` contains a vanilla-JS `LayersControl` class that implements `maplibregl.IControl`. It builds a collapsible checkbox UI for toggling layer visibility. It is **not** a React component by design — it interacts directly with the MapLibre map instance.

### Layer precedence
Click events are handled in priority order: Drupal Places → Drupal Images → Overture Places. Only the top-priority hit on any click is acted upon.

## Key implementation details

**Map style**: Starts with no style URL; OSM and satellite raster sources/layers are added imperatively after `map.on('load')`. Overture Maps vector tiles come from a PMTiles file via the `pmtiles` protocol plugin.

**PMTiles**: Registered globally before map init: `addProtocol('pmtiles', new Protocol().tile)`.

**Layer IDs to know**:
- `osm`, `satellite` — raster base layers
- `places`, `places-transparent` — Overture Maps vector points
- `3d-buildings` — fill-extrusion from OpenFreeMap
- `geoplaces-points`, `geoimages-points` — Drupal GeoJSON layers

**GeoJSON feature properties** the app reads (set by Drupal views):
- `leaflet_popup_rendered_entity` — pre-rendered HTML popup (takes precedence over field-by-field rendering)
- `name`, `field_sub_title`, `field_category_type`, `image_url`, `weblink`, `websites` — popup content fields
- `field_tooltip_permanent` — `"1"` = always-visible tooltip, `"0"` = hover tooltip
- `field_map_popup_disabled` — `"1"` disables popup for that feature
- `field_exclude_from_map_bounds` — `"1"` excludes feature from bounds calculation
- `geomarker_icon_url` — custom marker icon; only used when `VITE_CUSTOM_MARKERS=1`

**Build note**: `vite.config.ts` entry is listed as `src/main.jsx` but the actual file is `src/main.tsx` — Vite resolves this correctly; do not rename either without updating the other.
