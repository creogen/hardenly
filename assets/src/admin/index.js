/**
 * The dashboard screen, rendered into the element DashboardPage outputs.
 */
import { createElement, createRoot } from '@wordpress/element';
import App from './App';
import './index.css';

const root = document.getElementById( 'hardenly-dashboard' );
if ( root ) {
	createRoot( root ).render( <App /> );
}
