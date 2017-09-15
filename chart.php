<?php
error_reporting(-1);
ini_set('display_errors', 'On');
header('Content-Type: image/svg+xml; charset=UTF-8'); // We'll be outputting a SVG
require '../includes/db.php';
require '../includes/class.BuildingOS.php';
require '../includes/class.TimeSeries.php';
require 'includes/vars.php'; // Including in seperate file to keep this file clean. Contains $from, $to, etc
require 'includes/really-long-switch.php';
?>
<svg height="<?php echo $height; ?>" width="<?php echo $width; ?>" viewBox="0 0 <?php echo $width; ?> <?php echo $height; ?>" class="chart" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
<?php
if (isset($_GET['timeseriesconfig'])) {
  $stmt = $db->prepare('SELECT * FROM time_series_configs WHERE id = ?');
  $stmt->execute(array($_GET['timeseriesconfig']));
  $timeseriesconfigs = $stmt->fetch();
  foreach ($timeseriesconfigs as $key => $value) {
    $_GET[$key] = $value;
  }
}
$bos = new BuildingOS($db, $db->query("SELECT id FROM api WHERE user_id = {$user_id}")->fetchColumn());
$main_ts = new TimeSeries($db, $_GET['meter_id'], $from, $now, $res); // The main timeseries
try {
  $main_ts->data();
} catch (Exception $e) {
  $main_ts->data(use_api($db, $bos, $_GET['meter_id'], $res, $from, $now));
  $log[] = 'used api for main data';
}
/*
echo "<!--";
echo "SELECT value, recorded FROM meter_data
      WHERE meter_id = {$_GET['meter_id']} AND resolution = '{$res}' AND recorded > {$from} AND recorded < {$now} ORDER BY recorded ASC\n";
// echo (date('l n\/j \| g:i a',$from));
// echo (date('l n\/j \| g:i a',$to));
// print_r($main_ts->data);
echo "-->";
*/
if (!isset($_GET['meter_id2'])) {
  $_GET['meter_id2'] = $_GET['meter_id'];
}
$secondary_ts_set = ($_GET['meter_id'] !== $_GET['meter_id2']);
if ($secondary_ts_set) {
  $secondary_ts = new TimeSeries($db, $_GET['meter_id2'], $from, $now, $res); // "Second variable" timeseries
  try {
    $secondary_ts->data();
  } catch (Exception $e) {
    $log[] = 'used api for second variable';
    $secondary_ts->data(use_api($db, $bos, $_GET['meter_id2'], $res, $from, $now));
  }
} else {
  $secondary_ts = null;
}
$historical_ts = new TimeSeries($db, $_GET['meter_id'], $double_time, $from, $res); // Historical data of main
try {
  $historical_ts->data();
} catch (Exception $e) {
  $log[] = 'used api for historical chart';
  $historical_ts->data(use_api($db, $bos, $_GET['meter_id'], $res, $double_time, $from));
}
$meter = new Meter($db);
$typical_time_frame = ($time_frame === 'today' || $time_frame === 'week');

