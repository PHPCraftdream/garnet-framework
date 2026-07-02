/**
 * Garnet Browser MCP — Debug Collector
 *
 * Injected into web pages via Playwright's context.addInitScript().
 * The application itself has no awareness of this script.
 *
 * Captures JS errors, network activity (fetch + XHR), navigation,
 * form submits, and console.error into a unified ring buffer (max 1000 entries).
 *
 * Exposes query/clear/snapshot/describe APIs on `window` for the MCP server.
 */
(function () {
  'use strict';

  // ── Configuration ──────────────────────────────────────────────
  var CFG = window.__GARNET_MCP_CONFIG__ || {};
  var TESTID_ATTR = CFG.testidAttr || 'data-test-id';

  // ── Ring buffer ──────────────────────────────────────────────────
  var MAX = 1000;
  var LOG = [];
  var mutationCount = 0;

  function push(entry) {
    if (LOG.length >= MAX) LOG.shift();
    LOG.push(entry);
  }

  // Merge any entries that business code pushed to __GARNET_LOG__
  function drainAppLog() {
    var app = window.__GARNET_LOG__;
    if (!app || !app.length) return;
    while (app.length) {
      var entry = app.shift();
      if (!entry.t) entry.t = Date.now();
      push(entry);
    }
  }

  // ── JS errors ────────────────────────────────────────────────────
  window.addEventListener('error', function (e) {
    push({
      t: Date.now(),
      cat: 'js.error',
      src: 'error',
      msg: (e.message || 'Unknown error') +
        (e.filename ? ' at ' + e.filename + ':' + e.lineno + ':' + e.colno : ''),
    });
  });

  window.addEventListener('unhandledrejection', function (e) {
    var reason = e.reason;
    var msg = reason instanceof Error ? reason.message : String(reason);
    push({ t: Date.now(), cat: 'js.promise', src: 'error', msg: msg });
  });

  // ── Console.error intercept ──────────────────────────────────────
  var origConsoleError = console.error;
  console.error = function () {
    var parts = [];
    var componentStack = null;
    for (var i = 0; i < arguments.length; i++) {
      var arg = arguments[i];
      // Detect React/Preact component stack
      if (typeof arg === 'string' && arg.indexOf('The above error occurred in the <') === 0) {
        componentStack = arg;
      }
      try {
        parts.push(typeof arg === 'string' ? arg : JSON.stringify(arg));
      } catch (_) {
        parts.push(String(arg));
      }
    }

    var cat = componentStack ? 'react.error' : 'js.error';
    var entry = { t: Date.now(), cat: cat, src: 'error', msg: parts.join(' ') };
    if (componentStack) entry.data = componentStack;
    push(entry);
    return origConsoleError.apply(console, arguments);
  };

  // ── Helper: capture response body (truncated, JSON/text only) ────
  function captureBody(response, callback) {
    try {
      var ct = response.headers.get('content-type') || '';
      if (ct.indexOf('json') === -1 && ct.indexOf('text') === -1) {
        callback(null);
        return;
      }
      // Skip large responses
      var cl = response.headers.get('content-length');
      if (cl && parseInt(cl, 10) > 10000) {
        callback('(body too large: ' + cl + ' bytes)');
        return;
      }
      response.clone().text().then(function (body) {
        callback(body.length > 500 ? body.substring(0, 500) + '...' : body);
      }).catch(function () {
        callback(null);
      });
    } catch (_) {
      callback(null);
    }
  }

  // ── Fetch intercept ──────────────────────────────────────────────
  var origFetch = window.fetch;

  window.fetch = function (input, init) {
    var method = (init && init.method) ? init.method.toUpperCase() : 'GET';
    var url;
    if (typeof input === 'string') {
      url = input;
    } else if (input instanceof URL) {
      url = input.href;
    } else if (input && input.url) {
      url = input.url;
      if (!init || !init.method) method = (input.method || 'GET').toUpperCase();
    } else {
      url = String(input);
    }

    var start = Date.now();

    return origFetch.apply(window, arguments).then(
      function (response) {
        var duration = Date.now() - start;
        var status = response.status;
        var label = method + ' ' + url + ' \u2192 ' + status + ' (' + duration + 'ms)';

        if (status >= 200 && status < 300) {
          captureBody(response, function (body) {
            var entry = { t: Date.now(), cat: 'net.ok', src: 'net', msg: label };
            if (body) entry.data = body;
            push(entry);
          });
        } else {
          captureBody(response, function (body) {
            var entry = { t: Date.now(), cat: 'net.fail', src: 'net', msg: label };
            if (body) entry.data = body;
            push(entry);
          });
        }

        if (duration > 1000) {
          push({ t: Date.now(), cat: 'perf.slow', src: 'perf', msg: label });
        }

        return response;
      },
      function (err) {
        var duration = Date.now() - start;
        push({
          t: Date.now(),
          cat: 'net.error',
          src: 'net',
          msg: method + ' ' + url + ' \u2192 NETWORK ERROR (' + duration + 'ms): ' + (err && err.message || err),
        });
        throw err;
      }
    );
  };

  // ── XHR intercept ────────────────────────────────────────────────
  var origXhrOpen = XMLHttpRequest.prototype.open;
  var origXhrSend = XMLHttpRequest.prototype.send;

  XMLHttpRequest.prototype.open = function (method, url) {
    this.__garnet_method = (method || 'GET').toUpperCase();
    this.__garnet_url = url;
    this.__garnet_start = 0;
    return origXhrOpen.apply(this, arguments);
  };

  XMLHttpRequest.prototype.send = function () {
    var xhr = this;
    xhr.__garnet_start = Date.now();

    xhr.addEventListener('loadend', function () {
      var duration = Date.now() - (xhr.__garnet_start || Date.now());
      var status = xhr.status;
      var method = xhr.__garnet_method || '?';
      var url = xhr.__garnet_url || '?';
      var label = method + ' ' + url + ' \u2192 ' + status + ' (' + duration + 'ms)';

      if (status >= 200 && status < 300) {
        var body = null;
        try {
          var ct = xhr.getResponseHeader('content-type') || '';
          if ((ct.indexOf('json') !== -1 || ct.indexOf('text') !== -1) && xhr.responseText) {
            body = xhr.responseText.length > 500 ? xhr.responseText.substring(0, 500) + '...' : xhr.responseText;
          }
        } catch (_) {}
        var entry = { t: Date.now(), cat: 'net.ok', src: 'net', msg: label };
        if (body) entry.data = body;
        push(entry);
      } else if (status > 0) {
        var errBody = null;
        try { errBody = xhr.responseText ? xhr.responseText.substring(0, 500) : null; } catch (_) {}
        var errEntry = { t: Date.now(), cat: 'net.fail', src: 'net', msg: label };
        if (errBody) errEntry.data = errBody;
        push(errEntry);
      }
      // status 0 = aborted/network error, handled by 'error' event

      if (duration > 1000) {
        push({ t: Date.now(), cat: 'perf.slow', src: 'perf', msg: label });
      }
    });

    xhr.addEventListener('error', function () {
      var duration = Date.now() - (xhr.__garnet_start || Date.now());
      push({
        t: Date.now(),
        cat: 'net.error',
        src: 'net',
        msg: (xhr.__garnet_method || '?') + ' ' + (xhr.__garnet_url || '?') + ' \u2192 NETWORK ERROR (' + duration + 'ms)',
      });
    });

    return origXhrSend.apply(this, arguments);
  };

  // ── Navigation tracking ──────────────────────────────────────────
  var lastUrl = location.href;

  window.addEventListener('load', function () {
    push({ t: Date.now(), cat: 'nav.full', src: 'nav', msg: location.href });
  });

  function checkUrlChange() {
    if (location.href !== lastUrl) {
      var from = lastUrl;
      lastUrl = location.href;
      push({ t: Date.now(), cat: 'nav.spa', src: 'nav', msg: from + ' \u2192 ' + lastUrl });
    }
  }

  var origPushState = history.pushState;
  var origReplaceState = history.replaceState;

  history.pushState = function () {
    var result = origPushState.apply(this, arguments);
    checkUrlChange();
    return result;
  };

  history.replaceState = function () {
    var result = origReplaceState.apply(this, arguments);
    checkUrlChange();
    return result;
  };

  window.addEventListener('popstate', checkUrlChange);
  window.addEventListener('hashchange', checkUrlChange);

  // ── Mutation observer ────────────────────────────────────────────
  if (typeof MutationObserver !== 'undefined') {
    var observer = new MutationObserver(function (mutations) {
      mutationCount += mutations.length;
    });

    function startObserving() {
      if (document.documentElement) {
        observer.observe(document.documentElement, {
          childList: true,
          subtree: true,
          attributes: true,
        });
      }
    }

    if (document.documentElement) {
      startObserving();
    } else {
      document.addEventListener('DOMContentLoaded', startObserving);
    }
  }

  // ── Form submit tracking ─────────────────────────────────────────
  document.addEventListener('submit', function (e) {
    var form = e.target;
    var id = form.getAttribute(TESTID_ATTR) || form.id || form.action || '(anonymous)';
    push({ t: Date.now(), cat: 'form.submit', src: 'form', msg: 'submit ' + id });
  }, true);

  // ── Glob pattern -> RegExp ────────────────────────────────────────
  function globToRegex(pattern) {
    var escaped = pattern.replace(/[.+^${}()|[\]\\]/g, '\\$&');
    escaped = escaped.replace(/\*/g, '.*');
    return new RegExp('^' + escaped + '$');
  }

  // ── Public API: __queryLog(opts) ─────────────────────────────────
  window.__queryLog = function (opts) {
    drainAppLog();
    opts = opts || {};

    var catRe = opts.cat ? globToRegex(opts.cat) : null;
    var src = opts.src || null;
    var search = opts.search ? opts.search.toLowerCase() : null;

    var results = [];

    for (var i = 0; i < LOG.length; i++) {
      var e = LOG[i];
      if (catRe && !catRe.test(e.cat)) continue;
      if (src && e.src !== src) continue;
      if (search) {
        var haystack = (e.cat + ' ' + e.msg + ' ' + (e.data || '')).toLowerCase();
        if (haystack.indexOf(search) === -1) continue;
      }
      results.push(e);
    }

    if (opts.last && opts.last > 0) {
      results = results.slice(-opts.last);
    }

    return results;
  };

  // ── Public API: __clearLog() ─────────────────────────────────────
  window.__clearLog = function () {
    LOG.length = 0;
    mutationCount = 0;
    if (window.__GARNET_LOG__) window.__GARNET_LOG__.length = 0;
  };

  // ── Public API: __tailLog(last, filterOpts?) ────────────────────
  window.__tailLog = function (last, filterOpts) {
    drainAppLog();
    last = last || 20;
    filterOpts = filterOpts || {};

    var tail = LOG.slice(-last);

    var catRe = filterOpts.cat ? globToRegex(filterOpts.cat) : null;
    var src = filterOpts.src || null;
    var search = filterOpts.search ? filterOpts.search.toLowerCase() : null;

    if (!catRe && !src && !search) return tail;

    var results = [];
    for (var i = 0; i < tail.length; i++) {
      var e = tail[i];
      if (catRe && !catRe.test(e.cat)) continue;
      if (src && e.src !== src) continue;
      if (search) {
        var haystack = (e.cat + ' ' + e.msg + ' ' + (e.data || '')).toLowerCase();
        if (haystack.indexOf(search) === -1) continue;
      }
      results.push(e);
    }
    return results;
  };

  // ── Public API: __collectPageState() ─────────────────────────────
  window.__collectPageState = function () {
    drainAppLog();

    var testidEls = document.querySelectorAll('[' + TESTID_ATTR + ']');
    var testids = [];
    for (var i = 0; i < testidEls.length; i++) {
      testids.push(testidEls[i].getAttribute(TESTID_ATTR));
    }

    var formEls = document.querySelectorAll('form');
    var forms = [];
    for (var f = 0; f < formEls.length; f++) {
      var form = formEls[f];
      var formId = form.getAttribute(TESTID_ATTR) || form.id || null;
      var fields = {};
      var inputs = form.querySelectorAll('input, select, textarea');
      for (var j = 0; j < inputs.length; j++) {
        var inp = inputs[j];
        var name = inp.name || inp.getAttribute(TESTID_ATTR) || inp.id || ('field-' + j);
        fields[name] = inp.value || '';
      }
      forms.push({ id: formId, fields: fields });
    }

    var toasts = [];
    var toastEls = document.querySelectorAll(
      '[role="alert"], .toast, .alert, [' + TESTID_ATTR + '*="toast"], [' + TESTID_ATTR + '*="alert"]'
    );
    for (var k = 0; k < toastEls.length; k++) {
      var el = toastEls[k];
      if (el.offsetParent !== null || el.style.display !== 'none') {
        var text = (el.textContent || '').trim();
        if (text) toasts.push(text);
      }
    }

    var recentErrors = [];
    for (var r = LOG.length - 1; r >= 0 && recentErrors.length < 5; r--) {
      if (LOG[r].cat === 'js.error' || LOG[r].cat === 'js.promise' || LOG[r].cat === 'react.error') {
        recentErrors.unshift(LOG[r]);
      }
    }

    return {
      url: location.href,
      title: document.title,
      mutations: mutationCount,
      logSize: LOG.length,
      testids: testids,
      forms: forms,
      toasts: toasts,
      recentErrors: recentErrors,
    };
  };

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

  // ── Public API: __getDomHtml(selector, maxDepth) ─────────────────
  // Returns truncated outer HTML of an element — safe from tsx __name issues
  window.__getDomHtml = function (selector, maxDepth) {
    maxDepth = maxDepth || 3;
    var el = document.querySelector(selector);
    if (!el) return '(element not found: ' + selector + ')';

    function trunc(node, d) {
      if (d <= 0) {
        var cc = node.children.length;
        if (cc === 0) {
          var t = (node.textContent || '').trim();
          var tag = node.tagName.toLowerCase();
          return t ? '<' + tag + '>...' + t.substring(0, 50) + '...</' + tag + '>' : '<' + tag + ' />';
        }
        return '<' + node.tagName.toLowerCase() + '>...(' + cc + ' children)...</' + node.tagName.toLowerCase() + '>';
      }
      var tag = node.tagName.toLowerCase();
      var attrs = [];
      for (var i = 0; i < node.attributes.length; i++) {
        var a = node.attributes[i];
        if (a.name === 'style' || a.name === 'class') continue;
        attrs.push(a.name + '="' + a.value + '"');
      }
      var attrStr = attrs.length > 0 ? ' ' + attrs.join(' ') : '';
      if (node.children.length === 0) {
        var t = (node.textContent || '').trim();
        if (!t) return '<' + tag + attrStr + ' />';
        return '<' + tag + attrStr + '>' + (t.length > 80 ? t.substring(0, 80) + '...' : t) + '</' + tag + '>';
      }
      var ch = [];
      for (var c = 0; c < node.children.length; c++) {
        ch.push(trunc(node.children[c], d - 1));
      }
      return '<' + tag + attrStr + '>\n' + ch.join('\n') + '\n</' + tag + '>';
    }

    return trunc(el, maxDepth);
  };

  // ── Public API: __getReactTree(selector?, depth?) ────────────────
  // Walk React/Preact fiber tree and return simplified component hierarchy
  window.__getReactTree = function (selector, maxDepth) {
    maxDepth = maxDepth || 4;
    var root;
    if (selector) {
      root = document.querySelector(selector);
    } else {
      root = document.getElementById('root');
      // If no #root, find first element with __reactContainer$ or __reactFiber$
      if (!root) {
        var all = document.querySelectorAll('*');
        for (var ri = 0; ri < all.length; ri++) {
          var rkeys = Object.keys(all[ri]);
          for (var rk = 0; rk < rkeys.length; rk++) {
            if (rkeys[rk].indexOf('__reactContainer$') === 0 || rkeys[rk].indexOf('__reactFiber$') === 0) {
              root = all[ri];
              break;
            }
          }
          if (root) break;
        }
      }
      if (!root) root = document.body;
    }
    if (!root) return '(element not found)';

    // Find React fiber key on DOM node
    function getFiber(node) {
      var keys = Object.keys(node);
      for (var i = 0; i < keys.length; i++) {
        var k = keys[i];
        // React 18+: __reactContainer$ on root, __reactFiber$ on children
        if (k.indexOf('__reactContainer$') === 0) {
          // Container stores the fiber root — walk to stateNode.current
          var container = node[k];
          if (container && container.stateNode && container.stateNode.current) {
            return container.stateNode.current;
          }
          return container;
        }
        if (k.indexOf('__reactFiber$') === 0 || k.indexOf('__reactInternalInstance$') === 0) {
          return node[k];
        }
      }
      // Preact: check _component or __v
      if (node._component) return { _preact: true, component: node._component };
      if (node.__v) return { _preact: true, vnode: node.__v };
      return null;
    }

    function fiberName(fiber) {
      if (!fiber) return '?';
      if (fiber._preact) {
        var comp = fiber.component || fiber.vnode;
        if (comp && comp.constructor) return comp.constructor.name || '(anonymous)';
        return '(preact)';
      }
      if (fiber.type) {
        if (typeof fiber.type === 'string') return fiber.type;
        return fiber.type.displayName || fiber.type.name || '(anonymous)';
      }
      return '(fiber)';
    }

    function walkFiber(fiber, depth, indent) {
      if (!fiber || depth > maxDepth) return '';
      var lines = [];
      var name = fiberName(fiber);

      // Skip internal React types
      if (name === '(fiber)' || name === '(anonymous)') {
        // Still walk children
        if (fiber.child) {
          lines.push(walkFiber(fiber.child, depth, indent));
        }
      } else {
        // Extract key props
        var propsStr = '';
        if (fiber.memoizedProps || fiber.pendingProps) {
          var props = fiber.memoizedProps || fiber.pendingProps;
          var interesting = [];
          var propKeys = Object.keys(props);
          for (var i = 0; i < propKeys.length && i < 5; i++) {
            var k = propKeys[i];
            if (k === 'children' || k === 'key' || k === 'ref') continue;
            var v = props[k];
            if (typeof v === 'string') interesting.push(k + '="' + v.substring(0, 20) + '"');
            else if (typeof v === 'number' || typeof v === 'boolean') interesting.push(k + '=' + v);
          }
          if (interesting.length) propsStr = ' ' + interesting.join(' ');
        }

        // Check error state
        var errorStr = '';
        if (fiber.memoizedState && fiber.memoizedState.error) {
          errorStr = ' ERROR: ' + fiber.memoizedState.error.message;
        }

        lines.push(indent + '<' + name + propsStr + '>' + errorStr);

        if (fiber.child && depth < maxDepth) {
          lines.push(walkFiber(fiber.child, depth + 1, indent + '  '));
        }
      }

      // Walk siblings
      if (fiber.sibling) {
        lines.push(walkFiber(fiber.sibling, depth, indent));
      }

      return lines.filter(Boolean).join('\n');
    }

    var fiber = getFiber(root);
    if (!fiber) return '(no React/Preact tree found on ' + (selector || 'root') + ')';

    return walkFiber(fiber, 0, '');
  };

  // ── Bootstrap ────────────────────────────────────────────────────
  if (!window.__GARNET_LOG__) {
    window.__GARNET_LOG__ = [];
  }
})();
