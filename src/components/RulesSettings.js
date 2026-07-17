import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Card, CardBody, Spinner } from '@wordpress/components';
import {
	DndContext,
	closestCenter,
	PointerSensor,
	KeyboardSensor,
	useSensor,
	useSensors,
} from '@dnd-kit/core';
import {
	SortableContext,
	verticalListSortingStrategy,
	sortableKeyboardCoordinates,
	arrayMove,
} from '@dnd-kit/sortable';

import { useSettings } from '../context/SettingsContext';
import RuleCard from './RuleCard';
import { emptyRule, makeId } from '../utils';
import { PlusIcon } from './icons';

/**
 * Ensure every rule and condition has a stable id for drag-and-drop / keys.
 *
 * @param {Array} rules Rules from the API.
 * @return {Array} Rules with ids guaranteed.
 */
const withIds = ( rules ) =>
	( rules ?? [] ).map( ( rule ) => ( {
		...emptyRule(),
		...rule,
		id: rule.id || makeId(),
		conditions: ( rule.conditions ?? [] ).map( ( condition ) => ( {
			...condition,
			id: condition.id || makeId(),
		} ) ),
	} ) );

const RulesSettings = () => {
	const { settings, isSaving, saveSettings } = useSettings();
	const [ rules, setRules ] = useState( [] );
	const [ lastAddedId, setLastAddedId ] = useState( null );

	useEffect( () => {
		setRules( withIds( settings.rules ) );
	}, [ settings.rules ] );

	const sensors = useSensors(
		useSensor( PointerSensor, {
			activationConstraint: { distance: 5 },
		} ),
		useSensor( KeyboardSensor, {
			coordinateGetter: sortableKeyboardCoordinates,
		} )
	);

	const updateRule = ( id, patch ) => {
		setRules( ( prev ) =>
			prev.map( ( rule ) =>
				rule.id === id ? { ...rule, ...patch } : rule
			)
		);
	};

	const removeRule = ( id ) => {
		setRules( ( prev ) => prev.filter( ( rule ) => rule.id !== id ) );
	};

	const addRule = () => {
		const rule = emptyRule();
		setLastAddedId( rule.id );
		setRules( ( prev ) => [ ...prev, rule ] );
	};

	const handleDragEnd = ( event ) => {
		const { active, over } = event;

		if ( ! over || active.id === over.id ) {
			return;
		}

		setRules( ( prev ) => {
			const oldIndex = prev.findIndex( ( r ) => r.id === active.id );
			const newIndex = prev.findIndex( ( r ) => r.id === over.id );
			return arrayMove( prev, oldIndex, newIndex );
		} );
	};

	const handleSave = () => {
		saveSettings(
			{ rules },
			__( 'Redirect rules saved!', 'wp-login-logout-redirect' )
		);
	};

	return (
		<div className="wplalr-section" id="wplalr-rules-settings">
			<Card className="wplalr-form-header-card">
				<CardBody className="wplalr-form-section-header">
					<h3 className="wplalr-section-title">
						{ __( 'Redirect Rules', 'wp-login-logout-redirect' ) }
					</h3>
					<p className="wplalr-section-description">
						{ __(
							'Rules are evaluated top to bottom; the first matching rule wins. A rule matches when all of its conditions pass. When no rule matches, the defaults on the Redirects tab are used.',
							'wp-login-logout-redirect'
						) }
					</p>
				</CardBody>
			</Card>

			<DndContext
				sensors={ sensors }
				collisionDetection={ closestCenter }
				onDragEnd={ handleDragEnd }
			>
				<SortableContext
					items={ rules.map( ( rule ) => rule.id ) }
					strategy={ verticalListSortingStrategy }
				>
					{ rules.map( ( rule, index ) => (
						<RuleCard
							key={ rule.id }
							rule={ rule }
							index={ index }
							defaultExpanded={ rule.id === lastAddedId }
							onChange={ ( patch ) =>
								updateRule( rule.id, patch )
							}
							onRemove={ () => removeRule( rule.id ) }
						/>
					) ) }
				</SortableContext>
			</DndContext>

			{ rules.length === 0 && (
				<Card className="wplalr-rules-empty">
					<CardBody>
						<p>
							{ __(
								'No rules yet. Add a rule to redirect specific roles, users, or capabilities.',
								'wp-login-logout-redirect'
							) }
						</p>
					</CardBody>
				</Card>
			) }

			<div className="wplalr-rules-actions">
				<Button
					variant="secondary"
					icon={ <PlusIcon /> }
					onClick={ addRule }
				>
					{ __( 'Add rule', 'wp-login-logout-redirect' ) }
				</Button>
				<Button
					variant="primary"
					onClick={ handleSave }
					isBusy={ isSaving }
					disabled={ isSaving }
				>
					{ isSaving && <Spinner /> }
					{ __( 'Save Rules', 'wp-login-logout-redirect' ) }
				</Button>
			</div>
		</div>
	);
};

export default RulesSettings;
