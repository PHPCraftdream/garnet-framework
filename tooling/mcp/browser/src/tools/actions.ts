import type { SessionManager } from '../sessions.ts';
import type { ToolDef, ToolResult } from '../types.ts';
import { textResult, formatTimestamp } from '../utils.ts';

export const tools: ToolDef[] = [
  {
    name: 'act',
    description:
      'Execute a sequence of browser actions and return the final diff. ' +
      'Each step can target a different session for cross-role scenarios. ' +
      'Actions: fill, click, select, wait, navigate, describe, assert_text, scroll, key, hover.',
    inputSchema: {
      type: 'object',
      properties: {
        session: { type: 'string', description: 'Default session for steps that omit session' },
        steps: {
          type: 'array',
          description: 'Array of action steps to execute sequentially',
          items: {
            type: 'object',
            properties: {
              action: {
                type: 'string',
                enum: ['fill', 'click', 'select', 'wait', 'navigate', 'describe', 'assert_text', 'scroll', 'key', 'hover'],
                description: 'Action type',
              },
              session: { type: 'string', description: 'Session for this step (overrides default)' },
              testid: { type: 'string', description: 'Test ID (for fill, click, select, wait)' },
              value: { type: 'string', description: 'Value (for fill, select, assert_text)' },
              url: { type: 'string', description: 'URL (for navigate)' },
              timeout: { type: 'number', description: 'Timeout in ms (for wait, default 5000)' },
              selector: { type: 'string', description: 'CSS selector (for assert_text)' },
            },
            required: ['action'],
          },
        },
      },
      required: ['steps'],
    },
  },
  {
    name: 'batch_fill',
    description:
      'Fill multiple form fields at once. Returns a single diff after all fields are filled.',
    inputSchema: {
      type: 'object',
      properties: {
        session: { type: 'string', description: 'Session role name' },
        fields: {
          type: 'array',
          description: 'Array of {testid, value} pairs to fill',
          items: {
            type: 'object',
            properties: {
              testid: { type: 'string', description: 'Test ID of the input element' },
              value: { type: 'string', description: 'Value to type into the field' },
            },
            required: ['testid', 'value'],
          },
        },
      },
      required: ['session', 'fields'],
    },
  },
];

export async function handle(
  name: string,
  args: Record<string, unknown>,
  sm: SessionManager,
): Promise<ToolResult> {
  try {
    switch (name) {
      case 'act':
        return await handleAct(args, sm);
      case 'batch_fill':
        return await handleBatchFill(args, sm);
      default:
        return textResult(`Unknown tool: ${name}`, true);
    }
  } catch (err) {
    return textResult(`Error: ${err instanceof Error ? err.message : String(err)}`, true);
  }
}

interface ActionStep {
  action: string;
  session?: string;
  testid?: string;
  value?: string;
  url?: string;
  timeout?: number;
  selector?: string;
}

