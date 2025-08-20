/**
 * Multi-DB SQL Tool JavaScript
 * CodeMirror 5対応版
 */

class MultiDbSqlTool {
  constructor() {
    this.editor = null;
    this.currentResults = {};
    this.activeTab = null;
    this.isExecuting = false;

    this.init();
  }

  /**
   * Initialize application
   */
  async init() {
    try {
      await this.initializeEditor();
      this.setupEventListeners();
      await this.loadStatus();

      console.log('Multi-DB SQL Tool initialized successfully');
    } catch (error) {
      console.error('Failed to initialize application:', error);
      this.showAlert('Failed to initialize application: ' + error.message, 'danger');
    }
  }

  /**
   * Initialize CodeMirror 5 editor
   */
  async initializeEditor() {
    const editorElement = document.getElementById('sql-editor');
    if (!editorElement) {
      throw new Error('SQL editor element not found');
    }

    this.editor = CodeMirror(editorElement, {
      mode: 'text/x-mysql',
      theme: 'eclipse',
      lineNumbers: true,
      indentUnit: 2,
      smartIndent: true,
      lineWrapping: false,
      viewportMargin: Infinity,
      extraKeys: {
        "Ctrl-Enter": () => {
          this.executeQuery();
        },
        "Ctrl-Space": "autocomplete"
      },
      value: ''
    });

    // エディターサイズを領域にフィットさせる
    this.editor.setSize('100%', '100%');
    
    // 初期化後に確実にサイズを適用
    setTimeout(() => {
      // CodeMirrorの内部要素に直接サイズを設定
      const wrapper = this.editor.getWrapperElement();
      if (wrapper) {
        wrapper.style.height = '100%';
        wrapper.style.width = '100%';
      }
      
      const scrollElement = this.editor.getScrollerElement();
      if (scrollElement) {
        scrollElement.style.height = '100%';
      }
      
      // ガッターの高さを動的に設定
      this.adjustGutterHeight();
      
      // 最終的にリフレッシュ
      this.editor.refresh();
    }, 100);
    
    // リサイズ時の調整
    window.addEventListener('resize', () => {
      this.editor.refresh();
      setTimeout(() => this.adjustGutterHeight(), 50);
    });
  }

  /**
   * Adjust CodeMirror gutter height
   */
  adjustGutterHeight() {
    if (!this.editor) return;
    
    const wrapper = this.editor.getWrapperElement();
    const gutters = wrapper?.querySelector('.CodeMirror-gutters');
    
    if (gutters && wrapper) {
      const wrapperHeight = wrapper.offsetHeight;
      gutters.style.height = `${wrapperHeight}px`;
    }
  }

  /**
   * Setup event listeners
   */
  setupEventListeners() {
    document.getElementById('btn-execute')?.addEventListener('click', () => this.executeQuery());
    document.getElementById('btn-format')?.addEventListener('click', () => this.formatQuery());
    document.getElementById('btn-history')?.addEventListener('click', () => this.showHistory());
    document.getElementById('btn-export-csv')?.addEventListener('click', () => this.exportResultsCsv());
    document.getElementById('btn-export')?.addEventListener('click', () => this.exportResults());

    document.getElementById('cluster-selector')?.addEventListener('change', (e) => {
      this.switchCluster(e.target.value);
    });

    document.getElementById('tab-nav-left')?.addEventListener('click', () => this.scrollTabs('left'));
    document.getElementById('tab-nav-right')?.addEventListener('click', () => this.scrollTabs('right'));

    document.addEventListener('click', (e) => {
      if (e.target.classList.contains('table-physical-name')) {
        this.insertTableName(e.target.textContent.trim());
      } else if (e.target.closest('.table-item')) {
        const physicalName = e.target.closest('.table-item').querySelector('.table-physical-name');
        if (physicalName) {
          this.insertTableName(physicalName.textContent.trim());
        }
      }
    });
  }

  /**
   * Execute SQL query
   */
  async executeQuery() {
    if (this.isExecuting) return;

    const sql = this.editor.getValue().trim();
    if (!sql) {
      this.showAlert('Please enter a SQL query', 'warning');
      return;
    }

    // SQL実行前の警告ダイアログ
    const confirmed = await this.showExecutionWarning(sql);
    if (!confirmed) {
      return;
    }

    this.setExecuting(true);

    try {
      const response = await this.apiCall('execute', { sql });

      if (response.success) {
        this.displayResults(response.data.results);
        this.showAlert('Query executed successfully', 'success');
      } else {
        throw new Error(response.error);
      }
    } catch (error) {
      console.error('Query execution error:', error);
      this.showAlert('Query execution failed: ' + error.message, 'danger');
    } finally {
      this.setExecuting(false);
    }
  }

