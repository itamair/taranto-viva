(function (Drupal, once) {
  'use strict';

  // Mulberry32 — fast 32-bit seeded PRNG, good enough for visual shuffling.
  function seededPrng(seed) {
    let s = seed >>> 0;
    return function () {
      s += 0x6d2b79f5;
      let t = Math.imul(s ^ (s >>> 15), 1 | s);
      t ^= t + Math.imul(t ^ (t >>> 7), 61 | t);
      return ((t ^ (t >>> 14)) >>> 0) / 0x100000000;
    };
  }

  Drupal.behaviors.geoImagesShuffleGrid = {
    attach(context) {
      // The theme template renders items as flat .views-view-grid__item
      // siblings inside .views-view-grid — no .views-row/.views-col wrappers.
      once('geo-images-shuffle', '.view-geo-images-list .views-view-grid', context)
        .forEach(function (grid) {
          let seed = sessionStorage.getItem('geo_images_shuffle_seed');
          if (!seed) {
            seed = (Math.random() * 0xffffffff) >>> 0;
            sessionStorage.setItem('geo_images_shuffle_seed', seed);
          }

          const rand = seededPrng(parseInt(seed, 10));
          const items = Array.from(grid.querySelectorAll(':scope > .views-view-grid__item'));

          // Fisher-Yates shuffle.
          for (let i = items.length - 1; i > 0; i--) {
            const j = Math.floor(rand() * (i + 1));
            [items[i], items[j]] = [items[j], items[i]];
          }

          // Re-append in shuffled order — CSS Grid handles the layout.
          items.forEach(function (item) {
            grid.appendChild(item);
          });
        });
    },
  };

}(Drupal, once));