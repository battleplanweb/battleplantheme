!function(e,t){"object"==typeof exports&&"undefined"!=typeof module?t(exports,require("jquery"),require("popper.js")):"function"==typeof define&&define.amd?define(["exports","jquery","popper.js"],t):t((e="undefined"!=typeof globalThis?globalThis:e||self).bootstrap={},e.jQuery,e.Popper)}(this,function(e,t,i){"use strict";function n(e){return e&&"object"==typeof e&&"default"in e?e:{default:e}}var r=n(t);function a(e,t){for(var i=0;i<t.length;i++){var n=t[i];n.enumerable=n.enumerable||!1,n.configurable=!0,"value"in n&&(n.writable=!0),Object.defineProperty(e,n.key,n)}}function o(){return(o=Object.assign||function(e){for(var t=1;t<arguments.length;t++){var i=arguments[t];for(var n in i)Object.prototype.hasOwnProperty.call(i,n)&&(e[n]=i[n])}return e}).apply(this,arguments)}var s="transitionend";function l(e){var t=this,i=!1;return r.default(this).one(u.TRANSITION_END,function(){i=!0}),setTimeout(function(){i||u.triggerTransitionEnd(t)},e),this}var u={TRANSITION_END:"bsTransitionEnd",getUID:function(e){do{e+=~~(1e6*Math.random())}while(document.getElementById(e));return e},getSelectorFromElement:function(e){var t=e.getAttribute("data-target");if(!t||"#"===t){var i=e.getAttribute("href");t=i&&"#"!==i?i.trim():""}try{return document.querySelector(t)?t:null}catch(e){return null}},getTransitionDurationFromElement:function(e){if(!e)return 0;var t=r.default(e).css("transition-duration"),i=r.default(e).css("transition-delay"),n=parseFloat(t),a=parseFloat(i);return n||a?(t=t.split(",")[0],i=i.split(",")[0],1e3*(parseFloat(t)+parseFloat(i))):0},reflow:function(e){return e.offsetHeight},triggerTransitionEnd:function(e){r.default(e).trigger(s)},supportsTransitionEnd:function(){return Boolean(s)},isElement:function(e){return(e[0]||e).nodeType},typeCheckConfig:function(e,t,i){for(var n in i)if(Object.prototype.hasOwnProperty.call(i,n)){var r=i[n],a=t[n],o=a&&u.isElement(a)?"element":null==(s=a)?""+s:{}.toString.call(s).match(/\s([a-z]+)/i)[1].toLowerCase();if(!new RegExp(r).test(o))throw new Error(e.toUpperCase()+': Option "'+n+'" provided type "'+o+'" but expected type "'+r+'".')}var s},findShadowRoot:function(e){if(!document.documentElement.attachShadow)return null;if("function"==typeof e.getRootNode){var t=e.getRootNode();return t instanceof ShadowRoot?t:null}return e instanceof ShadowRoot?e:e.parentNode?u.findShadowRoot(e.parentNode):null},jQueryDetection:function(){if(void 0===r.default)throw new TypeError("Bootstrap's JavaScript requires jQuery. jQuery must be included before Bootstrap's JavaScript.");var e=r.default.fn.jquery.split(" ")[0].split(".");if(e[0]<2&&e[1]<9||1===e[0]&&9===e[1]&&e[2]<1||e[0]>=4)throw new Error("Bootstrap's JavaScript requires at least jQuery v1.9.1 but less than v4.0.0")}};u.jQueryDetection(),r.default.fn.emulateTransitionEnd=l,r.default.event.special[u.TRANSITION_END]={bindType:s,delegateType:s,handle:function(e){if(r.default(e.target).is(this))return e.handleObj.handler.apply(this,arguments)}};var c="carousel",d=".bs.carousel",f=r.default.fn[c],h={interval:5e3,keyboard:!0,slide:!1,pause:"hover",wrap:!0,touch:!0},v={interval:"(number|boolean)",keyboard:"boolean",slide:"(boolean|string)",pause:"(string|boolean)",wrap:"boolean",touch:"boolean"},_=".carousel-indicators",p={TOUCH:"touch",PEN:"pen"},m=function(){function e(e,t){this._items=null,this._interval=null,this._activeElement=null,this._isPaused=!1,this._isSliding=!1,this.touchTimeout=null,this.touchStartX=0,this.touchDeltaX=0,this._config=this._getConfig(t),this._element=e,this._indicatorsElement=this._element.querySelector(_),this._touchSupported="ontouchstart"in document.documentElement||navigator.maxTouchPoints>0,this._pointerEvent=Boolean(window.PointerEvent||window.MSPointerEvent),this._addEventListeners()}var t,i,n,s=e.prototype;return s.next=function(){this._isSliding||this._slide("next")},s.nextWhenVisible=function(){var e=r.default(this._element);!document.hidden&&e.is(":visible")&&"hidden"!==e.css("visibility")&&this.next()},s.prev=function(){this._isSliding||this._slide("prev")},s.pause=function(e){e||(this._isPaused=!0),this._element.querySelector(".carousel-item-next, .carousel-item-prev")&&(u.triggerTransitionEnd(this._element),this.cycle(!0)),clearInterval(this._interval),this._interval=null},s.cycle=function(e){e||(this._isPaused=!1),this._interval&&(clearInterval(this._interval),this._interval=null),this._config.interval&&!this._isPaused&&(this._interval=setInterval((document.visibilityState?this.nextWhenVisible:this.next).bind(this),this._config.interval))},s.to=function(e){var t=this;this._activeElement=this._element.querySelector(".active.carousel-item");var i=this._getItemIndex(this._activeElement);if(!(e>this._items.length-1||e<0))if(this._isSliding)r.default(this._element).one("slid.bs.carousel",function(){return t.to(e)});else{if(i===e)return this.pause(),void this.cycle();var n=e>i?"next":"prev";this._slide(n,this._items[e])}},s.dispose=function(){r.default(this._element).off(d),r.default.removeData(this._element,"bs.carousel"),this._items=null,this._config=null,this._element=null,this._interval=null,this._isPaused=null,this._isSliding=null,this._activeElement=null,this._indicatorsElement=null},s._getConfig=function(e){return e=o({},h,e),u.typeCheckConfig(c,e,v),e},s._handleSwipe=function(){var e=Math.abs(this.touchDeltaX);if(!(e<=40)){var t=e/this.touchDeltaX;this.touchDeltaX=0,t>0&&this.prev(),t<0&&this.next()}},s._addEventListeners=function(){var e=this;this._config.keyboard&&r.default(this._element).on("keydown.bs.carousel",function(t){return e._keydown(t)}),"hover"===this._config.pause&&r.default(this._element).on("mouseenter.bs.carousel",function(t){return e.pause(t)}).on("mouseleave.bs.carousel",function(t){return e.cycle(t)}),this._config.touch&&this._addTouchEventListeners()},s._addTouchEventListeners=function(){var e=this;if(this._touchSupported){var t=function(t){e._pointerEvent&&p[t.originalEvent.pointerType.toUpperCase()]?e.touchStartX=t.originalEvent.clientX:e._pointerEvent||(e.touchStartX=t.originalEvent.touches[0].clientX)},i=function(t){e._pointerEvent&&p[t.originalEvent.pointerType.toUpperCase()]&&(e.touchDeltaX=t.originalEvent.clientX-e.touchStartX),e._handleSwipe(),"hover"===e._config.pause&&(e.pause(),e.touchTimeout&&clearTimeout(e.touchTimeout),e.touchTimeout=setTimeout(function(t){return e.cycle(t)},500+e._config.interval))};r.default(this._element.querySelectorAll(".carousel-item img")).on("dragstart.bs.carousel",function(e){return e.preventDefault()}),this._pointerEvent?(r.default(this._element).on("pointerdown.bs.carousel",function(e){return t(e)}),r.default(this._element).on("pointerup.bs.carousel",function(e){return i(e)}),this._element.classList.add("pointer-event")):(r.default(this._element).on("touchstart.bs.carousel",function(e){return t(e)}),r.default(this._element).on("touchmove.bs.carousel",function(t){return function(t){t.originalEvent.touches&&t.originalEvent.touches.length>1?e.touchDeltaX=0:e.touchDeltaX=t.originalEvent.touches[0].clientX-e.touchStartX}(t)}),r.default(this._element).on("touchend.bs.carousel",function(e){return i(e)}))}},s._keydown=function(e){if(!/input|textarea/i.test(e.target.tagName))switch(e.which){case 37:e.preventDefault(),this.prev();break;case 39:e.preventDefault(),this.next()}},s._getItemIndex=function(e){return this._items=e&&e.parentNode?[].slice.call(e.parentNode.querySelectorAll(".carousel-item")):[],this._items.indexOf(e)},s._getItemByDirection=function(e,t){var i="next"===e,n="prev"===e,r=this._getItemIndex(t),a=this._items.length-1;if((n&&0===r||i&&r===a)&&!this._config.wrap)return t;var o=(r+("prev"===e?-1:1))%this._items.length;return-1===o?this._items[this._items.length-1]:this._items[o]},s._triggerSlideEvent=function(e,t){var i=this._getItemIndex(e),n=this._getItemIndex(this._element.querySelector(".active.carousel-item")),a=r.default.Event("slide.bs.carousel",{relatedTarget:e,direction:t,from:n,to:i});return r.default(this._element).trigger(a),a},s._setActiveIndicatorElement=function(e){if(this._indicatorsElement){var t=[].slice.call(this._indicatorsElement.querySelectorAll(".active"));r.default(t).removeClass("active");var i=this._indicatorsElement.children[this._getItemIndex(e)];i&&r.default(i).addClass("active")}},s._slide=function(e,t){var i,n,a,o=this,s=this._element.querySelector(".active.carousel-item"),l=this._getItemIndex(s),c=t||s&&this._getItemByDirection(e,s),d=this._getItemIndex(c),f=Boolean(this._interval);if("next"===e?(i="carousel-item-left",n="carousel-item-next",a="left"):(i="carousel-item-right",n="carousel-item-prev",a="right"),c&&r.default(c).hasClass("active"))this._isSliding=!1;else if(!this._triggerSlideEvent(c,a).isDefaultPrevented()&&s&&c){this._isSliding=!0,f&&this.pause(),this._setActiveIndicatorElement(c);var h=r.default.Event("slid.bs.carousel",{relatedTarget:c,direction:a,from:l,to:d});if(r.default(this._element).hasClass("slide")){r.default(c).addClass(n),u.reflow(c),r.default(s).addClass(i),r.default(c).addClass(i);var v=parseInt(c.getAttribute("data-interval"),10);v?(this._config.defaultInterval=this._config.defaultInterval||this._config.interval,this._config.interval=v):this._config.interval=this._config.defaultInterval||this._config.interval;var _=u.getTransitionDurationFromElement(s);r.default(s).one(u.TRANSITION_END,function(){r.default(c).removeClass(i+" "+n).addClass("active"),r.default(s).removeClass("active "+n+" "+i),o._isSliding=!1,setTimeout(function(){return r.default(o._element).trigger(h)},0)}).emulateTransitionEnd(_)}else r.default(s).removeClass("active"),r.default(c).addClass("active"),this._isSliding=!1,r.default(this._element).trigger(h);f&&this.cycle()}},e._jQueryInterface=function(t){return this.each(function(){var i=r.default(this).data("bs.carousel"),n=o({},h,r.default(this).data());"object"==typeof t&&(n=o({},n,t));var a="string"==typeof t?t:n.slide;if(i||(i=new e(this,n),r.default(this).data("bs.carousel",i)),"number"==typeof t)i.to(t);else if("string"==typeof a){if(void 0===i[a])throw new TypeError('No method named "'+a+'"');i[a]()}else n.interval&&n.ride&&(i.pause(),i.cycle())})},e._dataApiClickHandler=function(t){var i=u.getSelectorFromElement(this);if(i){var n=r.default(i)[0];if(n&&r.default(n).hasClass("carousel")){var a=o({},r.default(n).data(),r.default(this).data()),s=this.getAttribute("data-slide-to");s&&(a.interval=!1),e._jQueryInterface.call(r.default(n),a),s&&r.default(n).data("bs.carousel").to(s),t.preventDefault()}}},t=e,n=[{key:"VERSION",get:function(){return"4.5.3"}},{key:"Default",get:function(){return h}}],(i=null)&&a(t.prototype,i),n&&a(t,n),e}();r.default(document).on("click.bs.carousel.data-api","[data-slide], [data-slide-to]",m._dataApiClickHandler),r.default(window).on("load.bs.carousel.data-api",function(){for(var e=[].slice.call(document.querySelectorAll('[data-ride="carousel"]')),t=0,i=e.length;t<i;t++){var n=r.default(e[t]);m._jQueryInterface.call(n,n.data())}}),r.default.fn[c]=m._jQueryInterface,r.default.fn[c].Constructor=m,r.default.fn[c].noConflict=function(){return r.default.fn[c]=f,m._jQueryInterface},e.Carousel=m,e.Util=u,Object.defineProperty(e,"__esModule",{value:!0})});