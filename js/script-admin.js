document.addEventListener("DOMContentLoaded", function () {	"use strict"; (function($) {
/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Admin interface

--------------------------------------------------------------*/

/*--------------------------------------------------------------
# Admin interface
--------------------------------------------------------------*/
	
	/* Control color of Top 10 Most Visited Days box */
	$("#battleplan_site_stats tr").each(function(){
		var getAge = 100 - $(this).attr("data-age");
		getAge = getAge * 2;
		if (getAge < 0) { getAge = 0; }
		$(this).find('td').css({ "filter": "saturate("+getAge+"%)" });
	});
	
	/* Control color of Visitor Trends box */
	function runVisitorTrendColor(trend) {
		var getCount = [], getTotal, getHalf, loopThru, loopNum=0, varyAmt;
		$("#battleplan_trends_stats ."+trend+" tr").each(function(){
			getCount.push( $(this).attr("data-count") );
		});	
		getCount.shift(); 
		getCount.sort(function(a, b){return b-a});
		getTotal = getCount.length;
		getHalf = Math.floor(getTotal / 2);
		getTotal--;

		for (loopThru = 0; loopThru < getHalf; loopThru++) {	
			varyAmt = 100 - ((100 / getHalf) * loopThru);
			varyAmt = varyAmt * 2;
			$('#battleplan_trends_stats .'+trend+' tr[data-count="' + getCount[loopThru] + '"]').find('td').css({ "filter": "saturate("+varyAmt+"%)" });
		} 
		for (loopThru = getTotal; loopThru > getHalf; loopThru--) {	
			varyAmt = 100 - ((100 / getHalf) * loopNum);
			varyAmt = varyAmt * 2;
			$('#battleplan_trends_stats .'+trend+' tr[data-count="' + getCount[loopThru] + '"]').find('td').css({ "color":"#f00", "filter": "saturate("+varyAmt+"%)" });
			loopNum++;
		} 
	}	
	runVisitorTrendColor('trends-weekly');
	runVisitorTrendColor('trends-monthly');
	runVisitorTrendColor('trends-quarterly');	
	
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