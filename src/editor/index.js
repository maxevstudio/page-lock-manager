import { registerPlugin } from '@wordpress/plugins';
import PageLockPanel from './components/PageLockPanel';

registerPlugin( 'page-lock-manager', {
	render: PageLockPanel,
	icon: 'lock',
} );
