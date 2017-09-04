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
  'dimension' => 'ga:nthDay',
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
  $g_dimension = new Google_Service_AnalyticsReporting_Dimension();
  $g_dimension->setName($dimension);
  
  // Create the ReportRequest object.
  $request = new Google_Service_AnalyticsReporting_ReportRequest();
  $request->setViewId($VIEW_ID);
  $request->setDateRanges($g_dateRanges);
  $request->setDimensions(array($g_dimension));
  $request->setMetrics($g_metrics);

  $body = new Google_Service_AnalyticsReporting_GetReportsRequest();
  $body->setReportRequests(array($request));
  return $analytics->reports->batchGet($body);
}

function convertResults($reports, $ranges)
{
  $datas = [];
  
  foreach ($reports as $report) {
    $dimension_header = NULL;
    
    $header = $report->getColumnHeader();
    $metric_headers = $header->getMetricHeader()->getMetricHeaderEntries();
    $entry_max = count($metric_headers);
    
    // totals
    $totals = $report->getData()->getTotals();
    $max = count($totals);
    foreach ($totals as $i => $total) {
      $total_data = [];
      $values = $total->getValues();
      $v_max = min(count($values), $entry_max);
      for ($v_i = 0; $v_i < $v_max; $v_i++) {
        $entry = $metric_headers[$v_i];
        $data = new stdClass();
        $data->name = $entry->getName();
        $data->type = $entry->getType();
        $data->value = $values[$v_i];
        $total_data[] = $data;
      }
      
      // append
      if (isset($datas[$i]) == NULL) {
        $datas[$i] = new stdClass();
      }
      $datas[$i]->total = $total_data;
      $datas[$i]->rows = [];
    }
    
    // header
    $dimension_headers = $header->getDimensions();
    foreach ($dimension_headers as $dimension_header_) {
      $dimension_header = $dimension_header_;
      break;
    }
    
    // rows
    $row_temps = [];
    $rows = $report->getData()->getRows();
    foreach ($rows as $row) {
      
      // dimensions
      $dimension = NULL;
      $dimensions = $row->getDimensions();
      foreach ($dimensions as $d) {
        $dimension = $d;
        break;
      }
      
      if ($dimension == NULL) {
        continue;
      }
      
      // metrics
      $metrics = $row->getMetrics();
      foreach ($metrics as $i => $metric) {
        if (isset($row_temps[$i]) == NULL) {
          $row_temps[$i] = [];
        }
        if (isset($row_temps[$i][$dimension]) == NULL) {
          $row_temps[$i][$dimension] = [];
        }
        $row_temps[$i][$dimension] = $metric->getValues();
      }
    }
    
    // marge
    foreach ($ranges as $r_i => $range) {
      if ($range == NULL) {
        continue;
      }
      $startDate = $range[0];
      $endDate = $range[1];
      $row_datas = [];
      
      $i = 0;
      while ($startDate <= $endDate) {
        $data = new stdClass();
        $data->metrics = [];
        $data->date1 = $startDate;
        $data->date2 = NULL;
        
        switch ($dimension_header) {
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
        
        switch ($dimension_header) {
          case 'ga:nthDay':
          case 'ga:nthWeek':
          case 'ga:nthMonth':
            $data->name = sprintf('%04d', $i);
            break;
          case 'ga:day':
            $data->name = date('d', $data->date1);
            break;
          case 'ga:week':
            $data->name = date('W', $data->date2);
            break;
          case 'ga:month':
            $data->name = date('m', $data->date2);
            break;
          default:
            $data->name = $i;
            break;
        }
        
        $temps = NULL;
        if (isset($row_temps[$r_i]) && (isset($row_temps[$r_i][$data->name]))) {
          $temps = $row_temps[$r_i][$data->name];
        }
        
        for ($j = 0; $j < $entry_max; $j++) {
          $entry = $metric_headers[$j];
          $name = $entry->getName();
          $data->metrics[$name] = new stdClass();
          $data->metrics[$name]->type = $entry->getType();
          $data->metrics[$name]->value = 0;
          
          if (($temps != NULL) && (isset($temps[$j]))){
            $data->metrics[$name]->value = $temps[$j];
          }
        }
        
        // append
        $row_datas[] = $data;
        $i++;
      }
      
      // append
      $datas[$r_i]->rows = $row_datas;
    }
  }
  
  return $datas;
}

$datas = convertResults($response, $ranges);
echo json_encode($datas, JSON_PRETTY_PRINT);
