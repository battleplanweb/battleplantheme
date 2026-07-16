document.addEventListener("DOMContentLoaded", function () {	"use strict";
// Raw Script: Admin

	getObjects('#the-list tr').forEach(function (row) {

		var actions = row.querySelector('.column-primary .row-actions');
		var titleTd = row.querySelector('.column-bp-title');

		if (!actions || !titleTd) return;

		// Move row-actions into the title column
		titleTd.appendChild(actions);

	});

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

	const trendButtons = getObjects('.trend-buttons > .sessions, .trend-buttons > .new, .trend-buttons > .engagement, .trend-buttons > .search, .trend-buttons > .pageviews, .trend-buttons > .duration');

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




		const btn = document.getElementById('bp-ga4-rebuild');
		if (btn) {

			const status = document.getElementById('bp-ga4-status');

			btn.onclick = function () {
				btn.disabled = true;
				status.innerHTML = "Starting…";
				runStage();
			};

			function runStage() {
				fetch(ajaxurl, {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: 'action=bp_ga4_collect_stage'
				})
				.then(r => r.json())
				.then(data => {
					if (!data.success) {
						status.innerHTML = "Error";
						btn.disabled = false;
						return;
					}
					const s = data.data;
					status.innerHTML = "Stage " + s.stage_before + " complete";
					if (!s.complete) {
						setTimeout(runStage, 500);
					} else {
						status.innerHTML = "Complete";
						btn.disabled = false;
					}
				})
				.catch(() => {
					status.innerHTML = "Request failed";
					btn.disabled = false;
				});
			}
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

		QTags.addButton('bp_parallax', 'parallax', '[parallax section name="becomes id attribute" style="corresponds to css" width="default, stretch, full, edge, inline" img-w="1920" img-h="1024" height="800" padding="50 / applies to mobile" pos-x="50%" top-y="0" bottom-y="0" z-index="2" image="/wp-content/uploads/image.webp"]\n', '[/parallax]\n\n', 'parallax', 'Parallax', 1000 );

		QTags.addButton( 'bp_section', 'section', '[section name="becomes id attribute" hash="compensation for scroll on one-page sites" style="corresponds to css" width="default, stretch, full, edge, inline" background="url" left="50" top="50" class="" start="YYYY-MM-DD" end="YYYY-MM-DD"]\n', '[/section]\n\n', 'section', 'Section', 1000 );

		QTags.addButton( 'bp_layout', 'layout', ' [layout grid="1-auto, 1-1-1-1, 5e, content, 80px 100px 1fr" break="none, 3, 4" valign="start, stretch, center, end" class=""]\n\n', ' [/layout]\n', 'layout', 'Layout', 1000 );

		QTags.addButton( 'bp_column', 'column', '  [col name="becomes id attribute" hash="compensation for scroll on one-page sites" align="center, left, right" valign="start, stretch, center, end" background="url" left="50" top="50" class="" start="YYYY-MM-DD" end="YYYY-MM-DD"]\n', '  [/col]\n\n', 'column', 'Column', 1000 );

		QTags.addButton( 'bp_text', 'text', '   [txt size="100 1/2 1/3 1/4 1/6 1/12" order="2, 1, 3" class="" start="YYYY-MM-DD" end="YYYY-MM-DD"]\n', '   [/txt]\n', 'text', 'Text', 1000 );

		QTags.addButton( 'bp_group', 'group', '   [group size = "100 1/2 1/3 1/4 1/6 1/12" order="1, 2, 3" class="" start="YYYY-MM-DD" end="YYYY-MM-DD"]\n', '   [/group]\n\n', 'group', 'Group', 1000 );

		QTags.addButton( 'bp_widget', 'widget', '[widget type="basic" title="Brand Logo (omit to hide)" lock="none, top, bottom" priority="2, 1, 3, 4, 5" set="none, param" class="" show="slug" hide="slud" start="YYYY-MM-DD" end="YYYY-MM-DD"]\n', '[/widget]\n\n', 'widget', 'Widget', 1000 );

		QTags.addButton( 'bp_image', 'image', '   [img size="100 1/2 1/3 1/4 1/6 1/12" order="1, 2, 3" link="url to link to" new-tab="false, true" ada-hidden="false, true" class="" start="YYYY-MM-DD" end="YYYY-MM-DD"]', '[/img]\n', 'image', 'Image', 1000 );

		QTags.addButton( 'bp_video', 'video', '   [vid size="100 1/2 1/3 1/4 1/6 1/12" order="1, 2, 3" link="url of video" thumb="url of thumb, if not using auto" preload="false, true" class="" related="false, true" start="YYYY-MM-DD" end="YYYY-MM-DD"]', '[/vid]\n', 'video', 'Video', 1000 );

		QTags.addButton( 'bp_caption', 'caption', '[caption align="align-center, align-left, align-right | size-full-s" width="800"]<img src="/filename.jpg" alt="" class="size-full-s" >Type caption here.[/caption]\n', '', 'caption', 'Caption', 1000 );

		QTags.addButton( 'bp_button', 'button', '   [btn size="100 1/2 1/3 1/4 1/6 1/12" order="3, 1, 2" align="center, left, right" link="url to link to" get-biz="link in functions.php" new-tab="false, true" class="" icon="chevron-right" fancy="(blank), 2" ada="text for ada button" start="YYYY-MM-DD" end="YYYY-MM-DD"]', '[/btn]\n', 'button', 'Button', 1000 );

		QTags.addButton( 'bp_social', 'social', '   [social-btn type="email, facebook, twitter" img="none, link"]', '', 'social', 'Social', 1000 );

		QTags.addButton( 'bp_accordion', 'accordion', '   [accordion title="clickable title" class="" excerpt="false, whatever text you want the excerpt to be" active="false, true" icon="true, false, /wp-content/uploads/image.jpg" btn="false/true (prints title) / Open Button Text" btn_collapse="blank (hides btn) / Close Button Text" start="YYYY-MM-DD" end="YYYY-MM-DD", scroll="true", track="" multiple="true/ false (if one accordion false, all will collapse when clicked"]', '[/accordion]\n\n', 'accordion', 'Accordion', 1000 );

		QTags.addButton( 'bp_restrict-content', 'restrict', '[restrict max="administrator, any role" min="none, any role"]', '[/restrict]\n\n', 'restrict', 'Restrict', 1000 );

		QTags.addButton( 'bp_clear', 'clear', '[clear height="px, em" class=""]\n\n', '', 'clear', 'Clear', 1000 );

		QTags.addButton( 'bp_getLocation', 'get-location', '[get-location state="true/false" default="blank" before="" after=""]\n\n', '', 'get-location', 'get-location', 1000 );

		QTags.addButton( 'bp_get-countup', 'get countup', '[get-countup name="becomes the id" start="0" end="1000" decimals="0" duration="5" delay="0" waypoint="85%" easing="false, easeInSine, EaseOutSine, EaseInOutSine, Quad, Cubic, Expo, Circ" grouping="true, false" separator="," decimal="." prefix="..." suffix="..."]\n\n', '', 'get countup', 'Get Count Up', 1000 );

		QTags.addButton( 'bp_get-wp-page', 'get wp page', '[get-wp-page type="page, post, cpt" id="" slug="" title="" display="content, excerpt, title, thumbnail, link"]\n\n', '', 'get wp page', 'Get WP Page', 1000 );

		QTags.addButton('bp_copy-content', 'copy content', '[copy-content slug="home" section="page top, page bottom" ]\n\n', '', 'copy content', 'Copy Content', 1000);

		QTags.addButton('bp_images_side-by-side', 'side by side images', '[side-by-side img="ids" size="half-s, third-s, full" gap="2em" align="center, left, right" full="id" pos="bottom, top" break="none, 3, 2, 1"]\n\n', '', 'side by side images', 'Side By Side Images', 1000);

		QTags.addButton('bp_desktop_mobile_img', 'desktop mobile image', '[get-image desktop="/wp-content/uploads/XXXXX.webp" desktop_width="960" desktop_height="720" desktop_id="5876" mobile="/wp-content/uploads/XXXXX-mobile.webp" mobile_width="576" mobile_height="1023" mobile_id="5876" alt="" align="center, left, right" size="half-s, third-s, full" ]\n\n', '', 'desktop mobile image', 'Desktop / Mobile Images', 1000);

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

// Clear WP Engine + Cloudflare cache — isolated listener so errors above can't block it
document.addEventListener('DOMContentLoaded', function() {
	var clearCacheBtn = document.getElementById('bp-clear-cache-btn');
	if (!clearCacheBtn) return;
	clearCacheBtn.addEventListener('click', function(e) {
		e.preventDefault();
		e.stopPropagation();
		var btn = this;
		var original = btn.textContent;
		btn.disabled = true;
		btn.textContent = 'Clearing...';
		fetch(ajaxurl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: 'action=bp_clear_wpe_cache&_ajax_nonce=' + encodeURIComponent(btn.dataset.nonce)
		})
		.then(function(r) { return r.json(); })
		.then(function(data) {
			btn.textContent = data.success ? 'Cleared!' : 'Error';
			if (data.data && data.data.message) btn.title = data.data.message;
			setTimeout(function() { btn.textContent = original; btn.title = ''; btn.disabled = false; }, 4000);
		})
		.catch(function() {
			btn.textContent = 'Failed';
			setTimeout(function() { btn.textContent = original; btn.disabled = false; }, 3000);
		});
	});
});


// Visitor Trends — YoY line charts (isolated so errors elsewhere can't block it)
document.addEventListener('DOMContentLoaded', function () {
	var charts = document.querySelectorAll('.bp-trend-chart');
	if (!charts.length) return;

	var SVGNS   = 'http://www.w3.org/2000/svg';
	var METRICS = ['sessions', 'new', 'engagement', 'pageviews', 'duration'];
	var MONTHS  = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

	// --- formatting helpers -------------------------------------------------
	function fmtInt(n)  { return Math.round(n).toLocaleString('en-US'); }
	function round1(n)  { return Math.round(n * 10) / 10; }
	function fmtDur(s)  { s = Math.round(s); return Math.floor(s / 60) + 'm ' + (s % 60) + 's'; }
	function fmtY(metric, v) { return metric === 'duration' ? Math.round(v / 60) + 'm' : fmtInt(v); }
	function fmtVal(metric, v, which) {
		if (metric === 'duration') return fmtDur(v);
		if (which === 1) {
			if (metric === 'new' || metric === 'engagement') return round1(v) + '%';
			if (metric === 'pageviews') return String(round1(v));
		}
		return fmtInt(v);
	}
	function getYear(label) { var d = new Date(label); return isNaN(d) ? (label.match(/\d{4}/) || [''])[0] : d.getFullYear(); }
	function shortDate(label) { var d = new Date(label); return isNaN(d) ? label : MONTHS[d.getMonth()] + ' ' + d.getDate(); }

	function el(tag, attrs) {
		var e = document.createElementNS(SVGNS, tag);
		for (var k in attrs) if (attrs.hasOwnProperty(k)) e.setAttribute(k, attrs[k]);
		return e;
	}

	// Nice round axis maximum + step for ~`ticks` divisions.
	function niceMax(max, ticks) {
		if (max <= 0) return { max: 1, step: 1 };
		var raw  = max / ticks;
		var mag  = Math.pow(10, Math.floor(Math.log10(raw)));
		var norm = raw / mag;
		var nice = norm <= 1 ? 1 : norm <= 2 ? 2 : norm <= 5 ? 5 : 10;
		var step = nice * mag;
		return { max: Math.ceil(max / step) * step, step: step };
	}

	// Monotone cubic (Fritsch–Carlson) — smooth curve that never overshoots the data.
	function monotonePath(pts) {
		var n = pts.length, i;
		if (n === 0) return '';
		if (n === 1) return 'M' + pts[0][0] + ',' + pts[0][1];
		if (n === 2) return 'M' + pts[0][0] + ',' + pts[0][1] + 'L' + pts[1][0] + ',' + pts[1][1];

		var dx = [], dy = [], m = [];
		for (i = 0; i < n - 1; i++) { dx[i] = pts[i + 1][0] - pts[i][0]; dy[i] = pts[i + 1][1] - pts[i][1]; m[i] = dy[i] / dx[i]; }

		var t = []; t[0] = m[0]; t[n - 1] = m[n - 2];
		for (i = 1; i < n - 1; i++) {
			if (m[i - 1] * m[i] <= 0) { t[i] = 0; }
			else { var w1 = 2 * dx[i] + dx[i - 1], w2 = dx[i] + 2 * dx[i - 1]; t[i] = (w1 + w2) / (w1 / m[i - 1] + w2 / m[i]); }
		}

		var d = 'M' + pts[0][0] + ',' + pts[0][1];
		for (i = 0; i < n - 1; i++) {
			var x0 = pts[i][0], y0 = pts[i][1], x1 = pts[i + 1][0], y1 = pts[i + 1][1], h = dx[i] / 3;
			d += 'C' + (x0 + h) + ',' + (y0 + t[i] * h) + ' ' + (x1 - h) + ',' + (y1 - t[i + 1] * h) + ' ' + x1 + ',' + y1;
		}
		return d;
	}

	function activeMetric() {
		var btn = document.querySelector('.trend-buttons .button.active');
		if (btn) {
			var cls = btn.className.split(/\s+/);
			for (var i = 0; i < cls.length; i++) if (METRICS.indexOf(cls[i]) >= 0) return cls[i];
		}
		return 'sessions';
	}

	// --- render one chart container ----------------------------------------
	function render(container) {
		var data = container._bp || (container._bp = JSON.parse(container.dataset.chart));
		var width = container.clientWidth || 0;
		if (width < 60) return; // hidden or not laid out yet

		var metric = activeMetric();
		var series = data.series[metric] || {};
		var axis   = data.axis || {};
		var colEnd = data.colEnd;

		var cols = [];
		[1, 2, 3].forEach(function (c) { if (series[c] && series[c].length) cols.push(c); });

		container.innerHTML = '';
		if (!cols.length) { container.innerHTML = '<p class="bp-viz-empty">No data yet.</p>'; return; }

		var height = 260, mL = 46, mR = 46, mT = 12, mB = 26;
		var plotL = mL, plotR = width - mR, plotT = mT, plotB = height - mB;
		var plotW = plotR - plotL, plotH = plotB - plotT;

		var yMax = 0;
		cols.forEach(function (c) { series[c].forEach(function (p) { if (p.v > yMax) yMax = p.v; }); });
		var ny = niceMax(yMax, 4), yTop = ny.max;

		function xS(i) { return plotL + ((colEnd - i) / (colEnd - 1)) * plotW; } // i=1 (now) -> right
		function yS(v) { return plotB - (v / yTop) * plotH; }

		// legend (identity channel — always present for the overlaid years)
		var legend = document.createElement('div');
		legend.className = 'bp-viz-legend';
		cols.forEach(function (c) {
			var it = document.createElement('span');
			it.className = 'bp-leg-item';
			it.innerHTML = '<span class="bp-key bp-key-' + c + '"></span>' + getYear(series[c][0].l);
			legend.appendChild(it);
		});
		container.appendChild(legend);

		var svg = el('svg', { 'class': 'bp-viz-svg', width: '100%', height: height, viewBox: '0 0 ' + width + ' ' + height });

		// horizontal gridlines + y labels
		for (var v = 0; v <= yTop + 1e-9; v += ny.step) {
			var gy = yS(v);
			svg.appendChild(el('line', { 'class': 'bp-grid', x1: plotL, y1: gy, x2: plotR, y2: gy }));
			var yl = el('text', { 'class': 'bp-ylab', x: plotL - 6, y: gy + 3, 'text-anchor': 'end' });
			yl.textContent = fmtY(metric, v);
			svg.appendChild(yl);
		}

		// x labels at month changes (declutter: min 30px apart)
		var positions = Object.keys(axis).map(Number).sort(function (a, b) { return a - b; });
		var ticks = [], lastMonth = null;
		positions.forEach(function (pos) {
			var d = new Date(axis[pos]); if (isNaN(d)) return;
			if (d.getMonth() !== lastMonth) {
				lastMonth = d.getMonth();
				ticks.push({ x: xS(pos), lbl: MONTHS[d.getMonth()] + (d.getMonth() === 0 ? " '" + String(d.getFullYear()).slice(2) : '') });
			}
		});
		ticks.sort(function (a, b) { return a.x - b.x; });
		var lastX = -999;
		ticks.forEach(function (tk) {
			if (tk.x - lastX < 30) return;
			lastX = tk.x;
			var xl = el('text', { 'class': 'bp-xlab', x: tk.x, y: plotB + 16, 'text-anchor': 'middle' });
			xl.textContent = tk.lbl;
			svg.appendChild(xl);
		});

		// one line per year, shared y-scale so they compare directly
		var idx = {}, endLabels = [];
		cols.forEach(function (c) {
			idx[c] = {};
			var coords = [], recent = null;
			series[c].forEach(function (p) {
				idx[c][p.i] = p;
				coords.push([xS(p.i), yS(p.v)]);
				if (!recent || p.i < recent.i) recent = p;
			});
			coords.sort(function (a, b) { return a[0] - b[0]; });
			svg.appendChild(el('path', { 'class': 'bp-line bp-line-' + c, d: monotonePath(coords) }));

			var ex = xS(recent.i), ey = yS(recent.v);
			svg.appendChild(el('circle', { 'class': 'bp-dot-ring', cx: ex, cy: ey, r: 5 }));
			svg.appendChild(el('circle', { 'class': 'bp-dot bp-dot-' + c, cx: ex, cy: ey, r: 3 }));
			endLabels.push({ x: ex, y: ey, year: getYear(recent.l) });
		});

		// end-of-line year labels (neutral ink; the colored dot carries identity)
		endLabels.sort(function (a, b) { return a.y - b.y; });
		for (var k = 1; k < endLabels.length; k++) {
			if (endLabels[k].y - endLabels[k - 1].y < 12) endLabels[k].y = endLabels[k - 1].y + 12;
		}
		endLabels.forEach(function (L) {
			var t = el('text', { 'class': 'bp-endlab', x: Math.min(L.x + 8, width - 2), y: L.y + 3 });
			t.textContent = L.year;
			svg.appendChild(t);
		});

		// hover: crosshair + focus dots + tooltip
		var focus = el('g', { 'class': 'bp-focus', style: 'display:none' });
		focus.appendChild(el('line', { 'class': 'bp-crosshair', x1: 0, y1: plotT, x2: 0, y2: plotB }));
		var fdots = cols.map(function (c) {
			var ring = el('circle', { 'class': 'bp-dot-ring', r: 6, cx: 0, cy: 0 });
			var dot  = el('circle', { 'class': 'bp-dot-' + c, r: 4, cx: 0, cy: 0 });
			focus.appendChild(ring); focus.appendChild(dot);
			return { c: c, dot: dot, ring: ring };
		});
		svg.appendChild(focus);
		var hit = el('rect', { 'class': 'bp-hit', x: plotL, y: plotT, width: plotW, height: plotH, fill: 'transparent' });
		svg.appendChild(hit);
		container.appendChild(svg);

		var tip = document.createElement('div');
		tip.className = 'bp-viz-tip';
		tip.style.display = 'none';
		container.appendChild(tip);

		function nearestPos(i) {
			var best = positions[0], bd = Infinity;
			positions.forEach(function (p) { var dd = Math.abs(p - i); if (dd < bd) { bd = dd; best = p; } });
			return best;
		}

		hit.addEventListener('mousemove', function (ev) {
			var rect = svg.getBoundingClientRect();
			var scale = width / (rect.width || width);
			var px = (ev.clientX - rect.left) * scale;
			var i = colEnd - ((px - plotL) / plotW) * (colEnd - 1);
			var pos = nearestPos(Math.round(i));
			var cx = xS(pos);

			focus.style.display = '';
			focus.firstChild.setAttribute('x1', cx);
			focus.firstChild.setAttribute('x2', cx);

			var html = '<div class="bp-tip-head">' + (data.time === 'weekly' ? 'Week of ' : '') + shortDate(axis[pos]) + '</div>';
			fdots.forEach(function (f) {
				var p = idx[f.c][pos];
				if (!p) { f.dot.style.display = 'none'; f.ring.style.display = 'none'; return; }
				f.dot.style.display = ''; f.ring.style.display = '';
				var py = yS(p.v);
				f.dot.setAttribute('cx', cx); f.dot.setAttribute('cy', py);
				f.ring.setAttribute('cx', cx); f.ring.setAttribute('cy', py);
				html += '<div class="bp-tip-row"><span class="bp-key bp-key-' + f.c + '"></span><b>' + getYear(p.l) + '</b>'
					 +  '<span class="bp-tip-v">' + fmtVal(metric, p.v, 0) + '</span>'
					 +  '<span class="bp-tip-v2">' + fmtVal(metric, p.v2, 1) + '</span></div>';
			});
			tip.innerHTML = html;
			tip.style.display = 'block';
			var tw = tip.offsetWidth;
			tip.style.left = (cx + 12 + tw > width ? Math.max(2, cx - 12 - tw) : cx + 12) + 'px';
		});
		hit.addEventListener('mouseleave', function () { focus.style.display = 'none'; tip.style.display = 'none'; });
	}

	function debouncedRender(c) {
		if (c._bpPending) return;
		c._bpPending = true;
		requestAnimationFrame(function () { c._bpPending = false; render(c); });
	}
	function renderAll() { Array.prototype.forEach.call(charts, function (c) { debouncedRender(c); }); }

	// Re-render when the (global) metric buttons change; defer so .active is set first.
	document.addEventListener('click', function (ev) {
		if (ev.target.closest('.trend-buttons a')) setTimeout(renderAll, 0);
	});

	// Chart / Table toggle. stopPropagation keeps clicks from expanding the postbox.
	document.addEventListener('click', function (ev) {
		var b = ev.target.closest('.bp-viz-btn');
		if (!b) return;
		ev.preventDefault(); ev.stopPropagation();
		var wrap = b.closest('.bp-viz-toggle');
		wrap.querySelectorAll('.bp-viz-btn').forEach(function (x) { x.classList.remove('active'); });
		b.classList.add('active');
		var postbox = b.closest('.postbox');
		if (b.dataset.view === 'table') {
			postbox.classList.add('bp-show-tables');
		} else {
			postbox.classList.remove('bp-show-tables');
			var chart = postbox.querySelector('.bp-trend-chart');
			if (chart) render(chart);
		}
	});

	// Interacting with the chart shouldn't collapse/expand the postbox.
	Array.prototype.forEach.call(charts, function (c) {
		c.addEventListener('click', function (e) { e.stopPropagation(); });
	});

	if ('ResizeObserver' in window) {
		var ro = new ResizeObserver(function (entries) { entries.forEach(function (en) { debouncedRender(en.target); }); });
		Array.prototype.forEach.call(charts, function (c) { ro.observe(c); });
	} else {
		renderAll();
		window.addEventListener('resize', renderAll);
	}
});


// Analytics page — channel chart + visitor-behavior chart, weekly/monthly, isolated block
document.addEventListener('DOMContentLoaded', function () {
	var channelCharts  = document.querySelectorAll('.bp-analytics-chart');
	var behaviorCharts = document.querySelectorAll('.bp-analytics-behavior');
	var channelPies    = document.querySelectorAll('.bp-analytics-pie[data-pie="channel"]');
	var behaviorPies   = document.querySelectorAll('.bp-analytics-pie[data-pie="behavior"]');
	var tileEls        = document.querySelectorAll('.bp-an-tile[data-chs]');
	var techPies       = document.querySelectorAll('.bp-analytics-pie[data-pie="tech"]');
	var widthBars      = document.querySelectorAll('.bp-analytics-widthbars');
	var speedCharts    = document.querySelectorAll('.bp-analytics-speed');
	var locationCharts = document.querySelectorAll('.bp-analytics-locations');
	var locationZoomCharts = document.querySelectorAll('.bp-analytics-locations-zoom');
	var pageTrendCharts = document.querySelectorAll('.bp-analytics-pagetrend');
	var pagesPies      = document.querySelectorAll('.bp-analytics-pie[data-pie="pages"]');
	var clarityCharts = document.querySelectorAll('.bp-analytics-clarity');
	var scrollCharts = document.querySelectorAll('.bp-analytics-scroll');
	var eventCharts = document.querySelectorAll('.bp-analytics-events');
	if (!channelCharts.length && !behaviorCharts.length) return;

	var payloadEl = document.getElementById('bp-an-payload');
	if (!payloadEl) return;
	var payload;
	try { payload = JSON.parse(payloadEl.textContent); } catch (e) { return; }

	var SVGNS  = 'http://www.w3.org/2000/svg';
	var MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
	var CLIP_SEQ = 0;

	var CH_SERIES = [
		{ key: 'Organic Search', cls: 'red',    emph: true  },
		{ key: 'GBP',            cls: 'orange', emph: true  },
		{ key: 'Paid',           cls: 'green',  emph: false },
		{ key: 'Direct',         cls: 'grey',   emph: false },
		{ key: 'Social',         cls: 'blue',   emph: false },
		{ key: 'Referral',       cls: 'yellow', emph: false },
		{ key: 'Other',          cls: 'violet', emph: false }
	];

	// Behavior metrics: each = 1-2 lines derived from site totals. Field names beginning
	// with "_" are derived (see derive()); others read straight from the site object.
	var BEHAVIOR = {
		sessions_users: { fmt: 'int', series: [['Sessions','sessions','orange'], ['Users','users','purple']] },
		new_returning:  { fmt: 'int', pct: true, series: [['New','_new','orange'], ['Returning','_ret','purple']] },
		engaged:        { fmt: 'int', pct: true, series: [['Engaged','_eng','orange'], ['Non-engaged','_non','purple']] },
		pageviews:      { fmt: 'int', series: [['Pageviews','pageviews','red']], perUser: true },
		duration:       { fmt: 'dur', series: [['Engaged avg','_durEng','orange'], ['Session avg','_durSess','grey']] }
	};

	function el(tag, attrs) { var e = document.createElementNS(SVGNS, tag); for (var k in attrs) if (attrs.hasOwnProperty(k)) e.setAttribute(k, attrs[k]); return e; }
	function fmtInt(n) { return Math.round(n).toLocaleString('en-US'); }
	function fmtDur(s) { s = Math.round(s); return s < 60 ? s + 's' : Math.floor(s/60) + 'm ' + (s % 60) + 's'; }
	function fmtCompact(n) { n = Math.round(n); return n >= 1000 ? (n/1000).toFixed(n >= 10000 ? 0 : 1) + 'K' : '' + n; }

	// Value of a behavior field at period i (shared by the line chart and the pie).
	function deriveAt(site, i, field) {
		var se = site.sessions[i] || 0, u = site.users[i] || 0, nu = site.newUsers[i] || 0,
		    eng = site.engagedSessions[i] || 0, pv = site.pageviews[i] || 0, du = site.duration[i] || 0;
		switch (field) {
			case '_new':            return nu;
			case '_ret':            return Math.max(0, u - nu);
			case '_eng':            return eng;
			case '_non':            return Math.max(0, se - eng);
			case '_durEng':         return eng ? du / eng : 0;
			case '_durSess':        return se ? du / se : 0;
			case 'sessions':        return se;
			case 'users':           return u;
			case 'newUsers':        return nu;
			case 'engagedSessions': return eng;
			case 'pageviews':       return pv;
			case 'duration':        return du;
			default:                return 0;
		}
	}

	// Donut of slices [{label,value,cls}] — totals over the current range; % in the list.
	function drawDonut(container, slices) {
		container.innerHTML = '';
		var total = 0; slices.forEach(function (s) { total += s.value; });
		if (!total) { container.innerHTML = '<p class="bp-an-empty">No data.</p>'; return; }
		var size = 150, cx = size/2, cy = size/2, r = 68, ir = 42, gap = slices.length > 1 ? 0.03 : 0;
		var svg = el('svg', { 'class': 'bp-an-pie-svg', width: size, height: size, viewBox: '0 0 ' + size + ' ' + size });
		var a0 = -Math.PI/2;
		slices.forEach(function (s) {
			var a1 = a0 + (s.value/total) * Math.PI*2, s0 = a0 + gap/2, s1 = a1 - gap/2;
			if (s1 > s0) {
				var large = (s1-s0) > Math.PI ? 1 : 0;
				var x0=cx+r*Math.cos(s0), y0=cy+r*Math.sin(s0), x1=cx+r*Math.cos(s1), y1=cy+r*Math.sin(s1);
				var xi1=cx+ir*Math.cos(s1), yi1=cy+ir*Math.sin(s1), xi0=cx+ir*Math.cos(s0), yi0=cy+ir*Math.sin(s0);
				var d = 'M'+x0+','+y0+' A'+r+','+r+' 0 '+large+' 1 '+x1+','+y1+' L'+xi1+','+yi1+' A'+ir+','+ir+' 0 '+large+' 0 '+xi0+','+yi0+' Z';
				var path = el('path', { 'class': 'bp-an-pie-slice k-'+s.cls, d: d });
				var ttl = document.createElementNS(SVGNS, 'title'); ttl.textContent = s.label + ': ' + fmtInt(s.value) + ' (' + Math.round(s.value/total*100) + '%)';
				path.appendChild(ttl);
				svg.appendChild(path);
			}
			a0 = a1;
		});
		var ct = el('text', { 'class': 'bp-an-pie-center', x: cx, y: cy + 4, 'text-anchor': 'middle' });
		ct.textContent = fmtCompact(total);
		svg.appendChild(ct);
		container.appendChild(svg);

		var list = document.createElement('div'); list.className = 'bp-an-pie-list';
		slices.forEach(function (s) {
			var it = document.createElement('div'); it.className = 'bp-an-pie-item';
			it.innerHTML = '<span class="bp-an-key k-'+s.cls+'"></span><span class="bp-an-pie-lbl">' + s.label + '</span><span class="bp-an-pie-pct">' + Math.round(s.value/total*100) + '%</span>';
			list.appendChild(it);
		});
		container.appendChild(list);
	}

	function renderChannelPie(pieC) {
		var grain = activeGrain(), g = filterGrain(payload[grain], grain);
		if (!g) { pieC.innerHTML = ''; return; }
		var card = pieC.closest('.bp-an-card'), chart = card ? card.querySelector('.bp-analytics-chart') : null;
		var hidden = (chart && chart._hidden) || {};
		var slices = [];
		CH_SERIES.forEach(function (s) {
			if (hidden[s.key]) return;
			var arr = (g.channels && g.channels[s.key]) || [], tot = 0;
			arr.forEach(function (v) { tot += v; });
			if (tot > 0) slices.push({ label: s.key, value: tot, cls: s.cls });
		});
		drawDonut(pieC, slices);
	}

	// Behavior pie shows only for true partition metrics. Sessions·Users overlap (a user has
	// many sessions) so a pie there misleads — New·Returning is the real single-vs-repeat split.
	var BEHAVIOR_PIE = { new_returning: 1, engaged: 1 };
	// Tech pies (browsers/devices/screen widths) use per-window snapshots (7/30/90/180/365d);
	// pick the snapshot nearest the active range. Slices use a generic categorical palette.
	function techWindow() {
		var r = activeRange(), days;
		if (!r || !r.lo) days = 365;
		else { var hi = r.hi || new Date(); days = Math.round((hi - r.lo) / 86400000); }
		return days <= 15 ? 7 : days <= 60 ? 30 : days <= 135 ? 90 : days <= 270 ? 180 : 365;
	}
	function renderTechPie(pieC) {
		var tech = pieC.dataset.tech, data = payload.tech && payload.tech[tech];
		if (!data) { pieC.innerHTML = '<p class="bp-an-empty">No data.</p>'; return; }
		var arr = data[techWindow()] || data[365] || [];
		var gt = 0; arr.forEach(function (x) { gt += x.v; });
		arr = arr.filter(function (x) { return gt > 0 && Math.round(x.v / gt * 100) >= 1; });  // drop 0%-rounding bands
		var slices = arr.map(function (x, i) { return { label: x.l, value: x.v, cls: (x.c ? x.c : (x.l === 'Other' ? 'cother' : 'c' + (i % 11))) }; });
		drawDonut(pieC, slices);
	}

	// Ranked horizontal bars of the most-used exact widths — the top bar is the mode.
	function renderWidthBars(c) {
		var data = payload.tech && payload.tech.width;
		var arr = (data && (data[techWindow()] || data[365])) || [];
		if (!arr.length) { c.innerHTML = '<p class="bp-an-empty">No data.</p>'; return; }
		var total = 0, max = 0;
		arr.forEach(function (x) { total += x.v; if (x.v > max) max = x.v; });
		var html = '<div class="bp-an-bars">';
		arr.forEach(function (x, i) {
			var pct = total ? Math.round(x.v / total * 100) : 0;
			var bw = max ? (x.v / max * 100) : 0;
			var rc = 'bp-an-bar-row' + (x.o ? ' other' : (x.v === max ? ' mode' : ''));
			html += '<div class="' + rc + '">'
			     +    '<span class="bp-an-bar-lbl">' + x.l + '</span>'
			     +    '<span class="bp-an-bar-track"><span class="bp-an-bar-fill" style="width:' + bw + '%"></span></span>'
			     +    '<span class="bp-an-bar-val">' + fmtInt(x.v) + ' · ' + pct + '%</span>'
			     +  '</div>';
		});
		html += '</div>';
		c.innerHTML = html;
	}

	function renderBehaviorPie(pieC) {
		var card = pieC.closest('.bp-an-card'), metric = activeBehaviorMetric(card), def = BEHAVIOR[metric];
		if (!def || !BEHAVIOR_PIE[metric]) { pieC.innerHTML = ''; pieC.classList.add('bp-an-pie-off'); return; }
		pieC.classList.remove('bp-an-pie-off');
		var grain = activeGrain(), g = filterGrain(payload[grain], grain);
		if (!g) { pieC.innerHTML = ''; return; }
		var site = g.site, n = g.periods.length;
		var slices = def.series.map(function (sd) {
			var tot = 0; for (var i = 0; i < n; i++) tot += deriveAt(site, i, sd[1]);
			return { label: sd[0], value: tot, cls: sd[2] };
		}).filter(function (x) { return x.value > 0; });
		drawDonut(pieC, slices);
	}
	function niceMax(max, ticks) {
		if (max <= 0) return { max: 1, step: 1 };
		var raw = max / ticks, mag = Math.pow(10, Math.floor(Math.log10(raw))), norm = raw / mag;
		var nice = norm <= 1 ? 1 : norm <= 2 ? 2 : norm <= 5 ? 5 : 10, step = nice * mag;
		return { max: Math.ceil(max / step) * step, step: step };
	}
	function monotonePath(pts) {
		var n = pts.length, i;
		if (n === 0) return '';
		if (n === 1) return 'M' + pts[0][0] + ',' + pts[0][1];
		if (n === 2) return 'M' + pts[0][0] + ',' + pts[0][1] + 'L' + pts[1][0] + ',' + pts[1][1];
		var dx = [], dy = [], m = [];
		for (i = 0; i < n - 1; i++) { dx[i] = pts[i+1][0]-pts[i][0]; dy[i] = pts[i+1][1]-pts[i][1]; m[i] = dy[i]/dx[i]; }
		var t = []; t[0] = m[0]; t[n-1] = m[n-2];
		for (i = 1; i < n-1; i++) { if (m[i-1]*m[i] <= 0) { t[i] = 0; } else { var w1 = 2*dx[i]+dx[i-1], w2 = dx[i]+2*dx[i-1]; t[i] = (w1+w2)/(w1/m[i-1]+w2/m[i]); } }
		var d = 'M' + pts[0][0] + ',' + pts[0][1];
		for (i = 0; i < n-1; i++) { var x0 = pts[i][0], y0 = pts[i][1], x1 = pts[i+1][0], y1 = pts[i+1][1], h = dx[i]/3; d += 'C'+(x0+h)+','+(y0+t[i]*h)+' '+(x1-h)+','+(y1-t[i+1]*h)+' '+x1+','+y1; }
		return d;
	}
	function fmtPeriodFull(key, grain) {
		key = '' + key; var y = key.slice(0,4), mo = +key.slice(4,6) - 1;
		if (grain === 'monthly') return MONTHS[mo] + ' ' + y;
		return (grain === 'weekly' ? 'Week of ' : '') + MONTHS[mo] + ' ' + (+key.slice(6,8)) + ', ' + y;
	}

	function activeGrain() { var b = document.querySelector('.bp-an-gbtn.active'); return (b && b.dataset.grain) || 'monthly'; }

	// A period key -> the Date it starts on (month=1st; day/week=that day).
	function periodDate(key, grain) {
		key = '' + key; var y = +key.slice(0,4), mo = +key.slice(4,6) - 1;
		return grain === 'monthly' ? new Date(y, mo, 1) : new Date(y, mo, +key.slice(6,8));
	}
	// Selected range: custom dates win; else the active preset ("all" | N days).
	function activeRange() {
		var s = document.getElementById('bp-an-start'), e = document.getElementById('bp-an-end');
		if ((s && s.value) || (e && e.value)) {
			return { lo: s && s.value ? new Date(s.value + 'T00:00:00') : null, hi: e && e.value ? new Date(e.value + 'T23:59:59') : null };
		}
		var b = document.querySelector('.bp-an-rbtn.active'), d = b ? b.dataset.days : 'all';
		if (d === 'all') return null;
		var lo = new Date(); lo.setDate(lo.getDate() - (+d));
		return { lo: lo, hi: null };
	}
	var PIE_CH  = ['Organic Search','GBP','Paid','Direct','Social','Referral','Other'];
	var SITE_M  = ['sessions','users','newUsers','engagedSessions','pageviews','duration'];
	function pad2(n) { return (n < 10 ? '0' : '') + n; }
	// The grain bucket a day (YYYYMMDD) belongs to. Weekly = Monday-of-week (matches PHP).
	function bucketKey(ymd, grain) {
		ymd = '' + ymd;
		if (grain === 'daily')   return ymd;
		if (grain === 'monthly') return ymd.slice(0,6);
		var dt = new Date(+ymd.slice(0,4), +ymd.slice(4,6) - 1, +ymd.slice(6,8)), dow = (dt.getDay() + 6) % 7;
		dt.setDate(dt.getDate() - dow);
		return '' + dt.getFullYear() + pad2(dt.getMonth() + 1) + pad2(dt.getDate());
	}
	function dailyCovers(daily, lo) { return daily && daily.periods && daily.periods.length && periodDate(daily.periods[0], 'daily') <= lo; }

	// Rebuild grain buckets from DAILY over an exact [lo,hi] window, so the total is the
	// same at any grain (and line + pie agree). Edge buckets are partial by design.
	function resampleDaily(daily, grain, lo, hi) {
		var buckets = {}, order = [];
		daily.periods.forEach(function (k, i) {
			var dt = periodDate(k, 'daily');
			if ((lo && dt < lo) || (hi && dt > hi)) return;
			var bk = bucketKey(k, grain);
			if (!buckets[bk]) { buckets[bk] = { ch: {}, si: {} }; order.push(bk); PIE_CH.forEach(function (c) { buckets[bk].ch[c] = 0; }); SITE_M.forEach(function (m) { buckets[bk].si[m] = 0; }); }
			PIE_CH.forEach(function (c) { buckets[bk].ch[c] += (daily.channels[c] || [])[i] || 0; });
			SITE_M.forEach(function (m) { buckets[bk].si[m] += (daily.site[m] || [])[i] || 0; });
		});
		order.sort();
		var out = { periods: order.map(function (k) { return parseInt(k, 10); }), channels: {}, site: {} };
		PIE_CH.forEach(function (c) { out.channels[c] = order.map(function (k) { return buckets[k].ch[c]; }); });
		SITE_M.forEach(function (m) { out.site[m] = order.map(function (k) { return buckets[k].si[m]; }); });
		return out;
	}

	// Slice a grain's data to the active range. For a bounded range covered by daily, we
	// resample from daily so the totals are grain-independent; otherwise filter whole buckets.
	function filterGrain(g, grain) {
		var r = activeRange();
		if (!r) return g; // "All" — stored grain as-is (grains span different retained history)
		var daily = payload.daily;
		if (daily && r.lo && dailyCovers(daily, r.lo)) return resampleDaily(daily, grain, r.lo, r.hi);
		if (!g) return g;
		var idx = [];
		g.periods.forEach(function (k, i) { var dt = periodDate(k, grain); if ((!r.lo || dt >= r.lo) && (!r.hi || dt <= r.hi)) idx.push(i); });
		function pick(arr) { return idx.map(function (i) { return arr[i]; }); }
		var channels = {}, site = {}, c, m;
		for (c in g.channels) if (g.channels.hasOwnProperty(c)) channels[c] = pick(g.channels[c]);
		for (m in g.site) if (g.site.hasOwnProperty(m)) site[m] = pick(g.site[m]);
		return { periods: idx.map(function (i) { return g.periods[i]; }), channels: channels, site: site };
	}
	function activeBehaviorMetric(container) { var card = container.closest('.bp-an-card'); var b = card && card.querySelector('.bp-an-mbtn.active'); return (b && b.dataset.metric) || 'sessions_users'; }

	// Generic line chart. seriesDefs: [{key,label,cls,emph,values:[]}]. opts: {grain, fmt,
	// interactiveLegend, endLabels, rerender, tooltipExtra}.
	function drawLineChart(container, periods, seriesDefs, opts) {
		var width = container.clientWidth || 0;
		if (width < 80) return;
		var grain = opts.grain, isDur = opts.fmt === 'dur';
		var hidden = opts.interactiveLegend ? (container._hidden || (container._hidden = {})) : {};
		var withData = seriesDefs.filter(function (s) { return s.values.some(function (v) { return v > 0; }); });
		var vis = withData.filter(function (s) { return !hidden[s.key]; });

		var height = 300, mL = 54, mR = opts.endLabels ? 110 : 22, mT = 14, mB = 28;
		var plotL = mL, plotR = width - mR, plotT = mT, plotB = height - mB, plotW = plotR - plotL, plotH = plotB - plotT;
		var N = periods.length;

		var yMax = 0; vis.forEach(function (s) { s.values.forEach(function (v) { if (v > yMax) yMax = v; }); });
		var capped = container._yCap && container._yCap > 0;                                  // clicked y-axis cap
		var ny = niceMax(capped ? container._yCap : yMax, 4), yTop = capped ? container._yCap : ny.max;
		function xS(i) { return N <= 1 ? plotL : plotL + (i/(N-1))*plotW; }
		function yS(v) { return plotB - (v/yTop)*plotH; }
		function yfmt(v) { return opts.yFmt ? opts.yFmt(v) : (isDur ? (v >= 60 ? Math.round(v/60) + 'm' : Math.round(v) + 's') : fmtInt(v)); }
		function vfmt(v) { return opts.vFmt ? opts.vFmt(v) : (isDur ? fmtDur(v) : fmtInt(v)); }

		container.innerHTML = '';

		var legend = document.createElement('div'); legend.className = 'bp-an-legend';
		withData.forEach(function (s) {
			var it = document.createElement('span');
			it.className = 'bp-an-leg' + (s.emph ? ' emph' : '') + (hidden[s.key] ? ' off' : '') + (opts.interactiveLegend ? '' : ' static');
			it.innerHTML = '<span class="bp-an-key k-'+s.cls+'"></span>' + s.label;
			if (opts.interactiveLegend) it.addEventListener('click', function () { if (hidden[s.key]) { delete hidden[s.key]; } else { hidden[s.key] = true; } opts.rerender(); });
			legend.appendChild(it);
		});
		container.appendChild(legend);
		if (!vis.length) { var em = document.createElement('p'); em.className = 'bp-an-empty'; em.textContent = 'Nothing to show — click a channel above.'; container.appendChild(em); return; }

		var svg = el('svg', { 'class': 'bp-an-svg', width: '100%', height: height, viewBox: '0 0 ' + width + ' ' + height });

		for (var v = 0; v <= yTop + 1e-9; v += ny.step) {
			var gy = yS(v);
			svg.appendChild(el('line', { 'class': 'bp-an-grid', x1: plotL, y1: gy, x2: plotR, y2: gy }));
			var yl = el('text', { 'class': 'bp-an-ylab' + (v > 0 ? ' bp-an-ycap' : '') + (capped && Math.abs(v - yTop) < 1e-6 ? ' capped' : ''), x: plotL - 8, y: gy + 3, 'text-anchor': 'end' });
			yl.textContent = yfmt(v);
			svg.appendChild(yl);
			if (v > 0) { // clickable band over the label: cap the scale here (click again to reset)
				var band = el('rect', { x: 0, y: gy - 9, width: plotL, height: 18, fill: 'transparent', style: 'cursor:pointer' });
				var tt = document.createElementNS(SVGNS, 'title'); tt.textContent = 'Cap the scale here (click again to reset)'; band.appendChild(tt);
				(function (val) { band.addEventListener('click', function (e) { e.stopPropagation(); container._yCap = (container._yCap && Math.abs(container._yCap - val) < 1e-6) ? null : val; if (opts.rerender) opts.rerender(); }); })(v);
				svg.appendChild(band);
			}
		}

		// Optional reference line (e.g. the "good" speed threshold).
		if (opts.threshold != null && opts.threshold > 0 && opts.threshold <= yTop) {
			var ty = yS(opts.threshold);
			svg.appendChild(el('line', { 'class': 'bp-an-threshold', x1: plotL, y1: ty, x2: plotR, y2: ty }));
			if (opts.thresholdLabel) { var thl = el('text', { 'class': 'bp-an-threshlab', x: plotR - 3, y: ty - 4, 'text-anchor': 'end' }); thl.textContent = opts.thresholdLabel; svg.appendChild(thl); }
		}

		var lastX = -999, prevMo = null;
		periods.forEach(function (keyRaw, i) {
			var key = '' + keyRaw, mo = +key.slice(4,6), isJan = mo === 1, x = xS(i), major;
			var dayGrain = (grain === 'weekly' || grain === 'daily');
			if (dayGrain) { major = (mo !== prevMo); prevMo = mo; } else { major = isJan || i === 0; }
			if (dayGrain) { if (!major || x - lastX < 34) return; }   // day/week: month boundaries only
			else if (!major && x - lastX < 46) return;
			if (x - lastX < 22) return;
			lastX = x;
			var lbl = MONTHS[mo-1] + (isJan || (i === 0 && grain === 'monthly') ? " '" + key.slice(2,4) : '');
			var xl = el('text', { 'class': 'bp-an-xlab' + (isJan ? ' yr' : ''), x: x, y: plotB + 18, 'text-anchor': 'middle' });
			xl.textContent = lbl; svg.appendChild(xl);
		});

		// Data marks live in a clipped group so a capped spike vanishes off the top ("nowhere land").
		var clipId = 'bpcl' + (++CLIP_SEQ);
		var defs = el('defs', {}), cp = el('clipPath', { id: clipId });
		cp.appendChild(el('rect', { x: plotL, y: plotT - 1, width: plotW + 10, height: plotH + 2 }));
		defs.appendChild(cp); svg.appendChild(defs);
		var marks = el('g', capped ? { 'clip-path': 'url(#' + clipId + ')' } : {});

		var order = vis.slice().sort(function (a, b) { return (a.emph?1:0) - (b.emph?1:0); });
		var ends = [];
		order.forEach(function (s) {
			var coords = s.values.map(function (val, i) { return [xS(i), yS(val)]; });
			marks.appendChild(el('path', { 'class': 'bp-an-line k-'+s.cls + (s.emph ? ' emph' : ''), d: monotonePath(coords) }));
			if (opts.endLabels && s.values.length) {
				var li = s.values.length - 1, ex = xS(li), ey = yS(s.values[li]);
				marks.appendChild(el('circle', { 'class': 'bp-an-dotring', cx: ex, cy: ey, r: 5 }));
				marks.appendChild(el('circle', { 'class': 'bp-an-dot k-'+s.cls, cx: ex, cy: ey, r: 3.5 }));
				ends.push({ y: Math.max(plotT, Math.min(plotB, ey)), label: s.label, cls: s.cls }); // clamp so off-scale labels sit at the edge
			}
		});

		var focus = el('g', { 'class': 'bp-an-focus', style: 'display:none' });
		var vLine = el('line', { 'class': 'bp-an-cross', x1: 0, y1: plotT, x2: 0, y2: plotB });        // vertical (at hovered day)
		var hLine = el('line', { 'class': 'bp-an-cross', x1: plotL, y1: 0, x2: plotR, y2: 0 });        // horizontal (at cursor height)
		focus.appendChild(vLine); focus.appendChild(hLine);
		var fdots = vis.map(function (s) {
			var ring = el('circle', { 'class': 'bp-an-dotring', r: 5, cx: 0, cy: 0 });
			var dot  = el('circle', { 'class': 'bp-an-dot k-'+s.cls, r: 3.5, cx: 0, cy: 0 });
			focus.appendChild(ring); focus.appendChild(dot);
			return { s: s, dot: dot, ring: ring };
		});
		marks.appendChild(focus);
		svg.appendChild(marks);

		if (opts.endLabels) {
			ends.sort(function (a, b) { return a.y - b.y; });
			for (var k = 1; k < ends.length; k++) { if (ends[k].y - ends[k-1].y < 13) ends[k].y = ends[k-1].y + 13; }
			ends.forEach(function (L) { var t = el('text', { 'class': 'bp-an-endlab k-'+L.cls, x: plotR + 8, y: L.y + 3 }); t.textContent = L.label; svg.appendChild(t); });
		}

		var hit = el('rect', { 'class': 'bp-an-hit', x: plotL, y: plotT, width: plotW, height: plotH, fill: 'transparent' });
		svg.appendChild(hit);
		container.appendChild(svg);

		var tip = document.createElement('div'); tip.className = 'bp-an-tip'; tip.style.display = 'none'; container.appendChild(tip);

		hit.addEventListener('mousemove', function (ev) {
			var rect = svg.getBoundingClientRect(), scale = width / (rect.width || width);
			var px = (ev.clientX - rect.left) * scale;
			var i = N <= 1 ? 0 : Math.round(((px - plotL)/plotW)*(N-1));
			if (i < 0) i = 0; if (i > N-1) i = N-1;
			var cx = xS(i);
			var my = Math.max(plotT, Math.min(plotB, (ev.clientY - rect.top) * (height / (rect.height || height)))); // cursor height
			focus.style.display = '';
			vLine.setAttribute('x1', cx); vLine.setAttribute('x2', cx);
			hLine.setAttribute('y1', my); hLine.setAttribute('y2', my);
			var html = '<div class="bp-an-tip-head">' + fmtPeriodFull(periods[i], grain) + '</div>';
			var total = 0;
			if (opts.showPct) fdots.forEach(function (f) { total += (f.s.values[i] || 0); });
			fdots.forEach(function (f) {
				var val = f.s.values[i] || 0, py = yS(val);
				f.dot.setAttribute('cx', cx); f.dot.setAttribute('cy', py);
				f.ring.setAttribute('cx', cx); f.ring.setAttribute('cy', py);
				var vtxt = vfmt(val);
				if (opts.showPct && total > 0) vtxt += ' <span class="bp-an-tip-pct">(' + Math.round((val / total) * 100) + '%)</span>';
				html += '<div class="bp-an-tip-row"><span class="bp-an-key k-'+f.s.cls+'"></span>' + f.s.label + '<span class="bp-an-tip-v">' + vtxt + '</span></div>';
			});
			if (opts.tooltipExtra) html += opts.tooltipExtra(i);
			tip.innerHTML = html; tip.style.display = 'block';
			var tw = tip.offsetWidth;
			tip.style.left = (cx + 14 + tw > width ? Math.max(2, cx - 14 - tw) : cx + 14) + 'px';
		});
		hit.addEventListener('mouseleave', function () { focus.style.display = 'none'; tip.style.display = 'none'; });
	}

	function renderChannels(container) {
		var grain = activeGrain(), g = filterGrain(payload[grain], grain);
		if (!g || !g.periods.length) { container.innerHTML = '<p class="bp-an-empty">No data for this view/range.</p>'; return; }
		var defs = CH_SERIES.map(function (s) { return { key: s.key, label: s.key, cls: s.cls, emph: s.emph, values: (g.channels && g.channels[s.key]) || [] }; });
		// 6th series: Total (sum of all channels) — overall traffic trend. Not in the pie.
		var totalVals = g.periods.map(function (_, i) { var t = 0; CH_SERIES.forEach(function (s) { t += ((g.channels && g.channels[s.key]) || [])[i] || 0; }); return t; });
		defs.push({ key: 'Total', label: 'Total', cls: 'total', emph: false, values: totalVals });
		drawLineChart(container, g.periods, defs, { grain: grain, fmt: 'int', interactiveLegend: true, endLabels: true, rerender: function () {
			renderChannels(container);
			var pie = container.closest('.bp-an-card').querySelector('.bp-analytics-pie');
			if (pie) renderChannelPie(pie);
		} });
	}

	// Site-speed chart — lab vs real-user over time (Mobile vs Desktop), its own daily audit history.
	var SPEED_META = {
		lcp:    { unit: 's', good: 2.5, dir: 'low'  },
		load:   { unit: 's', good: 2,   dir: 'low'  },
		target: { unit: '%', good: 90,  dir: 'high' },
		score:  { unit: '',  good: 90,  dir: 'high' }
	};
	function activeSpeedMetric(container) { var card = container.closest('.bp-an-card'); var b = card && card.querySelector('.bp-an-sbtn.active'); return (b && b.dataset.smetric) || 'load'; }
	// Speed is an AVERAGE metric (not a sum like sessions), so bucket by grain and take the mean
	// within each bucket, clipped to the active range — mirrors the View/Range controls.
	function resampleSpeed(d, grain, r) {
		var buckets = {}, order = [];
		d.periods.forEach(function (k, i) {
			var dt = periodDate('' + k, 'daily');
			if (r && ((r.lo && dt < r.lo) || (r.hi && dt > r.hi))) return;
			var bk = bucketKey(k, grain);
			if (!buckets[bk]) { buckets[bk] = { mS: 0, mN: 0, dS: 0, dN: 0 }; order.push(bk); }
			var mv = d.mobile[i], dv = d.desktop[i];
			if (mv > 0) { buckets[bk].mS += mv; buckets[bk].mN++; }
			if (dv > 0) { buckets[bk].dS += dv; buckets[bk].dN++; }
		});
		order.sort();
		return {
			periods: order.slice(),
			mobile:  order.map(function (k) { return buckets[k].mN ? buckets[k].mS / buckets[k].mN : 0; }),
			desktop: order.map(function (k) { return buckets[k].dN ? buckets[k].dS / buckets[k].dN : 0; })
		};
	}
	function renderSpeed(container) {
		var sp = payload.speed || {}, metric = activeSpeedMetric(container), d0 = sp[metric];
		if (!d0 || !d0.periods || !d0.periods.length) { container.innerHTML = '<p class="bp-an-empty">No speed history yet — it fills in as the nightly audit runs.</p>'; return; }
		var grain = activeGrain(), d = resampleSpeed(d0, grain, activeRange());
		if (!d.periods.length) { container.innerHTML = '<p class="bp-an-empty">No speed data in this range.</p>'; return; }
		var meta = SPEED_META[metric] || SPEED_META.lcp, unit = meta.unit;
		var fmtV = unit === 's' ? function (v) { return (Math.round(v * 10) / 10) + 's'; }
		         : unit === '%' ? function (v) { return Math.round(v) + '%'; }
		         :                function (v) { return Math.round(v); };
		var defs = [
			{ key: 'Mobile',  label: 'Mobile',  cls: 'green', emph: true, values: d.mobile },
			{ key: 'Desktop', label: 'Desktop', cls: 'blue',  emph: true, values: d.desktop }
		];
		drawLineChart(container, d.periods, defs, {
			grain: grain, fmt: 'int', interactiveLegend: false, endLabels: true,
			yFmt: fmtV, vFmt: fmtV,
			threshold: meta.good, thresholdLabel: fmtV(meta.good) + (meta.dir === 'high' ? ' goal' : ' good'),
			rerender: function () { renderSpeed(container); }
		});
	}

	function renderBehavior(container) {
		var grain = activeGrain(), g = filterGrain(payload[grain], grain);
		if (!g || !g.periods.length) { container.innerHTML = '<p class="bp-an-empty">No data for this view/range.</p>'; return; }
		var metric = activeBehaviorMetric(container), def = BEHAVIOR[metric] || BEHAVIOR.sessions_users;
		var site = g.site, n = g.periods.length;
		function derive(field) { var o = []; for (var i = 0; i < n; i++) o.push(deriveAt(site, i, field)); return o; }
		var defs = def.series.map(function (sd) { return { key: sd[0], label: sd[0], cls: sd[2], emph: true, values: derive(sd[1]) }; });
		var opts = { grain: grain, fmt: def.fmt, interactiveLegend: false, endLabels: true, showPct: !!def.pct, rerender: function () { renderBehavior(container); } };
		if (def.perUser) opts.tooltipExtra = function (i) {
			var u = site.users[i] || 0, pv = site.pageviews[i] || 0;
			return '<div class="bp-an-tip-row"><span class="bp-an-key" style="visibility:hidden"></span>Pages / user<span class="bp-an-tip-v">' + (u ? (Math.round((pv/u)*10)/10) : 0) + '</span></div>';
		};
		drawLineChart(container, g.periods, defs, opts);
	}

	// Sum channels' sessions over an exact [lo,hi] day window from daily. null if uncovered.
	function daySum(chs, loD, hiD) {
		var daily = payload.daily;
		if (!daily || !daily.periods || !daily.periods.length) return null;
		if (periodDate(daily.periods[0], 'daily') > loD) return null; // daily doesn't reach back far enough
		var t = 0;
		daily.periods.forEach(function (k, i) {
			var d = periodDate(k, 'daily');
			if (d >= loD && d <= hiD) chs.forEach(function (c) { t += (daily.channels[c] || [])[i] || 0; });
		});
		return t;
	}
	function rangeLabelText() {
		var s = document.getElementById('bp-an-start'), e = document.getElementById('bp-an-end');
		if ((s && s.value) || (e && e.value)) return (s && s.value ? s.value : '…') + ' – ' + (e && e.value ? e.value : 'today');
		var b = document.querySelector('.bp-an-rbtn.active'), d = b ? b.dataset.days : 'all';
		if (d === 'all') return 'All time';
		var n = +d;
		if (n % 365 === 0) { var yr = n / 365; return 'Last ' + yr + ' year' + (yr > 1 ? 's' : ''); }
		return 'Last ' + n + ' days';
	}
	function tileDelta(pct, label) {
		if (pct === null) return '<span class="bp-an-delta none">' + label + ' —</span>';
		var dir = pct > 0 ? 'up' : (pct < 0 ? 'down' : 'flat'), arr = pct > 0 ? '▲' : (pct < 0 ? '▼' : '–');
		return '<span class="bp-an-delta ' + dir + '">' + label + ' ' + arr + ' ' + Math.abs(pct) + '%</span>';
	}
	// Page trends — monthly pageviews for the single selected page (pill), sliced to the active range.
	function activePageKey(container) {
		var card = container.closest('.bp-an-card'), b = card && card.querySelector('.bp-an-pbtn.active');
		var list = payload.pages && payload.pages.list;
		return (b && b.dataset.pkey) || (list && list[0] && list[0].key);
	}
	function renderPageTrend(container) {
		var data = payload.pages;
		if (!data || !data.list || !data.list.length) { container.innerHTML = '<p class="bp-an-empty">No page history yet — it builds on the nightly analytics run (or hit “refresh stats now” on the Stats page).</p>'; return; }
		var grain = activeGrain(), g = data[grain] || data.monthly;   // follows the Daily/Weekly/Monthly toggle
		if (!g || !g.periods.length) { container.innerHTML = '<p class="bp-an-empty">No page data at this view.</p>'; return; }
		var key = activePageKey(container);
		if (!g.series[key]) key = data.list[0].key;
		var full = g.series[key] || [];
		var r = activeRange(), lo = r ? r.lo : null, hi = r ? (r.hi || new Date()) : null, idx = [];
		g.periods.forEach(function (k, j) { var dt = periodDate(k, grain); if ((!lo || dt >= lo) && (!hi || dt <= hi)) idx.push(j); });
		if (!idx.length) idx = g.periods.map(function (_, j) { return j; });
		var periods = idx.map(function (j) { return g.periods[j]; });
		var values = idx.map(function (j) { return full[j]; });
		var label = key, i;
		for (i = 0; i < data.list.length; i++) if (data.list[i].key === key) { label = data.list[i].label; break; }
		var defs = [{ key: label, label: label, cls: 'blue', emph: true, values: values }];
		drawLineChart(container, periods, defs, { grain: grain, fmt: 'int', interactiveLegend: false, endLabels: true, rerender: function () { renderPageTrend(container); } });
	}
	// Page-share donut — each top page's slice of TOTAL pageviews over the active range/grain, with an
	// "Other pages" slice for everything outside the top list. Total is summed from the site pageviews
	// over the SAME period keys the pages cover, so the split is exact (both are screenPageViews).
	function renderPagesPie(pieC) {
		var data = payload.pages;
		if (!data || !data.list || !data.list.length) { pieC.innerHTML = ''; return; }
		var grain = activeGrain(), g = data[grain] || data.monthly;
		if (!g || !g.periods.length) { pieC.innerHTML = ''; return; }
		var r = activeRange(), lo = r ? r.lo : null, hi = r ? (r.hi || new Date()) : null, idx = [];
		g.periods.forEach(function (k, j) { var dt = periodDate(k, grain); if ((!lo || dt >= lo) && (!hi || dt <= hi)) idx.push(j); });
		if (!idx.length) idx = g.periods.map(function (_, j) { return j; });
		var keys = idx.map(function (j) { return g.periods[j]; });
		var slices = [], topSum = 0;
		data.list.forEach(function (p, i) {
			var arr = g.series[p.key] || [], sum = 0;
			idx.forEach(function (j) { sum += arr[j] || 0; });
			if (sum > 0) { slices.push({ label: p.label, value: sum, cls: 'c' + (i % 11) }); topSum += sum; }
		});
		var gt = filterGrain(payload[grain], grain), pv = {}, totalPV = 0;
		if (gt && gt.periods && gt.site && gt.site.pageviews) gt.periods.forEach(function (k, j) { pv['' + k] = gt.site.pageviews[j] || 0; });
		keys.forEach(function (k) { totalPV += pv['' + k] || 0; });
		var other = Math.max(0, Math.round(totalPV - topSum));
		if (other > 0) slices.push({ label: 'Other pages', value: other, cls: 'cother' });
		if (!slices.length) { pieC.innerHTML = '<p class="bp-an-empty">No data.</p>'; return; }
		drawDonut(pieC, slices);
	}
	// UX health (Clarity) — single selected frustration metric, follows the grain + range controls.
	function activeClarityMetric(container) {
		var card = container.closest('.bp-an-card'), b = card && card.querySelector('.bp-an-cbtn.active');
		var metrics = payload.clarity && payload.clarity.metrics;
		return (b && b.dataset.cmetric) || (metrics && metrics[0] && metrics[0].key);
	}
	function renderClarity(container) {
		var data = payload.clarity;
		if (!data || !data.metrics || !data.metrics.length) { container.innerHTML = '<p class="bp-an-empty">No Clarity data yet — it builds nightly once a Data Export token is set for this site.</p>'; return; }
		var grain = activeGrain(), g = data[grain] || data.daily || data.monthly;
		if (!g || !g.periods.length) { container.innerHTML = '<p class="bp-an-empty">No Clarity data at this view.</p>'; return; }
		var key = activeClarityMetric(container);
		if (!g.series[key]) key = data.metrics[0].key;
		var full = g.series[key] || [];
		var r = activeRange(), lo = r ? r.lo : null, hi = r ? (r.hi || new Date()) : null, idx = [];
		g.periods.forEach(function (k, j) { var dt = periodDate(k, grain); if ((!lo || dt >= lo) && (!hi || dt <= hi)) idx.push(j); });
		if (!idx.length) idx = g.periods.map(function (_, j) { return j; });
		var periods = idx.map(function (j) { return g.periods[j]; });
		var values = idx.map(function (j) { return full[j]; });
		var label = key, i;
		for (i = 0; i < data.metrics.length; i++) if (data.metrics[i].key === key) { label = data.metrics[i].label; break; }
		var defs = [{ key: label, label: label, cls: 'red', emph: true, values: values }];
		drawLineChart(container, periods, defs, { grain: grain, fmt: 'int', interactiveLegend: false, endLabels: true, rerender: function () { renderClarity(container); } });
	}
	// Tracked events — the single selected component/conversion, follows the grain + range controls.
	// Components plot as % of engaged sessions (teal); conversions plot as counts (green).
	function activeEventKey(container) {
		var card = container.closest('.bp-an-card'), b = card && card.querySelector('.bp-an-ebtn.active');
		var items = payload.events && payload.events.items;
		return (b && b.dataset.ekey) || (items && items[0] && items[0].key);
	}
	function renderEvents(container) {
		var data = payload.events;
		if (!data || !data.items || !data.items.length) { container.innerHTML = '<p class="bp-an-empty">No tracked-event history yet — it builds on the nightly analytics run (or hit “refresh stats now” on the Stats page).</p>'; return; }
		var grain = activeGrain(), g = data[grain] || data.daily || data.monthly;   // follows the Daily/Weekly/Monthly toggle
		if (!g || !g.periods.length) { container.innerHTML = '<p class="bp-an-empty">No tracked-event data at this view.</p>'; return; }
		var key = activeEventKey(container);
		if (!g.series[key]) key = data.items[0].key;
		var full = g.series[key] || [];
		var r = activeRange(), lo = r ? r.lo : null, hi = r ? (r.hi || new Date()) : null, idx = [];
		g.periods.forEach(function (k, j) { var dt = periodDate(k, grain); if ((!lo || dt >= lo) && (!hi || dt <= hi)) idx.push(j); });
		if (!idx.length) idx = g.periods.map(function (_, j) { return j; });
		var periods = idx.map(function (j) { return g.periods[j]; });
		var values = idx.map(function (j) { return full[j]; });
		var label = key, type = 'count', i;
		for (i = 0; i < data.items.length; i++) if (data.items[i].key === key) { label = data.items[i].label; type = data.items[i].type; break; }
		var isPct = type === 'pct';
		var opts = { grain: grain, fmt: 'int', interactiveLegend: false, endLabels: true, rerender: function () { renderEvents(container); } };
		if (isPct) {
			opts.yFmt = function (v) { return Math.round(v) + '%'; };
			opts.vFmt = function (v) { return (Math.round(v * 10) / 10) + '%'; };
		}
		var defs = [{ key: label, label: label, cls: isPct ? 'teal' : 'green', emph: true, values: values }];
		drawLineChart(container, periods, defs, opts);
	}
	// Content Visibility — the depth distribution stacked to 100% over time (deepest = dark, bottom).
	// Legend labels the drawn slices (ranges); the tooltip reports CUMULATIVE reach (reached ≥ depth).
	function bpScrollSlice(container) {
		var data = payload.scroll, grain = activeGrain(), g = data && (data[grain] || data.monthly);
		if (!g || !g.periods.length) return null;
		var r = activeRange(), lo = r ? r.lo : null, hi = r ? (r.hi || new Date()) : null, idx = [];
		g.periods.forEach(function (k, j) { var dt = periodDate(k, grain); if ((!lo || dt >= lo) && (!hi || dt <= hi)) idx.push(j); });
		if (!idx.length) idx = g.periods.map(function (_, j) { return j; });
		return { grain: grain, periods: idx.map(function (j) { return g.periods[j]; }),
			pick: function (t) { return idx.map(function (j) { return g.series[t] ? g.series[t][j] : 0; }); } };
	}
	function renderScroll(container) {
		var data = payload.scroll;
		if (!data || !data.thresholds) { container.innerHTML = '<p class="bp-an-empty">No scroll-depth history yet — it builds on the nightly analytics run.</p>'; return; }
		var s = bpScrollSlice(container);
		if (!s) { container.innerHTML = '<p class="bp-an-empty">No scroll data at this view.</p>'; return; }
		var T = data.thresholds, cum = {}; T.forEach(function (t) { cum[t] = s.pick(t); });
		// Each band's drawn height is its slice (cum[t] − cum[deeper]); its tooltip value is cum[t] itself
		// (the cumulative "reached at least this depth", which is what reads intuitively).
		var bands = [{ key: '100', label: 'Reached 100%', cls: 'd100', values: cum['100'], tipLabel: 'Reached 100%', tipVals: cum['100'] }];
		for (var i = T.length - 2; i >= 0; i--) {
			var t = T[i], deeper = T[i + 1];
			bands.push({ key: t, label: t + '–' + deeper + '%', cls: 'd' + t,
				values: cum[t].map(function (v, j) { return Math.max(0, v - cum[deeper][j]); }),
				tipLabel: 'Reached ≥' + t + '%', tipVals: cum[t] });
		}
		// The "Under 20%" band is drawn as its slice (100 − reached≥20%), but cumulatively it's the base —
		// everyone who loaded the page — so the tooltip shows 100%.
		var under = cum['20'].map(function (v) { return Math.max(0, 100 - v); });
		bands.push({ key: 'under', label: 'Under 20%', cls: 'dunder', values: under, tipLabel: 'Loaded (all)', tipVals: under.map(function () { return 100; }) });
		drawStackedBands(container, s.periods, bands, { grain: s.grain });
	}
	// Stacked-area renderer for the depth-bands view (bands sum to ~100% per period).
	function drawStackedBands(container, periods, bands, opts) {
		var width = container.clientWidth || 0; if (width < 80) return;
		var height = 300, mL = 54, mR = 22, mT = 14, mB = 28;
		var plotL = mL, plotR = width - mR, plotT = mT, plotB = height - mB, plotW = plotR - plotL, plotH = plotB - plotT, N = periods.length;
		function xS(i) { return N <= 1 ? plotL : plotL + (i / (N - 1)) * plotW; }
		function yS(v) { return plotB - (v / 100) * plotH; }
		container.innerHTML = '';
		var legend = document.createElement('div'); legend.className = 'bp-an-legend';
		bands.forEach(function (b) { var it = document.createElement('span'); it.className = 'bp-an-leg static'; it.innerHTML = '<span class="bp-an-key k-' + b.cls + '"></span>' + b.label; legend.appendChild(it); });
		container.appendChild(legend);
		var svg = el('svg', { 'class': 'bp-an-svg', width: '100%', height: height, viewBox: '0 0 ' + width + ' ' + height });
		[0, 25, 50, 75, 100].forEach(function (v) {
			var gy = yS(v);
			svg.appendChild(el('line', { 'class': 'bp-an-grid', x1: plotL, y1: gy, x2: plotR, y2: gy }));
			var yl = el('text', { 'class': 'bp-an-ylab', x: plotL - 8, y: gy + 3, 'text-anchor': 'end' }); yl.textContent = v + '%'; svg.appendChild(yl);
		});
		var lastX = -999, prevMo = null;
		periods.forEach(function (keyRaw, i) {
			var key = '' + keyRaw, mo = +key.slice(4, 6), isJan = mo === 1, x = xS(i), major, dayGrain = (opts.grain === 'weekly' || opts.grain === 'daily');
			if (dayGrain) { major = (mo !== prevMo); prevMo = mo; } else { major = isJan || i === 0; }
			if (dayGrain) { if (!major || x - lastX < 34) return; } else if (!major && x - lastX < 46) return;
			if (x - lastX < 22) return; lastX = x;
			var lbl = MONTHS[mo - 1] + (isJan || (i === 0 && opts.grain === 'monthly') ? " '" + key.slice(2, 4) : '');
			var xl = el('text', { 'class': 'bp-an-xlab' + (isJan ? ' yr' : ''), x: x, y: plotB + 18, 'text-anchor': 'middle' }); xl.textContent = lbl; svg.appendChild(xl);
		});
		var cumBase = []; for (var i = 0; i < N; i++) cumBase.push(0);
		var g = el('g', {});
		bands.forEach(function (b) {
			var top = [], bot = [], j;
			for (j = 0; j < N; j++) { var base = cumBase[j], t = base + (b.values[j] || 0); bot.push([xS(j), yS(base)]); top.push([xS(j), yS(t)]); cumBase[j] = t; }
			var d = 'M' + top[0][0] + ',' + top[0][1];
			for (j = 1; j < N; j++) d += 'L' + top[j][0] + ',' + top[j][1];
			for (j = N - 1; j >= 0; j--) d += 'L' + bot[j][0] + ',' + bot[j][1];
			g.appendChild(el('path', { 'class': 'bp-an-band k-' + b.cls, d: d + 'Z' }));
		});
		svg.appendChild(g);
		var focus = el('g', { 'class': 'bp-an-focus', style: 'display:none' });
		var vLine = el('line', { 'class': 'bp-an-cross', x1: 0, y1: plotT, x2: 0, y2: plotB }); focus.appendChild(vLine); svg.appendChild(focus);
		var hit = el('rect', { 'class': 'bp-an-hit', x: plotL, y: plotT, width: plotW, height: plotH, fill: 'transparent' }); svg.appendChild(hit);
		container.appendChild(svg);
		var tip = document.createElement('div'); tip.className = 'bp-an-tip'; tip.style.display = 'none'; container.appendChild(tip);
		hit.addEventListener('mousemove', function (ev) {
			var rect = svg.getBoundingClientRect(), scale = width / (rect.width || width);
			var px = (ev.clientX - rect.left) * scale, i = N <= 1 ? 0 : Math.round(((px - plotL) / plotW) * (N - 1));
			if (i < 0) i = 0; if (i > N - 1) i = N - 1;
			focus.style.display = ''; var x = xS(i); vLine.setAttribute('x1', x); vLine.setAttribute('x2', x);
			var rows = bands.map(function (b) { return '<div class="bp-an-tip-row"><span class="bp-an-key k-' + b.cls + '"></span>' + b.tipLabel + '<span class="bp-an-tip-v">' + Math.round(b.tipVals[i] || 0) + '%</span></div>'; }).join('');
			tip.innerHTML = '<div class="bp-an-tip-h">' + fmtPeriodFull(periods[i], opts.grain) + '</div>' + rows;
			tip.style.display = 'block';
			var rr = container.getBoundingClientRect(), lx = (x / width) * rr.width;
			tip.style.left = Math.min(rr.width - tip.offsetWidth - 6, Math.max(6, lx + 12)) + 'px'; tip.style.top = '34px';
		});
		hit.addEventListener('mouseleave', function () { focus.style.display = 'none'; tip.style.display = 'none'; });
	}
	// Hero tiles follow the active range: total over the window + "vs prev" and YoY deltas.
	function renderTiles() {
		if (!tileEls.length) return;
		var grain = activeGrain(), g = filterGrain(payload[grain], grain), r = activeRange();
		var pl = document.getElementById('bp-an-period-lbl'); if (pl) pl.textContent = rangeLabelText();
		var lo = r ? r.lo : null, hi = r ? (r.hi || new Date()) : null;
		Array.prototype.forEach.call(tileEls, function (tile) {
			var chs = tile.dataset.chs.split(',');
			var cur = 0; if (g) chs.forEach(function (c) { (g.channels[c] || []).forEach(function (v) { cur += v; }); });
			var mom = null, yoy = null;
			if (lo && hi) {
				var prev = daySum(chs, new Date(lo.getTime() - (hi - lo)), lo);
				var y1lo = new Date(lo), y1hi = new Date(hi); y1lo.setFullYear(y1lo.getFullYear() - 1); y1hi.setFullYear(y1hi.getFullYear() - 1);
				var yv = daySum(chs, y1lo, y1hi);
				if (prev !== null && prev > 0) mom = Math.round((cur - prev) / prev * 100);
				if (yv   !== null && yv   > 0) yoy = Math.round((cur - yv)   / yv   * 100);
			}
			tile.querySelector('.bp-an-tile-value').innerHTML = fmtInt(cur) + '<span class="bp-an-tile-unit">sessions</span>';
			tile.querySelector('.bp-an-tile-deltas').innerHTML = tileDelta(mom, 'vs prev') + tileDelta(yoy, 'YoY');
		});
	}

	// Visitor map — geocoded city bubbles over a simplified US basemap. The basemap rings and the
	// bubbles share ONE Albers projection (below), so points always land on the map by construction.
	var BP_US_RINGS = null;
	var BP_US_RINGS_STR = '[[-87.36,35,-85.61,34.98,-85.18,32.86,-84.89,32.26,-85.14,31.84,-85,31,-87.6,31,-87.37,30.43,-87.66,30.25,-88.01,30.69,-88.14,30.32,-88.39,30.37,-88.47,31.9,-88.2,35,-87.36,35],[-109.04,37,-109.05,31.33,-111.07,31.33,-114.82,32.49,-114.47,32.84,-114.73,33.41,-114.52,33.55,-114.54,33.93,-114.14,34.31,-114.63,34.88,-114.74,36.1,-114.15,36.03,-114.05,37,-109.04,37],[-94.47,36.5,-90.15,36.5,-90.06,36.3,-90.38,36,-89.73,36,-89.76,35.81,-90.13,35.44,-90.57,34.42,-90.95,34.14,-91.23,33.56,-91.06,33.43,-91.17,33,-94.04,33.02,-94.04,33.55,-94.48,33.64,-94.43,35.4,-94.62,36.5,-94.47,36.5],[-123.23,42.01,-120,42,-120,39,-114.63,35,-114.14,34.31,-114.54,33.93,-114.52,33.55,-114.73,33.41,-114.52,32.76,-117.13,32.54,-117.47,33.3,-118.41,33.74,-118.57,34.04,-120.65,34.58,-120.63,35.1,-121.9,36.32,-121.79,36.8,-121.93,36.98,-122.42,37.24,-122.41,38.15,-122.5,37.93,-122.94,38.03,-123.13,38.45,-123.74,38.96,-123.85,39.83,-124.36,40.26,-124.07,41.44,-124.21,42,-123.23,42.01],[-107.92,41,-102.05,41,-102.04,36.99,-109.04,37,-109.05,41,-107.92,41],[-73.05,42.04,-71.8,42.02,-71.8,41.41,-73.66,40.99,-73.73,41.1,-73.48,41.21,-73.49,42.05,-73.05,42.04],[-75.41,39.8,-75.59,39.46,-75.09,38.8,-75.05,38.45,-75.69,38.46,-75.79,39.72,-75.41,39.8],[-77.04,38.99,-76.91,38.9,-77.04,38.79,-77.04,38.99],[-85.5,31,-85,31,-84.87,30.71,-82.22,30.57,-82.17,30.36,-81.95,30.83,-81.44,30.71,-81.26,29.79,-80.52,28.46,-80.57,28.09,-80.03,26.8,-80.15,25.74,-80.5,25.2,-81.08,25.12,-81.35,25.82,-81.68,25.84,-82.06,26.88,-82.25,26.76,-82.69,27.44,-82.39,27.84,-82.72,27.69,-82.85,27.89,-82.64,28.89,-83.64,29.89,-84.02,30.1,-85.31,29.7,-85.4,29.94,-86.3,30.36,-87.52,30.28,-87.37,30.43,-87.6,31,-85.5,31],[-83.11,35,-83.34,34.68,-82.9,34.49,-82.56,33.94,-81.49,33.01,-81.12,32.12,-80.89,32.03,-81.4,31.13,-81.44,30.71,-81.95,30.83,-82.05,30.36,-82.22,30.57,-84.87,30.71,-85.11,31.28,-85.14,31.84,-84.89,32.26,-85.18,32.86,-85.61,34.98,-83.11,35],[-116.05,49,-116.05,47.98,-115.72,47.7,-115.72,47.42,-114.61,46.64,-114.32,46.65,-114.55,45.56,-113.81,45.6,-113.46,44.87,-113.13,44.77,-112.89,44.39,-111.62,44.55,-111.39,44.76,-111.05,44.48,-111.05,42,-117.03,42,-116.9,44.16,-117.24,44.39,-116.46,45.62,-117.06,46.34,-117.03,49,-116.05,49],[-90.64,42.51,-87.8,42.49,-87.52,41.71,-87.64,39.17,-87.5,38.78,-87.95,38.28,-88.07,37.48,-88.48,37.39,-88.55,37.07,-89.03,37.21,-89.29,36.99,-89.52,37.28,-89.52,37.69,-90.36,38.22,-90.11,38.85,-90.66,38.93,-90.73,39.26,-91.37,39.73,-91.49,40.03,-91.4,40.56,-90.96,40.92,-91.05,41.41,-90.34,41.59,-90.18,41.81,-90.17,42.13,-90.64,42.51],[-85.99,41.76,-84.81,41.76,-84.81,38.79,-85.43,38.73,-85.42,38.53,-86.04,37.96,-86.3,38.17,-86.5,37.93,-86.8,37.99,-87.13,37.79,-87.6,37.98,-88.03,37.8,-87.95,38.28,-87.5,38.78,-87.64,39.17,-87.52,41.71,-85.99,41.76],[-91.37,43.5,-91.06,43.25,-91.07,42.75,-90.71,42.64,-90.14,42,-90.34,41.59,-91.05,41.41,-90.96,40.92,-91.42,40.38,-91.73,40.62,-95.77,40.59,-96.13,41.97,-96.63,42.71,-96.43,43.12,-96.58,43.48,-96.45,43.5,-91.37,43.5],[-101.91,40,-95.31,40,-94.88,39.83,-95.11,39.54,-94.61,39.16,-94.62,37,-102.04,36.99,-102.05,40,-101.91,40],[-83.9,38.77,-83.68,38.63,-82.89,38.76,-82.59,38.42,-82.5,37.93,-81.97,37.54,-83.14,36.74,-83.69,36.58,-88.07,36.68,-88.05,36.5,-89.42,36.5,-89.22,36.58,-89.03,37.21,-88.55,37.07,-88.48,37.39,-88.07,37.48,-88.16,37.66,-87.93,37.89,-87.6,37.98,-87.13,37.79,-86.8,37.99,-86.5,37.93,-86.3,38.17,-86.04,37.96,-85.42,38.53,-85.43,38.73,-84.81,38.79,-84.82,39.1,-84.43,39.1,-84.22,38.81,-83.9,38.77],[-93.61,33.02,-91.17,33,-90.99,32.22,-91.5,31.64,-91.64,31,-89.75,31,-89.85,30.67,-89.52,30.18,-89.84,29.95,-89.6,29.88,-89.5,30.04,-89.29,29.88,-89.42,29.7,-89.65,29.75,-89.7,29.51,-89,29.18,-89.34,29.04,-89.85,29.31,-89.85,29.48,-90.1,29.15,-90.56,29.28,-90.8,29.09,-91.89,29.84,-92.31,29.54,-93.23,29.78,-93.84,29.69,-93.53,30.94,-94.04,31.99,-94.04,33.02,-93.61,33.02],[-70.7,43.06,-70.97,43.34,-71.08,45.3,-70.39,45.74,-70,46.69,-69.23,47.46,-68.9,47.18,-68.23,47.36,-67.95,47.2,-67.79,47.07,-67.8,45.68,-67.46,45.6,-67.49,45.28,-67.16,45.16,-66.98,44.8,-68.05,44.33,-68.22,44.49,-68.17,44.33,-68.4,44.25,-68.98,44.43,-69.07,44.04,-69.83,43.72,-70.03,43.85,-70.7,43.06],[-79.48,39.72,-75.79,39.72,-75.69,38.46,-75.05,38.45,-75.24,38.03,-75.89,37.91,-75.85,38.21,-76.26,38.32,-76.28,39.15,-75.97,39.56,-76.37,39.31,-76.56,38.77,-76.36,38.06,-77.02,38.45,-77.21,38.36,-77.28,38.48,-76.91,38.9,-77.46,39.08,-77.83,39.6,-78.77,39.59,-79.49,39.21,-79.48,39.72],[-70.92,42.89,-70.78,42.7,-70.99,42.27,-70.77,42.25,-70.54,41.81,-69.94,41.81,-70.01,41.67,-71.12,41.5,-71.38,42.02,-73.51,42.09,-73.27,42.75,-71.3,42.7,-70.92,42.89],[-83.45,41.73,-86.82,41.76,-86.36,42.25,-86.21,42.72,-86.53,43.59,-86.25,44.69,-85.61,45.13,-85.52,44.75,-85.39,45.24,-85.03,45.36,-85.12,45.58,-84.94,45.76,-84.22,45.64,-83.32,45.14,-83.45,45.03,-83.27,44.71,-83.33,44.34,-83.83,43.99,-83.91,43.67,-83.67,43.59,-82.92,44.07,-82.64,43.85,-82.41,42.98,-82.52,42.61,-82.8,42.65,-83.45,41.73],[-87.59,45.1,-87.74,45.2,-87.65,45.34,-87.89,45.36,-87.78,45.68,-88.1,45.92,-90.12,46.34,-90.42,46.57,-89,47,-88.18,47.46,-87.96,47.38,-88.44,46.97,-88.44,46.79,-87.9,46.91,-87.39,46.54,-86.7,46.44,-86.16,46.67,-85.06,46.76,-85.03,46.48,-84.13,46.53,-83.99,46.03,-83.48,45.99,-84.66,46.05,-84.7,45.85,-85.5,46.1,-86.66,45.7,-86.78,45.86,-87.17,45.66,-87.59,45.1],[-88.81,47.98,-89.19,47.83,-88.55,48.17,-88.81,47.98],[-92.01,46.71,-92.29,46.67,-92.29,46.08,-92.87,45.72,-92.64,45.44,-92.81,44.75,-91.43,43.99,-91.22,43.5,-96.45,43.5,-96.45,45.3,-96.86,45.6,-96.58,45.82,-96.6,46.33,-96.8,46.66,-97.23,49,-95.15,49,-95.15,49.38,-94.96,49.37,-94.59,48.72,-93.79,48.52,-92.98,48.62,-92.37,48.22,-92.05,48.36,-91.57,48.04,-90.84,48.24,-90.75,48.09,-89.62,48.01,-90.74,47.63,-92.01,46.71],[-88.47,35,-88.1,34.89,-88.47,31.9,-88.39,30.37,-89.52,30.18,-89.85,30.67,-89.75,31,-91.64,31,-91.5,31.64,-90.99,32.22,-91.15,32.64,-91.06,33.43,-91.23,33.56,-90.31,35,-88.47,35],[-91.83,40.61,-91.42,40.38,-91.37,39.73,-90.73,39.26,-90.66,38.93,-90.11,38.85,-90.36,38.22,-89.52,37.69,-89.52,37.28,-89.13,36.98,-89.22,36.58,-89.54,36.5,-89.73,36,-90.38,36,-90.06,36.3,-90.15,36.5,-94.62,36.5,-94.61,39.16,-95.11,39.54,-94.88,39.83,-95.21,39.91,-95.77,40.59,-91.83,40.61],[-104.05,49,-104.04,45,-111.05,45,-111.05,44.48,-111.39,44.76,-111.62,44.55,-112.89,44.39,-113.13,44.77,-113.46,44.87,-113.81,45.6,-114.55,45.56,-114.32,46.65,-114.61,46.64,-115.72,47.42,-115.72,47.7,-116.05,47.98,-116.05,49,-104.05,49],[-103.32,43,-98.5,43,-97.95,42.77,-97.22,42.84,-96.69,42.66,-96.13,41.97,-95.88,40.72,-95.31,40,-102.05,40,-102.05,41,-104.05,41,-104.05,43,-103.32,43],[-117.03,42,-114.04,42,-114.05,36.2,-114.15,36.03,-114.74,36.1,-114.63,35,-120,39,-120,42,-117.03,42],[-71.08,45.3,-70.97,43.34,-70.7,43.06,-71.3,42.7,-72.46,42.73,-72.38,43.57,-72.03,44.32,-71.54,44.59,-71.63,44.75,-71.36,45.27,-71.08,45.3],[-74.24,41.14,-73.9,41,-74.27,40.49,-74,40.41,-74.1,39.76,-74.8,38.99,-75.56,39.63,-74.77,40.22,-75.2,40.58,-75.13,40.97,-74.7,41.36,-74.24,41.14],[-107.42,37,-103,37,-103.07,32,-106.62,32,-106.53,31.79,-108.21,31.79,-108.21,31.33,-109.05,31.33,-109.04,37,-107.42,37],[-73.34,45.01,-73.44,44.04,-73.25,43.52,-73.27,42.75,-73.51,42.09,-73.48,41.21,-73.73,41.1,-73.23,40.91,-72.28,41.16,-72.1,40.99,-73.94,40.54,-73.9,41,-74.89,41.44,-75.36,42,-79.76,42,-79.76,42.27,-78.85,42.78,-79.07,43.26,-76.7,43.34,-76.24,43.53,-76.14,43.96,-76.31,44.2,-75.28,44.85,-74.83,45.02,-73.34,45.01],[-80.98,36.56,-75.87,36.55,-75.75,36.15,-76.67,35.94,-75.78,35.94,-75.72,35.7,-76.15,35.32,-76.48,35.31,-76.54,35.14,-76.28,34.94,-76.49,34.66,-77.21,34.61,-77.83,34.16,-77.97,33.85,-78.54,33.85,-79.68,34.8,-80.8,34.82,-81.04,35.15,-84.32,34.99,-84.29,35.23,-83.77,35.56,-82.99,35.77,-82.64,36.06,-82.04,36.12,-81.68,36.59,-80.98,36.56],[-97.23,49,-96.56,45.93,-104.05,45.94,-104.05,49,-97.23,49],[-80.52,41.98,-80.52,40.64,-80.67,40.58,-80.83,39.71,-81.69,39.27,-81.89,38.87,-82.04,39.03,-82.33,38.45,-82.59,38.42,-82.89,38.76,-83.68,38.63,-84.22,38.81,-84.43,39.1,-84.82,39.1,-84.81,41.69,-83.45,41.73,-82.48,41.38,-80.52,41.98],[-100.09,37,-94.62,37,-94.43,35.4,-94.48,33.64,-95.22,33.96,-96.35,33.69,-96.92,33.96,-97.17,33.74,-97.69,33.98,-97.87,33.85,-98.17,34.11,-99.19,34.21,-99.26,34.4,-99.7,34.38,-100,34.56,-100,36.5,-103,36.5,-103,37,-100.09,37],[-123.21,46.17,-122.9,46.08,-122.76,45.66,-122.25,45.55,-118.99,46,-116.92,45.99,-116.55,45.75,-116.46,45.62,-117.24,44.39,-116.9,44.16,-117.03,42,-124.21,42,-124.55,42.84,-124.17,43.81,-123.99,45.94,-123.55,46.26,-123.21,46.17],[-79.76,42.25,-79.76,42,-75.36,42,-74.7,41.36,-75.21,40.69,-74.77,40.22,-75.15,39.89,-75.79,39.72,-80.52,39.72,-80.52,41.98,-79.76,42.25],[-71.2,41.68,-71.12,41.5,-71.32,41.47,-71.2,41.68],[-71.53,42.02,-71.22,41.71,-71.48,41.37,-71.86,41.32,-71.8,42.01,-71.53,42.02],[-82.76,35.07,-81.04,35.15,-80.8,34.82,-79.68,34.8,-78.54,33.85,-78.94,33.64,-79.36,33.01,-79.58,33.01,-80.89,32.03,-81.12,32.12,-81.49,33.01,-82.56,33.94,-82.9,34.49,-83.34,34.68,-83.11,35,-82.76,35.07],[-104.05,45.94,-96.56,45.93,-96.86,45.6,-96.45,45.3,-96.45,43.5,-96.58,43.48,-96.43,43.12,-96.63,42.71,-96.45,42.49,-97.22,42.84,-97.95,42.77,-98.5,43,-104.05,43,-104.05,45.94],[-88.05,36.5,-88.07,36.68,-81.68,36.59,-82.04,36.12,-82.64,36.06,-82.99,35.77,-83.77,35.56,-84.29,35.23,-84.32,34.99,-90.31,35,-89.54,36.5,-88.05,36.5],[-101.81,36.5,-100,36.5,-100,34.56,-99.7,34.38,-99.26,34.4,-99.19,34.21,-98.17,34.11,-97.87,33.85,-97.69,33.98,-97.17,33.74,-96.92,33.96,-96.35,33.69,-95.22,33.96,-94.04,33.55,-94.04,31.99,-93.53,30.94,-93.84,29.69,-94.52,29.55,-94.74,29.79,-95.02,29.56,-94.9,29.31,-95.38,28.87,-95.99,28.6,-96.66,28.7,-96.4,28.44,-96.77,28.41,-97.54,27.23,-97.22,25.99,-97.52,25.89,-98.2,26.06,-99.17,26.54,-99.48,27.48,-100.3,28.28,-100.67,29.1,-101.41,29.75,-102.34,29.87,-103.28,28.98,-104.51,29.64,-104.9,30.57,-106.64,31.9,-103.07,32,-103.04,36.5,-101.81,36.5],[-112.16,42,-111.05,42,-111.05,41,-109.05,41,-109.04,37,-114.05,37,-114.04,42,-112.16,42],[-71.5,45.01,-71.54,44.59,-72.03,44.32,-72.38,43.57,-72.46,42.73,-73.27,42.75,-73.25,43.52,-73.44,44.04,-73.34,45.01,-71.5,45.01],[-75.4,38.01,-75.24,38.03,-75.97,37.12,-75.94,37.56,-75.67,37.95,-75.4,38.01],[-78.35,39.46,-77.83,39.13,-77.57,39.31,-77.12,38.93,-77.28,38.34,-77.01,38.37,-76.24,37.89,-76.4,37.16,-76.27,37.08,-76.67,37.07,-75.99,36.92,-75.87,36.55,-83.67,36.6,-83.14,36.74,-81.97,37.54,-81.68,37.2,-80.3,37.51,-79.65,38.59,-79.31,38.41,-79,38.85,-78.87,38.76,-78.4,39.17,-78.35,39.46],[-117.03,49,-117.06,46.34,-116.92,45.99,-118.99,46,-121.18,45.6,-122.76,45.66,-122.9,46.08,-124.07,46.33,-123.9,46.54,-124.71,48.18,-124.6,48.38,-122.8,48.09,-122.52,47.88,-122.42,47.32,-122.23,48.03,-122.76,49,-117.03,49],[-122.72,48.31,-122.59,48.35,-122.61,48.15,-122.72,48.31],[-123.03,48.58,-122.92,48.72,-122.81,48.42,-123.03,48.58],[-80.52,40.64,-80.52,39.72,-79.48,39.72,-79.49,39.21,-78.17,39.69,-77.83,39.6,-77.72,39.32,-77.83,39.13,-78.35,39.46,-78.4,39.17,-78.87,38.76,-79,38.85,-79.31,38.41,-79.65,38.59,-80.3,37.51,-81.68,37.2,-82.5,37.93,-82.59,38.42,-82.33,38.45,-82.04,39.03,-81.89,38.87,-81.69,39.27,-80.83,39.71,-80.67,40.58,-80.52,40.64],[-90.42,46.57,-90.12,46.34,-88.1,45.92,-87.78,45.68,-87.89,45.36,-87.65,45.34,-87.74,45.2,-87.59,45.1,-88.04,44.56,-87.03,45.22,-87.74,43.88,-87.91,43.25,-87.8,42.49,-90.64,42.51,-91.07,42.75,-91.06,43.25,-91.43,43.99,-92.81,44.75,-92.64,45.44,-92.87,45.72,-92.29,46.08,-92.29,46.67,-90.84,46.96,-90.89,46.75,-90.42,46.57],[-109.08,45,-104.06,45,-104.05,41,-111.05,41,-111.05,45,-109.08,45]]';
	function bpUsRings() { if (!BP_US_RINGS) { try { BP_US_RINGS = JSON.parse(BP_US_RINGS_STR); } catch (e) { BP_US_RINGS = []; } } return BP_US_RINGS; }
	// Spherical Albers equal-area conic, lower-48 (parallels 29.5/45.5, meridian -96). y is returned
	// already flipped so north is up. Constants precomputed once.
	var ALB_N, ALB_C, ALB_RHO0;
	(function () { var rad = Math.PI/180, p1 = 29.5*rad, p2 = 45.5*rad, la0 = 37.5*rad;
		ALB_N = (Math.sin(p1) + Math.sin(p2)) / 2;
		ALB_C = Math.cos(p1)*Math.cos(p1) + 2*ALB_N*Math.sin(p1);
		ALB_RHO0 = Math.sqrt(ALB_C - 2*ALB_N*Math.sin(la0)) / ALB_N; })();
	function bpAlbers(lng, lat) {
		var rad = Math.PI/180, th = ALB_N * (lng*rad + 96*rad), rho = Math.sqrt(ALB_C - 2*ALB_N*Math.sin(lat*rad)) / ALB_N;
		return [rho*Math.sin(th), rho*Math.cos(th) - ALB_RHO0];
	}
	// Bubbles sized by engaged sessions; picks the snapshot window nearest the active range.
	function bpHaversine(lat1, lng1, lat2, lng2) {
		var R = 3958.8, rad = Math.PI/180, dla = (lat2-lat1)*rad, dln = (lng2-lng1)*rad;
		var a = Math.sin(dla/2)*Math.sin(dla/2) + Math.cos(lat1*rad)*Math.cos(lat2*rad)*Math.sin(dln/2)*Math.sin(dln/2);
		return 2 * R * Math.asin(Math.sqrt(a));
	}
	function bpDiamond(cx, cy, r) { return 'M' + cx + ',' + (cy-r) + 'L' + (cx+r) + ',' + cy + 'L' + cx + ',' + (cy+r) + 'L' + (cx-r) + ',' + cy + 'Z'; }
	// Shared map drawer. opts: { proj, W, H, pts, maxV, home, ariaLabel, noteHtml, maxLabels }.
	// The US basemap rings are always drawn with opts.proj and clipped by the viewBox, so a zoomed
	// projection just shows the relevant states. Bubbles/labels/ home marker share that same proj.
	function bpDrawMap(container, opts) {
		container.innerHTML = '';
		var proj = opts.proj, W = opts.W, H = opts.H;
		var svg = el('svg', { 'class': 'bp-an-map-svg', viewBox: '0 0 ' + W + ' ' + H, preserveAspectRatio: 'xMidYMid meet', role: 'img', 'aria-label': opts.ariaLabel });
		var gBase = el('g', { 'class': 'bp-an-map-base' });
		bpUsRings().forEach(function (rr) {
			var d = '', j, q;
			for (j = 0; j < rr.length; j += 2) { q = proj(rr[j], rr[j+1]); d += (j ? 'L' : 'M') + (Math.round(q[0]*10)/10) + ',' + (Math.round(q[1]*10)/10); }
			gBase.appendChild(el('path', { 'class': 'bp-an-map-state', d: d + 'Z' }));
		});
		svg.appendChild(gBase);
		if (opts.circle) {
			var cc = proj(opts.circle.lng, opts.circle.lat), cedge = proj(opts.circle.lng, opts.circle.lat + opts.circle.radius/69), cr = Math.abs(cedge[1] - cc[1]);
			// Clip the basemap to the service-area disc so everything outside it is the white card
			// background (matching the national map), not a solid fill of the state we've zoomed into.
			var clipId = 'bp-map-clip-' + (CLIP_SEQ++), defs = el('defs', {}), clip = el('clipPath', { id: clipId });
			clip.appendChild(el('circle', { cx: cc[0], cy: cc[1], r: cr }));
			defs.appendChild(clip); svg.insertBefore(defs, svg.firstChild);
			gBase.setAttribute('clip-path', 'url(#' + clipId + ')');
			// Edge ring only — the grey disc is the clipped land itself, so it matches the national map's
			// land colour. fill:none inline is a fallback; .bp-an-map-radius CSS overrides it when present.
			svg.appendChild(el('circle', { 'class': 'bp-an-map-radius', cx: cc[0], cy: cc[1], r: cr,
				fill: 'none', stroke: '#c9ccc6', 'stroke-width': '1.5' }));
		}
		var RMIN = 3, RMAX = 34, maxV = opts.maxV;
		function radius(v) { return maxV <= 0 ? RMIN : RMIN + (RMAX - RMIN) * Math.sqrt(v / maxV); }
		var sorted = opts.pts.slice().sort(function (a, b) { return b.v - a.v; }); // big first, small on top
		var gB = el('g', { 'class': 'bp-an-map-bubbles' });
		sorted.forEach(function (p) {
			var xy = proj(p.lng, p.lat), c = el('circle', { 'class': 'bp-an-map-bubble', cx: xy[0], cy: xy[1], r: radius(p.v) });
			var t = document.createElementNS(SVGNS, 'title'); t.textContent = p.l + ': ' + fmtInt(p.v) + ' sessions'; c.appendChild(t);
			gB.appendChild(c);
		});
		svg.appendChild(gB);
		// Greedy, width-aware label placement: skip any label that would overlap one already placed
		// (each entry stores [x, y, halfWidth]) so dense metros don't stack into an unreadable blob.
		// Collisions compare each label's ACTUAL drawn box (x, its baseline y, half-width) — a big
		// bubble's label sits far above its center, so comparing centers would under-detect overlaps.
		var gL = el('g', { 'class': 'bp-an-map-labels' }), placed = [];
		function halfW(str) { return str.length * 3.4 + 5; }
		if (opts.home) { var hlx = proj(opts.home.lng, opts.home.lat); placed.push([hlx[0], hlx[1] + 20, halfW(opts.home.label)]); }
		for (var li = 0; li < sorted.length && placed.length < (opts.maxLabels || 7); li++) {
			var p = sorted[li], lxy = proj(p.lng, p.lat), txt = ('' + p.l).split(',')[0], hw = halfW(txt);
			var ly = lxy[1] - radius(p.v) - 4, clash = false;
			for (var pi = 0; pi < placed.length; pi++) {
				if (Math.abs(placed[pi][0] - lxy[0]) < (placed[pi][2] + hw) && Math.abs(placed[pi][1] - ly) < 13) { clash = true; break; }
			}
			if (clash) continue;
			placed.push([lxy[0], ly, hw]);
			var tx = el('text', { 'class': 'bp-an-map-label', x: lxy[0], y: ly, 'text-anchor': 'middle' });
			tx.textContent = txt; gL.appendChild(tx);
		}
		if (opts.home) {
			var hxy = proj(opts.home.lng, opts.home.lat);
			var star = el('path', { 'class': 'bp-an-map-home', d: bpDiamond(hxy[0], hxy[1], 7) });
			var ht = document.createElementNS(SVGNS, 'title'); ht.textContent = opts.home.label + ' (home)'; star.appendChild(ht); gL.appendChild(star);
			var hl = el('text', { 'class': 'bp-an-map-homelabel', x: hxy[0], y: hxy[1] + 20, 'text-anchor': 'middle' }); hl.textContent = opts.home.label; gL.appendChild(hl);
		}
		svg.appendChild(gL);
		container.appendChild(svg);
		if (opts.noteHtml) { var note = document.createElement('div'); note.className = 'bp-an-map-note'; note.innerHTML = opts.noteHtml; container.appendChild(note); }
	}
	// Cities to plot for the active range. With a monthly time series we sum the months overlapping the
	// window (so 30d/60d/1y all differ and track the rest of the page); otherwise fall back to the
	// pre-timeline snapshot buckets. Returns { pts:[{l,lat,lng,v}], uncoded, span:'<month label>' }.
	function bpLocationPts(range) {
		var tl = payload.locationsTimeline;
		if (tl && tl.cities && tl.cities.length) {
			var frames = tl.frames, inc = [], lo = range ? range.lo : null, hi = range ? (range.hi || new Date()) : null, i;
			for (i = 0; i < frames.length; i++) {
				var ym = '' + frames[i], y = +ym.slice(0, 4), mo = +ym.slice(4, 6) - 1;
				var mStart = new Date(y, mo, 1), mEnd = new Date(y, mo + 1, 0, 23, 59, 59);
				if ((!lo || mEnd >= lo) && (!hi || mStart <= hi)) inc.push(i);
			}
			var pts = [];
			tl.cities.forEach(function (c) {
				var v = 0, k; for (k = 0; k < inc.length; k++) v += c.v[inc[k]] || 0;
				if (v > 0) pts.push({ l: c.l, lat: c.lat, lng: c.lng, v: v });
			});
			var span = inc.length ? (tl.labels[inc[0]] === tl.labels[inc[inc.length - 1]] ? tl.labels[inc[0]] : tl.labels[inc[0]] + ' \u2013 ' + tl.labels[inc[inc.length - 1]]) : 'no data in range';
			return { pts: pts, uncoded: 0, span: span };
		}
		var loc = payload.locations, w = techWindow(), bucket = loc && (loc[w] || loc[365]);
		return { pts: (bucket && bucket.pts) || [], uncoded: (bucket && bucket.uncoded) || 0, span: w + '-day snapshot' };
	}
	// National map — all mapped cities across the lower-48.
	function renderLocations(container) {
		var src = bpLocationPts(activeRange()), pts = src.pts, uncoded = src.uncoded;
		if (!pts.length) { container.innerHTML = '<p class="bp-an-empty">No mapped locations yet — city coordinates fill in as the nightly analytics run geocodes them.</p>'; return; }
		var rings = bpUsRings();
		if (!rings.length) { container.innerHTML = '<p class="bp-an-empty">Map unavailable.</p>'; return; }
		var W = 960, H = 560, PAD = 14, minx = 1e9, maxx = -1e9, miny = 1e9, maxy = -1e9, ri, i, xy, r;
		for (ri = 0; ri < rings.length; ri++) { r = rings[ri]; for (i = 0; i < r.length; i += 2) { xy = bpAlbers(r[i], r[i+1]);
			if (xy[0] < minx) minx = xy[0]; if (xy[0] > maxx) maxx = xy[0]; if (xy[1] < miny) miny = xy[1]; if (xy[1] > maxy) maxy = xy[1]; } }
		var s = Math.min((W - 2*PAD) / (maxx - minx), (H - 2*PAD) / (maxy - miny)), ox = (W - s*(maxx + minx)) / 2, oy = (H - s*(maxy + miny)) / 2;
		var proj = function (lng, lat) { var p = bpAlbers(lng, lat); return [s*p[0] + ox, s*p[1] + oy]; };
		var maxV = 0, total = 0; pts.forEach(function (p) { if (p.v > maxV) maxV = p.v; total += p.v; });
		var note = fmtInt(total) + ' engaged sessions across ' + pts.length + ' cit' + (pts.length === 1 ? 'y' : 'ies')
			+ (uncoded > 0 ? ' · <span class="bp-an-map-uncoded">' + fmtInt(uncoded) + ' not mapped</span>' : '')
			+ ' · <span class="bp-an-map-window">' + src.span + '</span>';
		bpDrawMap(container, { proj: proj, W: W, H: H, pts: pts, maxV: maxV, home: null, ariaLabel: 'Visitor locations across the United States', noteHtml: note });
	}
	// Data-driven service-area extent: centre + radius that tightly enclose the LOCAL cluster of
	// cities around home, so the zoom re-centres (e.g. shifts toward the metro) and zooms as close as
	// possible without cutting off relevant cities — instead of a fixed radius wasting empty quadrants.
	// Uses all-time city totals so the viewport is stable as the range changes (only bubbles resize).
	// Walks cities outward from home and stops at the first big distance gap (that's the jump to the
	// next metro / cross-country noise). Returns { lat, lng, radius } or null (→ fixed fallback).
	function bpZoomExtent(home) {
		var tl = payload.locationsTimeline;
		if (!tl || !tl.cities || !tl.cities.length) return null;
		var CAP = 150, GROWTH = 2.2, MINGAP = 30, MINR = 12, PAD = 0.12;
		var cand = [], localTotal = 0;
		tl.cities.forEach(function (c) {
			var d = bpHaversine(home.lat, home.lng, c.lat, c.lng);
			if (d > CAP) return;
			var tot = 0, k; for (k = 0; k < c.v.length; k++) tot += c.v[k] || 0;
			if (tot > 0) { cand.push({ lat: c.lat, lng: c.lng, tot: tot, d: d }); localTotal += tot; }
		});
		if (!cand.length) return null;
		var floor = Math.max(2, Math.ceil(0.01 * localTotal));
		var sig = cand.filter(function (c) { return c.tot >= floor; });
		if (!sig.length) sig = cand;
		sig.sort(function (a, b) { return a.d - b.d; });
		var kept = [sig[0]], i;
		for (i = 1; i < sig.length; i++) {
			var prevD = kept[kept.length - 1].d, allow = Math.max(prevD * GROWTH, prevD + MINGAP);
			if (sig[i].d <= allow) kept.push(sig[i]); else break;
		}
		var minLat = home.lat, maxLat = home.lat, minLng = home.lng, maxLng = home.lng;
		kept.forEach(function (c) {
			if (c.lat < minLat) minLat = c.lat; if (c.lat > maxLat) maxLat = c.lat;
			if (c.lng < minLng) minLng = c.lng; if (c.lng > maxLng) maxLng = c.lng;
		});
		var center = { lat: (minLat + maxLat) / 2, lng: (minLng + maxLng) / 2 }, radiusMi = 0;
		kept.concat([{ lat: home.lat, lng: home.lng }]).forEach(function (c) {
			var d = bpHaversine(center.lat, center.lng, c.lat, c.lng); if (d > radiusMi) radiusMi = d;
		});
		return { lat: center.lat, lng: center.lng, radius: Math.max(radiusMi * (1 + PAD), MINR) };
	}
	// Regional map — auto-fit to the local service area around the client's home town (payload.home).
	function renderLocationsZoom(container) {
		var home = payload.home;
		if (!home) { container.innerHTML = ''; return; }
		var src = bpLocationPts(activeRange()), allPts = src.pts;
		if (!allPts.length) { container.innerHTML = '<p class="bp-an-empty">No mapped locations yet — city coordinates fill in as the nightly analytics run geocodes them.</p>'; return; }
		var rings = bpUsRings();
		if (!rings.length) { container.innerHTML = '<p class="bp-an-empty">Map unavailable.</p>'; return; }
		var ext = bpZoomExtent(home) || { lat: home.lat, lng: home.lng, radius: home.radius || 50 };
		var R = Math.round(ext.radius);
		var W = 640, H = 560, PAD = 14;
		var dLat = ext.radius/69, dLng = ext.radius / (69 * Math.cos(ext.lat * Math.PI/180));
		var corners = [[ext.lng-dLng, ext.lat-dLat], [ext.lng+dLng, ext.lat-dLat], [ext.lng+dLng, ext.lat+dLat], [ext.lng-dLng, ext.lat+dLat]];
		var minx = 1e9, maxx = -1e9, miny = 1e9, maxy = -1e9, k, p;
		for (k = 0; k < corners.length; k++) { p = bpAlbers(corners[k][0], corners[k][1]);
			if (p[0] < minx) minx = p[0]; if (p[0] > maxx) maxx = p[0]; if (p[1] < miny) miny = p[1]; if (p[1] > maxy) maxy = p[1]; }
		var s = Math.min((W - 2*PAD) / (maxx - minx), (H - 2*PAD) / (maxy - miny)), ox = (W - s*(maxx + minx)) / 2, oy = (H - s*(maxy + miny)) / 2;
		var proj = function (lng, lat) { var q = bpAlbers(lng, lat); return [s*q[0] + ox, s*q[1] + oy]; };
		var localPts = [], localTotal = 0, allTotal = 0, maxV = 0;
		allPts.forEach(function (pt) {
			if (pt.v > maxV) maxV = pt.v; allTotal += pt.v;
			if (bpHaversine(ext.lat, ext.lng, pt.lat, pt.lng) <= ext.radius) { localPts.push(pt); localTotal += pt.v; }
		});
		var pct = allTotal > 0 ? Math.round(localTotal / allTotal * 100) : 0;
		var title = container.closest('.bp-an-mapcol') && container.closest('.bp-an-mapcol').querySelector('.bp-an-maptitle');
		if (title) title.textContent = 'Within ~' + R + ' mi of ' + home.label;
		var note = ( localPts.length
			? fmtInt(localTotal) + ' engaged sessions in the ~' + R + ' mi service area · <b>' + pct + '%</b> of mapped US traffic'
			: 'No mapped sessions near ' + home.label + ' in this range.' )
			+ ' · <span class="bp-an-map-window">' + src.span + '</span>';
		bpDrawMap(container, { proj: proj, W: W, H: H, pts: localPts, maxV: maxV,
			circle: { lat: ext.lat, lng: ext.lng, radius: ext.radius }, home: { lat: home.lat, lng: home.lng, label: home.label },
			ariaLabel: 'Visitor locations in the service area around ' + home.label, noteHtml: note, maxLabels: 10 });
	}

	function renderAll() {
		renderTiles();
		Array.prototype.forEach.call(behaviorPies, renderBehaviorPie); // set pie visibility first (affects chart width)
		Array.prototype.forEach.call(channelCharts, renderChannels);
		Array.prototype.forEach.call(behaviorCharts, renderBehavior);
		Array.prototype.forEach.call(channelPies, renderChannelPie);
		Array.prototype.forEach.call(techPies, renderTechPie);
		Array.prototype.forEach.call(widthBars, renderWidthBars);
		Array.prototype.forEach.call(speedCharts, renderSpeed);
		Array.prototype.forEach.call(locationCharts, renderLocations);
		Array.prototype.forEach.call(locationZoomCharts, renderLocationsZoom);
		Array.prototype.forEach.call(pageTrendCharts, renderPageTrend);
		Array.prototype.forEach.call(pagesPies, renderPagesPie);
		Array.prototype.forEach.call(clarityCharts, renderClarity);
		Array.prototype.forEach.call(scrollCharts, renderScroll);
		Array.prototype.forEach.call(eventCharts, renderEvents);
	}

	function clearDates() { var s = document.getElementById('bp-an-start'), e = document.getElementById('bp-an-end'); if (s) s.value = ''; if (e) e.value = ''; }
	function setActive(sel, btn) { document.querySelectorAll(sel).forEach(function (x) { x.classList.remove('active'); }); btn.classList.add('active'); }
	function clearCaps(nodes) { Array.prototype.forEach.call(nodes, function (c) { c._yCap = null; }); }
	function clearAllCaps() { clearCaps(channelCharts); clearCaps(behaviorCharts); clearCaps(speedCharts); clearCaps(pageTrendCharts); clearCaps(clarityCharts); clearCaps(scrollCharts); clearCaps(eventCharts); }

	document.addEventListener('click', function (ev) {
		var gb = ev.target.closest('.bp-an-gbtn');
		if (gb && !gb.classList.contains('disabled')) { ev.preventDefault(); setActive('.bp-an-gbtn', gb); clearAllCaps(); renderAll(); return; }

		var rb = ev.target.closest('.bp-an-rbtn');
		if (rb) { ev.preventDefault(); clearDates(); setActive('.bp-an-rbtn', rb); clearAllCaps(); renderAll(); return; }

		var m = ev.target.closest('.bp-an-mbtn');
		if (m) {
			ev.preventDefault();
			var card = m.closest('.bp-an-card');
			card.querySelectorAll('.bp-an-mbtn').forEach(function (x) { x.classList.remove('active'); });
			m.classList.add('active');
			clearCaps(card.querySelectorAll('.bp-analytics-behavior'));
			card.querySelectorAll('.bp-analytics-pie').forEach(renderBehaviorPie);   // pie visibility first
			card.querySelectorAll('.bp-analytics-behavior').forEach(renderBehavior); // then chart at correct width
			return;
		}

		var pb = ev.target.closest('.bp-an-pbtn');
		if (pb) {
			ev.preventDefault();
			var pcard = pb.closest('.bp-an-card');
			pcard.querySelectorAll('.bp-an-pbtn').forEach(function (x) { x.classList.remove('active'); });
			pb.classList.add('active');
			clearCaps(pcard.querySelectorAll('.bp-analytics-pagetrend'));
			pcard.querySelectorAll('.bp-analytics-pagetrend').forEach(renderPageTrend);
			return;
		}

		var cb = ev.target.closest('.bp-an-cbtn');
		if (cb) {
			ev.preventDefault();
			var ccard = cb.closest('.bp-an-card');
			ccard.querySelectorAll('.bp-an-cbtn').forEach(function (x) { x.classList.remove('active'); });
			cb.classList.add('active');
			clearCaps(ccard.querySelectorAll('.bp-analytics-clarity'));
			ccard.querySelectorAll('.bp-analytics-clarity').forEach(renderClarity);
			return;
		}

		var eb = ev.target.closest('.bp-an-ebtn');
		if (eb) {
			ev.preventDefault();
			var ecard = eb.closest('.bp-an-card');
			ecard.querySelectorAll('.bp-an-ebtn').forEach(function (x) { x.classList.remove('active'); });
			eb.classList.add('active');
			clearCaps(ecard.querySelectorAll('.bp-analytics-events'));
			ecard.querySelectorAll('.bp-analytics-events').forEach(renderEvents);
			return;
		}

		var sb = ev.target.closest('.bp-an-sbtn');
		if (sb) {
			ev.preventDefault();
			var scard = sb.closest('.bp-an-card');
			scard.querySelectorAll('.bp-an-sbtn').forEach(function (x) { x.classList.remove('active'); });
			sb.classList.add('active');
			clearCaps(scard.querySelectorAll('.bp-analytics-speed'));
			scard.querySelectorAll('.bp-analytics-speed').forEach(renderSpeed);
			return;
		}
	});

	// Custom date entry overrides the range presets.
	document.addEventListener('change', function (ev) {
		if (ev.target.classList && ev.target.classList.contains('bp-an-date')) {
			document.querySelectorAll('.bp-an-rbtn').forEach(function (x) { x.classList.remove('active'); });
			clearAllCaps();
			renderAll();
		}
	});

	if ('ResizeObserver' in window) {
		// rAF-debounced: render() changes the element's height, which would otherwise
		// re-trigger the observer in a loop ("ResizeObserver loop" warning).
		var ro = new ResizeObserver(function (entries) {
			entries.forEach(function (en) {
				var t = en.target;
				if (t._bpPending) return;
				t._bpPending = true;
				requestAnimationFrame(function () {
					t._bpPending = false;
					if (t.classList.contains('bp-analytics-widthbars')) renderWidthBars(t);
					else if (t.classList.contains('bp-analytics-speed')) renderSpeed(t);
					else if (t.dataset.pie === 'pages') renderPagesPie(t);
					else if (t.dataset.pie === 'tech') renderTechPie(t);
					else if (t.dataset.pie === 'channel') renderChannelPie(t);
					else if (t.dataset.pie === 'behavior') renderBehaviorPie(t);
					else if (t.classList.contains('bp-analytics-pagetrend')) renderPageTrend(t);
					else if (t.classList.contains('bp-analytics-clarity')) renderClarity(t);
					else if (t.classList.contains('bp-analytics-scroll')) renderScroll(t);
					else if (t.classList.contains('bp-analytics-events')) renderEvents(t);
					else if (t.classList.contains('bp-analytics-locations-zoom')) renderLocationsZoom(t);
					else if (t.classList.contains('bp-analytics-locations')) renderLocations(t);
					else if (t.classList.contains('bp-analytics-behavior')) renderBehavior(t);
					else renderChannels(t);
				});
			});
		});
		[channelCharts, behaviorCharts, channelPies, behaviorPies, techPies, widthBars, speedCharts, locationCharts, locationZoomCharts, pageTrendCharts, pagesPies, clarityCharts, scrollCharts, eventCharts].forEach(function (list) {
			Array.prototype.forEach.call(list, function (c) { ro.observe(c); });
		});
		renderAll(); // deterministic initial paint — don't rely solely on ResizeObserver's first callback
	} else {
		renderAll();
		window.addEventListener('resize', renderAll);
	}
});