if ($typical_time_frame) {
  // See if a configuration for the relative data exists in the db, and if not, have a default
  $stmt = $db->prepare('SELECT relative_values.grouping FROM relative_values INNER JOIN meters ON meters.id = ? LIMIT 1');
  $stmt->execute(array($_GET['meter_id']));
  $json = $stmt->fetchColumn();
  if (strlen($json) > 0) {
    $json = json_decode($json, true);
  } else {
    $json = json_decode('[{"days":[1,2,3,4,5],"npoints":8},{"days":[1,7],"npoints":5}]', true);
  }
  $day_of_week = date('w') + 1;
  foreach ($json as $grouping) {
    if (in_array($day_of_week, $grouping['days'])) {
      $days = $grouping['days']; // The array that has the current day in it
      $npoints = (array_key_exists('npoints', $grouping) ? $grouping['npoints'] : 5); // you can only use npoints, not start
      break;
    }
  }
  $result = array();
  $recorded_vals = $from;
  $last_data = null;
  if ($time_frame === 'today') { // Get the typical data for today
    $stmt = $db->prepare(
    'SELECT value, recorded FROM meter_data
    WHERE meter_id = ? AND value IS NOT NULL AND resolution = ?
    AND DAYOFWEEK(FROM_UNIXTIME(recorded)) IN ('.implode(',', $days).')
    ORDER BY recorded DESC LIMIT ' . intval($npoints*24));
    $stmt->execute(array($_GET['meter_id'], 'hour'));
    $typical_data = $stmt->fetchAll();
    $sec = $from;
    $result = array();
    while ($sec <= $to) {
      $array_val = array();
      foreach ($typical_data as $row) {
        if (date('G', $sec) === date('G', $row['recorded'])) {
          $array_val[] = $row['value'];
        }
      }
      $result[] = array('recorded' => $sec, 'value' => median($array_val));
      $sec += 3600;
    }
  } else { // week
    $stmt = $db->prepare(
    'SELECT value, recorded FROM meter_data
    WHERE meter_id = ? AND value IS NOT NULL AND resolution = ?
    ORDER BY DAYOFWEEK(FROM_UNIXTIME(recorded)) ASC');
    $stmt->execute(array($_GET['meter_id'], 'hour'));
    $typical_data = $stmt->fetchAll();
    $sec = $from;
    $result = array();
    while ($sec <= $to) {
      $array_val = array();
      foreach ($typical_data as $row) {
        // if it's the same day of week & hour of day
        if (date('G w', $sec) === date('G w', $row['recorded'])) {
          $array_val[] = $row['value'];
        }
      }
      $result[] = array('recorded' => $sec, 'value' => median($array_val));
      $sec += 3600;
    }
  }
  echo "<!--";
  print_r($result);
  $typical_ts = new TimeSeries($db, $_GET['meter_id'], $from, $now, $res, null, null, $result);
  $typical_ts->dashed(false);
  $typical_ts->fill(false);
  $typical_ts->color('#f39c12');
  if (empty($typical_ts->data)) {
    $typical_ts = $historical_ts;
    $typical_time_frame = false;
  }
  var_dump(empty($typical_ts->data));
  echo "-->";
}

$main_ts->dashed( (isset($_GET['dasharr1']) && $_GET['dasharr1'] === 'on') ? true : false );
$historical_ts->dashed( (isset($_GET['dasharr2']) && $_GET['dasharr2'] === 'on') ? true : false );
$main_ts->fill( (isset($_GET['fill1']) && $_GET['fill1'] === 'off') ? false : true );
$historical_ts->fill( (isset($_GET['fill2']) && $_GET['fill2'] === 'off') ? false : true );
$current_graph_color = (isset($_GET['color1']) && strlen($_GET['color1']) > 0) ? $_GET['color1'] : '#2ecc71';
$historical_graph_color = (isset($_GET['color2']) && strlen($_GET['color1']) > 0) ? $_GET['color2'] : '#bdc3c7';
$main_ts->color($current_graph_color);
$historical_ts->color($historical_graph_color);
if ($secondary_ts_set) {
  $var2_graph_color = (isset($_GET['color3']) && strlen($_GET['color1']) > 0) ? $_GET['color3'] : '#33A7FF';
  $secondary_ts->color($var2_graph_color);
  $secondary_ts->fill( (isset($_GET['fill3']) && $_GET['fill3'] === 'off') ? false : true );
  $secondary_ts->dashed( (isset($_GET['dasharr3']) && $_GET['dasharr3'] === 'on') ? true : false );
  $secondary_ts->setMin(); $secondary_ts->setMax();
  $secondary_ts->setUnits();
  $secondary_ts->setMin(); $secondary_ts->setMax();
}

$main_ts->setMin(); $main_ts->setMax();
$historical_ts->setMin(); $historical_ts->setMax();
// If the units of both timeseries are the same, scale the charts to each other
$main_ts->setUnits();
if ($secondary_ts_set && ($secondary_ts->units === $main_ts->units && $main_ts->units !== null)) {
  // echo "<!-- Scaling primary + secondary together -->";
  $min = min($main_ts->min, $secondary_ts->min, $historical_ts->min);
  $max = max($main_ts->max, $secondary_ts->max, $historical_ts->max);
  $main_ts->setMin($min); $main_ts->setMax($max);
  $historical_ts->setMin($min); $historical_ts->setMax($max);
  $secondary_ts->setMin($min); $secondary_ts->setMax($max);
}
// If the units are not equal only scale the primary and historical charts to eachother
else {
  // echo "<!-- Scaling primary + secondary seperately -->";
  $min = min($main_ts->min, $historical_ts->min);
  $max = max($main_ts->max, $historical_ts->max);
  $main_ts->setMin($min); $main_ts->setMax($max);
  $historical_ts->setMin($min); $historical_ts->setMax($max);
}
if ($typical_time_frame) {
  $typical_ts->setMin($min);
  $typical_ts->setMax($max);
}
$name1 = $main_ts->getName();
if ($secondary_ts_set) {
  $secondary_ts->yAxis();
  $name2 = $secondary_ts->getName();
  $name = $name1 . 'vs. ' . $name2;
} else {
  $name = $name1;
}
$main_ts->yAxis();
$historical_ts->yAxis();
$main_ts->setTimes();
if ($main_ts->units === 'Kilowatts') {
  $charachter = 'squirrel';
} else if ($main_ts->units === 'Gallons / hour' || $main_ts->units === 'Liters / hour' || $main_ts->units === 'Liters' || $main_ts->units === 'Milligrams per liter' || $main_ts->units === 'Gallons per minute') {
  $charachter = 'fish';
} else {
  $charachter = 'both';
}
// URLs for buttons on bottom
$curr_url = "$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
parse_str(parse_url($curr_url, PHP_URL_QUERY), $tmp);
if (!isset($tmp['time'])) { // todo: fix
  $tmp['time'] = 'today';
}
$url1h = str_replace('time=' . rawurlencode($tmp['time']), 'time=live', $curr_url);
$url1d = str_replace('time=' . rawurlencode($tmp['time']), 'time=today', $curr_url);
$url1w = str_replace('time=' . rawurlencode($tmp['time']), 'time=week', $curr_url);
$url1m = str_replace('time=' . rawurlencode($tmp['time']), 'time=month', $curr_url);
$url1y = str_replace('time=' . rawurlencode($tmp['time']), 'time=year', $curr_url);

$show_hist = false;
if ($time_frame !== 'today' && $time_frame !== 'week') {
  $show_hist = true;
}

function median($arr) {
  if (empty($arr)) {
    return 0;
  }
  $count = count($arr);
  $mid = floor(($count-1)/2);
  if ($count % 2) {
    $median = $arr[$mid];
  } else {
    $low = $arr[$mid];
    $high = $arr[$mid+1];
    $median = (($low+$high)/2);
  }
  return $median;
}
function use_api($db, $bos, $meter_id, $res, $start, $end) {
  $api_resp = json_decode(
    $bos->getMeter($db->query("SELECT url FROM meters WHERE id = {$meter_id}")->fetchColumn() . '/data', $res, $start, $end), true)['data'];
  return array_map(function($tag) {
    return array(
        'value' => $tag['value'],
        'recorded' => $tag['localtime']
    );
  }, $api_resp);
}
?>
<defs>
  <linearGradient id="shadow">
    <stop class="stop1" stop-color="#eee" offset="0%"/>
    <stop class="stop2" stop-color="#eee" offset="100%"/>
  </linearGradient>
  <linearGradient id="shadow2" x1="0%" y1="0%" x2="0%" y2="100%">
    <stop offset="0%" style="stop-color:#fff;stop-opacity:1" />
    <stop offset="100%" style="stop-color:#777;stop-opacity:1" />
  </linearGradient>
  <filter x="0" y="0" width="1" height="1" id="solid-active">
    <feFlood flood-color="#2196F3"/>
    <feComposite in="SourceGraphic"/>
  </filter>
  <filter x="0" y="0" width="1" height="1" id="solid">
    <feFlood flood-color="<?php echo $font_color ?>"/>
    <feComposite in="SourceGraphic"/>
  </filter>
</defs>
<style>
/* <![CDATA[ */
@import url(https://fonts.googleapis.com/css?family=Roboto:400,700);
@keyframes anim {
  0% { width: 100%; }
  100% { width: 0%; }
}
.anim { animation: anim 1s cubic-bezier(.17,.67,.83,.67) 1; animation-fill-mode: forwards; }
@keyframes slide {
  from { transform: translateX(0);}
  to { transform: translateX(260px); }
}
/*.slide { animation: slide 350ms ease-in-out 1; animation-fill-mode: forwards; }
@keyframes slide-back {
  from { transform: translateX(260px);}
  to { transform: translateX(0px); }
}
.slide-back { animation: slide-back 350ms ease-in-out 1; animation-fill-mode: forwards; }*/
.noselect {
  -webkit-touch-callout: none;
  -webkit-user-select: none;
  -moz-user-select: none;
  -ms-user-select: none;
  user-select: none;
}
text {
  font-family: <?php echo $font_family; ?>;
  font-weight: 400;
}
#current-value {
  font-weight: 700;
}
#kwh, #kwh-active, #co2, #co2-active, #money, #money-active
#gal, #gal-active, #money2, #money2-active {
  cursor: pointer;
}
.stop1 { stop-color: #424242; }
.stop2 { stop-color: #424242; stop-opacity: 0; }
/* ]]> */
</style>
  <rect x="0" y="0" width="100%" height="100%" fill="<?php echo $primary_color; ?>"/>

  <!-- Historical data -->
  <g id="historical-chart" <?php echo ($show_hist) ? '' : 'style="opacity: 0;"'; ?>>
    <?php $historical_ts->printChart($graph_height, $graph_width, $graph_offset, $historical_ts->yaxis_min, $historical_ts->yaxis_max); ?>
    <circle cx="-10" cy="-100" id="historical-circle" <?php echo $circle_size . $historical_graph_color . '"'; ?> />
  </g>

  <!-- Typical chart -->
  <g id="typical-chart" style="opacity: 1;">
    <?php if ($typical_time_frame) {$typical_ts->printChart($graph_height, $graph_width, $graph_offset, $historical_ts->yaxis_min, $historical_ts->yaxis_max);} ?>
    <circle cx="-10" cy="-30" id="typical-circle" <?php echo $circle_size . '#f39c12' . '"'; ?> />
  </g>

  <!-- Second variable overlay -->
  <g id="second-chart" style="opacity: 0;">
    <?php if ($secondary_ts_set) {$secondary_ts->printChart($graph_height, $graph_width * $pct_through, $graph_offset, $secondary_ts->yaxis_min, $secondary_ts->yaxis_max);} ?>
  </g>

  <!-- Current data -->
  <g id="chart">
    <?php $main_ts->printChart($graph_height, $graph_width * $pct_through, $graph_offset, $main_ts->yaxis_min, $main_ts->yaxis_max); ?>
    <circle cx="-10" cy="0" id="current-circle" <?php echo $circle_size . $current_graph_color . '"'; ?> />
    <!-- <polygon fill='#000' fill-opacity='0.25' points='0,0 0,250 800,250 800,0' /> -->
  </g>

  <rect width="<?php echo $graph_width; ?>px" height="<?php echo $height - ($height * 0.075); ?>px" x="0" y="<?php echo $height * 0.075; ?>" style="fill:<?php echo $primary_color; ?>;" id="curtain"/><!-- For curtain animation -->

  <?php if (isset($_GET['ticks']) && $_GET['ticks'] === 'on') { ?>
  <line x1="<?php echo $chart_padding ?>" y1="<?php echo $main_ts->baseload ?>" x2="<?php echo $graph_width - $chart_padding ?>" y2="<?php echo $main_ts->baseload ?>" stroke-width="1" stroke="<?php echo $font_color ?>" stroke-dasharray="10,5"/>
  <line x1="<?php echo $chart_padding ?>" y1="<?php echo $main_ts->peak ?>" x2="<?php echo $graph_width - $chart_padding ?>" y2="<?php echo $main_ts->peak ?>" stroke-width="1" stroke="<?php echo $font_color ?>" stroke-dasharray="10,5"/>
  <?php } ?>

  <!-- padding at bottom of chart -->
  <rect width="<?php echo $graph_width ?>" height="30px" x="0" y="<?php echo $height * 0.88; ?>" style="fill:#fff;" />
  <!-- side columns -->
  <rect width="40px" height="100%" x="0" y="0" style="fill:#eee;" />
  <rect width="40px" height="100%" x="<?php echo $graph_width-40 ?>" y="0" style="fill:#eee;" />
  <g id="y-axis-left" text-anchor="start">
    <?php
    $chart_min = $graph_offset;
    $chart_max = $graph_height + $graph_offset;
    $interval = ($chart_max - $chart_min)/count($main_ts->yaxis);
    foreach ($main_ts->yaxis as $y) {
      echo "<text x='38' text-anchor='end' y='{$chart_max}' font-size='13' fill='{$font_color}'>{$y}</text>";
      $chart_max -= $interval;
    }
    ?>
    <text x="-170" y="44%" transform="translate(0)rotate(-90 10 175)" font-size="11" fill="<?php echo $font_color; ?>"><?php echo $main_ts->units; ?></text>
  </g>
  <?php if (isset($secondary_ts->yaxis)) { ?>
  <g id="y-axis-right" text-anchor="end" style="opacity: 0">
    <?php
    $chart_min = $graph_offset;
    $chart_max = $graph_height + $graph_offset;
    $interval = ($chart_max - $chart_min)/count($secondary_ts->yaxis);
    foreach ($secondary_ts->yaxis as $y) {
      echo "<text x='708' y='{$chart_max}' font-size='13' fill='{$font_color}' text-anchor='start'>{$y}</text>";
      $chart_max -= $interval;
    }
    ?>
    <text x="-110" y="905" transform="translate(0)rotate(-90 10 175)" font-size="11" fill="<?php echo $font_color; ?>"><?php echo $secondary_ts->units; ?></text>
  </g>
  <?php } ?>

  <!-- Dates at bottom -->
  <?php echo $dates; ?>

  <!-- Current time -->
  <rect width="<?php echo $width * 0.1; ?>px" height="<?php echo $height * 0.06; ?>px" x="-9999" y="<?php echo $height * 0.878//0.075; ?>" style="fill:<?php echo $font_color; ?>;" id="current-time-rect" />
  <text fill="<?php echo $primary_color; ?>" id="current-time-text" text-anchor="middle"
        x="-9999" y="<?php echo $height * 0.91//0.115; ?>"
        font-size="12"></text>

  <!-- Sidebar -->
  <rect width="<?php echo $width - $graph_width; ?>px" height="<?php echo $height; ?>px" x="<?php echo $graph_width ?>" y="0" style="fill:<?php echo $primary_color; ?>;" />
  <text id="suggestion" text-anchor="middle" x="<?php echo $graph_width + (($width - $graph_width)/2) ?>" y="200" font-size="18" width="<?php echo $width - ($graph_width+20); ?>px">Move your mouse over the data</text>
  <!-- <image display="none" id="error" xlink:href='images/error.svg' height="120px" width="150px" y="50" x="<?php //echo $graph_width + (($width - $graph_width)/4) ?>" /> -->
  <text text-anchor="middle" x="<?php echo $graph_width + (($width - $graph_width)/2) ?>" y="200" font-size="15" width="<?php echo $width - ($graph_width+20); ?>px" display="none" id="error-msg">Data are not available for this point</text>
  <!-- put animation markup here -->
  <g id="kwh-animation" style="display: none">
    <rect width='500px' height="500px" x="745" y="40" fill="#B4E3F4"/>
    <g id="ground">
      <image overflow="visible" enable-background="new    " width="500" height="500" xlink:href="https://oberlindashboard.org/oberlin/cwd/img/ground.svg" x="745" y="40">
      </image>
    </g> 
    <g id="power-lines"> 
      <image overflow="visible" enable-background="new    " width="300" height="200" xlink:href="https://oberlindashboard.org/oberlin/cwd/img/powerlines2.svg" x="760" y="180">
      </image>
    </g>
  </g>
  <g id="co2-animation" style="display: none">
    <rect width='500px' height="500px" x="745" y="40" fill="#B4E3F4"/>
    <g id="ground">
      <image overflow="visible" enable-background="new    " width="500" height="500" xlink:href="https://oberlindashboard.org/oberlin/cwd/img/ground.svg" x="745" y="40">
      </image>
    </g>  
    <g id="building">
      <image xmlns="http://www.w3.org/2000/svg" style="cursor:pointer" overflow="visible" enable-background="new    " width="180" height="90" id="industry" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="https://oberlindashboard.org/oberlin/cwd/img/power_plant.png" x="780" y="275">
      </image>
    </g>
    <g id="pipes">
      <image overflow="visible" enable-background="new    " width="130" height="130" xlink:href="https://oberlindashboard.org/oberlin/cwd/img/smokestack/smokestack1.png" transform="matrix(1 0 0 1 107 161)" x="690" y="15">
      </image>
      <image overflow="visible" enable-background="new    " width="130" height="130" xlink:href="https://oberlindashboard.org/oberlin/cwd/img/smokestack/smokestack1.png" transform="matrix(1 0 0 1 140 161)" x="685" y="15">
      </image>
      <image overflow="visible" enable-background="new    " width="130" height="130" xlink:href="https://oberlindashboard.org/oberlin/cwd/img/smokestack/smokestack1.png" transform="matrix(1 0 0 1 174 161)" x="680" y="15">
      </image>
    </g>
    <g id="smoke">
      <image overflow="visible" enable-background="new    " width="80" height="21" xlink:href="https://oberlindashboard.org/oberlin/cwd/img/smoke.png" x="770" y="210">
      </image>
      <image overflow="visible" enable-background="new    " width="80" height="21" xlink:href="https://oberlindashboard.org/oberlin/cwd/img/smoke.png" x="805" y="210">
      </image>
      <image overflow="visible" enable-background="new    " width="80" height="21" xlink:href="https://oberlindashboard.org/oberlin/cwd/img/smoke.png" x="840" y="210">
      </image>
    </g>
  </g>
  <g id="money-animation" style="display: none">
    <rect width='500px' height="500px" x="745" y="40" fill="#B4E3F4"/>
    <g id="ground">
      <image overflow="visible" enable-background="new    " width="500" height="500" xlink:href="https://oberlindashboard.org/oberlin/cwd/img/ground.svg" x="745" y="40">
      </image>
    </g>  
    <g id="tree"> 
      <image overflow="visible" enable-background="new    " width="250" height="300" xlink:href="https://oberlindashboard.org/oberlin/cwd/img/tree.svg" x="750" y="50">
      </image>
    </g>
    <g id="banknote"> 
      <image overflow="visible" enable-background="new    " width="20" height="20" xlink:href="https://oberlindashboard.org/oberlin/cwd/img/banknote.svg" x="790" y="130">
      </image>
      <image overflow="visible" enable-background="new    " width="20" height="20" xlink:href="https://oberlindashboard.org/oberlin/cwd/img/banknote.svg" x="770" y="180">
      </image>
      <image overflow="visible" enable-background="new    " width="20" height="20" xlink:href="https://oberlindashboard.org/oberlin/cwd/img/banknote.svg" x="830" y="130">
      </image>
       <image overflow="visible" enable-background="new    " width="20" height="20" xlink:href="https://oberlindashboard.org/oberlin/cwd/img/banknote.svg" x="900" y="100">
      </image>
      <image overflow="visible" enable-background="new    " width="20" height="20" xlink:href="https://oberlindashboard.org/oberlin/cwd/img/banknote.svg" x="970" y="170">
      </image>
       <image overflow="visible" enable-background="new    " width="20" height="20" xlink:href="https://oberlindashboard.org/oberlin/cwd/img/banknote.svg" x="950" y="110">
      </image>
    </g>
  </g>
  <g id="kwh-animation" style="display: none">
    <rect width='100px' height="100px" x="800" y="100" fill="red" id="test"/>
  </g>
  <g id="empathetic-char">
    <?php
    if ($main_ts->units === 'Gallons / hour' || $main_ts->units === 'Liters / hour' || $main_ts->units === 'Liters') {
      $number_of_frames = 49;
      for ($i = 0; $i <= $number_of_frames; $i++) { 
        echo "<image id='frame_{$i}' xlink:href='images/second_frames/frame_{$i}.gif' height='100%' width='";
        echo $width - $graph_width . "px' x='";
        echo $graph_width . "' ";
        // $im = imagecreatefromgif("images/second_frames/frame_{$i}.gif");
        // $rgb = imagecolorat($im, 1, 1);
        // $rgb = imagecolorsforindex($im, $rgb);
        // echo "data-color='rgb({$rgb['red']},{$rgb['green']},{$rgb['blue']})' ";
        echo ($i !== 0) ? 'display="none"' : '';
        echo ' y="0" />';
      }
    } else {
      $number_of_frames = 46;
      for ($i = 0; $i <= $number_of_frames; $i++) { 
        echo "<image id='frame_{$i}' xlink:href='images/main_frames/frame_{$i}.gif' height='100%' width='";
        echo $width - $graph_width . "px' x='";
        echo $graph_width . "' ";
        // $im = imagecreatefromgif("images/main_frames/frame_{$i}.gif");
        // $rgb = imagecolorat($im, 1, 1);
        // $rgb = imagecolorsforindex($im, $rgb);
        // echo "data-color='rgb({$rgb['red']},{$rgb['green']},{$rgb['blue']})' ";
        echo ($i !== 0) ? 'display="none"' : '';
        echo ' y="0" />';
      }
    }
    ?>
    <rect id='fishbgbg' style="fill: #3498db" height='100%' width='<?php echo $width - $graph_width ?>px' x="<?php echo $graph_width ?>" y="0" display="none"/>
    <!-- fishbg is for the extra layered on fish animations like the seawead -->
    <image id='fishbg' xlink:href='' height='100%' width='<?php echo $width - $graph_width ?>px' x="<?php echo $graph_width ?>" y="50" display="none" />
    <!-- movie is where the primary gif goes -->
    <image id='movie' xlink:href='' height='100%' width='<?php echo $width - $graph_width ?>px' x="<?php echo $graph_width ?>" y="0" display="none" />
  </g>
  <rect style="fill:#eee;" height="40px" width="<?php echo $width-$graph_width ?>" y="0" x="<?php echo $graph_width ?>"></rect>
  <rect style="fill:#eee;" height="30px" width="<?php echo $width-$graph_width ?>" y="<?php echo $height-30 ?>" x="<?php echo $graph_width ?>"></rect>
  <?php if ($charachter === 'squirrel') { ?>
  <text text-anchor="end" fill="#4C595A" x="1000" y="20" style="font-size: 20;font-weight: 800">
    <tspan id="accum-label-value">0</tspan>
    <tspan id="accum-label-units" font-size="12" x="1000" dy="1.4em">Kilowatt-hours <?php echo $so_far; ?></tspan>
  </text>
  <?php } elseif ($charachter === 'fish') { ?>
  <text text-anchor="end" fill="#4C595A" x="1000" y="20" style="font-size: 20;font-weight: 800">
    <tspan id="accum-label-value">0</tspan> 
    <tspan id="accum-label-units" font-size="12" x="1000" dy="1.4em">Gallons so far <?php echo $so_far; ?></tspan>
  </text>
  <?php } ?>
  <rect width="3px" height="<?php echo $height; ?>px" x="<?php echo $graph_width ?>" y="0" fill="url(#shadow)" />

  <!-- Topbar -->
  <g transform="translate(40)">
  <!-- was 74.5% width -->
  <rect width="70%" height="35px" x="0" y="0" style="fill:<?php echo '#eee';//$primary_color; ?>;stroke:<?php echo $font_color; ?>;" stroke-width="0" />
  <!-- <line x1="0" y1="<?php echo $height * 0.075; ?>px" x2="<?php echo $width; ?>px" y2="<?php echo $height * 0.075; ?>px" stroke-width="0.25" stroke="<?php echo $font_color; ?>"/> -->
  <text id="current-value-container" fill="#4C595A" x="710" y="20" style="font-size:12;font-weight: 800"><tspan id="current-value" font-size="20">0</tspan> <tspan dy="1.4em" x="710"><?php echo $main_ts->units; ?></tspan></text>
  <text fill="<?php echo $font_color; ?>" id="legend"
        x="0" y="<?php echo $height * 0.13; ?>"
        font-size="13" style="font-weight: 400">
    <tspan dy="8" style='font-size: 40px;fill: <?php echo $current_graph_color; ?>'>&#9632;</tspan>
    <tspan dy="-8"><?php echo $name1; ?></tspan>
    &#160;&#160;&#160;
    <?php if ($typical_time_frame) { ?>
      <tspan id="typical-use-legend">
        <tspan dy="8" style='font-size: 40px;fill: #f39c12'>&#9632;</tspan>
        <tspan dy="-8">Typical Use</tspan>
        &#160;&#160;&#160;
      </tspan>
    <?php } ?>
    <tspan id="historical-use-legend" style="display: none">
      <tspan dy="8" style='font-size: 40px;fill: <?php echo $historical_graph_color; ?>'>&#9632;</tspan>
      <tspan dy="-8">Previous <?php
      if ($time_frame === 'live') {
        echo 'Hour';
      } elseif ($time_frame === 'today') {
        echo 'Day';
      } else {
        echo ucwords($time_frame);
      }
      ?></tspan>
    </tspan>
    <?php if ($secondary_ts_set) { ?>
    <tspan id="secondary-chart-legend" style="display: none;">
      &#160;&#160;&#160;
      <tspan dy="8" style='font-size: 40px;fill: <?php echo $var2_graph_color; ?>'>&#9632;</tspan>
      <tspan dy="-8"><?php echo $name2; ?></tspan>
    </tspan>
    <?php } ?>
  </text>

  <!-- Main button -->
  <g id="layer-btn" style="cursor: pointer;" class="noselect">
    <rect id='layer-btn-rect' data-state="closed" width="120px" height="<?php echo $height * 0.06; ?>px" x="545" y="3" fill="<?php echo $font_color; ?>" stroke="#4C595A" stroke-width="3" style="stroke-dasharray:0,144,120,100;" />
    <text id='layer-btn-text' x="554" y="5%" font-size="15" fill="#ECEFF1" style="font-weight: 400">Graph overlay <tspan style="font-size: 10px;fill:#4C595A">&#9660;</tspan></text>
  </g>
  <g id="resource-btn" style="cursor: pointer;" class="noselect">
    <text fill="#fff" x="8" y="22" style="font-weight: 800">
    <?php
    foreach ($db->query('SELECT id, resource FROM meters WHERE scope = \'Whole Building\'
      AND building_id IN (SELECT building_id FROM meters WHERE id = '.intval($_GET['meter_id']).')
      AND ((gauges_using > 0 OR for_orb > 0 OR timeseries_using > 0) OR bos_uuid IN (SELECT DISTINCT meter_uuid FROM relative_values WHERE permission = \'orb_server\' AND meter_uuid != \'\'))
      ORDER BY units DESC') as $row) {
        echo "<a style='fill:";
        echo ($row['id'] == $_GET['meter_id']) ? '#2196F3' : $font_color;
        echo "' target='_top' xlink:href='index.php?";
        parse_str($_SERVER['QUERY_STRING'], $tmp_qs);
        echo str_replace('&', '&amp;', http_build_query(array_replace($tmp_qs, array('meter_id' => $row['id']))));
        echo "'>{$row['resource']}</a> \n";
      }
    ?>
    </text>
  </g>
  <?php
  // Select the main meters for the current building being viewed and exclude emters that we're not collecting data for
  $stmt = $db->prepare('SELECT id, name FROM meters WHERE scope != \'Whole Building\'
    AND building_id IN (SELECT building_id FROM meters WHERE id = ?)
    AND ((gauges_using > 0 OR for_orb > 0 OR timeseries_using > 0)
    OR bos_uuid IN (SELECT DISTINCT meter_uuid FROM relative_values WHERE permission = \'orb_server\' AND meter_uuid != \'\'))');
  $stmt->execute(array($_GET['meter_id']));
  $related_meters = $stmt->fetchAll();
  if (count($related_meters) > 0) { ?>
  <g id="meter-btn" style="cursor: pointer;" class="noselect">
    <rect id='meter-btn-rect' data-state="closed" width="115px" height="<?php echo $height * 0.06; ?>px" x="420" y="3" fill="<?php echo $font_color; ?>" stroke="#4C595A" stroke-width="3" style="stroke-dasharray:0,139,115,100;" />
    <text id='meter-btn-text' x="430" y="5%" font-size="15" fill="#ECEFF1" style="font-weight: 400">Other meters <tspan style="font-size: 10px;fill:#4C595A">&#9660;</tspan></text>
  </g>
  <?php } ?>
  </g>

  <!-- Bottom bar -->
  <rect width="74.5%" height="<?php echo $height * 0.075; ?>px" x="0" y="<?php echo $height * 0.925; ?>" style="fill:#eee;" />
  <line x1="0" y1="<?php echo $height; ?>" x2="<?php echo $width; ?>px" y2="<?php echo $height; ?>" stroke-width="0.5" stroke="<?php echo $font_color; ?>"/>
  <!-- <line x1="0" y1="<?php echo $height - ($height * 0.075); ?>" x2="<?php echo $width; ?>px" y2="<?php echo $height - ($height * 0.075); ?>" stroke-width="0.5" stroke="<?php echo $font_color; ?>"/> -->
  <!-- <rect width="100%" height="5px" x="0" y="<?php echo $height - ($height * 0.085); ?>" fill="url(#shadow2)" /> -->
  <!-- <g transform="translate(-140)"> -->
  <a xlink:href="<?php echo str_replace('&', '&amp;', $url1h); ?>">
    <?php if ($time_frame !== 'live') { ?>
    <rect width="<?php echo $width * 0.09; ?>px" height="22" x="<?php echo $width * 0.18; ?>" y="<?php echo $height * 0.935; ?>" style="fill:<?php echo $font_color; ?>;stroke:#4C595A;stroke-width:2" />
    <text fill="#fff" x="<?php echo $width * 0.205; ?>" y="<?php echo $height * 0.975; ?>" font-size="14" style="font-weight:400">Hour</text>
    <?php } else { ?>
      <rect width="<?php echo $width * 0.09; ?>px" height="22" x="<?php echo $width * 0.18; ?>" y="<?php echo $height * 0.935; ?>" style="fill:#2196F3;stroke:#4C595A;stroke-width:2"  />
      <text fill="#fff" x="<?php echo $width * 0.205; ?>" y="<?php echo $height * 0.975; ?>" font-size="14" style="font-weight:400">Hour</text>
    <?php } ?>
  </a>
  <a xlink:href="<?php echo str_replace('&', '&amp;', $url1d); ?>">
    <?php if ($time_frame !== 'today') { ?>
    <rect width="<?php echo $width * 0.09; ?>px" height="22" x="<?php echo $width * 0.26; ?>" y="<?php echo $height * 0.935; ?>" style="fill:<?php echo $font_color; ?>;stroke:#4C595A;stroke-width:2" />
    <text fill="#fff" x="<?php echo $width * 0.285; ?>" y="<?php echo $height * 0.975; ?>" font-size="14" style="font-weight:400">Today</text>
    <?php } else { ?>
      <rect width="<?php echo $width * 0.09; ?>px" height="22" x="<?php echo $width * 0.26; ?>" y="<?php echo $height * 0.935; ?>" style="fill:#2196F3;stroke:#4C595A;stroke-width:2" />
      <text fill="#fff" x="<?php echo $width * 0.285; ?>" y="<?php echo $height * 0.975; ?>" font-size="14" style="font-weight:400">Today</text>
    <?php } ?>
  </a>
  <a xlink:href="<?php echo str_replace('&', '&amp;', $url1w); ?>">
    <?php if ($time_frame !== 'week') { ?>
    <rect width="<?php echo $width * 0.09; ?>px" height="22" x="<?php echo $width * 0.35; ?>" y="<?php echo $height * 0.935; ?>" style="fill:<?php echo $font_color; ?>;stroke:#4C595A;stroke-width:2" />
    <text fill="#fff" x="<?php echo $width * 0.374; ?>" y="<?php echo $height * 0.975; ?>" font-size="14" style="font-weight:400">Week</text>
    <?php } else { ?>
      <rect width="<?php echo $width * 0.09; ?>px" height="22" x="<?php echo $width * 0.35; ?>" y="<?php echo $height * 0.935; ?>" style="fill:#2196F3;stroke:#4C595A;stroke-width:2" />
      <text fill="#fff" x="<?php echo $width * 0.374; ?>" y="<?php echo $height * 0.975; ?>" font-size="14" style="font-weight:400">Week</text>
    <?php } ?>
  </a>
  <a xlink:href="<?php echo str_replace('&', '&amp;', $url1m); ?>">
    <?php if ($time_frame !== 'month') { ?>
    <rect width="<?php echo $width * 0.09; ?>px" height="22" x="<?php echo $width * 0.432; ?>" y="<?php echo $height * 0.935; ?>" style="fill:<?php echo $font_color; ?>;stroke:#4C595A;stroke-width:2" />
    <text fill="#fff" x="<?php echo $width * 0.458; ?>" y="<?php echo $height * 0.975; ?>" font-size="14" style="font-weight:400">Month</text>
    <?php } else { ?>
      <rect width="<?php echo $width * 0.09; ?>px" height="22" x="<?php echo $width * 0.432; ?>" y="<?php echo $height * 0.935; ?>" style="fill:#2196F3;stroke:#4C595A;stroke-width:2" />
      <text fill="#fff" x="<?php echo $width * 0.458; ?>" y="<?php echo $height * 0.975; ?>" font-size="14" style="font-weight:400">Month</text>
    <?php } ?>
  </a>
  <a xlink:href="<?php echo str_replace('&', '&amp;', $url1y); ?>">
    <?php if ($time_frame !== 'year') { ?>
    <rect width="<?php echo $width * 0.09; ?>px" height="22" x="<?php echo $width * 0.518; ?>" y="<?php echo $height * 0.935; ?>" style="fill:<?php echo $font_color; ?>;stroke:#4C595A;stroke-width:2" />
    <text fill="#fff" x="<?php echo $width * 0.55; ?>" y="<?php echo $height * 0.975; ?>" font-size="14" style="font-weight:400">Year</text>
    <?php } else { ?>
      <rect width="<?php echo $width * 0.09; ?>px" height="22" x="<?php echo $width * 0.522; ?>" y="<?php echo $height * 0.935; ?>" style="fill:#2196F3;stroke:#4C595A;stroke-width:2" />
      <text fill="#fff" x="<?php echo $width * 0.555; ?>" y="<?php echo $height * 0.975; ?>" font-size="14" style="font-weight:400">Year</text>
    <?php } ?>
  </a>
  <!-- </g> -->
  <!-- accum_btnulation selection -->
  <?php if ($charachter === 'squirrel') { ?>
  <!-- <g id="kwh" style="display: none">
    <rect width="<?php echo $width * 0.05; ?>px" height="20" x="<?php echo $graph_width; ?>" y="0" style="fill:<?php echo $font_color; ?>" />
    <text fill="#fff" x="<?php echo $graph_width + 10; ?>" y="15" font-size="14" style="font-weight:400">kWh</text>
  </g>
  <g id="kwh-active">
    <rect width="<?php echo $width * 0.05; ?>px" height="20" x="<?php echo $graph_width; ?>" y="0" style="fill:#2196F3;" />
    <text fill="#fff" x="<?php echo $graph_width + 10; ?>" y="15" font-size="14" style="font-weight:400">kWh</text>
  </g>
  <g id="co2">
    <rect width="<?php echo $width * 0.05; ?>px" height="20" x="<?php echo $graph_width; ?>" y="20" style="fill:<?php echo $font_color; ?>;" />
    <text fill="#fff" x="<?php echo $graph_width + 10; ?>" y="35" font-size="14" style="font-weight:400">CO2</text>
  </g>
  <g id="co2-active" style="display: none">
    <rect width="<?php echo $width * 0.05; ?>px" height="20" x="<?php echo $graph_width; ?>" y="20" style="fill:#2196F3;" />
    <text fill="#fff" x="<?php echo $graph_width + 10; ?>" y="35" font-size="14" style="font-weight:400">CO2</text>
  </g>
  <g id="money">
    <rect width="<?php echo $width * 0.05; ?>px" height="20" x="<?php echo $graph_width; ?>" y="40" style="fill:<?php echo $font_color; ?>;" />
    <text fill="#fff" x="<?php echo $graph_width + 20; ?>" y="55" font-size="14" style="font-weight:400">$</text>
  </g>
  <g id="money-active" style="display: none">
    <rect width="<?php echo $width * 0.05; ?>px" height="20" x="<?php echo $graph_width; ?>" y="40" style="fill:#2196F3;" />
    <text fill="#fff" x="<?php echo $graph_width + 20; ?>" y="55" font-size="14" style="font-weight:400">$</text>
  </g> -->
  <g id="emo" style="display: none">
    <rect width="<?php echo $width * 0.04; ?>px" height="22" x="<?php echo $graph_width + 40; ?>" y="<?php echo $height * 0.935; ?>" style="fill:<?php echo $font_color; ?>;stroke:#4C595A;stroke-width:2" />
    <text fill="#fff" x="<?php echo $graph_width + 52; ?>" y="<?php echo $height * 0.975; ?>" font-size="14" style="font-weight:400">☺</text><!-- used to be 🙂-->
  </g>
  <g id="emo-active">
    <rect width="<?php echo $width * 0.04; ?>px" height="22" x="<?php echo $graph_width + 40; ?>" y="<?php echo $height * 0.935; ?>" style="fill:#2196F3;stroke:#4C595A;stroke-width:2" />
    <text fill="#fff" x="<?php echo $graph_width + 52; ?>" y="<?php echo $height * 0.975; ?>" font-size="14" style="font-weight:400">☺</text>
  </g>
  <g id="kwh">
    <rect width="<?php echo $width * 0.05; ?>px" height="22" x="<?php echo $graph_width + 80; ?>" y="<?php echo $height * 0.935; ?>" style="fill:<?php echo $font_color; ?>;stroke:#4C595A;stroke-width:2" />
    <text fill="#fff" x="<?php echo $graph_width + 95; ?>" y="<?php echo $height * 0.975; ?>" font-size="14" style="font-weight:400">kWh</text>
  </g>
  <g id="kwh-active" style="display: none">
    <rect width="<?php echo $width * 0.05; ?>px" height="22" x="<?php echo $graph_width + 80; ?>" y="<?php echo $height * 0.935; ?>" style="fill:#2196F3;stroke:#4C595A;stroke-width:2" />
    <text fill="#fff" x="<?php echo $graph_width + 95; ?>" y="<?php echo $height * 0.975; ?>" font-size="14" style="font-weight:400">kWh</text>
  </g>
  <g id="co2">
    <rect width="<?php echo $width * 0.05; ?>px" height="22" x="<?php echo $graph_width + 130; ?>" y="<?php echo $height * 0.935; ?>" style="fill:<?php echo $font_color; ?>;stroke:#4C595A;stroke-width:2" />
    <text fill="#fff" x="<?php echo $graph_width + 140; ?>" y="<?php echo $height * 0.975; ?>" font-size="14" style="font-weight:400">CO2</text>
  </g>
  <g id="co2-active" style="display: none">
    <rect width="<?php echo $width * 0.05; ?>px" height="22" x="<?php echo $graph_width + 130; ?>" y="<?php echo $height * 0.935; ?>" style="fill:#2196F3;stroke:#4C595A;stroke-width:2" />
    <text fill="#fff" x="<?php echo $graph_width + 140; ?>" y="<?php echo $height * 0.975; ?>" font-size="14" style="font-weight:400">CO2</text>
  </g>
  <g id="money">
    <rect width="<?php echo $width * 0.04; ?>px" height="22" x="<?php echo $graph_width + 180; ?>" y="<?php echo $height * 0.935; ?>" style="fill:<?php echo $font_color; ?>;stroke:#4C595A;stroke-width:2" />
    <text fill="#fff" x="<?php echo $graph_width + 195; ?>" y="<?php echo $height * 0.975; ?>" font-size="14" style="font-weight:400">$</text>
  </g>
  <g id="money-active" style="display: none">
    <rect width="<?php echo $width * 0.04; ?>px" height="22" x="<?php echo $graph_width + 180; ?>" y="<?php echo $height * 0.935; ?>" style="fill:#2196F3;stroke:#4C595A;stroke-width:2" />
    <text fill="#fff" x="<?php echo $graph_width + 195; ?>" y="<?php echo $height * 0.975; ?>" font-size="14" style="font-weight:400">$</text>
  </g>

  <?php } elseif ($charachter === 'fish') { ?>

  <g id="gal" style="display:none">
    <rect width="<?php echo $width * 0.05; ?>px" height="22" x="<?php echo $graph_width + 80; ?>" y="<?php echo $height * 0.935; ?>" style="fill:<?php echo $font_color; ?>stroke:#4C595A;stroke-width:2" />
    <text fill="#fff" x="<?php echo $graph_width + 93; ?>" y="<?php echo $height * 0.975; ?>" font-size="14" style="font-weight:400">Gal</text>
  </g>
  <g id="gal-active">
    <rect width="<?php echo $width * 0.05; ?>px" height="22" x="<?php echo $graph_width + 80; ?>" y="<?php echo $height * 0.935; ?>" style="fill:#2196F3;stroke:#4C595A;stroke-width:2" />
    <text fill="#fff" x="<?php echo $graph_width + 93; ?>" y="<?php echo $height * 0.975; ?>" font-size="14" style="font-weight:400">Gal</text>
  </g>
  <g id="money2">
    <rect width="<?php echo $width * 0.04; ?>px" height="22" x="<?php echo $graph_width + 130; ?>" y="<?php echo $height * 0.935; ?>" style="fill:<?php echo $font_color; ?>;stroke:#4C595A;stroke-width:2" />
    <text fill="#fff" x="<?php echo $graph_width + 145; ?>" y="<?php echo $height * 0.975; ?>" font-size="14" style="font-weight:400">$</text>
  </g>
  <g id="money2-active" style="display: none">
    <rect width="<?php echo $width * 0.04; ?>px" height="22" x="<?php echo $graph_width + 130; ?>" y="<?php echo $height * 0.935; ?>" style="fill:#2196F3;stroke:#4C595A;stroke-width:2" />
    <text fill="#fff" x="<?php echo $graph_width + 145; ?>" y="<?php echo $height * 0.975; ?>" font-size="14" style="font-weight:400">$</text>
  </g>
  <?php } ?>
  <!-- graph overlay menu -->
  <g id="overlay-dropdown" style="display: none">
    <rect x="505" y="30" height="100px" width="200px" style="fill:#eee;" />
    <text style="cursor:pointer" id="historical" x="515" y="60" font-size="12" fill="<?php echo $font_color ?>"><?php echo ($show_hist) ? 'Hide' : 'Show'; ?> previous <?php
          if ($time_frame === 'live') { echo 'hour'; }
          elseif ($time_frame === 'today') { echo 'day'; }
          else { echo $time_frame; }
          ?></text>
    <text style="cursor:pointer" <?php echo ($typical_time_frame) ? 'id="typical"' : ''; ?> x="515" y="85" font-size="12" fill="<?php echo $font_color ?>">
      <?php echo ($typical_time_frame) ? 'Show typical' : 'Typical not available'; ?>
    </text>
    <?php if ($secondary_ts_set) { ?>
    <text style="cursor:pointer" id="second" x="515" y="110" font-size="12" fill="<?php echo $font_color ?>">Show <?php echo $name2; ?></text>
    <?php } ?>
  </g>
  <g id="meter-dropdown" style="display: none">
    <?php
    echo '<rect x="380" y="30" height="'.(33*(count($related_meters))).'px" width="200px" style="fill:#eee;" />';
    $tmp = 60;
    foreach ($related_meters as $rm) {
      $url = 'https://oberlindashboard.org/oberlin/time-series/index.php?meter_id=' . $rm['id'];
      echo "<a href='{$url}'><text style='cursor:pointer' x='385' y='{$tmp}' font-size='12' fill='{$font_color}'>{$rm['name']}</text></a>\n";
      $tmp += 25;
    }
    // echo "<a href='#'><text style='cursor:pointer' x='175' y='{$tmp}' font-size='12' fill='{$font_color}'>Other buildings</text></a>\n";
    ?>
  </g>

  <script xlink:href="https://cdnjs.cloudflare.com/ajax/libs/gsap/1.19.1/TweenMax.min.js"></script>
  <script type="text/javascript" xlink:href="js/jquery.min.js"/>
  <script type="text/javascript">
  // <![CDATA[
  console.log(<?php echo json_encode($log) ?>);
  /* BUTTON/MENU FUNCTIONALITY */
  <?php if ($charachter === 'squirrel') { ?>
  var accum_btn = $('#emo');//$('#kwh');
  var active_accum_btn = $('#emo-active');//$('#kwh-active');
  $('#emo').on('click', function() {
    $('#empathetic-char').css('display', '');
    active_accum_btn.css('display', 'none');
    accum_btn.css('display', '');
    $('#emo').css('display', 'none');
    $('#emo-active').css('display', '');
    accum_btn = $('#emo');
    active_accum_btn = $('#emo-active');
  });
  $('#kwh').on('click', function() {
    // hide the empathetic character
    $('#empathetic-char').css('display', 'none');
    // display the kwh-animation
    $('#kwh-animation').css('display', '');  
    // hide co2 animation
    $('#co2-animation').css('display', 'none'); 
    // hide money animation
    $('#money-animation').css('display', 'none'); 
    active_accum_btn.css('display', 'none');
    accum_btn.css('display', '');
    $('#kwh').css('display', 'none');
    $('#kwh-active').css('display', '');
    accum_btn = $('#kwh');
    active_accum_btn = $('#kwh-active');
    $('#accum-label-units').text('Kilowatt-hours <?php echo $so_far; ?>');
    // the amount of time elapsed
    var elapsed = (current_timestamps[current_timestamps.length-1]-current_timestamps[0])/3600;
    var kw = 0; // the total number of kw in the time elapsed
    var kw_count = 0; // used for calculating average
    for (var i = current_timestamps.length-1; i >= 0; i--) {
      kw += raw_data[i];
      kw_count++;
    }
    // the # of hours elapsed * the average kw reading
    $('#accum-label-value').text(Math.round(elapsed*(kw/kw_count)).toLocaleString());
    });
  
  $('#co2').on('click', function() {
      // hide the empathetic character
      $('#empathetic-char').css('display', 'none');
      // hide the kwh-animation
      $('#kwh-animation').css('display', 'none');  
      // display co2 animation
      $('#co2-animation').css('display', '');    
      // hide the money-animation
      $('#money-animation').css('display', 'none');  
      active_accum_btn.css('display', 'none');
      accum_btn.css('display', '');
      $('#co2').css('display', 'none');
      $('#co2-active').css('display', '');
      accum_btn = $('#co2');
      active_accum_btn = $('#co2-active');
      $('#accum-label-units').text('Pounds of CO2 <?php echo $so_far; ?>');
      var elapsed = (current_timestamps[current_timestamps.length-1]-current_timestamps[0])/3600;
      var kw = 0;
      var kw_count = 0;
      for (var i = current_timestamps.length-1; i >= 0; i--) {
        kw += raw_data[i];
        kw_count++;
      }
      $('#accum-label-value').text(Math.round((elapsed*(kw/kw_count))*1.22).toLocaleString());
      //smokestack swapping animation//
      var counter = 0;
      var smokestack = setInterval(function() {
        if (counter++ % 2 == 0) {
          $('#pipes').children().attr('xlink:href', 'https://oberlindashboard.org/oberlin/cwd/img/smokestack/smokestack2.png');
        }
        else {
          $('#pipes').children().attr('xlink:href', 'https://oberlindashboard.org/oberlin/cwd/img/smokestack/smokestack1.png');
        }
      }, 3000);
      var smoke = $('#smoke').children();
      var newsmoke = TweenMax.to($('#smoke > image'), 1, {y: "-60px", x: "20px", scaleX: 2, scaleY: 1.5, opacity: 0, ease:Power0.easeNone, repeat: -1, repeatDelay: 3});
  });

  $('#money').on('click', function() {
    // hide the empathetic charachter
    $('#empathetic-char').css('display', 'none');
    //hide the kwh-animation
    $('#kwh-animation').css('display', 'none');  
    //hide co2 animation
    $('#co2-animation').css('display', 'none');    
    // display money animation
    $('#money-animation').css('display', '');    
    active_accum_btn.css('display', 'none');
    accum_btn.css('display', '');
    $('#money').css('display', 'none');
    $('#money-active').css('display', '');
    accum_btn = $('#money');
    active_accum_btn = $('#money-active');
    $('#accum-label-units').text('Dollars spent <?php echo $so_far; ?>');
    var elapsed = (current_timestamps[current_timestamps.length-1]-current_timestamps[0])/3600;
    var kw = 0;
    var kw_count = 0;
    for (var i = current_timestamps.length-1; i >= 0; i--) {
      kw += raw_data[i];
      kw_count++;
    }
    $('#accum-label-value').text('$'+Math.round((elapsed*(kw/kw_count))*0.12).toLocaleString());

    var banknote = TweenMax.to($('#banknote > image'), 2, {y: "300px", x: "10px", ease: Power0.easeNone, delay: 2, repeat: -1, repeatDelay: 2});
    
    // var banknote = $('#banknote').children();
    // $({y:banknote.attr('y')}).animate(
    //   {y: 200},
    //   {
    //     duration: 1500,
    //     step:function(now) {
    //       banknote.attr('y', now);
    //     },
    //     complete: function() {
    //       banknote.css('opacity', '0');
    //       banknote.attr('y', '0');

    //     }
    //   }
    // );
  });

  <?php } elseif ($charachter === 'fish') { ?>

  var accum_btn = $('#gal');
  var active_accum_btn = $('#gal-active');
  $('#gal').on('click', function() {
    active_accum_btn.css('display', 'none');
    accum_btn.css('display', '');
    $('#gal').css('display', 'none');
    $('#gal-active').css('display', '');
    accum_btn = $('#gal');
    active_accum_btn = $('#gal-active');
    $('#accum-label-units').text('Gallons so far <?php echo $so_far; ?>');
    var elapsed = (current_timestamps[current_timestamps.length-1]-current_timestamps[0])/3600;
    var gals = 0;
    var gals_count = 0;
    for (var i = current_timestamps.length-1; i >= 0; i--) {
      gals += raw_data[i];
      gals_count++;
    }
    $('#accum-label-value').text(Math.round(elapsed*(gals/gals_count)).toLocaleString());
  });
  $('#money2').on('click', function() {
    active_accum_btn.css('display', 'none');
    accum_btn.css('display', '');
    $('#money2').css('display', 'none');
    $('#money2-active').css('display', '');
    accum_btn = $('#money2');
    active_accum_btn = $('#money2-active');
    $('#accum-label-units').text('Dollars spent <?php echo $so_far; ?>');
    var elapsed = (current_timestamps[current_timestamps.length-1]-current_timestamps[0])/3600;
    var gals = 0;
    var gals_count = 0;
    for (var i = current_timestamps.length-1; i >= 0; i--) {
      gals += raw_data[i];
      gals_count++;
    }
    $('#accum-label-value').text('$'+Math.round((elapsed*(gals/gals_count))*0.12).toLocaleString());
  });
  <?php } ?>

  $('#layer-btn').on("click", function() {
    var layer_btn_rect = $('#layer-btn-rect');
    var layer_btn_text = $('#layer-btn-text');
    if (layer_btn_rect.data('state') === 'closed') {
      layer_btn_text.html('Graph overlay <tspan style="font-size: 10px;fill:#4C595A">&#9650;</tspan>');
      $('#overlay-dropdown').css('display', '');
      layer_btn_rect.data('state', 'open');
    }
    else {
      layer_btn_text.css('transform', 'translateY(0px)').html('Graph overlay <tspan style="font-size: 10px;fill:#4C595A">&#9660;</tspan>');
      $('#overlay-dropdown').css('display', 'none');
      layer_btn_rect.data('state', 'closed');
    }
  });
  $('#meter-btn').on("click", function() {
    var layer_btn_rect = $('#meter-btn-rect');
    var layer_btn_text = $('#meter-btn-text');
    if (layer_btn_rect.data('state') === 'closed') {
      layer_btn_text.html('Other meters <tspan style="font-size: 10px;fill:#4C595A">&#9650;</tspan>');
      $('#meter-dropdown').css('display', '');
      layer_btn_rect.data('state', 'open');
    }
    else {
      layer_btn_text.css('transform', 'translateY(0px)').html('Other meters <tspan style="font-size: 10px;fill:#4C595A">&#9660;</tspan>');
      $('#meter-dropdown').css('display', 'none');
      layer_btn_rect.data('state', 'closed');
    }
  });
  $('#historical').on("click", function() {
    var historical = $('#historical-chart');
    if (historical.css('opacity') === '0') {
      historical.css('opacity', '1');
      $('#historical-use-legend').css('display', 'initial');
      curtain('curtain');
      $(this).text('Hide previous <?php
      if ($time_frame === 'live') {
        echo 'hour';
      }
      elseif ($time_frame === 'today') {
        echo 'day';
      }
      else {
        echo $time_frame;
      }
      ?>');
    }
    else {
      historical.css('opacity', '0');
      $('#historical-use-legend').css('display', 'none');
      $(this).text('Show previous <?php
      if ($time_frame === 'live') {
        echo 'hour';
      }
      elseif ($time_frame === 'today') {
        echo 'day';
      }
      else {
        echo $time_frame;
      }
      ?>');
    }
  });
  <?php if ($secondary_ts_set) { ?>
  $('#second').on("click", function() {
    var second = $('#second-chart');
    if (second.css('opacity') === '0') {
      $('#secondary-chart-legend').css('display', 'initial');
      $('#y-axis-right').css('opacity', '1');
      second.css('opacity', '1');
      curtain('curtain');
      $(this).text('Hide <?php echo $name2; ?>');
    }
    else {
      $('#secondary-chart-legend').css('display', 'none');
      $('#y-axis-right').css('opacity', '0');
      second.css('opacity', '0');
      $(this).text('Show <?php echo $name2; ?>');
    }
  });
  <?php } ?>
  $('#typical').on("click", function() {
    var typ = $('#typical-chart');
    if (typ.css('opacity') === '0') {
      $('#typical-use-legend').css('display', 'initial');
      typ.css('opacity', '1');
      curtain('curtain');
      $(this).text('Hide typical');
    }
    else {
      $('#typical-use-legend').css('display', 'none');
      typ.css('opacity', '0');
      $(this).text('Show typical');
    }
  });
  // Curtain animation
  function curtain(id) {
    var curtain = $('#' + id);
    curtain.css('opacity', '1');
    $({x:curtain.attr('x')}).animate(
      {x: <?php echo $graph_width; ?>},
      {
        duration: 2500,
        step:function(now) {
          curtain.attr('x', now);
        },
        complete: function() {
          curtain.css('opacity', '0');
          curtain.attr('x', '0');
        }
      }
    );
  }
  curtain('curtain');

  /* MOUSE INTERACTION */

  // Find your root SVG element
  var svg = document.querySelector('svg');
  // Create an SVGPoint for future math
  var pt = svg.createSVGPoint();
  // Get point in global SVG space
  function cursorPoint(evt){
    pt.x = evt.pageX; pt.y = evt.pageY;
    return pt.matrixTransform(svg.getScreenCTM().inverse());
  }
  // Variable setting
  var index_rn;
  var alreadydone;
  var counter;
  var movies_played = 0;
  var current_points = <?php echo json_encode($main_ts->circlepoints); ?>;
  var current_times = <?php echo json_encode($main_ts->times) ?>;
  var current_timestamps = <?php echo json_encode($main_ts->recorded) ?>;
  var historical_points = <?php echo json_encode($historical_ts->circlepoints) ?>;
  var relativized_points = <?php echo ($typical_time_frame) ? json_encode($typical_ts->circlepoints) : json_encode($historical_ts->circlepoints); ?>;
  var raw_data = <?php echo json_encode($main_ts->value); ?>;
  var raw_data_formatted = <?php echo json_encode(array_map('my_nf', $main_ts->value));
      function my_nf($n) { if ($n < 10) {$default = 2;} else {$default = 0;} return number_format($n, (!empty($_GET['rounding'])) ? $_GET['rounding'] : $default); } ?>;
  var min = <?php echo json_encode($main_ts->min) ?>;
  var max = <?php echo json_encode($main_ts->max) ?>;
  var playing = true;
  var frames = [];
  var current_frame = 0;
  var last_frame = 0;
  var movie = $('#movie');
  <?php
    // Create an array the same size as the $main_ts->circlepoints that stores the squirrel/fish `current_frame` (do command-f for 'current_frame = ')
    $charachter_moods = array();
    if ($typical_time_frame) {
      $relativized_points = $typical_ts->circlepoints;
    } else {
      $relativized_points = $historical_ts->circlepoints;
    }
    $diff_min = PHP_INT_MAX;
    $diff_max = PHP_INT_MIN;
    // calculate the $diff_min/$diff_max
    echo "/*\n";
    for ($i=0; $i < count($main_ts->circlepoints); $i++) {
      $scaled = round($pct_through*$i);
      // echo "{$i},{$scaled}:{$main_ts->circlepoints[$i][1]},{$relativized_points[$scaled][1]} ";
      $d = $main_ts->circlepoints[$i][1] - $relativized_points[$scaled][1];
      $charachter_moods[] = $d; // save difference to scale later
      if ($d > $diff_max) {
        $diff_max = $d;
      }
      if ($d < $diff_min) {
        $diff_min = $d;
      }
    }
    echo "*/\n\n";
    // scale the difference between two points to a gif frame
    for ($i=0; $i < count($charachter_moods); $i++) { 
      $charachter_moods[$i] = round($main_ts->convertRange($charachter_moods[$i], $diff_min, $diff_max, 0, $number_of_frames));
    }
    echo "var charachter_moods = " . json_encode($charachter_moods) . ";\n";
    // The commented-out JS below creates what the PHP does above
  ?>
  // var diff_min = Number.MAX_VALUE;
  // var diff_max = 0;
  // // Need to find the min/max difference between the current and relativized data for scaling
  // var shorter_arr = Math.min(current_points.length, relativized_points.length) - 1;
  // for (; shorter_arr >= 0; shorter_arr--) {
  //   var d = current_points[shorter_arr][1] - relativized_points[shorter_arr][1];
  //   if (d > diff_max) {
  //     diff_max = d;
  //   }
  //   if (d < diff_min) {
  //     diff_min = d;
  //   }
  // }
  $(svg).one('mousemove', function() {
    $('#suggestion').attr('display', 'none');
    $('#error-msg').attr('display', '');
  });
  $(svg).on('mousemove', function(evt) {
    playing = true;
    var loc = cursorPoint(evt);
    if (loc.x <= 40) {
      return;
    }
    // subtract 40 from loc.x b/c of the chart padding. subtract 80 from graph width to account for
    // padding on both sides
    var pct_through = ((loc.x-40) / <?php echo (($graph_width-80)*$pct_through); ?>);
    var pct_through_whole = ((loc.x-40) / <?php echo $graph_width-80; ?>);
    if (pct_through > 1) { // if the mouse is to the right of the main chart
      return;
    }
    index_rn = Math.round(pct_through * (current_points.length-1)); // Coords for circle (subtract 1 to 0-base for array index)
    $('#current-circle').attr('cx', current_points[index_rn][0]);
    $('#current-circle').attr('cy', current_points[index_rn][1]);
    <?php if ($typical_time_frame) { ?>
      var index2 = Math.round(pct_through_whole * (relativized_points.length-1)); // Coords for typical circle
      $('#typical-circle').attr('cx', relativized_points[index2][0]);
      $('#typical-circle').attr('cy', relativized_points[index2][1]);
    <?php } else { ?>
      var index2 = Math.round(pct_through_whole * (historical_points.length-1)); // Coords for historical circle
      $('#historical-circle').attr('cx', historical_points[index2][0]);
      $('#historical-circle').attr('cy', historical_points[index2][1]);
    <?php } ?>
    $('#current-value').text(raw_data_formatted[index_rn]);
    $('#current-time-rect').attr('x', current_points[index_rn][0] - <?php echo $width * 0.05; ?>);
    $('#current-time-text').attr('x', current_points[index_rn][0]);
    $('#current-time-text').text(current_times[index_rn]);
    // console.log(index_rn, index2);
    var elapsed = (current_timestamps[index_rn]-current_timestamps[0]);
    var kw = 0;
    var kw_count = 0;
    for (var i = index_rn; i >= 0; i--) {
      kw += raw_data[i];
      kw_count++;
    }
    accumulation(elapsed, kw/kw_count);
    if (raw_data[index_rn] === null) {
      $('#error-msg').attr('display', '');
      $('#frame_0').attr('display', '');
      $('#current-value').text('--');
    }
    else {
      $('#error-msg').attr('display', 'none');
    }
    // Display the current gif frame
    last_frame = current_frame;
    current_frame = charachter_moods[index_rn];
    if (current_frame > last_frame) {
      counter = last_frame;
      while (current_frame >= counter && frames.length < 100) {
        frames.push(counter);
        counter++;
      }
    }
    else if (current_frame < last_frame) {
      counter = last_frame;
      while (current_frame <= counter && frames.length < 100) {
        frames.push(counter);
        counter--;
      }
    }

    // Hide the movie if the mouse moves
    if (movie.attr('display') != 'none') {
      movie.attr('display', 'none');
      $("#fishbgbg").attr('display', 'none');
      $("#fishbg").attr('display', 'none');
      alreadydone = true;
    }
  });
  
  // "Play" the data -- when the mouse is idle for 5 seconds, move the dot up the line
  const mouse_idle_ms = 5000;
  var interval = null;
  var timeout = null;
  var timeout2 = null;
  $(document).on('mousemove', function() { // when to cancel interval
    clearInterval(interval); interval = null;
    clearTimeout(timeout2); timeout2 = null;
    clearTimeout(timeout); timeout = null;
    timeout = setTimeout(play, mouse_idle_ms); // Mouse idle for 3 seconds
  });

  function play() {
    // Make sure there are no timers running
    clearInterval(interval); interval = null;
    clearTimeout(timeout2); timeout2 = null;
    clearTimeout(timeout); timeout = null;
    if (accum_btn.attr('id') !== 'emo') { // do one of the accumulation animations
      // accumulation_animation();
    } else {
      if (Math.random() > 0.6) { // Randomly either play through the data or play movie
        play_data();
      } else {
        play_movie();
      }
    }
  }

  function play_data() {
    console.log('play_data');
    var i = 0, kw = 0, elapsed = 0;
    interval = setInterval(function() {
      var pct_through = i/(current_points.length-1);
      var shift_i = Math.round(pct_through * (relativized_points.length*<?php echo $pct_through; ?>));
      $('#current-circle').attr('cx', current_points[i][0]);
      $('#current-circle').attr('cy', current_points[i][1]);
      <?php if ($typical_time_frame) { ?>
      $('#typical-circle').attr('cx', relativized_points[shift_i][0]);
      $('#typical-circle').attr('cy', relativized_points[shift_i][1]);
      <?php } else { ?>
      $('#historical-circle').attr('cx', relativized_points[shift_i][0]);
      $('#historical-circle').attr('cy', relativized_points[shift_i][1]);
      <?php } ?>
      $('#current-time-rect').attr('x', current_points[i][0] - <?php echo $width * 0.05; ?>);
      $('#current-time-text').attr('x', current_points[i][0]);
      $('#current-value').text(raw_data_formatted[i]);
      $('#current-time-text').text(current_times[i]);
      last_frame = current_frame;
      current_frame = charachter_moods[i];
      kw += raw_data[i];
      elapsed += current_timestamps[1]-current_timestamps[0];
      accumulation(elapsed, kw/(i+1));
      i++;
      if (current_frame > last_frame && frames.length < 100) {
        counter = last_frame;
        while (current_frame >= counter) {
          frames.push(counter);
          counter++;
        }
      }
      else if (current_frame < last_frame && frames.length < 100) {
        counter = last_frame;
        while (current_frame <= counter) {
          frames.push(counter);
          counter--;
        }
      }
      if (i == current_points.length-1) {
        clearInterval(interval); interval = null;
        $('#frame_' + current_frame).attr('display', '');
        index_rn = <?php echo count($main_ts->value)-1; ?>; // set the indicator ball stuff to the last point
        clearTimeout(timeout2); timeout2 = null;
        timeout2 = setTimeout(play, mouse_idle_ms);
      }
    }, 30);
  }

  function sortNumber(a,b) { return a - b; }

  function play_movie() {
    console.log('play_movie');
    playing = false;
    var val = (raw_data[index_rn] == null) ? 0 : raw_data[index_rn];
    var raw_data_copy_sorted = raw_data.slice().sort(sortNumber);
    var indexof = raw_data_copy_sorted.indexOf(val);
    var relative_value = ((indexof) / raw_data_copy_sorted.length) * 100; // Get percent (0-100)
    $.get("movie.php", {relative_value: relative_value, count: movies_played, charachter: <?php echo json_encode($charachter) ?>}, function(data) {
      movies_played++;
      var split = data.split('$SEP$');
      console.log(split)
      var len = split[1];
      var name = split[0];
      var fishbg = split[2];
      $("#fishbgbg").attr('display', '');
      if (fishbg != 'none') { // The fish animation is layered on top of the 
        $("#fishbg").attr('xlink:href', 'images/' + fishbg + '.gif').attr('display', '');
      }
      $('#movie').attr('xlink:href', 'images/' + name + '.gif').attr('display', '');
      if (name.indexOf("Story") >= 0 || name.indexOf("Idea") >= 0) {
      }
      alreadydone = false;
      setTimeout(function() {
        if (!alreadydone) {
          $('#movie').attr('display', 'none');
          $('#frame_' + current_frame).attr('display', '');
          $("#fishbgbg").attr('display', 'none');
          $("#fishbg").attr('display', 'none');
          playing = true;
        }
        clearTimeout(timeout2); timeout2 = null;
        timeout2 = setTimeout(play, mouse_idle_ms);
      }, len);
    }, 'text');
    // Get gif lengths: http://gifduration.konstochvanligasaker.se/
  }

  function accumulation(time_sofar, avg_kw) { // how calculate kwh
    var kwh = (time_sofar/3600)*avg_kw; // the number of hours in time period * the average kw reading
    // console.log('time elapsed in hours: '+(time_sofar/3600)+"\navg_kw: "+ avg_kw+"\nkwh: "+kwh);
    var id = accum_btn.attr('id');
    if (id === 'kwh' || id === 'emo') {
      $('#accum-label-value').text(Math.round(kwh).toLocaleString()); // kWh = time elapsed in hours * kilowatts so far
    }
    else if (id === 'co2') {
      $('#accum-label-value').text(Math.round(kwh*1.22).toLocaleString()); // pounds of co2 per kwh https://www.eia.gov/tools/faqs/faq.cfm?id=74&t=11
    } else if (id === 'gal') {
      $('#accum-label-value').text(Math.round(kwh).toLocaleString());
    } else if (id === 'money2') {
      $('#accum-label-value').text('$'+Math.round(kwh*0.004).toLocaleString());
    } else { // money
      $('#accum-label-value').text('$'+Math.round(kwh*0.11).toLocaleString()); // average cost of kwh http://www.npr.org/sections/money/2011/10/27/141766341/the-price-of-electricity-in-your-state
    }
  }

  play_data(); // start by playing data

  var last_animated = current_frame;
  // var changingHeader = $('#matchingColorHeader');
  setInterval(function(){
    if (frames.length > 0 && playing) {
      var shift = frames.shift();
      $('#frame_' + last_animated).attr('display', 'none');
      $('#frame_' + shift).attr('display', '');
      // changingHeader.css('fill', $('#frame_' + shift).data('color'));
      last_animated = shift;
    }
  }, 8);

  <?php
  if ($time_frame === 'live') {
    echo 'setTimeout(function(){ window.location.reload(); }, 120000);';
  }
  ?>
  // ]]>
  </script>
</svg>