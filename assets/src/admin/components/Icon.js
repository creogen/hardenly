/**
 * Small stroke icons. Decorative: the text next to them carries the meaning.
 */
import { createElement, Fragment } from '@wordpress/element';

const PATHS = {
	shield: <path d="M12 3l8 3v6c0 4.5-3.4 8.3-8 9-4.6-.7-8-4.5-8-9V6l8-3z" />,
	critical: (
		<>
			<path d="M12 3l10 18H2L12 3z" />
			<path d="M12 10v5M12 18v.5" />
		</>
	),
	high: (
		<>
			<path d="M12 3l10 18H2L12 3z" />
			<path d="M12 10v5M12 18v.5" />
		</>
	),
	medium: (
		<>
			<circle cx="12" cy="12" r="9" />
			<path d="M12 7v6M12 16v.5" />
		</>
	),
	low: (
		<>
			<circle cx="12" cy="12" r="9" />
			<path d="M12 11v6M12 7.5v.5" />
		</>
	),
	info: (
		<>
			<circle cx="12" cy="12" r="9" />
			<path d="M12 11v6M12 7.5v.5" />
		</>
	),
	pass: <path d="M5 12l5 5 9-10" />,
	down: <path d="M12 5v14M5 12l7 7 7-7" />,
	up: <path d="M12 19V5M5 12l7-7 7 7" />,
	refresh: (
		<>
			<path d="M21 12a9 9 0 1 1-3-6.7" />
			<path d="M21 3v6h-6" />
		</>
	),
};

export default function Icon( { name, size = 16 } ) {
	return (
		<svg
			className="sitewarden-icon"
			width={ size }
			height={ size }
			viewBox="0 0 24 24"
			fill="none"
			stroke="currentColor"
			strokeWidth="2.25"
			strokeLinecap="round"
			strokeLinejoin="round"
			aria-hidden="true"
			focusable="false"
		>
			{ PATHS[ name ] }
		</svg>
	);
}
