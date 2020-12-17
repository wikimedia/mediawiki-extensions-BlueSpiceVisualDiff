<?php

namespace BlueSpice\VisualDiff;

use Config;
use MediaWiki\MediaWikiServices;
use Message;
use RequestContext;

abstract class DiffEngine implements IDiffEngine {

	/**
	 *
	 * @var Config
	 */
	protected $config = null;

	/**
	 *
	 * @param string $name
	 * @param Config $config
	 */
	protected function __construct( $name, Config $config ) {
		$this->name = $name;
		$this->config = $config;
	}

	/**
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * @return Message
	 */
	public function getLabel() {
		return RequestContext::getMain()->msg( $this->getLabelMsgKey() );
	}

	/**
	 * @return string
	 */
	abstract protected function getLabelMsgKey();

	/**
	 *
	 * @param string $name
	 * @param MediaWikiServices $services
	 * @param Config $config
	 * @return IDiffEngine
	 */
	public static function factory( $name, MediaWikiServices $services, Config $config ) {
		return new static( $name, $config );
	}

}
