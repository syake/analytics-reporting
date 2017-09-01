<?php
require_once __DIR__ . '/functions.php';

// config
$date = strtotime('-1 day');
$rangeDay = 6;
$rangeMonth = NULL;
$ageDay = NULL;
$dateRange = NULL;
$ageDateRange = NULL;
$metrics = ['ga:users', 'ga:sessions', 'ga:bounceRate', 'ga:avgSessionDuration', 'ga:pageviews'];

// default
$request = 'day';

// request
$request = NULL;
if (isset($_GET['request'])) {
  $request = $_GET['request'];
}
switch ($request) {
  case 'day':
    $dimension = 'ga:nthDay';
    $startDate = strtotime("-{$rangeDay} day", $date);
    $dateRange = [$startDate, $date];
    
    $ageDay = $rangeDay + 1;
    $ageStartDate = strtotime("-{$ageDay} day", $startDate);
    $ageEndDate = strtotime("-{$ageDay} day", $date);
    $ageDateRange = [$ageStartDate, $ageEndDate];
    break;
  case 'week':
    $rangeMonth = 3;
    $dimension = 'ga:nthWeek';
    $startDate = strtotime("last Monday -{$rangeMonth} month +1 day", $date);
    $dateRange = [$startDate, $date];
    break;
  case 'month':
    $rangeMonth = 12;
    $dimension = 'ga:nthMonth';
    $startDate = strtotime("first day of -{$rangeMonth} month", $date);
    $dateRange = [$startDate, $date];
    break;
  default:
    $dimension = NULL;
    break;
}
if ($dimension == NULL) {
  print('error');
  exit(0);
}

// Load the Google API PHP Client Library.
require_once __DIR__ . '/vendor/autoload.php';

$analytics = initializeAnalytics();

$options = [
  'startDate' => $dateRange[0],
  'endDate' => $dateRange[1],
  'dimensionValue' => $dimension,
  'metricValues' => $metrics
];
if ($ageDateRange != NULL) {
  $options['ageStartDate'] = $ageDateRange[0];
  $options['ageEndDate'] = $ageDateRange[1];
}
$response = getReport($analytics, $options);

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

function getReport($analytics, $options)
{
  extract($options = array_merge([
    'startDate' => '7daysago',
    'endDate' => '1daysago',
    'ageStartDate' => NULL,
    'ageEndDate' => NULL,
    'dimensionValue' => 'ga:nthDay',
    'metricValues' => []
  ], $options));
  
  // Replace with your view ID, for example XXXX.
  $VIEW_ID = '44262736';
/*   $VIEW_ID = '158197120'; */

  // Create the DateRange object.
  $dateRange = new Google_Service_AnalyticsReporting_DateRange();
  $dateRange->setStartDate(date('Y-m-d', $startDate));
  $dateRange->setEndDate(date('Y-m-d', $endDate));
  if (($ageStartDate != NULL) && ($ageEndDate != NULL)) {
    $dateRange2 = new Google_Service_AnalyticsReporting_DateRange();
    $dateRange2->setStartDate(date('Y-m-d', $ageStartDate));
    $dateRange2->setEndDate(date('Y-m-d', $ageEndDate));
    $dateRanges = array($dateRange, $dateRange2);
  } else {
    $dateRanges = array($dateRange);
  }
  
  // Create the Metrics object.
  $metrics = [];
  if ($metricValues != NULL) {
    foreach ($metricValues as $value) {
      $metric = new Google_Service_AnalyticsReporting_Metric();
      $metric->setExpression($value);
      $metrics[] = $metric;
    }
  }
  
  // Create the Dimensions object.
  $dimension = new Google_Service_AnalyticsReporting_Dimension();
  $dimension->setName($dimensionValue);
  
  // Create the ReportRequest object.
  $request = new Google_Service_AnalyticsReporting_ReportRequest();
  $request->setViewId($VIEW_ID);
  $request->setDateRanges($dateRanges);
  $request->setDimensions(array($dimension));
  $request->setMetrics($metrics);

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
$datas = convertResults($response, [$dateRange, $ageDateRange]);

$data = $datas[0];
$ageData = NULL;
if (isset($datas[1])) {
  $ageData = $datas[1];
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title></title>
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-alpha.6/css/bootstrap.min.css" integrity="sha384-rwoIResjU2yc3z8GV/NPeZWAv56rSmLldC3R/AZzGRnGxQQKnKkoFVhFQhNUwEyJ" crossorigin="anonymous">
<link href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet" integrity="sha384-wvfXpqpZZVQGK6TAh5PVlGOfQNHSoD2xbE+QkPxCAFlNEevoEH3Sl0sibVcOQVnN" crossorigin="anonymous">
<link rel="stylesheet" href="./css/style.css">
</head>
<body>
<?php require __DIR__ . '/_inc_header.php'; ?>
<main role="main">
  <div class="container">
    <div class="dashboard">
      <p class="address">http://syake-labo.com</p>
      <p>期間：<?= date('Y/m/d', $dateRange[0]) ?> - <?= date('Y/m/d', $dateRange[1]) ?><?php if ($ageDay != NULL) : ?><br><small><?= $ageDay ?>日前との比較</small><?php endif; ?></p>
    </div><!-- /.dashboard -->
    <section class="dashboard">
      <h2>Totals</h2>
      <table class="table">
        <tbody>
<?php foreach ($data->total as $i => $total) : ?>
          <tr>
            <th><?= _n($total->name) ?></th>
            <td><?= _v($total->value, $total->type) ?></td>
<?php if ($ageData != NULL) : ?>
            <td><?= _v($ageData->total[$i]->value, $ageData->total[$i]->type) ?></td>
<?php endif; ?>
          </tr>
<?php endforeach; ?>
        </tbody>
      </table>
    </section><!-- /.dashboard -->
    <div class="dashboard">
      <table class="table table-sm table-rows">
        <thead>
          <tr>
            <th></th>
            <th colspan="2"><?= _n($dimension) ?></th>
<?php foreach ($metrics as $name) : ?>
            <td><?= _n($name) ?></td>
<?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
<?php foreach ($data->rows as $i => $row) : ?>
          <tr>
            <th class="num"><?= ($i + 1) ?>.</th>
            <th><?= $row->name ?></th>
<?php if ($row->date2 != NULL) : ?>
            <th><?= date('Y/m/d', $row->date1) ?> - <?= date('m/d', $row->date2) ?></th>
<?php else: ?>
            <th><?= date('Y/m/d <\s\m\a\l\l>(D)</\s\m\a\l\l>', $row->date1) ?></th>
<?php endif; ?>
<?php foreach ($row->metrics as $name => $metric) : ?>
            <td>
              <?= _v($metric->value, $metric->type) ?>
<?php if ($ageData != NULL) : ?>
              <small>(<?= _v($ageData->rows[$i]->metrics[$name]->value, $ageData->rows[$i]->metrics[$name]->type) ?>)</small>
<?php endif; ?>
            </td>
<?php endforeach; ?>
          </tr>
<?php endforeach; ?>
        </tbody>
      </table>
    </div><!-- /.dashboard -->
  </div>
</main>
<?php require __DIR__ . '/_inc_footer.php'; ?>
</body>
</html>
