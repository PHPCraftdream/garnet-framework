import {TClassClickHandler} from '@common/Support/Models';

/** Sends the clicked element's target as a Yandex Metrica reachGoal event. */
export const yandexTargetClick: TClassClickHandler = (event: MouseEvent, element: HTMLElement): void => {
    const ym: Function | any = window['ym'];
    const ym_id: number | string | any = window['__YM_ID__'];

    if (!ym_id || typeof ym !== 'function' || !element.dataset.target) {
        return;
    }

    ym(ym_id, 'reachGoal', element.dataset.target)
};
