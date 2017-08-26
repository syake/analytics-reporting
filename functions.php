<?php
function _n($name)
{
  switch ($name) {
    case 'ga:users':
      $name = 'ユーザー';
      break;
    case 'ga:newUsers':
      $name = '新規ユーザー';
      break;
    case 'ga:percentNewSessions':
      $name = '新規セッション率';
      break;
    case 'ga:sessions':
      $name = 'セッション';
      break;
    case 'ga:bounceRate':
      $name = '直帰率';
      break;
    case 'ga:avgSessionDuration':
      $name = 'セッション継続時間';
      break;
    case 'ga:entrances':
      $name = '閲覧開始数';
      break;
    case 'ga:pageviews':
      $name = 'ページビュー';
      break;
    case 'ga:uniquePageviews':
      $name = 'ページ別訪問数';
      break;
    case 'ga:pageviewsPerSession':
      $name = 'ページ／セッション';
      break;
    case 'ga:avgTimeOnPage':
      $name = '平均ページ滞在時間';
      break;
    case 'ga:exitRate':
      $name = '離脱率';
      break;
    case 'ga:region':
      $name = '地域';
      break;
    case 'ga:city':
      $name = '市区町村';
      break;
    case 'ga:pagePath':
      $name = 'ページ';
      break;
    case 'ga:nthDay':
    case 'ga:day':
      $name = '日';
      break;
    case 'ga:nthWeek':
    case 'ga:week':
      $name = '週';
      break;
    case 'ga:nthMonth':
    case 'ga:month':
      $name = '月';
      break;
    default:
      break;
  }
  return $name;
}

function _v($value, $type)
{
  switch ($type) {
    case 'PERCENT':
      $value = number_format(round(floatval($value), 2), 2) . '%';
      break;
    case 'TIME':
      $value = date('i分s秒', floatval($value));
      $value = preg_replace('/^0/','',$value);
      break;
    case 'INTEGER':
      $value = intval($value);
      break;
    case 'FLOAT':
      $value = number_format(round(floatval($value), 2), 2);
      break;
    default:
      break;
  }
  return $value;
}
