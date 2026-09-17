/**
 * Кнопка сохранения формы настроек.
 *
 * Один и тот же блок стоял в каждой из пяти вкладок дословно — распил это
 * и обнажил. Пять копий означают, что правка вида кнопки делается пять
 * раз, а забытая шестая копия выглядит как случайная неровность
 * интерфейса.
 */
import * as React from 'react';
import {SystemSettingsLabels} from '../types';

export const SaveButton: React.FC<{labels: SystemSettingsLabels; sending: boolean}> = ({labels, sending}) => (
    <div className="mt-6">
        <button
            type="submit"
            className="rounded-lg bg-accent px-4 py-2 font-medium text-accent-text hover:bg-accent-hover disabled:opacity-60"
            disabled={sending}
        >
            {sending ? labels.saving : labels.save}
        </button>
    </div>
);
