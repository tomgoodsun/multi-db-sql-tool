(function(window, document) {
  let dbSelector = document.getElementById('db-selector');

  /**
   * Get the currently selected cluster from the dropdown.
   *
   * @returns {string} - Selected cluster name
   */
  let getCurrentCluster = () => {
    let clusterName = document.getElementById('cluster-selector').value;
    console.log('Selected cluster:', clusterName);
    return clusterName;
  };

  /**
   * Get the selected target shards (databases) from the multi-select dropdown.
   *
   * @returns {Array} - Selected target shards (databases)
   */
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
  let alertDialogElem = document.getElementById('alert-dialog');
  let alertDialog = null;
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
   * Split SQL statements by semicolon.
   *
   * @param {string} sql
   * @returns {Array<string>}
   */
  let splitSql = (sql) => {
    // Simple split by semicolon, ignoring semicolons in quotes
    let sqls = [];
    let currentStatement = '';
    let inSingleQuote = false;
    let inDoubleQuote = false;
    let inBacktick = false;
    for (let char of sql) {
      if ("'" === char && !inDoubleQuote && !inBacktick) {
        inSingleQuote = !inSingleQuote;
      } else if ('"' === char && !inSingleQuote && !inBacktick) {
        inDoubleQuote = !inDoubleQuote;
      } else if ('`' === char && !inSingleQuote && !inDoubleQuote) {
        inBacktick = !inBacktick;
      }
      if (';' === char && !inSingleQuote && !inDoubleQuote && !inBacktick) {
        if (currentStatement.trim()) {
          sqls.push(currentStatement.trim());
          currentStatement = '';
        }
      } else {
        currentStatement += char;
      }
    }
    if (currentStatement.trim()) {
      sqls.push(currentStatement.trim());
    }
    return sqls;
  };

  /**
   * Clean the SQL query by removing comments and extra whitespace.
   *
   * @param {string} sql
   * @returns {string}
   */
  let cleanSql = (sql) => {
    // Remove comments and trim
    return sql.replace(/--.*$/gm, '').replace(/\/\*[\s\S]*?\*\//g, '').trim();
  };

  /**
   * Check if the SQL query is a reading query (SELECT, SHOW, DESC, DESCRIBE).
   *
   * @param {string} sql
   * @returns {boolean}
   */
  let isReadOnlyQuery = (sql) => {
    // Check if the SQL query is a SELECT, SHOW, DESC, DESCRIBE statement
    sql = cleanSql(sql);
    sql = sql.trim().toUpperCase();
    sql = sql.replace(/^[\s\(]+/, ''); // Remove leading spaces and parentheses
    return sql.startsWith('SELECT')
      || sql.startsWith('SHOW')
      || sql.startsWith('DESC')
      || sql.startsWith('DESCRIBE');
  };

  /**
   * Check if the SQL query can be executed (not read-only).
   *
   * @param {string} sql
   * @returns {boolean}
   */
  let canExecuteQuery = (sql) => {
    if (!window.MultiDbSql.isReadOnlyMode) {
      return true;
    }

    let sqls = splitSql(sql);
    if (0 === sqls.length) {
      return false;
    }

    return sqls.every(stmt => isReadOnlyQuery(stmt));
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

    console.log('Executing SQL:', sql);
    if (!canExecuteQuery(sql)) {
      console.log('This query is read-only and cannot be executed.');
      sqlExecutionDialog.hide();
      showAlert('This query is read-only and cannot be executed.', 'warning');
      return;
    }

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

      // This API call must user POST method to avoid URL length limit
      fetch('', {method: 'POST', body: postData})
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

  /**
   * Show alert dialog with a message.
   *
   * @param {string} message
   * @param {string} type
   * @returns {void}
   */
  let showAlert = (message, type = 'info') => {
    alertDialogElem.querySelector('.modal-body').innerHTML = message.replace(/\n/g, '<br>');
    let title = '<i class="bi bi-info-circle modal-icon modal-icon-info"></i> Info';
    if ('warning' === type) {
      title = '<i class="bi bi-exclamation-octagon-fill modal-icon modal-icon-warning"></i> Warning';
    } else if ('danger' === type || 'error' === type) {
      title = '<i class="bi bi-x-octagon-fill modal-icon modal-icon-danger"></i> Error';
    } else if ('success' === type) {
      title = '<i class="bi bi-check-circle-fill modal-icon modal-icon-success"></i> Success';
    }
    alertDialogElem.querySelector('.modal-title').innerHTML = title;
    alertDialog.show();
  };

  /**
   * Show confirmation dialog for SQL execution.
   *
   * @returns {void}
   */
  let confirmSqlExecution = () => {
    sqlExecutionDialog.show();
  };

  /**
   * Initialize CodeMirror editor and bind buttons.
   *
   * @returns {void}
   */
  let initSqlEditor = () => {
    alertDialog = new bootstrap.Modal(alertDialogElem, {backdrop: 'static', keyboard: false});
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
   *
   * @returns {void}
   */
  let createHistoryContent = () => {
    fetch('?action=api_history')
      .then(response => response.json())
      .then(data => {
        // TODO: Test data
        // data = {
        //   "histories": [
        //     {"cluster": "development_cluster", "sql": "select * from users", "timestamp": 1757430299, "formattedTime": "2025-09-09T15:04:59+00:00"}
        //   ]
        // }

        console.log('History result:', data);

        let container = document.createElement('div');

        if (0 === data.histories.length) {
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

  /**
   * Initialize result tabs and bind events.
   *
   * @returns {void}
   */
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

  /**
   * Activate a result tab.
   *
   * @param {string} targetId
   */
  let activateResultTab = (targetId) => {
    activateTab(targetId, '#results-tabs .results-tab', '#results-content .tab-pane');
  };

  /**
   * Initialize sidebar tabs and bind events.
   *
   * @returns {void}
   */
  let initSidebarTabs = () => {
    document.querySelectorAll('#sidebar-tabs .sidebar-tab').forEach(btn => {
      btn.addEventListener('click', evt => {
        let targetId = evt.target.dataset.target;
        activateSidebarTab(targetId);
      });
    });
    activateSidebarTab('stab-1');
  };

  /**
   * Activate a sidebar tab.
   *
   * @param {string} targetId
   */
  let activateSidebarTab = (targetId) => {
    activateTab(targetId, '#sidebar-tabs .sidebar-tab', '#sidebar-content .tab-pane');
  };

  /**
   * Activate a tab.
   *
   * @param {string} targetId
   * @param {string} tabCssSelector
   * @param {string} tabContentSelector
   */
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

  /**
   * Scroll the result tabs.
   *
   * @param {string} direction - The direction to scroll ('left' or 'right').
   */
  let scrollTabs = (direction) => {
    const tabsContainer = document.getElementById('results-tabs');
    if (!tabsContainer) {
      return;
    }

    const scrollAmount = 200;
    const currentScroll = tabsContainer.scrollLeft;

    if ('left' === direction) {
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

  /**
   * Update the state of the tab navigation buttons.
   *
   * @returns {void}
   */
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

  /**
   * Create a result grid using ag-Grid.
   *
   * @param {HTMLElement} container - The container element to hold the grid.
   * @param {Array} combinedData - The data to display in the grid.
   */
  let createResultGrid = (container, combinedData) => {
    if (!combinedData || 0 === combinedData.length) {
      return;
    }

    let gridDiv = document.createElement('div');
    gridDiv.className = 'results-grid ag-theme-alpine';
    container.appendChild(gridDiv);

    let columnDefs = Object.keys(combinedData[0]).map(key => ({
      field: key,
      headerName: '_shard' === key ? 'DB' : key,
      sortable: true,
      filter: true,
      resizable: true,
      pinned: '_shard' === key ? 'left' : false,
      cellClass: '_shard' === key ? 'shard-column' : '',
      width: '_shard' === key ? 150 : undefined
    }));

    let gridOptions = {
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

  /**
   * Render the query results into tabs and grids.
   *
   * @param {Object} response - The response object containing the result set.
   */
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
  //let testResults = {
  //  'cluster': getCurrentCluster(),
  //  'resultSet': [
  //    {
  //      'id': 1, 'errors': [], 'rows': 0, 'sql': 'select * from user1;',
  //      'results': [
  //        {_shard: 'db1', id: 1, name: 'Alice'  }, {_shard: 'db2', id:  2, name: 'Bob'  }, {_shard: 'db1', id: 3, name: 'Charlie'}, {_shard: 'db2', id:  4, name: 'David'}, {_shard: 'db1', id: 5, name: 'Eve'    }, {_shard: 'db2', id:  6, name: 'Frank'}, {_shard: 'db1', id: 7, name: 'Grace'  }, {_shard: 'db2', id:  8, name: 'Hank' },
  //        {_shard: 'db1', id: 9, name: 'Ivy'    }, {_shard: 'db2', id: 10, name: 'Jack' }, {_shard: 'db1', id: 1, name: 'Alice'  }, {_shard: 'db2', id:  2, name: 'Bob'  }, {_shard: 'db1', id: 3, name: 'Charlie'}, {_shard: 'db2', id:  4, name: 'David'}, {_shard: 'db1', id: 5, name: 'Eve'    }, {_shard: 'db2', id:  6, name: 'Frank'},
  //        {_shard: 'db1', id: 7, name: 'Grace'  }, {_shard: 'db2', id:  8, name: 'Hank' }, {_shard: 'db1', id: 9, name: 'Ivy'    }, {_shard: 'db2', id: 10, name: 'Jack' }, {_shard: 'db1', id: 1, name: 'Alice'  }, {_shard: 'db2', id:  2, name: 'Bob'  }, {_shard: 'db1', id: 3, name: 'Charlie'}, {_shard: 'db2', id:  4, name: 'David'},
  //        {_shard: 'db1', id: 5, name: 'Eve'    }, {_shard: 'db2', id:  6, name: 'Frank'}, {_shard: 'db1', id: 7, name: 'Grace'  }, {_shard: 'db2', id:  8, name: 'Hank' }, {_shard: 'db1', id: 9, name: 'Ivy'    }, {_shard: 'db2', id: 10, name: 'Jack' }
  //      ]
  //    },
  //    {
  //      'id': 3,
  //      'errors': [
  //        {'shard': 'db1', 'message': 'error message example'},
  //        {'shard': 'db1', 'message': 'error message example'}
  //      ],
  //      'rows': 0, 'sql': 'select * from user1;',
  //      'results': [
  //        {_shard: 'db1', id: 1, name: 'Alice'  }, {_shard: 'db2', id:  2, name: 'Bob'  }, {_shard: 'db1', id: 3, name: 'Charlie'}, {_shard: 'db2', id:  4, name: 'David'}, {_shard: 'db1', id: 5, name: 'Eve'    }, {_shard: 'db2', id:  6, name: 'Frank'}, {_shard: 'db1', id: 7, name: 'Grace'  }, {_shard: 'db2', id:  8, name: 'Hank' },
  //        {_shard: 'db1', id: 9, name: 'Ivy'    }, {_shard: 'db2', id: 10, name: 'Jack' }, {_shard: 'db1', id: 1, name: 'Alice'  }, {_shard: 'db2', id:  2, name: 'Bob'  }, {_shard: 'db1', id: 3, name: 'Charlie'}, {_shard: 'db2', id:  4, name: 'David'}, {_shard: 'db1', id: 5, name: 'Eve'    }, {_shard: 'db2', id:  6, name: 'Frank'},
  //        {_shard: 'db1', id: 7, name: 'Grace'  }, {_shard: 'db2', id:  8, name: 'Hank' }, {_shard: 'db1', id: 9, name: 'Ivy'    }, {_shard: 'db2', id: 10, name: 'Jack' }, {_shard: 'db1', id: 1, name: 'Alice'  }, {_shard: 'db2', id:  2, name: 'Bob'  }, {_shard: 'db1', id: 3, name: 'Charlie'}, {_shard: 'db2', id:  4, name: 'David'},
  //        {_shard: 'db1', id: 5, name: 'Eve'    }, {_shard: 'db2', id:  6, name: 'Frank'}, {_shard: 'db1', id: 7, name: 'Grace'  }, {_shard: 'db2', id:  8, name: 'Hank' }, {_shard: 'db1', id: 9, name: 'Ivy'    }, {_shard: 'db2', id: 10, name: 'Jack' }
  //      ],
  //    },
  //    {'id': 10, 'errors': [], 'rows': 0, 'sql': 'select * from user1;', 'results': [{_shard: 'db1', id: 1, name: 'Alice'}, {_shard: 'db2', id:  2, name: 'Bob'}, {_shard: 'db1', id: 3, name: 'Charlie'}]}
  //  ],
  //  'hasError': false
  //};
  //currentResults = testResults;
  //renderResults(testResults);

  // ------------------------------------------------------------

  /**
   * Export results to CSV
   *
   * @returns {void}
   */
  let exportResultsCsv = () => {
    if (!currentResults || 0 === Object.keys(currentResults).length) {
      //this.showAlert('No results to export', 'warning');
      console.log('No results to export', 'warning');
      return;
    }

    currentResults.resultSet.forEach(resultSetItem => {
      let csv = convertToCSV(resultSetItem.results);
      let dateStr = new Date().toISOString().slice(0, 19).replace(/:/g, '-');
      let filename = `${window.MultiDbSql.appShortNameLower}-result-${dateStr}-query-${resultSetItem.id}.csv`;
      downloadCSV(csv, filename);
    });
  };

  /**
   * Convert data to CSV format
   *
   * @param {Object} data
   * @returns
   */
  let convertToCSV = (data) => {
    if (0 === data.length) {
      return '';
    }

    let headers = Object.keys(data[0]);
    let csvContent = [
      headers.join(','),
      ...data.map(row =>
        headers.map(header => {
          let value = row[header] || '';
          let escaped = String(value).replace(/"/g, '""');
          if (escaped.includes(',') || escaped.includes('"') || escaped.includes('\n')) {
            return `"${escaped}"`;
          }
          return escaped;
        }).join(',')
      )
    ].join('\n');

    return csvContent;
  };

  /**
   * Download CSV file
   *
   * @param {string} csvContent
   * @param {string} filename
   */
  let downloadCSV = (csvContent, filename) => {
    let blob = new Blob([csvContent], {type: 'text/csv;charset=utf-8;'});
    let link = document.createElement('a');
    if (undefined !== link.download) {
      let url = URL.createObjectURL(blob);
      link.setAttribute('href', url);
      link.setAttribute('download', filename);
      link.style.visibility = 'hidden';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    }
  };

  /**
   * Export results to XLSX
   *
   * @returns {void}
   */
  let exportResults = () => {
    if (!currentResults || 0 === Object.keys(currentResults).length) {
      //this.showAlert('No results to export', 'warning');
      console.log('No results to export', 'warning');
      return;
    }

    try {
      // Options for workbook
      let wsopts = {
        dateNF: 'yyyy-mm-dd hh:mm:ss'
      };

      let workbook = XLSX.utils.book_new();

      currentResults.resultSet.forEach(resultSetItem => {
        let combinedData = resultSetItem.results;
        if (0 === combinedData.length) {
          combinedData = [{}];
        }
        let worksheet = XLSX.utils.json_to_sheet(combinedData, wsopts);
        let sheetName = `Query ${resultSetItem.id}`;
        XLSX.utils.book_append_sheet(workbook, worksheet, sheetName);
      });

      let dateStr = new Date().toISOString().slice(0, 19).replace(/:/g, '-');
      let filename = `${window.MultiDbSql.appShortNameLower}-results-${dateStr}.xlsx`;
      XLSX.writeFile(workbook, filename);

      //this.showAlert('Results exported successfully', 'success');
      console.log('Results exported successfully', 'success');
    } catch (error) {
      console.error('Export error:', error);
      //this.showAlert('Export failed: ' + error.message, 'danger');
      console.log('Export failed: ' + error.message, 'danger');
    }
  };

  /**
   * Initialize result exporter by binding buttons.
   *
   * @returns {void}
   */
  let initResultExporter = () => {
    document.getElementById('btn-export-csv')?.addEventListener('click', () => exportResultsCsv());
    document.getElementById('btn-export')?.addEventListener('click', () => exportResults());
  };

  initResultExporter();

  // ------------------------------------------------------------

  /**
   * Initialize the application by loading cluster settings and populating the UI.
   *
   * @returns {void}
   */
  let initialize = () => {
    let clusterName = getCurrentCluster();

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

  /**
   * Adjust styles dynamically based on window size.
   *
   * @returns {void}
   */
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
