(function(window, document) {
  let dbSelector = document.getElementById('db-selector');

  let getCurrentCluster = () => {
    let clusterName = document.getElementById('cluster-selector').value;
    console.log('Selected cluster:', clusterName);
    return clusterName;
  };

  let getTargetShards = () => {
    let selectedDbs = [];
    dbSelector.querySelectorAll('option').forEach(el => {
      if (el.selected) {
        selectedDbs.push(el.value);
      }
    });
    console.log('Selected DBs:', selectedDbs);
    return selectedDbs;
  };

  // ------------------------------------------------------------

  let currentResults = {};
  let editorElement = document.getElementById('sql-editor')
  let sqlEditor = null;
  let isExecuting = false;
  let sqlExecutionDialogElem = document.getElementById('execution-confirm');
  let sqlExecutionDialog = null;
  let historyDialogElem = document.getElementById('sql-history');
  let historyDialog = null;

  /**
   * Format the SQL query in the editor using sql-formatter library.
   *
   * @returns {void}
   */
  let formatQuery = () => {
    let sql = sqlEditor.getValue().trim();
    if (!sql) {
      return;
    }

    sql = sqlFormatter.format(sql);
    sqlEditor.setValue(sql);
  };

  /**
   * Execute the SQL query in the editor.
   *
   * @returns {void}
   */
  let executeQuery = async () => {
    if (isExecuting) {
      return;
    }

    let sql = sqlEditor.getValue().trim();
    if (!sql) {
      console.warn('No SQL query to execute.');
      // TODO
      //window.alert(this.t('enter_sql_query'), 'warning');
      sqlExecutionDialog.hide();
      return;
    }

    // TODO
    // SQL実行前の警告ダイアログ
    //const confirmed = await this.showExecutionWarning(sql);
    //if (!confirmed) {
    //  return;
    //}

    isExecuting = true;

    try {
      // POST / API call to execute SQL
      let postData = new FormData();
      postData.append('action', 'api_query');
      postData.append('cluster', getCurrentCluster());
      getTargetShards().forEach(db => {
        console.log(db);
        postData.append('shards[]', db);
      });
      postData.append('sql', sql);
      console.log(postData);

      fetch('', {
        method: 'POST',
        body: postData
      })
      .then(response => response.json())
      .then(data => {
        console.log('Query response:', data);
        if (data.hasError) {
          console.error('Query execution error:', data.error);
        }
        currentResults = data;
        renderResults(data);
      })
      .catch(error => {
        console.error('Error executing query:', error);
      })
      .finally(() => {
        isExecuting = false;
      });
    } catch (error) {
      console.error('Unexpected error:', error);
      isExecuting = false;
    }

    sqlExecutionDialog.hide();
  };

  let confirmSqlExecution = () => {
    sqlExecutionDialog.show();
  };

  /**
   * Initialize CodeMirror editor and bind buttons.
   *
   * @returns {void}
   */
  let initSqlEditor = () => {
    sqlExecutionDialog = new bootstrap.Modal(sqlExecutionDialogElem, {backdrop: 'static', keyboard: false});
    historyDialog = new bootstrap.Modal(historyDialogElem, {backdrop: 'static', keyboard: false});

    document.getElementById('btn-format')?.addEventListener('click', () => formatQuery());
    document.getElementById('btn-execute')?.addEventListener('click', () => confirmSqlExecution());
    document.getElementById('btn-confirm-execute')?.addEventListener('click', () => executeQuery());
    document.getElementById('btn-history')?.addEventListener('click', () => createHistoryContent());

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
        'Ctrl-Enter': () => {
          confirmSqlExecution();
        },
        'Ctrl-Space': 'autocomplete'
      },
      value: ''
    });
    sqlEditor.setSize('100%', '100%');
  };

  /**
   * Create history content
   */
  let createHistoryContent = () => {
    fetch('?action=api_history')
      .then(response => response.json())
      .then(data => {
        // data = {
        //   "histories": [
        //     {"cluster": "development_cluster", "sql": "select * from users", "timestamp": 1757430299, "formattedTime": "2025-09-09T15:04:59+00:00"}
        //   ]
        // }

        console.log('History result:', data);

        let container = document.createElement('div');

        if (data.histories.length === 0) {
          container.innerHTML = '<p class="text-muted">No query history available.</p>';
          return container;
        }

        let historyList = document.createElement('div');
        historyList.className = 'history-list';

        data.histories.forEach((item) => {
          let historyItem = document.createElement('div');
          historyItem.className = 'history-item';
          historyItem.addEventListener('click', () => {
            sqlEditor.setValue(item.sql);
            historyDialog.hide();
          });

          let timeDiv = document.createElement('div');
          timeDiv.className = 'history-time';
          timeDiv.textContent = item.formattedTime + ' @' + item.cluster;

          let sqlDiv = document.createElement('div');
          sqlDiv.className = 'history-sql';
          sqlDiv.textContent = item.sql.length > 100 ? item.sql.substring(0, 100) + '...' : item.sql;

          historyItem.appendChild(timeDiv);
          historyItem.appendChild(sqlDiv);
          historyList.appendChild(historyItem);
        });

        container.appendChild(historyList);
        historyDialogElem.querySelector('.modal-body').innerHTML = '';
        historyDialogElem.querySelector('.modal-body').appendChild(container);
        historyDialog.show();
      })
      .catch(error => {
        console.error('Error fetching history:', error);
      });
  };

  initSqlEditor();

  // ------------------------------------------------------------

  let initResultsTabs = () => {
    document.querySelectorAll('#results-tabs .results-tab').forEach(btn => {
      btn.addEventListener('click', evt => {
        let targetId = evt.target.dataset.target;
        activateResultTab(targetId);
      });
    });

    document.getElementById('tab-nav-left')?.addEventListener('click', () => scrollTabs('left'));
    document.getElementById('tab-nav-right')?.addEventListener('click', () => scrollTabs('right'));
    updateTabNavigation();
  };

  let activateResultTab = (targetId) => {
    activateTab(targetId, '#results-tabs .results-tab', '#results-content .tab-pane');
  };

  let initSidebarTabs = () => {
    document.querySelectorAll('#sidebar-tabs .sidebar-tab').forEach(btn => {
      btn.addEventListener('click', evt => {
        let targetId = evt.target.dataset.target;
        activateSidebarTab(targetId);
      });
    });
    activateSidebarTab('stab-1');
  };

  let activateSidebarTab = (targetId) => {
    activateTab(targetId, '#sidebar-tabs .sidebar-tab', '#sidebar-content .tab-pane');
  };

  let activateTab = (targetId, tabCssSelector, tabContentSelector) => {
    // Tab
    document.querySelectorAll(tabCssSelector).forEach(tab => {
      tab.classList.remove('active');
      if (tab.dataset.target === targetId) {
        tab.classList.add('active');
      }
    });

    // Tab panel
    document.querySelectorAll(tabContentSelector).forEach(pane => {
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
  initSidebarTabs();

  // ------------------------------------------------------------

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

  let renderResults = (response) => {
    let tabArea = document.getElementById('results-tabs');
    let gridArea = document.getElementById('results-content');

    tabArea.innerHTML = '';
    gridArea.innerHTML = '';

    response.resultSet.forEach(result => {
      let id = result.id;
      let errors = result.errors;
      let rows = result.rows;
      let sql = result.sql;
      let results = result.results;

      // Tab format 1 (Success)
      // <button class="results-tab" type="button" data-target="tab-1" role="tab">
      //   <i class="bi bi-check-circle"></i> Query 1 (10)
      // </button>
      // Tab format 2 (Error)
      // <button class="results-tab error" type="button" data-target="tab-2" role="tab">
      //   <i class="bi bi-exclamation-triangle"></i> Query 2 (0)
      // </button>
      let tabBtn = document.createElement('button');
      tabBtn.className = 'results-tab';

      if (errors.length > 0) {
        tabBtn.classList.add('error');
        tabBtn.innerHTML = `<i class="bi bi-exclamation-triangle"></i> Query ${id} (${rows})`;
      } else {
        tabBtn.innerHTML = `<i class="bi bi-check-circle"></i> Query ${id} (${rows})`;
      }
      tabBtn.type = 'button';
      tabBtn.setAttribute('data-target', `tab-${id}`);
      tabBtn.setAttribute('role', 'tab');
      tabBtn.addEventListener('click', evt => {
        let targetId = evt.target.dataset.target;
        activateResultTab(targetId);
      });
      tabArea.appendChild(tabBtn);

      // Tab panel format (error + grid), no error-list if no error
      // <div class="tab-pane" id="tab-3" role="tabpanel">
      //   <div class="error-list">
      //     <div class="alert alert-danger align-items-center sql-error" role="alert">
      //       <strong>ERROR [shard1]</strong> syntax error at or near "FROM"
      //     </div>
      //   </div>
      // </div>
      let tabPane = document.createElement('div');
      tabPane.className = 'tab-pane';
      tabPane.id = `tab-${id}`;
      tabPane.setAttribute('role', 'tabpanel');
      gridArea.appendChild(tabPane);

      if (errors.length > 0) {
        let errorListDiv = document.createElement('div');
        errorListDiv.className = 'error-list';
        tabPane.appendChild(errorListDiv);
        for (let error of errors) {
          let errorDiv = document.createElement('div');
          errorDiv.className = 'alert alert-danger align-items-center sql-error';
          errorDiv.setAttribute('role', 'alert');
          errorDiv.innerHTML = `<strong>ERROR [${error.shard}]</strong> ${error.message}`;
          errorListDiv.appendChild(errorDiv);
        }
      }
      createResultGrid(tabPane, results);
    });
    activateResultTab('tab-1');
  };

  // TODO: Remove this test data in production
  let testResults = {
    'cluster': getCurrentCluster(),
    'resultSet': [
      {
        'id': 1, 'errors': [], 'rows': 0, 'sql': 'select * from user1;',
        'results': [
          {_shard: 'db1', id: 1, name: 'Alice'  }, {_shard: 'db2', id:  2, name: 'Bob'  }, {_shard: 'db1', id: 3, name: 'Charlie'}, {_shard: 'db2', id:  4, name: 'David'}, {_shard: 'db1', id: 5, name: 'Eve'    }, {_shard: 'db2', id:  6, name: 'Frank'}, {_shard: 'db1', id: 7, name: 'Grace'  }, {_shard: 'db2', id:  8, name: 'Hank' },
          {_shard: 'db1', id: 9, name: 'Ivy'    }, {_shard: 'db2', id: 10, name: 'Jack' }, {_shard: 'db1', id: 1, name: 'Alice'  }, {_shard: 'db2', id:  2, name: 'Bob'  }, {_shard: 'db1', id: 3, name: 'Charlie'}, {_shard: 'db2', id:  4, name: 'David'}, {_shard: 'db1', id: 5, name: 'Eve'    }, {_shard: 'db2', id:  6, name: 'Frank'},
          {_shard: 'db1', id: 7, name: 'Grace'  }, {_shard: 'db2', id:  8, name: 'Hank' }, {_shard: 'db1', id: 9, name: 'Ivy'    }, {_shard: 'db2', id: 10, name: 'Jack' }, {_shard: 'db1', id: 1, name: 'Alice'  }, {_shard: 'db2', id:  2, name: 'Bob'  }, {_shard: 'db1', id: 3, name: 'Charlie'}, {_shard: 'db2', id:  4, name: 'David'},
          {_shard: 'db1', id: 5, name: 'Eve'    }, {_shard: 'db2', id:  6, name: 'Frank'}, {_shard: 'db1', id: 7, name: 'Grace'  }, {_shard: 'db2', id:  8, name: 'Hank' }, {_shard: 'db1', id: 9, name: 'Ivy'    }, {_shard: 'db2', id: 10, name: 'Jack' }
        ]
      },
      {
        'id': 2, 'errors': [{'shard': 'db1', 'message': 'error message example'}], 'rows': 0, 'sql': 'select * from user1;',
        'results': [
          {_shard: 'db1', id: 1, name: 'Alice'  }, {_shard: 'db2', id:  2, name: 'Bob'  }, {_shard: 'db1', id: 3, name: 'Charlie'}, {_shard: 'db2', id:  4, name: 'David'}, {_shard: 'db1', id: 5, name: 'Eve'    }, {_shard: 'db2', id:  6, name: 'Frank'}, {_shard: 'db1', id: 7, name: 'Grace'  }, {_shard: 'db2', id:  8, name: 'Hank' },
          {_shard: 'db1', id: 9, name: 'Ivy'    }, {_shard: 'db2', id: 10, name: 'Jack' }, {_shard: 'db1', id: 1, name: 'Alice'  }, {_shard: 'db2', id:  2, name: 'Bob'  }, {_shard: 'db1', id: 3, name: 'Charlie'}, {_shard: 'db2', id:  4, name: 'David'}, {_shard: 'db1', id: 5, name: 'Eve'    }, {_shard: 'db2', id:  6, name: 'Frank'},
          {_shard: 'db1', id: 7, name: 'Grace'  }, {_shard: 'db2', id:  8, name: 'Hank' }, {_shard: 'db1', id: 9, name: 'Ivy'    }, {_shard: 'db2', id: 10, name: 'Jack' }, {_shard: 'db1', id: 1, name: 'Alice'  }, {_shard: 'db2', id:  2, name: 'Bob'  }, {_shard: 'db1', id: 3, name: 'Charlie'}, {_shard: 'db2', id:  4, name: 'David'},
          {_shard: 'db1', id: 5, name: 'Eve'    }, {_shard: 'db2', id:  6, name: 'Frank'}, {_shard: 'db1', id: 7, name: 'Grace'  }, {_shard: 'db2', id:  8, name: 'Hank' }, {_shard: 'db1', id: 9, name: 'Ivy'    }, {_shard: 'db2', id: 10, name: 'Jack' }
        ],
      },
      {
        'id': 3,
        'errors': [
          {'shard': 'db1', 'message': 'error message example'},
          {'shard': 'db1', 'message': 'error message example'},
          {'shard': 'db1', 'message': 'error message example'},
          {'shard': 'db1', 'message': 'error message example'},
          {'shard': 'db1', 'message': 'error message example'},
          {'shard': 'db1', 'message': 'error message example'}
        ],
        'rows': 0, 'sql': 'select * from user1;',
        'results': [
          {_shard: 'db1', id: 1, name: 'Alice'  }, {_shard: 'db2', id:  2, name: 'Bob'  }, {_shard: 'db1', id: 3, name: 'Charlie'}, {_shard: 'db2', id:  4, name: 'David'}, {_shard: 'db1', id: 5, name: 'Eve'    }, {_shard: 'db2', id:  6, name: 'Frank'}, {_shard: 'db1', id: 7, name: 'Grace'  }, {_shard: 'db2', id:  8, name: 'Hank' },
          {_shard: 'db1', id: 9, name: 'Ivy'    }, {_shard: 'db2', id: 10, name: 'Jack' }, {_shard: 'db1', id: 1, name: 'Alice'  }, {_shard: 'db2', id:  2, name: 'Bob'  }, {_shard: 'db1', id: 3, name: 'Charlie'}, {_shard: 'db2', id:  4, name: 'David'}, {_shard: 'db1', id: 5, name: 'Eve'    }, {_shard: 'db2', id:  6, name: 'Frank'},
          {_shard: 'db1', id: 7, name: 'Grace'  }, {_shard: 'db2', id:  8, name: 'Hank' }, {_shard: 'db1', id: 9, name: 'Ivy'    }, {_shard: 'db2', id: 10, name: 'Jack' }, {_shard: 'db1', id: 1, name: 'Alice'  }, {_shard: 'db2', id:  2, name: 'Bob'  }, {_shard: 'db1', id: 3, name: 'Charlie'}, {_shard: 'db2', id:  4, name: 'David'},
          {_shard: 'db1', id: 5, name: 'Eve'    }, {_shard: 'db2', id:  6, name: 'Frank'}, {_shard: 'db1', id: 7, name: 'Grace'  }, {_shard: 'db2', id:  8, name: 'Hank' }, {_shard: 'db1', id: 9, name: 'Ivy'    }, {_shard: 'db2', id: 10, name: 'Jack' }
        ],
      },
      {'id': 4, 'errors': [], 'rows': 0, 'sql': 'select * from user1;', 'results': [{_shard: 'db1', id: 1, name: 'Alice'}, {_shard: 'db2', id:  2, name: 'Bob'}, {_shard: 'db1', id: 3, name: 'Charlie'}]},
      {'id': 5, 'errors': [], 'rows': 0, 'sql': 'select * from user1;', 'results': [{_shard: 'db1', id: 1, name: 'Alice'}, {_shard: 'db2', id:  2, name: 'Bob'}, {_shard: 'db1', id: 3, name: 'Charlie'}]},
      {'id': 6, 'errors': [], 'rows': 0, 'sql': 'select * from user1;', 'results': [{_shard: 'db1', id: 1, name: 'Alice'}, {_shard: 'db2', id:  2, name: 'Bob'}, {_shard: 'db1', id: 3, name: 'Charlie'}]},
      {'id': 7, 'errors': [], 'rows': 0, 'sql': 'select * from user1;', 'results': [{_shard: 'db1', id: 1, name: 'Alice'}, {_shard: 'db2', id:  2, name: 'Bob'}, {_shard: 'db1', id: 3, name: 'Charlie'}]},
      {'id': 8, 'errors': [], 'rows': 0, 'sql': 'select * from user1;', 'results': [{_shard: 'db1', id: 1, name: 'Alice'}, {_shard: 'db2', id:  2, name: 'Bob'}, {_shard: 'db1', id: 3, name: 'Charlie'}]},
      {'id': 9, 'errors': [], 'rows': 0, 'sql': 'select * from user1;', 'results': [{_shard: 'db1', id: 1, name: 'Alice'}, {_shard: 'db2', id:  2, name: 'Bob'}, {_shard: 'db1', id: 3, name: 'Charlie'}]},
      {'id': 10, 'errors': [], 'rows': 0, 'sql': 'select * from user1;', 'results': [{_shard: 'db1', id: 1, name: 'Alice'}, {_shard: 'db2', id:  2, name: 'Bob'}, {_shard: 'db1', id: 3, name: 'Charlie'}]}
    ],
    'hasError': false
  };

  renderResults(testResults);

  // ------------------------------------------------------------

  let initialize = () => {
    let clusterName = getCurrentCluster();

    // Load cluster settings via AJAX
    fetch(`?action=api_initial_data&cluster=${encodeURIComponent(clusterName)}`)
      .then(response => response.json())
      .then(data => {
        console.log(data);
        let elem = document.getElementById('table-list');
        elem.innerHTML = '';
        for (let i in data.tables) {
          let table = data.tables[i];
          let itemDiv = document.createElement('div');
          itemDiv.className = 'table-item';

          let physicalDiv = document.createElement('div');
          physicalDiv.className = 'table-physical-name';
          physicalDiv.textContent = table.name;

          let logicalDiv = document.createElement('div');
          logicalDiv.className = 'table-logical-name';
          logicalDiv.textContent = table.comment || table.name;

          itemDiv.appendChild(physicalDiv);
          itemDiv.appendChild(logicalDiv);
          elem.appendChild(itemDiv);
        };

        dbSelector.innerHTML = '';
        data.shardList.forEach((db, i) => {
          let option = document.createElement('option');
          option.value = db;
          option.innerHTML = db;
          option.setAttribute('selected', true);
          dbSelector.appendChild(option);
        });
      })
      .catch(error => {
        console.error('Error fetching cluster settings:', error);
      });
  };

  initialize();

  // ------------------------------------------------------------

  let adjustStyles = () => {
    document.querySelectorAll('.table-list, #db-selector').forEach(el => {
      el.style.height = (window.innerHeight - el.offsetTop) + 'px';
    });

    document.querySelectorAll('.tab-pane').forEach(el => {
      el.style.height = (window.innerHeight - el.offsetTop) + 'px';
    });

    let selectedDbCount = 0;
    dbSelector.querySelectorAll('option').forEach(el => {
      if (el.selected) {
        selectedDbCount++;
      }
    });
    document.getElementById('db-count').innerHTML = selectedDbCount;

    setTimeout(adjustStyles, 100);
  };

  adjustStyles();
  // ------------------------------------------------------------

})(window, window.document);
