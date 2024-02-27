/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

export default {
	lock: {
		type: 'object',
		default: {
			move: false,
			remove: true,
		},
	},
	className: {
		type: 'string',
		default: '',
	},
};
