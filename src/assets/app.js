(function(window, document) {

  let editorElement = document.getElementById("sql-editor")
  let sqlEditor = null;

  let initCodeMirror = () => {
    // Initialize CodeMirror
    sqlEditor = CodeMirror(editorElement, {
      mode: 'text/x-mysql',
      theme: 'eclipse',
      lineNumbers: true,
      indentUnit: 2,
      smartIndent: true,
      lineWrapping: false,
      //viewportMargin: Infinity,
      extraKeys: {
        "Ctrl-Enter": () => {
          //this.executeQuery();
        },
        "Ctrl-Space": "autocomplete"
      },
      value: ''
    });
    sqlEditor.setSize('100%', '100%');
  };

  initCodeMirror();
  // ------------------------------------------------------------

  let initResultsTabs = () => {
    document.querySelectorAll('#results-tabs .results-tab').forEach(btn => {
      btn.addEventListener('click', evt => {
        let targetId = evt.target.dataset.target;
        activateTab(targetId);
      });
    });

    document.getElementById('tab-nav-left')?.addEventListener('click', () => scrollTabs('left'));
    document.getElementById('tab-nav-right')?.addEventListener('click', () => scrollTabs('right'));
    updateTabNavigation();
  };

  let activateTab = (targetId) => {
    // Tab
    document.querySelectorAll('#results-tabs .results-tab').forEach(tab => {
      tab.classList.remove('active');
      if (tab.dataset.target === targetId) {
        tab.classList.add('active');
      }
    });

    // Tab panel
    document.querySelectorAll('.tab-pane').forEach(pane => {
      pane.classList.remove('active');
      if (pane.id === targetId) {
        pane.classList.add('active');
      }
    });
  };

  let scrollTabs = (direction) => {
    const tabsContainer = document.getElementById('results-tabs');
    if (!tabsContainer) {
      return;
    }

    const scrollAmount = 200;
    const currentScroll = tabsContainer.scrollLeft;

    if (direction === 'left') {
      tabsContainer.scrollTo({
        left: currentScroll - scrollAmount,
        behavior: 'smooth'
      });
    } else {
      tabsContainer.scrollTo({
        left: currentScroll + scrollAmount,
        behavior: 'smooth'
      });
    }

    setTimeout(() => updateTabNavigation(), 300);
  };

  let updateTabNavigation = () => {
    const tabsContainer = document.getElementById('results-tabs');
    const leftBtn = document.getElementById('tab-nav-left');
    const rightBtn = document.getElementById('tab-nav-right');

    if (!tabsContainer || !leftBtn || !rightBtn) {
      return;
    }

    const { scrollLeft, scrollWidth, clientWidth } = tabsContainer;

    leftBtn.disabled = scrollLeft <= 0;
    rightBtn.disabled = scrollLeft >= scrollWidth - clientWidth - 1;
  };



  initResultsTabs();
  // ------------------------------------------------------------

  let currentResults = {};

  let createResultGrid = (container, combinedData) => {
    const gridDiv = document.createElement('div');
    gridDiv.className = 'results-grid ag-theme-alpine';
    container.appendChild(gridDiv);

    const columnDefs = Object.keys(combinedData[0]).map(key => ({
      field: key,
      headerName: key === '_shard' ? 'DB' : key,
      sortable: true,
      filter: true,
      resizable: true,
      pinned: key === '_shard' ? 'left' : false,
      cellClass: key === '_shard' ? 'shard-column' : '',
      width: key === '_shard' ? 150 : undefined
    }));

    const gridOptions = {
      columnDefs,
      rowData: combinedData,
      defaultColDef: {
        flex: 1,
        minWidth: 100
      },
      suppressRowClickSelection: true,
      enableCellTextSelection: true
    };

    new agGrid.Grid(gridDiv, gridOptions);
  };

  currentResults['query1'] = [
    {_shard: 'db1', id: 1, name: 'Alice'  }, {_shard: 'db2', id:  2, name: 'Bob'  }, {_shard: 'db1', id: 3, name: 'Charlie'}, {_shard: 'db2', id:  4, name: 'David'},
    {_shard: 'db1', id: 5, name: 'Eve'    }, {_shard: 'db2', id:  6, name: 'Frank'}, {_shard: 'db1', id: 7, name: 'Grace'  }, {_shard: 'db2', id:  8, name: 'Hank' },
    {_shard: 'db1', id: 9, name: 'Ivy'    }, {_shard: 'db2', id: 10, name: 'Jack' }, {_shard: 'db1', id: 1, name: 'Alice'  }, {_shard: 'db2', id:  2, name: 'Bob'  },
    {_shard: 'db1', id: 3, name: 'Charlie'}, {_shard: 'db2', id:  4, name: 'David'}, {_shard: 'db1', id: 5, name: 'Eve'    }, {_shard: 'db2', id:  6, name: 'Frank'},
    {_shard: 'db1', id: 7, name: 'Grace'  }, {_shard: 'db2', id:  8, name: 'Hank' }, {_shard: 'db1', id: 9, name: 'Ivy'    }, {_shard: 'db2', id: 10, name: 'Jack' },
    {_shard: 'db1', id: 1, name: 'Alice'  }, {_shard: 'db2', id:  2, name: 'Bob'  }, {_shard: 'db1', id: 3, name: 'Charlie'}, {_shard: 'db2', id:  4, name: 'David'},
    {_shard: 'db1', id: 5, name: 'Eve'    }, {_shard: 'db2', id:  6, name: 'Frank'}, {_shard: 'db1', id: 7, name: 'Grace'  }, {_shard: 'db2', id:  8, name: 'Hank' },
    {_shard: 'db1', id: 9, name: 'Ivy'    }, {_shard: 'db2', id: 10, name: 'Jack' }
  ];

  currentResults['query2'] = [
    {_shard: 'db1', id: 1, name: 'Alice'  }, {_shard: 'db2', id:  2, name: 'Bob'  }, {_shard: 'db1', id: 3, name: 'Charlie'}, {_shard: 'db2', id:  4, name: 'David'},
    {_shard: 'db1', id: 5, name: 'Eve'    }, {_shard: 'db2', id:  6, name: 'Frank'}, {_shard: 'db1', id: 7, name: 'Grace'  }, {_shard: 'db2', id:  8, name: 'Hank' },
    {_shard: 'db1', id: 9, name: 'Ivy'    }, {_shard: 'db2', id: 10, name: 'Jack' }, {_shard: 'db1', id: 1, name: 'Alice'  }, {_shard: 'db2', id:  2, name: 'Bob'  },
    {_shard: 'db1', id: 3, name: 'Charlie'}, {_shard: 'db2', id:  4, name: 'David'}, {_shard: 'db1', id: 5, name: 'Eve'    }, {_shard: 'db2', id:  6, name: 'Frank'},
    {_shard: 'db1', id: 7, name: 'Grace'  }, {_shard: 'db2', id:  8, name: 'Hank' }, {_shard: 'db1', id: 9, name: 'Ivy'    }, {_shard: 'db2', id: 10, name: 'Jack' },
    {_shard: 'db1', id: 1, name: 'Alice'  }, {_shard: 'db2', id:  2, name: 'Bob'  }, {_shard: 'db1', id: 3, name: 'Charlie'}, {_shard: 'db2', id:  4, name: 'David'},
    {_shard: 'db1', id: 5, name: 'Eve'    }, {_shard: 'db2', id:  6, name: 'Frank'}, {_shard: 'db1', id: 7, name: 'Grace'  }, {_shard: 'db2', id:  8, name: 'Hank' },
    {_shard: 'db1', id: 9, name: 'Ivy'    }, {_shard: 'db2', id: 10, name: 'Jack' }
  ];

  currentResults['query3'] = [
    {_shard: 'db1', id: 1, name: 'Alice'  }, {_shard: 'db2', id:  2, name: 'Bob'  }, {_shard: 'db1', id: 3, name: 'Charlie'}, {_shard: 'db2', id:  4, name: 'David'},
    {_shard: 'db1', id: 5, name: 'Eve'    }, {_shard: 'db2', id:  6, name: 'Frank'}, {_shard: 'db1', id: 7, name: 'Grace'  }, {_shard: 'db2', id:  8, name: 'Hank' },
    {_shard: 'db1', id: 9, name: 'Ivy'    }, {_shard: 'db2', id: 10, name: 'Jack' }, {_shard: 'db1', id: 1, name: 'Alice'  }, {_shard: 'db2', id:  2, name: 'Bob'  },
    {_shard: 'db1', id: 3, name: 'Charlie'}, {_shard: 'db2', id:  4, name: 'David'}, {_shard: 'db1', id: 5, name: 'Eve'    }, {_shard: 'db2', id:  6, name: 'Frank'},
    {_shard: 'db1', id: 7, name: 'Grace'  }, {_shard: 'db2', id:  8, name: 'Hank' }, {_shard: 'db1', id: 9, name: 'Ivy'    }, {_shard: 'db2', id: 10, name: 'Jack' },
    {_shard: 'db1', id: 1, name: 'Alice'  }, {_shard: 'db2', id:  2, name: 'Bob'  }, {_shard: 'db1', id: 3, name: 'Charlie'}, {_shard: 'db2', id:  4, name: 'David'},
    {_shard: 'db1', id: 5, name: 'Eve'    }, {_shard: 'db2', id:  6, name: 'Frank'}, {_shard: 'db1', id: 7, name: 'Grace'  }, {_shard: 'db2', id:  8, name: 'Hank' },
    {_shard: 'db1', id: 9, name: 'Ivy'    }, {_shard: 'db2', id: 10, name: 'Jack' }
  ]

  createResultGrid(document.getElementById('tab-1'), currentResults['query1']);
  createResultGrid(document.getElementById('tab-3'), currentResults['query2']);
  createResultGrid(document.getElementById('tab-4'), currentResults['query3']);

  // ------------------------------------------------------------

  let adjustStyles = () => {
    document.querySelectorAll('.table-list').forEach(el => {
      el.style.height = (window.innerHeight - el.offsetTop) + 'px';
    });

    document.querySelectorAll('.tab-pane').forEach(el => {
      el.style.height = (window.innerHeight - el.offsetTop) + 'px';
    });


    setTimeout(adjustStyles, 100);
  };
  adjustStyles();
  // ------------------------------------------------------------

})(window, window.document);
