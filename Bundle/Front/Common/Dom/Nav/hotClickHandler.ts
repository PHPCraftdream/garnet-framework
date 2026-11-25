import {goTo} from '@common/Dom/Nav/GoTo';

let activeLink: HTMLElement | null = null;

/** Navigates to a link through the same-page hot navigation flow. */
export const hotClickHandler = (event: MouseEvent, element: HTMLElement): void => {
    const href = element.getAttribute('href');
    if (activeLink && activeLink !== element) activeLink.classList.remove('hot-clicked');
    activeLink = element;
    element.classList.add('hot-clicked');
    document.body.classList.add('page-leaving');

    if (!href) return;
    event.preventDefault();
    event.stopPropagation();
    goTo(href).catch(() => {
        element.classList.remove('hot-clicked');
        document.body.classList.remove('page-leaving');
        if (activeLink === element) activeLink = null;
    });
};