  /**
   * Format SQL query
   */
  async formatQuery() {
    const sql = this.editor.getValue().trim();
    if (!sql) return;

    try {
      const response = await this.apiCall('format', { sql });
      if (response.success) {
        this.editor.setValue(response.data.formatted_sql);
        this.showAlert('SQL formatted successfully', 'success');
      } else {
        throw new Error(response.error);
      }
    } catch (error) {
      console.error('Format error:', error);
      this.showAlert('Format failed: ' + error.message, 'danger');
    }
  }

  /**
   * Show query history
   */
  async showHistory() {
    try {
      const response = await this.apiCall('history');
      if (response.success) {
        this.displayHistoryModal(response.data.history);
      } else {
        throw new Error(response.error);
      }
    } catch (error) {
      console.error('History load error:', error);
      this.showAlert('Failed to load history: ' + error.message, 'danger');
    }
  }

  /**
   * Load status information
   */
  async loadStatus() {
    try {
      const response = await this.apiCall('status');
      if (response.success) {
        this.updateStatusDisplay(response.data);
      } else {
        throw new Error(response.error);
      }
    } catch (error) {
      console.error('Status load error:', error);
    }
  }

  /**
   * Switch cluster
   */
  async switchCluster(clusterName) {
    try {
      const response = await this.apiCall('switch_cluster', { cluster: clusterName });
      if (response.success) {
        await this.loadStatus();
        this.showAlert(`Switched to cluster: ${clusterName}`, 'success');
      } else {
        throw new Error(response.error);
      }
    } catch (error) {
      console.error('Cluster switch error:', error);
      this.showAlert('Failed to switch cluster: ' + error.message, 'danger');
    }
  }

  /**
   * Insert table name into editor
   */
  insertTableName(tableName) {
    const cursor = this.editor.getCursor();
    this.editor.replaceRange(tableName, cursor);
    this.editor.focus();
  }

  /**
   * Display query results
   */
  displayResults(results) {
    this.currentResults = results;

    const tabsContainer = document.getElementById('results-tabs');
    const contentContainer = document.getElementById('results-content');

    if (!tabsContainer || !contentContainer) {
      console.error('Results container elements not found');
      return;
    }

    tabsContainer.innerHTML = '';
    contentContainer.innerHTML = '';

    const queryGroups = this.groupResultsByQuery(results);

    queryGroups.forEach((queryGroup, index) => {
      this.createResultTab(queryGroup, index, tabsContainer, contentContainer);
    });

    if (queryGroups.length > 0) {
      this.activateTab(0);
      setTimeout(() => this.updateTabNavigation(), 100);
    }
  }

  /**
   * Group results by query index
   */
  groupResultsByQuery(results) {
    const groups = new Map();

    Object.entries(results).forEach(([shardName, shardData]) => {
      shardData.queries.forEach(queryResult => {
        const queryIndex = queryResult.query_index;

        if (!groups.has(queryIndex)) {
          groups.set(queryIndex, {
            queryIndex,
            query: queryResult.query,
            shards: [],
            totalRows: 0,
            hasErrors: false
          });
        }

        const group = groups.get(queryIndex);
        group.shards.push({
          shardName,
          displayName: shardData.display_name,
          result: queryResult
        });

        if (queryResult.success) {
          group.totalRows += queryResult.row_count;
        } else {
          group.hasErrors = true;
        }
      });
    });

    return Array.from(groups.values()).sort((a, b) => a.queryIndex - b.queryIndex);
  }

  /**
   * Create result tab
   */
  createResultTab(queryGroup, index, tabsContainer, contentContainer) {
    const tab = document.createElement('button');
    tab.className = `results-tab ${queryGroup.hasErrors ? 'error' : ''}`;

    if (queryGroup.hasErrors) {
      tab.innerHTML = `<i class="bi bi-exclamation-triangle"></i> Query ${queryGroup.queryIndex}(0)`;
    } else {
      tab.innerHTML = `<i class="bi bi-check-circle"></i> Query ${queryGroup.queryIndex}(${queryGroup.totalRows})`;
    }

    tab.addEventListener('click', () => this.activateTab(index));
    tabsContainer.appendChild(tab);

    const tabPane = document.createElement('div');
    tabPane.className = 'tab-pane';
    tabPane.id = `tab-${index}`;

    if (queryGroup.hasErrors) {
      this.createErrorContent(tabPane, queryGroup);
    } else {
      this.createGridContent(tabPane, queryGroup);
    }

    contentContainer.appendChild(tabPane);
  }

