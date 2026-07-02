import {TClassClickHandler} from '@common/Models';

/**
 * Attaches a delegated click handler scoped to a class within a container.
 *
 * @param container The container element.
 * @param className The class to match.
 * @param handle The click handler.
 * @returns An unsubscribe function.
 */
export const classClick = (container: Element, className: string, handle: TClassClickHandler): () => void => {
    const handler = (event: MouseEvent) => {
        let target: HTMLElement | null = event.target as HTMLElement;

        while (target) {
            const classList = Array.from(target.classList);
            const classMap: Record<string, true> = {};

            for (const item of classList) {
                classMap[item] = true;
            }

            if (classMap[className]) {
                handle(event, target);
                break;
            }

            if (container === target) {
                break;
            }

            target = (target.parentNode as HTMLElement) || null;
        }
    };

    container.addEventListener('click', handler);

    return () => container.removeEventListener('click', handler);
};
