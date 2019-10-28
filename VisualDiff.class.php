<?php
/**
 * VisualDiff for BlueSpice
 *
 * Display diff in a human readable format.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3.
 *
 * This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 *
 * This file is part of BlueSpice MediaWiki
 * For further information visit http://bluespice.com
 *
 * @author     Markus Glaser <glaser@hallowelt.com>
 * @author     Robert Vogel <vogel@hallowelt.com
 * @package    BlueSpice_pro
 * @subpackage VisualDiff
 * @copyright  Copyright (C) 2016 Hallo Welt! GmbH, All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GPL-3.0-only
 * @filesource
 */

/**
 * Base class for VisualDiff extension
 * @package BlueSpice_pro
 * @subpackage VisualDiff
 */
class VisualDiff extends BsExtensionMW {

	protected $aEngines = [ 'HTMLDiffEngine', 'UnifiedTextDiffEngine' ];
	public static $sVisualDiffFolderName = 'VisualDiff';

	/**
	 * Initialization of VisualDiff extension
	 */
	public function  initExt() {
		// Hooks
		$this->setHook(
			'BSUEModulePDFBeforeAddingStyleBlocks',
			'onBSUEModulePDFBeforeAddingStyleBlocks'
		);
		$this->setHook(
			'BSUEModulePDFBeforeCreatePDF',
			'onBSUEModulePDFBeforeCreatePDF'
		);

		global $wgHooks;
		if ( isset( $wgHooks['ArticleContentOnDiff'] )
			&& is_array( $wgHooks['ArticleContentOnDiff'] ) ) {
			// Execute before everything else, i.e. FlaggedRevs //TODO: In FlaggedRevsConnector?
			array_unshift( $wgHooks['ArticleContentOnDiff'], [ $this, 'onArticleContentOnDiff' ] );
		} else {
			$this->setHook( 'ArticleContentOnDiff' );
		}
	}

	/**
	 * Embeds CSS into pdf export
	 * @param array &$aTemplate
	 * @param array &$aStyleBlocks
	 * @return bool Always true to keep hook running
	 */
	public function onBSUEModulePDFBeforeAddingStyleBlocks( &$aTemplate, &$aStyleBlocks ) {
		// Welcome to ResourceLoader changes -.-
		$sFile = __DIR__ . '/resources/bluespice.visualDiff.less';

		$oConfig = ConfigFactory::getDefaultInstance()->makeConfig( 'main' );
		$oCompiler = RequestContext::getMain()
			->getOutput()
			->getResourceLoader()
			->getLessCompiler();

		$aStyleBlocks['VisualDiff'] = $oCompiler->parse(
				file_get_Contents( $sFile ),
				$sFile
		)->getCss();

		$aStyleBlocks[ 'VisualDiff' ] .=
<<<HEREDOC
ul#difftabslist, #bs-vdiff-popup-prev, #bs-vdiff-popup-next {
	display: none;
}
.UnifiedTextDiffEngine, .UnifiedTextDiffEngine pre {
	white-space: pre-wrap;
}
HEREDOC;

