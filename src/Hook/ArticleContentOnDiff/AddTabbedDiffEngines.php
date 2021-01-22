<?php

namespace BlueSpice\VisualDiff\Hook\ArticleContentOnDiff;

use BlueSpice\Hook\ArticleContentOnDiff;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Html;
use HtmlArmor;
use Title;

class AddTabbedDiffEngines extends ArticleContentOnDiff {

	protected function doProcess() {
		$this->output->addModules( 'ext.bluespice.visualDiff' );
		$this->output->addModuleStyles( 'ext.bluespice.visualDiff.styles' );

		$oCurrentTitle = $this->output->getTitle();

		$iOldId = $this->output->getRequest()->getInt( 'oldid', 0 );
		$iDiff  = $this->output->getRequest()->getVal( 'diff', '0' );

		if ( $iDiff === '0' ) {
			$iDiff = $oCurrentTitle->getLatestRevID();
		}

		$revisionLookup = $this->getServices()->getRevisionLookup();
		// Fallback to latest revision. In some caeses only "diff" is set, but not "oldid".
		if ( $iOldId === 0 ) {
			$oOldRevision = $revisionLookup->getRevisionById( $iDiff );
			// This prevents the code from breaking if there is only one
			// revision yet --> We cannot diff
			if ( $oOldRevision === null ) {
				return true;
			}
			$oOldRevision = $revisionLookup->getPreviousRevision( $oOldRevision );
		} else {
			$oOldRevision = $revisionLookup->getRevisionById( $iOldId );
		}

		$sClassicDiff = $this->output->getHTML();
		$this->output->clearHTML();
		$aClassicDiff = $this->processClassicDiff( $sClassicDiff );

		if ( $oOldRevision == null ) {
			// If the provided base revision is invalid: abort.
			$this->output->addHTML(
				$this->msg( 'bs-visualdiff-error-oldid-invalid' )->escaped()
			);
			return true;
		}

		// TODO: Why not $oCurrentTitle?
		// So the base revision is okay? Let's build the page.
		$oReturnToTitle = Title::newFromID( $oOldRevision->getPageId() );
		$this->output->setSubtitle(
			$this->getServices()->getLinkRenderer()->makeLink(
				$oReturnToTitle,
				new HtmlArmor( $this->msg( 'bs-visualdiff-return-to-history' )->plain() ),
				[ 'known', 'noclasses' ],
				[ 'action' => 'history' ]
			)
		);
		$this->output->addBacklinkSubtitle( $this->output->getTitle() );

		// maybe null if !is_nummeric( $iDiff ) == false
		$oDiffRevision = $revisionLookup->getRevisionById( $iDiff );
		// $oDiffRevision may be changed by some other Extension like
		// FlaggedRevsConnector (i.e 'cur' means 'last stable' instead of 'last revision')
		$visualDiff = $this;
		$res = $this->getServices()->getHookContainer()->run(
			'VisualDiffRetrieveDiffRevision',
			[
				&$visualDiff,
				&$oOldRevision,
				&$oDiffRevision,
				$iOldId,
				$iDiff
			]
		);
		if ( $res ) {
			if ( $iDiff == 'next' ) {
				$oDiffRevision = $revisionLookup->getNextRevision( $oOldRevision );
			} elseif ( $iDiff == 'prev' ) {
				$oDiffRevision = $oOldRevision;
				$oOldRevision = $revisionLookup->getPreviousRevision( $oDiffRevision );
			} elseif ( $iDiff == 'cur' ) {
				$title = Title::newFromID( $oOldRevision->getPageId() );
				$oDiffRevision = $revisionLookup->getRevisionById( $title->getLatestRevID() );
			}
		}

		if ( $oDiffRevision == null ) {
			// If the revision that should be compared with the base revision is invalid: abort.
			$this->output->addHTML(
				$this->msg( 'bs-visualdiff-error-diff-invalid' )->escaped()
			);
			return true;
		}

		// HINT: $diffEngine->getOldid();$diffEngine->getNewid(); ???
		// Base revision and diff revison are okay? Render the navigation and the headline.
		// $sDiffHead = $this->renderDiffHead( $diffEngine, $oOldRevision, $oDiffRevision );

		$aTabList = [];
		$aDiffs   = [];
		$aTabList[] = Html::openElement( 'ul', [
			'id' => 'difftabslist',
			'class' => 'ui-tabs-nav'
		] );

		$factory = $this->getServices()->getService(
			'BSVisualDiffDiffEngineFactory'
		);
		foreach ( $factory->getDiffEngines() as $oEngine ) {
			// hacky, backwards compatible, so related JS and CSS still works
			$sEngineClass = get_class( $oEngine );
			$aTabList[] = Html::openElement( 'li', [
				'class' => 'ui-state-default ui-corner-top'
			] );
			$aTabList[] = Html::element(
				'a',
				[ 'href' => "#$sEngineClass" ],
				$oEngine->getLabel()->escaped()
			);
			$aTabList[] = Html::closeElement( 'li' );
			$aDiffs[] = Html::openElement( 'div', [
				'id' => $sEngineClass,
				'class' => 'diffcontainer',
			] );
			$aDiffs[] = $oEngine->showDiffPage( $oOldRevision, $oDiffRevision );
			$aDiffs[] = Html::closeElement( 'div' );
		}

		$aTabList[] = Html::openElement( 'li', [
			'class' => 'ui-state-default ui-corner-top'
		] );
		$aTabList[] = Html::element(
			'a',
			[ 'href' => "#ClassicDiffEngine" ],
			$this->msg( 'bs-visualdiff-classicdiffengine-tab' )->escaped()
		);
		$aTabList[] = Html::closeElement( 'li' );

		$aTabList[] = Html::closeElement( 'ul' );

		$this->output->addHTML( $aClassicDiff['headeritems'] );
		$this->output->addHTML( $aClassicDiff['revisiontable'] );
		$this->output->addHTML( Html::openElement( 'div', [
			'id' => 'difftabs',
			'class' => 'ui-widget ui-tabs' ]
		) );
		$this->output->addHTML( implode( "\n", $aTabList ) );
		$this->output->addHTML( implode( "\n", $aDiffs ) );

		// Add the classic diff
		$this->output->addHTML( Html::openElement( 'div', [
			'id' => 'ClassicDiffEngine',
			'class' => 'diffcontainer'
		] ) );
		$this->output->addHTML( $aClassicDiff['difftable'] );
		$this->output->addHTML( Html::closeElement( 'div' ) );
		// #difftabs
		$this->output->addHTML( Html::closeElement( 'div' ) );
		$this->output->addHTML( $aClassicDiff['afterdiff'] );

		// As we return false at the end of the handler and therefore skip
		// further processing in the caller we need to populate the OutputPage
		// with some information that might be needed by others (e.g.
		// "FlaggedRevsConnector")
		$this->output->setRevisionId( $oDiffRevision->getId() );
		$this->output->setRevisionTimestamp( $oDiffRevision->getTimestamp() );
		$this->output->setArticleFlag( true );

		return false;
	}

