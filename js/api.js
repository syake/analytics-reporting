/* ========================================================================
 * report plugin
 * ======================================================================== */
(function($){
    'use strict';
    
    var Plugin = function(element, query, type){
      this.$element = $(element);
      this.query = query;
      this.type = type;
      
      if (Plugin.ready) {
        this.load.call(this, query);
      } else {
        if (Plugin.stock.length == 0) {
          google.charts.load('current', {
            packages: ['line', 'geochart'],
            mapsApiKey: 'AIzaSyDWMhVo5iNvqXnS7cJWCOPS2yTctA7gtsA'
          });
          google.charts.setOnLoadCallback(function(){
            var max = Plugin.stock.length;
            for (var i = 0; i < max; i++) {
              var e = Plugin.stock[i];
              e.load.call(e, e.query);
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
    
/*
    Plugin.DEFAULTS = {
//       type: 'line'
    }
*/
    
    Plugin.prototype.load = function(query){
      this.ready.call(this);
//       var query = $.extend({}, Plugin.DEFAULTS, query);
      var data = JSON.stringify(query);
      this.$element.data('data',data);
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
          case 'geochart':
            that.drawGeochart.call(that, data, query);
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
      var column_max = 0;
      
      // convert
      var rows = data['rows'];
      var max = rows.length;
      for(var i=0;i<max;i++){
        var new_row = [];
        new_row.push(++i);
        
        var row = rows[i];
        var metrics = row['metrics'];
        var dat = metrics['ga:sessions'];
        if (dat) {
          var values = dat['values'];
          var value_max = values.length;
          for (var j=0;j<value_max;j++) {
            var n = parseInt(values[j],10);
            new_row.push(n);
          }
        }
        new_rows.push(new_row);
        if (column_max == 0) {
          column_max = new_row.length;
        }
      }
      
      // create table
      var data = new google.visualization.DataTable();
      data.addColumn('number', 'Day');
      data.addColumn('number', 'ユーザー');
      if (column_max > 2) {
        data.addColumn('number', query['diff'] + '日前との比較');
      }
      data.addRows(new_rows);
  
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
    
    Plugin.prototype.drawGeochart = function(data, query){
      var region_index = 0;
      var dimensions = query['dimensions'];
      for(var i in dimensions){
        if (dimensions[i]=='ga:region') {
          region_index = i;
        }
      }
      
      var dat = dimensions.concat();
      dat.push('セッション');
      var new_data = [dat];
      
      // convert
      var rows = data['rows'];
      var max = rows.length;
      for(var i=0;i<max;i++){
        var row = rows[i];
        var dimensions = row['dimensions'];
        var sessions = row['metrics']['ga:sessions'];
        var type = sessions['type'];
        var n = sessions['values'][0][region_index];
        if(type == 'INTEGER'){
          n = parseInt(n,10);
        }
        var dat = dimensions;
        dat.push(n);
        new_data.push(dat);
      }
      
      var data = google.visualization.arrayToDataTable(new_data);
      var options = {
        'region': 'JP',
        'resolution': 'provinces'
      };
      this.$element.each(function(){
        var chart = new google.visualization.GeoChart(this);
        chart.draw(data, options);
      });
    }
    
    $.fn.report = function(query, type){
      return this.each(function(){
        var $this = $(this);
        var data = $this.data('report');
//         var querys = $.extend({}, Plugin.DEFAULTS, $this.data(), typeof query == 'object' && query);
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
    $('#chart-1-container').report({
      'dimensions': ['ga:nthDay'],
      'metrics': ['ga:users', 'ga:sessions', 'ga:bounceRate', 'ga:avgSessionDuration', 'ga:pageviews'],
      'startDate': start,
      'endDate': end,
      'diff': diff
    }, 'line');
    
    $('#chart-2-container').report({
      'dimensions': ['ga:regionIsoCode', 'ga:region'],
      'metrics': ['ga:sessions'],
      'startDate': start,
      'endDate': end
    }, 'geochart');
    
/*
    $('#chart-3-container').report({
      'dimensions': ['ga:regionIsoCode', 'ga:region'],
      'metrics': ['ga:sessions'],
      'startDate': start,
      'endDate': end
    }, 'geochart');
*/
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

/* ========================================================================
 * test preview
 * ======================================================================== */
(function(){
  $(function(){
    $('.report').each(function(){
      var $this = $(this);
      var $btn = $('<a href="api" target="_blanke" class="api-btn">API<i class="fa fa-external-link" aria-hidden="true"></i></a>');
      $btn.data('target','#'+$this.attr('id'));
      $this.after($btn);
      $btn.on('click',function(){
        var id = $(this).data('target');
        var $target = $(id);
        var data = $target.data('data');
        window.open(this.href+'?data='+data, id);
        return false;
      });
    });
  });
})(jQuery);
