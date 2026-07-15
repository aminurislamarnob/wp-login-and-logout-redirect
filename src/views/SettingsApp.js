/**
 * External dependencies
 */
import { HashRouter as Router, Routes, Route } from 'react-router-dom';

/**
 * WordPress dependencies
 */
import { applyFilters } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import { SettingsProvider } from '../context/SettingsContext';
import Layout from '../components/Layout';
import RedirectSettings from '../components/RedirectSettings';
import RulesSettings from '../components/RulesSettings';
import OthersSettings from '../components/OthersSettings';

/**
 * The Redirects + Rules settings view.
 *
 * Keeps its internal Redirects/Rules hash tabs (sub-views of this one page).
 * The page-level header/nav/snackbars live in the shared `PageShell`.
 */
const SettingsApp = () => {
	// Pro extensions add routes here via the `wplalr_routes` filter.
	const routes = applyFilters( 'wplalr_routes', [
		{ path: 'rules', element: <RulesSettings /> },
		{ path: 'others', element: <OthersSettings /> },
	] );

	return (
		<SettingsProvider>
			<Router>
				<Routes>
					<Route path="/" element={ <Layout /> }>
						<Route index element={ <RedirectSettings /> } />
						{ routes.map( ( { path, element } ) => (
							<Route
								key={ path }
								path={ path }
								element={ element }
							/>
						) ) }
					</Route>
				</Routes>
			</Router>
		</SettingsProvider>
	);
};

export default SettingsApp;
