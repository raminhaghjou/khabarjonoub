/* wowslider modifications
 * add:
 * -   <link rel="stylesheet" type="text/css" href="engine1/style.mod.css" />
 * -   <script type="text/javascript" src="engine1/wowslider.mod.js"></script> (after "wowslider.js", before "script.js")
 *
 * wowslider-contaner, css-classes:
 * -   "playpause" : adds play/pause controll in slider
 * -   "fullscreen" : enable fullscreen-mode button
 * -   "hidecontrolls" : hide left on first slide and right on last
 * -   "hidetitles" : title and description only on mouse hover
 * -   "stoptitles" : title and description without animation
 * -   "delays" : enable delay for each slide
 *
 * ws_images(for each <li>-block) attributes:
 * -   "data-delay = %number%" : delay for this slide
 */
(function(b){var a=b.fn.wowSlider;b.fn.wowSlider=function(p){var N=p.autoPlay;var e=b(".ws_images ul")[0].children;var I=e.length;var L=null;var m;var F=true;if(this.hasClass("delays")){p.autoPlay=false;var l=new Array();for(var K=0;K<I;K++){l[K]=b(e[K]).data("delay")||p.delay}function v(){clearTimeout(L);L=setTimeout(function(){if(N&&m&&!F){m[0].wsStart()}else{F=false}v()},l[B]+p.duration)}v()}var t=p.onBeforeStep;var k=p.onStep;b.extend(p,{onBeforeStep:function(O,P){if(!N&&m){m[0].wsStop();return O}else{if(t){return t.apply(this,[O,P])}else{return O+1}}},onStep:function(O,P){if(k){k.apply(this,[O,P])}B=O;if(j){if(j.hasCla