<?php

use BlueSpice\VisualDiff\Http\Curl11;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;

class UnifiedTextDiffEngine extends HTMLDiffEngine {

	/**
	 *
	 * @return string
	 */
	protected function getLabelMsgKey() {
		return 'bs-visualdiff-unifiedtextdiffengine-tab';
	}

	/**
	 *
	 * @param RevisionRecord $oOldRevision
	 * @param RevisionRecord $oDiffRevision
	 * @return string
	 */
	public function showDiffPage( $oOldRevision, $oDiffRevision ) {
		// Now let's get the diff
		$sTmpPath = BsFileSystemHelper::getCacheDirectory( 'VisualDiff' );
		// TODO RBV (01.08.12 13:33): TmpPath may not be web accessible
		$this->cleanTmpPath( $sTmpPath );

		$sResultFile = $sTmpPath
			. '/result_ut_'
			. $oOldRevision->getId()
			. '_'
			. $oDiffRevision->getId()
			. '.html';

		if ( file_exists( $sResultFile ) ) {
			// In this case we don't need to recalculate
			wfDebugLog( 'VisualDiff', 'Using file ' . $sResultFile . ' from cache' );
			return $this->outputVisualDiff( $sResultFile );
		}

		// Get the WIKI content
		$sOldWIKI = "$sTmpPath/{$oOldRevision->getId()}.wiki";
		file_put_contents(
			$sOldWIKI,
			ContentHandler::getContentText( $oOldRevision->getContent( 'main' ) )
		);
		$sDiffWIKI = "$sTmpPath/{$oDiffRevision->getId()}.wiki";
		file_put_contents(
			$sDiffWIKI,
			ContentHandler::getContentText( $oDiffRevision->getContent( 'main' ) )
		);

		$aParams = [
			'type' => 'tag',
			'wikiId' => WikiMap::getCurrentWikiId()
		];

		$config = MediaWikiServices::getInstance()->getConfigFactory()
			->makeConfig( 'bsg' );

		if ( $config->get( 'TestMode' ) ) {
			$aParams['debug'] = "true";
		}

		$sUrl = wfAppendQuery(
			$config->get( 'VisualDiffHtmlDiffEngineUrl' ) . '/RenderDiff',
			$aParams
		);

		$options = [
			'timeout' => 120,
			'method' => 'POST',
			'postData' => [
				'old'  => class_exists( 'CURLFile' ) ? new CURLFile( $sOldWIKI ) : '@' . $sOldWIKI,
				'diff' => class_exists( 'CURLFile' ) ? new CURLFile( $sDiffWIKI ) : '@' . $sDiffWIKI
			]
		];
		if ( $config->get( 'VisualDiffForceCurlHttp11' ) ) {
			$oRequest = new Curl11(
				wfExpandUrl( $sUrl ),
				$options,
				__METHOD__,
				Profiler::instance()
			);
		} else {
			$oRequest = MediaWikiServices::getInstance()->getHttpRequestFactory()->create(
				wfExpandUrl( $sUrl ),
				$options,
				__METHOD__
			);
		}
		$oStatus = $oRequest->execute();

		if ( !$oStatus->isOK() ) {
			throw new MWException( $oStatus->getMessage() );
		}

		$sResponse = $oRequest->getContent();
		file_put_contents( $sResultFile, $sResponse );

		$this->cleanResultHTML( $sResultFile );
		return $this->outputVisualDiff( $sResultFile );
	}

	/**
	 *
	 * @param string $outfile
	 * @return string
	 */
	protected function outputVisualDiff( $outfile ) {
		$resulttext = parent::outputVisualDiff( $outfile );
		$resulttext = trim( $resulttext );
		// DaisyDiff returns oldschhol <br>
		$resulttext = preg_replace( '#<br><br>#si', "\n", $resulttext );
		$resulttext = preg_replace( '#<br>#si', "", $resulttext );
		return '<div class="UnifiedTextDiffEngine"><pre>' . $resulttext . '</pre></div>';
	}
}