async function handleAct(
  args: Record<string, unknown>,
  sm: SessionManager,
): Promise<ToolResult> {
  const defaultSession = args.session as string | undefined;
  const steps = args.steps as ActionStep[];
  if (!steps?.length) {
    return textResult('Missing required parameter: steps[]', true);
  }

  const config = sm.getConfig();
  const executed: string[] = [];
  // Track which sessions were touched for final summary
  const touchedSessions = new Set<string>();

  for (let i = 0; i < steps.length; i++) {
    const step = steps[i];
    const session = step.session || defaultSession;
    if (!session) {
      return textResult(`step ${i + 1}: no session specified and no default session`, true);
    }

    const page = sm.getPage(session);
    touchedSessions.add(session);
    const prefix = touchedSessions.size > 1 ? `[${session}] ` : '';
    const stepLabel = `step ${i + 1}/${steps.length}: ${prefix}${step.action}`;

    try {
      switch (step.action) {
        case 'fill': {
          if (!step.testid || step.value === undefined) {
            return textResult(`${stepLabel} — missing testid or value`, true);
          }
          await page.fill(sm.testidSelector(step.testid!), step.value, { timeout: 5000 });
          executed.push(`${prefix}fill ${step.testid}="${step.value}"`);
          break;
        }
        case 'click': {
          if (!step.testid) {
            return textResult(`${stepLabel} — missing testid`, true);
          }
          await page.click(sm.testidSelector(step.testid!), { timeout: 10000 });
          await sm.waitForIdle(session);
          executed.push(`${prefix}click ${step.testid}`);
          break;
        }
        case 'select': {
          if (!step.testid || !step.value) {
            return textResult(`${stepLabel} — missing testid or value`, true);
          }
          const selector = sm.testidSelector(step.testid!);
          await page.selectOption(selector, { value: step.value }, { timeout: 5000 }).catch(
            () => page.selectOption(selector, { label: step.value! }, { timeout: 5000 }),
          );
          executed.push(`${prefix}select ${step.testid}="${step.value}"`);
          break;
        }
        case 'wait': {
          if (!step.testid) {
            return textResult(`${stepLabel} — missing testid`, true);
          }
          const timeout = step.timeout ?? 5000;
          await page.waitForSelector(sm.testidSelector(step.testid!), { timeout });
          executed.push(`${prefix}wait ${step.testid}`);
          break;
        }
        case 'navigate': {
          if (!step.url) {
            return textResult(`${stepLabel} — missing url`, true);
          }
          const fullUrl = step.url.startsWith('http') ? step.url : config.baseUrl + step.url;
          await page.goto(fullUrl, { waitUntil: 'domcontentloaded' });
          await sm.waitForIdle(session);
          executed.push(`${prefix}navigate ${step.url}`);
          break;
        }
        case 'describe': {
          const desc = await page.evaluate(() => (window as any).__describePage()) as string;
          executed.push(`${prefix}describe → ${desc.split('\n').slice(0, 3).join(' | ')}`);
          break;
        }
        case 'assert_text': {
          const sel = step.selector || step.testid ? sm.testidSelector(step.testid!) : 'body';
          const expected = step.value || '';
          const text = await page.locator(sel).innerText({ timeout: 5000 });
          if (text.includes(expected)) {
            executed.push(`${prefix}assert OK: "${expected}" found`);
          } else {
            const snippet = text.length > 100 ? text.substring(0, 100) + '...' : text;
            return textResult(
              `${stepLabel}: ASSERT FAILED — "${expected}" not found in "${snippet}"\nCompleted: ${executed.join(' → ')}`,
              true,
            );
          }
          break;
        }
        case 'scroll': {
          if (step.testid) {
            await page.locator(sm.testidSelector(step.testid!)).first().scrollIntoViewIfNeeded({ timeout: 5000 });
          } else if (step.selector) {
            await page.locator(step.selector).first().scrollIntoViewIfNeeded({ timeout: 5000 });
          } else {
            const dir = step.value || 'down';
            if (dir === 'top') await page.evaluate(() => window.scrollTo(0, 0));
            else if (dir === 'bottom') await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
            else {
              const px = step.timeout || 500; // reuse timeout field for pixels in act
              await page.mouse.wheel(0, dir === 'up' ? -px : px);
            }
          }
          await page.waitForTimeout(200);
          executed.push(`${prefix}scroll ${step.testid || step.selector || step.value || 'down'}`);
          break;
        }
        case 'key': {
          if (!step.value) return textResult(`${stepLabel} — missing value (key name)`, true);
          await page.keyboard.press(step.value);
          await sm.waitForIdle(session);
          executed.push(`${prefix}key ${step.value}`);
          break;
        }
        case 'hover': {
          if (!step.testid) return textResult(`${stepLabel} — missing testid`, true);
          await page.hover(sm.testidSelector(step.testid!), { timeout: 5000 });
          await page.waitForTimeout(300);
          executed.push(`${prefix}hover ${step.testid}`);
          break;
        }
        default:
          return textResult(`${stepLabel} — unknown action: ${step.action}`, true);
      }
    } catch (err) {
      const msg = err instanceof Error ? err.message : String(err);
      // Return partial progress + error
      const diffParts: string[] = [];
      for (const s of touchedSessions) {
        try {
          const { formatted } = await sm.captureAndDiff(s);
          diffParts.push(touchedSessions.size > 1 ? `[${s}] ${formatted}` : formatted);
        } catch { /* skip */ }
      }
      return textResult(
        `Failed at ${stepLabel}: ${msg}\nCompleted: ${executed.join(' → ')}\n${diffParts.join('\n')}`,
        true,
      );
    }
  }

  // Capture final diff for all touched sessions
  const diffParts: string[] = [];
  for (const s of touchedSessions) {
    const { formatted } = await sm.captureAndDiff(s);
    diffParts.push(touchedSessions.size > 1 ? `--- [${s}] ---\n${formatted}` : formatted);
  }

  return textResult(`${executed.join(' → ')}\n${diffParts.join('\n')}`);
}

async function handleBatchFill(
  args: Record<string, unknown>,
  sm: SessionManager,
): Promise<ToolResult> {
  const session = args.session as string;
  const fields = args.fields as { testid: string; value: string }[];
  if (!session || !fields?.length) {
    return textResult('Missing required parameters: session, fields[]', true);
  }

  const page = sm.getPage(session);
  const filled: string[] = [];

  for (const field of fields) {
    await page.fill(sm.testidSelector(field.testid), field.value, { timeout: 5000 });
    filled.push(`${field.testid}="${field.value}"`);
  }

  const { formatted } = await sm.captureAndDiff(session);
  return textResult(`Filled: ${filled.join(', ')}\n${formatted}`);
}
