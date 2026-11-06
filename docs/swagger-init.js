(() => {
  'use strict';

  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
  const group = $('meta[name="docs-group"]')?.content || 'global';
  const version = $('meta[name="docs-spec-version"]')?.content || '';
  const defaultApp = $('meta[name="docs-app-default"]')?.content || group;
  const content = $('[data-docs-content]');
  const nav = $('[data-endpoint-nav]');
  const sidebar = $('[data-endpoint-sidebar]');
  const sidebarToggle = $('[data-sidebar-toggle]');
  const sidebarBackdrop = $('[data-sidebar-backdrop]');
  const search = $('[data-endpoint-search]');
  const serverSelect = $('[data-server-select]');
  const authApp = $('[data-auth-app]');
  const authToken = $('[data-auth-token]');
  const authJwt = $('[data-auth-jwt]');
  const saveHeadersButton = $('[data-save-headers]');
  const clearHeadersButton = $('[data-clear-headers]');
  const docsBase = $('meta[name="docs-base"]')?.content || './';
  const countTargets = $$('[data-endpoint-count]');
  const methods = ['get', 'post', 'put', 'patch', 'delete', 'options', 'head'];
  const state = { spec: null, operations: [], visible: [], activeId: null };

  const closeSidebar = () => {
    sidebar?.classList.remove('is-open');
    sidebarToggle?.setAttribute('aria-expanded', 'false');
    if (sidebarBackdrop) sidebarBackdrop.hidden = true;
    document.body.classList.remove('docs-sidebar-open');
  };

  const openSidebar = () => {
    sidebar?.classList.add('is-open');
    sidebarToggle?.setAttribute('aria-expanded', 'true');
    if (sidebarBackdrop) sidebarBackdrop.hidden = false;
    document.body.classList.add('docs-sidebar-open');
  };

  const escapeHtml = (value) => String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

  const methodClass = (method) => `method-${method.toLowerCase()}`;
  const operationId = (method, path) => `${method}-${path}`.replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '').toLowerCase();
  const operationLabel = (operation) => operation.summary || operation.operationId || 'Endpoint';
  const operationDescription = (operation) => operation.description || '';
  const securityNames = (operation) => [...new Set((operation.security || []).flatMap((item) => Object.keys(item || {})))];
  const headerDefinitions = { appCode: 'App', appTokenAuth: 'X-App-Token', bearerAuth: 'Authorization' };

  const maskedValue = (value) => {
    const text = String(value || '').trim();
    if (!text) return 'belum diisi';
    if (text.length < 8) return 'terisi';
    return `${text.slice(0, 4)}...${text.slice(-3)}`;
  };

  const updateHeaderPreviews = () => {
    $$('[data-header-preview]').forEach((preview) => {
      preview.querySelectorAll('[data-header-value]').forEach((valueNode) => {
        const name = valueNode.dataset.headerValue;
        const source = name === 'App' ? authApp : name === 'X-App-Token' ? authToken : authJwt;
        valueNode.textContent = maskedValue(source?.value);
      });
    });
  };

  const loadStoredHeaders = () => {
    try {
      const stored = JSON.parse(sessionStorage.getItem('bkk_api_docs_headers') || '{}');
      if (stored.app && authApp) authApp.value = stored.app;
      if (stored.token && authToken) authToken.value = stored.token;
      if (stored.jwt && authJwt) authJwt.value = stored.jwt;
    } catch (_) { /* session storage is optional */ }
  };

  const saveHeaders = () => {
    try {
      sessionStorage.setItem('bkk_api_docs_headers', JSON.stringify({ app: authApp?.value || '', token: authToken?.value || '', jwt: authJwt?.value || '' }));
      if (saveHeadersButton) {
        saveHeadersButton.textContent = 'Tersimpan di sesi';
        window.setTimeout(() => { saveHeadersButton.textContent = 'Simpan header'; }, 1400);
      }
    } catch (_) { /* request tetap berjalan jika storage diblokir */ }
  };

  const clearHeaders = () => {
    try { sessionStorage.removeItem('bkk_api_docs_headers'); } catch (_) { /* optional */ }
    if (authToken) authToken.value = '';
    if (authJwt) authJwt.value = '';
    updateHeaderPreviews();
  };

  const exampleForSchema = (schema = {}, propertyName = '') => {
    if (schema.example !== undefined) return schema.example;
    if (schema.default !== undefined) return schema.default;
    if (schema.enum?.length) return schema.enum[0];
    if (schema.type === 'integer' || schema.type === 'number') return 0;
    if (schema.type === 'boolean') return false;
    if (schema.type === 'array') return [];
    if (schema.type === 'object') return {};
    if (/password|token|secret/i.test(propertyName)) return '';
    return '';
  };

  const formatJson = (value) => {
    if (typeof value === 'string') {
      try { return JSON.stringify(JSON.parse(value), null, 2); } catch (_) { return value; }
    }
    return JSON.stringify(value, null, 2);
  };

  const copyText = async (value, button) => {
    try {
      await navigator.clipboard.writeText(value);
      const original = button.textContent;
      button.textContent = 'Tersalin';
      window.setTimeout(() => { button.textContent = original; }, 1200);
    } catch (_) {
      button.textContent = 'Gagal menyalin';
    }
  };

  const updateCount = (value) => {
    countTargets.forEach((target) => {
      target.textContent = target.classList.contains('docs-chip') ? `${value} endpoint` : `${value} endpoint`;
    });
  };

  const renderServers = () => {
    if (!serverSelect) return;
    const servers = Array.isArray(state.spec?.servers) && state.spec.servers.length
      ? state.spec.servers
      : [{ url: window.location.origin, description: 'Current origin' }];
    serverSelect.innerHTML = servers.map((server, index) => `<option value="${escapeHtml(server.url)}">${escapeHtml(server.description || server.url || `Server ${index + 1}`)}</option>`).join('');
    const currentOrigin = window.location.origin.replace(/\/$/, '');
    const currentServer = servers.find((server) => String(server.url || '').replace(/\/$/, '') === currentOrigin);
    const local = servers.find((server) => /localhost|127\.0\.0\.1/i.test(server.url || ''));
    if (currentServer) serverSelect.value = currentServer.url;
    else if (local) serverSelect.value = local.url;
  };

  const renderNav = () => {
    const groups = new Map();
    state.visible.forEach((operation) => {
      const tag = operation.operation.tags?.[0] || 'General';
      if (!groups.has(tag)) groups.set(tag, []);
      groups.get(tag).push(operation);
    });
    if (!groups.size) {
      nav.innerHTML = '<div class="empty-state">Endpoint tidak ditemukan.</div>';
      return;
    }
    nav.innerHTML = [...groups.entries()].map(([tag, operations]) => `
      <div class="endpoint-group">
        <div class="endpoint-group-title">${escapeHtml(tag)}</div>
        ${operations.map((item) => `<button class="endpoint-link ${state.activeId === item.id ? 'is-active' : ''}" type="button" data-scroll-to="${escapeHtml(item.id)}"><span class="method-mini ${methodClass(item.method)}">${escapeHtml(item.method)}</span><code>${escapeHtml(item.path)}</code></button>`).join('')}
      </div>
    `).join('');
  };

  const schemaFields = (operation) => {
    const fields = [];
    (operation.operation.parameters || []).forEach((parameter) => {
      fields.push({
        name: parameter.name,
        location: parameter.in,
        required: Boolean(parameter.required),
        description: parameter.description || '',
        schema: parameter.schema || {},
        example: parameter.example ?? exampleForSchema(parameter.schema || {}, parameter.name),
      });
    });
    const body = operation.operation.requestBody?.content?.['application/json'];
    const schema = body?.schema || {};
    Object.entries(schema.properties || {}).forEach(([name, fieldSchema]) => {
      fields.push({
        name,
        location: 'body',
        required: (schema.required || []).includes(name),
        description: fieldSchema.description || '',
        schema: fieldSchema,
        example: fieldSchema.example ?? exampleForSchema(fieldSchema, name),
      });
    });
    return fields;
  };

  const renderField = (field) => {
    const isLong = /token|secret/i.test(field.name) || String(field.example || '').length > 80;
    const inputType = /password|token|secret/i.test(field.name) ? 'password' : field.schema.type === 'number' || field.schema.type === 'integer' ? 'number' : 'text';
    const valueType = escapeHtml(field.schema.type || 'string');
    const value = typeof field.example === 'object' ? JSON.stringify(field.example) : String(field.example ?? '');
    const enumOptions = Array.isArray(field.schema.enum) ? field.schema.enum : [];
    const minLength = Number.isInteger(field.schema.minLength) ? ` minlength="${field.schema.minLength}"` : '';
    const maxLength = Number.isInteger(field.schema.maxLength) ? ` maxlength="${field.schema.maxLength}"` : '';
    const control = enumOptions.length
      ? `<select name="${escapeHtml(field.name)}" data-location="${escapeHtml(field.location)}" data-value-type="${valueType}" ${field.required ? 'required' : ''}>${enumOptions.map((option) => `<option value="${escapeHtml(option)}">${escapeHtml(option)}</option>`).join('')}</select>`
      : isLong
        ? `<textarea name="${escapeHtml(field.name)}" data-location="${escapeHtml(field.location)}" data-value-type="${valueType}" placeholder="${escapeHtml(value)}"${minLength}${maxLength} ${field.required ? 'required' : ''}></textarea>`
        : `<input type="${inputType}" name="${escapeHtml(field.name)}" data-location="${escapeHtml(field.location)}" data-value-type="${valueType}" value="${escapeHtml(value)}" placeholder="${escapeHtml(field.example ? '' : field.name)}"${minLength}${maxLength} ${field.required ? 'required' : ''}>`;
    return `<div class="field ${field.location === 'body' && isLong ? 'field-full' : ''}"><label><span>${escapeHtml(field.name)}${field.required ? ' *' : ''}<small>${escapeHtml(field.location)}</small></span>${control}${field.description ? `<small>${escapeHtml(field.description)}</small>` : ''}</label></div>`;
  };

  const renderSecurity = (operation) => {
    const names = securityNames(operation.operation);
    if (!names.length) return '<span class="security-pill">Public</span>';
    const labels = { appTokenAuth: 'X-App-Token', appCode: 'App', bearerAuth: 'Bearer JWT' };
    return names.map((name) => `<span class="security-pill">${escapeHtml(labels[name] || name)}</span>`).join('');
  };

  const renderHeaders = (operation) => {
    const names = securityNames(operation.operation);
    const headers = [...new Set(names.map((name) => headerDefinitions[name]).filter(Boolean))];
    if (!headers.length) return '';
    return `<div class="header-preview" data-header-preview><span class="header-preview-label">Header yang dikirim</span>${headers.map((headerName) => `<span class="header-chip"><code>${escapeHtml(headerName)}</code><b data-header-value="${escapeHtml(headerName)}">belum diisi</b></span>`).join('')}</div>`;
  };

  const renderResponses = (operation) => {
    const responses = operation.operation.responses || {};
    return Object.keys(responses).map((code) => `<span class="response-code">${escapeHtml(code)} ${escapeHtml(responses[code]?.description || '')}</span>`).join('');
  };

  const renderOperation = (item) => {
    const fields = schemaFields(item);
    const hasBody = fields.some((field) => field.location === 'body');
    const formFields = fields.length ? `<div class="field-grid">${fields.map(renderField).join('')}</div>` : '<p class="operation-description">Endpoint ini tidak memerlukan parameter tambahan.</p>';
    return `
      <article class="operation-card" id="${escapeHtml(item.id)}" data-operation-card="${escapeHtml(item.id)}">
        <header class="operation-header">
          <div class="operation-heading">
            <div class="operation-line"><span class="method-badge ${methodClass(item.method)}">${escapeHtml(item.method)}</span><code>${escapeHtml(item.path)}</code></div>
            <h3>${escapeHtml(operationLabel(item.operation))}</h3>
            ${operationDescription(item.operation) ? `<p class="operation-description">${escapeHtml(operationDescription(item.operation))}</p>` : ''}
          </div>
          <div class="operation-actions"><button class="button-subtle" type="button" data-copy-path="${escapeHtml(item.path)}">Copy path</button></div>
        </header>
        <div class="operation-body">
          <div class="security-row">${renderSecurity(item)}</div>
          ${renderHeaders(item)}
          <div class="request-layout">
            <form class="request-form" data-request-form data-method="${escapeHtml(item.method)}" data-path="${escapeHtml(item.path)}" data-has-body="${hasBody ? '1' : '0'}">
              ${formFields}
              <button class="run-button" type="submit">Jalankan request</button>
            </form>
            <section class="response-panel" aria-live="polite">
              <div class="response-heading"><strong>Response</strong><span class="response-status" data-response-status>Belum dijalankan</span></div>
              <pre class="response-output" data-response-output>Tekan Jalankan request untuk melihat hasil.</pre>
            </section>
          </div>
          <div class="responses-summary"><span class="section-kicker">RESPONSE CODES</span>${renderResponses(item)}</div>
        </div>
      </article>
    `;
  };

  const renderContent = () => {
    if (!state.visible.length) {
      content.innerHTML = '<div class="empty-state">Tidak ada endpoint yang cocok dengan pencarian.</div>';
      return;
    }
    content.innerHTML = state.visible.map(renderOperation).join('');
    state.activeId = state.visible[0].id;
    renderNav();
  };

  const collectRequest = (form) => {
    const query = new URLSearchParams();
    const body = {};
    const path = {};
    $$('[name][data-location]', form).forEach((input) => {
      if (!input.value) return;
      const value = ['id_peg', 'app'].includes(input.name) ? input.value.trim() : input.value;
      if (input.dataset.location === 'query') query.set(input.name, value);
      if (input.dataset.location === 'path') path[input.name] = value.trim();
      if (input.dataset.location === 'body') {
        if (input.dataset.valueType === 'number' || input.dataset.valueType === 'integer') {
          body[input.name] = Number(value);
        } else if (input.dataset.valueType === 'boolean') {
          body[input.name] = value === 'true';
        } else if (input.dataset.valueType === 'object' || input.dataset.valueType === 'array') {
          try { body[input.name] = JSON.parse(value); } catch (_) { body[input.name] = value; }
        } else {
          body[input.name] = value;
        }
      }
    });
    return { query, body, path };
  };

  const executeRequest = async (form) => {
    const method = form.dataset.method.toUpperCase();
    const path = form.dataset.path;
    const { query, body, path: pathValues } = collectRequest(form);
    let missingPathParameter = '';
    const resolvedPath = path.replace(/\{([A-Za-z][A-Za-z0-9_]*)\}/g, (placeholder, name) => {
      const value = pathValues[name];
      if (!value) {
        missingPathParameter = name;
        return placeholder;
      }
      return encodeURIComponent(value);
    });
    if (missingPathParameter) {
      const card = form.closest('.operation-card');
      const output = $('[data-response-output]', card);
      const status = $('[data-response-status]', card);
      output.textContent = `Parameter path '${missingPathParameter}' wajib diisi.`;
      status.textContent = '400 Bad Request';
      status.className = 'response-status is-error';
      return;
    }
    const base = String(serverSelect.value || window.location.origin).replace(/\/$/, '');
    const url = `${base}${resolvedPath}${query.toString() ? `?${query.toString()}` : ''}`;
    const headers = { Accept: 'application/json' };
    const app = String(authApp?.value || '').trim();
    const token = String(authToken?.value || '').trim();
    const jwt = String(authJwt?.value || '').trim();
    if (app) headers.App = app;
    if (token) headers['X-App-Token'] = token;
    if (jwt) headers.Authorization = `Bearer ${jwt}`;
    if (form.dataset.hasBody === '1') {
      headers['Content-Type'] = 'application/json';
    }
    const button = $('button[type="submit"]', form);
    const output = $('[data-response-output]', form.closest('.operation-card'));
    const status = $('[data-response-status]', form.closest('.operation-card'));
    button.disabled = true;
    button.textContent = 'Menjalankan...';
    status.textContent = 'Loading';
    status.className = 'response-status';
    try {
      const response = await fetch(url, { method, headers, body: form.dataset.hasBody === '1' ? JSON.stringify(body) : undefined, credentials: 'same-origin' });
      const text = await response.text();
      output.textContent = formatJson(text);
      status.textContent = `${response.status} ${response.statusText}`;
      status.className = `response-status ${response.ok ? 'is-success' : 'is-error'}`;
    } catch (error) {
      output.textContent = error.message || 'Request gagal dijalankan.';
      status.textContent = 'Network error';
      status.className = 'response-status is-error';
    } finally {
      button.disabled = false;
      button.textContent = 'Jalankan request';
    }
  };

  const filterOperations = (value) => {
    const query = value.trim().toLowerCase();
    state.visible = state.operations.filter((item) => !query || `${item.path} ${operationLabel(item.operation)} ${(item.operation.tags || []).join(' ')}`.toLowerCase().includes(query));
    renderContent();
    updateCount(state.visible.length);
  };

  document.addEventListener('click', (event) => {
    const scrollButton = event.target.closest('[data-scroll-to]');
    if (scrollButton) {
      const target = document.getElementById(scrollButton.dataset.scrollTo);
      if (target) {
        state.activeId = scrollButton.dataset.scrollTo;
        renderNav();
        closeSidebar();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
      return;
    }
    if (event.target.closest('[data-sidebar-toggle]')) {
      if (sidebar?.classList.contains('is-open')) closeSidebar();
      else openSidebar();
      return;
    }
    if (event.target.closest('[data-sidebar-close]') || event.target.closest('[data-sidebar-backdrop]')) {
      closeSidebar();
      return;
    }
    const copyButton = event.target.closest('[data-copy-path]');
    if (copyButton) copyText(copyButton.dataset.copyPath, copyButton);
  });

  document.addEventListener('submit', (event) => {
    if (event.target.matches('[data-request-form]')) {
      event.preventDefault();
      executeRequest(event.target);
    }
  });

  search?.addEventListener('input', () => filterOperations(search.value));
  window.addEventListener('resize', () => {
    if (window.innerWidth > 680) closeSidebar();
  });
  [authApp, authToken, authJwt].forEach((input) => input?.addEventListener('input', updateHeaderPreviews));
  saveHeadersButton?.addEventListener('click', saveHeaders);
  clearHeadersButton?.addEventListener('click', clearHeaders);

  const backLink = $('[data-back-link]');
  if (backLink && document.referrer) {
    try {
      const previousUrl = new URL(document.referrer);
      if (previousUrl.origin === window.location.origin && previousUrl.pathname.includes('/admin/')) backLink.href = previousUrl.href;
    } catch (_) { /* safe fallback remains */ }
  }

  const loadSpec = async () => {
    try {
      const response = await fetch(`${docsBase}/openapi/${encodeURIComponent(group)}`, { credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Docs-Version': version } });
      if (!response.ok) throw new Error(`Kontrak API tidak dapat dimuat (${response.status}).`);
      state.spec = await response.json();
      state.operations = Object.entries(state.spec.paths || {}).flatMap(([path, pathItem]) => methods.filter((method) => pathItem?.[method]).map((method) => ({ id: operationId(method, path), path, method: method.toUpperCase(), operation: pathItem[method] })));
      state.visible = state.operations;
      authApp.value = defaultApp;
      loadStoredHeaders();
      renderServers();
      renderContent();
      updateHeaderPreviews();
      updateCount(state.operations.length);
    } catch (error) {
      content.innerHTML = `<div class="docs-error">${escapeHtml(error.message || 'Dokumentasi gagal dimuat.')}</div>`;
      updateCount(0);
    }
  };

  loadSpec();
})();
