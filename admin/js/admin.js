/* Beplus GitHub Deploy Admin JS */
(function () {
  'use strict';

  var root = document.getElementById('beplus-github-deploy-root');
  if (!root) return;

  var NS = bmApi.restUrl;
  var state = { settings: {}, packages: {}, log: [] };

  function api(path, opts) {
    opts = opts || {};
    var config = {
      method: opts.method || 'GET',
      headers: { 'X-WP-Nonce': bmApi.nonce }
    };
    if (opts.form) {
      // FormData (file upload): let the browser set Content-Type + boundary.
      config.body = opts.body;
    } else if (opts.body) {
      config.headers['Content-Type'] = 'application/json';
      config.body = JSON.stringify(opts.body);
    }
    return fetch(NS + path, config).then(function (r) {
      return r.json().then(function (data) {
        if (!r.ok) throw new Error((data && data.message) || 'Request failed');
        return data;
      });
    });
  }

  function el(tag, attrs, children) {
    var node = document.createElement(tag);
    if (attrs) {
      Object.keys(attrs).forEach(function (k) {
        if (k === 'class') node.className = attrs[k];
        else if (k === 'html') node.innerHTML = attrs[k];
        else if (k === 'text') node.textContent = attrs[k];
        else node.setAttribute(k, attrs[k]);
      });
    }
    (children || []).forEach(function (c) {
      if (typeof c === 'string') node.appendChild(document.createTextNode(c));
      else node.appendChild(c);
    });
    return node;
  }

  function showMsg(type, text) {
    var m = document.getElementById('bm-msg');
    m.textContent = text;
    m.className = 'bm-msg show ' + type;
    setTimeout(function () { m.className = 'bm-msg'; }, 5000);
  }

  // Error popup modal — asks the user to re-check the package info.
  function showErrorModal(title, message) {
    var overlay = el('div', { class: 'bm-modal-overlay' });
    var modal = el('div', { class: 'bm-modal bm-error-modal' });
    var head = el('div', { class: 'bm-error-head' });
    head.appendChild(icon('warning'));
    head.appendChild(el('h2', { text: title || 'Deploy failed' }));
    modal.appendChild(head);
    modal.appendChild(el('p', { class: 'bm-error-text', text: message || 'Something went wrong.' }));
    modal.appendChild(el('div', { class: 'bm-note bm-error-hint', text: 'Please check the package information (repository, branch, subdirectory) and try again.' }));
    var okBtn = icBtn('bm-btn bm-btn-primary', 'yes-alt', 'OK');
    okBtn.addEventListener('click', function () { overlay.remove(); });
    modal.appendChild(okBtn);
    overlay.appendChild(modal);
    document.body.appendChild(overlay);
  }

  function field(label, input) {
    var wrap = el('div', { class: 'bm-field' });
    var lab = el('label', { text: label });
    wrap.appendChild(lab);
    wrap.appendChild(input);
    return wrap;
  }

  function textInput(value, placeholder) {
    return el('input', { type: 'text', value: value || '', placeholder: placeholder || '' });
  }

  // WP dashicons helper — renders <span class="dashicons dashicons-xxx">
  function icon(name) {
    return el('span', { class: 'dashicons dashicons-' + name });
  }
  // Button with a leading dashicon + label text.
  function icBtn(cls, iconName, label) {
    var b = el('button', { class: cls });
    b.appendChild(icon(iconName));
    b.appendChild(el('span', { text: label }));
    return b;
  }

  function renderSettingsCard() {
    var card = el('div', { class: 'bm-card' });
    var title = el('h2');
    title.appendChild(icon('admin-generic'));
    title.appendChild(el('span', { text: 'GitHub Connection' }));
    card.appendChild(title);

    var savedToken = state.settings.token === 'set';
    var ghLogin = state.settings.github_login || '';
    var repoMode = state.settings.repo_mode || 'auto';

    if (savedToken) {
      // Connected state: badge + actions.
      var badge = el('div', { class: 'bm-badge bm-badge-active' });
      badge.appendChild(icon('yes'));
      badge.appendChild(el('span', { text: 'Connected to GitHub' + (ghLogin ? ' as @' + ghLogin : '') }));
      card.appendChild(badge);
      card.appendChild(el('div', { class: 'bm-note', text: 'Your GitHub account is linked to this site. You can now add packages below.' }));

      // Settings summary row: account + repo mode.
      var summary = el('div', { class: 'bm-settings-summary' });
      var sumItem1 = el('div', { class: 'bm-summary-item' });
      sumItem1.appendChild(el('span', { class: 'bm-summary-label', text: 'Repo loading' }));
      sumItem1.appendChild(el('span', { class: 'bm-summary-value', text: repoMode === 'auto' ? 'Auto (Choose Repository)' : 'Manual (type yourself)' }));
      summary.appendChild(sumItem1);
      card.appendChild(summary);

      var actionsRow = el('div', { class: 'bm-row' });
      var replaceBtn = icBtn('bm-btn bm-btn-ghost', 'update', 'Replace Token');
      var disconnectBtn = icBtn('bm-btn bm-btn-danger', 'no', 'Disconnect');
      actionsRow.appendChild(replaceBtn);
      actionsRow.appendChild(disconnectBtn);
      card.appendChild(actionsRow);

      replaceBtn.addEventListener('click', function () { showTokenForm(); });
      disconnectBtn.addEventListener('click', function () {
        if (!confirm('Disconnect GitHub? The saved token will be removed from this site.')) return;
        disconnectBtn.disabled = true;
        api('settings', { method: 'POST', body: { github_token_clear: true } })
          .then(function () { showMsg('ok', 'Disconnected from GitHub.'); return load(); })
          .catch(function (e) { showMsg('err', e.message); disconnectBtn.disabled = false; });
      });

      card.appendChild(el('div', { class: 'bm-note', text: 'Token stays on this site. To revoke it on GitHub: github.com/settings/applications → Authorized OAuth Apps → Beplus GitHub Deploy → Revoke.' }));
      return card;
    }

    // Not connected: token form — clean modern layout.
    var tokenInput = textInput('', 'Paste your Personal Access Token (starts with ghp_)');
    var tokenField = field('GitHub token', tokenInput);
    card.appendChild(tokenField);

    // Repository loading preference — locked in once the token is saved.
    var repoModeAuto = el('input', { type: 'radio', name: 'bm-repo-mode', value: 'auto' });
    repoModeAuto.checked = true;
    var repoModeManual = el('input', { type: 'radio', name: 'bm-repo-mode', value: 'manual' });
    var modeField = el('div', { class: 'bm-field' });
    modeField.appendChild(el('label', { text: 'Repository loading' }));
    var opts = el('div', { class: 'bm-repo-mode' });
    var autoLabel = el('label', { class: 'bm-radio' });
    var autoIcon = icon('cloud-upload');
    autoIcon.classList.add('bm-radio-icon');
    autoLabel.appendChild(repoModeAuto);
    autoLabel.appendChild(autoIcon);
    autoLabel.appendChild(el('span', { html: '<strong>Auto</strong> — load my repositories (Choose Repository button)' }));
    var manualLabel = el('label', { class: 'bm-radio' });
    var manualIcon = icon('editor-code');
    manualIcon.classList.add('bm-radio-icon');
    manualLabel.appendChild(repoModeManual);
    manualLabel.appendChild(manualIcon);
    manualLabel.appendChild(el('span', { html: '<strong>Manual</strong> — I will type repository, slug and branch myself' }));
    opts.appendChild(autoLabel);
    opts.appendChild(manualLabel);
    modeField.appendChild(opts);
    modeField.appendChild(el('div', { class: 'bm-note', text: 'This choice is locked in when you save the token. To change it later, replace the token.' }));
    card.appendChild(modeField);

    var steps = el('div', { class: 'bm-steps' });
    var step1 = el('div', { class: 'bm-step' });
    step1.appendChild(el('span', { class: 'bm-step-num', text: '1' }));
    step1.appendChild(el('span', { text: 'Click the button below — GitHub opens the token creation page with the right permissions pre-selected.' }));
    steps.appendChild(step1);
    var step2 = el('div', { class: 'bm-step' });
    step2.appendChild(el('span', { class: 'bm-step-num', text: '2' }));
    step2.appendChild(el('span', { text: 'At the bottom of that page, click "Generate token", then copy the token (starts with ghp_).' }));
    steps.appendChild(step2);
    var step3 = el('div', { class: 'bm-step' });
    step3.appendChild(el('span', { class: 'bm-step-num', text: '3' }));
    step3.appendChild(el('span', { text: 'Paste the token here and click "Save GitHub token". You are connected!' }));
    steps.appendChild(step3);
    card.appendChild(steps);

    var row = el('div', { class: 'bm-row' });
    var obtainLink = el('a', {
      href: 'https://github.com/settings/tokens/new?scopes=repo&description=Beplus+Manager',
      target: '_blank',
      rel: 'noopener',
      class: 'bm-btn bm-btn-primary'
    });
    obtainLink.appendChild(icon('external'));
    obtainLink.appendChild(el('span', { text: 'Obtain a GitHub token' }));
    var testBtn = icBtn('bm-btn bm-btn-ghost', 'yes-alt', 'Test Token');
    var saveBtn = icBtn('bm-btn bm-btn-primary', 'saved', 'Save GitHub token');
    row.appendChild(obtainLink);
    row.appendChild(testBtn);
    row.appendChild(saveBtn);
    card.appendChild(row);

    testBtn.addEventListener('click', function () {
      var tk = tokenInput.value.trim();
      if (!tk) { showMsg('err', 'Enter a token first.'); return; }
      testBtn.disabled = true;
      api('github/test', { method: 'POST', body: { token: tk } })
        .then(function (r) {
          showMsg(r.ok ? 'ok' : 'err', r.message || (r.ok ? 'Token OK' : 'Token invalid'));
        })
        .catch(function (e) { showMsg('err', e.message); })
        .finally(function () { testBtn.disabled = false; });
    });

    saveBtn.addEventListener('click', function () {
      var tk = tokenInput.value.trim();
      if (!tk) { showMsg('err', 'Enter a token first.'); return; }
      saveBtn.disabled = true;
      var mode = document.querySelector('input[name="bm-repo-mode"]:checked')?.value || 'auto';
      api('settings', { method: 'POST', body: { github_token: tk, repo_mode: mode } })
        .then(function () { showMsg('ok', 'Token saved.'); return load(); })
        .catch(function (e) { showMsg('err', e.message); })
        .finally(function () { saveBtn.disabled = false; });
    });

    return card;
  }

  function showTokenForm() {
    // Simple inline replacement for the token card: prompt for a new token.
    var tokenInput = textInput('', 'New GitHub token');
    var saveBtn = el('button', { class: 'bm-btn bm-btn-primary', text: 'Save New Token' });
    var wrap = el('div', {});
    wrap.appendChild(field('GitHub token', tokenInput));
    wrap.appendChild(saveBtn);
    var card = document.querySelector('.bm-card');
    if (card) {
      // Replace the whole connection card content.
      card.innerHTML = '';
      var title = el('h2');
      title.appendChild(icon('admin-generic'));
      title.appendChild(el('span', { text: 'GitHub Connection' }));
      card.appendChild(title);
      card.appendChild(wrap);
    }
    saveBtn.addEventListener('click', function () {
      var tk = tokenInput.value.trim();
      if (!tk) { showMsg('err', 'Enter a token first.'); return; }
      saveBtn.disabled = true;
      var mode = document.querySelector('input[name="bm-repo-mode"]:checked')?.value || 'auto';
      api('settings', { method: 'POST', body: { github_token: tk, repo_mode: mode } })
        .then(function () { showMsg('ok', 'Token saved.'); return load(); })
        .catch(function (e) { showMsg('err', e.message); })
        .finally(function () { saveBtn.disabled = false; });
    });
  }

  function showRepoPicker(repos, onSelect) {
    var overlay = el('div', { class: 'bm-modal-overlay' });
    var modal = el('div', { class: 'bm-modal bm-modal-wide' });
    var title = el('h2');
    title.appendChild(icon('portfolio'));
    title.appendChild(el('span', { text: 'Choose a Repository' }));
    modal.appendChild(title);

    var searchInput = textInput('', 'Search repositories…');
    var listBox = el('div', { class: 'bm-repo-list' });
    listBox.textContent = 'Loading…';

    function renderList(filter) {
      listBox.innerHTML = '';
      var filtered = repos.filter(function (r) {
        return !filter || r.name.toLowerCase().indexOf(filter.toLowerCase()) > -1;
      });
      if (!filtered.length) {
        listBox.appendChild(el('div', { class: 'bm-note', text: 'No repositories match.' }));
        return;
      }
      filtered.forEach(function (repo) {
        var item = el('button', { class: 'bm-repo-item' });
        item.appendChild(el('span', { text: repo.name }));
        if (repo.private) {
          var priv = el('span', { class: 'bm-badge bm-badge-private' });
          priv.appendChild(icon('lock'));
          priv.appendChild(el('span', { text: 'private' }));
          item.appendChild(priv);
        }
        item.addEventListener('click', function () {
          if (onSelect) onSelect(repo);
          overlay.remove();
        });
        listBox.appendChild(item);
      });
    }

    searchInput.addEventListener('input', function () { renderList(searchInput.value); });
    modal.appendChild(searchInput);
    modal.appendChild(listBox);
    renderList('');

    var cancelBtn = el('button', { class: 'bm-btn bm-btn-ghost', text: 'Cancel' });
    cancelBtn.addEventListener('click', function () { overlay.remove(); });
    modal.appendChild(cancelBtn);
    overlay.appendChild(modal);
    document.body.appendChild(overlay);
  }

  function renderPackageForm() {
    var card = el('div', { class: 'bm-card' });
    var title = el('h2');
    title.appendChild(icon('plus-alt2'));
    title.appendChild(el('span', { text: 'Add Package' }));
    card.appendChild(title);

    var slugInput = textInput('', 'Directory slug, e.g. my-plugin');
    slugInput.id = 'bm-slug-input';
    var repoInput = textInput('', 'owner/repository');
    repoInput.id = 'bm-repo-input';
    var branchInput = textInput('main', 'Branch');
    branchInput.id = 'bm-branch-input';
    var subdirInput = textInput('', 'Subdirectory (optional)');
    subdirInput.id = 'bm-subdir-input';

    var typeSelect = el('select');
    typeSelect.id = 'bm-type-select';
    ['plugin', 'theme'].forEach(function (t) {
      typeSelect.appendChild(el('option', { value: t, text: t === 'plugin' ? 'Plugin' : 'Theme' }));
    });

    var webhookCheck = el('input', { type: 'checkbox' });
    webhookCheck.checked = false; // Auto-deploy OFF by default; user opts in.
    var webhookField = field('Enable auto-deploy via webhook', webhookCheck);

    var addBtn = el('button', { class: 'bm-btn bm-btn-primary', text: 'Add Package' });

    // Choose repository button — only shown when auto repo-loading is enabled.
    var repoMode = (state.settings.repo_mode || 'auto') === 'auto';
    var chooseBtn = null;
    if (repoMode) {
      chooseBtn = icBtn('bm-btn bm-btn-ghost', 'portfolio', 'Choose Repository');
    }
    var detectNote = el('div', { class: 'bm-note', text: repoMode ? 'Pick a repository — plugin/theme type, slug and branch are filled automatically.' : 'Manual mode: type the repository (owner/repo), slug and branch yourself.' });

    // 2-column grid layout.
    var form = el('div', { class: 'bm-add-form' });
    form.appendChild(field('Type', typeSelect));
    form.appendChild(field('Slug (directory name)', slugInput));
    form.appendChild(field('Repository (owner/repo)', repoInput));
    form.appendChild(field('Branch', branchInput));
    form.appendChild(field('Subdirectory (optional)', subdirInput));
    var wf = webhookField;
    wf.classList.add('bm-field-full');
    form.appendChild(wf);

    var footer = el('div', { class: 'bm-form-footer' });
    if (chooseBtn) {
      footer.appendChild(chooseBtn);
      footer.appendChild(detectNote);
    } else {
      footer.appendChild(detectNote);
    }
    footer.appendChild(addBtn);
    form.appendChild(footer);
    card.appendChild(form);

    if (chooseBtn) {
      chooseBtn.addEventListener('click', function () {
        chooseBtn.disabled = true;
        chooseBtn.textContent = 'Loading…';
        api('github/repos')
          .then(function (r) {
            if (!r.ok || !r.repos) { showMsg('err', r.message || 'Failed to load repos'); chooseBtn.disabled = false; chooseBtn.textContent = 'Choose Repository'; return; }
            showRepoPicker(r.repos, function (repo) {
            // Auto-detect package info and fill everything.
            branchInput.value = repo.branch || 'main';
            repoInput.value = repo.name;
            slugInput.value = '';
            subdirInput.value = '';
            detectNote.textContent = 'Detecting package info…';
            api('github/detect?repo=' + encodeURIComponent(repo.name) + '&branch=' + encodeURIComponent(repo.branch || 'main'))
              .then(function (d) {
                if (d.ok) {
                  typeSelect.value = d.type || 'plugin';
                  slugInput.value = d.slug || '';
                  subdirInput.value = d.subdirectory || '';
                  detectNote.textContent = 'Auto-detected. Check the fields and click Add Package.';
                  detectNote.classList.add('bm-note-ok');
                } else {
                  detectNote.textContent = (d.message || 'Could not auto-detect — fill the fields manually.');
                }
              })
              .catch(function () {
                detectNote.textContent = 'Auto-detect failed — fill the fields manually.';
              });
          });
          chooseBtn.disabled = false;
          chooseBtn.textContent = 'Choose Repository';
        })
        .catch(function (e) { showMsg('err', e.message); chooseBtn.disabled = false; chooseBtn.textContent = 'Choose Repository'; });
      });
    }

    addBtn.addEventListener('click', function () {
      addBtn.disabled = true;
      api('packages', {
        method: 'POST',
        body: {
          slug: slugInput.value.trim(),
          type: typeSelect.value,
          repository: repoInput.value.trim(),
          branch: branchInput.value.trim() || 'main',
          subdirectory: subdirInput.value.trim(),
          webhook: webhookCheck.checked
        }
      })
        .then(function () { showMsg('ok', 'Package added.'); return load(); })
        .catch(function (e) { showMsg('err', e.message); })
        .finally(function () { addBtn.disabled = false; });
    });

    return card;
  }

  // Singleton global progress bar — created once, reused across re-renders so
  // polling keeps updating the SAME element that is in the DOM.
  var globalProgEl = null;
  function getGlobalProgress() {
    if (globalProgEl) return globalProgEl;
    var wrap = el('div', { class: 'bm-progress-wrap bm-progress-global' });
    var track = el('div', { class: 'bm-progress-track' });
    var fill = el('div', { class: 'bm-progress-fill' });
    track.appendChild(fill);
    var label = el('div', { class: 'bm-progress-label' });
    var text = el('span', { text: '' });
    var pct = el('span', { text: '0%' });
    label.appendChild(text);
    label.appendChild(pct);
    wrap.appendChild(track);
    wrap.appendChild(label);
    globalProgEl = wrap;
    return wrap;
  }

  function renderPackagesCard() {
    var card = el('div', { class: 'bm-card' });
    var title = el('h2');
    title.appendChild(icon('admin-plugins'));
    title.appendChild(el('span', { text: 'Packages' }));
    card.appendChild(title);

    // Global progress bar — shown under the title while a deploy/rollback runs.
    var globalProg = getGlobalProgress();
    card.appendChild(globalProg);

    // Keep the bar visible at 100% for a while so the user actually sees it complete.
    function showGlobalProgress(p) {
      var pct = Math.max(0, Math.min(100, p.percent || 0));
      globalProg.querySelector('.bm-progress-fill').style.width = pct + '%';
      globalProg.querySelector('.bm-progress-fill').className = 'bm-progress-fill' + (p.error ? ' error' : (p.done ? ' success' : ''));
      globalProg.querySelector('.bm-progress-label span').textContent = p.message || '';
      globalProg.querySelector('.bm-progress-label span:last-child').textContent = pct + '%';
      if (p.done) {
        // Stay at 100% for 5s, then fade out.
        clearTimeout(globalProg._t);
        globalProg._t = setTimeout(function () {
          globalProg.classList.remove('show');
        }, 5000);
      }
    }

    function startGlobalProgress() {
      clearTimeout(globalProg._t);
      globalProg.classList.add('show');
      showGlobalProgress({ percent: 2, message: 'Starting…' });
    }

    // Called from row actions via a shared registry.
    var progState = { timer: null, slug: null };
    window._bmStartProgress = function (slug) {
      startGlobalProgress();
      if (progState.timer) clearInterval(progState.timer);
      progState.slug = slug;
      progState.timer = setInterval(function () {
        api('progress/' + encodeURIComponent(slug)).then(showGlobalProgress);
      }, 1200);
    };
    window._bmStopProgress = function () {
      if (progState.timer) { clearInterval(progState.timer); progState.timer = null; }
    };
    // Mark the operation as FAILED: stop polling, show the error on the bar
    // itself (red), and hide the bar shortly after — never leave it stuck.
    window._bmFailProgress = function (errMsg) {
      window._bmStopProgress();
      showGlobalProgress({ percent: 100, message: errMsg || 'Failed.', error: true, done: true });
    };

    var names = Object.keys(state.packages);
    if (!names.length) {
      card.appendChild(el('div', { class: 'bm-note', text: 'No packages yet — add one above.' }));
      return card;
    }

    // If disconnected, hide packages (they cannot be deployed without a token).
    if (state.settings.token !== 'set') {
      var note = el('div', { class: 'bm-note' });
      note.appendChild(icon('warning'));
      note.appendChild(el('span', { text: ' Connect your GitHub account above to see your packages. They are hidden because deploys require a token.' }));
      card.appendChild(note);
      return card;
    }

    var table = el('table', { class: 'bm-table' });
    var thead = el('thead');
    var hr = el('tr');
    ['Type', 'Slug', 'Repository', 'Branch', 'Auto', 'Actions'].forEach(function (h) {
      hr.appendChild(el('th', { text: h }));
    });
    thead.appendChild(hr);
    table.appendChild(thead);

    var tbody = el('tbody');
    names.forEach(function (slug) {
      var pkg = state.packages[slug];
      var tr = el('tr');

      // Rollback availability — declared BEFORE the repo cell uses it.
      var hasBackup = !!(pkg.has_backup);
      var backupInfo = hasBackup
        ? 'Backup available:\n' + (pkg.backup_dir || '') + '\n(' + (pkg.backup_time || '') + ')'
        : 'No backup yet — deploy once to create one.';

      tr.appendChild(el('td', {}, [el('span', { class: 'bm-badge ' + (pkg.type === 'theme' ? 'bm-badge-theme' : 'bm-badge-plugin'), text: pkg.type })]));
      tr.appendChild(el('td', { text: pkg.slug }));

      // Repository cell (backup availability shown via the Rollback button state).
      var repoCell = el('td');
      repoCell.appendChild(el('div', { text: pkg.repository }));
      tr.appendChild(repoCell);
      tr.appendChild(el('td', { text: pkg.branch }));

      var autoTd = el('td');
      var autoBadge = el('span', {
        class: 'bm-badge ' + (pkg.webhook ? 'bm-badge-auto' : 'bm-badge-manual'),
        text: pkg.webhook ? 'Auto' : 'Manual'
      });
      if (pkg.webhook) {
        autoBadge.insertBefore(icon('update'), autoBadge.firstChild);
      }
      autoTd.appendChild(autoBadge);
      tr.appendChild(autoTd);

      var actions = el('td');
      var actionsWrap = el('div', { class: 'bm-actions' });
      var deployBtn = icBtn('bm-btn bm-btn-primary', 'upload', 'Deploy');

      // Rollback button — lit up only when a backup file exists.
      var rollbackBtn = el('button', {
        class: 'bm-btn bm-btn-ghost' + (hasBackup ? ' bm-btn-rollback-ready' : ' bm-btn-disabled'),
        text: hasBackup ? 'Rollback' : 'Rollback',
        title: backupInfo
      });
      if (hasBackup) {
        rollbackBtn.insertBefore(icon('image-rotate'), rollbackBtn.firstChild);
      }
      if (!hasBackup) {
        rollbackBtn.disabled = true;
        rollbackBtn.addEventListener('click', function (e) { e.preventDefault(); });
      }
      var editBtn = icBtn('bm-btn bm-btn-ghost', 'edit', 'Edit');
      var delBtn = icBtn('bm-btn bm-btn-danger', 'trash', '');

      deployBtn.addEventListener('click', function () {
        if (!confirm('Deploy ' + pkg.slug + ' from ' + pkg.repository + ' (' + pkg.branch + ')?')) return;
        deployBtn.disabled = true;
        rollbackBtn.disabled = true;
        window._bmStartProgress(pkg.slug);
        api('deploy', { method: 'POST', body: { slug: pkg.slug } })
          .then(function (r) { showMsg('ok', r.message); return load(); })
          .catch(function (e) { showErrorModal('Deploy failed', e.message); window._bmFailProgress(e.message); deployBtn.disabled = false; rollbackBtn.disabled = false; });
      });

      rollbackBtn.addEventListener('click', function () {
        if (!confirm('Rollback ' + pkg.slug + ' to the previous version?')) return;
        rollbackBtn.disabled = true;
        deployBtn.disabled = true;
        window._bmStartProgress(pkg.slug);
        api('rollback', { method: 'POST', body: { slug: pkg.slug, type: pkg.type } })
          .then(function (r) { showMsg('ok', r.message); return load(); })
          .catch(function (e) { showErrorModal('Rollback failed', e.message); window._bmFailProgress(e.message); })
          .finally(function () { rollbackBtn.disabled = false; deployBtn.disabled = false; });
      });

      editBtn.addEventListener('click', function () {
        showEditModal(pkg);
      });

      delBtn.addEventListener('click', function () {
        if (!confirm('Remove package ' + pkg.slug + ' from Beplus GitHub Deploy?\n\nThe deployed files will NOT be deleted — they stay live on the site.')) return;
        api('packages/' + pkg.slug, { method: 'DELETE' })
          .then(function (r) { showMsg('ok', r.message || 'Package removed.'); return load(); })
          .catch(function (e) { showMsg('err', e.message); });
      });

      actionsWrap.appendChild(deployBtn);
      actionsWrap.appendChild(rollbackBtn);
      actionsWrap.appendChild(editBtn);
      actionsWrap.appendChild(delBtn);
      actions.appendChild(actionsWrap);
      tr.appendChild(actions);
      tbody.appendChild(tr);
    });
    table.appendChild(tbody);
    card.appendChild(table);
    return card;
  }

  function showEditModal(pkg) {
    var overlay = el('div', { class: 'bm-modal-overlay' });
    var modal = el('div', { class: 'bm-modal bm-modal-wide' });
    var title = el('h2');
    title.appendChild(icon('edit'));
    title.appendChild(el('span', { text: 'Edit Package: ' + pkg.slug }));
    modal.appendChild(title);

    var typeSelect = el('select');
    ['plugin', 'theme'].forEach(function (t) {
      typeSelect.appendChild(el('option', { value: t, text: t === 'plugin' ? 'Plugin' : 'Theme' }));
    });
    typeSelect.value = pkg.type || 'plugin';

    var slugInput = textInput(pkg.slug, 'Slug');
    var repoInput = textInput(pkg.repository || '', 'owner/repository');
    var branchInput = textInput(pkg.branch || 'main', 'Branch');
    var subdirInput = textInput(pkg.subdirectory || '', 'Subdirectory (optional)');
    var webhookCheck = el('input', { type: 'checkbox' });
    webhookCheck.checked = !!pkg.webhook;

    var grid = el('div', { class: 'bm-form-grid' });
    grid.appendChild(field('Type', typeSelect));
    grid.appendChild(field('Slug', slugInput));
    grid.appendChild(field('Repository', repoInput));
    grid.appendChild(field('Branch', branchInput));
    grid.appendChild(field('Subdirectory (optional)', subdirInput));
    grid.appendChild(field('Enable auto-deploy via webhook', webhookCheck));
    modal.appendChild(grid);

    var saveBtn = el('button', { class: 'bm-btn bm-btn-primary', text: 'Save Changes' });
    var cancelBtn = el('button', { class: 'bm-btn bm-btn-ghost', text: 'Cancel', style: 'margin-left:6px' });
    modal.appendChild(saveBtn);
    modal.appendChild(cancelBtn);

    saveBtn.addEventListener('click', function () {
      saveBtn.disabled = true;
      api('packages', {
        method: 'POST',
        body: {
          slug: slugInput.value.trim(),
          type: typeSelect.value,
          repository: repoInput.value.trim(),
          branch: branchInput.value.trim() || 'main',
          subdirectory: subdirInput.value.trim(),
          webhook: webhookCheck.checked
        }
      })
        .then(function () { showMsg('ok', 'Package updated.'); overlay.remove(); return load(); })
        .catch(function (e) { showMsg('err', e.message); saveBtn.disabled = false; })
        .finally(function () { saveBtn.disabled = false; });
    });
    cancelBtn.addEventListener('click', function () { overlay.remove(); });

    overlay.appendChild(modal);
    document.body.appendChild(overlay);
  }

  function renderBackupsCard() {
    var card = el('div', { class: 'bm-card' });
    var header = el('div', { class: 'bm-card-header' });
    var title = el('h2');
    title.appendChild(icon('backup'));
    title.appendChild(el('span', { text: 'Backups' }));
    header.appendChild(title);
    var headerBtns = el('div', { class: 'bm-card-header-btns' });
    var importBtn = icBtn('bm-btn bm-btn-ghost bm-btn-sm', 'upload', 'Import Backup');
    var viewBtn = icBtn('bm-btn bm-btn-ghost bm-btn-sm', 'visibility', 'Hide Backups');
    headerBtns.appendChild(importBtn);
    headerBtns.appendChild(viewBtn);
    header.appendChild(headerBtns);
    card.appendChild(header);

    // Hidden file input for import.
    var fileInput = el('input', { type: 'file', accept: '.zip,application/zip' });
    fileInput.style.display = 'none';
    card.appendChild(fileInput);

    importBtn.addEventListener('click', function () { fileInput.click(); });

    fileInput.addEventListener('change', function () {
      if (!fileInput.files || !fileInput.files.length) return;
      var fd = new FormData();
      fd.append('file', fileInput.files[0]);
      importBtn.disabled = true;
      importBtn.textContent = 'Importing…';
      api('backups/import', { method: 'POST', body: fd, form: true })
        .then(function (r) { showMsg('ok', r.message || 'Imported.'); if (listBox.style.display === 'block') viewBtn.click(); return load(); })
        .catch(function (e) { showMsg('err', e.message); })
        .finally(function () { importBtn.disabled = false; importBtn.textContent = 'Import Backup'; fileInput.value = ''; });
    });

    var listBox = el('div', { class: 'bm-backup-list' });
    listBox.style.display = 'none';
    card.appendChild(listBox);

    viewBtn.addEventListener('click', function () {
      if (listBox.style.display === 'none') {
        viewBtn.disabled = true;
        viewBtn.textContent = 'Loading…';
        api('backups')
          .then(function (data) {
            listBox.innerHTML = '';
            var backups = data.backups || [];
            if (!backups.length) {
              listBox.appendChild(el('div', { class: 'bm-note', text: 'No backups found.' }));
            }
            backups.forEach(function (b) {
              var row = el('div', { class: 'bm-backup-row' });
              var info = el('span', { class: 'bm-backup-name', text: b.name + ' (' + b.size + ' — ' + b.time + ')' });
              var rowBtns = el('div', { class: 'bm-actions' });
              // Restore this exact backup (already on the server) immediately.
              var rs = icBtn('bm-btn bm-btn-ghost bm-btn-sm', 'image-rotate', 'Restore');
              rs.addEventListener('click', function () {
                if (!confirm('Restore backup ' + b.name + '? The current version will be snapshotted first.')) return;
                rs.disabled = true;
                rs.textContent = 'Restoring…';
                api('backups/' + b.name + '/restore', { method: 'POST' })
                  .then(function (r) { showMsg('ok', r.message || 'Restored.'); return load(); })
                  .catch(function (e) { showMsg('err', e.message); })
                  .finally(function () { rs.disabled = false; rs.textContent = 'Restore'; });
              });

              var dl = icBtn('bm-btn bm-btn-ghost bm-btn-sm', 'download', 'Download');
              dl.addEventListener('click', function () {
                dl.disabled = true;
                dl.textContent = 'Zipping…';
                fetch(bmApi.restUrl + 'backups/' + b.name + '/download', { headers: { 'X-WP-Nonce': bmApi.nonce } })
                  .then(function (r) {
                    if (!r.ok) throw new Error('Download failed (' + r.status + ')');
                    return r.blob();
                  })
                  .then(function (blob) {
                    var url = URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = b.name + '.zip';
                    document.body.appendChild(a);
                    a.click();
                    setTimeout(function () { URL.revokeObjectURL(url); a.remove(); }, 1500);
                    showMsg('ok', 'Downloaded ' + b.name + '.zip');
                  })
                  .catch(function (e) { showMsg('err', e.message); })
                  .finally(function () { dl.disabled = false; dl.textContent = 'Download'; });
              });
              var del = icBtn('bm-btn bm-btn-danger bm-btn-sm', 'trash', 'Delete');
              del.addEventListener('click', function () {
                if (!confirm('Delete backup ' + b.name + '?')) return;
                del.disabled = true;
                api('backups/' + b.name, { method: 'DELETE' })
                  .then(function (r) { showMsg('ok', r.message || 'Deleted.'); viewBtn.click(); return load(); })
                  .catch(function (e) { showMsg('err', e.message); del.disabled = false; });
              });
              rowBtns.appendChild(rs);
              rowBtns.appendChild(dl);
              rowBtns.appendChild(del);
              row.appendChild(info);
              row.appendChild(rowBtns);
              listBox.appendChild(row);
            });
            listBox.style.display = 'block';
            viewBtn.textContent = 'Hide Backups';
            viewBtn.disabled = false;
          })
          .catch(function (e) { showMsg('err', e.message); viewBtn.textContent = 'View All Backups'; viewBtn.disabled = false; });
      } else {
        listBox.style.display = 'none';
        viewBtn.textContent = 'View All Backups';
      }
    });

    // Show the backup list by default.
    viewBtn.click();

    return card;
  }

  function renderLogCard() {
    var card = el('div', { class: 'bm-card' });
    var header = el('div', { class: 'bm-card-header' });
    var title = el('h2');
    title.appendChild(icon('list-view'));
    title.appendChild(el('span', { text: 'Activity Log' }));
    header.appendChild(title);
    var clearBtn = icBtn('bm-btn bm-btn-danger bm-btn-sm', 'trash', 'Clear All');
    clearBtn.addEventListener('click', function () {
      if (!confirm('Clear all activity log entries?')) return;
      clearBtn.disabled = true;
      api('logs', { method: 'DELETE' })
        .then(function (r) { showMsg('ok', r.message || 'Cleared.'); return load(); })
        .catch(function (e) { showMsg('err', e.message); clearBtn.disabled = false; });
    });
    header.appendChild(clearBtn);
    card.appendChild(header);
    var logBox = el('div', { class: 'bm-log' });
    (state.log || []).forEach(function (entry) {
      logBox.appendChild(el('div', {}, [
        el('span', { class: 'time', text: entry.time }),
        document.createTextNode(entry.level.toUpperCase() + ': ' + entry.msg)
      ]));
    });
    if (!state.log || !state.log.length) {
      logBox.appendChild(el('div', { text: 'No activity yet.' }));
    }
    card.appendChild(logBox);
    return card;
  }

  function render() {
    root.innerHTML = '';
    var msg = el('div', { id: 'bm-msg' });
    root.appendChild(msg);
    root.appendChild(renderSettingsCard());
    root.appendChild(renderPackageForm());
    root.appendChild(renderPackagesCard());
    root.appendChild(renderBackupsCard());
    root.appendChild(renderLogCard());
  }

  function load() {
    return api('settings').then(function (data) {
      state.settings = data.settings || {};
      state.packages = data.packages || {};
      state.log = data.log || [];
      render();
    });
  }

  load().catch(function (e) {
    root.innerHTML = '<p>Failed to load Beplus GitHub Deploy: ' + e.message + '</p>';
  });
})();
