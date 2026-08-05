import wordpress from '@wordpress/eslint-plugin';

export default [
	{
		ignores: [
			'**/*.min.js',
			'assets/blocks/build/**',
			'**/node_modules/**',
			'assets/js/jquery.webui-popover.js',
			'assets/js/driver-js.js',
			'**/vendor/**',
			'/*.js',
		],
	},
	...wordpress.configs.recommended,
	{
		rules: {
			'computed-property-spacing': [ 'error', 'always' ],
		},
	},
];
