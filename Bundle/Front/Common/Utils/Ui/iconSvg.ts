import {createElement, Plus, FileText, Trash2} from 'lucide';

const vanillaIcons = {plus: Plus, 'file-text': FileText, 'trash-2': Trash2} as const;

/**
 * Render a vanilla Lucide icon as an SVG string.
 *
 * @param name - Name of the icon in the vanilla icon map.
 * @param size - Width and height of the SVG in pixels.
 * @returns The rendered SVG markup, or an empty string for an unknown icon.
 */
export function iconSvg(name: keyof typeof vanillaIcons, size = 18): string {
	const node = vanillaIcons[name];
	if (!node) return '';
	const el = createElement(node) as unknown as SVGElement;
	el.setAttribute('width', String(size));
	el.setAttribute('height', String(size));
	return el.outerHTML;
}
