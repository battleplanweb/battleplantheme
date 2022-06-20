document.addEventListener("DOMContentLoaded", function () {	"use strict"; (function($) {
/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Admin interface

--------------------------------------------------------------*/

/*--------------------------------------------------------------
# Admin interface
--------------------------------------------------------------*/

	var ajaxURL = 'https://'+window.location.hostname+'/wp-admin/admin-ajax.php';	

	setTimeout(function() {			
	// Check chron jobs	
		$.post({
			url : ajaxURL,
			data : { action: "run_chron_jobs", admin: "true" },
			success: function( response ) { console.log(response);  }
		});
	}, 200);

	/* Allow useage of Admin Columns */
	$('.disabled').removeClass("disabled").removeClass("-disabled");
	$('select, input, button').removeAttr("disabled");
		
	/* Control color of Top 10 Most Visited Days box */
	$("#battleplan_site_stats tr").each(function(){
		var getAge = 100 - $(this).attr("data-age");
		getAge = getAge * 2;
		if (getAge < 0) { getAge = 0; }
		$(this).find('td').css({ "filter": "saturate("+getAge+"%)" });
	});
	
	/* Allow for expansion of admin boxes on click */
	$("#dashboard-widgets .postbox").click(function() { 
		var thisPostbox = $(this);
		if ( thisPostbox.hasClass('active') ) {
			thisPostbox.removeClass('active');
		} else {
			thisPostbox.addClass('active');
		}
	});	
	
	/* Control color of Visitor Trends box */
	function runVisitorTrendColor(trend) {
		var trends = new Array('users','search','pageviews','engagement');		
		for (var subtrend of trends) {
			var getCount = [], getTotal, getThird, topThird, loopThru, loopNum=0, varyAmt;		

			$($("#battleplan_"+trend+"_stats .trends-"+trend+" tr."+subtrend).get().reverse()).each(function() { // remove all months before stats began counting
				if ( $(this).attr("data-count") != '0' ) { return false; } else { $(this).remove(); }
			});	

			$("#battleplan_"+trend+"_stats .trends-"+trend+" tr."+subtrend).each(function() {
				getCount.push( $(this).attr("data-count") );
			});	
			//getCount.shift();  
			getCount.sort(function(a, b){return b-a});
			getTotal = getCount.length;
			getThird = Math.floor(getTotal / 3);
			topThird = getThird * 2; 
			getTotal--;

			for (loopThru = 0; loopThru < getThird; loopThru++) {	
				varyAmt = 100 - ((100 / getThird) * loopThru);
				varyAmt = varyAmt * 2;
				$('#battleplan_'+trend+'_stats .trends-'+trend+' tr.'+subtrend+'[data-count="' + getCount[loopThru] + '"]').find('td').css({ "color":"#009809", "filter": "saturate("+varyAmt+"%)" });
			} 
			for (loopThru = getTotal; loopThru > topThird; loopThru--) {	
				varyAmt = 100 - ((100 / getThird) * loopNum);
				varyAmt = varyAmt * 2;
				$('#battleplan_'+trend+'_stats .trends-'+trend+' tr.'+subtrend+'[data-count="' + getCount[loopThru] + '"]').find('td').css({ "color":"#f00", "filter": "saturate("+varyAmt+"%)" });
				loopNum++;
			} 
		}
	}	
	runVisitorTrendColor('weekly');
	runVisitorTrendColor('monthly');
	runVisitorTrendColor('quarterly');
	
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
	

	//$('#battleplan_referrer_stats h2.hndle').text( $('#battleplan_referrer_stats h2.hndle').text() + $('#battleplan_referrer_stats div.handle-label').attr('data-label') );
	//$('#battleplan_location_stats h2.hndle').text( $('#battleplan_location_stats h2.hndle').text() + $('#battleplan_location_stats div.handle-label').attr('data-label') );
	//$('#battleplan_pages_stats h2.hndle').text( $('#battleplan_pages_stats h2.hndle').text() + $('#battleplan_pages_stats div.handle-label').attr('data-label') );
		
		
	// Visitor Trend buttons
	$('#postbox-container-3').prepend($('.trend-buttons'));
		
	$('.trend-buttons .users').click(function() {
		$('table.trends tr.trends, .trend-buttons div a').removeClass('active');
		$('table.trends tr.trends.users').addClass('active');		
		$(this).find('a').addClass('active');
		$('table.trends td.page').text('Visitors');
	});
		
	$('.trend-buttons .search').click(function() {
		$('table.trends tr.trends, .trend-buttons div a').removeClass('active');
		$('table.trends tr.trends.search').addClass('active');	
		$(this).find('a').addClass('active');
		$('table.trends td.page').text('Search');
	});
		
	$('.trend-buttons .pageviews').click(function() {
		$('table.trends tr.trends, .trend-buttons div a').removeClass('active');
		$('table.trends tr.trends.pageviews').addClass('active');	
		$(this).find('a').addClass('active');
		$('table.trends td.page').text('Pages');
	});
		
	$('.trend-buttons .engagement').click(function() {
		$('table.trends tr.trends, .trend-buttons div a').removeClass('active');
		$('table.trends tr.trends.engagement').addClass('active');	
		$(this).find('a').addClass('active');
		$('table.trends td.page').text('Engaged');
	});
	
	// Last ??? Visitors buttons
	$('#postbox-container-2').prepend($('.last-visitors-buttons'));
		
	$('.last-visitors-buttons .last-100').click(function() {
		$('.handle-label, .last-visitors-buttons div, .last-visitors-buttons div a').removeClass('active');
		$('.handle-label_100').addClass('active');		
		$(this).find('a').addClass('active');
	});	
	
	$('.last-visitors-buttons .last-250').click(function() {
		$('.handle-label, .last-visitors-buttons div, .last-visitors-buttons div a').removeClass('active');
		$('.handle-label_250').addClass('active');		
		$(this).find('a').addClass('active');
	});
	
	$('.last-visitors-buttons .last-500').click(function() {
		$('.handle-label, .last-visitors-buttons div, .last-visitors-buttons div a').removeClass('active');
		$('.handle-label_500').addClass('active');		
		$(this).find('a').addClass('active');
	});
	
	$('.last-visitors-buttons .all-time').click(function() {
		$('.handle-label, .last-visitors-buttons div, .last-visitors-buttons div a').removeClass('active');
		$('.handle-label_AT').addClass('active');		
		$(this).find('a').addClass('active');
	});
	
	
	
// Site Audit
		
	$('.col.when').click(function() {
		$('.col.notes').fadeIn();	
	});
	
	$('.col.notes').click(function() {
		$('.col.notes').fadeOut();	
	});	
	
    
	// Contact Form icons
	$('span.edit a').html('<i class="dashicons-edit"></i>');
	$('span.copy a').html('<i class="dashicons-clone"></i>');
			
			
	
})(jQuery); });