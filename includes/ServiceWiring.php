<?php

use BlueSpice\ExtensionAttributeBasedRegistry;
use BlueSpice\VisualDiff\DiffEngineFactory;
use MediaWiki\MediaWikiServices;

// PHP unit does not understand code coverage for this file
// as the @covers annotation cannot cover a specific file
// This is fully tested in ServiceWiringTest.php
// @codeCoverageIgnoreStart

return [

	'BSVisualDiffDiffEngineFactory' => function ( MediaWikiServices $services ) {
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

// @codeCoverageIgnoreEnd
