<?php

namespace BlueSpice\VisualDiff;

use BlueSpice\ExtensionAttributeBasedRegistry;
use Config;
use MediaWiki\MediaWikiServices;

class DiffEngineFactory {

	/**
	 * @var ExtensionAttributeBasedRegistry
	 */
	protected $diffEngineRegistry = null;

	/**
	 * @var MediaWikiServices
	 */
	protected $services = null;

	/**
	 * @var Config
	 */
	protected $config = null;

	/**
	 * @var IDiffEngine[]
	 */
	protected $diffEngines = [];

	/**
	 * @param ExtensionAttributeBasedRegistry $diffEngineRegistry
	 * @param MediaWikiServices $services
	 * @param Config $config
	 */
	public function __construct( ExtensionAttributeBasedRegistry $diffEngineRegistry,
		MediaWikiServices $services, Config $config ) {
		$this->diffEngineRegistry = $diffEngineRegistry;
		$this->services = $services;
		$this->config = $config;
	}

	/**
	 * @param string $name
	 * @return IDiffEngine|null
	 */
	public function newFromName( $name ) {
		if ( isset( $this->diffEngines[$name] ) ) {
			$this->diffEngines[$name];
		}
		$this->diffEngines[$name] = null;
		$callable = $this->diffEngineRegistry->getValue( $name, null );

		if ( !is_callable( $callable ) ) {
			return $this->diffEngines[$name];
		}
		$this->diffEngines[$name] = call_user_func_array( $callable, [
			$name,
			$this->services,
			$this->config,
		] );
		return $this->diffEngines[$name];
	}

	/**
	 * @return IDiffEngine[]
	 */
	public function getDiffEngines() {
		$diffEngines = [];
		foreach ( $this->diffEngineRegistry->getAllKeys() as $diffEngineName ) {
			$diffEngine = $this->newFromName( $diffEngineName );
			if ( !$diffEngine ) {
				continue;
			}
			$diffEngines[$diffEngineName] = $diffEngine;
		}
		return $diffEngines;
	}
}
