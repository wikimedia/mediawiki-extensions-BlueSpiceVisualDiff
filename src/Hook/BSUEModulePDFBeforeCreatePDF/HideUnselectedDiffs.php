<?php
namespace BlueSpice\VisualDiff\Hook\BSUEModulePDFBeforeCreatePDF;

use BlueSpice\UEModulePDF\Hook\BSUEModulePDFBeforeCreatePDF;
use DOMXPath;

class HideUnselectedDiffs extends BSUEModulePDFBeforeCreatePDF {

	/**
	 * @return bool
	 */
	protected function doProcess() {

		if ( $this->specs->getParam( 'difftab' ) === null ) {
			return true;
		}

		$finder = new DOMXPath( $this->DOM );
		$containerElements = $finder->query( "//*[contains(@class, 'diffcontainer')]" );
		foreach ( $containerElements as $containerElement ) {
			if ( $containerElement->getAttribute( 'id' ) == $this->specs->getParam( 'difftab' ) ) {
				// Keep user selected
				continue;
			}
			// Remove all other
			$containerElement->parentNode->removeChild( $containerElement );
		}
		return true;
	}
}
