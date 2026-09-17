/* 07-describe-page.js — фрагмент внедряемого сборщика.
   Человекочитаемое описание страницы.

   ВНИМАНИЕ: это не модуль, а часть одного замыкания. Обёртку
   (function(){'use strict'; ... })() добавляет загрузчик
   (src/sessions.ts), он же склеивает части в порядке имён.
   Поэтому здесь нет ни импортов, ни экспортов: переменные общие
   с остальными частями, и редактор законно не видит их объявлений.
   Сборщик внедряется в страницу одним скриптом через
   addInitScript, поэтому частями он может быть только так. */

  // ── Public API: __describePage() ─────────────────────────────────
  // Returns a compact human-readable text summary of the page.
  window.__describePage = function () {
    drainAppLog();

    var lines = [];

    // URL and title
    lines.push(document.title + ' \u2014 ' + location.href);
    lines.push('');

    // Headings (h1-h3)
    var headings = document.querySelectorAll('h1, h2, h3');
    if (headings.length) {
      for (var h = 0; h < headings.length && h < 10; h++) {
        var el = headings[h];
        var text = (el.textContent || '').trim();
        if (text) lines.push(el.tagName + ': ' + text);
      }
      lines.push('');
    }

    var main = document.querySelector('main') || document.body;

    // Navigation sections
    var navs = main.querySelectorAll('nav, [role="navigation"], [role="tablist"]');
    if (navs.length) {
      var navItems = [];
      for (var ni = 0; ni < navs.length && ni < 3; ni++) {
        var navEl = navs[ni];
        var id = navEl.getAttribute(TESTID_ATTR) || navEl.getAttribute('aria-label') || 'nav-' + ni;
        var links = navEl.querySelectorAll('a, button');
        var active = navEl.querySelector('.active, [aria-selected="true"], [aria-current="page"]');
        var activeText = active ? ' (active: ' + (active.textContent || '').trim().substring(0, 30) + ')' : '';
        navItems.push(id + ': ' + links.length + ' items' + activeText);
      }
      lines.push('Nav: ' + navItems.join(' | '));
    }

    // Tables with header info
    var tables = main.querySelectorAll('table');
    if (tables.length) {
      for (var ti = 0; ti < tables.length; ti++) {
        var rows = tables[ti].querySelectorAll('tbody tr');
        var headers = tables[ti].querySelectorAll('thead th');
        var headerTexts = [];
        for (var hi = 0; hi < headers.length && hi < 6; hi++) {
          var ht = (headers[hi].textContent || '').trim();
          if (ht) headerTexts.push(ht);
        }
        var headerInfo = headerTexts.length ? ' [' + headerTexts.join(', ') + ']' : '';
        lines.push('Table: ' + rows.length + ' rows' + headerInfo);
      }
    }

    // Lists with first items preview
    var lists = main.querySelectorAll('ul, ol');
    if (lists.length) {
      for (var li = 0; li < lists.length && li < 5; li++) {
        var items = lists[li].querySelectorAll(':scope > li');
        if (items.length === 0) continue;
        var preview = [];
        for (var pi = 0; pi < items.length && pi < 3; pi++) {
          var itemText = (items[pi].textContent || '').trim().substring(0, 40);
          if (itemText) preview.push('"' + itemText + '"');
        }
        var more = items.length > 3 ? ' (+' + (items.length - 3) + ' more)' : '';
        lines.push('List: ' + items.length + ' items: ' + preview.join(', ') + more);
      }
    }

    // Forms with field details
    var formEls = main.querySelectorAll('form');
    // Also look for standalone inputs/textareas (React forms without <form> tag)
    var standaloneInputs = main.querySelectorAll('input:not(form input), textarea:not(form textarea), select:not(form select)');
    if (formEls.length) {
      for (var f = 0; f < formEls.length; f++) {
        var form = formEls[f];
        var fid = form.getAttribute(TESTID_ATTR) || form.id || 'form-' + f;
        var inputs = form.querySelectorAll('input, select, textarea');
        var fieldDescs = [];
        for (var j = 0; j < inputs.length; j++) {
          var inp = inputs[j];
          if (inp.type === 'hidden') continue;
          var fname = inp.getAttribute(TESTID_ATTR) || inp.name || inp.placeholder || inp.type;
          var val = inp.value;
          var desc = fname;
          if (val) desc += '="' + val.substring(0, 20) + '"';
          if (inp.classList.contains('is-invalid') || inp.getAttribute('aria-invalid') === 'true') {
            desc += '(!)';
          }
          fieldDescs.push(desc);
        }
        lines.push('Form[' + fid + ']: ' + fieldDescs.join(', '));
      }
    }
    if (standaloneInputs.length > 0) {
      var standaloneDescs = [];
      for (var si = 0; si < standaloneInputs.length && si < 10; si++) {
        var sinp = standaloneInputs[si];
        if (sinp.type === 'hidden') continue;
        var stid = sinp.getAttribute(TESTID_ATTR) || sinp.name || sinp.placeholder || sinp.type;
        standaloneDescs.push(stid);
      }
      if (standaloneDescs.length) {
        lines.push('Inputs: ' + standaloneDescs.join(', '));
      }
    }

    // Buttons
    var buttons = main.querySelectorAll('button, [role="button"]');
    var visibleBtns = [];
    for (var bi = 0; bi < buttons.length; bi++) {
      var btn = buttons[bi];
      if (btn.offsetParent === null && btn.style.display === 'none') continue;
      var btid = btn.getAttribute(TESTID_ATTR);
      var blabel = btid || (btn.textContent || '').trim().substring(0, 25);
      if (blabel) visibleBtns.push(blabel);
    }
    if (visibleBtns.length) {
      var shown = visibleBtns.slice(0, 8);
      var moreB = visibleBtns.length > 8 ? ' (+' + (visibleBtns.length - 8) + ' more)' : '';
      lines.push('Buttons: ' + shown.join(', ') + moreB);
    }

    // Links
    var links = main.querySelectorAll('a[href]');
    if (links.length > 0) {
      lines.push('Links: ' + links.length);
    }

    // Islands (class with -init suffix pattern)
    var islands = document.querySelectorAll('[class*="-init"]');
    if (islands.length) {
      var islandNames = [];
      for (var ii = 0; ii < islands.length; ii++) {
        var cls = islands[ii].className;
        var match = cls.match(/(\S+-init)/);
        if (match) islandNames.push(match[1]);
      }
      if (islandNames.length) {
        lines.push('Islands: ' + islandNames.join(', '));
      }
    }

    // Testid summary (grouped by prefix)
    var testidEls = document.querySelectorAll('[' + TESTID_ATTR + ']');
    if (testidEls.length) {
      var groups = {};
      for (var tii = 0; tii < testidEls.length; tii++) {
        var tid = testidEls[tii].getAttribute(TESTID_ATTR) || '';
        // Extract prefix: everything before the last dash+number
        var prefix = tid.replace(/-\d+$/, '-*');
        if (prefix === tid) prefix = tid; // no number suffix
        groups[prefix] = (groups[prefix] || 0) + 1;
      }
      var summary = [];
      var keys = Object.keys(groups);
      for (var gi = 0; gi < keys.length && gi < 15; gi++) {
        var count = groups[keys[gi]];
        summary.push(count > 1 ? count + 'x ' + keys[gi] : keys[gi]);
      }
      var moreT = keys.length > 15 ? ' (+' + (keys.length - 15) + ' more)' : '';
      lines.push('Testids (' + testidEls.length + '): ' + summary.join(', ') + moreT);
    }

    lines.push('');

    // Modals
    var modals = document.querySelectorAll(
      '[role="dialog"]:not([style*="display: none"]), .modal.show, .modal[open]'
    );
    if (modals.length) {
      lines.push('Modals: ' + modals.length + ' visible');
    }

    // Toasts
    var toastEls = document.querySelectorAll(
      '[role="alert"], .toast, .alert, [' + TESTID_ATTR + '*="toast"], [' + TESTID_ATTR + '*="alert"]'
    );
    var visibleToasts = 0;
    for (var k = 0; k < toastEls.length; k++) {
      if (toastEls[k].offsetParent !== null) visibleToasts++;
    }
    if (visibleToasts) {
      lines.push('Toasts/alerts: ' + visibleToasts);
    }

    // Loading states
    var loading = document.querySelectorAll('.spinner, .loading, [aria-busy="true"], [role="status"]');
    if (loading.length) {
      lines.push('Loading indicators: ' + loading.length);
    }

    // Network activity summary from log
    var netOk = 0, netFail = 0, netErr = 0;
    for (var e = 0; e < LOG.length; e++) {
      if (LOG[e].cat === 'net.ok') netOk++;
      else if (LOG[e].cat === 'net.fail') netFail++;
      else if (LOG[e].cat === 'net.error') netErr++;
    }
    if (netOk || netFail || netErr) {
      var netParts = [];
      if (netOk) netParts.push(netOk + ' ok');
      if (netFail) netParts.push(netFail + ' fail');
      if (netErr) netParts.push(netErr + ' error');
      lines.push('Network: ' + netParts.join(', '));
    }

    // Error count
    var errorCount = 0;
    for (var ec = 0; ec < LOG.length; ec++) {
      if (LOG[ec].cat === 'js.error' || LOG[ec].cat === 'js.promise' || LOG[ec].cat === 'react.error') errorCount++;
    }
    if (errorCount) {
      lines.push('Errors: ' + errorCount);
    }

    // Clean up empty lines at end
    while (lines.length > 0 && lines[lines.length - 1] === '') lines.pop();

    return lines.join('\n');
  };
