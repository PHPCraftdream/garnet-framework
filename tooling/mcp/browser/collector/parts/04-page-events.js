/* 04-page-events.js — фрагмент внедряемого сборщика.
   Навигация, изменения DOM, отправка форм и разбор маски.

   ВНИМАНИЕ: это не модуль, а часть одного замыкания. Обёртку
   (function(){'use strict'; ... })() добавляет загрузчик
   (src/sessions.ts), он же склеивает части в порядке имён.
   Поэтому здесь нет ни импортов, ни экспортов: переменные общие
   с остальными частями, и редактор законно не видит их объявлений.
   Сборщик внедряется в страницу одним скриптом через
   addInitScript, поэтому частями он может быть только так. */

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
