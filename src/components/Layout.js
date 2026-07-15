import { __ } from '@wordpress/i18n';
import { Spinner, Card, CardBody } from '@wordpress/components';
import { Link, Outlet, useLocation } from 'react-router-dom';
import { applyFilters } from '@wordpress/hooks';

import { useSettings } from '../context/SettingsContext';
import { RedirectIcon, RulesIcon, OthersIcon } from './icons';

const TABS = [
	{
		to: '/',
		icon: RedirectIcon,
		label: __( 'Redirects', 'wp-login-logout-redirect' ),
	},
	{
		to: '/rules',
		icon: RulesIcon,
		label: __( 'Rules', 'wp-login-logout-redirect' ),
	},
	{
		to: '/others',
		icon: OthersIcon,
		label: __( 'Others', 'wp-login-logout-redirect' ),
	},
];

const SKELETON_WIDTHS = [ 120, 90 ];

/**
 * Inner layout for the Settings view: the Redirects/Rules hash tabs and the
 * routed sub-view. Page chrome (header, cross-page nav, snackbars) lives in the
 * shared PageShell that wraps this.
 */
const Layout = () => {
	const { isLoading } = useSettings();
	const { pathname } = useLocation();

	// Pro extensions add tabs via the `wplalr_tabs` filter.
	const tabs = applyFilters( 'wplalr_tabs', TABS );

	if ( isLoading ) {
		return (
			<>
				<div className="wplalr-hash-nav">
					{ SKELETON_WIDTHS.map( ( width ) => (
						<div
							key={ width }
							className="wplalr-skeleton-tab"
							style={ { width: `${ width }px` } }
						/>
					) ) }
				</div>
				<div className="wplalr-section">
					<Card>
						<CardBody className="wplalr-form-section-body">
							<div className="wplalr-loading">
								<Spinner />
							</div>
						</CardBody>
					</Card>
				</div>
			</>
		);
	}

	return (
		<>
			<div className="wplalr-hash-nav">
				{ tabs.map( ( { to, icon: Icon, label } ) => (
					<Link
						key={ to }
						to={ to }
						className={ pathname === to ? 'is-active' : '' }
					>
						<Icon />
						{ label }
					</Link>
				) ) }
			</div>
			<Outlet />
		</>
	);
};

export default Layout;
