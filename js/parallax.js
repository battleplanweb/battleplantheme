!function(t,i,e,s){function o(e,h){var r=this;"object"==typeof h&&(delete h.refresh,delete h.render,t.extend(this,h)),this.$element=t(e),!this.imageSrc&&this.$element.is("img")&&(this.imageSrc=this.$element.attr("src"));var a=(this.position+"").toLowerCase().match(/\S+/g)||[];if(a.length<1&&a.push("center"),1==a.length&&a.push(a[0]),"top"!=a[0]&&"bottom"!=a[0]&&"left"!=a[1]&&"right"!=a[1]||(a=[a[1],a[0]]),this.positionX!==s&&(a[0]=this.positionX.toLowerCase()),this.positionY!==s&&(a[1]=this.positionY.toLowerCase()),r.positionX=a[0],r.positionY=a[1],"left"!=this.positionX&&"right"!=this.positionX&&(isNaN(parseInt(this.positionX))?this.positionX="center":this.positionX=parseInt(this.positionX)),"top"!=this.positionY&&"bottom"!=this.positionY&&(isNaN(parseInt(this.positionY))?this.positionY="center":this.positionY=parseInt(this.positionY)),this.position=this.positionX+(isNaN(this.positionX)?"":"px")+" "+this.positionY+(isNaN(this.positionY)?"":"px"),navigator.userAgent.match(/(iPod|iPhone|iPad|Android)/)||t(i).width()<=1024){if(this.imageSrc&&!this.$element.is("img")){var n=this.imageSrc.split(".")[0],l="."+this.imageSrc.split(".")[1],p=t(i).width(),d=[],f=[],m=0,c=this.naturalWidth/this.naturalHeight,x=this.$element.attr("data-padding");if(navigator.userAgent.match(/(iPod|iPhone|iPad)/)&&(90!=i.orientation&&-90!=i.orientation||(deviceWidth=i.screen.height)),d[0]=480,f[0]=Math.round(d[0]/c),d[1]=640,f[1]=Math.round(d[1]/c),d[2]=960,f[2]=Math.round(d[2]/c),d[3]=1280,f[3]=Math.round(d[3]/c),p>480&&(m=1),p>640&&(m=2),p>960&&(m=3),p>1280&&(m=4),"true"==this.$element.attr("data-has-content")){var u=this.$element.find(".col.parallax").outerHeight()+2*x;this.$element.css({paddingTop:x+"px",paddingBottom:x+"px","min-height":u+"px","max-height":u+"px"}),u>f[m]&&u>f[++m]&&u>f[++m]&&m++}else this.$element.css({"min-height":f+"px","max-height":f+"px"});m<4?n=n+"-"+d[m]+"x"+f[m]+l:n+=l,this.$element.css({"background-image":"url("+n+")","background-size":"cover","background-position":this.position})}return this}this.$mirror=t("<div />").prependTo(this.mirrorContainer);var g=this.$element.find(">.parallax-slider"),b=!1;0==g.length?this.$slider=t("<img />").prependTo(this.$mirror):(this.$slider=g.prependTo(this.$mirror),b=!0),this.$mirror.addClass("parallax-mirror").css({visibility:"hidden",zIndex:this.zIndex,position:"fixed",top:0,left:0,overflow:"hidden"}),this.$slider.addClass("parallax-slider").one("load",function(){r.naturalHeight&&r.naturalWidth||(r.naturalHeight=this.naturalHeight||this.height||1,r.naturalWidth=this.naturalWidth||this.width||1),r.aspectRatio=r.naturalWidth/r.naturalHeight,o.isSetup||o.setup(),o.sliders.push(r),o.isFresh=!1,o.requestRender()}),b||(this.$slider[0].src=this.imageSrc),(this.naturalHeight&&this.naturalWidth||this.$slider[0].complete||g.length>0)&&this.$slider.trigger("load")}!function(){for(var t=0,e=["ms","moz","webkit","o"],s=0;s<e.length&&!i.requestAnimationFrame;++s)i.requestAnimationFrame=i[e[s]+"RequestAnimationFrame"],i.cancelAnimationFrame=i[e[s]+"CancelAnimationFrame"]||i[e[s]+"CancelRequestAnimationFrame"];i.requestAnimationFrame||(i.requestAnimationFrame=function(e){var s=(new Date).getTime(),o=Math.max(0,16-(s-t)),h=i.setTimeout(function(){e(s+o)},o);return t=s+o,h}),i.cancelAnimationFrame||(i.cancelAnimationFrame=function(t){clearTimeout(t)})}(),t.extend(o.prototype,{speed:.2,bleed:0,zIndex:-100,iosFix:!0,androidFix:!0,position:"center",overScrollFix:!1,mirrorContainer:"body",refresh:function(){this.boxWidth=this.$element.outerWidth(),this.boxHeight=this.$element.outerHeight()+2*this.bleed,this.boxOffsetTop=this.$element.offset().top-this.bleed,this.boxOffsetLeft=this.$element.offset().left,this.boxOffsetBottom=this.boxOffsetTop+this.boxHeight;var t,i=o.winHeight,e=o.docHeight,s=Math.min(this.boxOffsetTop,e-i),h=Math.max(this.boxOffsetTop+this.boxHeight-i,0),r=this.boxHeight+(s-h)*(1-this.speed)|0,a=(this.boxOffsetTop-s)*(1-this.speed)|0;r*this.aspectRatio>=this.boxWidth?(this.imageWidth=r*this.aspectRatio|0,this.imageHeight=r,this.offsetBaseTop=a,t=this.imageWidth-this.boxWidth,"left"==this.positionX?this.offsetLeft=0:"right"==this.positionX?this.offsetLeft=-t:isNaN(this.positionX)?this.offsetLeft=-t/2|0:this.offsetLeft=Math.max(this.positionX,-t)):(this.imageWidth=this.boxWidth,this.imageHeight=this.boxWidth/this.aspectRatio|0,this.offsetLeft=0,t=this.imageHeight-r,"top"==this.positionY?this.offsetBaseTop=a:"bottom"==this.positionY?this.offsetBaseTop=a-t:isNaN(this.positionY)?this.offsetBaseTop=a-t/2|0:this.offsetBaseTop=a+Math.max(this.positionY,-t))},render:function(){var t=o.scrollTop,i=o.scrollLeft,e=this.overScrollFix?o.overScroll:0,s=t+o.winHeight;this.boxOffsetBottom>t&&this.boxOffsetTop<=s?(this.visibility="visible",this.mirrorTop=this.boxOffsetTop-t,this.mirrorLeft=this.boxOffsetLeft-i,this.offsetTop=this.offsetBaseTop-this.mirrorTop*(1-this.speed)):this.visibility="hidden",this.$mirror.css({transform:"translate3d("+this.mirrorLeft+"px, "+(this.mirrorTop-e)+"px, 0px)",visibility:this.visibility,height:this.boxHeight,width:this.boxWidth}),this.$slider.css({transform:"translate3d("+this.offsetLeft+"px, "+this.offsetTop+"px, 0px)",position:"absolute",height:this.imageHeight,width:this.imageWidth,maxWidth:"none"})}}),t.extend(o,{scrollTop:0,scrollLeft:0,winHeight:0,winWidth:0,docHeight:1<<30,docWidth:1<<30,sliders:[],isReady:!1,isFresh:!1,isBusy:!1,setup:function(){if(!this.isReady){var s=this,h=t(e),r=t(i),a=function(){o.winHeight=r.height(),o.winWidth=r.width(),o.docHeight=h.height(),o.docWidth=h.width()},n=function(){var t=r.scrollTop(),i=o.docHeight-o.winHeight,e=o.docWidth-o.winWidth;o.scrollTop=Math.max(0,Math.min(i,t)),o.scrollLeft=Math.max(0,Math.min(e,r.scrollLeft())),o.overScroll=Math.max(t-i,Math.min(t,0))};r.on("resize.px.parallax load.px.parallax",function(){a(),s.refresh(),o.isFresh=!1,o.requestRender()}).on("scroll.px.parallax load.px.parallax",function(){n(),o.requestRender()}),a(),n(),this.isReady=!0;var l=-1;!function t(){if(l==i.pageYOffset)return i.requestAnimationFrame(t),!1;l=i.pageYOffset,s.render(),i.requestAnimationFrame(t)}()}},configure:function(i){"object"==typeof i&&(delete i.refresh,delete i.render,t.extend(this.prototype,i))},refresh:function(){t.each(this.sliders,function(){this.refresh()}),this.isFresh=!0},render:function(){this.isFresh||this.refresh(),t.each(this.sliders,function(){this.render()})},requestRender:function(){this.render(),this.isBusy=!1},destroy:function(e){var s,h=t(e).data("px.parallax");for(h.$mirror.remove(),s=0;s<this.sliders.length;s+=1)this.sliders[s]==h&&this.sliders.splice(s,1);t(e).data("px.parallax",!1),0===this.sliders.length&&(t(i).off("scroll.px.parallax resize.px.parallax load.px.parallax"),this.isReady=!1,o.isSetup=!1)}});var h=t.fn.parallax;t.fn.parallax=function(s){return this.each(function(){var h=t(this),r="object"==typeof s&&s;this==i||this==e||h.is("body")?o.configure(r):h.data("px.parallax")?"object"==typeof s&&t.extend(h.data("px.parallax"),r):(r=t.extend({},h.data(),r),h.data("px.parallax",new o(this,r))),"string"==typeof s&&("destroy"==s?o.destroy(this):o[s]())})},t.fn.parallax.Constructor=o,t.fn.parallax.noConflict=function(){return t.fn.parallax=h,this},t(function(){t('[data-parallax="scroll"]').parallax()})}(jQuery,window,document);