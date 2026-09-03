(function () {
  'use strict';

  const state = {
    connections: [],
    activeConnectionId: null,
    activeDatabase: '',
    editingConnectionId: null,
  };

  const PAGE_SIZE = 200;
  const resultState = {
    sql: null,
    database: null,
    connectionId: null,
    columns: [],
    tbody: null,
    rowsRendered: 0,
    offset: 0,
    hasMore: false,
    loading: false,
  };

  // ---------- helpers ----------

  async function api(url, options = {}) {
    const res = await fetch(url, {
      headers: { 'Content-Type': 'application/json' },
      ...options,
    });
    let data = null;
    try {
      data = await res.json();
    } catch (e) {
      /* no body */
    }
    if (!res.ok) {
      const message = (data && data.error) || `Request failed (${res.status})`;
      throw new Error(message);
    }
    return data;
  }

  function el(tag, attrs = {}, children = []) {
    const node = document.createElement(tag);
    for (const [k, v] of Object.entries(attrs)) {
      if (k === 'class') node.className = v;
      else if (k === 'text') node.textContent = v;
      else if (k.startsWith('on') && typeof v === 'function') node.addEventListener(k.slice(2), v);
      else node.setAttribute(k, v);
    }
    for (const child of [].concat(children)) {
      if (child) node.appendChild(child);
    }
    return node;
  }

  function logMessage(text, level = 'info') {
    const log = document.getElementById('messages-log');
    const line = el('div', {
      class: level === 'error' ? 'text-danger' : level === 'success' ? 'text-success' : 'text-body',
    });
    const time = new Date().toLocaleTimeString();
    line.textContent = `[${time}] ${text}`;
    log.appendChild(line);
    log.scrollTop = log.scrollHeight;
  }

  function setStatus(left, right = '') {
    document.getElementById('status-left').textContent = left;
    document.getElementById('status-right').textContent = right;
  }

  // ---------- CodeMirror ----------

  const editor = CodeMirror.fromTextArea(document.getElementById('sql-editor'), {
    mode: 'text/x-mysql',
    theme: 'eclipse',
    lineNumbers: true,
    indentWithTabs: true,
    smartIndent: true,
    matchBrackets: true,
    autofocus: true,
    extraKeys: {
      'Ctrl-Enter': runQuery,
      'Cmd-Enter': runQuery,
      'Ctrl-Alt-H': highlightCurrentStatement,
      'Cmd-Alt-H': highlightCurrentStatement,
    },
  });

  document.getElementById('btn-clear-editor').addEventListener('click', () => {
    editor.setValue('');
    editor.focus();
  });

  // ---------- highlight (select) statement at cursor ----------
  // Selects the SQL statement the cursor is currently inside of, bounded by the
  // nearest semicolons (or the start/end of the document) — same effect as dragging
  // the mouse over it. runQuery() already runs the selection when one is present,
  // so this lets a specific statement be run without manual drag-selecting.

  function highlightCurrentStatement() {
    const text = editor.getValue();
    const cursorIndex = editor.indexFromPos(editor.getCursor());

    const prevSemi = text.lastIndexOf(';', cursorIndex - 1);
    const nextSemi = text.indexOf(';', cursorIndex);

    let start = prevSemi === -1 ? 0 : prevSemi + 1;
    let end = nextSemi === -1 ? text.length : nextSemi;

    while (start < end && /\s/.test(text[start])) start++;
    while (end > start && /\s/.test(text[end - 1])) end--;

    if (start >= end) {
      editor.focus();
      return;
    }

    editor.setSelection(editor.posFromIndex(start), editor.posFromIndex(end));
    editor.focus();
  }

  document.getElementById('btn-highlight-line').addEventListener('click', highlightCurrentStatement);

  // ---------- results tabs ----------

  document.querySelectorAll('#results-tabs .nav-link').forEach((btn) => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('#results-tabs .nav-link').forEach((b) => b.classList.remove('active'));
      btn.classList.add('active');
      const tab = btn.dataset.tab;
      document.getElementById('tab-grid').classList.toggle('d-none', tab !== 'grid');
      document.getElementById('tab-messages').classList.toggle('d-none', tab !== 'messages');
    });
  });

  function showTab(tab) {
    document.querySelector(`#results-tabs [data-tab="${tab}"]`).click();
  }

  // ---------- mobile sidebar drawer ----------

  const sidebarEl = document.querySelector('.sidebar');
  const sidebarBackdrop = document.getElementById('sidebar-backdrop');

  function openSidebar() {
    sidebarEl.classList.add('open');
    sidebarBackdrop.classList.add('show');
  }

  function closeSidebar() {
    sidebarEl.classList.remove('open');
    sidebarBackdrop.classList.remove('show');
  }

  document.getElementById('btn-toggle-sidebar').addEventListener('click', () => {
    sidebarEl.classList.contains('open') ? closeSidebar() : openSidebar();
  });
  document.getElementById('btn-close-sidebar').addEventListener('click', closeSidebar);
  sidebarBackdrop.addEventListener('click', closeSidebar);

  // ---------- connections list ----------

  async function loadConnections() {
    state.connections = await api('api/connections.php');
    renderConnectionList();
    renderConnectionSelect();
  }

  function renderConnectionList() {
    const list = document.getElementById('connection-list');
    list.innerHTML = '';

    if (state.connections.length === 0) {
      list.appendChild(
        el('li', { class: 'list-group-item text-muted small', text: 'No connections yet. Click + to add one.' })
      );
      return;
    }

    state.connections.forEach((conn) => {
      const item = el('li', {
        class: 'list-group-item' + (conn.id === state.activeConnectionId ? ' active' : ''),
        onclick: () => activateConnection(conn.id),
      });
      item.appendChild(el('i', { class: 'bi bi-hdd-network' }));
      item.appendChild(el('span', { text: conn.name }));
      const editBtn = el('i', { class: 'bi bi-pencil-square conn-edit-btn' });
      editBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        openConnectionModal(conn);
      });
      item.appendChild(editBtn);
      list.appendChild(item);
    });
  }

  function renderConnectionSelect() {
    const select = document.getElementById('active-connection-select');
    select.innerHTML = '';
    if (state.connections.length === 0) {
      select.appendChild(el('option', { value: '', text: 'No connection selected' }));
      select.disabled = true;
      return;
    }
    select.disabled = false;
    state.connections.forEach((conn) => {
      const opt = el('option', { value: conn.id, text: `${conn.name} (${conn.host})` });
      if (conn.id === state.activeConnectionId) opt.selected = true;
      select.appendChild(opt);
    });
  }

  document.getElementById('active-connection-select').addEventListener('change', (e) => {
    const id = parseInt(e.target.value, 10);
    if (id) activateConnection(id);
  });

  async function activateConnection(id) {
    state.activeConnectionId = id;
    state.activeDatabase = '';
    renderConnectionList();
    renderConnectionSelect();

    document.getElementById('btn-run').disabled = false;
    document.getElementById('btn-refresh-schema').disabled = false;

    const conn = state.connections.find((c) => c.id === id);
    setStatus(`Connected: ${conn ? conn.name : ''}`);
    document.getElementById('connection-status').textContent = 'Connected';
    document.getElementById('connection-status').className = 'badge text-bg-success';

    if (conn && conn.database_name) {
      state.activeDatabase = conn.database_name;
    }

    await loadDatabaseSelect();
    await loadSchemaTree();
    logMessage(`Connected to "${conn ? conn.name : id}".`, 'success');

    if (window.innerWidth < 768) {
      closeSidebar();
    }
  }

  async function loadDatabaseSelect() {
    const select = document.getElementById('active-database-select');
    select.innerHTML = '';
    select.disabled = true;

    try {
      const databases = await api(`api/schema.php?id=${state.activeConnectionId}`);
      select.appendChild(el('option', { value: '', text: '(default database)' }));
      databases.forEach((db) => {
        const opt = el('option', { value: db, text: db });
        if (db === state.activeDatabase) opt.selected = true;
        select.appendChild(opt);
      });
      select.disabled = false;
    } catch (err) {
      logMessage(`Failed to load databases: ${err.message}`, 'error');
    }
  }

  document.getElementById('active-database-select').addEventListener('change', (e) => {
    state.activeDatabase = e.target.value;
  });

  document.getElementById('btn-refresh-schema').addEventListener('click', loadSchemaTree);

  // ---------- schema tree ----------

  async function loadSchemaTree() {
    const container = document.getElementById('schema-tree');
    container.innerHTML = '';

    if (!state.activeConnectionId) {
      container.appendChild(el('div', { class: 'text-muted small px-2 py-3', text: 'Connect to a database to browse its schema.' }));
      return;
    }

    try {
      const databases = await api(`api/schema.php?id=${state.activeConnectionId}`);
      databases.forEach((db) => container.appendChild(buildDatabaseNode(db)));
    } catch (err) {
      container.appendChild(el('div', { class: 'text-danger small px-2 py-3', text: err.message }));
    }
  }

  function buildDatabaseNode(dbName) {
    const wrapper = el('div', { class: 'tree-node' });
    const row = el('div', { class: 'tree-row' });
    const caret = el('i', { class: 'bi bi-caret-right-fill tree-caret' });
    row.appendChild(caret);
    row.appendChild(el('i', { class: 'bi bi-database' }));
    row.appendChild(el('span', { text: dbName }));

    const childrenBox = el('div', { class: 'tree-children d-none' });
    let loaded = false;

    row.addEventListener('click', async () => {
      const isOpen = caret.classList.toggle('open');
      childrenBox.classList.toggle('d-none', !isOpen);
      caret.className = 'bi tree-caret' + (isOpen ? ' bi-caret-down-fill open' : ' bi-caret-right-fill');

      // double click behavior: single click also selects the db as active
      state.activeDatabase = dbName;
      document.getElementById('active-database-select').value = dbName;

      if (isOpen && !loaded) {
        loaded = true;
        childrenBox.appendChild(el('div', { class: 'text-muted small px-2', text: 'Loading...' }));
        try {
          const tables = await api(`api/schema.php?id=${state.activeConnectionId}&database=${encodeURIComponent(dbName)}`);
          childrenBox.innerHTML = '';
          if (tables.length === 0) {
            childrenBox.appendChild(el('div', { class: 'text-muted small px-2', text: '(empty)' }));
          }
          tables.forEach((t) => childrenBox.appendChild(buildTableNode(dbName, t.name, t.type)));
        } catch (err) {
          childrenBox.innerHTML = '';
          childrenBox.appendChild(el('div', { class: 'text-danger small px-2', text: err.message }));
        }
      }
    });

    wrapper.appendChild(row);
    wrapper.appendChild(childrenBox);
    return wrapper;
  }

  function buildTableNode(dbName, tableName, tableType) {
    const wrapper = el('div', { class: 'tree-node' });
    const row = el('div', { class: 'tree-row' });
    const caret = el('i', { class: 'bi bi-caret-right-fill tree-caret' });
    row.appendChild(caret);
    row.appendChild(el('i', { class: tableType === 'VIEW' ? 'bi bi-eye' : 'bi bi-table' }));
    row.appendChild(el('span', { text: tableName }));

    const childrenBox = el('div', { class: 'tree-children d-none' });
    let loaded = false;

    row.addEventListener('click', async (e) => {
      e.stopPropagation();
      const isOpen = caret.classList.toggle('open');
      childrenBox.classList.toggle('d-none', !isOpen);
      caret.className = 'bi tree-caret' + (isOpen ? ' bi-caret-down-fill open' : ' bi-caret-right-fill');

      if (isOpen && !loaded) {
        loaded = true;
        childrenBox.appendChild(el('div', { class: 'text-muted small px-2', text: 'Loading...' }));
        try {
          const columns = await api(
            `api/schema.php?id=${state.activeConnectionId}&database=${encodeURIComponent(dbName)}&table=${encodeURIComponent(tableName)}`
          );
          childrenBox.innerHTML = '';
          columns.forEach((col) => childrenBox.appendChild(buildColumnNode(col)));
        } catch (err) {
          childrenBox.innerHTML = '';
          childrenBox.appendChild(el('div', { class: 'text-danger small px-2', text: err.message }));
        }
      }
    });

    row.addEventListener('dblclick', (e) => {
      e.stopPropagation();
      editor.replaceSelection(`${dbName}.${tableName}`);
    });

    wrapper.appendChild(row);
    wrapper.appendChild(childrenBox);
    return wrapper;
  }

  function buildColumnNode(col) {
    const row = el('div', { class: 'tree-row', style: 'cursor:default' });
    row.appendChild(el('span', { class: 'tree-caret' }));
    row.appendChild(el('i', { class: 'bi bi-list-columns-reverse' }));
    row.appendChild(el('span', { text: col.name }));
    row.appendChild(el('span', { class: 'text-muted', text: ' ' + col.type }));
    if (col.key === 'PRI') row.appendChild(el('span', { class: 'tree-col-key', text: 'PK' }));
    return row;
  }

  // ---------- run query ----------

  async function runQuery() {
    if (!state.activeConnectionId) {
      logMessage('No active connection.', 'error');
      return;
    }

    const selection = editor.getSelection();
    const sql = (selection && selection.trim() !== '' ? selection : editor.getValue()).trim();

    if (sql === '') {
      logMessage('Nothing to run.', 'error');
      return;
    }

    setStatus('Running query...');
    document.getElementById('btn-run').disabled = true;

    resultState.sql = null;
    resultState.hasMore = false;

    try {
      const result = await api('api/query.php', {
        method: 'POST',
        body: JSON.stringify({ id: state.activeConnectionId, database: state.activeDatabase, sql, offset: 0, limit: PAGE_SIZE }),
      });

      if (result.type === 'select') {
        renderResultGrid(result.columns, result.rows, { reset: true });

        resultState.sql = sql;
        resultState.database = state.activeDatabase;
        resultState.connectionId = state.activeConnectionId;
        resultState.offset = result.rows.length;
        resultState.hasMore = !!result.has_more;

        setStatus(`${result.rows.length}${result.has_more ? '+' : ''} row(s) fetched`, `${result.time_ms} ms`);
        logMessage(`OK, ${result.rows.length}${result.has_more ? '+' : ''} row(s) fetched in ${result.time_ms} ms.`, 'success');
        showTab('grid');
        maybeAutoFillGrid();
      } else if (result.type === 'exec') {
        renderMessageOnlyResult(`${result.affected_rows} row(s) affected.`);
        setStatus(`${result.affected_rows} row(s) affected.`, `${result.time_ms} ms`);
        logMessage(`OK, ${result.affected_rows} row(s) affected in ${result.time_ms} ms.`, 'success');
      } else {
        renderMessageOnlyResult(result.message, true);
        setStatus('Error', `${result.time_ms} ms`);
        logMessage(result.message, 'error');
        showTab('messages');
      }
    } catch (err) {
      renderMessageOnlyResult(err.message, true);
      setStatus('Error');
      logMessage(err.message, 'error');
      showTab('messages');
    } finally {
      document.getElementById('btn-run').disabled = false;
    }
  }

  document.getElementById('btn-run').addEventListener('click', runQuery);

  // ---------- result grid pagination (infinite scroll) ----------

  async function loadMoreRows() {
    if (resultState.loading || !resultState.hasMore || !resultState.sql) return;
    resultState.loading = true;

    try {
      const result = await api('api/query.php', {
        method: 'POST',
        body: JSON.stringify({
          id: resultState.connectionId,
          database: resultState.database,
          sql: resultState.sql,
          offset: resultState.offset,
          limit: PAGE_SIZE,
        }),
      });

      if (result.type === 'select') {
        renderResultGrid(result.columns, result.rows, { reset: false });
        resultState.offset += result.rows.length;
        resultState.hasMore = !!result.has_more;
        setStatus(`${resultState.offset}${resultState.hasMore ? '+' : ''} row(s) fetched`, `${result.time_ms} ms`);
        maybeAutoFillGrid();
      } else {
        resultState.hasMore = false;
        if (result.type === 'error') {
          logMessage(`Failed to fetch more rows: ${result.message}`, 'error');
        }
      }
    } catch (err) {
      logMessage(`Failed to fetch more rows: ${err.message}`, 'error');
    } finally {
      resultState.loading = false;
    }
  }

  function onResultGridScroll() {
    const wrapper = document.getElementById('result-grid-wrapper');
    if (resultState.loading || !resultState.hasMore) return;
    const threshold = 150;
    if (wrapper.scrollTop + wrapper.clientHeight >= wrapper.scrollHeight - threshold) {
      loadMoreRows();
    }
  }

  document.getElementById('result-grid-wrapper').addEventListener('scroll', onResultGridScroll);

  // If the page of rows doesn't fill (or barely fills) the visible area, there's no
  // scrollbar to trigger onResultGridScroll, so keep fetching until it's scrollable.
  function maybeAutoFillGrid() {
    const wrapper = document.getElementById('result-grid-wrapper');
    if (resultState.hasMore && !resultState.loading && wrapper.scrollHeight <= wrapper.clientHeight + 5) {
      loadMoreRows();
    }
  }

  function renderResultGrid(columns, rows, opts = {}) {
    const wrapper = document.getElementById('result-grid-wrapper');
    const reset = opts.reset !== false;

    if (reset) {
      wrapper.innerHTML = '';
      resultState.rowsRendered = 0;
      resultState.tbody = null;
      resultState.columns = columns;

      if (rows.length === 0) {
        wrapper.appendChild(el('div', { class: 'text-muted small p-3', text: 'Query returned no rows.' }));
        return;
      }

      const table = el('table', { class: 'table table-sm table-striped table-hover table-bordered mb-0' });
      const thead = el('thead');
      const headRow = el('tr');
      headRow.appendChild(el('th', { text: '#', style: 'width:40px' }));
      columns.forEach((c) => headRow.appendChild(el('th', { text: c })));
      thead.appendChild(headRow);
      table.appendChild(thead);

      const tbody = el('tbody');
      table.appendChild(tbody);
      wrapper.appendChild(table);
      resultState.tbody = tbody;
    }

    const tbody = resultState.tbody;
    if (!tbody) return;

    rows.forEach((row) => {
      resultState.rowsRendered++;
      const tr = el('tr');
      tr.appendChild(el('td', { class: 'text-muted', text: String(resultState.rowsRendered) }));
      resultState.columns.forEach((c) => {
        const value = row[c];
        if (value === null) {
          tr.appendChild(el('td', { class: 'cell-null', text: 'NULL' }));
        } else {
          tr.appendChild(el('td', { text: String(value), title: String(value) }));
        }
      });
      tbody.appendChild(tr);
    });
  }

  function renderMessageOnlyResult(message, isError = false) {
    const wrapper = document.getElementById('result-grid-wrapper');
    wrapper.innerHTML = '';
    wrapper.appendChild(
      el('div', { class: `alert ${isError ? 'alert-danger' : 'alert-success'} m-2 mb-0 small`, text: message })
    );
  }

  // ---------- connection modal ----------

  const modalEl = document.getElementById('connection-modal');
  const modal = new bootstrap.Modal(modalEl);

  document.getElementById('btn-new-connection').addEventListener('click', () => openConnectionModal(null));

  function openConnectionModal(conn) {
    state.editingConnectionId = conn ? conn.id : null;
    document.getElementById('connection-modal-title').textContent = conn ? 'Edit Connection' : 'New Connection';
    document.getElementById('conn-id').value = conn ? conn.id : '';
    document.getElementById('conn-name').value = conn ? conn.name : '';
    document.getElementById('conn-host').value = conn ? conn.host : '';
    document.getElementById('conn-port').value = conn ? conn.port : 3306;
    document.getElementById('conn-username').value = conn ? conn.username : '';
    document.getElementById('conn-password').value = '';
    document.getElementById('conn-database').value = conn && conn.database_name ? conn.database_name : '';
    document.getElementById('test-connection-result').innerHTML = '';
    document.getElementById('btn-delete-connection').classList.toggle('d-none', !conn);
    modal.show();
  }

  function readConnectionForm() {
    return {
      name: document.getElementById('conn-name').value.trim(),
      host: document.getElementById('conn-host').value.trim(),
      port: parseInt(document.getElementById('conn-port').value, 10) || 3306,
      username: document.getElementById('conn-username').value.trim(),
      password: document.getElementById('conn-password').value,
      database_name: document.getElementById('conn-database').value.trim(),
    };
  }

  document.getElementById('btn-test-connection').addEventListener('click', async () => {
    const resultBox = document.getElementById('test-connection-result');
    resultBox.innerHTML = '<span class="text-secondary">Testing...</span>';
    try {
      const payload = readConnectionForm();
      if (state.editingConnectionId) payload.id = state.editingConnectionId;
      const result = await api('api/test_connection.php', { method: 'POST', body: JSON.stringify(payload) });
      resultBox.innerHTML = result.ok
        ? `<span class="text-success"><i class="bi bi-check-circle"></i> ${result.message}</span>`
        : `<span class="text-danger"><i class="bi bi-x-circle"></i> ${result.message}</span>`;
    } catch (err) {
      resultBox.innerHTML = `<span class="text-danger"><i class="bi bi-x-circle"></i> ${err.message}</span>`;
    }
  });

  document.getElementById('connection-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const payload = readConnectionForm();

    if (!payload.name || !payload.host || !payload.username) {
      document.getElementById('test-connection-result').innerHTML =
        '<span class="text-danger">Name, host, and username are required.</span>';
      return;
    }

    try {
      if (state.editingConnectionId) {
        await api(`api/connections.php?id=${state.editingConnectionId}`, { method: 'PUT', body: JSON.stringify(payload) });
        logMessage(`Updated connection "${payload.name}".`, 'success');
      } else {
        await api('api/connections.php', { method: 'POST', body: JSON.stringify(payload) });
        logMessage(`Created connection "${payload.name}".`, 'success');
      }
      modal.hide();
      await loadConnections();
    } catch (err) {
      document.getElementById('test-connection-result').innerHTML = `<span class="text-danger">${err.message}</span>`;
    }
  });

  document.getElementById('btn-delete-connection').addEventListener('click', async () => {
    if (!state.editingConnectionId) return;
    if (!confirm('Delete this connection? This cannot be undone.')) return;
    try {
      await api(`api/connections.php?id=${state.editingConnectionId}`, { method: 'DELETE' });
      logMessage('Connection deleted.', 'success');
      if (state.activeConnectionId === state.editingConnectionId) {
        state.activeConnectionId = null;
        document.getElementById('btn-run').disabled = true;
        document.getElementById('btn-refresh-schema').disabled = true;
        document.getElementById('connection-status').textContent = 'Not connected';
        document.getElementById('connection-status').className = 'badge text-bg-secondary';
        await loadSchemaTree();
      }
      modal.hide();
      await loadConnections();
    } catch (err) {
      alert(err.message);
    }
  });

  // ---------- logout ----------

  document.getElementById('btn-logout').addEventListener('click', async () => {
    try {
      await api('api/logout.php', { method: 'POST' });
    } finally {
      window.location.href = 'login.php';
    }
  });

  // ---------- lab exam monitoring ----------
  // Only active when config/config.php sets LAB_EXAM_MODE = true (see index.php,
  // which mirrors it into window.APP_CONFIG.labExamMode). Reports tab-switch /
  // app-switch events and network connection type to api/lab_exam_event.php,
  // which itself re-checks the flag server-side and no-ops otherwise.

  function initLabExamMonitoring() {
    if (!window.APP_CONFIG || !window.APP_CONFIG.labExamMode) return;

    function sendEvent(eventType, detail) {
      const payload = JSON.stringify({ event_type: eventType, detail: detail || null });
      if (navigator.sendBeacon) {
        navigator.sendBeacon('api/lab_exam_event.php', new Blob([payload], { type: 'application/json' }));
      } else {
        fetch('api/lab_exam_event.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: payload,
          keepalive: true,
        }).catch(() => {});
      }
    }

    // Fires for alt-tab, minimizing, switching desktop virtual desktops, and
    // backgrounding the mobile browser app — the one signal both platforms
    // raise consistently. window blur/focus is a secondary, less reliable signal.
    document.addEventListener('visibilitychange', () => {
      sendEvent(document.hidden ? 'tab_hidden' : 'tab_visible');
    });

    window.addEventListener('blur', () => sendEvent('window_blur'));
    window.addEventListener('focus', () => sendEvent('window_focus'));

    // pagehide covers actual tab/window close and navigation away; it's more
    // reliable than beforeunload on mobile browsers.
    window.addEventListener('pagehide', () => sendEvent('page_hide'));

    // Network Information API: Chromium/Android report connection.type
    // ('wifi', 'cellular', ...); Safari doesn't implement this API at all,
    // so there's no reliable Wi-Fi vs cellular signal there.
    const conn = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
    if (conn) {
      const reportConnection = () => {
        sendEvent('network_info', `type=${conn.type || 'unknown'};effectiveType=${conn.effectiveType || 'unknown'}`);
      };
      reportConnection();
      conn.addEventListener('change', reportConnection);
    } else {
      sendEvent('network_info', 'type=unsupported');
    }
  }

  // ---------- init ----------

  async function init() {
    initLabExamMonitoring();
    await loadConnections();

    try {
      const session = await api('api/session.php');
      if (session.logged_in && session.connection) {
        await activateConnection(session.connection.id);
      }
    } catch (err) {
      logMessage(`Failed to load session: ${err.message}`, 'error');
    }
  }

  init().catch((err) => logMessage(`Failed to initialize: ${err.message}`, 'error'));
})();
