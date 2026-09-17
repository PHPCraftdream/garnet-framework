/* 08-dom-react.js — фрагмент внедряемого сборщика.
   Выдача HTML поддерева и дерева компонентов React/Preact.

   ВНИМАНИЕ: это не модуль, а часть одного замыкания. Обёртку
   (function(){'use strict'; ... })() добавляет загрузчик
   (src/sessions.ts), он же склеивает части в порядке имён.
   Поэтому здесь нет ни импортов, ни экспортов: переменные общие
   с остальными частями, и редактор законно не видит их объявлений.
   Сборщик внедряется в страницу одним скриптом через
   addInitScript, поэтому частями он может быть только так. */

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
