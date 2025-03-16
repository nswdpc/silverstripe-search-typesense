/**
 * Search adapter
 */
document.addEventListener(
    'DOMContentLoaded',
    function() {
        const typesenseInstantsearchAdapter = new TypesenseInstantSearchAdapter($Configuration);
        const searchClient = typesenseInstantsearchAdapter.searchClient;
        const search = instantsearch({
          searchClient,
          indexName: '$CollectionName',
        });
        const renderHits = (renderOptions, isFirstRender) => {
            console.log(renderOptions);
            const {
              object[] items,
              object results,
              object banner,
              function sendEvent,
              object widgetParams,
            } = renderOptions;

            if (isFirstRender) {
              // Do some initial rendering and bind events
              console.log('first render')
            }
            console.log(items);
        };

        const customHits = instantsearch.widgets.cconnectHits(renderHits);

        const renderSearchBox = (renderOptions, isFirstRender) => {
          const {
            string query,
            function refine,
            function clear,
            boolean isSearchStalled,
            object widgetParams,
          } = renderOptions;

          if (isFirstRender) {
            // Do some initial rendering and bind events
            console.log('first render');
          }

          console.log(query);

          // Render the widget
        };

        const customSearchBox =  instantsearch.widgets.connectSearchBox(renderSearchBox);

        search.addWidgets([
          customSearchBox({}),
          customHits({})
        ]);
        search.start();
    }
);
