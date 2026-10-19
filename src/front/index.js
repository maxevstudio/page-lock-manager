/**
 * Script front — amélioration progressive.
 *
 * Sans JavaScript, le formulaire fonctionne seul (POST → PHP → cookie →
 * redirection). Avec JavaScript :
 * - la fenêtre est déplacée sous <body> pour rester fixe même si le thème
 *   applique un `transform` à un conteneur parent ;
 * - le mot de passe est vérifié sans rechargement : erreur affichée sur place,
 *   et en cas de succès le flou se dissipe avant le rechargement de la page.
 */
( function () {
	const gate = document.getElementById( 'plm-gate' );
	const form = document.getElementById( 'plm-form' );
	if ( ! gate || ! form ) {
		return;
	}

	const overlay = gate.querySelector( '.plm-gate__overlay' );
	const dialog = gate.querySelector( '.plm-gate__dialog' );
	const input = form.querySelector( '.plm-gate__input' );
	const error = form.querySelector( '.plm-gate__error' );
	const submit = form.querySelector( '.plm-gate__submit' );
	const label = submit.textContent;
	const reduceMotion = window.matchMedia(
		'(prefers-reduced-motion: reduce)'
	).matches;

	document.body.appendChild( overlay );
	input.focus( { preventScroll: true } );

	function showError( message ) {
		error.textContent = message;
		error.hidden = false;
		dialog.classList.remove( 'is-shaking' );
		// Force un reflow pour rejouer l'animation à chaque erreur.
		void dialog.offsetWidth;
		dialog.classList.add( 'is-shaking' );
		input.select();
	}

	function setLoading( loading ) {
		submit.disabled = loading;
		submit.textContent = loading ? '…' : label;
	}

	form.addEventListener( 'submit', function ( event ) {
		if ( ! window.fetch || ! window.FormData ) {
			return;
		}
		event.preventDefault();
		setLoading( true );

		const data = new window.FormData( form );
		data.append( 'plm_ajax', '1' );

		window
			.fetch( form.action, {
				method: 'POST',
				body: data,
				credentials: 'same-origin',
			} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( result ) {
				if ( result && result.success ) {
					overlay.classList.add( 'is-unlocked' );
					window.setTimeout(
						function () {
							window.location.reload();
						},
						reduceMotion ? 0 : 450
					);
					return;
				}
				setLoading( false );
				showError(
					( result && result.data && result.data.message ) ||
						window.plkmFront.networkError
				);
			} )
			.catch( function () {
				setLoading( false );
				showError( window.plkmFront.networkError );
			} );
	} );
} )();
