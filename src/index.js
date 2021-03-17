/**
 * Registers a new block provided a unique name and an object defining its behavior.
 *
 * @see https://developer.wordpress.org/block-editor/developers/block-api/#registering-a-block
 */
import { registerBlockType } from '@wordpress/blocks';

import { SelectControl, ColorPalette } from '@wordpress/components';

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

function Edit( props ) {
		if (_sakura_networks.status != "success") {
				return <h3> Failed to get your networks list from Sakura Server! </h3>
		}

		var networks_options =
				[{value: 0, label: 'All'}].concat(
						_sakura_networks.networks.map( network =>
								{ var o = new Object();
									o.value = network.id;
									o.label = network.name.en;
									return o;}));
		var bgcolor_options = [{value: '', label: 'Default'},
													 {value: '#f7edec', label: 'Red'},
													 {value: '#97a7a9', label: 'Blue'}];

		var font_options = [{value: '', label: 'Default'},
													 {value: 'Montserrat', label: 'Montserrat'},
												{value: 'Avenir LT W04_65 Medium1475536', label: 'Avenir'},
												{value: 'Vesper Libre', label: 'Vesper Libre'},
													 {value: 'IBM Plex Sans', label: 'IBM Plex Sans'}];

		return (
				[
								<SelectControl
						label={ __( 'Target network:' ) }
						value={ props.attributes.network }
						onChange={( network ) => { props.setAttributes ({network: network })}}
						options={ networks_options }
								/>,
								<SelectControl
						label={ __( 'Background color:' ) }
						value={ props.attributes.bgcolor }
						onChange={( bgcolor ) => { props.setAttributes ({bgcolor: bgcolor })}}
						options={ bgcolor_options }
								/>,
								<SelectControl
						label={ __( 'Widget font:' ) }
						value={ props.attributes.font }
						onChange={( font ) => { props.setAttributes ({font: font })}}
						options={ font_options }
								/>,
				]);
}

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
				network: {
						type: 'string',
						default: '0',
				},
				bgcolor: {
						type: 'string',
						default: '',
				},
				font: {
						type: 'string',
						default: '',
				},
		},
		edit: Edit,

		save: function ( props ) {
	return null;
}
} );
