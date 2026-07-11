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
	if (!channelCharts.length && !behaviorCharts.length) return;

	var payloadEl = document.getElementById('bp-an-payload');
	if (!payloadEl) return;
	var payload;
	try { payload = JSON.parse(payloadEl.textContent); } catch (e) { return; }

	var SVGNS  = 'http://www.w3.org/2000/svg';
	var MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
	var CLIP_SEQ = 0;

	var CH_SERIES = [
		{ key: 'Organic Search', cls: 'os',     emph: true  },
		{ key: 'GBP',            cls: 'gbp',    emph: true  },
		{ key: 'Paid',           cls: 'paid',   emph: false },
		{ key: 'Direct',         cls: 'direct', emph: false },
		{ key: 'Other',          cls: 'other',  emph: false }
	];

	// Behavior metrics: each = 1-2 lines derived from site totals. Field names beginning
	// with "_" are derived (see derive()); others read straight from the site object.
	var BEHAVIOR = {
		sessions_users: { fmt: 'int', series: [['Sessions','sessions','os'], ['Users','users','gbp']] },
		new_returning:  { fmt: 'int', pct: true, series: [['New','_new','os'], ['Returning','_ret','paid']] },
		engaged:        { fmt: 'int', pct: true, series: [['Engaged','_eng','gbp'], ['Non-engaged','_non','direct']] },
		pageviews:      { fmt: 'int', series: [['Pageviews','pageviews','os']], perUser: true },
		duration:       { fmt: 'dur', series: [['Engaged avg','_durEng','gbp'], ['Session avg','_durSess','paid']] }
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
		var slices = arr.map(function (x, i) { return { label: x.l, value: x.v, cls: (x.l === 'Other' ? 'cother' : 'c' + (i % 11)) }; });
		drawDonut(pieC, slices);
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
	var PIE_CH  = ['Organic Search','GBP','Paid','Direct','Other'];
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
		function yfmt(v) { return isDur ? (v >= 60 ? Math.round(v/60) + 'm' : Math.round(v) + 's') : fmtInt(v); }
		function vfmt(v) { return isDur ? fmtDur(v) : fmtInt(v); }

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

	function renderAll() {
		renderTiles();
		Array.prototype.forEach.call(behaviorPies, renderBehaviorPie); // set pie visibility first (affects chart width)
		Array.prototype.forEach.call(channelCharts, renderChannels);
		Array.prototype.forEach.call(behaviorCharts, renderBehavior);
		Array.prototype.forEach.call(channelPies, renderChannelPie);
		Array.prototype.forEach.call(techPies, renderTechPie);
	}

	function clearDates() { var s = document.getElementById('bp-an-start'), e = document.getElementById('bp-an-end'); if (s) s.value = ''; if (e) e.value = ''; }
	function setActive(sel, btn) { document.querySelectorAll(sel).forEach(function (x) { x.classList.remove('active'); }); btn.classList.add('active'); }
	function clearCaps(nodes) { Array.prototype.forEach.call(nodes, function (c) { c._yCap = null; }); }
	function clearAllCaps() { clearCaps(channelCharts); clearCaps(behaviorCharts); }

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
					if (t.dataset.pie === 'tech') renderTechPie(t);
					else if (t.dataset.pie === 'channel') renderChannelPie(t);
					else if (t.dataset.pie === 'behavior') renderBehaviorPie(t);
					else if (t.classList.contains('bp-analytics-behavior')) renderBehavior(t);
					else renderChannels(t);
				});
			});
		});
		[channelCharts, behaviorCharts, channelPies, behaviorPies, techPies].forEach(function (list) {
			Array.prototype.forEach.call(list, function (c) { ro.observe(c); });
		});
		renderTiles(); // tiles aren't observed by RO — render them at init
	} else {
		renderAll();
		window.addEventListener('resize', renderAll);
	}
});
