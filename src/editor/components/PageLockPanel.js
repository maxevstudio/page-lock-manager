import { __ } from '@wordpress/i18n';
import { useSelect, useDispatch } from '@wordpress/data';
import { useCallback, useState } from '@wordpress/element';
import { PluginDocumentSettingPanel } from '@wordpress/edit-post';
import {
	ToggleControl,
	TextControl,
	Button,
	Flex,
	FlexItem,
	Notice,
	Icon,
} from '@wordpress/components';

/**
 * Génère un mot de passe aléatoire.
 */
function generatePassword( length = 12 ) {
	const chars = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%';
	let password = '';
	const array = new Uint32Array( length );
	crypto.getRandomValues( array );
	for ( let i = 0; i < length; i++ ) {
		password += chars[ array[ i ] % chars.length ];
	}
	return password;
}

export default function PageLockPanel() {
	const [ showPassword, setShowPassword ] = useState( false );

	const { isLocked, hasPassword, meta } = useSelect( ( select ) => {
		const postMeta = select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {};
		return {
			isLocked: !! postMeta._plm_locked,
			hasPassword: !! postMeta._plm_has_password,
			meta: postMeta,
		};
	}, [] );

	const { editPost } = useDispatch( 'core/editor' );

	const password = meta._plm_password || '';

	const updateMeta = useCallback(
		( newMeta ) => {
			editPost( { meta: { ...meta, ...newMeta } } );
		},
		[ editPost, meta ]
	);

	const handleToggle = useCallback(
		( value ) => {
			const newMeta = { _plm_locked: value };
			if ( ! value ) {
				newMeta._plm_password = '';
			}
			updateMeta( newMeta );
		},
		[ updateMeta ]
	);

	const handlePasswordChange = useCallback(
		( value ) => {
			updateMeta( { _plm_password: value } );
		},
		[ updateMeta ]
	);

	const handleGenerate = useCallback( () => {
		const pwd = generatePassword();
		updateMeta( { _plm_password: pwd } );
		setShowPassword( true );
	}, [ updateMeta ] );

	return (
		<PluginDocumentSettingPanel
			name="page-lock-manager"
			title={ __( 'Page Lock Manager', 'page-lock-manager' ) }
			icon="lock"
		>
			<div className="plm-editor-panel">
				<ToggleControl
					label={ __( 'Protéger cette page par mot de passe', 'page-lock-manager' ) }
					checked={ isLocked }
					onChange={ handleToggle }
				/>

				{ isLocked && (
					<>
						<div className="plm-editor-panel__password">
							<TextControl
								label={ __( 'Mot de passe', 'page-lock-manager' ) }
								type={ showPassword ? 'text' : 'password' }
								value={ password }
								onChange={ handlePasswordChange }
								placeholder={
									hasPassword && ! password
										? __( '••••••••', 'page-lock-manager' )
										: __( 'Saisir un mot de passe', 'page-lock-manager' )
								}
								help={
									hasPassword && ! password
										? __( 'Un mot de passe est déjà défini. Saisissez-en un nouveau pour le modifier.', 'page-lock-manager' )
										: __( 'Ce mot de passe sera requis pour accéder à la page.', 'page-lock-manager' )
								}
								autoComplete="new-password"
							/>
							<Flex justify="flex-start" gap={ 2 }>
								<FlexItem>
									<Button
										variant="link"
										onClick={ () => setShowPassword( ! showPassword ) }
										size="small"
									>
										{ showPassword
											? __( 'Masquer', 'page-lock-manager' )
											: __( 'Afficher', 'page-lock-manager' ) }
									</Button>
								</FlexItem>
								<FlexItem>
									<Button
										variant="link"
										onClick={ handleGenerate }
										size="small"
									>
										{ __( 'Générer', 'page-lock-manager' ) }
									</Button>
								</FlexItem>
							</Flex>
						</div>

						{ ! hasPassword && ! password && (
							<Notice status="warning" isDismissible={ false }>
								{ __( 'Définissez un mot de passe avant de publier.', 'page-lock-manager' ) }
							</Notice>
						) }

						{ ( hasPassword || password ) && (
							<div className="plm-editor-panel__status">
								<Flex alignment="center" gap={ 2 }>
									<FlexItem>
										<Icon icon="lock" size={ 18 } />
									</FlexItem>
									<FlexItem>
										<strong>{ __( 'Page protégée', 'page-lock-manager' ) }</strong>
									</FlexItem>
								</Flex>
							</div>
						) }
					</>
				) }

				{ ! isLocked && (
					<p className="plm-editor-panel__hint">
						{ __( 'Activez la protection pour restreindre l\'accès à cette page par mot de passe.', 'page-lock-manager' ) }
					</p>
				) }
			</div>
		</PluginDocumentSettingPanel>
	);
}
