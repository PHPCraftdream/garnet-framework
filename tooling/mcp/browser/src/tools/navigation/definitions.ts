/**
 * Описания инструментов навигации: имена, параметры, подсказки.
 *
 * Отдельно от обработчиков намеренно: описания читает клиент MCP, и
 * правка текста подсказки не должна заставлять открывать файл с логикой.
 */

import type { ToolDef } from '../../types.ts';

export const tools: ToolDef[] = [
  {
    name: 'navigate',
    description:
      'Navigate to a URL in the specified session. Returns a diff of page state changes.',
    inputSchema: {
      type: 'object',
      properties: {
        session: { type: 'string', description: 'Session role name' },
        url: { type: 'string', description: 'URL path to navigate to (appended to base URL)' },
      },
      required: ['session', 'url'],
    },
  },
  {
    name: 'viewport',
    description:
      'Resize the session viewport to test responsive layouts. Pass an explicit width/height, ' +
      'or a device preset (mobile 390×844, tablet 768×1024, desktop 1280×800). Explicit width/height ' +
      'override the preset. Returns a page-state diff after the resize.',
    inputSchema: {
      type: 'object',
      properties: {
        session: { type: 'string', description: 'Session role name' },
        device: {
          type: 'string',
          enum: ['mobile', 'tablet', 'desktop'],
          description: 'Preset viewport. Overridden by explicit width/height.',
        },
        width: { type: 'number', description: 'Viewport width in CSS px' },
        height: { type: 'number', description: 'Viewport height in CSS px' },
      },
      required: ['session'],
    },
  },
  {
    name: 'back',
    description:
      'Navigate back in the session history (browser Back button). Returns a diff of page state changes. ' +
      'No-op (returns a notice) when there is no previous page in history.',
    inputSchema: {
      type: 'object',
      properties: {
        session: { type: 'string', description: 'Session role name' },
      },
      required: ['session'],
    },
  },
  {
    name: 'forward',
    description:
      'Navigate forward in the session history (browser Forward button). Returns a diff of page state changes. ' +
      'No-op (returns a notice) when there is no next page in history.',
    inputSchema: {
      type: 'object',
      properties: {
        session: { type: 'string', description: 'Session role name' },
      },
      required: ['session'],
    },
  },
  {
    name: 'click',
    description:
      'Click an element by its test ID. Waits for network idle then returns page state diff. ' +
      'Optionally wait for a specific network response pattern and include its body in the result.',
    inputSchema: {
      type: 'object',
      properties: {
        session: { type: 'string', description: 'Session role name' },
        testid: { type: 'string', description: 'Test ID value of the element to click' },
        waitForUrl: {
          type: 'string',
          description: 'URL substring to wait for in network response (e.g. "~createTicket"). If set, includes response body in result.',
        },
      },
      required: ['session', 'testid'],
    },
  },
  {
    name: 'fill',
    description: 'Fill a form field identified by test ID with a value.',
    inputSchema: {
      type: 'object',
      properties: {
        session: { type: 'string', description: 'Session role name' },
        testid: { type: 'string', description: 'Test ID value of the input element' },
        value: { type: 'string', description: 'Value to type into the field' },
      },
      required: ['session', 'testid', 'value'],
    },
  },
  {
    name: 'select_option',
    description: 'Select an option in a <select> element by value or label.',
    inputSchema: {
      type: 'object',
      properties: {
        session: { type: 'string', description: 'Session role name' },
        testid: { type: 'string', description: 'Test ID of the <select> element' },
        value: { type: 'string', description: 'Option value or visible text to select' },
      },
      required: ['session', 'testid', 'value'],
    },
  },
  {
    name: 'wait',
    description: 'Wait for an element with a specific test ID to appear on the page.',
    inputSchema: {
      type: 'object',
      properties: {
        session: { type: 'string', description: 'Session role name' },
        testid: { type: 'string', description: 'Test ID value to wait for' },
        timeout: { type: 'number', description: 'Max wait time in ms (default 5000)' },
      },
      required: ['session', 'testid'],
    },
  },
  {
    name: 'dialog',
    description:
      'Set up a handler for the next browser dialog (alert/confirm/prompt). ' +
      'Call this BEFORE the action that triggers the dialog.',
    inputSchema: {
      type: 'object',
      properties: {
        session: { type: 'string', description: 'Session role name' },
        action: {
          type: 'string',
          enum: ['accept', 'dismiss'],
          description: 'Whether to accept or dismiss the dialog',
        },
        promptText: {
          type: 'string',
          description: 'Text to enter for prompt dialogs (optional)',
        },
      },
      required: ['session', 'action'],
    },
  },
  {
    name: 'scroll',
    description:
      'Scroll the page or scroll to a specific element. ' +
      'Use direction for relative scroll, or testid/selector to scroll an element into view.',
    inputSchema: {
      type: 'object',
      properties: {
        session: { type: 'string', description: 'Session role name' },
        direction: {
          type: 'string',
          enum: ['up', 'down', 'top', 'bottom'],
          description: 'Scroll direction. up/down scroll by ~80% viewport height. top/bottom go to extremes.',
        },
        pixels: {
          type: 'number',
          description: 'Override scroll distance in pixels (for up/down)',
        },
        testid: {
          type: 'string',
          description: 'Scroll element with this test ID into view (overrides direction)',
        },
        selector: {
          type: 'string',
          description: 'Scroll element matching CSS selector into view (overrides direction)',
        },
      },
      required: ['session'],
    },
  },
  {
    name: 'keyboard',
    description:
      'Press a key or key combination. Examples: "Enter", "Escape", "Tab", "ArrowDown", "Control+a", "Shift+Tab".',
    inputSchema: {
      type: 'object',
      properties: {
        session: { type: 'string', description: 'Session role name' },
        key: {
          type: 'string',
          description: 'Key or combo: Enter, Escape, Tab, ArrowDown, ArrowUp, Control+a, Shift+Tab, etc.',
        },
      },
      required: ['session', 'key'],
    },
  },
  {
    name: 'hover',
    description:
      'Hover over an element by test ID. Triggers CSS :hover states, tooltips, dropdowns. Returns page diff.',
    inputSchema: {
      type: 'object',
      properties: {
        session: { type: 'string', description: 'Session role name' },
        testid: { type: 'string', description: 'Test ID of the element to hover' },
      },
      required: ['session', 'testid'],
    },
  },
  {
    name: 'upload',
    description:
      'Set files on a <input type="file"> element (simulates a file-picker selection). ' +
      'Target the input by testid or CSS selector. Pass one or more file paths. ' +
      'Returns a page-state diff after the files are set.',
    inputSchema: {
      type: 'object',
      properties: {
        session: { type: 'string', description: 'Session role name' },
        testid: { type: 'string', description: 'Test ID of the <input type="file">' },
        selector: {
          type: 'string',
          description: 'CSS selector of the <input type="file"> (used when testid is not set)',
        },
        files: {
          type: 'array',
          items: { type: 'string' },
          description: 'Absolute path(s) to the file(s) to upload',
        },
      },
      required: ['session', 'files'],
    },
  },
];
