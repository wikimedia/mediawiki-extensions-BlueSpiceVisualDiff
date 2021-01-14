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
 * For further information visit https://bluespice.com
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

	public static $sVisualDiffFolderName = 'VisualDiff';

	/**
	 * Initialization of VisualDiff extension
	 */
	public function  initExt() {
		$this->setHook(
			'BSUEModulePDFBeforeCreatePDF',
			'onBSUEModulePDFBeforeCreatePDF'
		);
	}

	public static function onRegistration() {
		$GLOBALS['wgExtensionFunctions'][] = function () {
			if ( !isset( $GLOBALS['wgHooks']['ArticleContentOnDiff'] ) ) {
				$GLOBALS['wgHooks']['ArticleContentOnDiff'] = [];
			}
			array_unshift(
				$GLOBALS['wgHooks']['ArticleContentOnDiff'],
				"\\BlueSpice\\VisualDiff\\Hook\\ArticleContentOnDiff\\"
					. "AddTabbedDiffEngines::callback"
			);
		};
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
}
