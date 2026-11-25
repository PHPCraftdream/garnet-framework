/**
 * Кнопка сохранения формы настроек.
 *
 * Один и тот же блок стоял в каждой из пяти вкладок дословно — распил это
 * и обнажил. Пять копий означают, что правка вида кнопки делается пять
 * раз, а забытая шестая копия выглядит как случайная неровность
 * интерфейса.
 */
import * as React from 'react';
import {SystemSettingsLabels} from '@common/Components/Admin/SystemSettings/SystemSettingsPage/types';

/** Renders the save control for a settings form. */
export const SaveButton: React.FC<{
    labels: SystemSettingsLabels;
    sending: boolean;
}> = ({labels, sending}) => (
    <div className="settings-save-wrapper">
        <button
            type="submit"
            className="settings-save-button"
            disabled={sending}
        >
            {sending ? labels.saving : labels.save}
        </button>
    </div>
);
