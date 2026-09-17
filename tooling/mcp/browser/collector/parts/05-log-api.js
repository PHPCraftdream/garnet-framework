/* 05-log-api.js — фрагмент внедряемого сборщика.
   Публичный доступ к журналу: запрос, очистка, хвост.

   ВНИМАНИЕ: это не модуль, а часть одного замыкания. Обёртку
   (function(){'use strict'; ... })() добавляет загрузчик
   (src/sessions.ts), он же склеивает части в порядке имён.
   Поэтому здесь нет ни импортов, ни экспортов: переменные общие
   с остальными частями, и редактор законно не видит их объявлений.
   Сборщик внедряется в страницу одним скриптом через
   addInitScript, поэтому частями он может быть только так. */

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
