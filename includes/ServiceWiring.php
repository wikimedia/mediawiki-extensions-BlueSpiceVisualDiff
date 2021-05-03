<?php

use BlueSpice\ExtensionAttributeBasedRegistry;
use BlueSpice\VisualDiff\DiffEngineFactory;
use MediaWiki\MediaWikiServices;

return [

	'BSVisualDiffDiffEngineFactory' => static function ( MediaWikiServices $services ) {
		$registry = new ExtensionAttributeBasedRegistry(
			'BlueSpiceVisualDiffDiffEngineRegistry'
		);
		return new DiffEngineFactory(
			$registry,
			$services,
			$services->getConfigFactory()->makeConfig( 'bsg' )
		);
	},

];
