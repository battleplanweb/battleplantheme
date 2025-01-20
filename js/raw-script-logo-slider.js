document.addEventListener("DOMContentLoaded", function () {	"use strict";
														   
// Raw Script: Logo Slider
														   
	getObjects('.logo-slider').forEach(logoSlider => {
		let logoRow = getObject('.logo-row', logoSlider),
			direction = logoSlider.getAttribute('data-direction'),
			speed = logoSlider.getAttribute('data-speed'),
			maxW = getDeviceW() * (parseInt(logoSlider.getAttribute('data-maxw')) / 100),
			pause = logoSlider.getAttribute('data-pause'),
			spacing = (getDeviceW() * (parseInt(logoSlider.getAttribute('data-spacing')) / 100)) /2,
			easing = "linear",
			containerW = 0,
			slider_id = Date.now();
		
		getObjects('div img', logoRow).forEach(img => {
			img.classList.remove('unloaded');
			let imgW = Number(img.getAttribute('width'));
			if ( imgW > maxW ) imgW = maxW;
			img.style.maxWidth = maxW+"px";
			img.style.margin = "0 "+spacing+"px";
			containerW += imgW + (spacing * 2);
		});
		
		if ( speed === "fast" ) {
			speed = containerW / 250;
		} else if ( speed === "slow" ) {
			speed = containerW / 100;
		} else {
			speed = (containerW / 10) / Number(speed);
		}
		
		if ( containerW < getDeviceW() ) containerW = getDeviceW();
		logoRow.style.width = containerW+"px";
		
        let keyframes = `@keyframes logo_slider_${slider_id} {
            from { transform: translateX(${direction === 'reverse' ? '-100%' : '0%'}); }
            to { transform: translateX(${direction === 'reverse' ? '0%' : '-100%'}); }
        }`;
		
		let styleSheet = document.createElement("style");
        styleSheet.type = "text/css";
        styleSheet.innerText = keyframes;
        document.body.appendChild(styleSheet);				
		
		logoRow.style.animation = `logo_slider_${slider_id} ${speed}s ${easing} infinite`;
		
		let logoRowW = logoRow.offsetWidth,
			logoRowH = logoRow.offsetHeight;
		
		const pos2nd = direction === "normal" ? 'after' : 'before';		
		cloneDiv(logoRow, logoRow, pos2nd);
		
		let logo2ndRow = getObject('.logo-row:nth-of-type(2)', logoSlider);
		
		setStyles(logo2ndRow, {
			'height':			logoRowH+"px",
			'left':				logoRowW+"px"
		});
	
		if (pause === "yes" || pause === "true") {
			logoSlider.addEventListener('mouseover', () => {
				getObjects('.logo-row', logoSlider).forEach(row => {
					row.style.animationPlayState = 'paused';
				});
			});
			logoSlider.addEventListener('mouseout', () => {
				getObjects('.logo-row', logoSlider).forEach(row => {
					row.style.animationPlayState = 'running';
				});
			});
		}
	});
});