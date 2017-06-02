function postToFeed(link, image, name, description, action, action_link) {
  var obj = {
    method: 'feed',
    link: link,
    display: 'dialog',
    picture: image,
    name: name,
    description: description,
    actions: [{ name: action, link: action_link }]
  };
  FB.ui(obj, function(){});
};

function toggleEmbed() {
  $('#embedHolder').toggle();
  $('#embed').toggleClass('open');
};

function newTweet(status){
  var width  = 575,height = 400,
      left   = ($(window).width()  - width)  / 2,
      top    = ($(window).height() - height) / 2,
      url    = "https://twitter.com/home?status=" + status,
      opts   = 'status=1' +
               ',width='  + width  +
               ',height=' + height +
               ',top='    + top    +
               ',left='   + left;
  
  window.open(url, 'twitter', opts);
};

window.fbAsyncInit = function() {
  FB.init({ appId: fb_app_id, cookie:true, status:true, xfbml:true });
};

$(document).ready(function(){

  $("#shareLink, #embedContent").click(function(e){
    $(e.target).focus();
    $(e.target).select();
  });
  
});
