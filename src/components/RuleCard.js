import { __, _n, sprintf } from '@wordpress/i18n';
import { useState, Fragment } from '@wordpress/element';
import {
	Card,
	CardBody,
	Button,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { applyFilters } from '@wordpress/hooks';
import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';

import ConditionRow from './ConditionRow';
import PlaceholderHint from './PlaceholderHint';
import { emptyCondition } from '../utils';
import {
	DragHandleIcon,
	PlusIcon,
	TrashIcon,
	ChevronDownIcon,
	ChevronUpIcon,
} from './icons';

/**
 * A single, sortable redirect rule card.
 *
 * Renders collapsed as a compact summary row (priority, name, conditions,
 * target) and expands in place for editing.
 *
 * @param {Object}   props
 * @param {Object}   props.rule            The rule object.
 * @param {number}   props.index           Zero-based position (evaluation order).
 * @param {boolean}  props.defaultExpanded Whether the card starts expanded.
 * @param {Function} props.onChange        Receives a partial patch for the rule.
 * @param {Function} props.onRemove        Removes this rule.
 */
const RuleCard = ( {
	rule,
	index,
	defaultExpanded = false,
	onChange,
	onRemove,
} ) => {
	const [ isExpanded, setIsExpanded ] = useState( defaultExpanded );

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

	const updateCondition = ( idx, patch ) => {
		const next = conditions.map( ( condition, i ) =>
			i === idx ? { ...condition, ...patch } : condition
		);
		onChange( { conditions: next } );
	};

	const removeCondition = ( idx ) => {
		onChange( {
			conditions: conditions.filter( ( _, i ) => i !== idx ),
		} );
	};

	const addCondition = () => {
		onChange( { conditions: [ ...conditions, emptyCondition() ] } );
	};

	const toggleExpanded = () => setIsExpanded( ( prev ) => ! prev );

	const homeUrl = window.wplalrAdmin?.homeUrl ?? '';

	const summaryParts = [
		conditions.length > 0
			? sprintf(
					/* translators: %d: number of conditions. */
					_n(
						'%d condition',
						'%d conditions',
						conditions.length,
						'wp-login-logout-redirect'
					),
					conditions.length
			  )
			: __( 'Applies to everyone', 'wp-login-logout-redirect' ),
	];

	if ( rule.login_url ) {
		summaryParts.push( `→ ${ rule.login_url }` );
	}

	const cardClasses = [
		'wplalr-rule-card',
		isExpanded ? 'is-expanded' : '',
		rule.enabled ? '' : 'is-disabled',
	]
		.filter( Boolean )
		.join( ' ' );

	return (
		<div ref={ setNodeRef } style={ style } className={ cardClasses }>
			<Card>
				<CardBody className="wplalr-rule-card-body">
					<div
						className="wplalr-rule-header"
						onClick={ toggleExpanded }
						role="presentation"
					>
						<Button
							className="wplalr-rule-drag"
							icon={ <DragHandleIcon /> }
							label={ __(
								'Reorder rule',
								'wp-login-logout-redirect'
							) }
							onClick={ ( event ) => event.stopPropagation() }
							{ ...attributes }
							{ ...listeners }
						/>
						<span
							className="wplalr-rule-priority"
							title={ __(
								'Evaluation order — the first matching rule wins.',
								'wp-login-logout-redirect'
							) }
						>
							{ index + 1 }
						</span>
						<div className="wplalr-rule-title">
							<strong>
								{ rule.label ||
									__(
										'Untitled rule',
										'wp-login-logout-redirect'
									) }
							</strong>
							<span className="wplalr-rule-summary">
								{ summaryParts.join( ' · ' ) }
							</span>
						</div>
						<div
							className="wplalr-rule-header-actions"
							onClick={ ( event ) => event.stopPropagation() }
							role="presentation"
						>
							<ToggleControl
								label={ __(
									'Enabled',
									'wp-login-logout-redirect'
								) }
								checked={ !! rule.enabled }
								onChange={ ( enabled ) =>
									onChange( { enabled } )
								}
								__nextHasNoMarginBottom
							/>
							<Button
								className="wplalr-rule-delete"
								icon={ <TrashIcon /> }
								label={ __(
									'Delete rule',
									'wp-login-logout-redirect'
								) }
								onClick={ onRemove }
								isDestructive
							/>
							<Button
								className="wplalr-rule-expand"
								icon={
									isExpanded ? (
										<ChevronUpIcon />
									) : (
										<ChevronDownIcon />
									)
								}
								label={
									isExpanded
										? __(
												'Collapse rule',
												'wp-login-logout-redirect'
										  )
										: __(
												'Expand rule',
												'wp-login-logout-redirect'
										  )
								}
								aria-expanded={ isExpanded }
								onClick={ toggleExpanded }
							/>
						</div>
					</div>

					<div
						className={ `wplalr-rule-collapse${
							isExpanded ? ' is-open' : ''
						}` }
						aria-hidden={ ! isExpanded }
					>
						<div className="wplalr-rule-collapse-inner">
							<div className="wplalr-rule-body">
								<div className="wplalr-settings-group">
									<TextControl
										label={ __(
											'Rule name',
											'wp-login-logout-redirect'
										) }
										value={ rule.label ?? '' }
										placeholder={ __(
											'e.g. Send editors to the dashboard',
											'wp-login-logout-redirect'
										) }
										help={ __(
											'Only shown here, to help you identify the rule.',
											'wp-login-logout-redirect'
										) }
										onChange={ ( label ) =>
											onChange( { label } )
										}
										__next40pxDefaultSize
										__nextHasNoMarginBottom
									/>
								</div>

								<div className="wplalr-rule-conditions">
									<p className="wplalr-rule-conditions-title">
										{ __(
											'Apply when all of these match:',
											'wp-login-logout-redirect'
										) }
									</p>
									<div className="wplalr-conditions-box">
										{ conditions.length === 0 && (
											<p className="wplalr-rule-conditions-empty">
												{ __(
													'No conditions — this rule applies to everyone. Add a condition to target specific users.',
													'wp-login-logout-redirect'
												) }
											</p>
										) }
										{ conditions.map(
											( condition, idx ) => (
												<Fragment
													key={ condition.id ?? idx }
												>
													{ idx > 0 && (
														<div className="wplalr-condition-and">
															<span>
																{ __(
																	'AND',
																	'wp-login-logout-redirect'
																) }
															</span>
														</div>
													) }
													<ConditionRow
														condition={ condition }
														onChange={ ( patch ) =>
															updateCondition(
																idx,
																patch
															)
														}
														onRemove={ () =>
															removeCondition(
																idx
															)
														}
													/>
												</Fragment>
											)
										) }
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
								</div>

								<div className="wplalr-rule-urls">
									<TextControl
										type="text"
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
										type="text"
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
									<PlaceholderHint />
								</div>

								{ /* Pro extensions inject extra rule fields here. */ }
								{ applyFilters( 'wplalr_rule_fields', null, {
									rule,
									onChange,
								} ) }
							</div>
						</div>
					</div>
				</CardBody>
			</Card>
		</div>
	);
};

export default RuleCard;
