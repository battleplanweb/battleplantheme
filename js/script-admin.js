document.addEventListener("DOMContentLoaded", function () {	"use strict";

// Raw Script: Admin

	getObjects('.disabled').forEach(el => {
        el.classList.remove('disabled', '-disabled');
    });

	getObjects('select, input, button').forEach(el => {
        el.removeAttribute('disabled');
    });

// Map admin icon (on non-admin pages) to the admin section
	const adminBtn = getObject('.logged-in #wpadminbar button');
	if (adminBtn) {
		adminBtn.addEventListener('click', () => {
			window.location.href = '/wp-admin/';
		});
	}

// Control color of Top 10 Most Visited Days box
	getObjects('#battleplan_site_stats tr').forEach(function(tr) {
		var getAge = 100 - parseInt(tr.getAttribute('data-age'), 10);
		getAge = getAge * 2;
		if (getAge < 0) { getAge = 0; }
		getObjects('td', tr).forEach(function(td) {
			td.style.filter = 'saturate(' + getAge + '%)';
		});
	});



// Allow for expansion of admin boxes on click
	getObjects('#dashboard-widgets .postbox').forEach(postbox => {
        postbox.addEventListener('click', () => {
            postbox.classList.toggle('active');
        });
    });


// Control color of Visitor Trends box
	function runVisitorTrendColor(trend) {
		const trends = ['sessions', 'new', 'engagement', 'search', 'pageviews', 'duration'];

		trends.forEach(subtrend => {
			let getCount = [];

		// Remove all months before stats began counting
			var trendRows = Array.prototype.slice.call(document.querySelectorAll('#battleplan_' + trend + '_stats .trends-' + trend + ' tr.' + subtrend)).reverse();
			for (var i = 0; i < trendRows.length; i++) {
				var row = trendRows[i];
				if (row.getAttribute("data-count") !== '0') {
					break;
				} else {
					row.parentNode.removeChild(row);
				}
			}


			// Collect data counts
			var rows = getObjects('#battleplan_' + trend + '_stats .trends-' + trend + ' tr.' + subtrend);
			for (var i = 0; i < rows.length; i++) {
				getCount.push(parseInt(rows[i].getAttribute("data-count"), 10));
			}

			// Sort counts in descending order
			getCount.sort((a, b) => b - a);

			const getTotal = getCount.length,
				  getThird = Math.floor(getTotal / 3),
				  topThird = getThird * 2;
			let loopNum = 0;

			// Apply styles to top third
			for (let loopThru = 0; loopThru < getThird; loopThru++) {
				let varyAmt = 100 - ((100 / getThird) * loopThru);
				varyAmt *= 2;
				getObjects(`#battleplan_${trend}_stats .trends-${trend} tr.${subtrend}[data-count="${getCount[loopThru]}"] td`).forEach(td => {
					td.style.color = "#009809";
					td.style.filter = `saturate(${varyAmt}%)`;
				});
			}

			// Apply styles to bottom third
			for (let loopThru = getTotal - 1; loopThru > topThird; loopThru--) {
				let varyAmt = 100 - ((100 / getThird) * loopNum);
				varyAmt *= 2;
				getObjects(`#battleplan_${trend}_stats .trends-${trend} tr.${subtrend}[data-count="${getCount[loopThru]}"] td`).forEach(td => {
					td.style.color = "#f00";
					td.style.filter = `saturate(${varyAmt}%)`;
				});
				loopNum++;
			}
		});
	}

	runVisitorTrendColor('weekly');
	runVisitorTrendColor('monthly');
	runVisitorTrendColor('quarterly');


// Check meta boxes for content, collapse if empty
	const pageTopText = getObject('#page-top_text'),
		  pageBotText = getObject('#page-bottom_text'),
		  commentStatus = getObject('#comment_status'),
		  pingStatus = getObject('#ping_status'),
		  commentStatusDiv = getObject('#commentstatusdiv'),
		  commentsDiv = getObject('#commentsdiv');

	if (pageTopText && !pageTopText.innerHTML.trim()) {
        getObject('#page-top').classList.add('closed');
    }

    if (pageBotText && !pageBotText.innerHTML.trim()) {
        getObject('#page-bottom').classList.add('closed');
    }

    if (commentStatus && (commentStatus.checked || pingStatus.checked)) {
        if (commentStatusDiv) commentStatusDiv.classList.remove('closed');
    } else {
        if (commentStatusDiv) commentStatusDiv.classList.add('closed');
        if (commentsDiv) commentsDiv.style.display = 'none';
    }

    setTimeout(() => {
        const wdsTitle = getObject('#wds_title'),
			  wdsMetadesc = getObject('#wds_metadesc'),
			  wdsMetaBox = getObject('#wds-wds-meta-box');

        if (wdsTitle && !wdsTitle.value.trim() && wdsMetadesc && !wdsMetadesc.value.trim()) {
           if (wdsMetaBox) wdsMetaBox.classList.add('closed');
        } else {
           if (wdsMetaBox) wdsMetaBox.classList.remove('closed');
        }

        getObjects('.sui-border-frame').forEach(el => el.style.display = 'block');
        getObjects('#poststuff .sui-box-body, #poststuff .wds-focus-keyword, #poststuff .wds-preview-description, #poststuff p.wds-preview-description, #poststuff .wds-edit-meta .sui-button').forEach(el => el.style.display = 'none');

       getObjects('.wds-seo-analysis-label').forEach(label => {
            label.addEventListener('click', () => {
                getObjects('.sui-box-body, .wds-focus-keyword').forEach(el => el.style.display = 'block');
            });
        });
    }, 1000);


	function saveBtnChoice(btn_no, choice) {
		const key = 'bp_admin_' + btn_no,
			  url = 'https://' + window.location.hostname + '/wp-admin/admin-ajax.php',
			  data = new URLSearchParams();

		data.append('action', 'update_meta');
		data.append('type', 'site');
		data.append('key', key);
		data.append('value', choice);

		fetch(url, {
			method: 'POST',
			body: data,
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded'
			}
		})
		.then(response => response.json())
		.then(data => console.log(data))
		.catch(error => console.error('Error:', error));
	}


// Visitor Trend buttons
	const trendBtns = getObject('.trend-buttons'),
		  postboxContainer = getObject('#postbox-container-3');

	if (trendBtns && postboxContainer) {
		postboxContainer.prepend(trendBtns);
	}

	function changeHeaders() {
		const activeTrend = getObject('table.trends tr.trends.active');
		if (activeTrend) {
			const pageCells = getObjects('table.trends td.page');
			if (activeTrend.classList.contains('sessions')) {
				pageCells.forEach(cell => cell.textContent = 'Sessions • Users');
			} else if (activeTrend.classList.contains('new')) {
				pageCells.forEach(cell => cell.textContent = 'New Users • Pct %');
			} else if (activeTrend.classList.contains('engagement')) {
				pageCells.forEach(cell => cell.textContent = 'Engaged • Pct %');
			} else if (activeTrend.classList.contains('search')) {
				pageCells.forEach(cell => cell.textContent = 'Search');
			} else if (activeTrend.classList.contains('pageviews')) {
				pageCells.forEach(cell => cell.textContent = 'Pageviews • Per User');
			} else if (activeTrend.classList.contains('duration')) {
				pageCells.forEach(cell => cell.textContent = 'Engaged • Total');
			}
		}
	}

	changeHeaders();

	const trendButtons = getObjects('.trend-buttons .sessions, .trend-buttons .new, .trend-buttons .engagement, .trend-buttons .search, .trend-buttons .pageviews, .trend-buttons .duration');

    trendButtons.forEach(button => {
        button.addEventListener('click', event => {
            event.preventDefault();
            const trendType = button.className.split(' ').find(cls => ['sessions', 'new', 'engagement', 'search', 'pageviews', 'duration'].includes(cls));
            if (trendType) {
                getObjects('table.trends tr.trends, .trend-buttons div a').forEach(el => el.classList.remove('active'));
                getObjects(`table.trends tr.trends.${trendType}`).forEach(el => el.classList.add('active'));
                getObject('a', button).classList.add('active');
                changeHeaders();
                saveBtnChoice('btn2', trendType);
            }
        });
    });


// Last ??? Visitors buttons
	const postboxContainer2 = getObject('#postbox-container-2'),
		  localVisitorsButtons = getObject('.local-visitors-buttons'),
		  lastVisitorsButtons = getObject('.last-visitors-buttons');

    if (postboxContainer2 && localVisitorsButtons) {
        postboxContainer2.prepend(localVisitorsButtons);
    }
    if (postboxContainer2 && lastVisitorsButtons) {
        postboxContainer2.prepend(lastVisitorsButtons);
    }

    // Function to handle the button clicks
    const handleButtonClick = (selector, handleLabelClass, saveValue) => {
		const objSelector = getObject(selector);
		if (objSelector) {
			objSelector.addEventListener('click', event => {
				event.preventDefault();
				getObjects('.handle-label, .last-visitors-buttons div, .last-visitors-buttons div a').forEach(el => el.classList.remove('active'));
				getObjects(handleLabelClass).forEach(el => el.classList.add('active'));
				event.currentTarget.querySelector('a').classList.add('active');
				saveBtnChoice('btn1', saveValue);
			});
		}
    };

    // Add event listeners to the buttons
    handleButtonClick('.last-visitors-buttons .week', '.handle-label-7', 'week');
    handleButtonClick('.last-visitors-buttons .month', '.handle-label-30', 'month');
    handleButtonClick('.last-visitors-buttons .quarter', '.handle-label-90', 'quarter');
    handleButtonClick('.last-visitors-buttons .semester', '.handle-label-180', 'semester');
    handleButtonClick('.last-visitors-buttons .year', '.handle-label-365', 'year');

    // Local visitors button
	const localVisitorBtn = getObject('.local-visitors-buttons .local');
   	if (localVisitorBtn) {
		localVisitorBtn.addEventListener('click', event => {
			event.preventDefault();
			const anchor = event.currentTarget.querySelector('a');
			if (anchor.classList.contains('active')) {
				anchor.classList.remove('active');
				anchor.classList.add('not-active');
				saveBtnChoice('btn3', 'not-active');
			} else {
				anchor.classList.remove('not-active');
				anchor.classList.add('active');
				saveBtnChoice('btn3', 'active');
			}
			setTimeout(() => location.reload(), 1000);
		});
	}


	// Add title of the page being edited to the "View Post" button in the admin banner
    const pageTitleInput = getObject('#title'),
		  viewPostLink = getObject('#wp-admin-bar-view a.ab-item');

    function updatePageTitle() {
        const pageTitle = pageTitleInput.value;
        if (viewPostLink) {
            viewPostLink.textContent = "View: " + pageTitle;
        }
    }

    if (pageTitleInput) {
        pageTitleInput.addEventListener('input', updatePageTitle);
        updatePageTitle();
    }

    const viewPostAnchor = getObject('#wp-admin-bar-view a');
    if (viewPostAnchor) {
        viewPostAnchor.setAttribute('target', '_blank');
    }

    // Site Audit
    const colWhen = getObjects('.col.when'),
		  colNotes = getObjects('.col.notes');

    colWhen.forEach(col => {
        col.addEventListener('click', () => {
            colNotes.forEach(note => note.style.display = 'block');
        });
    });

    colNotes.forEach(col => {
        col.addEventListener('click', () => {
            colNotes.forEach(note => note.style.display = 'none');
        });
    });

    // Contact Form icons
    const editSpans = getObjects('span.edit a'),
		  copySpans = getObjects('span.copy a');

    editSpans.forEach(span => {
        span.innerHTML = '<i class="dashicons-edit"></i>';
    });

    copySpans.forEach(span => {
        span.innerHTML = '<i class="dashicons-clone"></i>';
    });


// Jobsite GEO page filter (launch in new window)
	var jobsiteGEOLaunch = getObject('#view_jobsite_geo_pages');

	if ( jobsiteGEOLaunch ) {
		jobsiteGEOLaunch.addEventListener('change', function() {
			var selectedUrl = this.value;
			if (selectedUrl) {
				window.open(selectedUrl, '_blank');
			}
		});
	}

// Set up custom QTags
	if (typeof QTags !== 'undefined') {
		QTags.addButton( 'bp_paragraph', 'p', '<p>', '</p>', 'p', 'Paragraph Tag', 1 );
		QTags.addButton( 'bp_li', 'li', ' <li>', '</li>', 'li', 'List Item', 100 );
		QTags.addButton( 'bp_h1', 'h1', '<h1>', '</h1>', 'h1', 'H1 Tag', 1 );
		QTags.addButton( 'bp_h2', 'h2', '<h2>', '</h2>', 'h2', 'H2 Tag', 1 );
		QTags.addButton( 'bp_h3', 'h3', '<h3>', '</h3>', 'h3', 'H3 Tag', 1 );
		QTags.addButton( 'bp_h4', 'h4', '<h4>', '</h4>', 'h4', 'H4 Tag', 1 );
		QTags.addButton( 'bp_h5', 'h5', '<h5>', '</h5>', 'h5', 'H5 Tag', 1 );
		QTags.addButton( 'bp_h6', 'h6', '<h6>', '</h6>', 'h6', 'H3 Tag', 1 );

		QTags.addButton( 'bp_lock-section', 'lock', '[lock name="becomes id attribute" style="default:lock, 1, 2, 3, etc" width="edge, default, stretch, full, inline" position="bottom, top, modal, header" delay="3000" show="session, never, always, # days" background="url" left="50" top="50" class="" start="YYYY-MM-DD" end="YYYY-MM-DD" btn-activated="no, yes" track="adds to data-track" content="text, image"]\n [layout]\n\n', ' [/layout]\n[/lock]\n\n', 'lock', 'Lock', 1000 );

		QTags.addButton( 'bp_expire-content', 'expire', '[expire start="YYYY-MM-DD" end="YYYY-MM-DD"]', '[/expire]\n\n', 'expire', 'Expire', 1000 );

		QTags.addButton('bp_parallax', 'parallax', '[parallax section name="becomes id attribute" style="corresponds to css" width="default, stretch, full, edge, inline" img-w="1920" img-h="1024" height="800" padding="50" pos-x="50%" top-y="0" bottom-y="0" z-index="2" image="/wp-content/uploads/image.webp"]\n', '[/parallax]\n\n', 'parallax', 'Parallax', 1000 );

		QTags.addButton( 'bp_section', 'section', '[section name="becomes id attribute" hash="compensation for scroll on one-page sites" style="corresponds to css" width="default, stretch, full, edge, inline" background="url" left="50" top="50" class="" start="YYYY-MM-DD" end="YYYY-MM-DD"]\n', '[/section]\n\n', 'section', 'Section', 1000 );

		QTags.addButton( 'bp_layout', 'layout', ' [layout grid="1-auto, 1-1-1-1, 5e, content, 80px 100px 1fr" break="none, 3, 4" valign="start, stretch, center, end" class=""]\n\n', ' [/layout]\n', 'layout', 'Layout', 1000 );

		QTags.addButton( 'bp_column', 'column', '  [col name="becomes id attribute" hash="compensation for scroll on one-page sites" align="center, left, right" valign="start, stretch, center, end" background="url" left="50" top="50" class="" start="YYYY-MM-DD" end="YYYY-MM-DD"]\n', '  [/col]\n\n', 'column', 'Column', 1000 );

		QTags.addButton( 'bp_text', 'text', '   [txt size="100 1/2 1/3 1/4 1/6 1/12" order="2, 1, 3" class="" start="YYYY-MM-DD" end="YYYY-MM-DD"]\n', '   [/txt]\n', 'text', 'Text', 1000 );

		QTags.addButton( 'bp_group', 'group', '   [group size = "100 1/2 1/3 1/4 1/6 1/12" order="1, 2, 3" class="" start="YYYY-MM-DD" end="YYYY-MM-DD"]\n', '   [/group]\n\n', 'group', 'Group', 1000 );

		QTags.addButton( 'bp_widget', 'widget', '[widget type="basic" title="Brand Logo (omit to hide)" lock="none, top, bottom" priority="2, 1, 3, 4, 5" set="none, param" class="" show="slug" hide="slud" start="YYYY-MM-DD" end="YYYY-MM-DD"]\n', '[/widget]\n\n', 'widget', 'Widget', 1000 );

		QTags.addButton( 'bp_image', 'image', '   [img size="100 1/2 1/3 1/4 1/6 1/12" order="1, 2, 3" link="url to link to" new-tab="false, true" ada-hidden="false, true" class="" start="YYYY-MM-DD" end="YYYY-MM-DD"]', '[/img]\n', 'image', 'Image', 1000 );

		QTags.addButton( 'bp_video', 'video', '   [vid size="100 1/2 1/3 1/4 1/6 1/12" order="1, 2, 3" link="url of video" thumb="url of thumb, if not using auto" preload="false, true" class="" related="false, true" start="YYYY-MM-DD" end="YYYY-MM-DD"]', '[/vid]\n', 'video', 'Video', 1000 );

		QTags.addButton( 'bp_caption', 'caption', '[caption align="align-center, align-left, align-right | size-full-s" width="800"]<img src="/filename.jpg" alt="" class="size-full-s" />Type caption here.[/caption]\n', '', 'caption', 'Caption', 1000 );

		QTags.addButton( 'bp_button', 'button', '   [btn size="100 1/2 1/3 1/4 1/6 1/12" order="3, 1, 2" align="center, left, right" link="url to link to" get-biz="link in functions.php" new-tab="false, true" class="" icon="chevron-right" fancy="(blank), 2" ada="text for ada button" start="YYYY-MM-DD" end="YYYY-MM-DD"]', '[/btn]\n', 'button', 'Button', 1000 );

		QTags.addButton( 'bp_social', 'social', '   [social-btn type="email, facebook, twitter" img="none, link"]', '', 'social', 'Social', 1000 );

		QTags.addButton( 'bp_accordion', 'accordion', '   [accordion title="clickable title" class="" excerpt="false, whatever text you want the excerpt to be" active="false, true" icon="true, false, /wp-content/uploads/image.jpg" btn="false/true (prints title) / Open Button Text" btn_collapse="blank (hides btn) / Close Button Text" start="YYYY-MM-DD" end="YYYY-MM-DD", scroll="true", track="" multiple="true/ false (if one accordion false, all will collapse when clicked"]', '[/accordion]\n\n', 'accordion', 'Accordion', 1000 );

		QTags.addButton( 'bp_restrict-content', 'restrict', '[restrict max="administrator, any role" min="none, any role"]', '[/restrict]\n\n', 'restrict', 'Restrict', 1000 );

		QTags.addButton( 'bp_clear', 'clear', '[clear height="px, em" class=""]\n\n', '', 'clear', 'Clear', 1000 );

		QTags.addButton( 'bp_getLocation', 'get-location', '[get-location state="true/false" default="blank" before="" after=""]\n\n', '', 'get-location', 'get-location', 1000 );

		QTags.addButton( 'bp_images_side-by-side', 'side by side images', '[side-by-side img="ids" size="half-s, third-s, full" gap="2em" align="center, left, right" full="id" pos="bottom, top" break="none, 3, 2, 1"]\n\n', '', 'side by side images', 'Side By Side Images', 1000 );

		QTags.addButton( 'bp_get-countup', 'get countup', '[get-countup name="becomes the id" start="0" end="1000" decimals="0" duration="5" delay="0" waypoint="85%" easing="false, easeInSine, EaseOutSine, EaseInOutSine, Quad, Cubic, Expo, Circ" grouping="true, false" separator="," decimal="." prefix="..." suffix="..."]\n\n', '', 'get countup', 'Get Count Up', 1000 );

		QTags.addButton( 'bp_get-wp-page', 'get wp page', '[get-wp-page type="page, post, cpt" id="" slug="" title="" display="content, excerpt, title, thumbnail, link"]\n\n', '', 'get wp page', 'Get WP Page', 1000 );

		QTags.addButton( 'bp_copy-content', 'copy content', '[copy-content slug="home" section="page top, page bottom" ]\n\n', '', 'copy content', 'Copy Content', 1000 );

		QTags.addButton( 'bp_random-image', 'random image', '   [get-random-image id="" tag="random" size="thumbnail, third-s" link="no, yes" number="1" offset="" align="left, right, center" order_by="rand, menu_order, title, id, post_date, modified" order="asc, desc" shuffle="no, yes, peak, valley, alternate" lazy="true, false"]\n\n', '', 'random image', 'Random Image', 1000 );

		QTags.addButton( 'bp_random-post', 'random post', '   [get-random-posts num="1" offset="0" leeway="0" type="post" tax="" terms="" orderby="rand" sort="asc, desc" thumb_only="false, true" thumb_col="1, 2, 3, 4" show_title="true, false" title_pos="outside, inside" show_date="false, true" show_author="false, true" show_excerpt="true, false" show_social="false, true" show_btn="true, false" button="Read More" btn_pos="inside, outside" thumbnail="force, false" link="post, false, cf-field_name, /link-destination/" start="" end="" exclude="" x_current="true, false" size="thumbnail, size-third-s" lazy="true, false" pic_size="1/3" text_size=""]\n\n', '', 'random post', 'Random Post', 1000 );

		QTags.addButton( 'bp_random-text', 'random text', '   [get-random-text cookie="true, false" text1="" text2="" text3="" text4="" text5="" text6="" text7=""]\n\n', '', 'random text', 'Random Text', 1000 );

		QTags.addButton( 'bp_row-of-pics', 'row of pics', '   [get-row-of-pics id="" tag="row-of-pics" col="4" row="1" offset="0" size="half-s, thumbnail" valign="center, start, stretch, end" link="no, yes" order_by="rand, menu_order, title, id, post_date, modified" order="asc, desc" shuffle="no, yes, peak, valley, alternate" lazy="true, false" class=""]\n\n', '', 'row of pics', 'Row Of Pics', 1000 );

		QTags.addButton( 'bp_get-gallery', 'gallery', '   [get-gallery name="" size="thumbnail" id="" columns="5" max="-1" offset="0" caption="false, true" start="" end="" order_by="menu_order" order="asc, desc" tags="" field="" operator="any" class="" include="" exclude="" unique="true, false" value="" type="" compare=""]\n\n', '', 'gallery', 'Gallery', 1000 );
		QTags.addButton( 'bp_get-video-gallery', 'video gallery', '   [get-video-gallery name="" type="videos" id="" columns="4" max="-1" offset="0" start="" end="" order_by="date" order="desc, asc" tax="video-tags" terms="" operator="and/or" class="" valign="stretch" show_title="true/false" show_date="true/false"]\n\n', '', 'video gallery', 'Video Gallery', 1000 );

		QTags.addButton('bp_post-slider', 'post slider', '   [get-post-slider type="" id="(for images)" auto="yes, no" interval="6000" loop="true, false" num="4" offset="0" pics="yes, no" controls="yes, no" controls_pos="below, above, center" indicators="no, yes" justify="space-around, space-evenly, space-between, center" pause="true, false" speed="slow, fast, # (10=slow, 25=fast)" tax="" terms="" orderby="rand, id, author, title, name, type, date, modified, parent, comment_count, relevance, menu_order" order="asc, desc" post_btn="" all_btn="View All" link="" start="" end="" exclude="" x_current="true, false" show_excerpt="true, false" show_content="false, true" size="thumbnail, half-s" pic_size="1/3" text_size="" class="" (images) slide_type="box, screen, fade" slide_effect="fade, dissolve, cycle, boomerang, zoom, fade-cycle, cycle-fade, fade-zoom, zoom-fade" tag="" caption="no, yes" id="" mult="1" truncate="true, false, # of characters" lazy="true, false" blur="false, true", rand_start=>"", content_type="image, text"]\n\n', '', 'post slider', 'Post Slider', 1000 );

		QTags.addButton('bp_images-slider', 'Images Slider', '<div class="align-right size-half-s">[get-post-slider type="images" num="6" size="half-s" controls="no" indicators="yes" tag="featured" all_btn="" link="none, alt, description, blank" slide_type="box, screen, fade" slide_effect="fade, dissolve, cycle, boomerang, zoom, fade-cycle, cycle-fade, fade-zoom, zoom-fade" orderby="recent" blur="false, true" lazy="true, false" speed="slow, fast, # (10=slow, 25=fast)" ]</div>\n\n', '', 'images-slider', 'Images Slider', 1000 );

		QTags.addButton( 'bp_testimonial-slider', 'Testimonial Slider', '  [col]\n   <h2>What Our Customers Say...</h2>\n   [get-post-slider type="testimonials" num="6" pic_size="1/3"]\n  [/col]\n\n', '', 'testimonial-slider', 'Testimonial Slider', 1000 );

		QTags.addButton( 'bp_logo-slider', 'Logo Slider', '[section name="Logo Slider" style="1" width="edge"]\n [layout]\n  [col]\n   [get-logo-slider num="-1" space="10" size="full, thumbnail, quarter-s" max_w="33" tag="featured" package="null, hvac" orderby="rand, id, title, date, modified, menu_order" order="asc, desc" shuffle="false, true" speed="slow, fast, # (10=slow, 25=fast)" direction="normal, reverse" pause="no, yes" link="false, true"]\n  [/col]\n [/layout]\n[/section]\n\n', '', 'logo-slider', 'Logo Slider', 1000 );

		QTags.addButton( 'bp_random-product', 'Random Product', '  [col]\n   <h2>Featured Product</h2>\n   [get-random-posts type="products" leeway="1" button="Learn More" orderby="rand, id, title, date, modified, menu_order" sort="desc"]\n  [/col]\n\n', '', 'random-product', 'Random Product', 1000 );

		QTags.addButton( 'bp_phone-link', 'Phone Link', '<b>[get-biz info="phone-link"]</b>', '', 'phone-link', 'Phone Link', 1000 );
	}
});