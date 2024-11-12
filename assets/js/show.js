function toggleEmbed() {
  $('#embedHolder').toggle()
  $('#embed').toggleClass('open')
};

function newTweet(status) {
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

$(document).ready(function () {
  $('#mainLink, #embedContent').click(function (e) {
    $(e.target).focus()
    $(e.target).select()
  })
})
