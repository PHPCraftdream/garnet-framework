import type { PageSnapshot, DiffResult, LogEntry } from './types.ts';

/**
 * Compute a structured diff between two page snapshots.
 * If `prev` is null, everything in `next` is treated as "new".
 */
export function diffStates(prev: PageSnapshot | null, next: PageSnapshot): DiffResult {
  const result: DiffResult = {
    addedTestids: [],
    removedTestids: [],
    addedForms: [],
    removedForms: [],
    changedFields: [],
    newToasts: [],
    newErrors: [],
    mutations: next.mutations,
    networkSummary: '',
  };

  // URL change
  if (prev && prev.url !== next.url) {
    result.url = { from: prev.url, to: next.url };
  } else if (!prev) {
    result.url = { from: '', to: next.url };
  }

  // Title change
  if (prev && prev.title !== next.title) {
    result.title = { from: prev.title, to: next.title };
  }

  // Testid diff
  const prevTestids = new Set(prev?.testids ?? []);
  const nextTestids = new Set(next.testids);

  for (const tid of nextTestids) {
    if (!prevTestids.has(tid)) result.addedTestids.push(tid);
  }
  for (const tid of prevTestids) {
    if (!nextTestids.has(tid)) result.removedTestids.push(tid);
  }

  // Form diff
  const prevFormIds = new Set((prev?.forms ?? []).map(f => f.id));
  const nextFormIds = new Set(next.forms.map(f => f.id));

  for (const fid of nextFormIds) {
    if (fid && !prevFormIds.has(fid)) result.addedForms.push(fid);
  }
  for (const fid of prevFormIds) {
    if (fid && !nextFormIds.has(fid)) result.removedForms.push(fid);
  }

  // Field value changes (for forms present in both snapshots)
  if (prev) {
    const prevFormMap = new Map(prev.forms.map(f => [f.id, f.fields]));
    for (const form of next.forms) {
      if (!form.id) continue;
      const prevFields = prevFormMap.get(form.id);
      if (!prevFields) continue;
      for (const [field, value] of Object.entries(form.fields)) {
        const oldVal = prevFields[field];
        if (oldVal !== undefined && oldVal !== value) {
          result.changedFields.push({ form: form.id, field, from: oldVal, to: value });
        }
      }
    }
  }

  // New toasts
  const prevToasts = new Set(prev?.toasts ?? []);
  for (const toast of next.toasts) {
    if (!prevToasts.has(toast)) result.newToasts.push(toast);
  }

  // New errors (compare by timestamp — errors with t > max prev error t)
  if (prev) {
    const prevMaxT = prev.recentErrors.length > 0
      ? Math.max(...prev.recentErrors.map(e => e.t))
      : 0;
    result.newErrors = next.recentErrors.filter(e => e.t > prevMaxT);
  } else {
    result.newErrors = next.recentErrors;
  }

  return result;
}

/**
 * Format a DiffResult as compact human-readable text.
 */
export function formatDiff(diff: DiffResult): string {
  const lines: string[] = [];

  if (diff.url) {
    if (diff.url.from) {
      lines.push(`URL: ${diff.url.from} -> ${diff.url.to}`);
    } else {
      lines.push(`URL: ${diff.url.to}`);
    }
  }

  if (diff.title) {
    lines.push(`Title: "${diff.title.from}" -> "${diff.title.to}"`);
  }

  if (diff.addedTestids.length > 0) {
    lines.push(`+testids: ${diff.addedTestids.join(', ')}`);
  }
  if (diff.removedTestids.length > 0) {
    lines.push(`-testids: ${diff.removedTestids.join(', ')}`);
  }

  if (diff.addedForms.length > 0) {
    lines.push(`+forms: ${diff.addedForms.join(', ')}`);
  }
  if (diff.removedForms.length > 0) {
    lines.push(`-forms: ${diff.removedForms.join(', ')}`);
  }

  for (const cf of diff.changedFields) {
    lines.push(`field ${cf.form}.${cf.field}: "${cf.from}" -> "${cf.to}"`);
  }

  for (const toast of diff.newToasts) {
    lines.push(`toast: ${toast}`);
  }

  for (const err of diff.newErrors) {
    lines.push(`ERROR [${err.cat}]: ${err.msg}`);
  }

  if (diff.networkSummary) {
    lines.push(`net: ${diff.networkSummary}`);
  }

  lines.push(`mutations: ${diff.mutations}`);

  if (lines.length === 1) {
    // Only mutations line — nothing changed
    lines.unshift('(no visible changes)');
  }

  return lines.join('\n');
}
