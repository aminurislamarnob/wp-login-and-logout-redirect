/**
 * WordPress dependencies
 */
import { createRoot } from '@wordpress/element';

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
import './components/LayoutStyles.css';
import { SettingsProvider } from './context/SettingsContext';
import Layout from './components/Layout';
import RedirectSettings from './components/RedirectSettings';
import RulesSettings from './components/RulesSettings';

const App = () => {
	// Pro extensions add routes here via the `wplalr.routes` filter.
	const routes = applyFilters( 'wplalr.routes', [
		{ path: 'rules', element: <RulesSettings /> },
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

document.addEventListener( 'DOMContentLoaded', () => {
	const container = document.getElementById( 'wplalr-settings' );

	if ( container ) {
		const root = createRoot( container );
		root.render( <App /> );
	}
} );
