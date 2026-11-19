/**
 * Пропсы конструктора форм.
 *
 * Отдельным файлом потому, что их импортирует GridTable — он собирает
 * форму через React.createElement и типизирует набор пропсов, не трогая
 * саму реализацию.
 */

import * as React from 'react';
import {IDetailsInfo} from '@common/Dom/GridTable/Models';
import {IApiSuccessResponse} from '@common/Support/Models';


export interface FormBuilderProps {
	detailsInfo: IDetailsInfo;
	data: Record<string, unknown>;
	isNew?: boolean;
	defaultValues?: Record<string, string | number>;
	onSuccess: (data: IApiSuccessResponse) => void;
	onCancel?: () => void;
	onFail?: () => void;
	/** Extra content rendered INSIDE the form, just above the submit row. */
	footer?: React.ReactNode;
	/** Called before submit; return false to block submission (caller shows its own error). */
	beforeSubmit?: () => boolean;
}

// --- Validation ---
