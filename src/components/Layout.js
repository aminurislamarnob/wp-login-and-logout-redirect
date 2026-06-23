import { __ } from '@wordpress/i18n';
import {
	Button,
	Spinner,
	Card,
	CardBody,
	SnackbarList,
} from '@wordpress/components';
import { Link, Outlet, useLocation } from 'react-router-dom';
import { useSelect, useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';

import { useSettings } from '../context/SettingsContext';
import { RedirectIcon, RulesIcon, Squares2X2Icon } from './icons';
import SettingsHeader from './SettingsHeader';

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
];

const SKELETON_WIDTHS = [ 120, 90 ];

const Layout = () => {
	const { isLoading } = useSettings();
	const { pathname } = useLocation();

	const notices = useSelect(
		( select ) => select( noticesStore ).getNotices(),
		[]
	);
	const { removeNotice } = useDispatch( noticesStore );
	const snackbarNotices = notices.filter(
		( notice ) => notice.type === 'snackbar'
	);

	return (
		<div className="wplalr-admin-app">
			<SettingsHeader
				icon={ Squares2X2Icon }
				title={ __(
					'WP Login and Logout Redirect',
					'wp-login-logout-redirect'
				) }
				subTitle={ __(
					'Configure where users are sent after they log in or log out.',
					'wp-login-logout-redirect'
				) }
				actions={
					<>
						<Button
							variant="secondary"
							href="https://wordpress.org/plugins/wp-login-and-logout-redirect/"
							target="_blank"
							rel="noreferrer"
						>
							{ __(
								'Documentation',
								'wp-login-logout-redirect'
							) }
						</Button>
						<Button
							variant="primary"
							href="https://buymeacoffee.com/aiarnob"
							target="_blank"
							rel="noreferrer"
						>
							{ __( 'Support Me', 'wp-login-logout-redirect' ) }
						</Button>
					</>
				}
			/>

			<main className="wplalr-main-content wplalr-setting-wrapper">
				<div className="wplalr-content-body">
					{ isLoading ? (
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
					) : (
						<>
							<div className="wplalr-hash-nav">
								{ TABS.map( ( { to, icon: Icon, label } ) => (
									<Link
										key={ to }
										to={ to }
										className={
											pathname === to ? 'is-active' : ''
										}
									>
										<Icon />
										{ label }
									</Link>
								) ) }
							</div>
							<Outlet />
						</>
					) }
				</div>
			</main>

			<SnackbarList
				notices={ snackbarNotices }
				className="components-editor-notices__snackbar"
				onRemove={ removeNotice }
			/>
		</div>
	);
};

export default Layout;
