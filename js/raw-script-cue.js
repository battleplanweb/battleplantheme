document.addEventListener("DOMContentLoaded", function () {	"use strict"; 
														   
// Raw Script: Cue					
														   
/* Inject icons for use */
// Use whichever variable you localized from PHP
	const IconMap = window.BattleplanIconMap || window.IconMap || {};

	function buildFragment(iconKey) {
	  const html = IconMap[iconKey];
	  if (!html) return null;
	  const tpl = document.createElement('template');
	  tpl.innerHTML = html.trim();
	  const svg = tpl.content.querySelector('svg');
	  if (!svg) return null;
	  svg.setAttribute('data-injected-icon', iconKey);
	  return tpl.content;
	}

	function resolveIconForButton(btn) {
	  const wrap = btn.closest('.mejs-button');
	  if (!wrap) return null;

	  // Play and pause
	  if (wrap.classList.contains('mejs-playpause-button')) {
		if (wrap.classList.contains('mejs-play'))  return 'cue-play';
		if (wrap.classList.contains('mejs-pause')) return 'cue-pause';
	  }

	  // Volume
	  if (wrap.classList.contains('mejs-volume-button')) {
		if (wrap.classList.contains('mejs-mute'))    return 'cue-volume-on';
		if (wrap.classList.contains('mejs-unmute'))  return 'cue-volume-off';
	  }

	  // Previous and next
	  if (wrap.classList.contains('mejs-previous')) return 'cue-rewind';
	  if (wrap.classList.contains('mejs-next'))     return 'cue-forward';

	  return null;
	}

	function applyIcon(btn) {
	  const key = resolveIconForButton(btn);
	  if (!key) return;

	  const current = btn.querySelector('svg[data-injected-icon]');
	  if (current && current.dataset.injectedIcon === key) return; // already correct
	  if (current) current.remove();

	  const frag = buildFragment(key);
	  if (!frag) {
		console.warn('Missing IconMap key:', key);
		return;
	  }
	  btn.prepend(frag);
	}

	function injectIcons(root = document) {
	  root.querySelectorAll(
		'.mejs-previous button, .mejs-next button, .mejs-playpause-button button, .mejs-volume-button button'
	  ).forEach(applyIcon);
	}

	// Initial runs
	injectIcons();
	window.addEventListener('load', injectIcons);
	setTimeout(injectIcons, 150);

	// React to added nodes and wrapper class flips
	const mo = new MutationObserver(muts => {
	  for (const m of muts) {
		if (m.type === 'childList') {
		  m.addedNodes.forEach(n => { if (n.nodeType === 1) injectIcons(n); });
		} else if (m.type === 'attributes' && m.attributeName === 'class') {
		  const el = m.target;
		  if (el.matches('.mejs-playpause-button, .mejs-volume-button, .mejs-previous, .mejs-next')) {
			const btn = el.querySelector('button');
			if (btn) applyIcon(btn);
		  }
		}
	  }
	});

	mo.observe(document.documentElement, {
	  childList: true,
	  subtree: true,
	  attributes: true,
	  attributeFilter: ['class']
	});




	window.addEventListener("load", function() {
		const playlists = getObjects('.cue-playlist-container');

		for (const thisPlaylist of playlists) {
			const container = getObject('.mejs-container', thisPlaylist);
			if (container) {
				const cueH = container.offsetHeight * 2;
				const tracks = getObject('.cue-playlist .cue-tracks', thisPlaylist);
				if (tracks) {
					tracks.style.maxHeight = cueH + "px";
				}
			}

			const thisTitle = thisPlaylist.querySelector('.mejs-track-details');
			moveDiv('.mejs-track-title', thisTitle, 'top');
		}
	});	
});