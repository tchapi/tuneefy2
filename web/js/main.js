var tuneefy = function() {

  this.search_form = $('#form')
  this.search_input  = $('#query')
  this.results  = $('#results')

  this.search_form.submit((function(e) {

    // What platforms do the user want to search on ?
    var platforms = $("input[type='checkbox'].platform").filter(function(){ 
      return this.checked 
    }).map(function() {
      return $(this).attr('data-platform-tag')
    }).get()

    // What type ?
    var type = $("input[type='radio']:checked").attr("data-api-verb")

    // Initiate search ...
    this.search(this.search_input.val(), type, platforms)

    e.preventDefault()
    return false;

  }).bind(this))

}

var p = tuneefy.prototype

p.search = function(query, type, platforms) {

  /* Client-side aggregation */

  /* Or server side aggregation */
  $.get('/api/aggregate/' + type, { q: query, include: platforms.join(',') }, (function(response){
    
    // Process response
    if (response.data) {
      this.results.empty();
      $.each(response.data, (function(key, item) {
        var li = $("<li />", { text: item.musical_entity.safe_title + " (" + item.musical_entity.links.length + " links)"})
        li.appendTo(this.results)
      }).bind(this))
    }
    
  }).bind(this))

}

var t = new tuneefy()