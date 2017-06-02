$(document).ready(function(){

    $('#lang span').click(function(e){
      var value = "tuneefyLocale=" + $(e.target).attr("lang") + "; ";
      value += "expires=Sat, 01 Feb 2042 01:20:42 GMT; path=/; domain= .tuneefy.com;"; 
      document.cookie= value;
      location.reload();
    });

});