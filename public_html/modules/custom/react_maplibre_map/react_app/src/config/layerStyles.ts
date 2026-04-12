/**
 * Layer style configurations for different GeoJSON sources.
 */

export const LAYER_STYLES = {
  // Original taranto_viva_geojson and geoplaces styling (red points, orange polygons)
  default: {
    "point": {
      "circle-radius": [
        "interpolate",
        ["exponential", 0.5],
        ["zoom"],
        5, 3,   // at zoom 5 → radius 3
        18, 8  // at zoom 18 → radius 30
      ],
      "circle-color": "#ff0000",
      "circle-stroke-color": "#000000",
      "circle-stroke-width": 1
    },
    "polygon": {
      "fill": {
        "fill-color": "blue",
        "fill-opacity": 0.2
      },
      "outline": {
        "line-color": "black",
        "line-width": 2
      }
    },
    "linestring": {
      "line-color": "black",
      "line-width": 2
    }
  },

  // Geoimages styling (green points with blue border, green polygons with blue border)
  "geoimages": {
    "point": {
      "circle-radius": 5,
      "circle-color": "#00ff00",
      "circle-stroke-color": "#0000ff",
      "circle-stroke-width": 1
    },
    "polygon": {
      "fill": {
        "fill-color": "green",
        "fill-opacity": 0.5
      },
      "outline": {
        "line-color": "blue",
        "line-width": 2
      }
    },
    "linestring": {
      "line-color": "blue",
      "line-width": 2
    }
  }
};
