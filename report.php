<?php
require_once __DIR__ . '/functions.php';

// request
$request = NULL;
if (isset($_GET['request'])) {
  $request = $_GET['request'];
}
switch ($request) {
  case 'region':
    $dimensionValue = 'ga:region';
    $metricValues = ['ga:sessions', 'ga:percentNewSessions', 'ga:newUsers', 'ga:bounceRate', 'ga:pageviewsPerSession', 'ga:avgSessionDuration'];
    break;
  case 'page':
    $dimensionValue = 'ga:pagePath';
    $metricValues = ['ga:pageviews', 'ga:uniquePageviews', 'ga:avgTimeOnPage', 'ga:entrances', 'ga:bounceRate', 'ga:exitRate'];
    break;
  default:
    $dimensionValue = NULL;
    $metricValues = [];
    break;
}
if ($dimensionValue == NULL) {
  print('error');
  exit(0);
}

// config
$orderby = getOrderby($metricValues);
$sortAscending = getSortAscending();
$endDate = strtotime('-1 day');
$startDate = $endDate - 6 * 24 * 60 * 60;

// Load the Google API PHP Client Library.
require_once __DIR__ . '/vendor/autoload.php';

$analytics = initializeAnalytics();
$response = getReport($analytics, [
  'orderby' => $orderby,
  'sortAscending' => $sortAscending,
  'startDate' => date('Y-m-d', $startDate),
  'endDate' => date('Y-m-d', $endDate),
  'metricValues' => $metricValues,
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
    'orderby' => 'ga:sessions',
    'sortAscending' => 0,
    'startDate' => '7daysago',
    'endDate' => '1daysago',
    'metricValues' => ['ga:sessions'],
    'dimensionValue' => NULL
  ], $options));
  
  // Replace with your view ID, for example XXXX.
  $VIEW_ID = '44262736';

  // Create the DateRange object.
  $dateRange = new Google_Service_AnalyticsReporting_DateRange();
  $dateRange->setStartDate($startDate);
  $dateRange->setEndDate($endDate);

  // Create the Metrics object.
  $metrics = [];
  foreach ($metricValues as $value) {
    $metric = new Google_Service_AnalyticsReporting_Metric();
    $metric->setExpression($value);
    $metrics[] = $metric;
  }
  
  // Create the Dimensions object.
  $dimension = new Google_Service_AnalyticsReporting_Dimension();
  $dimension->setName($dimensionValue);
  
  // Create the Ordering.
  $ordering = new Google_Service_AnalyticsReporting_OrderBy();
  $ordering->setFieldName($orderby);
  $ordering->setOrderType('VALUE');
  if ($sortAscending == 1) {
    $ordering->setSortOrder('ASCENDING');
  } else {
    $ordering->setSortOrder('DESCENDING');
  }
  
  // Create the ReportRequest object.
  $request = new Google_Service_AnalyticsReporting_ReportRequest();
  $request->setViewId($VIEW_ID);
  $request->setDateRanges(array($dateRange));
  $request->setDimensions(array($dimension));
  $request->setMetrics($metrics);
  $request->setOrderBys($ordering);

  $body = new Google_Service_AnalyticsReporting_GetReportsRequest();
  $body->setReportRequests(array($request));
  return $analytics->reports->batchGet($body);
}

function getOrderby($metricValues)
{
  $value = NULL;
  if (isset($_GET['orderby'])) {
    $value = $_GET['orderby'];
  }
  if (!in_array($value, $metricValues)) {
    $value = $metricValues[0];
  }
  return $value;
}

function getSortAscending()
{
  $value = 0;
  if (isset($_GET['sortAscending'])) {
    $value = $_GET['sortAscending'];
  }
  return $value;
}

function convertResults($reports)
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
        $data_total[$name] = _v($value, $entry->getType());
      }
      break;
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
          $data->metrics[$name] = _v($value, $entry->getType());
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
$datas = convertResults($response);
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
      <p>期間：<?= date('Y/m/d', $startDate) ?> - <?= date('Y/m/d', $endDate) ?></p>
    </div><!-- /.dashboard -->
    <div class="dashboard">
      <table class="table table-sm table-rows">
        <thead>
          <tr>
            <th></th>
            <th><?= _n($datas->header->dimension) ?></th>
<?php foreach ($datas->header->metrics as $metric) : ?>
<?php if ($orderby == $metric) : ?>
<?php if ($sortAscending == 1) : ?>
            <td><a href="?orderby=<?= $metric ?>"><?= _n($metric) ?></a> <i class="fa fa-sort-asc" aria-hidden="true"></i></td>
<?php else: ?>
            <td><a href="?orderby=<?= $metric ?>&sortAscending=1"><?= _n($metric) ?></a> <i class="fa fa-sort-desc" aria-hidden="true"></i></td>
<?php endif; ?>
<?php else: ?>
            <td><a href="?orderby=<?= $metric ?>"><?= _n($metric) ?></a></td>
<?php endif; ?>
<?php endforeach; ?>
          </tr>
          <tr>
            <th colspan="2"></th>
<?php foreach ($datas->total as $name => $value) : ?>
            <td class="total"><?= $value ?></td>
<?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
<?php $max = count($datas->rows); for ($i = 0; $i < $max; $i++) : $row = $datas->rows[$i]; ?>
          <tr>
            <th class="num"><?= ($i + 1) ?>.</th>
            <th><?= $row->name ?></th>
<?php foreach ($row->metrics as $name => $metric) : ?>
            <td><?= $metric ?></td>
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
