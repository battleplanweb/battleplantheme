document.addEventListener("DOMContentLoaded", function () {	"use strict"; (function($) {
/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Admin interface

--------------------------------------------------------------*/

/*--------------------------------------------------------------
# Admin interface
--------------------------------------------------------------*/

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
		var trends = new Array('sessions', 'search', 'new','pages','engaged');		
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
		
	function saveBtnChoice(btn_no, choice) {
		var key = 'bp_admin_'+btn_no;
		$.post({
			url : 'https://'+window.location.hostname+'/wp-admin/admin-ajax.php',
			data : { action: "update_meta", type: 'site', key: key, value: choice },
			success: function( response ) { console.log(response); }
		});
	}
		
	// Visitor Trend buttons
	$('#postbox-container-3').prepend($('.trend-buttons'));		
	
	$('.trend-buttons .sessions').click(function(event) {
		event.preventDefault();
		$('table.trends tr.trends, .trend-buttons div a').removeClass('active');
		$('table.trends tr.trends.sessions').addClass('active');		
		$(this).find('a').addClass('active');
		$('table.trends td.page').text('Sessions');
		saveBtnChoice('btn2', 'sessions');
	});

	$('.trend-buttons .search').click(function(event) {
		event.preventDefault();
		$('table.trends tr.trends, .trend-buttons div a').removeClass('active');
		$('table.trends tr.trends.search').addClass('active');	
		$(this).find('a').addClass('active');
		$('table.trends td.page').text('Search');
		saveBtnChoice('btn2', 'search');
	});
	
	$('.trend-buttons .new').click(function(event) {
		event.preventDefault();
		$('table.trends tr.trends, .trend-buttons div a').removeClass('active');
		$('table.trends tr.trends.new').addClass('active');		
		$(this).find('a').addClass('active');
		$('table.trends td.page').text('New');
		saveBtnChoice('btn2', 'new');
	});
		
	$('.trend-buttons .pages').click(function(event) {
		event.preventDefault();
		$('table.trends tr.trends, .trend-buttons div a').removeClass('active');
		$('table.trends tr.trends.pages').addClass('active');	
		$(this).find('a').addClass('active');
		$('table.trends td.page').text('Pages');
		saveBtnChoice('btn2', 'pages');
	});
		
	$('.trend-buttons .engaged').click(function(event) {
		event.preventDefault();
		$('table.trends tr.trends, .trend-buttons div a').removeClass('active');
		$('table.trends tr.trends.engaged').addClass('active');	
		$(this).find('a').addClass('active');
		$('table.trends td.page').text('Engaged');
		saveBtnChoice('btn2', 'engaged');
	});
	
	// Last ??? Visitors buttons
	$('#postbox-container-2').prepend($('.local-visitors-buttons'));
	$('#postbox-container-2').prepend($('.last-visitors-buttons'));
	
	$('.last-visitors-buttons .week').click(function(event) {
		event.preventDefault();
		$('.handle-label, .last-visitors-buttons div, .last-visitors-buttons div a').removeClass('active');
		$('.handle-label-week').addClass('active');		
		$(this).find('a').addClass('active');
		saveBtnChoice('btn1', 'week');
	});	
	
	$('.last-visitors-buttons .month').click(function(event) {
		event.preventDefault();
		$('.handle-label, .last-visitors-buttons div, .last-visitors-buttons div a').removeClass('active');
		$('.handle-label-month').addClass('active');		
		$(this).find('a').addClass('active');
		saveBtnChoice('btn1', 'month');
	});
	
	$('.last-visitors-buttons .quarter').click(function(event) {
		event.preventDefault();
		$('.handle-label, .last-visitors-buttons div, .last-visitors-buttons div a').removeClass('active');
		$('.handle-label-quarter').addClass('active');		
		$(this).find('a').addClass('active');
		saveBtnChoice('btn1', 'quarter');
	});
	
	$('.last-visitors-buttons .year').click(function(event) {
		event.preventDefault();
		$('.handle-label, .last-visitors-buttons div, .last-visitors-buttons div a').removeClass('active');
		$('.handle-label-year').addClass('active');		
		$(this).find('a').addClass('active');
		saveBtnChoice('btn1', 'year');
	});
	
	$('.local-visitors-buttons .local').click(function(event) {
		event.preventDefault();
		if ( $(this).find('a').hasClass('active') ) {
			$(this).find('a').removeClass('active').addClass('not-active');
			saveBtnChoice('btn3', 'not-active');
		} else {
			$(this).find('a').removeClass('not-active').addClass('active');
			saveBtnChoice('btn3', 'active');
		}
		setTimeout(function(){ location.reload(); }, 1000);
	});	 
		
		/*
	$('a.clear-content-tracking').click(function(event) {
		event.preventDefault();
		$.post({
			url : ajaxURL,
			data : { action: "update_meta", type: "site", key: "content-tracking, content-column-views, content-scroll-pct", clear: "true" },
			success: function( response ) { ajax_response(response.dashboard);	}
		});
		setTimeout(function(){ location.reload(); }, 1000);
	});	 */
			
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