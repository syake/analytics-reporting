/* ========================================================================
 * report plugin
 * ======================================================================== */
(function($){
    'use strict';
    
    var Plugin = function(element, query, type){
      this.$element = $(element);
      this.type = type;
      
      if (Plugin.ready) {
        this.load.call(this, query);
      } else {
        if (Plugin.stock.length == 0) {
          google.charts.load('current', {packages: ['line']});
          google.charts.setOnLoadCallback(function(){
            var max = Plugin.stock.length;
            for (var i = 0; i < max; i++) {
              var e = Plugin.stock[i];
              e.load.call(e, query);
            }
            Plugin.ready = true;
            Plugin.stock = [];
          });
        }
        Plugin.stock.push(this);
      }
    }
    
    Plugin.ready = false;
    Plugin.stock = [];
    
    Plugin.DEFAULTS = {
//       type: 'line'
    }
    
    Plugin.prototype.load = function(query){
      this.ready.call(this);
      var query = $.extend({}, Plugin.DEFAULTS, query);
      var data = JSON.stringify(query);
      
      var that = this;
      $.ajax({
        type: 'get',
        url: 'api',
        data: {'data':data},
        dataType: 'json',
        context: this.$element
      }).done(function(data, textStatus, jqXHR){
        switch (that.type) {
          case 'line':
            that.drawLine.call(that, data, query);
            break;
          default:
            console.warn('type is ' + that.type);
            break;
        } 
        
      }).fail(function(jqXHR, textStatus, errorThrown){
        console.error(jqXHR.status);
        console.error(jqXHR.responseText);
      });
    }
    
    Plugin.prototype.ready = function(){
      this.$element.text('');
    }
    
    Plugin.prototype.drawLine = function(data, query){
      var new_rows = [];
      var range_max = data.length;
      for(var ri=0;ri<range_max;ri++){
        var range = data[ri];
        var rows = range['rows'];
        var max = rows.length;
        for(var i=0;i<max;i++){
          var row = rows[i];
          var metrics = row['metrics'];
          
          var dat = metrics['ga:sessions'];
          var n = parseInt(dat['value']);
          
          if (ri == 0) {
            var d = new Date(row['date1']);
            
//             new_rows.push([d.getDate(), n]);
            new_rows.push([(i + 1), n]);
          } else {
            new_rows[i].push(n);
          }
          
        }
      }
      
//       console.log(new_rows);
      
      var data = new google.visualization.DataTable();
      data.addColumn('number', 'Day');
      data.addColumn('number', 'ユーザー');
      if (new_rows.length > 2) {
        data.addColumn('number', query['diff'] + '日前との比較');
      }
      
      data.addRows(new_rows);
/*
      data.addRows([
        [1,  37.8, 80.8, 41.8],
        [2,  30.9, 69.5, 32.4],
        [3,  25.4,   57, 25.7],
        [4,  11.7, 18.8, 10.5],
        [5,  11.9, 17.6, 10.4],
        [6,   8.8, 13.6,  7.7],
        [7,   7.6, 12.3,  9.6],
        [8,  12.3, 29.2, 10.6],
        [9,  16.9, 42.9, 14.8],
        [10, 12.8, 30.9, 11.6],
        [11,  5.3,  7.9,  4.7],
        [12,  6.6,  8.4,  5.2],
        [13,  4.8,  6.3,  3.6],
        [14,  4.2,  6.2,  3.4]
      ]);
*/
  
      var options = {
        chart: {
          title: 'Box Office Earnings in First Two Weeks of Opening',
          subtitle: 'in millions of dollars (USD)'
        },
        width: 900,
        height: 500
      };
      
      this.$element.each(function(){
        var chart = new google.charts.Line(this);
        chart.draw(data, google.charts.Line.convertOptions(options));
      });
    }
    
    $.fn.report = function(query, type){
      return this.each(function(){
        var $this = $(this);
        var data = $this.data('report');
        var querys = $.extend({}, Plugin.DEFAULTS, $this.data(), typeof query == 'object' && query);
        if (!data) {
          $this.data('report', (data = new Plugin(this, query, type)));
        } else {
          data.load.call(data, query);
        }
      });
    }
    $.fn.report.Constructor = Plugin;
}(jQuery));

/* ========================================================================
 * select and render
 * ======================================================================== */
(function($){
  var render = function(start, end, diff){
//     var metrics = ['ga:users', 'ga:sessions', 'ga:bounceRate', 'ga:avgSessionDuration', 'ga:pageviews'];
    $('#chart-1-container').report({
      'dimension': 'ga:nthDay',
      'metrics': ['ga:users', 'ga:sessions', 'ga:bounceRate', 'ga:avgSessionDuration', 'ga:pageviews'],
      'startDate': start,
      'endDate': end,
      'diff': diff
    }, 'line');
    
    $('#chart-2-container').report({
      'dimension': 'ga:region',
      'metrics': ['ga:sessions', 'ga:percentNewSessions', 'ga:newUsers', 'ga:bounceRate', 'ga:pageviewsPerSession', 'ga:avgSessionDuration'],
      'startDate': start,
      'endDate': end
    }, 'pie');
  }
  
  $(function(){
    var $menu = $('#rangeMenu');
    var $label = $menu.find('#rangeLabel');
    var $item = $menu.find('button.dropdown-item');
    $item.on('click',function(){
      var $this = $(this);
      $item.removeClass('active');
      $this.addClass('active');
      var start = $this.data('start');
      var end = $this.data('end');
      $label.text(start.replace(/\-/g , '/') + ' - ' + end.replace(/\-/g , '/'));
      var diff = $this.data('diff');
      render(start, end, diff);
    });
    $item.first().trigger('click');
  });
})(jQuery);

