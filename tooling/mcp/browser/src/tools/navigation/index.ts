/**
 * Инструменты навигации: разбор имени и передача нужному обработчику.
 *
 * Файл называется index, чтобы импорт по пути tools/navigation.ts остался
 * рабочим — его знает реестр инструментов сервера.
 *
 * Раньше это был один файл на 623 строки.
 */

import type { SessionManager } from '../../sessions.ts';
import type { ToolResult } from '../../types.ts';
import { textResult } from '../../utils.ts';
import { handleNavigate, handleViewport, handleBack, handleForward } from './move.ts';
import { handleClick, handleFill, handleSelectOption, handleKeyboard, handleHover, handleUpload } from './input.ts';
import { handleWait, handleDialog, handleScroll } from './waiting.ts';

export { tools } from './definitions.ts';

export async function handle(
  name: string,
  args: Record<string, unknown>,
  sm: SessionManager,
): Promise<ToolResult> {
  try {
    switch (name) {
      case 'navigate':
        return await handleNavigate(args, sm);
      case 'viewport':
        return await handleViewport(args, sm);
      case 'back':
        return await handleBack(args, sm);
      case 'forward':
        return await handleForward(args, sm);
      case 'click':
        return await handleClick(args, sm);
      case 'fill':
        return await handleFill(args, sm);
      case 'select_option':
        return await handleSelectOption(args, sm);
      case 'wait':
        return await handleWait(args, sm);
      case 'dialog':
        return await handleDialog(args, sm);
      case 'scroll':
        return await handleScroll(args, sm);
      case 'keyboard':
        return await handleKeyboard(args, sm);
      case 'hover':
        return await handleHover(args, sm);
      case 'upload':
        return await handleUpload(args, sm);
      default:
        return textResult(`Unknown tool: ${name}`, true);
    }
  } catch (err) {
    return textResult(`Error: ${err instanceof Error ? err.message : String(err)}`, true);
  }
}
