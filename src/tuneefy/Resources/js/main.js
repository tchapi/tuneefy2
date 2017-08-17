$(document).ready(function(){

    var $DOMAIN = ""; // '' for localhost, else it won't work, or .tuneefy.com

    $('#lang span').click(function(e){
      var value = "tuneefyLocale=" + $(e.target).attr("lang") + "; ";
      value += "expires=Sat, 01 Feb 2042 01:20:42 GMT; path=/; domain= " + $DOMAIN + ";"; 
      document.cookie= value;
      location.reload();
    });

});