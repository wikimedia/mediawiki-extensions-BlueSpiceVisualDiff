<?php

use Wikimedia\AtEase\AtEase;

class HTMLDiffEngine {

	/**
	 *
	 * @param Revision $oOldRevision
	 * @param Revision $oDiffRevision
	 * @return string The HTML for display in diff
	 */
	public function showDiffPage( $oOldRevision, $oDiffRevision ) {
		// Now let's get the diff
		// ensure that the directory exits, otherwise create it
		BsFileSystemHelper::ensureCacheDirectory( VisualDiff::$sVisualDiffFolderName );
		$sTmpPath = BsFileSystemHelper::getCacheDirectory( VisualDiff::$sVisualDiffFolderName );
		// TODO RBV (01.08.12 13:33): TmpPath may not be web accessible
		$this->cleanTmpPath( $sTmpPath );

		$sResultFile = $sTmpPath
			. '/result_'
			. $oOldRevision->getId()
			. '_'
			. $oDiffRevision->getId()
			. '.html';

		if ( file_exists( $sResultFile ) ) {
			// In this case we don't need to recalculate
			wfDebugLog( 'VisualDiff', 'Using file ' . $sResultFile . ' from cache' );
			return $this->outputVisualDiff( $sResultFile );
		}

		// Get the HTML strings
		$sOldHTML  = $this->getRevisionHTML( $oOldRevision, $sTmpPath );
		$sDiffHTML = $this->getRevisionHTML( $oDiffRevision, $sTmpPath );

		$aParams = [
			'type' => 'html',
			'wikiId' => wfWikiID()
		];
		$config = \BlueSpice\Services::getInstance()->getConfigFactory()
			->makeConfig( 'bsg' );

		if ( $config->get( 'TestMode' ) ) {
			$aParams['debug'] = "true";
		}

		$sUrl = wfAppendQuery(
			$config->get( 'VisualDiffHtmlDiffEngineUrl' ) . '/RenderDiff',
			$aParams
		);

		$vHttpEngine = Http::$httpEngine;
		Http::$httpEngine = 'curl';
		$oRequest = MWHttpRequest::factory(
			wfExpandUrl( $sUrl ),
			[
				'timeout' => 120,
				'method' => 'POST',
				'postData' => [
					'old'  => class_exists( 'CURLFile' ) ? new CURLFile( $sOldHTML ) : '@' . $sOldHTML,
					'diff' => class_exists( 'CURLFile' ) ? new CURLFile( $sDiffHTML ) : '@' . $sDiffHTML
				]
			]
		);
		Http::$httpEngine = $vHttpEngine;

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
		$resulttext = file_get_contents( $outfile );
		return $resulttext;
	}

	/**
	 *
	 * @param string $sPath
	 */
	protected function cleanTmpPath( $sPath ) {
		$oDirIterator = new DirectoryIterator( $sPath );
		foreach ( $oDirIterator as $oFileInfo ) {
			if ( $oFileInfo->isDir() || $oFileInfo->isDot() ) {
				continue;
			}
			// TODO RBV (21.06.12 14:38): Maybe we should _not_ delete the "result_"
			// files as they will never ever change...
			if ( $oFileInfo->getMTime() < time() - 3600 * 24 * 5 ) {
				// Older than 5 days?
				wfDebugLog( 'VisualDiff', 'Removed file ' . $oFileInfo->getRealPath() );
				// TODO RBV (19.06.12 15:47): SPL?
				unlink( $oFileInfo->getRealPath() );
			}
		}
	}

	/**
	 * Renders the HTML of a Revision that should be compared
	 * @param Revision $oRevision
	 * @param string $sTmpPath
	 * @return string The revisions HTML representation
	 */
	protected function getRevisionHTML( $oRevision, $sTmpPath ) {
		// phpcs:ignore MediaWiki.Usage.DeprecatedGlobalVariables.Deprecated$wgParser
		global $wgUser, $wgParser;
		// TODO RBV (19.06.12 15:05): Use API to render?
		// TODO RBV (21.06.12 11:55): Use PageContentProvider to render? (see "<source> tag ticket")
		// TODO RBV (21.06.12 12:08): To the contructor?
		$oParserOptions = ParserOptions::newFromUser( $wgUser );
		$oParserOutput = $wgParser->parse(
			ContentHandler::getContentText( $oRevision->getContent() ),
			$oRevision->getTitle(),
			$oParserOptions
		);

		$sFilePath = "$sTmpPath/{$oRevision->getId()}.html";

		file_put_contents( $sFilePath, $oParserOutput->getText( [
			'enableSectionEditLinks' => false
		] ) );
		return $sFilePath;
	}

	/**
	 * Special transformation for DaisyDiff output
	 * @param type $sOutfile
	 */
	protected function cleanResultHTML( $sOutfile ) {
		// In a diff html there may be duplicate IDs which results in warnings
		AtEase::suppressWarnings();
		$oDOM = new DOMDocument();
		$oDOM->loadHTMLFile( $sOutfile );
		$oDOM->formatOutput = true;

		$oDOMXPath = new DOMXPath( $oDOM );
		$oRemoveClassElements = $oDOMXPath->query( '//*[contains(@class, "diff-topbar")]' );
		$aRemoveClassElements = [];
		foreach ( $oRemoveClassElements as $oRemoveClassElement ) {
			$aRemoveClassElements[] = $oRemoveClassElement;
		}
		foreach ( $aRemoveClassElements as $oRemoveClassElement ) {
			$oRemoveClassElement->parentNode->removeChild( $oRemoveClassElement );
		}

		// Remove script-Tags
		$oScripts = $oDOM->getElementsByTagName( 'script' );
		$aScripts = [];
		foreach ( $oScripts as $oScript ) {
			// Due to 'live' character of NodeList we have to build an array to avoid
			// iterator issues
			$aScripts[] = $oScript;
		}
		foreach ( $aScripts as $oScriptElement ) {
			$oScriptElement->parentNode->removeChild( $oScriptElement );
		}

		$oElements = $oDOMXPath->query( '//span|//img' );
		foreach ( $oElements as $oElement ) {
			$sClass = $oElement->getAttribute( 'class' );
			// TODO RBV (03.08.12 15:08): Maybe model this into XPath?
			if ( !in_array( $sClass, [ 'diff-html-removed', 'diff-html-added', 'diff-html-changed' ] )
				&& $oElement->nodeName != 'img' ) {
				continue;
			}

			// Let's build standard conformant attributes
			$aModAttr = [ 'previous', 'next', 'changeid', 'changes', 'changetype' ];
			foreach ( $aModAttr as $sAttr ) {
				if ( !$oElement->hasAttribute( $sAttr ) ) { continue;
				}
				$oElement->setAttribute( 'data-' . $sAttr, $oElement->getAttribute( $sAttr ) );
				$oElement->removeAttribute( $sAttr );
			}

			$oElement->removeAttribute( 'onclick' );
			$oElement->removeAttribute( 'onload' );
			$oElement->removeAttribute( 'onabort' );
			$oElement->removeAttribute( 'onerror' );
		}

		// TODO RBV (01.08.12 13:31): Find a DOM way to do this... :(
		$sResultContent = preg_replace(
			[
				'#(<!DOCTYPE.*?<html>.*?<body>)#si',
				'#(<\/body>.*?<\/html>)#si'
			],
			'',
			$oDOM->saveHTML()
		);
		AtEase::suppressWarnings( true );
		file_put_contents( $sOutfile, $sResultContent );
	}
}
