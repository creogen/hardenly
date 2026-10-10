import { describe, expect, it } from 'vitest';
import {
	groupBySeverity,
	isActive,
	detailPath,
	ignoredProblems,
	isProblem,
	notes,
	progressPercent,
	scoreChange,
	scoreLevel,
} from './report';

const result = ( status, severity, message = '' ) => ( {
	check_id: 'test',
	status,
	severity,
	message,
} );

describe( 'scoreLevel', () => {
	it.each( [
		[ 100, 'good' ],
		[ 90, 'good' ],
		[ 89, 'attention' ],
		[ 60, 'attention' ],
		[ 59, 'risk' ],
		[ 0, 'risk' ],
	] )( 'puts %i in %s', ( score, level ) => {
		expect( scoreLevel( score ) ).toBe( level );
	} );
} );

describe( 'isProblem', () => {
	it( 'counts failures and warnings only', () => {
		expect( isProblem( result( 'fail', 'high' ) ) ).toBe( true );
		expect( isProblem( result( 'warning', 'medium' ) ) ).toBe( true );
		expect( isProblem( result( 'pass', 'info' ) ) ).toBe( false );
		expect( isProblem( result( 'skipped', 'info' ) ) ).toBe( false );
		expect( isProblem( result( 'info', 'info' ) ) ).toBe( false );
	} );
} );

describe( 'groupBySeverity', () => {
	it( 'groups problems most severe first and drops empty groups', () => {
		const groups = groupBySeverity( [
			result( 'fail', 'low', 'a' ),
			result( 'pass', 'info', 'b' ),
			result( 'warning', 'high', 'c' ),
			result( 'fail', 'high', 'd' ),
		] );

		expect(
			groups.map( ( group ) => [
				group.severity,
				group.results.map( ( r ) => r.message ),
			] )
		).toEqual( [
			[ 'high', [ 'c', 'd' ] ],
			[ 'low', [ 'a' ] ],
		] );
	} );

	it( 'leaves out ignored problems', () => {
		const ignored = { ...result( 'fail', 'high', 'a' ), ignored: true };

		expect( groupBySeverity( [ ignored ] ) ).toEqual( [] );
		expect(
			ignoredProblems( [ ignored, result( 'fail', 'low' ) ] )
		).toEqual( [ ignored ] );
	} );

	it( 'returns nothing without problems', () => {
		expect( groupBySeverity( [ result( 'pass', 'info' ) ] ) ).toEqual( [] );
	} );
} );

describe( 'notes', () => {
	it( 'lists notes, then skipped checks, and no problems or passes', () => {
		const list = notes( [
			result( 'skipped', 'info', 'skipped' ),
			result( 'fail', 'high', 'problem' ),
			result( 'info', 'info', 'note' ),
			result( 'pass', 'info', 'pass' ),
		] );

		expect( list.map( ( r ) => r.message ) ).toEqual( [
			'note',
			'skipped',
		] );
	} );
} );

describe( 'detailPath', () => {
	it( 'reads the path at the start of file details', () => {
		expect(
			detailPath(
				'code.suspicious',
				'wp-content/a.php:12 eval(x) (rule)'
			)
		).toBe( 'wp-content/a.php' );
		expect(
			detailPath( 'files.uploads_php', 'wp-content/uploads/b.php' )
		).toBe( 'wp-content/uploads/b.php' );
	} );

	it( 'is null for checks whose details are not files', () => {
		expect(
			detailPath( 'versions.plugins', 'Akismet 5.0 → 5.4' )
		).toBeNull();
	} );
} );

describe( 'scoreChange', () => {
	it( 'compares the latest two scans', () => {
		expect( scoreChange( [ { score: 72 }, { score: 78 } ] ) ).toBe( -6 );
	} );

	it( 'is null after a single scan', () => {
		expect( scoreChange( [ { score: 72 } ] ) ).toBeNull();
	} );
} );

describe( 'isActive', () => {
	it( 'is true for queued and running scans only', () => {
		expect( isActive( { state: 'queued' } ) ).toBe( true );
		expect( isActive( { state: 'running' } ) ).toBe( true );
		expect( isActive( { state: 'idle' } ) ).toBe( false );
		expect( isActive( { state: 'failed' } ) ).toBe( false );
		expect( isActive( null ) ).toBe( false );
	} );
} );

describe( 'progressPercent', () => {
	it( 'clamps to 0..100', () => {
		expect( progressPercent( { progress: { percent: 58 } } ) ).toBe( 58 );
		expect( progressPercent( { progress: { percent: 140 } } ) ).toBe( 100 );
		expect( progressPercent( {} ) ).toBe( 0 );
	} );
} );