	/**
	 * parses the old diff and extracts various parts that can be used as common elements
	 * @param string $sClassicDiff
	 * @return array
	 */
	private function processClassicDiff( $sClassicDiff ) {
		$aResult = [
			'headeritems'   => '',
			'revisiontable' => '',
			'difftable'     => '',
			'afterdiff'     => ''
		];

		$oClassicDiffDOM = new DOMDocument();
		libxml_use_internal_errors( true );
		$oClassicDiffDOM->loadHTML(
			"<?xml encoding=\"UTF-8\"><html><body>$sClassicDiff</body></html>"
		);
		libxml_clear_errors();
		$oBodyElement = $oClassicDiffDOM->documentElement->childNodes->item( 0 );

		$aFirstLevelNodes = [];
		foreach ( $oBodyElement->childNodes as $oChild ) {
			$aFirstLevelNodes[] = $oChild;
		}

		foreach ( $aFirstLevelNodes as $oNode ) {
			if ( $oNode instanceof DOMElement == false ) { continue;
			}
			$aNodeClasses = explode( ' ', $oNode->getAttribute( 'class' ) );
			if ( $oNode->nodeName == 'table' && in_array( 'diff', $aNodeClasses ) ) {
				$aTableChildNodes = [];
				foreach ( $oNode->childNodes as $oChild ) {
					$aTableChildNodes[] = $oChild;
				}

				foreach ( $aTableChildNodes as $oChildNode ) {
					if ( $oChildNode->nodeName == 'tr' ) {
						$aResult['revisiontable'] = Html::openElement( 'table', [
							'class' => 'revisiontable'
						] );
						$aResult['revisiontable'] .= $oClassicDiffDOM->saveHTML(
							$oChildNode
						);
						$aResult['revisiontable'] .= Html::closeElement( 'table' );
						$oChildNode->parentNode->removeChild( $oChildNode );
						break;
					}
				}
				$aResult['difftable'] = $oClassicDiffDOM->saveHTML( $oNode );
				$oNode->parentNode->removeChild( $oNode );
				break;
			}

			$aResult['headeritems'] .= $oClassicDiffDOM->saveHTML( $oNode );
			$oNode->parentNode->removeChild( $oNode );
		}

		// We remove the "current version" heading because we would have to
		// render the content by ourselfs. The MW DifferenceEngine doesn't do it for us.
		$oDOMXPath = new DOMXPath( $oClassicDiffDOM );
		$oCurrentVersionHeadings = $oDOMXPath->query( "//h2[@class='diff-currentversion-title']" );
		if ( $oCurrentVersionHeadings->item( 0 ) != null ) {
			$oCurrentVersionHeadings->item( 0 )->parentNode->removeChild(
					$oCurrentVersionHeadings->item( 0 )
			);
		}

		$bodyInnerHTML = preg_replace(
			'#<body>(.*?)<\/body>#si',
			'$1',
			$oClassicDiffDOM->saveHTML( $oBodyElement )
		);
		$aResult['afterdiff'] = trim( $bodyInnerHTML );

		return $aResult;
	}

}
