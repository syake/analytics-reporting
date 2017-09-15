<?php
$date = strtotime('-1 day');
$dateAges = [7, 28, 90];
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
<script src="https://code.jquery.com/jquery-3.2.1.min.js" integrity="sha256-hwg4gsxgFZhOsEEamdOYGBf13FyQuiTwlAQgxVSNgt4=" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/tether/1.4.0/js/tether.min.js" integrity="sha384-DztdAPBWPRXSA/3eYEEUWrWCy7G5KFbe8fFjk5JAIxUYHKkDx6Qin1DkWx51bBrb" crossorigin="anonymous"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-alpha.6/js/bootstrap.min.js" integrity="sha384-vBWWzlZJ8ea9aCX4pEW3rVHjgjt7zpkNpZk+02D9phzyeVkE+jo0ieGizqPLForn" crossorigin="anonymous"></script>
<script charset="utf-8" src="https://www.gstatic.com/charts/loader.js"></script>
<script charset="utf-8" src="./js/api.js"></script>
</head>
<body>
<?php require __DIR__ . '/_inc_header.php'; ?>
<main role="main">
  <div class="container">
    <div class="dashboard">
      <p class="address">http://syake-labo.com</p>
      <div id="rangeMenu" class="dropdown">
        期間：<a href="javascript:void(0)" class="dropdown-toggle" id="rangeLabel" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><?php $diff = ($dateAges[0] - 1) ?><?= date('Y/m/d', strtotime("-{$diff} day", $date)) ?> - <?= date('Y/m/d', $date) ?></a>
        <div class="dropdown-menu" aria-labelledby="rangeLabel">
<?php foreach ($dateAges as $i => $age): $diff = $age; ?>
          <button class="dropdown-item" data-start="<?= date('Y-m-d', strtotime("-{$diff} day", $date)) ?>" data-end="<?= date('Y-m-d', $date) ?>" data-diff="<?= $diff ?>" type="button">過去 <?= $age ?> 日前</button>
<?php endforeach; ?>
        </div>
      </div>
    </div><!-- /.dashboard -->
    
    <div class="dashboard">
      <div id="chart-1-container" class="report"></div>
    </div>
    <div class="row">
      <div class="col-sm-12">
        <div class="dashboard">
          <div id="chart-2-container" class="report"></div>
          <div id="chart-3-container" class="report"></div>
        </div>
      </div>
      <div class="col-sm-6">
        <div class="dashboard">
          <div id="chart-4-container" class="report"></div>
        </div>
      </div>
      <div class="col-sm-6">
        <div class="dashboard">
          <div id="chart-5-container" class="report"></div>
        </div>
      </div>
    </div>
    
  </div>
</main>
<?php require __DIR__ . '/_inc_footer.php'; ?>
</body>
</html>


