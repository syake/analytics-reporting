<?php
// request
$request = [];
if (isset($_GET['data'])) {
  $request = json_decode($_GET['data'], true);
}

// default
$request = array_merge([
  'startDate' => date('Y-m-d', strtotime('-7 day')),
  'endDate' => date('Y-m-d', strtotime('-1 day')),
  'diff' => 7,
  'dimensions' => ['ga:nthDay'],
  'metrics' => []
], $request);

$ranges = [];
$ranges[] = [strtotime($request['startDate']), strtotime($request['endDate'])];
if ($request['diff'] != NULL) {
  $diff = intval($request['diff']);
  $ranges[] = [strtotime('-' . (--$diff) . ' day', $ranges[0][0]), strtotime('-' . $diff . ' day', $ranges[0][1])];
}

/*
switch ($dimension) {
  case 'ga:nthDay':
    $startDate = strtotime("-{$rangeDay} day", $date);
    $dateRange = [$startDate, $date];
    
    $ageDay = $rangeDay + 1;
    $ageStartDate = strtotime("-{$ageDay} day", $startDate);
    $ageEndDate = strtotime("-{$ageDay} day", $date);
    $ageDateRange = [$ageStartDate, $ageEndDate];
    break;
  case 'ga:nthWeek':
    $rangeMonth = 3;
    $startDate = strtotime("last Monday -{$rangeMonth} month +1 day", $date);
    $dateRange = [$startDate, $date];
    break;
  case 'ga:nthMonth':
    $rangeMonth = 12;
    $startDate = strtotime("first day of -{$rangeMonth} month", $date);
    $dateRange = [$startDate, $date];
    break;
  default:
    break;
}
*/

// Load the Google API PHP Client Library.
require_once __DIR__ . '/vendor/autoload.php';

$analytics = initializeAnalytics();
$response = getReport($analytics, $ranges, $request);
$datas = convertResults($response, $ranges[0]);
echo json_encode($datas, JSON_PRETTY_PRINT);

function initializeAnalytics()
{
  // Creates and returns the Analytics Reporting service object.

  // Use the developers console and download your service account
  // credentials in JSON format. Place them in this directory or
  // change the key file location if necessary.
  $KEY_FILE_LOCATION = __DIR__ . '/service-account-credentials.json';

  // Create and configure a new client object.
  $client = new Google_Client();
  $client->setApplicationName("Hello Analytics Reporting");
  $client->setAuthConfig($KEY_FILE_LOCATION);
  $client->setScopes(['https://www.googleapis.com/auth/analytics.readonly']);
  $analytics = new Google_Service_AnalyticsReporting($client);

  return $analytics;
}

function getReport($analytics, $ranges, $options)
{
  // default
  extract($options);
  
  // Replace with your view ID, for example XXXX.
  $VIEW_ID = '44262736';

  // Create the DateRange object.
  $g_dateRanges = [];
  foreach ($ranges as $range) {
    $g_dateRange = new Google_Service_AnalyticsReporting_DateRange();
    $g_dateRange->setStartDate(date('Y-m-d', $range[0]));
    $g_dateRange->setEndDate(date('Y-m-d', $range[1]));
    $g_dateRanges[] = $g_dateRange;
  }
  
  // Create the Metrics object.
  $g_metrics = [];
  if ($metrics != NULL) {
    foreach ($metrics as $value) {
      $g_metric = new Google_Service_AnalyticsReporting_Metric();
      $g_metric->setExpression($value);
      $g_metrics[] = $g_metric;
    }
  }
  
  // Create the Dimensions object.
  $g_dimensions = [];
  if ($dimensions != NULL) {
    foreach ($dimensions as $value) {
      $g_dimension = new Google_Service_AnalyticsReporting_Dimension();
      $g_dimension->setName($value);
      $g_dimensions[] = $g_dimension;
    }
  }
  
  // Create the ReportRequest object.
  $request = new Google_Service_AnalyticsReporting_ReportRequest();
  $request->setViewId($VIEW_ID);
  $request->setDateRanges($g_dateRanges);
  $request->setDimensions($g_dimensions);
  $request->setMetrics($g_metrics);

  $body = new Google_Service_AnalyticsReporting_GetReportsRequest();
  $body->setReportRequests(array($request));
  return $analytics->reports->batchGet($body);
}

