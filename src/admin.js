/**
 * WordPress dependencies
 */
import { createRoot } from '@wordpress/element';

/**
 * External dependencies
 */
import { HashRouter as Router, Routes, Route } from 'react-router-dom';

/**
 * Internal dependencies
 */
import './components/LayoutStyles.css';
import { SettingsProvider } from './context/SettingsContext';
import Layout from './components/Layout';
import RedirectSettings from './components/RedirectSettings';

const App = () => (
	<SettingsProvider>
		<Router>
			<Routes>
				<Route path="/" element={ <Layout /> }>
					<Route index element={ <RedirectSettings /> } />
				</Route>
			</Routes>
		</Router>
	</SettingsProvider>
);

document.addEventListener( 'DOMContentLoaded', () => {
	const container = document.getElementById( 'wplalr-settings' );

	if ( container ) {
		const root = createRoot( container );
		root.render( <App /> );
	}
} );
