/* 01-buffer.js — фрагмент внедряемого сборщика.
   Настройки и кольцевой буфер: куда всё складывается.

   ВНИМАНИЕ: это не модуль, а часть одного замыкания. Обёртку
   (function(){'use strict'; ... })() добавляет загрузчик
   (src/sessions.ts), он же склеивает части в порядке имён.
   Поэтому здесь нет ни импортов, ни экспортов: переменные общие
   с остальными частями, и редактор законно не видит их объявлений.
   Сборщик внедряется в страницу одним скриптом через
   addInitScript, поэтому частями он может быть только так. */

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