function convertResults($reports, $range)
{
  $datas = new stdClass();
  $data_totals = [];
  $data_rows = [];
  
  foreach ($reports as $report) {
    $header = $report->getColumnHeader();
    $metric_headers = $header->getMetricHeader()->getMetricHeaderEntries();
    $entry_max = count($metric_headers);
    
    // totals
    foreach ($metric_headers as $i => $entry) {
      $data = new stdClass();
      $data->name = $entry->getName();
      $data->type = $entry->getType();
      $data->values = [];
      $data_totals[$i] = $data;
    }
    $totals = $report->getData()->getTotals();
    foreach ($totals as $i => $total) {
      $values = $total->getValues();
      foreach ($values as $j => $value) {
        if (isset($data_totals[$j])) {
          $data_totals[$j]->values[$i] = $value;
        }
      }
    }
    
    // rows
    $row_temps = [];
    $rows = $report->getData()->getRows();
    foreach ($rows as $row) {
      $temps = [];
      
      // dimensions
      $temps['dimensions'] = $row->getDimensions();
      
      // metrics
      $temps['metrics'] = [];
      $metrics = $row->getMetrics();
      foreach ($metrics as $i => $metric) {
        $values = $metric->getValues();
        foreach ($values as $j => $value) {
          if (isset($temps['metrics'][$j]) == NULL) {
            $temps['metrics'][$j] = [];
          }
          $temps['metrics'][$j][$i] = $value;
        }
      }
      $row_temps[] = $temps;
    }
    
    // dimensions
    $date_dimension_index = -1;
    $date_dimension = NULL;
    $dimension_headers = $header->getDimensions();
    foreach ($dimension_headers as $i => $dimension_header) {
      switch ($dimension_header) {
        case 'ga:nthDay':
        case 'ga:day':
        case 'ga:nthWeek':
        case 'ga:week':
        case 'ga:nthMonth':
        case 'ga:month':
          $date_dimension_index = $i;
          $date_dimension = $dimension_header;
          break 2;
        default:
          break;
      }
    }
    
    // marge
    if ($date_dimension != NULL) {
      $startDate = $range[0];
      $endDate = $range[1];
      
      $i = 0;
      while ($startDate <= $endDate) {
        $data = new stdClass();
        $data->dimensions = [];
        $data->metrics = [];
        $data->date1 = $startDate;
        $data->date2 = NULL;
        
        switch ($date_dimension) {
          case 'ga:nthDay':
          case 'ga:day':
            $startDate = strtotime('+1 day', $startDate);
            break;
          case 'ga:nthWeek':
          case 'ga:week':
            $startDate = strtotime('next Sunday', $startDate);
            $data->date2 = strtotime('-1 days', $startDate);
            break;
          case 'ga:nthMonth':
          case 'ga:month':
            $startDate = strtotime('next Month', $startDate);
            $data->date2 = strtotime('-1 days', $startDate);
            break;
          default:
            $startDate = $endDate + 1;
            break;
        }
        if (($data->date2 != NULL) && ($data->date2 > $endDate)) {
          $data->date2 = $endDate;
        }
        
        switch ($date_dimension) {
          case 'ga:nthDay':
          case 'ga:nthWeek':
          case 'ga:nthMonth':
            $dimension = sprintf('%04d', $i);
            break;
          case 'ga:day':
            $dimension = date('d', $data->date1);
            break;
          case 'ga:week':
            $dimension = date('W', $data->date2);
            break;
          case 'ga:month':
            $dimension = date('m', $data->date2);
            break;
          default:
            $dimension = $i;
            break;
        }
        
        $metrics_temps = NULL;
        foreach ($row_temps as $temps) {
          if (isset($temps['dimensions'][$date_dimension_index])) {
            $temp_dimension = $temps['dimensions'][$date_dimension_index];
            if ($temp_dimension == $dimension) {
              $data->dimensions = $temps['dimensions'];
              $metrics_temps = $temps['metrics'];
              break;
            }
          }
        }
        
        for ($j = 0; $j < $entry_max; $j++) {
          $entry = $metric_headers[$j];
          $name = $entry->getName();
          $metrics = new stdClass();
          $metrics->type = $entry->getType();
          $metrics->values = [0];
          
          if (($metrics_temps != NULL) && (isset($metrics_temps[$j]))){
            $metrics->values = $metrics_temps[$j];
          }
          $data->metrics[$name] = $metrics;
        }
        
        // append
        $data_rows[] = $data;
        $i++;
      }
    } else {
      
      // marge
      foreach ($row_temps as $temps) {
        $data = new stdClass();
        $data->dimensions = $temps['dimensions'];
        $data->metrics = [];
        
        foreach ($metric_headers as $entry) {
          $name = $entry->getName();
          $metrics = new stdClass();
          $metrics->type = $entry->getType();
/*
          $metrics_values = [];
          foreach ($temps['metrics'] as $m_i => $temp_values) {
            foreach ($temp_values as $v_i => $temp_value) {
              if (isset($dimension_headers[$v_i])) {
                $dimension_header = $dimension_headers[$v_i];
              }
              if (isset($metrics_values[$m_i]) == NULL) {
                $metrics_values[$m_i] = [];
              }
              $metrics_values[$m_i][$dimension_header] = $temp_value;
            }
          }
          $metrics->values = $metrics_values;
*/
          $metrics->values = $temps['metrics'];
          $data->metrics[$name] = $metrics;
        }
        
        // append
        $data_rows[] = $data;
      }
    }
    
    $datas->totals = $data_totals;
    $datas->rows = $data_rows;
  }
  
  return $datas;
}
