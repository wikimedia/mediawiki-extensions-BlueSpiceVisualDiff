<?php

namespace BlueSpice\VisualDiff;

use MediaWiki\Revision\RevisionRecord;
use Message;

interface IDiffEngine {

	/**
	 * @param RevisionRecord $oldRevision
	 * @param RevisionRecord $diffRevision
	 * @return string The HTML for display in diff
	 */
	public function showDiffPage( $oldRevision, $diffRevision );

	/**
	 * @return string
	 */
	public function getName();

	/**
	 * @return Message
	 */
	public function getLabel();
}
