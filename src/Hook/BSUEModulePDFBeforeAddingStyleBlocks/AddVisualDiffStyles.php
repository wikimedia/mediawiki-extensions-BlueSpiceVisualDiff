<?php

namespace BlueSpice\VisualDiff\Hook\BSUEModulePDFBeforeAddingStyleBlocks;

use BlueSpice\UEModulePDF\Hook\BSUEModulePDFBeforeAddingStyleBlocks;

class AddVisualDiffStyles extends BSUEModulePDFBeforeAddingStyleBlocks {

	protected function doProcess() {
		$file = dirname( dirname( dirname( __DIR__ ) ) )
			. '/resources/bluespice.visualDiff.less';

		$compiler = $this->getContext()
			->getOutput()
			->getResourceLoader()
			->getLessCompiler();

		$this->styleBlocks['VisualDiff'] = $compiler->parse(
				file_get_Contents( $file ),
				$file
		)->getCss();

		$this->styleBlocks[ 'VisualDiff' ] .=
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
}
