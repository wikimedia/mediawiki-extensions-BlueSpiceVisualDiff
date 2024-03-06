<?php

use GuzzleHttp\Client;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;

class UnifiedTextDiffEngine extends HTMLDiffEngine {

	/**
	 * @return string
	 */
	protected function getLabelMsgKey() {
		return 'bs-visualdiff-unifiedtextdiffengine-tab';
	}

	/**
	 * @param RevisionRecord $oldRevision
	 * @param RevisionRecord $diffRevision
	 * @return string
	 */
	public function showDiffPage( $oldRevision, $diffRevision ) {
		// Now let's get the diff
		$cacheDir = BsFileSystemHelper::getCacheDirectory( 'VisualDiff' );
		// TODO RBV (01.08.12 13:33): TmpPath may not be web accessible
		$this->cleanTmpPath( $cacheDir );

		$filename = $cacheDir
			. '/result_ut_'
			. $oldRevision->getId()
			. '_'
			. $diffRevision->getId()
			. '.html';

		if ( file_exists( $filename ) ) {
			// In this case we don't need to recalculate
			wfDebugLog( 'VisualDiff', 'Using file ' . $filename . ' from cache' );
			return $this->outputVisualDiff( $filename );
		}

		// Get the WIKI content
		$oldWikiPath = "$cacheDir/{$oldRevision->getId()}.wiki";
		$oldContent = $oldRevision->getContent( 'main' );
		$oldText = ( $oldContent instanceof TextContent ) ? $oldContent->getText() : '';
		file_put_contents(
			$oldWikiPath,
			$oldText
		);
		$diffWikiPath = "$cacheDir/{$diffRevision->getId()}.wiki";
		$diffContent = $diffRevision->getContent( 'main' );
		$diffText = ( $diffContent instanceof TextContent ) ? $diffContent->getText() : '';
		file_put_contents(
			$diffWikiPath,
			$diffText
		);

		$params = [
			'type' => 'tag',
			'wikiId' => WikiMap::getCurrentWikiId()
		];

		$services = MediaWikiServices::getInstance();
		$config = $services->getConfigFactory()->makeConfig( 'bsg' );

		if ( $config->get( 'TestMode' ) ) {
			$params['debug'] = "true";
		}

		$sUrl = wfAppendQuery(
			$config->get( 'VisualDiffHtmlDiffEngineUrl' ) . '/RenderDiff',
			$params
		);

		$options = [
			'timeout' => 120,
			'multipart' => [
				[
					'name'     => 'old',
					'filename' => basename( $oldWikiPath ),
					'contents' => file_get_contents( $oldWikiPath )
				],
				[
					'name'     => 'diff',
					'filename' => basename( $diffWikiPath ),
					'contents' => file_get_contents( $diffWikiPath ),
				]
			],
		];

		$urlExpanded = $services->getUrlUtils()->expand( $sUrl, PROTO_CURRENT );
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
		$resulttext = parent::outputVisualDiff( $outfile );
		$resulttext = trim( $resulttext );
		// DaisyDiff returns oldschhol <br>
		$resulttext = preg_replace( '#<br><br>#si', "\n", $resulttext );
		$resulttext = preg_replace( '#<br>#si', "", $resulttext );

		return '<div class="UnifiedTextDiffEngine"><pre>' . $resulttext . '</pre></div>';
	}
}
