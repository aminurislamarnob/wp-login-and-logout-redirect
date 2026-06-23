import { __ } from '@wordpress/i18n';
import {
	Card,
	CardBody,
	Button,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';

import ConditionRow from './ConditionRow';
import { emptyCondition } from '../utils';
import { DragHandleIcon, PlusIcon, TrashIcon } from './icons';

/**
 * A single, sortable redirect rule card.
 *
 * @param {Object}   props
 * @param {Object}   props.rule     The rule object.
 * @param {Function} props.onChange Receives a partial patch for the rule.
 * @param {Function} props.onRemove Removes this rule.
 */
const RuleCard = ( { rule, onChange, onRemove } ) => {
	const {
		attributes,
		listeners,
		setNodeRef,
		transform,
		transition,
		isDragging,
	} = useSortable( { id: rule.id } );

	const style = {
		transform: CSS.Transform.toString( transform ),
		transition,
		opacity: isDragging ? 0.6 : 1,
	};

	const conditions = rule.conditions ?? [];

	const updateCondition = ( index, patch ) => {
		const next = conditions.map( ( condition, i ) =>
			i === index ? { ...condition, ...patch } : condition
		);
		onChange( { conditions: next } );
	};

	const removeCondition = ( index ) => {
		onChange( {
			conditions: conditions.filter( ( _, i ) => i !== index ),
		} );
	};

	const addCondition = () => {
		onChange( { conditions: [ ...conditions, emptyCondition() ] } );
	};

	const homeUrl = window.wplalrAdmin?.homeUrl ?? '';

	return (
		<div ref={ setNodeRef } style={ style } className="wplalr-rule-card">
			<Card>
				<CardBody>
					<div className="wplalr-rule-header">
						<Button
							className="wplalr-rule-drag"
							icon={ <DragHandleIcon /> }
							label={ __(
								'Reorder rule',
								'wp-login-logout-redirect'
							) }
							{ ...attributes }
							{ ...listeners }
						/>
						<TextControl
							className="wplalr-rule-label"
							value={ rule.label ?? '' }
							placeholder={ __(
								'Rule name (optional)',
								'wp-login-logout-redirect'
							) }
							onChange={ ( label ) => onChange( { label } ) }
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
						<ToggleControl
							label={ __(
								'Enabled',
								'wp-login-logout-redirect'
							) }
							checked={ !! rule.enabled }
							onChange={ ( enabled ) => onChange( { enabled } ) }
							__nextHasNoMarginBottom
						/>
						<Button
							icon={ <TrashIcon /> }
							label={ __(
								'Delete rule',
								'wp-login-logout-redirect'
							) }
							onClick={ onRemove }
							isDestructive
						/>
					</div>

					<div className="wplalr-rule-conditions">
						<p className="wplalr-rule-conditions-title">
							{ __(
								'Apply when all of these match:',
								'wp-login-logout-redirect'
							) }
						</p>
						{ conditions.length === 0 && (
							<p className="wplalr-rule-conditions-empty">
								{ __(
									'No conditions — this rule applies to everyone. Add a condition to target specific users.',
									'wp-login-logout-redirect'
								) }
							</p>
						) }
						{ conditions.map( ( condition, index ) => (
							<ConditionRow
								key={ condition.id ?? index }
								condition={ condition }
								onChange={ ( patch ) =>
									updateCondition( index, patch )
								}
								onRemove={ () => removeCondition( index ) }
							/>
						) ) }
						<Button
							className="wplalr-add-condition"
							variant="link"
							icon={ <PlusIcon /> }
							onClick={ addCondition }
						>
							{ __(
								'Add condition',
								'wp-login-logout-redirect'
							) }
						</Button>
					</div>

					<div className="wplalr-rule-urls">
						<TextControl
							type="url"
							label={ __(
								'Login redirect URL',
								'wp-login-logout-redirect'
							) }
							value={ rule.login_url ?? '' }
							placeholder={ `${ homeUrl }/dashboard/` }
							onChange={ ( value ) =>
								onChange( { login_url: value } )
							}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
						<TextControl
							type="url"
							label={ __(
								'Logout redirect URL',
								'wp-login-logout-redirect'
							) }
							value={ rule.logout_url ?? '' }
							placeholder={ `${ homeUrl }/goodbye/` }
							onChange={ ( value ) =>
								onChange( { logout_url: value } )
							}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</div>
				</CardBody>
			</Card>
		</div>
	);
};

export default RuleCard;
