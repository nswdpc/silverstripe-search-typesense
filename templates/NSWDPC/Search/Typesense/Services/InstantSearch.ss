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
        search.addWidgets([
          instantsearch.widgets.searchBox({
            container: '$Searchbox',
            showReset: false
          }),
          instantsearch.widgets.hits({
            container: '$Hitbox',
            templates: {
                item(hit, { html, components }) {
                  return html`
                    <a href="${hit.TypesenseSearchResultData.Link}">
                      ${components.Highlight({ attribute: 'Title', hit })}
                    </a>
                  `;
                },
            }
          })
        ]);
        search.start();
    }
);
