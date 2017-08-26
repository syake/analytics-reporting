<?php
require_once __DIR__ . '/functions.php';

// config
$date = strtotime('-1 day');
$rangeDay = 6;
$rangeMonth = NULL;
$ageDay = NULL;
$dateRange = NULL;
$ageDateRange = NULL;

// request
$request = NULL;
if (isset($_GET['request'])) {
  $request = $_GET['request'];
}
switch ($request) {
  case 'day':
    $dimensionValue = 'ga:nthDay';
    $startDate = strtotime("-{$rangeDay} day", $date);
    $dateRange = [$startDate, $date];
    
    $ageDay = $rangeDay + 1;
    $ageStartDate = strtotime("-{$ageDay} day", $startDate);
    $ageEndDate = strtotime("-{$ageDay} day", $date);
    $ageDateRange = [$ageStartDate, $ageEndDate];
    break;
  case 'week':
    $rangeMonth = 3;
    $dimensionValue = 'ga:nthWeek';
    $startDate = strtotime("last Monday -{$rangeMonth} month +1 day", $date);
    $dateRange = [$startDate, $date];
    break;
  case 'month':
    $rangeMonth = 12;
    $dimensionValue = 'ga:nthMonth';
    $startDate = strtotime("first day of -{$rangeMonth} month", $date);
    $dateRange = [$startDate, $date];
    break;
  default:
    $dimensionValue = NULL;
    break;
}
if ($dimensionValue == NULL) {
  print('error');
  exit(0);
}

// Load the Google API PHP Client Library.
require_once __DIR__ . '/vendor/autoload.php';

$analytics = initializeAnalytics();

$options = [
  'startDate' => $dateRange[0],
  'endDate' => $dateRange[1],
  'dimensionValue' => $dimensionValue
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
    'dimensionValue' => 'ga:nthDay'
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
  $metricValues = ['ga:users', 'ga:sessions', 'ga:bounceRate', 'ga:avgSessionDuration', 'ga:pageviews'];
  $metrics = [];
  foreach ($metricValues as $value) {
    $metric = new Google_Service_AnalyticsReporting_Metric();
    $metric->setExpression($value);
    $metrics[] = $metric;
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
  $data_total = [];
  $data_header = new stdClass();
  $data_header->metrics = [];
  $data_rows = [];
  
  foreach ($reports as $report) {
    $header = $report->getColumnHeader();
    $metric_headers = $header->getMetricHeader()->getMetricHeaderEntries();
    $entry_max = count($metric_headers);
    
    $dimension_headers = $header->getDimensions();
    $dimension_max = count($dimension_headers);
    
    // totals
    $totals = $report->getData()->getTotals();
    $max = count($totals);
    for ($i = 0; $i < $max; $i++) {
      $total = $totals[$i];
      
      $values = $total->getValues();
      $v_max = min(count($values), $entry_max);
      for ($v_i = 0; $v_i < $v_max; $v_i++) {
        $entry = $metric_headers[$v_i];
        $name = $entry->getName();
        $value = $values[$v_i];
        
        // append
        if (!isset($data_total[$name])) {
          $data_total[$name] = new stdClass();
          $data_total[$name]->values = [];
        }
        $data_total[$name]->values[] = _v($value, $entry->getType());
      }
    }
    
    // header
    for ($d_i = 0; $d_i < $dimension_max; $d_i++) {
      $key = $dimension_headers[$d_i];
      $data_header->dimension = $key;
      break;
    }
    for ($v_i = 0; $v_i < $entry_max; $v_i++) {
      $entry = $metric_headers[$v_i];
      $data_header->metrics[] = $entry->getName();
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
      $datas = [];
      
      $i = 0;
      while ($startDate <= $endDate) {
        $data = new stdClass();
        $data->metrics = [];
        $data->date1 = $startDate;
        $data->date2 = NULL;
        
        switch ($data_header->dimension) {
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
        
        switch ($data_header->dimension) {
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
        $datas[] = $data;
        $i++;
      }
      $data_rows[] = $datas;
    }
  }
  
  $result = new stdClass();
  $result->total = $data_total;
  $result->header = $data_header;
  $result->rows = $data_rows;
  return $result;
}

$datas = convertResults($response, [$dateRange, $ageDateRange]);
?>
<!doctype html>
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
<?php foreach ($datas->total as $name => $total) : ?>
          <tr>
            <th><?= _n($name) ?></th>
<?php foreach ($total->values as $value) : ?>
            <td><?= $value ?></td>
<?php endforeach; ?>
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
            <th colspan="2"><?= _n($datas->header->dimension) ?></th>
<?php foreach ($datas->header->metrics as $metric) : ?>
            <td><?= _n($metric) ?></td>
<?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
<?php foreach ($datas->rows[0] as $i => $data) : ?>
          <tr>
            <th class="num"><?= ($i + 1) ?>.</th>
            <th><?= $data->name ?></th>
<?php if ($data->date2 != NULL) : ?>
            <th><?= date('Y/m/d', $data->date1) ?> - <?= date('m/d', $data->date2) ?></th>
<?php else: ?>
            <th><?= date('Y/m/d <\s\m\a\l\l>(D)</\s\m\a\l\l>', $data->date1) ?></th>
<?php endif; ?>
<?php foreach ($data->metrics as $name => $metric) : ?>
            <td>
              <?= _v($metric->value, $metric->type) ?>
<?php if (isset($datas->rows[1])) : ?>
              <small>(<?= _v($datas->rows[1][$i]->metrics[$name]->value, $datas->rows[1][$i]->metrics[$name]->type) ?>)</small>
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
