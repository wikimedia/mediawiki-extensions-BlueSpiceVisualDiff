<?php

class UnifiedTextDiffEngine extends HTMLDiffEngine {

	/**
	 *
	 * @param Revision $oOldRevision
	 * @param Revision $oDiffRevision
	 */
	public function showDiffPage( $oOldRevision, $oDiffRevision ) {
		//Now let's get the diff
		$sTmpPath = BsFileSystemHelper::getCacheDirectory( VisualDiff::$sVisualDiffFolderName );
		$this->cleanTmpPath( $sTmpPath ); // TODO RBV (01.08.12 13:33): TmpPath may not be web accessible

		$sResultFile  = $sTmpPath.'/result_ut_'.$oOldRevision->getId().'_'.$oDiffRevision->getId().'.html';

		if( file_exists( $sResultFile ) ){ //In this case we don't need to recalculate
			wfDebugLog( 'VisualDiff', 'Using file '.$sResultFile.' from cache');
			return $this->outputVisualDiff( $sResultFile );
		}

		//Get the WIKI content
		$sOldWIKI = "$sTmpPath/{$oOldRevision->getId()}.wiki";
		file_put_contents( $sOldWIKI, ContentHandler::getContentText( $oOldRevision->getContent() ) );
		$sDiffWIKI = "$sTmpPath/{$oDiffRevision->getId()}.wiki";
		file_put_contents( $sDiffWIKI, ContentHandler::getContentText( $oDiffRevision->getContent() ) );

		$aParams = array(
			'type' => 'tag',
			'wikiId' => wfWikiID()
		);

		$config = \BlueSpice\Services::getInstance()->getConfigFactory()
			->makeConfig( 'bsg' );

		if( $config->get( 'TestMode' ) ) {
			$aParams['debug'] = "true";
		}

		$sUrl = wfAppendQuery(
			$config->get( 'VisualDiffHtmlDiffEngineUrl' ).'/RenderDiff',
			$aParams
		);

		$vHttpEngine = Http::$httpEngine;
		Http::$httpEngine = 'curl';
		$oRequest = MWHttpRequest::factory(
			wfExpandUrl( $sUrl ),
			array(
				'timeout' => 120,
				'method' => 'POST',
				'postData' => array(
					'old'  => class_exists( 'CURLFile' ) ? new CURLFile( $sOldWIKI ) : '@'.$sOldWIKI,
					'diff' => class_exists( 'CURLFile' ) ? new CURLFile( $sDiffWIKI ) : '@'.$sDiffWIKI
				)
			)
		);
		Http::$httpEngine = $vHttpEngine;

		$oStatus = $oRequest->execute();

		if( !$oStatus->isOK() ) {
			throw new MWException( $oStatus->getMessage() );
		}

		$sResponse = $oRequest->getContent();
		file_put_contents( $sResultFile, $sResponse );

		$this->cleanResultHTML( $sResultFile );
		return $this->outputVisualDiff( $sResultFile );
	}

	protected function outputVisualDiff( $outfile ) {
		$resulttext = parent::outputVisualDiff($outfile);
		$resulttext = trim( $resulttext );
		$resulttext = preg_replace( '#<br><br>#si', "\n", $resulttext); //DaisyDiff returns oldschhol <br>
		$resulttext = preg_replace( '#<br>#si', "", $resulttext);
		return '<div class="UnifiedTextDiffEngine"><pre>'.$resulttext.'</pre></div>';;
	}
}