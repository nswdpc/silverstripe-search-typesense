/**
 * Bind a search client to configured input
 */
class TypesenseInstantSearchInputs {
  init() {
    return this;
  }

  bind() {
    let placeholders = document.querySelectorAll('div[data-instantsearch]');
    placeholders.forEach((placeholder) => {
      let config = JSON.parse(placeholder.dataset.instantsearch);
      let client = new TypesenseInstantSearchClient();
      client.init(config).bindToInput();
    });
  }

}

/**
 * Client for instantsearch, binding search to configured inputs
 */
class TypesenseInstantSearchClient {

  config = {};

  hitBox = null;
  searchBox = null;
  hitBoxParent = null;

  init(config) {
    this.config = config;
    return this;
  }

  getConfiguration() {
    let config = {
      apiKey: this.config.apiKey,
      nodes: this.config.nodes
    };
    return config;
  }

  getAdditionalSearchParameters() {
    return {
      query_by: this.config.queryBy
    };
  }

  bindToInput() {

    let _inst = this;

    const typesenseInstantsearchAdapter = new TypesenseInstantSearchAdapter({
      server: this.getConfiguration(),
      additionalSearchParameters: this.getAdditionalSearchParameters()
    });

    // proxy search client
    const searchClient = {
      ...typesenseInstantsearchAdapter.searchClient,
      search(requests) {
        if (requests.every(({ params }) => !params.query)) {
          // do not search if query empty
          return Promise.resolve({
            results: requests.map(() => ({
              hits: [],
              nbHits: 0,
              nbPages: 0,
              page: 0,
              processingTimeMS: 0,
              hitsPerPage: 0,
              exhaustiveNbHits: false,
              query: '',
              params: '',
            })),
          });
        }
        return typesenseInstantsearchAdapter.searchClient.search(requests);
      }
    };

    const search = instantsearch({
      searchClient: searchClient,
      indexName: this.config.collectionName,
    });
    const renderHits = (renderOptions, isFirstRender) => {
        if(this.config.hasOwnProperty('parentId')) {
          this.hitBoxParent = document.getElementById(this.config.parentId);
        } else {
          this.hitBoxParent = document.getElementById(this.config.inputId).parentNode;
        }
        if (isFirstRender) {
            // Do some initial rendering and bind events
            this.hitBox = document.createElement('div');
            this.hitBox.classList.add('instantsearch-hits', 'hide');
            this.hitBox.appendChild(document.createElement('ul'));
            this.hitBoxParent.appendChild(this.hitBox);
        }

        let hitBoxList = this.hitBoxParent.querySelector('.instantsearch-hits ul');
        if(!isFirstRender) {
            hitBoxList.parentNode.classList.remove('hide');
        }
        hitBoxList.replaceChildren();
        renderOptions.items.map(
            function(item) {
                let listItem = document.createElement('li');
                let listItemLink = document.createElement('a');
                listItemLink.classList.add('instantsearch-hit');
                listItemLink.setAttribute('href', item.TypesenseSearchResultData.Link);
                listItemLink.insertAdjacentHTML('afterbegin', instantsearch.highlight({ attribute: 'Title', hit: item }));
                listItem.appendChild(listItemLink);
                hitBoxList.appendChild(listItem);
            }
        );
    };
    const customHits = instantsearch.connectors.connectHits(renderHits);

    const renderSearchBox = (renderOptions, isFirstRender) => {
        let _inst = this;
        this.searchBox = document.getElementById(this.config.inputId);
        this.searchBox.classList.add('instantsearch-bound');
        if (isFirstRender) {
            // Do some initial rendering and bind events
            this.searchBox.addEventListener(
                'input', event => {
                    renderOptions.refine(event.target.value);
                    if(this.hitBox) {
                      this.hitBox.classList.add('refined');
                    }
                }
            );
            this.searchBox.addEventListener(
                'blur', event => {
                  if(event.relatedTarget && event.relatedTarget.classList.contains('instantsearch-hit') ) {
                    return;
                  } else if(this.hitBox) {
                    this.hitBox.classList.add('hide');
                  }
                }
            );
        }
    };
    const customSearchBox =  instantsearch.connectors.connectSearchBox(renderSearchBox);

    // add and start
    search.addWidgets([
      customSearchBox({}),
      customHits({})
    ]);
    search.start();
  }

}

// Bind to inputs on DOM load
document.addEventListener(
  'DOMContentLoaded',
  function() {
    const client = new TypesenseInstantSearchInputs();
    client.init().bind();
  }
);
