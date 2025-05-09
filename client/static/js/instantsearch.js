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

  hasTabbed = false;

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
    let params = {};
    if(this.config.queryBy) {
      params.query_by = this.config.queryBy;
    }
    return params;
  }

  bindToInput() {
    let _inst = this;
    let config = {
      server: this.getConfiguration(),
      additionalSearchParameters: this.getAdditionalSearchParameters()
    };
    const typesenseInstantsearchAdapter = new TypesenseInstantSearchAdapter(config);

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
      let _inst = this;
      if(this.config.hasOwnProperty('parentId')) {
        this.hitBoxParent = document.getElementById(this.config.parentId);
      } else {
        this.hitBoxParent = document.getElementById(this.config.inputId).parentNode;
      }
      if (isFirstRender) {
        // Do some initial rendering and bind events
        this.hitBox = document.createElement('div');
        this.hitBox.setAttribute('id', this.config.id + '-instantsearch-hitbox');
        this.hitBox.classList.add('instantsearch-hits');
        this.hideHitbox();
        let list = document.createElement('ol');
        list.setAttribute('role','listbox');
        this.hitBox.appendChild(list);
        this.hitBoxParent.appendChild(this.hitBox);
        this.hitBoxParent.classList.add('instantsearch-has-hitbox');
      }

      let hitBoxList = this.hitBoxParent.querySelector('.instantsearch-hits ol');
      hitBoxList.replaceChildren();
      renderOptions.items.map(
        function(hit) {
          let link = _inst.createHitLink(hit);
          if(link && typeof link == 'string' || typeof link == 'object') {
            let listItem = document.createElement('li');
            listItem.setAttribute('role','option');
            if(typeof link == 'string') {
              listItem.insertAdjacentHTML('afterbegin', link);
            } else {
              listItem.appendChild(link);
            }
            hitBoxList.appendChild(listItem);
          }
        }
      );
      if(!isFirstRender) {
        this.showHitbox();
      }
    };
    const customHits = instantsearch.connectors.connectHits(renderHits);

    const renderSearchBox = (renderOptions, isFirstRender) => {
        let _inst = this;
        this.searchBox = document.getElementById(this.config.inputId);
        this.searchBox.classList.add('instantsearch-bound');
        if (isFirstRender) {
          if(this.config.placeholder) {
            this.searchBox.setAttribute('placeholder', this.config.placeholder);
          }
          if(this.config.ariaLabel) {
            this.searchBox.setAttribute('aria-label', this.config.ariaLabel);
          }
          this.searchBox.setAttribute('aria-controls', this.config.id + '-instantsearch-hitbox');
          this.createSearchBoxEvents(renderOptions);
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

  createHitLink(hit) {
    if(this.config.hitTemplate) {
      return this.renderHitTemplate(this.config.hitTemplate, hit);
    } else if(hit.hasOwnProperty('TypesenseSearchResultData')) {
      let listItemLink = document.createElement('a');
      listItemLink.classList.add('instantsearch-hit','instantsearch-hit-element');
      listItemLink.setAttribute('href', hit.TypesenseSearchResultData.Link);
      listItemLink.insertAdjacentHTML('afterbegin', instantsearch.highlight({ attribute: 'Title', hit: hit }));
      return listItemLink;
    } else {
      return null;
    }
  }

  renderHitTemplate(templateData, hit) {
    function hitLink(prop, hit) {
      let link = prop.split('.').reduce((a, b) => a[b], hit);
      return link;
    }
    let listItemLink = document.createElement('a');
    listItemLink.classList.add('instantsearch-hit','instantsearch-hit-template');
    listItemLink.setAttribute('href', hitLink(templateData.link, hit));
    listItemLink.insertAdjacentHTML('afterbegin', instantsearch.highlight({ attribute: templateData.title, hit: hit }));
    return listItemLink;
  }

  createSearchBoxEvents(renderOptions) {
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
      'keydown', event => {
        switch(event.keyCode) {
          case 27:
            this.closeHitbox(event);
            break;
          case 9:
            this.hasTabbed = true;
            break;
        }
      }
    );
    this.searchBox.addEventListener(
      'focus', event => {
        this.hasTabbed = false;// reset tab flag
        this.closeHitbox(event);
      }
    );
    this.searchBox.addEventListener(
      'blur', event => {
        this.closeHitbox(event);
      }
    );
  }

  closeHitbox(event) {
    if(!this.hitBox) {
      return;
    }
    switch(event.type) {
      case 'keydown':
        if(event.keyCode == 27) {
          // esc
          this.hideHitbox();
          return true;
        }
        break;
      case 'blur':
        if(this.hasTabbed) {
          // tabbing active, no close
          return false;
        }
        if(event.relatedTarget && event.relatedTarget.classList.contains('instantsearch-hit')) {
          return false;
        }
        this.hideHitbox();
        break;
      case 'focus':
        this.hideHitbox();
        break;

    }
    return false;
  }

  hideHitbox() {
    this.hitBox.classList.add('hide');
    this.hitBox.setAttribute("aria-hidden","true");
  }

  showHitbox() {
    this.hitBox.classList.remove('hide');
    this.hitBox.removeAttribute("aria-hidden");
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
