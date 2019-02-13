(function (d, s, id) {
  var js; var fjs = d.getElementsByTagName(s)[0]
  if (d.getElementById(id)) return
  js = d.createElement(s); js.id = id
  js.src = (document.location.protocol == 'https:' ? 'https://' : 'http://') + 'connect.facebook.net/en_GB/all.js#xfbml=1'
  fjs.parentNode.insertBefore(js, fjs)
}(document, 'script', 'facebook-jssdk'))

function postToFeed (link, image, name, description, action, action_link) {
  var obj = {
    method: 'feed',
    link: link,
    display: 'dialog',
    picture: image,
    name: name,
    description: description,
    actions: [{ name: action, link: action_link }]
  }
  FB.ui(obj, function () {})
};

function toggleEmbed () {
  $('#embedHolder').toggle()
  $('#embed').toggleClass('open')
};

function newTweet (status) {
  var width = 575; var height = 400

  var left = ($(window).width() - width) / 2

  var top = ($(window).height() - height) / 2

  var url = 'https://twitter.com/home?status=' + status

  var opts = 'status=1' +
               ',width=' + width +
               ',height=' + height +
               ',top=' + top +
               ',left=' + left

  window.open(url, 'twitter', opts)
};

window.fbAsyncInit = function () {
  FB.init({ appId: fb_app_id, cookie: true, status: true, xfbml: true })
}

$(document).ready(function () {
  $('#shareLink, #embedContent').click(function (e) {
    $(e.target).focus()
    $(e.target).select()
  })
})
