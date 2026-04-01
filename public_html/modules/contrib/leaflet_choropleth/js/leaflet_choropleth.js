/**
 * @file
 * JavaScript to enhance Leaflet maps with choropleth functionality.
 */

(function ($, Drupal, drupalSettings, once) {
  'use strict';

  Drupal.behaviors.leafletChoropleth = {
    attach: function (context, settings) {
      const self = this;

      // Attach to leafletMapInit event.
      $(document).on('leafletMapInit', function (e, settings, lMap, mapid, data_markers) {

        // Ensure the Leaflet Choropleth Behavior is attached only once to each
        // Leaflet map id element.
        // @see https://www.drupal.org/project/leaflet/issues/3314762#comment-15044223
          const leaflet_elements = $(once('behaviour-leaflet-choropleth', '#' + mapid,));
          leaflet_elements.each(function() {
            const map_settings = settings.settings;

            // Check if choropleth is enabled for this map.
            if (map_settings &&
              map_settings.choropleth &&
              map_settings.choropleth.enabled) {

              // Initialize choropleth settings.
              const choroplethSettings = settings.settings.choropleth;

              // Add choropleth legend if needed.
              if (choroplethSettings.colorScale && choroplethSettings.legend && choroplethSettings.legend.legend_control_enabled) {
                self.addChoroplethLegend(lMap, choroplethSettings);
              }
            }
          });
      });
    },

    /**
     * Add a choropleth legend to the map.
     *
     * @param {L.Map} map
     *   The Leaflet map object.
     * @param {Object} settings
     *   The choropleth settings.
     */
    addChoroplethLegend: function (map, settings) {
      const self = this;
      const colorScale = settings.colorScale;

      if (!colorScale || !colorScale['legend_items']) {
        return;
      }

      // Create a legend control.
      const legend = L.control({ position: settings.legend.position || 'bottomright' });

      legend.onAdd = function (map) {
        const div = L.DomUtil.create('div', 'leaflet-control leaflet-choropleth-legend');

        // Create Legend Collapsed label.
        const legendCollapsedLabel = L.DomUtil.create('div', 'legend-collapsed-label', div);
        // Apply custom Legend Collapsed Label content.
        legendCollapsedLabel.innerHTML = settings.legend.legend_collapsed_label ?? "Legend";

        // Add toggle button.
        const toggleBtn = L.DomUtil.create('div', 'leaflet-choropleth-legend-toggle', div);
        toggleBtn.innerHTML = 'X';
        toggleBtn.title = 'Close legend';

        // Create content container.
        const content = L.DomUtil.create('div', 'leaflet-choropleth-legend-content', div);

        // Add title if provided.
        if (settings.legend.title) {
          const title = L.DomUtil.create('div', 'leaflet-choropleth-legend-title', content);
          title.innerHTML = settings.legend.title;
        }

        // Add subtitle if provided.
        if (settings.legend.subtitle) {
          const subtitle = L.DomUtil.create('div', 'leaflet-choropleth-legend-subtitle', content);
          subtitle.innerHTML = settings.legend.subtitle.value;
        }

        // Add color boxes and labels for each class.
        const legend_items = colorScale.legend_items;
        const decimals = settings.legend.decimals !== undefined ? settings.legend.decimals : 1;

        // Add notes if provided.
        if (settings.legend.notes) {
          const notes = L.DomUtil.create('div', 'leaflet-choropleth-legend-notes', content);
          notes.innerHTML = settings.legend.notes.value;
        }

        const items = L.DomUtil.create('div', 'leaflet-choropleth-legend-items', content);

        for (let i = 0; i < legend_items.length; i++) {
          const item = L.DomUtil.create('div', 'leaflet-choropleth-legend-item', items);

          // Format values based on settings.
          let valueMin = i > 0 ? (parseFloat(legend_items[i]['values']['min']) + self.minimumDecimalValue(decimals)).toFixed(decimals) : parseFloat(legend_items[i]['values']['min']).toFixed(decimals);
          let valueMax = parseFloat(legend_items[i]['values']['max']).toFixed(decimals);
          let value = legend_items[i]['values']['value'];

          if (parseFloat(valueMin) > parseFloat(valueMax)) continue;

          // Create color box.
          const colorBox = L.DomUtil.create('span', 'leaflet-choropleth-legend-color', item);
          colorBox.style.backgroundColor = legend_items[i]['color'];

          // Create label.
          const label = L.DomUtil.create('span', 'leaflet-choropleth-legend-label', item);

          // Apply custom formatting if provided.
          label.innerHTML = settings.legend.item_format ? self.sprintf(settings.legend.item_format, valueMin, valueMax, value) : self.sprintf('%min - %max', valueMin, valueMax);
        }

        content.appendChild(items);

        // Add subtitle if provided.
        if (settings.legend.notes_after) {
          const notes = content.querySelector('.leaflet-choropleth-legend-notes');
          content.appendChild(notes);
        }

        // Check if legend should start collapsed based on settings.
        const startCollapsed = settings.legend.start_collapsed || false;
        if (startCollapsed) {
          div.classList.add('legend-collapsed');
        }

        // Add toggle functionality.
        self.setupLegendToggle(div, toggleBtn);

        return div;
      };

      // Add the legend to the map.
      legend.addTo(map);
    },

    /**
     * Setup legend toggle functionality.
     *
     * @param {Element} legendDiv
     *   The legend div element.
     * @param {Element} toggleBtn
     *   The toggle button element.
     */
    setupLegendToggle: function (legendDiv, toggleBtn) {

      // Handle toggle button click (collapse legend).
      toggleBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        legendDiv.classList.add('legend-collapsed');
      });

      // Handle collapsed legend click (expand legend).
      legendDiv.addEventListener('click', function (e) {
        if (legendDiv.classList.contains('legend-collapsed')) {
          e.stopPropagation();
          legendDiv.classList.remove('legend-collapsed');
        }
      });

      // Prevent map interaction when clicking on the legend.
      L.DomEvent.disableClickPropagation(legendDiv);
    },

    minimumDecimalValue: function (number) {
      const decimal = number; // Define the number of decimal places (e.g., 2 for 0.01)

      // Calculate the minimum decimal value (e.g., 0.01 for 2 decimal places)
      const minimumValue = Math.pow(10, -decimal);
      return parseFloat(minimumValue.toFixed(decimal));
    },

    /**
     * Simple sprintf implementation for string formatting.
     *
     * @param {string} string
     *   The format string.
     * @param min
     *   The min value to replace %min with.
     * @param max
     *   The max value to replace %max with.
     * @param value
     *   The value to replace %value with.
     * @return {string}
     *   The formatted string.
     */
    sprintf: function(string, min, max, value = null) {
      return string
        .replace(/%min/g, min)
        .replace(/%max/g, max)
        .replace(/%value/g, value);
    }
  };

})(jQuery, Drupal, drupalSettings, once);
