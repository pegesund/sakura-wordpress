/**
 * Registers a new block provided a unique name and an object defining its behavior.
 *
 * @see https://developer.wordpress.org/block-editor/developers/block-api/#registering-a-block
 */
import { registerBlockType } from '@wordpress/blocks';

import { SelectControl } from '@wordpress/components';

/**
 * Retrieves the translation of text.
 *
 * @see https://developer.wordpress.org/block-editor/packages/packages-i18n/
 */
import { __ } from '@wordpress/i18n';

/**
 * Lets webpack process CSS, SASS or SCSS files referenced in JavaScript files.
 * All files containing `style` keyword are bundled together. The code used
 * gets applied both to the front of your site and to the editor.
 *
 * @see https://www.npmjs.com/package/@wordpress/scripts#using-css
 */
import './style.scss';
import './editor.scss';

/**
 * React hook that is used to mark the block wrapper element.
 * It provides all the necessary props like the class name.
 *
 * @see https://developer.wordpress.org/block-editor/packages/packages-block-editor/#useBlockProps
 */
import { useBlockProps } from '@wordpress/block-editor';

/**
 * Every block starts by registering a new block type definition.
 *
 * @see https://developer.wordpress.org/block-editor/developers/block-api/#registering-a-block
 */
registerBlockType( 'sakura-network/sakura-network', {
		/**
		 * @see https://make.wordpress.org/core/2020/11/18/block-api-version-2/
		 */
		apiVersion: 2,

		/**
		 * This is the display title for your block, which can be translated with `i18n` functions.
		 * The block inserter will show this name.
		 */
		title: __( 'Sakura Network', 'sakura-network' ),

		/**
		 * This is a short description for your block, can be translated with `i18n` functions.
		 * It will be shown in the Block Tab in the Settings Sidebar.
		 */
		description: __(
				'Insert Sakura Network into your post or page.',
				'sakura-network'
		),

		/**
		 * Blocks are grouped into categories to help users browse and discover them.
		 * The categories provided by core are `text`, `media`, `design`, `widgets`, and `embed`.
		 */
		category: 'widgets',

		/**
		 * An icon property should be specified to make it easier to identify a block.
		 * These can be any of WordPress’ Dashicons, or a custom svg element.
		 */
		icon: 'networking',

		/**
		 * Optional block extended support features.
		 */
		// supports: {
		// 		// Removes support for an HTML mode.
		// 		html: false,
		// },
		attributes: {
				template: {
						type: 'string',
						default: 'Default',
				},
		},
		edit: function( props ) {
				// return "<div>Sakrua Network</div>";
				return		<SelectControl
				label={ __( 'Select widget template:' ) }
				value={ props.attributes.template }
				onChange={( template ) => { props.setAttributes ({template: template })}}
				options={ [
						// { value: null, label: 'Select a User', disabled: true },
						{ value: 'Default', label: 'Default' },
						{ value: 'Confettibird', label: 'Confettibird' },
				] }
/>
    },

		save: function ( props ) {
	return null;
}
} );
