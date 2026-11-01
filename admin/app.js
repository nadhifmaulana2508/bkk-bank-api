(() => {
  'use strict';

  const body = document.body;
  const isAuthenticated = body.dataset.authenticated === 'true';
  const page = body.dataset.page || 'registry';
  const state = { csrf: '', applications: [], dashboard: null, editingId: null, users: [], editingUserId: null, employeeResults: [], selectedEmployee: null };
  const $ = (selector) => document.querySelector(selector);
  const actionIcons = {
    edit: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m4 16-.8 4.8L8 20l11.5-11.5a2.1 2.1 0 0 0-3-3L5 17zM14.5 7.5l2 2"/></svg>',
    rotate: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11a8 8 0 0 0-14.8-3L3 11M3 5v6h6M4 13a8 8 0 0 0 14.8 3L21 13m0 6v-6h-6"/></svg>',
  };

  const escapeHtml = (value) => String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

  const formatNumber = (value) => new Intl.NumberFormat('id-ID').format(Number(value || 0));

  const formatDate = (value, fallback = 'Belum ada hit') => {
    if (!value) return fallback;
    const date = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) return String(value);
    return new Intl.DateTimeFormat('id-ID', {
      dateStyle: 'medium',
      timeStyle: 'short',
    }).format(date);
  };

  const formatDay = (value) => {
    const date = new Date(`${value}T00:00:00`);
    if (Number.isNaN(date.getTime())) return value;
    return new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short' }).format(date);
  };

  const showMessage = (message, tone = 'error') => {
    const notice = $('#global-notice');
    if (!notice) return;
    notice.textContent = message;
    notice.dataset.tone = tone;
    notice.hidden = false;
    window.setTimeout(() => { notice.hidden = true; }, 5000);
  };

  const request = async (action, options = {}) => {
    const { query, ...fetchOptions } = options;
    const queryString = query ? `&${new URLSearchParams(query).toString()}` : '';
    const headers = {
      Accept: 'application/json',
      ...(fetchOptions.body ? { 'Content-Type': 'application/json' } : {}),
      ...(state.csrf ? { 'X-CSRF-Token': state.csrf } : {}),
      ...(fetchOptions.headers || {}),
    };
    const response = await fetch(`api.php?action=${encodeURIComponent(action)}${queryString}`, {
      credentials: 'same-origin',
      ...fetchOptions,
      headers,
    });
    const payload = await response.json().catch(() => ({ message: 'Response server tidak valid.' }));
    if (!response.ok) {
      if (response.status === 401) window.location.reload();
      throw new Error(payload.message || 'Permintaan gagal.');
    }
    return payload;
  };

  const renderMetrics = (summary) => {
    const target = $('#metrics');
    if (!target || !summary || summary.total_applications === undefined) return;
    const metrics = [
      ['Aplikasi terdaftar', summary.total_applications, 'Semua registry yang tersimpan'],
      ['Aplikasi aktif', summary.active_applications, 'Aktif dan belum kedaluwarsa'],
      ['Hit hari ini', summary.hits_today, 'Request dengan app token valid'],
    ];
    target.innerHTML = metrics.map(([label, value, note]) => `
      <article class="metric-card">
        <p>${escapeHtml(label)}</p>
        <strong>${escapeHtml(formatNumber(value))}</strong>
        <span>${escapeHtml(note)}</span>
      </article>
    `).join('');
  };

  const statusLabel = { active: 'Aktif', inactive: 'Nonaktif', expired: 'Kedaluwarsa' };

  const renderApplications = (applications) => {
    const target = $('#applications-body');
    if (!target) return;
    const canManage = body.dataset.canManage === 'true';
    if (!applications.length) {
      target.innerHTML = '<tr><td colspan="6" class="table-state">Belum ada aplikasi. Tambahkan registry pertama untuk mulai memakai SSO.</td></tr>';
      return;
    }

    target.innerHTML = applications.map((application) => `
      <tr>
        <th scope="row">
          <span class="app-name">${escapeHtml(application.app_name)}</span>
          <span class="app-code">${escapeHtml(application.app_code)}</span>
        </th>
        <td><span class="status status-${escapeHtml(application.status)}">${escapeHtml(statusLabel[application.status] || application.status)}</span></td>
        <td>${escapeHtml(formatDate(application.expires_at, 'Tidak dibatasi'))}</td>
        <td><strong>${escapeHtml(formatNumber(application.hits_7d))}</strong></td>
        <td>${escapeHtml(formatDate(application.last_used_at))}</td>
        <td class="row-actions">${canManage ? `
          <button class="table-action" data-action="edit" data-id="${application.id}" type="button" title="Edit aplikasi" aria-label="Edit aplikasi">${actionIcons.edit}</button>
          <button class="table-action" data-action="rotate" data-id="${application.id}" type="button" title="Rotasi token" aria-label="Rotasi token">${actionIcons.rotate}</button>
        ` : '<span class="field-help">Read only</span>'}</td>
      </tr>
    `).join('');
  };

  const renderRecentHits = (hits) => {
    const target = $('#recent-hits');
    if (!target) return;
    if (!hits.length) {
      target.innerHTML = '<p class="table-state">Belum ada hit tercatat. Request valid akan muncul di sini.</p>';
      return;
    }

    target.innerHTML = hits.map((hit) => `
      <article class="activity-item">
        <div class="activity-topline">
          <strong>${escapeHtml(hit.app_code)}</strong>
          <span class="status status-${hit.status_code >= 400 ? 'inactive' : 'active'}">${escapeHtml(hit.status_code)}</span>
        </div>
        <code>${escapeHtml(hit.request_method)} ${escapeHtml(hit.request_path)}</code>
        <time datetime="${escapeHtml(hit.created_at)}">${escapeHtml(formatDate(hit.created_at))}</time>
      </article>
    `).join('');
  };

  const renderDailyHits = (daily) => {
    const target = $('#daily-hits');
    if (!target) return;
    if (!daily.length) {
      target.innerHTML = '<p class="table-state">Belum ada data hit untuk direkap.</p>';
      return;
    }

    const maximum = Math.max(...daily.map((item) => Number(item.hits || 0)), 1);
    target.innerHTML = daily.map((item) => {
      const hits = Number(item.hits || 0);
      const failures = Number(item.failures || 0);
      return `
        <div class="daily-row">
          <span class="daily-date">${escapeHtml(formatDay(item.day))}</span>
          <progress class="daily-bar" max="${maximum}" value="${hits}" aria-label="${escapeHtml(`${hits} hit, ${failures} error pada ${item.day}`)}">${hits}</progress>
          <span class="daily-value">${escapeHtml(formatNumber(hits))}</span>
          <span class="daily-error">${escapeHtml(formatNumber(failures))} error</span>
        </div>
      `;
    }).join('');
  };

  const loadDashboard = async () => {
    try {
      const payload = await request('dashboard', { query: { page } });
      state.dashboard = payload.data;
      state.csrf = payload.data.csrf_token || state.csrf;
      state.applications = payload.data.applications || [];
      if (payload.data.summary) renderMetrics(payload.data.summary);
      if (Array.isArray(payload.data.applications)) renderApplications(state.applications);
      if (Array.isArray(payload.data.recent_hits)) renderRecentHits(payload.data.recent_hits);
      if (Array.isArray(payload.data.summary?.daily_hits)) renderDailyHits(payload.data.summary.daily_hits);
    } catch (error) {
      showMessage(error.message);
      if ($('#applications-body')) $('#applications-body').innerHTML = '<tr><td colspan="6" class="table-state">Data gagal dimuat. Periksa koneksi database lalu coba muat ulang.</td></tr>';
    }
  };

  const openApplicationDialog = (application = null) => {
    state.editingId = application ? Number(application.id) : null;
    $('#application-dialog-title').textContent = application ? 'Edit aplikasi' : 'Tambah aplikasi';
    $('#application-code').value = application?.app_code || '';
    $('#application-code').disabled = Boolean(application);
    $('#application-name').value = application?.app_name || '';
    $('#application-expiry').value = application?.expires_at ? String(application.expires_at).replace(' ', 'T').slice(0, 16) : '';
    $('#application-active').checked = application ? Boolean(application.is_active) : true;
    updateApplicationCodeHint();
    $('#application-error').hidden = true;
    $('#application-dialog').showModal();
    window.setTimeout(() => $('#application-name').focus(), 0);
  };

  const duplicateApplication = () => {
    const code = $('#application-code')?.value.trim().toLowerCase();
    if (!code) return null;
    return state.applications.find((application) => (
      application.app_code.toLowerCase() === code
      && Number(application.id) !== Number(state.editingId || 0)
    )) || null;
  };

  const updateApplicationCodeHint = () => {
    const target = $('#application-duplicate-note');
    if (!target) return;
    const duplicate = duplicateApplication();
    target.textContent = duplicate
      ? `Kode ${duplicate.app_code} sudah terdaftar sebagai ${duplicate.app_name}. Gunakan kode lain atau edit registry yang ada.`
      : '';
    target.hidden = !duplicate;
  };

  const closeDialog = (dialog) => {
    if (dialog?.open) dialog.close();
  };

  const clearTokenDialog = () => {
    $('#token-app-name').textContent = '';
    $('#token-app-code').textContent = '';
    $('#credential-snippet').textContent = '';
    $('#new-token').textContent = '';
  };

  const showToken = (data) => {
    const token = String(data?.token || '');
    const application = data?.application || {};
    const appCode = String(application.app_code || '');
    const appName = String(application.app_name || appCode);
    const credentialSnippet = `App: ${appCode}\nX-App-Token: ${token}`;
    $('#token-app-name').textContent = appName;
    $('#token-app-code').textContent = appCode;
    $('#credential-snippet').textContent = credentialSnippet;
    $('#new-token').textContent = token;
    $('#token-error').hidden = true;
    $('#token-dialog').showModal();
    window.setTimeout(() => $('#done-token').focus(), 0);
  };

  const saveApplication = async () => {
    const errorTarget = $('#application-error');
    errorTarget.hidden = true;
    if (duplicateApplication()) {
      errorTarget.textContent = 'Kode aplikasi sudah terdaftar. Periksa registry atau gunakan kode lain.';
      errorTarget.hidden = false;
      return;
    }
    const payload = {
      app_code: $('#application-code').value.trim(),
      app_name: $('#application-name').value.trim(),
      expires_at: $('#application-expiry').value,
      is_active: $('#application-active').checked,
    };
    const action = state.editingId ? 'update' : 'create';
    if (state.editingId) payload.id = state.editingId;

    try {
      const response = await request(action, { method: 'POST', body: JSON.stringify(payload) });
      closeDialog($('#application-dialog'));
      if (response.data?.token) showToken(response.data);
      showMessage(response.message, 'success');
      await loadDashboard();
    } catch (error) {
      errorTarget.textContent = error.message;
      errorTarget.hidden = false;
    }
  };

  const rotateApplication = async (id) => {
    const application = state.applications.find((item) => Number(item.id) === Number(id));
    if (!application || !window.confirm(`Rotasi token ${application.app_code}? Token lama langsung tidak berlaku.`)) return;

    try {
      const response = await request('rotate', { method: 'POST', body: JSON.stringify({ id: Number(id) }) });
      showToken(response.data);
      showMessage(response.message, 'success');
      await loadDashboard();
    } catch (error) {
      showMessage(error.message);
    }
  };

  const copyToken = async () => {
    const token = $('#new-token').textContent;
    try {
      await navigator.clipboard.writeText(token);
      $('#copy-token').textContent = 'Tersalin';
      window.setTimeout(() => { $('#copy-token').textContent = 'Salin token'; }, 1800);
    } catch (error) {
      $('#token-error').textContent = 'Clipboard tidak tersedia. Blok token lalu salin manual.';
      $('#token-error').hidden = false;
    }
  };

  const copyCredentials = async () => {
    const credentials = $('#credential-snippet').textContent;
    try {
      await navigator.clipboard.writeText(credentials);
      $('#copy-credentials').textContent = 'Header tersalin';
      window.setTimeout(() => { $('#copy-credentials').textContent = 'Salin header'; }, 1800);
    } catch (error) {
      $('#token-error').textContent = 'Clipboard tidak tersedia. Blok header lalu salin manual.';
      $('#token-error').hidden = false;
    }
  };

  const login = () => {
    const form = $('#login-form');
    if (!form) return;
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const errorTarget = $('#login-error');
      errorTarget.hidden = true;
      try {
        const response = await request('login', {
          method: 'POST',
          body: JSON.stringify({
            username: $('#admin-username').value.trim(),
            password: $('#admin-password').value,
          }),
        });
        state.csrf = response.data.csrf_token;
        window.location.reload();
      } catch (error) {
        errorTarget.textContent = error.message;
        errorTarget.hidden = false;
      }
    });
  };

  const renderUsers = (users) => {
    const target = $('#users-body');
    if (!target) return;
    if (!users.length) {
      target.innerHTML = '<tr><td colspan="6" class="table-state">Belum ada user.</td></tr>';
      return;
    }
    target.innerHTML = users.map((user) => `
      <tr>
        <th scope="row"><span class="app-name">${escapeHtml(user.full_name)}</span><span class="app-code">${escapeHtml(user.username)}${user.email ? ` · ${escapeHtml(user.email)}` : ''}</span></th>
        <td><code>${escapeHtml(user.role_code)}</code></td>
        <td>${escapeHtml(formatNumber(user.permissions.length))} permission${user.employee_id ? ` <span class="app-code">· ${escapeHtml(user.employee_id)}</span>` : ''}</td>
        <td><span class="status status-${user.is_active ? 'active' : 'inactive'}">${user.is_active ? 'Aktif' : 'Nonaktif'}</span></td>
        <td>${escapeHtml(formatDate(user.last_login_at, 'Belum pernah'))}</td>
        <td class="row-actions"><button class="table-action" data-user-action="edit" data-id="${user.id}" type="button" title="Edit user" aria-label="Edit user">${actionIcons.edit}</button></td>
      </tr>
    `).join('');
  };

  const renderEmployeeResults = (employees) => {
    const target = $('#employee-results');
    if (!target) return;
    if (!employees.length) {
      target.innerHTML = '<p class="table-state">Pegawai tidak ditemukan. Coba nama yang lebih singkat atau ID pegawai yang tepat.</p>';
      return;
    }
    target.innerHTML = employees.map((employee) => `
      <button class="employee-result" type="button" data-employee-id="${escapeHtml(employee.employee_id)}">
        <strong>${escapeHtml(employee.full_name)}</strong>
        <span>${escapeHtml(employee.employee_id)}${employee.job_position ? ` · ${escapeHtml(employee.job_position)}` : ''}${employee.kode ? ` · Kantor ${escapeHtml(employee.kode)}` : ''}</span>
      </button>
    `).join('');
  };

  const renderSelectedEmployee = () => {
    const target = $('#employee-selected');
    const employee = state.selectedEmployee;
    if (!target) return;
    target.textContent = employee
      ? `Pegawai dipilih: ${employee.full_name} (${employee.employee_id})`
      : '';
    target.hidden = !employee;
  };

  const updateUserSourceUi = () => {
    const source = document.querySelector('input[name="user-source"]:checked')?.value || 'employee';
    const isEmployee = source === 'employee';
    const panel = $('#employee-search-panel');
    if (panel) panel.hidden = !isEmployee;
    const username = $('#user-username');
    const fullName = $('#user-full-name');
    if (username) username.readOnly = isEmployee;
    if (fullName) fullName.readOnly = isEmployee;
    if (!isEmployee) {
      state.selectedEmployee = null;
      state.employeeResults = [];
      $('#user-employee-id').value = '';
      $('#employee-results').innerHTML = '';
      renderSelectedEmployee();
    }
  };

  const selectEmployee = (employee) => {
    state.selectedEmployee = employee;
    $('#user-employee-id').value = employee.employee_id;
    $('#user-username').value = employee.employee_id.toLowerCase();
    $('#user-full-name').value = employee.full_name;
    renderSelectedEmployee();
    updateUserSourceUi();
  };

  const searchEmployees = async () => {
    const query = $('#user-employee-query').value.trim();
    const target = $('#employee-results');
    if (query.length < 2) {
      target.innerHTML = '<p class="field-error" role="alert">Isi minimal 2 karakter untuk mencari pegawai.</p>';
      return;
    }
    target.innerHTML = '<p class="table-state">Mencari pegawai…</p>';
    try {
      const payload = await request('employee-search', { query: { q: query } });
      state.employeeResults = payload.data?.employees || [];
      renderEmployeeResults(state.employeeResults);
    } catch (error) {
      target.innerHTML = `<p class="field-error" role="alert">${escapeHtml(error.message)}</p>`;
    }
  };

  const openUserDialog = (user = null) => {
    state.editingUserId = user ? Number(user.id) : null;
    state.selectedEmployee = user?.employee_id ? {
      employee_id: user.employee_id,
      full_name: user.full_name,
    } : null;
    state.employeeResults = [];
    $('#user-dialog-title').textContent = user ? 'Edit user' : 'Tambah user';
    $('#user-id').value = user?.id || '';
    $('#user-employee-id').value = user?.employee_id || '';
    $('#user-employee-query').value = '';
    $('#user-username').value = user?.username || '';
    $('#user-username').disabled = Boolean(user);
    $('#user-full-name').value = user?.full_name || '';
    $('#user-email').value = user?.email || '';
    $('#user-role').value = user?.role_code || 'operator';
    $('#user-password').value = '';
    $('#user-password').required = !user;
    $('#user-active').checked = user ? Boolean(user.is_active) : true;
    document.querySelectorAll('input[name="user-source"]').forEach((input) => {
      input.checked = input.value === (user?.employee_id ? 'employee' : (user ? 'external' : 'employee'));
    });
    renderSelectedEmployee();
    updateUserSourceUi();
    document.querySelectorAll('#user-dialog input[data-permission]').forEach((input) => {
      input.checked = Boolean(user?.permissions?.includes(input.value));
    });
    $('#user-error').hidden = true;
    $('#user-dialog').showModal();
    window.setTimeout(() => (user?.employee_id ? $('#user-password') : $('#user-employee-query'))?.focus(), 0);
  };

  const saveUser = async () => {
    const errorTarget = $('#user-error');
    errorTarget.hidden = true;
    const payload = {
      username: $('#user-username').value.trim(),
      employee_id: $('#user-employee-id').value.trim() || null,
      full_name: $('#user-full-name').value.trim(),
      email: $('#user-email').value.trim(),
      password: $('#user-password').value,
      role_code: $('#user-role').value.trim(),
      is_active: $('#user-active').checked,
      permissions: Array.from(document.querySelectorAll('#user-dialog input[data-permission]:checked')).map((input) => input.value),
    };
    if (state.editingUserId) payload.id = state.editingUserId;
    if (document.querySelector('input[name="user-source"]:checked')?.value === 'employee' && !payload.employee_id) {
      errorTarget.textContent = 'Cari dan pilih pegawai internal terlebih dahulu.';
      errorTarget.hidden = false;
      return;
    }
    try {
      const action = state.editingUserId ? 'user-update' : 'user-create';
      const response = await request(action, { method: 'POST', body: JSON.stringify(payload) });
      closeDialog($('#user-dialog'));
      showMessage(response.message, 'success');
      await loadUsers();
    } catch (error) {
      errorTarget.textContent = error.message;
      errorTarget.hidden = false;
    }
  };

  const loadUsers = async () => {
    try {
      const payload = await request('users');
      state.csrf = payload.data.csrf_token || state.csrf;
      state.users = payload.data.users || [];
      renderUsers(state.users);
    } catch (error) {
      showMessage(error.message);
    }
  };

  const bindLogout = () => {
    $('#logout-button')?.addEventListener('click', async () => {
      try {
        await request('logout', { method: 'POST' });
        window.location.reload();
      } catch (error) {
        showMessage(error.message);
      }
    });
  };

  if (!isAuthenticated) {
    login();
    return;
  }

  if (page.startsWith('docs-')) return;

  bindLogout();

  if (page === 'forbidden') return;

  if (page === 'rbac') {
    $('#create-user-button')?.addEventListener('click', () => openUserDialog());
    $('#save-user')?.addEventListener('click', saveUser);
    $('#search-employee-button')?.addEventListener('click', searchEmployees);
    $('#user-employee-query')?.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') {
        event.preventDefault();
        searchEmployees();
      }
    });
    document.querySelectorAll('input[name="user-source"]').forEach((input) => {
      input.addEventListener('change', updateUserSourceUi);
    });
    $('#employee-results')?.addEventListener('click', (event) => {
      const button = event.target.closest('button[data-employee-id]');
      if (!button) return;
      const employee = state.employeeResults.find((item) => String(item.employee_id) === button.dataset.employeeId);
      if (employee) selectEmployee(employee);
    });
    $('#users-body')?.addEventListener('click', (event) => {
      const button = event.target.closest('button[data-user-action]');
      if (!button) return;
      const user = state.users.find((item) => Number(item.id) === Number(button.dataset.id));
      if (button.dataset.userAction === 'edit') openUserDialog(user);
    });
    loadUsers();
    return;
  }

  $('#create-button')?.addEventListener('click', () => openApplicationDialog());
  $('#application-code')?.addEventListener('input', updateApplicationCodeHint);
  $('#save-application').addEventListener('click', saveApplication);
  $('#close-token').addEventListener('click', () => closeDialog($('#token-dialog')));
  $('#done-token').addEventListener('click', () => closeDialog($('#token-dialog')));
  $('#copy-token').addEventListener('click', copyToken);
  $('#copy-credentials').addEventListener('click', copyCredentials);
  $('#token-dialog').addEventListener('close', clearTokenDialog);
  $('#applications-body')?.addEventListener('click', (event) => {
    const button = event.target.closest('button[data-action]');
    if (!button) return;
    const application = state.applications.find((item) => Number(item.id) === Number(button.dataset.id));
    if (button.dataset.action === 'edit') openApplicationDialog(application);
    if (button.dataset.action === 'rotate') rotateApplication(button.dataset.id);
  });

  loadDashboard();
})();
