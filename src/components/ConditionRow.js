import { __ } from '@wordpress/i18n';
import { SelectControl, Button } from '@wordpress/components';
import { applyFilters } from '@wordpress/hooks';

import ConditionValueControl from './ConditionValueControl';
import { TrashIcon } from './icons';

const TYPE_OPTIONS = [
	{ label: __( 'Role', 'wp-login-logout-redirect' ), value: 'role' },
	{ label: __( 'Specific user', 'wp-login-logout-redirect' ), value: 'user' },
	{
		label: __( 'Capability', 'wp-login-logout-redirect' ),
		value: 'capability',
	},
];

/**
 * A single condition row inside a rule: type selector + value selector.
 *
 * @param {Object}   props
 * @param {Object}   props.condition The condition object.
 * @param {Function} props.onChange  Receives a partial patch for the condition.
 * @param {Function} props.onRemove  Removes this condition.
 */
const ConditionRow = ( { condition, onChange, onRemove } ) => {
	// Pro extensions register additional match types here.
	const typeOptions = applyFilters( 'wplalr.conditionTypes', TYPE_OPTIONS );

	return (
		<div className="wplalr-condition-row">
			<div className="wplalr-condition-type">
				<SelectControl
					label={ __( 'When', 'wp-login-logout-redirect' ) }
					value={ condition.type }
					options={ typeOptions }
					onChange={ ( type ) => onChange( { type, values: [] } ) }
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</div>
			<div className="wplalr-condition-values">
				<ConditionValueControl
					type={ condition.type }
					values={ condition.values ?? [] }
					onChange={ ( values ) => onChange( { values } ) }
				/>
			</div>
			<Button
				className="wplalr-condition-remove"
				icon={ <TrashIcon /> }
				label={ __( 'Remove condition', 'wp-login-logout-redirect' ) }
				onClick={ onRemove }
				isDestructive
			/>
		</div>
	);
};

export default ConditionRow;
