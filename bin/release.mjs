#!/usr/bin/env node
/**
 * Script de release Page Lock Manager.
 *
 * Usage :
 *   npm run package                         → build + ZIP de la version actuelle (utilisé par la CI)
 *   npm run release -- --version=1.2.0      → bump de version, CHANGELOG, ZIP, commit et tag git
 *   npm run release                         → idem avec un incrément patch
 *
 * Voir RELEASE.md. Le script s'arrête (code de sortie non nul) dès qu'une
 * étape critique échoue : aucune release invalide n'est produite.
 */
import { execSync } from 'node:child_process';
import { existsSync, mkdirSync, rmSync, cpSync, readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const SLUG = 'page-lock-manager';
const ROOT = path.dirname( path.dirname( fileURLToPath( import.meta.url ) ) );
const PKG_PATH = path.join( ROOT, 'package.json' );
const PLUGIN_FILE = path.join( ROOT, `${ SLUG }.php` );
const README_TXT = path.join( ROOT, 'readme.txt' );
const CHANGELOG = path.join( ROOT, 'CHANGELOG.md' );
const DIST_DIR = path.join( ROOT, 'dist' );
const DIST_PLUGIN_DIR = path.join( DIST_DIR, SLUG );

// Seuls ces éléments sont copiés dans le ZIP installable.
const DIST_INCLUDES = [
	`${ SLUG }.php`,
	'uninstall.php',
	'includes',
	'build',
	'assets',
	'languages',
	'readme.txt',
	'LICENSE',
];

const FORBIDDEN_ENTRIES = [ 'node_modules', '.git', '.github', 'src', 'tests', 'bin', 'dist', 'package.json', 'package-lock.json' ];

const args = process.argv.slice( 2 );
const packageOnly = args.includes( '--package-only' );
const versionArg = args.map( ( arg ) => arg.match( /^--version=(.+)$/ ) ).find( Boolean );

function log( step, message ) {
	console.log( `\n\u001b[1m[${ step }]\u001b[0m ${ message }` );
}

function fail( message ) {
	console.error( `\n\u001b[31mErreur release : ${ message }\u001b[0m` );
	process.exit( 1 );
}

function run( command ) {
	execSync( command, { cwd: ROOT, stdio: 'inherit' } );
}

function readJSON( file ) {
	return JSON.parse( readFileSync( file, 'utf8' ) );
}

function getPluginVersion() {
	const match = readFileSync( PLUGIN_FILE, 'utf8' ).match( /\* Version:\s+(.+)/ );
	return match ? match[ 1 ].trim() : null;
}

/**
 * Release complète : exige un dépôt git propre (le commit de release ne doit
 * contenir que le bump de version et le CHANGELOG).
 */
function verifyCleanTree() {
	log( 'git', 'Vérification du dépôt' );
	let status;
	try {
		status = execSync( 'git status --porcelain', { cwd: ROOT } ).toString().trim();
	} catch ( error ) {
		fail( 'git est indisponible : impossible de tagger la release.' );
	}
	if ( status ) {
		fail( 'Des modifications non commitées sont présentes. Commitez-les avant la release.' );
	}
}

function lintAndTest() {
	log( 'lint', 'Lint PHP / JS / CSS' );

	const phpFiles = execSync( `find . -name "*.php" -not -path "./node_modules/*" -not -path "./dist/*"`, { cwd: ROOT } )
		.toString()
		.trim()
		.split( '\n' )
		.filter( Boolean );

	try {
		for ( const file of phpFiles ) {
			execSync( `php -l "${ file }"`, { cwd: ROOT, stdio: 'ignore' } );
		}
	} catch ( error ) {
		fail( 'Le lint PHP a échoué (lancez `php -l` sur les fichiers modifiés).' );
	}

	try {
		run( 'npm run lint:js' );
		run( 'npm run lint:css' );
	} catch ( error ) {
		fail( 'Le lint JS/CSS a échoué.' );
	}
}

function buildProduction() {
	log( 'build', 'Build de production' );
	try {
		run( 'npm run build' );
	} catch ( error ) {
		fail( 'Le build de production a échoué.' );
	}
	for ( const entry of [ 'editor.js', 'front.js' ] ) {
		if ( ! existsSync( path.join( ROOT, 'build', entry ) ) ) {
			fail( `build/${ entry } est introuvable après compilation.` );
		}
	}
}

function resolveVersion( currentVersion ) {
	if ( versionArg ) {
		if ( ! /^\d+\.\d+\.\d+$/.test( versionArg[ 1 ] ) ) {
			fail( `Version invalide : ${ versionArg[ 1 ] } (format attendu : 1.2.3).` );
		}
		return versionArg[ 1 ];
	}
	const [ major, minor, patch ] = currentVersion.split( '.' ).map( Number );
	return `${ major }.${ minor }.${ patch + 1 }`;
}

/**
 * Écrit la version dans package.json, l'en-tête du plugin, PLKM_VERSION et
 * le Stable tag de readme.txt.
 */
function applyVersion( version ) {
	log( 'version', `Application de la version ${ version }` );

	const pkg = readJSON( PKG_PATH );
	pkg.version = version;
	writeFileSync( PKG_PATH, JSON.stringify( pkg, null, '\t' ) + '\n' );

	let plugin = readFileSync( PLUGIN_FILE, 'utf8' );
	plugin = plugin.replace( /(\* Version:\s+).+/, `$1${ version }` );
	plugin = plugin.replace( /define\(\s*'PLKM_VERSION',\s*'.+?'\s*\);/, `define( 'PLKM_VERSION', '${ version }' );` );
	writeFileSync( PLUGIN_FILE, plugin );

	let readme = readFileSync( README_TXT, 'utf8' );
	readme = readme.replace( /(Stable tag:\s+).+/, `$1${ version }` );
	writeFileSync( README_TXT, readme );
}

/**
 * Transforme la section [Unreleased] de CHANGELOG.md en section datée.
 */
function updateChangelog( version ) {
	log( 'changelog', 'Mise à jour de CHANGELOG.md' );

	let changelog = readFileSync( CHANGELOG, 'utf8' );
	const match = changelog.match( /## \[Unreleased\]\n([\s\S]*?)(?=\n## |$)/ );

	if ( ! match || ! match[ 1 ].trim() ) {
		fail( 'La section [Unreleased] de CHANGELOG.md est vide : rien à publier.' );
	}

	const date = new Date().toISOString().slice( 0, 10 );
	changelog = changelog.replace( match[ 0 ], `## [Unreleased]\n\n## [${ version }] — ${ date }\n${ match[ 1 ].trimEnd() }\n` );
	writeFileSync( CHANGELOG, changelog );
}

function prepareDistribution() {
	log( 'dist', 'Préparation de dist/' );

	rmSync( DIST_DIR, { recursive: true, force: true } );
	mkdirSync( DIST_PLUGIN_DIR, { recursive: true } );

	for ( const entry of DIST_INCLUDES ) {
		const source = path.join( ROOT, entry );
		if ( existsSync( source ) ) {
			cpSync( source, path.join( DIST_PLUGIN_DIR, entry ), {
				recursive: true,
				filter: ( src ) => ! src.endsWith( '.map' ) && ! src.endsWith( '.DS_Store' ),
			} );
		}
	}

	if ( ! existsSync( path.join( DIST_PLUGIN_DIR, `${ SLUG }.php` ) ) ) {
		fail( `${ SLUG }.php est absent de la distribution.` );
	}
	for ( const forbidden of FORBIDDEN_ENTRIES ) {
		if ( existsSync( path.join( DIST_PLUGIN_DIR, forbidden ) ) ) {
			fail( `L'élément interdit "${ forbidden }" est présent dans la distribution.` );
		}
	}
}

/**
 * Le dossier racine du ZIP doit être `page-lock-manager/` : c'est lui que
 * WordPress remplace lors d'une mise à jour automatique.
 */
function createZip( version ) {
	log( 'zip', 'Génération du ZIP' );

	const zipName = `${ SLUG }-${ version }.zip`;
	try {
		execSync( `zip -r -q -X "${ zipName }" "${ SLUG }"`, { cwd: DIST_DIR, stdio: 'inherit' } );
	} catch ( error ) {
		fail( 'La commande "zip" a échoué ou est indisponible.' );
	}
	return path.join( DIST_DIR, zipName );
}

function commitAndTag( version ) {
	log( 'git', `Commit et tag v${ version }` );
	run( `git add package.json ${ SLUG }.php readme.txt CHANGELOG.md` );
	run( `git commit -q -m "Release ${ version }"` );
	run( `git tag -a v${ version } -m "Page Lock Manager ${ version }"` );
}

function main() {
	if ( packageOnly ) {
		const version = getPluginVersion();
		const pkgVersion = readJSON( PKG_PATH ).version;
		if ( version !== pkgVersion ) {
			fail( `Versions incohérentes : ${ SLUG }.php=${ version }, package.json=${ pkgVersion }.` );
		}
		buildProduction();
		prepareDistribution();
		const zipPath = createZip( version );
		console.log( `\n\u001b[32mZIP ${ version } prêt : ${ path.relative( ROOT, zipPath ) }\u001b[0m` );
		return;
	}

	verifyCleanTree();
	const version = resolveVersion( readJSON( PKG_PATH ).version );
	lintAndTest();
	buildProduction();
	applyVersion( version );
	updateChangelog( version );
	prepareDistribution();
	const zipPath = createZip( version );
	commitAndTag( version );

	console.log( `\n\u001b[32mRelease ${ version } prête : ${ path.relative( ROOT, zipPath ) }\u001b[0m` );
	console.log( `Publiez-la avec :  git push origin main --follow-tags` );
}

main();
