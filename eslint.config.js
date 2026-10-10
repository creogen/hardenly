/**
 * The @wordpress/scripts ESLint config, set up for the classic JSX runtime (see babel.config.js):
 * JSX compiles to createElement() and Fragment, which files import from @wordpress/element.
 */
const defaultConfig = require( '@wordpress/scripts/config/eslint.config.cjs' );

module.exports = [
	...defaultConfig,
	{
		settings: {
			react: { pragma: 'createElement', fragment: 'Fragment' },
		},
		rules: {
			'react/jsx-uses-react': 'error',
		},
	},
];
