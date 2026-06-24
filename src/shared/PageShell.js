/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Button, SnackbarList } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import { applyFilters } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import SettingsHeader from '../components/SettingsHeader';
import { Squares2X2Icon } from '../components/icons';
import { getNavItems } from './navItems';

/**
 * Shared chrome for every admin page: header, cross-page nav, snackbars.
 *
 * The three submenu pages are separate WordPress URLs (full page loads), so the
 * cross-page nav is plain links rather than a client router; the active page is
 * highlighted via `current`.
 *
 * @param {Object}  props          Component props.
 * @param {string}  props.current  Active page slug (matches a nav item `page`).
 * @param {Element} props.children The active view.
 */
const PageShell = ( { current, children } ) => {
	const notices = useSelect(
		( select ) => select( noticesStore ).getNotices(),
		[]
	);
	const { removeNotice } = useDispatch( noticesStore );
	const snackbarNotices = notices.filter(
		( notice ) => notice.type === 'snackbar'
	);

	const adminUrl = window.wplalrAdmin?.adminUrl || '';
	const navItems = getNavItems();

	// Pro extensions add header actions via this filter.
	const headerActions = applyFilters(
		'wplalr_header_actions',
		<>
			<Button
				variant="secondary"
				href="https://wordpress.org/plugins/wp-login-and-logout-redirect/"
				target="_blank"
				rel="noreferrer"
			>
				{ __( 'Documentation', 'wp-login-logout-redirect' ) }
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
				actions={ headerActions }
			/>

			<nav className="wplalr-page-nav">
				{ navItems.map( ( { page, label } ) => (
					<a
						key={ page }
						href={ `${ adminUrl }admin.php?page=${ page }` }
						className={ page === current ? 'is-active' : '' }
						aria-current={ page === current ? 'page' : undefined }
					>
						{ label }
					</a>
				) ) }
			</nav>

			<main className="wplalr-main-content wplalr-setting-wrapper">
				<div className="wplalr-content-body">{ children }</div>
			</main>

			<SnackbarList
				notices={ snackbarNotices }
				className="components-editor-notices__snackbar"
				onRemove={ removeNotice }
			/>
		</div>
	);
};

export default PageShell;
