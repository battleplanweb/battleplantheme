document.addEventListener("DOMContentLoaded", function () {	"use strict";
														   
// Raw Script: Count Up	
														   
	var CountUp = function(target, startVal, endVal, decimals, duration, options) {
		var lastTime = 0;
		var vendors = ['webkit', 'moz', 'ms', 'o'];
		for(var x = 0; x < vendors.length && !window.requestAnimationFrame; ++x) {
			window.requestAnimationFrame = window[vendors[x]+'RequestAnimationFrame'];
			window.cancelAnimationFrame =
			  window[vendors[x]+'CancelAnimationFrame'] || window[vendors[x]+'CancelRequestAnimationFrame'];
		}
		if (!window.requestAnimationFrame) {
			window.requestAnimationFrame = function(callback, element) {
				var currTime = new Date().getTime();
				var timeToCall = Math.max(0, 16 - (currTime - lastTime));
				var id = window.setTimeout(function() { callback(currTime + timeToCall); },
				  timeToCall);
				lastTime = currTime + timeToCall;
				return id;
			};
		}
		if (!window.cancelAnimationFrame) {
			window.cancelAnimationFrame = function(id) {
				clearTimeout(id);
			};
		}

		 // default options
		this.options = {
			useEasing : true, // toggle easing
			useGrouping : true, // 1,000,000 vs 1000000
			separator : ',', // character to use as a separator
			decimal : '.' // character to use as a decimal
		};
		// extend default options with passed options object
		for (var key in options) {
			if (options.hasOwnProperty(key)) {
				this.options[key] = options[key];
			}
		}
		if (this.options.separator === '') this.options.useGrouping = false;
		if (!this.options.prefix) this.options.prefix = '';
		if (!this.options.suffix) this.options.suffix = '';

		this.d = (typeof target === 'string') ? document.getElementById(target) : target;
		this.startVal = Number(startVal);
		if (isNaN(startVal)) this.startVal = Number(startVal.match(/[\d]+/g).join('')); // strip non-numerical characters
		this.endVal = Number(endVal);
		if (isNaN(endVal)) this.endVal = Number(endVal.match(/[\d]+/g).join('')); // strip non-numerical characters
		this.countDown = (this.startVal > this.endVal);
		this.frameVal = this.startVal;
		this.decimals = Math.max(0, decimals || 0);
		this.dec = Math.pow(10, this.decimals);
		this.duration = Number(duration) * 1000 || 2000;
		var self = this;

		this.version = function () { return '1.5.3'; };

		// Print value to target
		this.printValue = function(value) {
			var result = (!isNaN(value)) ? self.formatNumber(value) : '--';
			if (self.d.tagName == 'INPUT') {
				this.d.value = result;
			}
			else if (self.d.tagName == 'text') {
				this.d.textContent = result;
			}
			else {
				this.d.innerHTML = result;
			}
		};

		// Robert Penner's easing
		this.applyEasing = function(t, b, c, d) {		
			if (self.options.useEasing=="easeInSine") { return -c * Math.cos(t/d * (Math.PI/2)) + c * 1024 / 1023 + b; }		
			if (self.options.useEasing=="easeOutSine") { return c * Math.sin(t/d * (Math.PI/2)) * 1024 / 1023 + b; }
			if (self.options.useEasing=="easeInOutSine") { return -c/2 * (Math.cos(Math.PI*t/d) - 1) * 1024 / 1023 + b; }

			if (self.options.useEasing=="easeInQuad") { 
				t = t / d;
				return c*t*t * 1024 / 1023 + b; 
			}		
			if (self.options.useEasing=="easeOutQuad") { 
				t = t / d;
				return -c * t*(t-2) * 1024 / 1023 + b; 
			}		
			if (self.options.useEasing=="easeInOutQuad") { 
				t = t / (d/2);
				if (t < 1) { return c/2*t*t * 1024 / 1023 + b; }
				t--;
				return -c/2 * (t*(t-2) - 1) * 1024 / 1023 + b; 
			}	

			if (self.options.useEasing=="easeInCubic") { 
				t = t / d;
				return c*t*t*t * 1024 / 1023 + b; 
			}		
			if (self.options.useEasing=="easeOutCubic") { 
				t = t / d;
				t--;
				return c*(t*t*t + 1) * 1024 / 1023 + b; 
			}		
			if (self.options.useEasing=="easeInOutCubic") { 
				t = t / (d/2);
				if (t < 1) { return c/2*t*t*t * 1024 / 1023 + b; }
				t = t-2;
				return c/2*(t*t*t + 2) * 1024 / 1023 + b; 
			}		

			if (self.options.useEasing=="easeInExpo") { return c * Math.pow( 2, 10 * (t/d - 1) ) * 1024 / 1023 + b; }
			if (self.options.useEasing=="easeOutExpo") { return c * (-Math.pow(2, -10 * t / d) + 1) * 1024 / 1023 + b; }
			if (self.options.useEasing=="easeInOutExpo") { 
				t = t / (d/2);
				if (t < 1) { return c/2 * Math.pow( 2, 10 * (t - 1) ) * 1024 / 1023 + b; }
				t--;
				return c/2 * ( -Math.pow( 2, -10 * t) + 2 ) * 1024 / 1023 + b; 
			}

			if (self.options.useEasing=="easeInCirc") { 
				t = t / d;
				return -c * (Math.sqrt(1 - t*t) - 1) * 1024 / 1023 + b; 
			}		
			if (self.options.useEasing=="easeOutCirc") { 
				t = t / d;
				t--;
				return c * Math.sqrt(1 - t*t) * 1024 / 1023 + b; 
			}		
			if (self.options.useEasing=="easeInOutCirc") { 
				t = t / (d/2);
				if (t < 1) { return -c/2 * (Math.sqrt(1 - t*t) - 1) * 1024 / 1023 + b; }
				t = t-2;
				return c/2 * (Math.sqrt(1 - t*t) + 1) * 1024 / 1023 + b; 
			}
		};
		this.count = function(timestamp) {

			if (!self.startTime) self.startTime = timestamp;

			self.timestamp = timestamp;

			var progress = timestamp - self.startTime;
			self.remaining = self.duration - progress;

			// to ease or not to ease
			if (self.options.useEasing=="false") {		
				if (self.countDown) {
					self.frameVal = self.startVal - ((self.startVal - self.endVal) * (progress / self.duration));
				} else {
					self.frameVal = self.startVal + (self.endVal - self.startVal) * (progress / self.duration);
				}			
			} else {		
				if (self.countDown) {
					self.frameVal = self.startVal - self.applyEasing(progress, 0, self.startVal - self.endVal, self.duration);
				} else {
					self.frameVal = self.applyEasing(progress, self.startVal, self.endVal - self.startVal, self.duration);
				}			
			}

			// don't go past endVal since progress can exceed duration in the last frame
			if (self.countDown) {
				self.frameVal = (self.frameVal < self.endVal) ? self.endVal : self.frameVal;
			} else {
				self.frameVal = (self.frameVal > self.endVal) ? self.endVal : self.frameVal;
			}

			// decimal
			self.frameVal = Math.round(self.frameVal*self.dec)/self.dec;

			// format and print value
			self.printValue(self.frameVal);

			// whether to continue
			if (progress < self.duration) {
				self.rAF = requestAnimationFrame(self.count);
			} else {
				if (self.callback) self.callback();
			}
		};
		// start your animation
		this.start = function(callback) {
			self.callback = callback;
			// make sure values are valid
			if (!isNaN(self.endVal) && !isNaN(self.startVal) && self.startVal !== self.endVal) {
				self.rAF = requestAnimationFrame(self.count);
			} else {
				console.log('countUp error: startVal or endVal is not a number');
				self.printValue(endVal);
			}
			return false;
		};
		// toggles pause/resume animation
		this.pauseResume = function() {
			if (!self.paused) {
				self.paused = true;
				cancelAnimationFrame(self.rAF);
			} else {
				self.paused = false;
				delete self.startTime;
				self.duration = self.remaining;
				self.startVal = self.frameVal;
				requestAnimationFrame(self.count);
			}
		};
		// reset to startVal so animation can be run again
		this.reset = function() {
			self.paused = false;
			delete self.startTime;
			self.startVal = startVal;
			cancelAnimationFrame(self.rAF);
			self.printValue(self.startVal);
		};
		// pass a new endVal and start animation
		this.update = function (newEndVal) {
			cancelAnimationFrame(self.rAF);
			self.paused = false;
			delete self.startTime;
			self.startVal = self.frameVal;
			self.endVal = Number(newEndVal);
			self.countDown = (self.startVal > self.endVal);
			self.rAF = requestAnimationFrame(self.count);
		};
		this.formatNumber = function(nStr) {
			nStr = nStr.toFixed(self.decimals);
			nStr += '';
			var x, x1, x2, rgx;
			x = nStr.split('.');
			x1 = x[0];
			x2 = x.length > 1 ? self.options.decimal + x[1] : '';
			rgx = /(\d+)(\d{3})/;
			if (self.options.useGrouping) {
				while (rgx.test(x1)) {
					x1 = x1.replace(rgx, '$1' + self.options.separator + '$2');
				}
			}
			return self.options.prefix + x1 + x2 + self.options.suffix;
		};

		// format startVal on initialization
		self.printValue(self.startVal);
	};
														   
	function initCountUp(element) {
		const duration = element.getAttribute('data-duration'),
			  start = element.getAttribute('data-start'),
			  end = element.getAttribute('data-end'),
			  target = getObject('span', element),
			  options = {
				useEasing: element.getAttribute('data-easing'),
				useGrouping: element.getAttribute('data-grouping'),
				separator: element.getAttribute('data-separator'),
				decimal: element.getAttribute('data-decimal'),
				prefix: element.getAttribute('data-prefix'),
				suffix: element.getAttribute('data-suffix')
			  };
		
		const countUpInstance = new CountUp(target, start, end, 0, duration, options);

		const waypoint = new IntersectionObserver(entries => {
			entries.forEach(entry => {
				if (entry.isIntersecting) {
					setTimeout(() => countUpInstance.start(), 0);
					waypoint.unobserve(entry.target);
				}
			});
		}, {
			threshold: 0.15
		});

		waypoint.observe(element);
	}

	getObjects('.count-up').forEach(element => {
		initCountUp(element);
	});
});