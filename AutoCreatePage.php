<?php
if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'AutoCreatePage' );

	$wgMessagesDirs['AutoCreatePage'] = __DIR__ . '/i18n';

	$wgExtensionMessagesFiles['AutoCreatePageMagic'] = __DIR__ . 'AutoCreatePage.i18n.magic.php';

	wfWarn(
		'Deprecated PHP entry point used for the AutoCreatePage extension. ' .
		'Please use wfLoadExtension() instead, ' .
		'see https://www.mediawiki.org/wiki/Special:MyLanguage/Manual:Extension_registration for more details.'
	);

	return;
} else {
	die( 'This version of the AutoCreatePage extension requires MediaWiki 1.35+' );
}
