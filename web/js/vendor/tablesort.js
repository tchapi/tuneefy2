!function(u){u.tablesort=function(t,e){var s=this;this.$table=t,this.$thead=this.$table.find("thead"),this.settings=u.extend({},u.tablesort.defaults,e),this.$sortCells=0<this.$thead.length?this.$thead.find("th:not(.no-sort)"):this.$table.find("th:not(.no-sort)"),this.$sortCells.on("click.tablesort",function(){s.sort(u(this))}),this.index=null,this.$th=null,this.direction=null},u.tablesort.prototype={sort:function(s,i){var n=new Date,o=this,t=this.$table,l=0<t.find("tbody").length?t.find("tbody"):t,a=l.find("tr").has("td, th"),r=a.find(":nth-child("+(s.index()+1)+")").filter("td, th"),d=s.data().sortBy,h=[],c=r.map(function(t,e){return d?"function"==typeof d?d(u(s),u(e),o):d:null!=u(this).data().sortValue?u(this).data().sortValue:u(this).text()});0!==c.length&&(this.index!==s.index()?(this.direction="asc",this.index=s.index()):this.direction="asc"!==i&&"desc"!==i?"asc"===this.direction?"desc":"asc":i,i="asc"==this.direction?1:-1,o.$table.trigger("tablesort:start",[o]),o.log("Sorting by "+this.index+" "+this.direction),o.$table.css("display"),setTimeout(function(){o.$sortCells.removeClass(o.settings.asc+" "+o.settings.desc);for(var t=0,e=c.length;t<e;t++)h.push({index:t,cell:r[t],row:a[t],value:c[t]});h.sort(function(t,e){return o.settings.compare(t.value,e.value)*i}),u.each(h,function(t,e){l.append(e.row)}),s.addClass(o.settings[o.direction]),o.log("Sort finished in "+((new Date).getTime()-n.getTime())+"ms"),o.$table.trigger("tablesort:complete",[o]),o.$table.css("display")},2e3<c.length?200:10))},log:function(t){(u.tablesort.DEBUG||this.settings.debug)&&console&&console.log&&console.log("[tablesort] "+t)},destroy:function(){return this.$sortCells.off("click.tablesort"),this.$table.data("tablesort",null),null}},u.tablesort.DEBUG=!1,u.tablesort.defaults={debug:u.tablesort.DEBUG,asc:"sorted ascending",desc:"sorted descending",compare:function(t,e){return e<t?1:t<e?-1:0}},u.fn.tablesort=function(t){var e,s;return this.each(function(){e=u(this),(s=e.data("tablesort"))&&s.destroy(),e.data("tablesort",new u.tablesort(e,t))})}}(window.Zepto||window.jQuery);