		return true;
	}

	/**
	 * Get global LESS variables.
	 *
	 * @param Config|null $config
	 * @since 1.22
	 * @return array Map of variable names to string CSS values.
	 */
	public static function getLessVars( Config $config = null ) {
		if ( is_null( $config ) ) {
			$config = $oConfig = ConfigFactory::getDefaultInstance()
				->makeConfig( 'main' );
		}
		return ResourceLoader::getLessVars( $config );
	}

	/**
	 * Make sure to hide content of not selected tabs
	 * @param BsExportModulePDF $oModule
	 * @param DOMDocument $oDOM
	 * @param SpecialUniversalExport $oCaller
	 * @return bool Always true to keep hook running
	 */
	public function onBSUEModulePDFBeforeCreatePDF( $oModule, $oDOM, $oCaller ) {
		$oDOMXPath = new DOMXPath( $oDOM );
		$oContainerElements = $oDOMXPath->query( "//*[contains(@class, 'diffcontainer')]" );
		foreach ( $oContainerElements as $oContainerElement ) {
			if ( $oContainerElement->getAttribute( 'id' ) == $oCaller->aParams['difftab'] ) {
				// Keep user selected
				continue;
			}
			// Remove all other
			$oContainerElement->parentNode->removeChild( $oContainerElement );
		}
		return true;
	}

	/**
	 * This seems to be a bad hook, because it is only executed when
	 * $user->getBoolOption( 'diffonly' ) === false
	 * @param DifferenceEngine $diffEngine
	 * @param OutputPage $output
	 * @return bool
	 */
	public function onArticleContentOnDiff( $diffEngine, $output ) {
		$output->addModules( 'ext.bluespice.visualDiff' );
		$output->addModuleStyles( 'ext.bluespice.visualDiff.styles' );

		$oCurrentTitle = $output->getTitle();

		$iOldId = $this->getRequest()->getInt( 'oldid', 0 );
		$iDiff  = $this->getRequest()->getVal( 'diff', '0' );

		if ( $iDiff === '0' ) {
			$iDiff = $oCurrentTitle->getLatestRevID();
		}

		// Fallback to latest revision. In some caeses only "diff" is set, but not "oldid".
		if ( $iOldId === 0 ) {
			$oOldRevision = Revision::newFromId( $iDiff );
			// This prevents the code from breaking if there is only one
			// revision yet --> We cannot diff
			if ( $oOldRevision === null ) {
				return true;
			}
			$oOldRevision = $oOldRevision->getPrevious();
		} else {
			$oOldRevision = Revision::newFromId( $iOldId );
		}

		$sClassicDiff = $output->getHTML();
		$output->clearHTML();
		$aClassicDiff = $this->processClassicDiff( $sClassicDiff );

		if ( $oOldRevision == null ) {
			// If the provided base revision is invalid: abort.
			$output->addHTML( wfMessage( 'bs-visualdiff-error-oldid-invalid' )->escaped() );
			return true;
		}

		// TODO: Why not $oCurrentTitle?
		// So the base revision is okay? Let's build the page.
		$oReturnToTitle = $oOldRevision->getTitle();
		$output->setSubtitle(
			Linker::link(
				$oReturnToTitle,
				wfMessage( 'bs-visualdiff-return-to-history' )->plain(),
				[],
				[ 'action' => 'history' ],
				[ 'known', 'noclasses' ]
			)
		);
		$output->addBacklinkSubtitle( $output->getTitle() );

		// maybe null if !is_nummeric( $iDiff ) == false
		$oDiffRevision = Revision::newFromId( $iDiff );
		// $oDiffRevision may be changed by some other Extension like
		// FlaggedRevsConnector (i.e 'cur' means 'last stable' instead of 'last revision')
		$visualDiff = $this;
		$res = Hooks::run( 'VisualDiffRetrieveDiffRevision', [
			&$visualDiff,
			&$oOldRevision,
			&$oDiffRevision,
			$iOldId,
			$iDiff
		] );
		if ( $res ) {
			if ( $iDiff == 'next' ) {
				$oDiffRevision = $oOldRevision->getNext();
			} elseif ( $iDiff == 'prev' ) {
				$oDiffRevision = $oOldRevision->getPrevious();
			} elseif ( $iDiff == 'cur' ) {
				$oDiffRevision = Revision::newFromId(
					$oOldRevision->getTitle()->getLatestRevID()
				);
			}
		}

		if ( $oDiffRevision == null ) {
			// If the revision that should be compared with the base revision is invalid: abort.
			$output->addHTML( wfMessage( 'bs-visualdiff-error-diff-invalid' )->escaped() );
			return true;
		}

		// HINT: $diffEngine->getOldid();$diffEngine->getNewid(); ???
		// Base revision and diff revison are okay? Render the navigation and the headline.
		// $sDiffHead = $this->renderDiffHead( $diffEngine, $oOldRevision, $oDiffRevision );

		$aTabList = [];
		$aDiffs   = [];
		$aTabList[] = '<ul id="difftabslist" class="ui-tabs-nav">';

		foreach ( $this->aEngines as $sEngineClass ) {
			$oEngine = new $sEngineClass();
			$aTabList[] =
			'<li class="ui-state-default ui-corner-top"><a href="#' . $sEngineClass . '">'
				// bs-visualdiff-htmldiffengine-tab, bs-visualdiff-unifiedtextdiffengine-tab
				. wfMessage( 'bs-visualdiff-' . strtolower( $sEngineClass ) . '-tab' )->escaped()
			. '</a></li>';
			$aDiffs[] = '<div id="' . $sEngineClass . '" class="diffcontainer">';
			// $aDiffs[] = $sDiffHead;
			$aDiffs[] = $oEngine->showDiffPage( $oOldRevision, $oDiffRevision );
			$aDiffs[] = '</div>';
		}

		$aTabList[] = '<li class="ui-state-default ui-corner-top"><a href="#ClassicDiffEngine">'
			. wfMessage( 'bs-visualdiff-classicdiffengine-tab' )->escaped()
		. '</a></li>';
		$aTabList[] = '</ul>';

		$output->addHTML( $aClassicDiff['headeritems'] );
		$output->addHTML( $aClassicDiff['revisiontable'] );
		$output->addHTML( '<div id="difftabs" class="ui-widget ui-tabs">' );
		$output->addHTML( implode( "\n", $aTabList ) );
		$output->addHTML( implode( "\n", $aDiffs ) );

		// Add the classic diff
		$output->addHTML( '<div id="ClassicDiffEngine" class="diffcontainer">' );
		$output->addHTML( $aClassicDiff['difftable'] );
		$output->addHTML( '</div>' );
		// #difftabs
		$output->addHTML( '</div>' );
		$output->addHTML( $aClassicDiff['afterdiff'] );

		// As we return false at the end of the handler and therefore skip
		// further processing in the caller we need to populate the OutputPage
		// with some information that might be needed by others (e.g.
		// "FlaggedRevsConnector")
		$output->setRevisionId( $oDiffRevision->getId() );
		$output->setRevisionTimestamp( $oDiffRevision->getTimestamp() );
		$output->setArticleFlag( true );

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
