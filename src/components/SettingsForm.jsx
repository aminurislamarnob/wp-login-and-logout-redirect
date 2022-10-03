import React from 'react';
import Select from 'react-select';
import api from '@wordpress/api';
import { __ } from '@wordpress/i18n';
import {
	Icon,
	Dashicon,
	TextControl,
	Button,
	Flex,
	FlexBlock,
	FlexItem,
	Card,
	CardHeader,
	CardBody,
	CardFooter,
	CardDivider,
} from '@wordpress/components';

import { useState, useEffect } from '@wordpress/element';

import FetchSettings from './FetchSettings';

const SettingsForm = ( { availableRoles } ) => {
	const [ valuesList, setValuesList ] = useState( [] );

	const [ siteRoles, setSiteRoles ] = useState( [] );

	const { data, error } = FetchSettings( availableRoles );

	useEffect( () => {
		setSiteRoles( availableRoles );
		setValuesList( data );
	}, [ data ] );

	const updateValuesFromInputs = ( type, value, index ) => {
		const list = [ ...valuesList ];
		list[ index ][ type ] = value;
		setValuesList( list );
	};

	const handleSave = ( e ) => {
		e.preventDefault();

		let newItems = [ ...valuesList ].map( ( item ) => {
			item.roles = item?.roles?.map( ( roles ) => roles.value );
			return item;
		} );

		newItems = JSON.stringify( newItems );
		const settings = new api.models.Settings( {
			[ 'wplalr_login_logout_data' ]: newItems,
		} );

		settings.save().then( ( res ) => {
			// update state values maybe
			console.info( 'SAved' );
		} );
	};

	const addToList = () => {
		setValuesList( [
			...valuesList,
			{
				login_url: '',
				logout_url: '',
				roles: [],
			},
		] );
	};

	const removeFromList = ( index ) => {
		const list = [ ...valuesList ];
		list.splice( index, 1 );
		// @TODO selected roles bug when top item is removed.
		setValuesList( list );
	};

	return (
		<>
			<div className="">
				<h1>
					{ __(
						'Wp Login Logout redirect',
						'wp-login-logout-redirect'
					) }{ ' ' }
					<Icon icon="admin-plugins" />
				</h1>
				<div className="login-logout-settings-form">
					<div>
						<Card
							className="wp-login-logout-settings-form"
							elevation={ 3 }
							isRounded={ true }
							size="medium"
						>
							{ valuesList &&
								valuesList.map( ( item, index ) => {
									return (
										<>
											<CardBody
												size="small"
												elevation={ 5 }
											>
												<Flex key={ index }>
													<FlexItem>
														<strong>
															Add url and roles
															pair.
														</strong>
													</FlexItem>
													<FlexBlock>
														<FlexItem>
															<TextControl
																help={ __(
																	'A url to redirect the selected roles to after Login.',
																	'wp-login-logout-redirect'
																) }
																label={ __(
																	'After Login Url (Redirect to)',
																	'wp-login-logout-redirect'
																) }
																placeholder="url"
																type="url"
																onChange={ (
																	value
																) =>
																	updateValuesFromInputs(
																		'login_url',
																		value,
																		index
																	)
																}
																value={
																	item?.login_url
																}
															/>
														</FlexItem>
														<FlexItem>
															<TextControl
																help={ __(
																	'A url to redirect the selected roles to after Logout.',
																	'wp-login-logout-redirect'
																) }
																label={ __(
																	'After Logout Url (Redirect to)',
																	'wp-login-logout-redirect'
																) }
																placeholder="url"
																type="url"
																onChange={ (
																	value
																) =>
																	updateValuesFromInputs(
																		'logout_url',
																		value,
																		index
																	)
																}
																value={
																	item?.logout_url
																}
															/>
														</FlexItem>
													</FlexBlock>
													<FlexBlock className="login-logout-roles-control">
														<FlexItem>
															<label htmlFor="Select Roles">
																{ __(
																	'Select Roles',
																	'wp-login-logout-redirect'
																) }
															</label>
															<Select
																name="Select Roles"
																placeholder="Please select a role"
																defaultValue={
																	item.roles
																}
																options={
																	siteRoles
																}
																onChange={ (
																	value
																) =>
																	updateValuesFromInputs(
																		'roles',
																		value,
																		index
																	)
																}
																autoFocus={
																	true
																}
																isMulti={ true }
																isClearable={
																	true
																}
															/>
														</FlexItem>
														<FlexItem>
															{ valuesList.length >
																1 && (
																<Button
																	isPrimary
																	isLarge
																	onClick={ () =>
																		removeFromList(
																			index
																		)
																	}
																>
																	<Dashicon icon="trash" />
																	{ __(
																		'- Remove collection -',
																		'wp-login-logout-redirect'
																	) }
																	<Dashicon icon="trash" />
																</Button>
															) }
														</FlexItem>
													</FlexBlock>
												</Flex>
											</CardBody>

											<CardDivider />
											<CardFooter>
												{ valuesList.length - 1 ===
													index && (
													<Button
														isPrimary
														isLarge
														onClick={ addToList }
													>
														<Dashicon icon="plus" />
														{ __(
															'Add another url/roles pair',
															'wp-login-logout-redirect'
														) }
														<Dashicon icon="plus" />
													</Button>
												) }
											</CardFooter>
										</>
									);
								} ) }
						</Card>
						<Button isPrimary isLarge onClick={ handleSave }>
							{ __( 'Save values', 'wp-login-logout-redirect' ) }
						</Button>
					</div>
				</div>
			</div>
		</>
	);
};

export default SettingsForm;
