/* 06-page-state.js — фрагмент внедряемого сборщика.
   Снимок состояния страницы.

   ВНИМАНИЕ: это не модуль, а часть одного замыкания. Обёртку
   (function(){'use strict'; ... })() добавляет загрузчик
   (src/sessions.ts), он же склеивает части в порядке имён.
   Поэтому здесь нет ни импортов, ни экспортов: переменные общие
   с остальными частями, и редактор законно не видит их объявлений.
   Сборщик внедряется в страницу одним скриптом через
   addInitScript, поэтому частями он может быть только так. */

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