  /**
   * Create error content
   */
  createErrorContent(container, queryGroup) {
    const errorDiv = document.createElement('div');
    errorDiv.className = 'p-3';

    const queryDiv = document.createElement('div');
    queryDiv.className = 'mb-3';
    queryDiv.innerHTML = `<strong>Query:</strong> <code>${this.escapeHtml(queryGroup.query)}</code>`;
    errorDiv.appendChild(queryDiv);

    queryGroup.shards.forEach(shard => {
      if (!shard.result.success) {
        const shardErrorDiv = document.createElement('div');
        shardErrorDiv.className = 'alert alert-danger';
        shardErrorDiv.innerHTML = `<strong>${this.escapeHtml(shard.displayName)}:</strong> ${this.escapeHtml(shard.result.error)}`;
        errorDiv.appendChild(shardErrorDiv);
      }
    });

    container.appendChild(errorDiv);
  }

  /**
   * Create grid content
   */
  createGridContent(container, queryGroup) {
    const combinedData = [];

    queryGroup.shards.forEach(shard => {
      if (shard.result.success && shard.result.data) {
        combinedData.push(...shard.result.data);
      }
    });

    if (combinedData.length === 0) {
      const noDataDiv = document.createElement('div');
      noDataDiv.className = 'p-3 text-muted';
      noDataDiv.textContent = 'No data returned';
      container.appendChild(noDataDiv);
      return;
    }

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
  }

