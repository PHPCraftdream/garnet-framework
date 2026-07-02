import type {LucideIcon} from 'lucide-react';
import {LayoutDashboard, Gauge, Users, Settings, User, UserX, BookOpen, BookOpenText, Calendar, Bookmark, FileText as FileTextReact, Library, CalendarCheck, Banknote, Wallet, ClipboardList, GraduationCap, MessageCircle, Mail, CircleX, Activity} from 'lucide-react';
import {createElement, Plus, FileText, Trash2} from 'lucide';

export const menuIconMap: Record<string, LucideIcon> = {
	columns: LayoutDashboard,
	people: Users,
	gear: Settings,
	person: User,
	'book-half': BookOpen,
	calendar3: Calendar,
	bookmark: Bookmark,
	'clipboard-list': FileTextReact,
	journals: Library,
	'file-earmark-text': FileTextReact,
	'calendar-check': CalendarCheck,
	'cash-stack': Banknote,
	wallet2: Wallet,
	collection: GraduationCap,
	'book-open': BookOpenText,
	'chat-dots': MessageCircle,
	'x-circle': CircleX,
	'person-x': UserX,
	envelope: Mail,
	activity: Activity,
	'layout-dashboard': LayoutDashboard,
	speedometer2: Gauge,
};

const vanillaIcons = {plus: Plus, 'file-text': FileText, 'trash-2': Trash2} as const;

export function iconSvg(name: keyof typeof vanillaIcons, size = 18): string {
	const node = vanillaIcons[name];
	if (!node) return '';
	const el = createElement(node) as unknown as SVGElement;
	el.setAttribute('width', String(size));
	el.setAttribute('height', String(size));
	return el.outerHTML;
}
