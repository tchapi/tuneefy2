$(document).ready(function () {
  var u = $('ul.platformsPatterns li.platform')

  $('form.contactForm #send').click(function () {
    var e = $('form.contactForm #email')
    var m = $('form.contactForm #message')
    var c = $('form.contactForm #g-recaptcha-response')
    var action = $('form').attr('action')

    m.removeClass('error')
    e.removeClass('error')

    if (e.val() == '') {
      e.addClass('error')
      return false
    }

    if (m.val() == '') {
      m.addClass('error')
      return false
    }

    $('form.contactForm').hide()
    $('.waitingMail').show()

    $.post(action, { mail: e.val(), message: m.val(), captcha: c.val() }, function (data) {
      $('.waitingMail').hide()

      if (data == '1') {
        $('.successMail').show()
      } else {
        $('.errorMail').show()
      }
    })
  })

  u.click(function (e) {
    if (!$(e.target).hasClass('platform')) return
    u.removeClass('active')
    $(e.target).addClass('active')
    u.find('ul').hide()
    $(e.target).find('ul').show()
  })
})
