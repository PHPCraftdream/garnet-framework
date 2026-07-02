/**
 * Auto-initializing lightbox for static pages.
 * Attaches to all <a data-lightbox="..."> links.
 * Supports: Esc to close, click backdrop to close, arrow keys for gallery navigation.
 */

let overlay: HTMLDivElement | null = null;
let currentGroup: HTMLAnchorElement[] = [];
let currentIndex = 0;

function show(src: string, alt?: string): void {
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.style.cssText = 'position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.85);backdrop-filter:blur(4px);cursor:zoom-out;padding:16px;';
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) close();
        });
        document.body.appendChild(overlay);
    }

    overlay.innerHTML = '';
    overlay.style.display = 'flex';
    document.body.style.overflow = 'hidden';

    const img = document.createElement('img');
    img.src = src;
    if (alt) img.alt = alt;
    img.style.cssText = 'max-width:100%;max-height:90%;object-fit:contain;border-radius:8px;box-shadow:0 25px 50px rgba(0,0,0,0.5);cursor:default;';
    img.addEventListener('click', (e) => e.stopPropagation());
    overlay.appendChild(img);

    // Bottom bar: caption + counter
    const hasCounter = currentGroup.length > 1;
    if (alt || hasCounter) {
        const bar = document.createElement('div');
        bar.style.cssText = 'position:absolute;bottom:12px;left:50%;transform:translateX(-50%);padding:6px 16px;border-radius:6px;background:rgba(0,0,0,0.6);color:#fff;font-size:13px;max-width:80%;text-align:center;display:flex;gap:12px;align-items:center;';
        if (alt) {
            const cap = document.createElement('span');
            cap.textContent = alt;
            cap.style.cssText = 'white-space:nowrap;overflow:hidden;text-overflow:ellipsis;';
            bar.appendChild(cap);
        }
        if (hasCounter) {
            const cnt = document.createElement('span');
            cnt.textContent = `${currentIndex + 1}/${currentGroup.length}`;
            cnt.style.cssText = 'opacity:0.7;flex-shrink:0;';
            bar.appendChild(cnt);
        }
        overlay.appendChild(bar);
    }

    // Close button
    const closeBtn = document.createElement('button');
    closeBtn.innerHTML = '&#215;';
    closeBtn.style.cssText = 'position:absolute;top:12px;right:12px;width:36px;height:36px;border-radius:6px;background:rgba(255,255,255,0.15);color:#fff;font-size:20px;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;';
    closeBtn.addEventListener('click', close);
    overlay.appendChild(closeBtn);

    // Nav arrows for galleries
    if (currentGroup.length > 1) {
        const makeArrow = (text: string, dir: -1 | 1) => {
            const btn = document.createElement('button');
            btn.innerHTML = text;
            const side = dir === -1 ? 'left:12px' : 'right:12px';
            btn.style.cssText = `position:absolute;top:50%;${side};transform:translateY(-50%);width:40px;height:40px;border-radius:50%;background:rgba(255,255,255,0.15);color:#fff;font-size:22px;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;`;
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                navigate(dir);
            });
            return btn;
        };
        overlay.appendChild(makeArrow('&#8249;', -1));
        overlay.appendChild(makeArrow('&#8250;', 1));
    }
}

function navigate(dir: -1 | 1): void {
    currentIndex = (currentIndex + dir + currentGroup.length) % currentGroup.length;
    const link = currentGroup[currentIndex];
    show(link.href, link.getAttribute('data-alt') || undefined);
}

function close(): void {
    if (overlay) {
        overlay.style.display = 'none';
        overlay.innerHTML = '';
    }
    document.body.style.overflow = '';
}

function onKeyDown(e: KeyboardEvent): void {
    if (!overlay || overlay.style.display === 'none') return;
    if (e.key === 'Escape') close();
    if (e.key === 'ArrowLeft' && currentGroup.length > 1) navigate(-1);
    if (e.key === 'ArrowRight' && currentGroup.length > 1) navigate(1);
}

export function initAutoLightbox(): void {
    document.addEventListener('click', (e) => {
        const link = (e.target as HTMLElement).closest('a[data-lightbox]') as HTMLAnchorElement | null;
        if (!link) return;
        e.preventDefault();

        const group = link.getAttribute('data-lightbox') || '';
        currentGroup = Array.from(document.querySelectorAll<HTMLAnchorElement>(`a[data-lightbox="${group}"]`));
        currentIndex = currentGroup.indexOf(link);
        if (currentIndex < 0) currentIndex = 0;

        show(link.href, link.getAttribute('data-alt') || undefined);
    });

    document.addEventListener('keydown', onKeyDown);
}
