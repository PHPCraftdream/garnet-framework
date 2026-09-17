import * as React from 'react';
import {createRoot, Root} from 'react-dom/client';
import {DomObserver} from '@common/Dom/El/DomObserver';
import {ErrorBoundary} from '@common/Components/Layout/ErrorBoundary';
import {reportHandledError} from '@common/Support/Errors/JsErrorReporter';

type LazyImport<P> = () => Promise<{ default: React.ComponentType<P> } | { [key: string]: React.ComponentType<P> }>;

/** Одна повторная попытка импорта: сорванный запрос чанка — обычное дело. */
const RETRY_DELAY_MS = 500;

/**
 * Похоже ли значение на тип, который React согласится отрендерить.
 *
 * Функция — компонент или хук-обёртка; объект с `$$typeof` — memo/forwardRef/
 * lazy. Всё остальное (строка из модуля констант, число, массив) React примет
 * в `createElement` молча, а упадёт уже в рендере — «element type is invalid»,
 * то есть React #130.
 */
function isComponentType(value: unknown): boolean {
    return typeof value === 'function'
        || (typeof value === 'object' && value !== null && '$$typeof' in (value as Record<string, unknown>));
}

/**
 * Выбрать из модуля то, что действительно можно рендерить.
 *
 * Прежний вариант заканчивался на `Object.values(mod)[0]` — первое значение
 * модуля, каким бы оно ни было. Проверка `if (Component)` от этого не спасает:
 * непустая строка тоже truthy, и тогда островок валится в React #130 вместо
 * внятной ошибки. Порядок предпочтений сохранён: явное имя экспорта, `default`,
 * затем первый подходящий экспорт.
 */
function pickComponent<P>(mod: object, exportName?: string): React.ComponentType<P> | null {
    const named = exportName ? (mod as Record<string, unknown>)[exportName] : undefined;
    const candidates: unknown[] = [named, (mod as Record<string, unknown>).default, ...Object.values(mod)];

    for (const candidate of candidates) {
        if (isComponentType(candidate)) {
            return candidate as React.ComponentType<P>;
        }
    }

    return null;
}

/**
 * Register a React island that mounts LAZILY when a DOM element with the given class appears.
 *
 * The JS chunk is loaded ONLY when DomObserver detects the element — nothing is bundled upfront.
 * Every island is wrapped in ErrorBoundary for crash resilience.
 *
 * Usage:
 *   createIsland({className: 'my-init', lazy: () => import('./MyIsland'), exportName: 'MyIsland'})
 *
 * Что делает эта фабрика, когда чанк НЕ пришёл. Раньше — ничего: `.then()`
 * без `.catch()`, промис отклонялся в пустоту, островок не монтировался, на
 * экране оставалась серверная разметка без какого-либо сообщения, повтора не
 * было. Под burst'ом запросов шейред-хостинг именно так и отвечает: документ
 * отдаёт, а css/js срывает — страница остаётся голым каркасом (D-221, D-224).
 * ErrorBoundary тут не помогает: он оборачивает УЖЕ отрендеренный компонент, а
 * до рендера дело не доходит.
 *
 * Поэтому: одна повторная попытка импорта, а если и она не удалась — запись в
 * тот же канал, куда уходят прочие клиентские ошибки, с именем островка. Уронить
 * страницу фабрика не должна ни при какой сети, поэтому наружу по-прежнему
 * ничего не бросается.
 */
export function createIsland<P>(config: {
    className: string;
    lazy: LazyImport<P>;
    exportName?: string;
}): void {
    const observer = DomObserver.init();

    observer.defineAddClassHandler(config.className, (element: HTMLElement) => {
        let props = {} as P;

        const propsAttr = element.getAttribute('data-props');
        if (propsAttr) {
            try {
                props = JSON.parse(propsAttr) as P;
            } catch {
                // ignore parse errors
            }
        }

        const retryOnce = (first: unknown): Promise<object> => new Promise<void>(
            (resolve) => setTimeout(resolve, RETRY_DELAY_MS),
        ).then(() => config.lazy()).catch((second: unknown) => {
            // Наружу уходит ошибка ВТОРОЙ попытки, но первая тоже сказала бы
            // то же самое — сообщение ниже называет островок, а не чанк.
            throw second ?? first;
        });

        config.lazy()
            .catch(retryOnce)
            .then((mod) => {
                const Component = pickComponent<P>(mod, config.exportName);

                if (!Component) {
                    throw new Error(
                        `module has no renderable export (exportName: ${config.exportName ?? 'default'})`,
                    );
                }

                // ErrorBoundary приезжает статическим импортом, но в сломанном
                // графе чанков и он может оказаться undefined — а это ровно тот
                // React #130, от которого он призван защищать. Тогда монтируем
                // островок без обёртки: без неё он живёт хуже, чем с ней, но
                // лучше, чем не живёт вовсе.
                const Boundary = isComponentType(ErrorBoundary) ? ErrorBoundary : React.Fragment;
                const root: Root = createRoot(element);
                root.render(<Boundary><Component {...props} /></Boundary>);
                observer.registerElementRemoval(element, () => {
                    root.unmount();
                });
            })
            .catch((e: unknown) => {
                const message = `island '${config.className}' did not mount`;
                reportHandledError(message, e);
                // eslint-disable-next-line no-console
                console.error(`[garnet] ${message}`, e);
            });
    });
}
