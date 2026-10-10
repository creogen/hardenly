/**
 * The dashboard screen, rendered into the element DashboardPage outputs.
 */
import { createElement, createRoot } from '@wordpress/element';
import App from './App';
import './index.css';

const root = document.getElementById( 'sitewarden-dashboard' );
if ( root ) {
	createRoot( root ).render( <App /> );
}
