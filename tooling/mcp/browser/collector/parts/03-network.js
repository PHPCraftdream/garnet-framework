/* 03-network.js — фрагмент внедряемого сборщика.
   Перехват fetch и XHR.

   ВНИМАНИЕ: это не модуль, а часть одного замыкания. Обёртку
   (function(){'use strict'; ... })() добавляет загрузчик
   (src/sessions.ts), он же склеивает части в порядке имён.
   Поэтому здесь нет ни импортов, ни экспортов: переменные общие
   с остальными частями, и редактор законно не видит их объявлений.
   Сборщик внедряется в страницу одним скриптом через
   addInitScript, поэтому частями он может быть только так. */

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
