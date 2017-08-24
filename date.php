<?php
require_once __DIR__ . '/functions.php';

// config
$endDate = strtotime('-1 day');

// request
$request = NULL;
if (isset($_GET['request'])) {
  $request = $_GET['request'];
}
switch ($request) {
  case 'day':
    $dimensionValue = 'ga:nthDay';
    $ageTime = 7 * 24 * 60 * 60;
    $startDate = $endDate - (6 * 24 * 60 * 60);
    break;
  case 'week':
    $dimensionValue = 'ga:nthWeek';
    $ageTime = NULL;
    $startDate = strtotime('last Monday -6 week', $endDate);
    break;
  case 'month':
    $dimensionValue = 'ga:nthMonth';
    $ageTime = NULL;
    $startDate = strtotime('first day of -6 month', $endDate);
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
$response = getReport($analytics, [
  'startDate' => $startDate,
  'endDate' => $endDate,
  'ageTime' => $ageTime,
  'dimensionValue' => $dimensionValue
]);

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
    'startDate' => strtotime('-7 day'),
    'endDate' => strtotime('-1 day'),
    'ageTime' => NULL,
    'dimensionValue' => 'ga:nthDay'
  ], $options));
  
  // Replace with your view ID, for example XXXX.
  $VIEW_ID = '44262736';

  // Create the DateRange object.
  $dateRange = new Google_Service_AnalyticsReporting_DateRange();
  $dateRange->setStartDate(date('Y-m-d', $startDate));
  $dateRange->setEndDate(date('Y-m-d', $endDate));
  
  if ($ageTime != NULL) {
    $dateRange2 = new Google_Service_AnalyticsReporting_DateRange();
    $dateRange2->setStartDate(date('Y-m-d', ($startDate - $ageTime)));
    $dateRange2->setEndDate(date('Y-m-d', ($endDate - $ageTime)));
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

function convertResults($reports, $startDate, $endDate)
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
    $rows = $report->getData()->getRows();
    $max = count($rows);
    for ($i = 0; $i < $max; $i++) {
      $row = $rows[$i];
      
      $data = new stdClass();
      $data->metrics = [];
      
      // dimensions
      $dimensions = $row->getDimensions();
      $d_max = count($dimensions);
      for ($d_i = 0; $d_i < $d_max; $d_i++) {
        $name = $dimensions[$d_i];
        $data->name = $name;
        $data->date = $startDate;
        $data->date2 = NULL;
        
        switch ($data_header->dimension) {
          case 'ga:nthDay':
            $startDate += 24 * 60 * 60;
            break;
          case 'ga:nthWeek':
            $startDate = strtotime('next Monday', $startDate);
            $data->date2 = strtotime('-1 day', $startDate);
            break;
          case 'ga:nthMonth':
            $startDate = strtotime('first day of +1 month', $startDate);
            $data->date2 = strtotime('-1 day', $startDate);
            break;
          default:
            break;
        }
        
        if ($data->date2 != NULL) {
          if ($data->date2 > $endDate) {
            $data->date2 = $endDate;
          }
        }
        break;
      }
      
      // metrics
      $metrics = $row->getMetrics();
      $m_max = count($metrics);
      for ($m_i = 0; $m_i < $m_max; $m_i++) {
        $metric = $metrics[$m_i];
        $values = $metric->getValues();
        $v_max = min(count($values), $entry_max);
        for ($v_i = 0; $v_i < $v_max; $v_i++) {
          $entry = $metric_headers[$v_i];
          $name = $entry->getName();
          $value = $values[$v_i];
          
          if (!isset($data->metrics[$name])) {
            $data->metrics[$name] = [];
          }
          $data->metrics[$name][$m_i] = _v($value, $entry->getType());
        }
      }
      
      // append
      $data_rows[] = $data;
    }
  }
  
  $result = new stdClass();
  $result->total = $data_total;
  $result->header = $data_header;
  $result->rows = $data_rows;
  return $result;
}
$datas = convertResults($response, $startDate, $endDate);
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
      <p>期間：<?= date('Y/m/d', $startDate) ?> - <?= date('Y/m/d', $endDate) ?></p>
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
            <th><?= _n($datas->header->dimension) ?></th>
<?php foreach ($datas->header->metrics as $metric) : ?>
            <td><?= _n($metric) ?></td>
<?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
<?php $max = count($datas->rows); for ($i = 0; $i < $max; $i++) : $row = $datas->rows[$i]; ?>
          <tr>
            <th class="num"><?= ($i + 1) ?>.</th>
<?php if ($row->date2 != NULL) : ?>
            <th><?= date('Y/m/d', $row->date) ?> - <?= date('m/d', $row->date2) ?></th>
<?php else: ?>
            <th><?= date('Y/m/d <\s\m\a\l\l>(D)</\s\m\a\l\l>', $row->date) ?></th>
<?php endif; ?>
<?php foreach ($row->metrics as $name => $metric) : ?>
<?php if (count($metric) > 1) : ?>
            <td><?= $metric[0] ?> <small>(<?= $metric[1] ?>)</small></td>
<?php else: ?>
            <td><?= $metric[0] ?></td>
<?php endif; ?>
<?php endforeach; ?>
          </tr>
<?php endfor; ?>
        </tbody>
      </table>
    </div><!-- /.dashboard -->
  </div>
</main>
<?php require __DIR__ . '/_inc_footer.php'; ?>
</body>
</html>
