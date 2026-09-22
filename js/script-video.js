document.addEventListener("DOMContentLoaded", function () {
	"use strict";

	// Raw Script: Video
	//
	// Both video blocks live here, because both are the same trick:
	//
	//   [vid]         one video  — functions-grid.php
	//   [yt-channel]  a channel  — includes-video.php
	//
	// Neither shortcode ever prints an iframe. The PHP emits a thumbnail plus a
	// play button, and the real embed is built here at click time. That is the
	// whole performance story: a grid of twelve videos costs twelve images until
	// somebody actually wants to watch one, instead of twelve YouTube players.
	//
	// Enqueued on demand by whichever shortcode rendered (bp_enqueue_script), so
	// it is absent from pages with no video on them. [vid] is the only thing in
	// the framework that emits .video-player markup, so nothing else can be left
	// stranded by that.


	/* ------------------------------------------------------------------
	   Shared
	------------------------------------------------------------------ */

	// Global because the section-lock modals in script-pages.js call it on close.
	// Every iframe either block builds carries .video-player, so this one selector
	// covers both. Pass an element to spare it — that is how clicking a second
	// video pauses the first without pausing the one just started.
	window.stopAllVideos = function (except) {
		getObjects('iframe.video-player').forEach(function (frame) {
			// contentWindow is null on a frame detached from the document but not
			// yet collected — reading .postMessage off it throws.
			if (frame === except || !frame.contentWindow) return;
			frame.contentWindow.postMessage('{"event":"command","func":"pauseVideo","args":""}', '*');
		});
	};


	/* ------------------------------------------------------------------
	   [vid] — single video facade
	------------------------------------------------------------------ */

	// Handle displaying YouTube or Vimeo thumbnail, and then loading video once clicked
	function activateYouTubeVimeo(div) {
		var iframe = document.createElement('iframe');
		setAttributes(iframe, {
			'src': div.dataset.link,
			'modestbranding': '0',
			'controls': '0',
			'frameborder': '0',
			'allowfullscreen': '1',
			'allow': 'accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture',
			'class': 'video-player'
		});
		div.parentNode.replaceChild(iframe, div);
	}

	getObjects('.video-player').forEach(function (playerObj) {
		const thumbNode = document.createElement('img');
		const playButton = document.createElement('div');
		const div = document.createElement('div');

		div.dataset.id = playerObj.dataset.id;
		div.dataset.link = playerObj.dataset.link;
		thumbNode.src = playerObj.dataset.thumb;
		playButton.className = 'play';

		div.appendChild(thumbNode);
		div.appendChild(playButton);
		div.onclick = function () { activateYouTubeVimeo(this); };

		playerObj.appendChild(div);
	});

	getObjects('.block-video.video-player').forEach(function (videoBlock) {
		videoBlock.addEventListener('click', function () { stopAllVideos(); });
	});


	/* ------------------------------------------------------------------
	   [yt-channel] — whole channel

	   Everything is delegated off the block, not bound per tile, so the tiles
	   Load More appends behave without a second pass of wiring.
	------------------------------------------------------------------ */

	var blocks = getObjects('.block-yt-channel');
	if (!blocks.length) return;

	function buildFrame(link, title) {
		var frame = document.createElement('iframe');
		setAttributes(frame, {
			src             : link,
			title           : title || 'YouTube video player',
			frameborder     : '0',
			allowfullscreen : '1',
			allow           : 'accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture',
			'class'         : 'yt-iframe video-player'
		});
		return frame;
	}

	blocks.forEach(function (block) {
		var isFeature = block.classList.contains('yt-feature'),
			stage     = getObject('.yt-stage', block);

		// --- Play -------------------------------------------------------------
		block.addEventListener('click', function (e) {
			var button = e.target.closest('.yt-thumb');
			if (!button || !block.contains(button)) return;

			var link  = button.dataset.link,
				title = button.dataset.title,
				id    = button.dataset.id;
			if (!link) return;

			// Feature layout: a strip tile feeds the stage rather than turning into
			// its own player. The stage tile itself falls through and plays in place.
			if (isFeature && stage && !button.classList.contains('yt-stage-thumb')) {
				var existing = getObject('iframe', stage);

				if (existing) {
					existing.src = link;
					existing.title = title || 'YouTube video player';
					stopAllVideos(existing);
				} else {
					var stageButton = getObject('.yt-thumb', stage);
					var frame = buildFrame(link, title);
					stopAllVideos(frame);
					if (stageButton) stageButton.parentNode.replaceChild(frame, stageButton);
					else stage.appendChild(frame);
				}

				var heading = getObject('.yt-stage-title', block);
				if (heading && title) heading.textContent = title;

				getObjects('.yt-tile', block).forEach(function (tile) {
					tile.classList.toggle('is-current', tile.contains(button));
				});

				// Send focus to the stage so a keyboard user isn't stranded at the
				// bottom of the strip after the player jumps to the top.
				stage.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
				var moved = getObject('iframe', stage);
				if (moved) moved.focus();
				return;
			}

			// Grid, scroll, and the feature stage itself: the button becomes the player.
			var replacement = buildFrame(link, title);
			stopAllVideos(replacement);
			button.parentNode.replaceChild(replacement, button);
			replacement.focus();

			if (id && typeof gtag === 'function') {
				gtag('event', 'video_start', { video_provider: 'youtube', video_id: id, video_title: title || '' });
			}
		});

		// --- Load more --------------------------------------------------------
		//
		// Delegated like the play handler, because the server sends back a whole
		// replacement button carrying the next signed payload — a listener bound to
		// the original element would die the moment that swap happens.
		var wrap = getObject('.yt-more-wrap', block),
			row  = getObject('.yt-row', block);
		if (!wrap || !row || typeof bpVideo === 'undefined') return;

		wrap.addEventListener('click', function (e) {
			var more = e.target.closest('.yt-more');
			if (!more || more.disabled) return;

			var label = more.textContent;
			more.disabled = true;
			more.textContent = 'Loading…';

			var params = new URLSearchParams();
			params.append('action', 'bp_yt_more');
			params.append('payload', more.dataset.payload);
			params.append('sig', more.dataset.sig);

			fetch(bpVideo.ajaxUrl, {
				method      : 'POST',
				credentials : 'same-origin',
				headers     : { 'Content-Type': 'application/x-www-form-urlencoded' },
				body        : params.toString()
			}).then(function (r) { return r.json(); }).then(function (res) {
				if (!res || !res.success || !res.data.html) {
					wrap.remove();
					return;
				}

				// Parse into a template first: innerHTML += on the row would blow away
				// and rebuild every tile already there, including any iframe the
				// visitor is part way through watching.
				var holder = document.createElement('template');
				holder.innerHTML = res.data.html;
				var added = Array.prototype.slice.call(holder.content.children);
				added.forEach(function (tile) { row.appendChild(tile); });

				// Empty button means that was the last of the channel.
				if (!res.data.button) {
					wrap.remove();
				} else {
					wrap.innerHTML = res.data.button;
				}

				// Hand focus to the first new tile — otherwise a keyboard user is left
				// on a button that just moved or vanished, with no idea anything landed.
				if (added.length) {
					var firstButton = getObject('.yt-thumb', added[0]);
					if (firstButton) firstButton.focus();
				}
			}).catch(function () {
				more.disabled = false;
				more.textContent = label;
			});
		});
	});
});
