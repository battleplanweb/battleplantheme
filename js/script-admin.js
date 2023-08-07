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
		var trends = new Array('sessions', 'new', 'engagement', 'search', 'pageviews', 'duration');		
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
	//runVisitorTrendColor('daily');	
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
	
	function changeHeaders() {
		if ( $('table.trends tr.trends.active').hasClass("sessions") ) { $('table.trends td.page').text('Sessions • Users'); }
		if ( $('table.trends tr.trends.active').hasClass("new") ) { $('table.trends td.page').text('New Users • Pct %'); }
		if ( $('table.trends tr.trends.active').hasClass("engagement") ) { $('table.trends td.page').text('Engaged • Pct %'); }
		if ( $('table.trends tr.trends.active').hasClass("search") ) { $('table.trends td.page').text('Search'); }
		if ( $('table.trends tr.trends.active').hasClass("pageviews") ) { $('table.trends td.page').text('Pageviews • Per User'); }
		if ( $('table.trends tr.trends.active').hasClass("duration") ) { $('table.trends td.page').text('Engaged • Total'); }
	}
	changeHeaders();

	
	$('.trend-buttons .sessions').click(function(event) {
		event.preventDefault();
		$('table.trends tr.trends, .trend-buttons div a').removeClass('active');
		$('table.trends tr.trends.sessions').addClass('active');		
		$(this).find('a').addClass('active');
		changeHeaders();
		saveBtnChoice('btn2', 'sessions');
	});
	
	$('.trend-buttons .new').click(function(event) {
		event.preventDefault();
		$('table.trends tr.trends, .trend-buttons div a').removeClass('active');
		$('table.trends tr.trends.new').addClass('active');		
		$(this).find('a').addClass('active');
		changeHeaders();
		saveBtnChoice('btn2', 'new');
	});
		
	$('.trend-buttons .engagement').click(function(event) {
		event.preventDefault();
		$('table.trends tr.trends, .trend-buttons div a').removeClass('active');
		$('table.trends tr.trends.engagement').addClass('active');	
		$(this).find('a').addClass('active');
		changeHeaders();
		saveBtnChoice('btn2', 'engagement');
	});

	$('.trend-buttons .search').click(function(event) {
		event.preventDefault();
		$('table.trends tr.trends, .trend-buttons div a').removeClass('active');
		$('table.trends tr.trends.search').addClass('active');	
		$(this).find('a').addClass('active');
		changeHeaders();
		saveBtnChoice('btn2', 'search');
	});
		
	$('.trend-buttons .pageviews').click(function(event) {
		event.preventDefault();
		$('table.trends tr.trends, .trend-buttons div a').removeClass('active');
		$('table.trends tr.trends.pageviews').addClass('active');	
		$(this).find('a').addClass('active');
		changeHeaders();
		saveBtnChoice('btn2', 'pageviews');
	});
		
	$('.trend-buttons .duration').click(function(event) {
		event.preventDefault();
		$('table.trends tr.trends, .trend-buttons div a').removeClass('active');
		$('table.trends tr.trends.duration').addClass('active');	
		$(this).find('a').addClass('active');
		changeHeaders();
		saveBtnChoice('btn2', 'duration');
	});
	
	// Last ??? Visitors buttons
	$('#postbox-container-2').prepend($('.local-visitors-buttons'));
	$('#postbox-container-2').prepend($('.last-visitors-buttons'));
	
	$('.last-visitors-buttons .week').click(function(event) {
		event.preventDefault();
		$('.handle-label, .last-visitors-buttons div, .last-visitors-buttons div a').removeClass('active');
		$('.handle-label-7').addClass('active');		
		$(this).find('a').addClass('active');
		saveBtnChoice('btn1', 'week');
	});	
	
	$('.last-visitors-buttons .month').click(function(event) {
		event.preventDefault();
		$('.handle-label, .last-visitors-buttons div, .last-visitors-buttons div a').removeClass('active');
		$('.handle-label-30').addClass('active');		
		$(this).find('a').addClass('active');
		saveBtnChoice('btn1', 'month');
	});
	
	$('.last-visitors-buttons .quarter').click(function(event) {
		event.preventDefault();
		$('.handle-label, .last-visitors-buttons div, .last-visitors-buttons div a').removeClass('active');
		$('.handle-label-90').addClass('active');		
		$(this).find('a').addClass('active');
		saveBtnChoice('btn1', 'quarter');
	});
	
	$('.last-visitors-buttons .semester').click(function(event) {
		event.preventDefault();
		$('.handle-label, .last-visitors-buttons div, .last-visitors-buttons div a').removeClass('active');
		$('.handle-label-180').addClass('active');		
		$(this).find('a').addClass('active');
		saveBtnChoice('btn1', 'semester');
	});
		
	$('.last-visitors-buttons .year').click(function(event) {
		event.preventDefault();
		$('.handle-label, .last-visitors-buttons div, .last-visitors-buttons div a').removeClass('active');
		$('.handle-label-365').addClass('active');		
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
	
	
	// Add title of the page being edited to the "View Post" button in the admin banner
	var pageTitleInput = $('#title');

    	function updatePageTitle() {
        	var pageTitle = pageTitleInput.val();
        	$('#wp-admin-bar-view a.ab-item').text("View: "+pageTitle);
    	}

    	pageTitleInput.on('input', updatePageTitle);
    	updatePageTitle();
	$('#wp-admin-bar-view a').attr('target','_blank');
			
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
	
	
	// Set up custom QTags	
	QTags.addButton( 'bp_paragraph', 'p', '<p>', '</p>\n', 'p', 'Paragraph Tag', 1 );
	QTags.addButton( 'bp_li', 'li', ' <li>', '</li>', 'li', 'List Item', 100 );			

	QTags.addButton( 'bp_widget', 'widget', '[widget type="basic" title="Brand Logo (omit to hide)" lock="none, top, bottom" priority="2, 1, 3, 4, 5" set="none, param" class="" show="slug" hide="slud" start="YYYY-MM-DD" end="YYYY-MM-DD"]\n', '[/widget]\n\n', 'widget', 'Widget', 1000 );			

	QTags.addButton( 'bp_section', 'section', '[section name="becomes id attribute" hash="compensation for scroll on one-page sites" style="corresponds to css" width="default, stretch, full, edge, inline" background="url" left="50" top="50" class="" start="YYYY-MM-DD" end="YYYY-MM-DD"]\n', '[/section]\n\n', 'section', 'Section', 1000 );		
	QTags.addButton( 'bp_layout', 'layout', ' [layout grid="1-auto, 1-1-1-1, 5e, content, 80px 100px 1fr" break="none, 3, 4" valign="start, stretch, center, end" class=""]\n\n', ' [/layout]\n', 'layout', 'Layout', 1000 );
	QTags.addButton( 'bp_column', 'column', '  [col name="becomes id attribute" hash="compensation for scroll on one-page sites" align="center, left, right" valign="start, stretch, center, end" background="url" left="50" top="50" class="" start="YYYY-MM-DD" end="YYYY-MM-DD"]\n', '  [/col]\n\n', 'column', 'Column', 1000 );
	QTags.addButton( 'bp_image', 'image', '   [img size="100 1/2 1/3 1/4 1/6 1/12" order="1, 2, 3" link="url to link to" new-tab="false, true" ada-hidden="false, true" class="" start="YYYY-MM-DD" end="YYYY-MM-DD"]', '[/img]\n', 'image', 'Image', 1000 ); 
	QTags.addButton( 'bp_video', 'video', '   [vid size="100 1/2 1/3 1/4 1/6 1/12" order="1, 2, 3" link="url of video" thumb="url of thumb, if not using auto" preload="false, true" class="" related="false, true" start="YYYY-MM-DD" end="YYYY-MM-DD"]', '[/vid]\n', 'video', 'Video', 1000 );
	QTags.addButton( 'bp_caption', 'caption', '[caption align="aligncenter, alignleft, alignright | size-full-s" width="800"]<img src="/filename.jpg" alt="" class="size-full-s" />Type caption here.[/caption]\n', '', 'caption', 'Caption', 1000 );
	QTags.addButton( 'bp_group', 'group', '   [group size = "100 1/2 1/3 1/4 1/6 1/12" order="1, 2, 3" class="" start="YYYY-MM-DD" end="YYYY-MM-DD"]\n', '   [/group]\n\n', 'group', 'Group', 1000 );	
	QTags.addButton( 'bp_text', 'text', '   [txt size="100 1/2 1/3 1/4 1/6 1/12" order="2, 1, 3" class="" start="YYYY-MM-DD" end="YYYY-MM-DD"]\n', '   [/txt]\n', 'text', 'Text', 1000 );
	QTags.addButton( 'bp_button', 'button', '   [btn size="100 1/2 1/3 1/4 1/6 1/12" order="3, 1, 2" align="center, left, right" link="url to link to" get-biz="link in functions.php" new-tab="false, true" class="" icon="fas fa-chevron-right" fancy="(blank), 2" ada="text for ada button" start="YYYY-MM-DD" end="YYYY-MM-DD"]', '[/btn]\n', 'button', 'Button', 1000 );	
	QTags.addButton( 'bp_social', 'social', '   [social-btn type="email, facebook, twitter" img="none, link"]', '', 'social', 'Social', 1000 );	
	QTags.addButton( 'bp_accordion', 'accordion', '   [accordion title="clickable title" class="" excerpt="false, whatever text you want the excerpt to be" active="false, true" icon="true, false, /wp-content/uploads/image.jpg" btn="false/true/ Open Button Text" btn_collapse="Close Button Text" start="YYYY-MM-DD" end="YYYY-MM-DD"]', '[/accordion]\n\n', 'accordion', 'Accordion', 1000 );			

	QTags.addButton( 'bp_expire-content', 'expire', '[expire start="YYYY-MM-DD" end="YYYY-MM-DD"]', '[/expire]\n\n', 'expire', 'Expire', 1000 );			
	QTags.addButton( 'bp_restrict-content', 'restrict', '[restrict max="administrator, any role" min="none, any role"]', '[/restrict]\n\n', 'restrict', 'Restrict', 1000 );	

	QTags.addButton( 'bp_lock-section', 'lock', '[lock name="becomes id attribute" style="default:lock, 1, 2, 3, etc" width="edge, default, stretch, full, inline" position="bottom, top, modal, header" delay="3000" show="session, never, always, # days" background="url" left="50" top="50" class="" start="YYYY-MM-DD" end="YYYY-MM-DD" btn-activated="no, yes"]\n [layout]\n\n', ' [/layout]\n[/lock]\n\n', 'lock', 'Lock', 1000 );	

	QTags.addButton( 'bp_clear', 'clear', '[clear height="px, em" class=""]\n\n', '', 'clear', 'Clear', 1000 );	

	QTags.addButton( 'bp_images_side-by-side', 'side by side images', '[side-by-side img="ids" size="half-s, third-s, full" align="center, left, right" full="id" pos="bottom, top" break="none, 3, 2, 1"]\n\n', '', 'side by side images', 'Side By Side Images', 1000 );			
		
	QTags.addButton( 'bp_get-countup', 'get countup', '[get-countup name="becomes the id" start="0" end="1000" decimals="0" duration="5" delay="0" waypoint="85%" easing="false, easeInSine, EaseOutSine, EaseInOutSine, Quad, Cubic, Expo, Circ" grouping="true, false" separator="," decimal="." prefix="..." suffix="..."]\n\n', '', 'get countup', 'Get Count Up', 1000 );			
		
	QTags.addButton( 'bp_get-wp-page', 'get wp page', '[get-wp-page type="page, post, cpt" id="" slug="" title="" display="content, excerpt, title, thumbnail, link"]\n\n', '', 'get wp page', 'Get WP Page', 1000 );

	QTags.addButton( 'bp_random-image', 'random image', '   [get-random-image id="" tag="random" size="thumbnail, third-s" link="no, yes" number="1" offset="" align="left, right, center" order_by="recent, rand, menu_order, title, id, post_date, modified, views" order="asc, desc" shuffle="no, yes, peak, valley, alternate" lazy="true, false"]\n\n', '', 'random image', 'Random Image', 1000 );
	QTags.addButton( 'bp_random-post', 'random post', '   [get-random-posts num="1" offset="0" leeway="0" type="post" tax="" terms="" orderby="recent, rand, views-today, views-7day, views-30day, views-90day, views-365day, views-all" sort="asc, desc" count_view="true, false" thumb_only="false, true" thumb_col="1, 2, 3, 4" show_title="true, false" title_pos="outside, inside" show_date="false, true" show_author="false, true" show_excerpt="true, false" show_social="false, true" show_btn="true, false" button="Read More" btn_pos="inside, outside" thumbnail="force, false" link="post, false, cf-field_name, /link-destination/" start="" end="" exclude="" x_current="true, false" size="thumbnail, size-third-s" pic_size="1/3" text_size=""]\n\n', '', 'random post', 'Random Post', 1000 );
	QTags.addButton( 'bp_random-text', 'random text', '   [get-random-text cookie="true, false" text1="" text2="" text3="" text4="" text5="" text6="" text7=""]\n\n', '', 'random text', 'Random Text', 1000 );
	QTags.addButton( 'bp_row-of-pics', 'row of pics', '   [get-row-of-pics id="" tag="row-of-pics" col="4" row="1" offset="0" size="half-s, thumbnail" valign="center, start, stretch, end" link="no, yes" order_by="recent, rand, menu_order, title, id, post_date, modified, views" order="asc, desc" shuffle="no, yes, peak, valley, alternate" lazy="true, false" class=""]\n\n', '', 'row of pics', 'Row Of Pics', 1000 );
	QTags.addButton( 'bp_post-slider', 'post slider', '   [get-post-slider type="" auto="yes, no" interval="6000" loop="true, false" num="4" offset="0" pics="yes, no" controls="yes, no" controls_pos="below, above" indicators="no, yes" justify="space-around, space-evenly, space-between, center" pause="true, false" tax="" terms="" orderby="recent, rand, id, author, title, name, type, date, modified, parent, comment_count, relevance, menu_order, (images) views, (posts) views-today, views-7day, views-30day, views-90day, views-365day, views-all" order="asc, desc" post_btn="" all_btn="View All" link="" start="" end="" exclude="" x_current="true, false" show_excerpt="true, false" show_content="false, true" size="thumbnail, half-s" pic_size="1/3" text_size="" class="" (images) slide_type="box, screen, fade" slide_effect="fade, dissolve, cycle, boomerang, zoom, fade-cycle, cycle-fade, fade-zoom, zoom-fade" tag="" caption="no, yes" id="" mult="1" truncate="true, false, # of characters" lazy="true, false" blur="false, true"]\n\n', '', 'post slider', 'Post Slider', 1000 );

	QTags.addButton( 'bp_images-slider', 'Images Slider', '<div class="alignright size-half-s">[get-post-slider type="images" num="6" size="half-s" controls="no" indicators="yes" tag="featured" all_btn="" link="none, alt, description, blank" slide_type="box, screen, fade" slide_effect="fade, dissolve, cycle, boomerang, zoom, fade-cycle, cycle-fade, fade-zoom, zoom-fade" orderby="recent" blur="false, true" lazy="true, false"]</div>\n\n', '', 'images-slider', 'Images Slider', 1000 );	
	QTags.addButton( 'bp_testimonial-slider', 'Testimonial Slider', '  [col]\n   <h2>What Our Customers Say...</h2>\n   [get-post-slider type="testimonials" num="6" pic_size="1/3"]\n  [/col]\n\n', '', 'testimonial-slider', 'Testimonial Slider', 1000 );
	QTags.addButton( 'bp_logo-slider', 'Logo Slider', '[section name="Logo Slider" style="1" width="edge"]\n [layout]\n  [col]\n   [get-logo-slider num="-1" space="15" size="full, thumbnail, quarter-s" max_w="85" tag="featured" package="null, hvac" orderby="rand, id, title, date, modified, menu_order, recent, views" order="asc, desc" shuffle="false, true" speed="slow, fast, #" delay="0" pause="no, yes" link="false, true"]\n  [/col]\n [/layout]\n[/section]\n\n', '', 'logo-slider', 'Logo Slider', 1000 );
	QTags.addButton( 'bp_random-product', 'Random Product', '  [col]\n   <h2>Featured Product</h2>\n   [get-random-posts type="products" leeway="1" button="Learn More" orderby="views-30day" sort="desc"]\n  [/col]\n\n', '', 'random-product', 'Random Product', 1000 );		
	
})(jQuery); });