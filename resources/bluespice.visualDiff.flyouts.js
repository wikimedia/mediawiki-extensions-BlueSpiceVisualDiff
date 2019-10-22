$(document).on('click', '#difftabs span[class^="diff-html-"]', function() {

	bs.visualDiff.select( $(this) );
});

Ext.onReady( function(){
	//Adapt thumbnails
	$('#HTMLDiffEngine img.thumbimage').each(function(){
		$(this).parents('.thumbinner').css('width', '');
	});

	$('#HTMLDiffEngine a').attr('href', '');

	//Wire up hotkeys
	$(document).keyup(function(e) {
		if( e.ctrlKey === false ) {
			return; //Only if Crtl key is pressed.
		}

		/*
		* 34 -> Page up
		* 33 -> Page down
		* 37 -> Left arrow
		* 38 -> Up arrow
		* 39 -> Right arrow
		* 40 -> Down arrow
		*/
		if( e.keyCode === 38 || e.keyCode === 37 ) {
			bs.visualDiff.selectPrev();
		}
		else if( e.keyCode === 40 || e.keyCode === 39 ){
			bs.visualDiff.selectNext();
		}

		e.preventDefault();
		return false;
	});
});

(function(mw, $, bs, d, undefined ){

	var _$current = null;

	function _getCurrent() {
		if( !_$current ) {
			_$current = $('#difftabs span[class^="diff-html-"]').first();
		}
		return _$current;
	}

	function _showToolTip( $target ) {
		var tip = $target.data( 'tip' );
		if( !tip ) {
			tip = Ext.create('BS.VisualDiff.tip.Change', {
				$target: $target
			});
			$target.data( 'tip', tip );
		}

		tip.show();
	}

	bs.visualDiff = {
		select: function( $target ) {
			_$current = $target;

			//Remove all other
			$('#difftabs span[class^="diff-html-"]').removeClass('diff-html-selected');

			//Addo to this
			_$current.addClass('diff-html-selected');

			_showToolTip( _$current );

			//window.location.hash = _$current.data( 'changeid' );
		},
		selectNext: function( $current ) {
			$current = $current || _getCurrent();
			var $next = $('#difftabs').find( '#' + $current.data('next') );
			bs.visualDiff.select( $next );
		},
		selectPrev: function( $current ) {
			$current = $current || _getCurrent();
			var $previous = $('#difftabs').find( '#' + $current.data('previous') );
			bs.visualDiff.select( $previous );
		}
	};
})(mediaWiki, jQuery, blueSpice, document );