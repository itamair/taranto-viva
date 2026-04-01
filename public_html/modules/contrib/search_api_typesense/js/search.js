((Drupal, TypesenseInstantSearchAdapter, instantsearch) => {
  Drupal.behaviors.search = {
    attach(context, settings) {
      if (!settings.search_api_typesense?.blocks) {
        return;
      }

      Object.entries(settings.search_api_typesense.blocks).forEach(
        ([blockUuid, blockSettings]) => {
          const searchboxSelector = `#searchbox--${blockUuid}`;
          const [searchbox] = once('searchbox', searchboxSelector, context);

          if (searchbox === undefined) {
            return;
          }

          this.initializeSearch(blockUuid, blockSettings);
        },
      );
    },

    initializeSearch(blockUuid, blockSettings) {
      const typesenseInstantsearchAdapter = new TypesenseInstantSearchAdapter({
        server: blockSettings.server,
        additionalSearchParameters: {
          exclude_fields: 'embedding',
          exhaustive_search: true,
        },
        collectionSpecificSearchParameters:
          blockSettings.collection_specific_search_parameters,
      });

      const searchClient = typesenseInstantsearchAdapter.searchClient;
      const collections = blockSettings.collection_render_parameters;
      const firstCollection = collections[0];

      const search = instantsearch({
        searchClient,
        indexName: firstCollection.collection_name,
      });

      const getCollectionWidgets = (collection) => [
        instantsearch.widgets.stats({
          container: `#stats-${collection.collection_name}--${blockUuid}`,
        }),
        instantsearch.widgets.infiniteHits({
          container: `#hits-${collection.collection_name}--${blockUuid}`,
          cssClasses: {
            list: 'search-results',
            loadMore: 'btn btn--load-more',
          },
          templates: {
            item(hit, { html, components }) {
              if (blockSettings.debug) {
                return html`
                  <article class="search-result-debug">
                    <ul>
                      ${Object.keys(hit).map(
                        (key) =>
                          html`<li>
                            <strong>${key}:</strong> ${JSON.stringify(
                              hit[key],
                              null,
                              2,
                            )}
                          </li>`,
                      )}
                    </ul>
                  </article>
                `;
              }
              if (hit.rendered_item) {
                return html`${hit.rendered_item}`;
              }
              return html`
                <article>
                  <h3>${components.Highlight({ hit, attribute: 'title' })}</h3>
                  <small> (${hit.id})</small>
                </article>
              `;
            },
          },
        }),
      ];

      // Build configure widget options
      const configureOptions = {
        hitsPerPage: blockSettings.hits_per_page,
      };

      // Check if langcode field exists in the schema and add filter if needed
      if (
        blockSettings.current_langcode &&
        firstCollection.all_fields &&
        firstCollection.all_fields.includes('langcode')
      ) {
        configureOptions.facetFilters = [
          `langcode:${blockSettings.current_langcode}`,
        ];
      }

      search.addWidgets([
        instantsearch.widgets.configure(configureOptions),
        instantsearch.widgets.searchBox({
          container: `#searchbox--${blockUuid}`,
          placeholder: 'Search...',
        }),
        ...getCollectionWidgets(firstCollection),
      ]);

      if (collections.length > 1) {
        collections.slice(1).forEach((collection) => {
          search.addWidgets([
            instantsearch.widgets
              .index({ indexName: collection.collection_name })
              .addWidgets(getCollectionWidgets(collection)),
          ]);
        });
      }

      const facetWidgets = {
        string: (facet) =>
          instantsearch.widgets.refinementList({
            container: `#facet-${facet.name}--${blockUuid}`,
            attribute: facet.name,
            searchable: true,
          }),
        number: (facet) =>
          instantsearch.widgets.rangeSlider({
            container: `#facet-${facet.name}--${blockUuid}`,
            attribute: facet.name,
          }),
        bool: (facet) =>
          instantsearch.widgets.toggleRefinement({
            container: `#facet-${facet.name}--${blockUuid}`,
            attribute: facet.name,
            templates: {
              labelText: facet.label,
            },
          }),
      };

      blockSettings.facets.forEach((facet) => {
        if (facetWidgets[facet.type]) {
          search.addWidgets([facetWidgets[facet.type](facet)]);
        }
      });

      search.start();
    },
  };
})(Drupal, TypesenseInstantSearchAdapter, instantsearch); // eslint-disable-line
