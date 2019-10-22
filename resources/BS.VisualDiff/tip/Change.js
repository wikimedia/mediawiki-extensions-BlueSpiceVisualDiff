Ext.define( 'BS.VisualDiff.tip.Change', {
	extend: 'Ext.tip.QuickTip',

	anchor: 'left',
	autoHide: false,
	minWidth: 300,

	$target: null,

	constructor: function( cfg ) {
		this.target = cfg.$target[0];
		this.title = '#' + cfg.$target.data('changeid');

		var text = '';

		if(cfg.$target.hasClass( 'diff-html-added' ) ) {
			text = mw.message('bs-VisualDiff-added_text').plain();
		}
		else if( cfg.$target.hasClass( 'diff-html-removed' ) ) {
			text = mw.message('bs-VisualDiff-removed_text').plain();
		}
		else {
			text = mw.message('bs-VisualDiff-changed_text').plain();
		}

		this.html = text;

		this.callParent( arguments );
	},

	initComponent: function() {
		this.btnNext = new Ext.Button({
			tooltip: mw.message('bs-VisualDiff-popup-next').plain(),
			iconCls: 'x-tbar-page-next'
		});
		this.btnNext.on( 'click', this.onBtnNextClick, this );

		this.btnPrev = new Ext.Button({
			tooltip: mw.message('bs-VisualDiff-popup-prev').plain(),
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