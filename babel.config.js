/**
 * Compiles JSX to createElement() from @wordpress/element instead of the automatic runtime.
 *
 * The automatic runtime needs the `react-jsx-runtime` script, which WordPress has only since
 * 6.6; the plugin supports 6.2. Files with JSX import `createElement` from `@wordpress/element`.
 */
module.exports = ( api ) => {
	api.cache( true );
	return {
		presets: [ '@wordpress/babel-preset-default' ],
		plugins: [
			[
				'@babel/plugin-transform-react-jsx',
				{ runtime: 'classic', pragma: 'createElement', pragmaFrag: 'Fragment' },
			],
		],
	};
};
