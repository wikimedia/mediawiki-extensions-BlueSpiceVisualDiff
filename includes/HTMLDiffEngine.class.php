<?php

use BlueSpice\VisualDiff\DiffEngine;
use GuzzleHttp\Client;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use Wikimedia\AtEase\AtEase;

class HTMLDiffEngine extends DiffEngine {

	/**
	 * @return string
	 */
	protected function getLabelMsgKey() {
		return 'bs-visualdiff-htmldiffengine-tab';
	}

	/**
	 * @param RevisionRecord $oldRevision
	 * @param RevisionRecord $diffRevision
	 * @return string The HTML for display in diff
	 */
	public function showDiffPage( $oldRevision, $diffRevision ) {
		// Now let's get the diff
		// ensure that the directory exits, otherwise create it
		BsFileSystemHelper::ensureCacheDirectory( 'VisualDiff' );
		$cacheDir = BsFileSystemHelper::getCacheDirectory( 'VisualDiff' );
		// TODO RBV (01.08.12 13:33): TmpPath may not be web accessible
		$this->cleanTmpPath( $cacheDir );

		$filename = $cacheDir
			. '/result_'
			. $oldRevision->getId()
			. '_'
			. $diffRevision->getId()
			. '.html';

		if ( file_exists( $filename ) ) {
			// In this case we don't need to recalculate
			wfDebugLog( 'VisualDiff', 'Using file ' . $filename . ' from cache' );
			return $this->outputVisualDiff( $filename );
		}

		// Get the HTML strings
		$user = RequestContext::getMain()->getUser();
		$oldHTMLPath  = $this->getRevisionHTML( $oldRevision, $cacheDir, $user );
		$diffHTMLPath = $this->getRevisionHTML( $diffRevision, $cacheDir, $user );

		$params = [
			'type' => 'html',
			'wikiId' => WikiMap::getCurrentWikiId()
		];
		$services = MediaWikiServices::getInstance();
		$config = $services->getConfigFactory()->makeConfig( 'bsg' );

		if ( $config->get( 'TestMode' ) ) {
			$params['debug'] = "true";
		}

		$url = wfAppendQuery(
			$config->get( 'VisualDiffHtmlDiffEngineUrl' ) . '/RenderDiff',
			$params
		);

		$options = [
			'timeout' => 120,
			'multipart' => [
				[
					'name'     => 'old',
					'filename' => basename( $oldHTMLPath ),
					'contents' => file_get_contents( $oldHTMLPath ),
				],
				[
					'name'     => 'diff',
					'filename' => basename( $diffHTMLPath ),
					'contents' => file_get_contents( $diffHTMLPath ),
				]
			],
		];

		$urlExpanded = $services->getUrlUtils()->expand( $url, PROTO_CURRENT );
		$client = new Client( $options );
		try {
			$response = $client->request( 'POST', $urlExpanded, $options );
			$contents = $response->getBody()->getContents();
		} catch ( Exception $e ) {
			throw new MWException( $e->getMessage() );
		}

		file_put_contents( $filename, $contents );
		$this->cleanResultHTML( $filename );

		return $this->outputVisualDiff( $filename );
	}

	/**
	 * @param string $outfile
	 * @return string
	 */
	protected function outputVisualDiff( $outfile ) {
		$resulttext = file_get_contents( $outfile );
		return $resulttext;
	}

	/**
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
	 * @param RevisionRecord $oRevision
	 * @param string $cacheDir
	 * @param User $user
	 * @return string The revisions HTML representation
	 */
	protected function getRevisionHTML( $oRevision, $cacheDir, User $user ) {
		// TODO RBV (19.06.12 15:05): Use API to render?
		// TODO RBV (21.06.12 11:55): Use PageContentProvider to render? (see "<source> tag ticket")
		// TODO RBV (21.06.12 12:08): To the contructor?
		$oParserOptions = ParserOptions::newFromUser( $user );
		$content = $oRevision->getContent( 'main' );
		$text = ( $content instanceof TextContent ) ? $content->getText() : '';

		$oParserOutput = MediaWikiServices::getInstance()->getParser()->parse(
			$text,
			Title::newFromID( $oRevision->getPageId() ),
			$oParserOptions
		);

		$sFilePath = "$cacheDir/{$oRevision->getId()}.html";

		file_put_contents( $sFilePath, $oParserOutput->getText( [
			'enableSectionEditLinks' => false
		] ) );

		return $sFilePath;
	}

	/**
	 * Special transformation for DaisyDiff output
	 * @param string $sOutfile
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

			if ( $oElement->parentNode->hasAttribute( 'href' ) ) {
				$oElement->parentNode->removeAttribute( 'href' );
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
