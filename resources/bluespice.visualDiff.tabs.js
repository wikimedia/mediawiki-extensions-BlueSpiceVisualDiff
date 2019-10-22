( function( mw, $, bs ) {
	$("#difftabs").tabs({
		cookie: {
			expires: 30,
			name: mw.config.get( 'wgCookiePrefix' ) + 'bs-visualdiff-tabs'
		},
		show: function( event, ui ) {
			$('#bs-widget-universalexport a, #bs-ta-uemodulepdf, .bs-ue-export-link').each(function(){
				if( !this.href ) {
					return;
				}
				var params = bs.util.getUrlParams( this.href );
				var baseurl = this.href.split('?');
				baseurl = baseurl[0];
				params["ue[difftab]"] = ui.panel.id;
				params = $.param(params);
				this.href = baseurl + "?" + params;
			});
		}
	});
} )( mediaWiki, jQuery, blueSpice );