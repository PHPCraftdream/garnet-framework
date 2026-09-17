/* 02-errors.js — фрагмент внедряемого сборщика.
   Ошибки JS и перехват console.error, плюс чтение тела ответа.

   ВНИМАНИЕ: это не модуль, а часть одного замыкания. Обёртку
   (function(){'use strict'; ... })() добавляет загрузчик
   (src/sessions.ts), он же склеивает части в порядке имён.
   Поэтому здесь нет ни импортов, ни экспортов: переменные общие
   с остальными частями, и редактор законно не видит их объявлений.
   Сборщик внедряется в страницу одним скриптом через
   addInitScript, поэтому частями он может быть только так. */

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