  /**
   * Activate tab
   */
  activateTab(index) {
    document.querySelectorAll('.results-tab').forEach(tab => tab.classList.remove('active'));
    document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));

    const tabs = document.querySelectorAll('.results-tab');
    const panes = document.querySelectorAll('.tab-pane');

    if (tabs[index]) tabs[index].classList.add('active');
    if (panes[index]) panes[index].classList.add('active');

    this.activeTab = index;
  }

  /**
   * Scroll tabs left or right
   */
  scrollTabs(direction) {
    const tabsContainer = document.getElementById('results-tabs');
    if (!tabsContainer) return;

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

    setTimeout(() => this.updateTabNavigation(), 300);
  }

  /**
   * Update tab navigation button states
   */
  updateTabNavigation() {
    const tabsContainer = document.getElementById('results-tabs');
    const leftBtn = document.getElementById('tab-nav-left');
    const rightBtn = document.getElementById('tab-nav-right');

    if (!tabsContainer || !leftBtn || !rightBtn) return;

    const { scrollLeft, scrollWidth, clientWidth } = tabsContainer;

    leftBtn.disabled = scrollLeft <= 0;
    rightBtn.disabled = scrollLeft >= scrollWidth - clientWidth - 1;
  }

  /**
   * Update status display
   */
  updateStatusDisplay(statusData) {
    const connectionList = document.getElementById('connection-status');
    const tablesList = document.getElementById('tables-list');

    if (connectionList) {
      connectionList.innerHTML = '';

      Object.entries(statusData.shards).forEach(([shardName, shardInfo]) => {
        const statusItem = document.createElement('div');
        statusItem.className = 'status-item';

        const indicator = document.createElement('div');
        indicator.className = `status-indicator ${shardInfo.connection.status === 'connected' ? 'connected' : 'failed'}`;

        const label = document.createElement('span');
        label.textContent = shardInfo.display_name;

        statusItem.appendChild(indicator);
        statusItem.appendChild(label);
        connectionList.appendChild(statusItem);
      });
    }

    if (tablesList) {
      tablesList.innerHTML = '';

      const allTables = new Set();
      Object.values(statusData.shards).forEach(shardInfo => {
        shardInfo.tables.forEach(table => allTables.add(table));
      });

      Array.from(allTables).sort().forEach(tableName => {
        const tableItem = document.createElement('div');
        tableItem.className = 'table-item';
        tableItem.innerHTML = `
          <div class="table-physical-name">${tableName}</div>
          <div class="table-logical-name">Logical Name ${tableName}</div>
        `;
        tablesList.appendChild(tableItem);
      });
    }
  }

  /**
   * Display history modal
   */
  displayHistoryModal(history) {
    const modal = this.createModal('Query History', this.createHistoryContent(history));
    document.body.appendChild(modal);
  }

  /**
   * Create history content
   */
  createHistoryContent(history) {
    const container = document.createElement('div');

    if (history.length === 0) {
      container.innerHTML = '<p class="text-muted">No query history available.</p>';
      return container;
    }

    const historyList = document.createElement('div');
    historyList.className = 'history-list';

    history.forEach((item) => {
      const historyItem = document.createElement('div');
      historyItem.className = 'history-item';
      historyItem.addEventListener('click', () => {
        this.selectHistoryItem(item.sql);
        this.closeModal();
      });

      const timeDiv = document.createElement('div');
      timeDiv.className = 'history-time';
      timeDiv.textContent = item.formatted_time;

      const sqlDiv = document.createElement('div');
      sqlDiv.className = 'history-sql';
      sqlDiv.textContent = item.sql.length > 100 ? item.sql.substring(0, 100) + '...' : item.sql;

      historyItem.appendChild(timeDiv);
      historyItem.appendChild(sqlDiv);
      historyList.appendChild(historyItem);
    });

    container.appendChild(historyList);
    return container;
  }

  /**
   * Create confirmation modal
   */
  createConfirmationModal(title, content, onConfirm, onCancel) {
    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay';
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) {
        onCancel();
        this.removeModal(overlay);
      }
    });

    const modal = document.createElement('div');
    modal.className = 'modal-content';
    modal.style.maxWidth = '600px';

    const header = document.createElement('div');
    header.className = 'modal-header';
    header.innerHTML = `
      <h5>${this.escapeHtml(title)}</h5>
      <button type="button" class="btn-close" onclick="event.stopPropagation()">×</button>
    `;
    
    header.querySelector('.btn-close').addEventListener('click', () => {
      onCancel();
      this.removeModal(overlay);
    });

    const body = document.createElement('div');
    body.className = 'modal-body';
    body.appendChild(content);

    const footer = document.createElement('div');
    footer.className = 'modal-footer';
    
    const cancelBtn = document.createElement('button');
    cancelBtn.className = 'btn btn-secondary';
    cancelBtn.textContent = 'キャンセル';
    cancelBtn.addEventListener('click', () => {
      onCancel();
      this.removeModal(overlay);
    });
    
    const confirmBtn = document.createElement('button');
    confirmBtn.className = 'btn btn-danger';
    confirmBtn.textContent = '実行する';
    confirmBtn.addEventListener('click', () => {
      onConfirm();
      this.removeModal(overlay);
    });
    
    footer.appendChild(cancelBtn);
    footer.appendChild(confirmBtn);

    modal.appendChild(header);
    modal.appendChild(body);
    modal.appendChild(footer);
    overlay.appendChild(modal);

    return overlay;
  }

  /**
   * Remove modal from DOM
   */
  removeModal(modal) {
    if (modal && modal.parentElement) {
      modal.remove();
    }
  }

  /**
   * Close modal
   */
  closeModal() {
    const modal = document.querySelector('.modal-overlay');
    if (modal) {
      modal.remove();
    }
  }

  /**
   * Select history item
   */
  selectHistoryItem(sql) {
    this.editor.setValue(sql);
  }

  /**
   * Create modal
   */
  createModal(title, content) {
    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay';
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) {
        this.closeModal();
      }
    });

    const modal = document.createElement('div');
    modal.className = 'modal-content';

    const header = document.createElement('div');
    header.className = 'modal-header';
    header.innerHTML = `
      <h5>${this.escapeHtml(title)}</h5>
      <button type="button" class="btn-close" onclick="app.closeModal()">×</button>
    `;

    const body = document.createElement('div');
    body.className = 'modal-body';
    body.appendChild(content);

    const footer = document.createElement('div');
    footer.className = 'modal-footer';
    footer.innerHTML = '<button type="button" class="btn btn-secondary" onclick="app.closeModal()">Close</button>';

    modal.appendChild(header);
    modal.appendChild(body);
    modal.appendChild(footer);
    overlay.appendChild(modal);

    return overlay;
  }

  /**
   * Show execution warning dialog
   */
  showExecutionWarning(sql) {
    return new Promise((resolve) => {
      const sqlType = this.detectSqlType(sql);
      const isDangerous = ['UPDATE', 'DELETE', 'DROP', 'TRUNCATE', 'ALTER', 'CREATE', 'INSERT'].includes(sqlType);
      
      const warningContent = this.createExecutionWarningContent(sql, sqlType, isDangerous);
      const modal = this.createConfirmationModal(
        'SQL実行確認',
        warningContent,
        () => resolve(true),
        () => resolve(false)
      );
      
      document.body.appendChild(modal);
    });
  }

  /**
   * Detect SQL type
   */
  detectSqlType(sql) {
    const cleanSql = sql.trim().toUpperCase();
    
    if (cleanSql.startsWith('SELECT')) return 'SELECT';
    if (cleanSql.startsWith('INSERT')) return 'INSERT';
    if (cleanSql.startsWith('UPDATE')) return 'UPDATE';
    if (cleanSql.startsWith('DELETE')) return 'DELETE';
    if (cleanSql.startsWith('DROP')) return 'DROP';
    if (cleanSql.startsWith('TRUNCATE')) return 'TRUNCATE';
    if (cleanSql.startsWith('ALTER')) return 'ALTER';
    if (cleanSql.startsWith('CREATE')) return 'CREATE';
    if (cleanSql.startsWith('SHOW')) return 'SHOW';
    if (cleanSql.startsWith('DESCRIBE') || cleanSql.startsWith('DESC')) return 'DESCRIBE';
    if (cleanSql.startsWith('EXPLAIN')) return 'EXPLAIN';
    
    return 'UNKNOWN';
  }

  /**
   * Create execution warning content
   */
  createExecutionWarningContent(sql, sqlType, isDangerous) {
    const container = document.createElement('div');
    
    // メイン警告メッセージ
    const mainWarning = document.createElement('div');
    mainWarning.className = `alert ${isDangerous ? 'alert-danger' : 'alert-warning'} mb-3`;
    mainWarning.innerHTML = `
      <h6><i class="bi ${isDangerous ? 'bi-exclamation-triangle-fill' : 'bi-info-circle-fill'}"></i> 
      ${isDangerous ? '危険なSSQL実行確認' : 'SQL実行確認'}</h6>
      <p class="mb-0">
        ${isDangerous 
          ? 'このSQLはデータを変更・削除する可能性があります。' 
          : 'このSQLを全てのデータベースに実行します。'
        }
      </p>
    `;
    container.appendChild(mainWarning);

    // SQLタイプ表示
    const sqlTypeDiv = document.createElement('div');
    sqlTypeDiv.className = 'mb-3';
    sqlTypeDiv.innerHTML = `<strong>SQLタイプ:</strong> <span class="badge bg-${isDangerous ? 'danger' : 'primary'}">${sqlType}</span>`;
    container.appendChild(sqlTypeDiv);

    // SQLプレビュー
    const sqlPreview = document.createElement('div');
    sqlPreview.className = 'mb-3';
    sqlPreview.innerHTML = `
      <strong>SQL:</strong>
      <pre class="bg-light p-2 border rounded" style="max-height: 150px; overflow-y: auto; font-size: 0.875rem;">${this.escapeHtml(sql)}</pre>
    `;
    container.appendChild(sqlPreview);

    // 実行対象DB情報
    const dbInfo = document.createElement('div');
    dbInfo.className = 'alert alert-info';
    dbInfo.innerHTML = `
      <h6><i class="bi bi-database"></i> 実行対象データベース</h6>
      <p class="mb-0">現在のクラスター内の<strong>全てのシャード</strong>に実行されます。</p>
    `;
    container.appendChild(dbInfo);

    if (isDangerous) {
      // 危険メッセージ
      const dangerWarning = document.createElement('div');
      dangerWarning.className = 'alert alert-danger border-danger';
      dangerWarning.innerHTML = `
        <h6><i class="bi bi-shield-exclamation"></i> 重要な警告</h6>
        <ul class="mb-0">
          <li>この操作は<strong>元に戻せません</strong></li>
          <li>本番環境での実行は特に注意が必要です</li>
          <li>必要に応じてバックアップを取ってください</li>
        </ul>
      `;
      container.appendChild(dangerWarning);
    }

    return container;
  }

  /**
   * Export results to CSV
   */
  exportResultsCsv() {
    if (!this.currentResults || Object.keys(this.currentResults).length === 0) {
      this.showAlert('No results to export', 'warning');
      return;
    }

    try {
      const queryGroups = this.groupResultsByQuery(this.currentResults);

      queryGroups.forEach((queryGroup) => {
        if (!queryGroup.hasErrors) {
          const combinedData = [];

          queryGroup.shards.forEach(shard => {
            if (shard.result.success && shard.result.data) {
              combinedData.push(...shard.result.data);
            }
          });

          if (combinedData.length > 0) {
            const csv = this.convertToCSV(combinedData);
            const filename = `multi-db-query-${queryGroup.queryIndex}-${new Date().toISOString().slice(0, 19).replace(/:/g, '-')}.csv`;
            this.downloadCSV(csv, filename);
          }
        }
      });

      this.showAlert('Results exported to CSV successfully', 'success');
    } catch (error) {
      console.error('CSV export error:', error);
      this.showAlert('CSV export failed: ' + error.message, 'danger');
    }
  }

  /**
   * Convert data to CSV format
   */
  convertToCSV(data) {
    if (data.length === 0) return '';

    const headers = Object.keys(data[0]);
    const csvContent = [
      headers.join(','),
      ...data.map(row =>
        headers.map(header => {
          const value = row[header] || '';
          const escaped = String(value).replace(/"/g, '""');
          return escaped.includes(',') || escaped.includes('"') || escaped.includes('\n')
            ? `"${escaped}"`
            : escaped;
        }).join(',')
      )
    ].join('\n');

    return csvContent;
  }

  /**
   * Download CSV file
   */
  downloadCSV(csvContent, filename) {
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    if (link.download !== undefined) {
      const url = URL.createObjectURL(blob);
      link.setAttribute('href', url);
      link.setAttribute('download', filename);
      link.style.visibility = 'hidden';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    }
  }

  /**
   * Export results to XLSX
   */
  exportResults() {
    if (!this.currentResults || Object.keys(this.currentResults).length === 0) {
      this.showAlert('No results to export', 'warning');
      return;
    }

    try {
      const workbook = XLSX.utils.book_new();
      const queryGroups = this.groupResultsByQuery(this.currentResults);

      queryGroups.forEach(queryGroup => {
        if (!queryGroup.hasErrors) {
          const combinedData = [];

          queryGroup.shards.forEach(shard => {
            if (shard.result.success && shard.result.data) {
              combinedData.push(...shard.result.data);
            }
          });

          if (combinedData.length > 0) {
            const worksheet = XLSX.utils.json_to_sheet(combinedData);
            const sheetName = `Query ${queryGroup.queryIndex}`;
            XLSX.utils.book_append_sheet(workbook, worksheet, sheetName);
          }
        }
      });

      const filename = `multi-db-results-${new Date().toISOString().slice(0, 19).replace(/:/g, '-')}.xlsx`;
      XLSX.writeFile(workbook, filename);

      this.showAlert('Results exported successfully', 'success');
    } catch (error) {
      console.error('Export error:', error);
      this.showAlert('Export failed: ' + error.message, 'danger');
    }
  }

  /**
   * Set executing state
   */
  setExecuting(isExecuting) {
    this.isExecuting = isExecuting;
    const executeBtn = document.getElementById('btn-execute');

    if (executeBtn) {
      if (isExecuting) {
        executeBtn.disabled = true;
        executeBtn.innerHTML = '<span class="loading"></span> Executing...';
      } else {
        executeBtn.disabled = false;
        executeBtn.innerHTML = '<i class="bi bi-play-fill"></i> Run (Ctrl+Enter)';
      }
    }
  }

  /**
   * Show alert message
   */
  showAlert(message, type = 'info') {
    const alertContainer = document.getElementById('alert-container');
    if (!alertContainer) {
      return;
    }

    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show`;
    alert.innerHTML = `
      ${this.escapeHtml(message)}
      <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
    `;

    alertContainer.appendChild(alert);

    setTimeout(() => {
      if (alert.parentElement) {
        alert.remove();
      }
    }, 5000);
  }

  /**
   * API call helper
   */
  async apiCall(action, data = {}) {
    const formData = new FormData();
    formData.append('action', action);

    Object.entries(data).forEach(([key, value]) => {
      formData.append(key, value);
    });

    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }

    return await response.json();
  }

  /**
   * Escape HTML
   */
  escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }
}

document.addEventListener('DOMContentLoaded', () => {
  window.app = new MultiDbSqlTool();
});
