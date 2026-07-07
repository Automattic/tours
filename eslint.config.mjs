import wordpress from '@wordpress/eslint-plugin';

export default [
	{
		ignores: [
			'**/*.min.js',
			'assets/blocks/build/**',
			'assets/js/driver-js.js',
			'**/node_modules/**',
			'**/vendor/**',
			'*.js',
		],
	},
	...wordpress.configs.recommended,
	{
		rules: {
			'computed-property-spacing': [ 'error', 'always' ],
		},
	},
];
