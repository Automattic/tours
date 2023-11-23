/**
 * Retrieves the translation of text.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-i18n/
 */
import { __ } from '@wordpress/i18n';

/**
 * React hook that is used to mark the block wrapper element.
 * It provides all the necessary props like the class name.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops
 */
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';

/**
 * Lets webpack process CSS, SASS or SCSS files referenced in JavaScript files.
 * Those files can contain any CSS code that gets applied to the editor.
 *
 * @see https://www.npmjs.com/package/@wordpress/scripts#using-css
 */
import './editor.scss';
import ServerSideRender from '@wordpress/server-side-render';
import { PanelBody, TextControl } from '@wordpress/components';

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @class      Edit (name)
 * @param {Object}   arg1               The argument 1.
 * @param {Object}   arg1.attributes    The attributes.
 * @param {Function} arg1.setAttributes The set attributes.
 * @return {Element} Element to render.
 */
export default function Edit( { attributes, setAttributes } ) {
	return (
		<div { ...useBlockProps() }>
			<InspectorControls key="settings">
				<PanelBody>
					<TextControl
						label={ __( 'No Tours Text', 'tour' ) }
						value={ attributes.noToursText }
						onChange={ ( newValue ) =>
							setAttributes( { noToursText: newValue } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<ServerSideRender
				block="tour/available-tours"
				attributes={ attributes }
			/>
		</div>
	);
}
