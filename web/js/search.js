"use strict";var search;$(document).ready(function(){var a="tuneefyPrefs",s="tuneefyHelpBox",t="tuneefySearchType",n=$("#find"),i=$("#query"),l=i.attr("data-placeholder"),r=$("#launch"),o=$("#aggressive"),c=$("#searchTypeCheckbox"),d=$("#options"),h=$("#advanced,#hideMisere"),u=$("a.btns"),e=$(".searchForIt"),p=$("#alerts"),f=$("span.closeHelp"),m=$("span.closeForever"),v=$("#resetQuery"),g=$("#results"),k=$("#results ul"),C=$(".nbResults"),b=$("#waiting"),y=($("a.tPagerNext img, a.tPagerPrev img, .tPagerPage"),"");r.removeAttr("disabled"),u.removeAttr("on"),"function"==typeof $.iphoneStyle&&(o.iphoneStyle({checkedLabel:o.attr("data-yes"),uncheckedLabel:o.attr("data-no"),resizeContainer:!1,resizeHandle:!1}),c.iphoneStyle({checkedLabel:"",uncheckedLabel:"",resizeContainer:!1,resizeHandle:!1,containerClass:"iPhoneCheckContainer otherContainer",labelOnClass:"iPhoneCheckLabelOn albums",labelOffClass:"iPhoneCheckLabelOff tracks",handleClass:"iPhoneCheckHandle otherHandle",handleCenterClass:"iPhoneCheckHandleCenter noBG",handleRightClass:"iPhoneCheckHandleRight activeTracks",containerRadius:2,onChange:function(){var e=c.is(":checked")?($("#typeTracks").removeClass("off"),$("#typeAlbums").addClass("off"),$(".iPhoneCheckHandleRight.activeAlbums").addClass("activeTracks"),$(".iPhoneCheckHandleRight.activeAlbums").removeClass("activeAlbums"),t+"=tracks; "):($("#typeTracks").addClass("off"),$("#typeAlbums").removeClass("off"),$(".iPhoneCheckHandleRight.activeTracks").addClass("activeAlbums"),$(".iPhoneCheckHandleRight.activeTracks").removeClass("activeTracks"),t+"=albums; ");e+="expires=Sat, 01 Feb 2042 01:20:42 GMT; path=/; domain= "+$DOMAIN+";",document.cookie=e}})),$(document).on("click",".closeAlert",function(){$(this).parent().fadeOut()});var P=document.cookie.split(a+"=")[1]||"",y=P=""===P?$default_platforms:decodeURIComponent(P.split(";")[0]),T=P.split(",");$.each(T,function(e,t){$("#platform_"+t).attr("on","yes").removeClass("off")}),null!==(P=document.cookie.split(t+"=")[1]||"")&&"albums"===P.split(";")[0]&&c.click(),search=function(e,t,n){console.log("Searching for "+n.itemType+"s with query '"+t+"' on "+n.selectedPlatforms+" (strict: "+n.strictMode+").");var a={q:t,aggressive:n.strictMode,include:n.selectedPlatforms,limit:n.limit||10};e=e+"?"+$.param(a);$.get({url:e,crossDomain:!0,beforeSend:function(e){e.setRequestHeader("X-Requested-With","XMLHttpRequest")},xhrFields:{withCredentials:!0}}).done(function(s){if(s.errors&&0<s.errors.length){for(var e=0;e<s.errors.length;e++)p.append("<span class='alert' ><div class=\"triangle\"></div>"+Object.values(s.errors[0])[0]+"<span class='closeAlert'></span></span>");p.children().last().fadeIn()}s.results?(n.updateNumberLabel&&C.html($results_found.replace("%query%",t).replace("%type%",n.itemType).replace("%number%",s.results.length)),$(".tHeader_disp[rel="+n.itemType+"]").show(),Twig.twig({href:"js/twig/result.html.twig",async:!0,load:function(e){for(var t in s.results){var a=s.results[t];k.append(e.render({type:n.itemType,item:a.musical_entity,intent:a.share.intent,share:$share,listenTo:$listen_to.replace("%name%",a.musical_entity.safe_title),shareTip:$share_tip.replace("%name%",a.musical_entity.safe_title),linkDirect:$path,linkIntent:$pathIntent,compact:!0}))}g.show()}})):(p.append("<span class='alert' ><div class=\"triangle\"></div>"+$error_message+"<span class='closeAlert'></span></span>"),p.children().last().fadeIn())}).fail(function(){p.append("<span class='alert' ><div class=\"triangle\"></div>"+$error_message+"<span class='closeAlert'></span></span>"),p.children().last().fadeIn()}).always(function(){b.hide(),r.removeAttr("disabled"),i.blur(),0<k.length&&$("html,body").animate({scrollTop:k.offset().top-20},"slow")})},n.submit(function(e){e.preventDefault(),$(".hideAll").fadeOut(),h.hide(),d.removeClass("shd");var t=$.trim(i.val()),a=o.is(":checked"),s=c.is(":checked")?"track":"album";if(""===t||t===l||""===y)return!1;r.attr("disabled","disabled"),g.hide(),k.find(".tResult").remove(),b.show(),p.empty(),$(".tHeader_disp").hide();e=n.attr("action").replace("%type%",s);search(e,t,{itemType:s,selectedPlatforms:y,strictMode:a,updateNumberLabel:!0})}),i.click(function(e){i.val()===l&&i.val(""),$(e.target).select(),$("#basic").addClass("focused"),$(".hideAll").fadeTo(500,.5),null===document.cookie.split(s+"=")[1]&&$("#help").fadeIn(),e.stopPropagation()}),e.click(function(e){i.val($(e.target).html()),n.submit()}),i.keyup(function(){i.val()!==l&&0!==$.trim(i.val()).length?v.show():v.hide()}),v.click(function(e){i.val(""),i.focus(),v.hide(),e.stopPropagation()}),f.click(function(e){$("#help").fadeOut(),i.focus(),e.stopPropagation()}),m.click(function(e){var t=s+"=neverAgain; ";t+="expires=Sat, 01 Feb 2042 01:20:42 GMT; path=/; domain= "+$DOMAIN+";",document.cookie=t,$("#help").fadeOut(),i.focus(),e.stopPropagation()}),d.click(function(e){h.toggle(),d.toggleClass("shd"),e.stopPropagation()}),$(".hideAll").click(function(){}),$("html").click(function(){h.hide(),d.removeClass("shd"),$("#basic").removeClass("focused"),$(".hideAll").fadeOut(),$("#help").fadeOut(),""===i.val()&&i.val(l)}),h.click(function(e){e.stopPropagation()}),u.click(function(e){"yes"===$(e.target).attr("on")?($(e.target).attr("on","no"),$(e.target).addClass("off")):($(e.target).attr("on","yes"),$(e.target).removeClass("off"));var t=[];u.each(function(){"yes"===$(this).attr("on")&&t.push($(this).attr("rel"))}),y=t.toString();e=a+"="+encodeURIComponent(y)+"; expires=Sat, 01 Feb 2042 01:20:42 GMT; path=/; domain= "+$DOMAIN+";";document.cookie=e}),$(document).on("click",".sharePage",function(){var e=$(this).attr("data-href");$.get({url:e,crossDomain:!0,beforeSend:function(e){e.setRequestHeader("X-Requested-With","XMLHttpRequest")},xhrFields:{withCredentials:!0}}).done(function(e){e.link&&(window.location.href=e.link)}).fail(function(){}).always(function(){})});var w=$(".pick"),A=$(".pickPagerItem");0<A.length&&A.first().addClass("active"),1<w.length&&setInterval(function(){var e=w.filter(":visible"),t=A.filter(".active"),a=e.next(),s=t.next();0==a.length&&(a=w.first(),s=A.first()),a.css("left","400px"),a.show(),a.animate({left:"30px"},"slow"),e.animate({left:"-300px"},"slow"),e.fadeOut("slow"),t.removeClass("active"),s.addClass("active")},4e3)});