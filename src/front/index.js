/**
 * Script front — amélioration progressive minimale.
 * Le formulaire HTML gère tout (POST → PHP → cookie → redirect).
 * Ce script ajoute juste un état de chargement visuel.
 */
( function () {
	const form = document.getElementById( 'plm-form' );
	if ( ! form ) {
		return;
	}

	form.addEventListener( 'submit', function () {
		const submitBtn = form.querySelector( '.plm-lock-form__submit' );
		if ( submitBtn ) {
			submitBtn.disabled = true;
			submitBtn.textContent = '…';
		}
	} );
} )();
