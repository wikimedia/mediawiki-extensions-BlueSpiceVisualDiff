Ext.define( 'BS.VisualDiff.tip.Change', {
	extend: 'Ext.tip.ToolTip',

	anchor: 'bottom',
	autoHide: false,
	showDelay: false,
	hideDelay: 0,
	minWidth: 300,

	$target: null,

	constructor: function( cfg ) {
		this.target = cfg.$target[0];

		var text = '';

		if(cfg.$target.hasClass( 'diff-html-added' ) ) {
			text = mw.message('bs-visualdiff-added-text').plain();
		}
		else if( cfg.$target.hasClass( 'diff-html-removed' ) ) {
			text = mw.message('bs-visualdiff-removed-text').plain();
		}
		else {
			text = mw.message('bs-visualdiff-changed-text').plain();
		}

		this.html = text;

		this.callParent( arguments );
	},

	initComponent: function() {
		this.btnNext = new Ext.Button({
			tooltip: mw.message('bs-visualdiff-popup-next').plain(),
			iconCls: 'x-tbar-page-next'
		});
		this.btnNext.on( 'click', this.onBtnNextClick, this );

		this.btnPrev = new Ext.Button({
			tooltip: mw.message('bs-visualdiff-popup-prev').plain(),
			iconCls: 'x-tbar-page-prev'
		});
		this.btnPrev.on( 'click', this.onBtnPrevClick, this );


		this.dockedItems = [{
			xtype: 'toolbar',
			dock: 'bottom',
			//ui: 'footer',
			items: [
				this.btnPrev,
				'->',
				this.btnNext
			]
		}];

		if( this.$target.data('next') === 'last-diff' ) {
			this.btnNext.hide();
		}
		if( this.$target.data('previous') === 'first-diff' ) {
			this.btnPrev.hide();
		}

		this.callParent( arguments );
	},

	onBtnNextClick: function() {
		this.close();
		bs.visualDiff.selectNext( this.$target );
	},

	onBtnPrevClick: function() {
		this.close();
		bs.visualDiff.selectPrev( this.$target );
	}
});