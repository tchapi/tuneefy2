Raphael.fn.pieChart = function (cx, cy, r, values, labels, stroke) {
  // We create the paper
  var paper = this

  var rad = Math.PI / 180

  var chart = this.set()

  // Function to create a sector
  function sector(cx, cy, r, startAngle, endAngle, params) {
    var x1 = cx + r * Math.cos(-startAngle * rad)

    var x2 = cx + r * Math.cos(-endAngle * rad)

    var y1 = cy + r * Math.sin(-startAngle * rad)

    var y2 = cy + r * Math.sin(-endAngle * rad)
    return paper.path(['M', cx, cy, 'L', x1, y1, 'A', r, r, 0, +(endAngle - startAngle > 180), 0, x2, y2, 'z']).attr(params)
  };

  var angle = 0

  var total = 0

  var start = 0

  var process = function (j) {
    var value = values[j]

    var angleplus = 360 * value / total

    var popangle = angle + (angleplus / 2)

    var ms = 500

    var delta = 20

    var p = sector(cx, cy, r, angle, angle + angleplus, { fill: Raphael.color('#' + labels[j][2]), stroke: 'none' })

    var txt = paper.text(cx + (r + delta + 55) * Math.cos(-popangle * rad), cy + (r + delta + 25) * Math.sin(-popangle * rad), labels[j][1]).attr({ fill: Raphael.color('#' + labels[j][2]), stroke: 'none', opacity: 0, 'font-size': 16 })
    if (angleplus > 13) { paper.image('/img/platforms/platform_chart_' + labels[j][0] + '.png', cx + (r * 0.65) * Math.cos(-popangle * rad) - 18, cy + (r * 0.65) * Math.sin(-popangle * rad) - 15, 36, 30) }
    p.mouseover(function () {
      p.stop().animate({ transform: 's1.1 1.1 ' + cx + ' ' + cy }, ms, 'elastic')
      txt.stop().animate({ opacity: 1 }, ms, 'elastic')
    }).mouseout(function () {
      p.stop().animate({ transform: '' }, ms, 'elastic')
      txt.stop().animate({ opacity: 0 }, ms)
    })
    angle += angleplus
    chart.push(p)
    chart.push(txt)
    start += 0.1
  }

  // For each value, we create the corresponding sector
  for (var i = 0, ii = values.length; i < ii; i++) {
    total += values[i]
  }
  for (i = 0; i < ii; i++) {
    process(i)
  }

  // Underlying grey circle
  paper.circle(cx, cy, r + 10).attr({ fill: '#2d2d2d', stroke: 'none' }).toBack()

  return chart
}

$(document).ready(function () {
  var values = []; var labels = []
  $('table#pieData tr').each(function () {
    values.push(parseInt($('td', this).text(), 10))
    labels.push(new Array($('th span.id', this).text(), $('th span.name', this).text(), $('th span.color', this).text()))
  })
  var w = $('#pieChart').outerWidth()
  var h = $('#pieChart').outerHeight()
  Raphael('pieChart', w, 500).pieChart(w / 2, 250, Math.min(h / 2.5, w / 2.5), values, labels, '#2d2d2d')
})
