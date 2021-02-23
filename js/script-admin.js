document.addEventListener("DOMContentLoaded", function () {	"use strict"; (function($) {
/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Admin interface

--------------------------------------------------------------*/

/*--------------------------------------------------------------
# Admin interface
--------------------------------------------------------------*/
	
	/* Check meta boxes for content, collapse if empty */	
	if ( !$('#page-top_text').html() ) { $('#page-top').addClass('closed'); }
	if ( !$('#page-bottom_text').html() ) { $('#page-bottom').addClass('closed'); }
	if ( $('#comment_status').is(':checked') || $('#ping_status').is(':checked') ) {		
		$('#commentstatusdiv').removeClass('closed');
	} else {
		$('#commentstatusdiv').addClass('closed');
		$('#commentsdiv').css({'display':'none'});
	}
	setTimeout(function() {
		if ( !$('#wds_title').val() && !$('#wds_metadesc').val() ) {		
			$('#wds-wds-meta-box').addClass('closed');
		} else {
			$('#wds-wds-meta-box').removeClass('closed');
		}
		$('.sui-border-frame').css({'display':'block'});
		$('#poststuff .sui-box-body, #poststuff .wds-focus-keyword, #poststuff .wds-preview-description, #poststuff p.wds-preview-description, #poststuff .wds-edit-meta .sui-button').css({'display':'none'}); 

		$('.wds-seo-analysis-label').click(function() {
			$('.sui-box-body').css({'display':'block'});
			$('.wds-focus-keyword').css({'display':'block'});
		});
	}, 1000);
	
})(jQuery); });