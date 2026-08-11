(function ($, Drupal) {

  'use strict';

  /**
   * Leaflet Ta2026 Watermark Control behavior.
   */
  Drupal.behaviors.leafletTa2026WatermarkControl = {

    /**
     * Attaches the behavior to the context.
     */
    attach: function(context) {
      const self = this;

      // React on leafletMapInit event.
      // Resizing Markers.
      $(context).on('leafletMapInit', function (e, settings, lMap, mapid, data_markers) {
        if (context.leafletTa2026WatermarkControl) {
          return;
        }
        context.leafletTa2026WatermarkControl = true;
        const map = lMap;

        L.Control.Watermark = L.Control.extend({
          onAdd: function(map) {
            const a = L.DomUtil.create('a');
            a.href = 'https://www.ta2026.com';
            a.target = '_blank';
            a.rel = 'noopener noreferrer';

            const img = L.DomUtil.create('img', '', a);
            img.src = '/sites/default/files/styles/medium/public/media_image/GdM_logo_header_it.png';
            img.style.width = '100px';

            return a;
          },

          onRemove: function(map) {
            // Nothing to do here
          }
        });

        L.control.watermark = function(opts) {
          return new L.Control.Watermark(opts);
        }

        L.control.watermark({ position: 'topleft' }).addTo(map);
      });
    }
  };

})(jQuery, Drupal);
