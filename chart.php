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
  $data = use_api($db, $bos, $_GET['meter_id'], $res, $from, $now);
  $main_ts->data($data);
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
$secondary_ts_set = (isset($_GET['meter_id2']) && $_GET['meter_id'] !== $_GET['meter_id2']);
if ($secondary_ts_set) {
  $secondary_ts = new TimeSeries($db, $_GET['meter_id2'], $from, $now, $res); // "Second variable" timeseries
  try {
    $secondary_ts->data();
  } catch (Exception $e) {
    $log[] = 'used api for second variable';
    $data = use_api($db, $bos, $_GET['meter_id2'], $res, $from, $now);
    $secondary_ts->data($data);
  }
} else {
  $secondary_ts = null;
}
$historical_ts = new TimeSeries($db, $_GET['meter_id'], $double_time, $from, $res); // Historical data of main
try {
  $historical_ts->data();
} catch (Exception $e) {
  $log[] = 'used api for historical chart';
  $data = use_api($db, $bos, $_GET['meter_id'], $res, $double_time, $from);
  $historical_ts->data($data);
}
// $meter = new Meter($db);
$main_ts->setUnits();
if ($main_ts->units === 'Gallons / hour' || $main_ts->units === 'Liters / hour' || $main_ts->units === 'Liters' || $main_ts->units === 'Milligrams per liter' || $main_ts->units === 'Gallons per minute') {
  $charachter = 'fish';
  $number_of_frames = 49;
} else {
  $charachter = 'squirrel';
  $number_of_frames = 46;
}
$typical_time_frame = ($time_frame === 'today' || $time_frame === 'week');
// $orb_values = array();
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
    // echo "<!--\n";
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
      // $cur = find_nearest($main_ts->data, $sec);
      // $rv = $number_of_frames - Meter::relativeValue($array_val, $cur, 0, $number_of_frames);
      // $orb_values[] = $rv;
      // echo "\n\n\nrv: {$rv}\ncurrent: {$cur}\n";
      // print_r($array_val);
      $sec += 3600;
    }
    // echo "-->\n";
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
      // $orb_values[] = $number_of_frames - Meter::relativeValue($array_val, find_nearest($main_ts->data, $sec), 0, $number_of_frames);
      $sec += 3600;
    }
  }
  $typical_ts = new TimeSeries($db, $_GET['meter_id'], $from, $now, $res);
  $typical_ts->data($result);
  $typical_ts->dashed(false);
  $typical_ts->fill(false);
  $typical_ts->color('#f39c12');
  if (empty($typical_ts->data)) {
    $typical_ts = $historical_ts;
    $typical_time_frame = false;
  }
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
/**
 * how is this not built in to php
 */
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
/**
 * Fallback to api when data not in mysql
 */
function use_api($db, $bos, $meter_id, $res, $start, $end) {
  $meter_id = intval($meter_id);
  $api_resp = json_decode(
    $bos->getMeter($db->query("SELECT url FROM meters WHERE id = {$meter_id}")->fetchColumn() . '/data', $res, $start, $end), true)['data'];
  return array_map(function($tag) {
    return array(
        'value' => $tag['value'],
        'recorded' => strtotime($tag['localtime'])
    );
  }, $api_resp);
}
/**
 * returns the average of two points in $arr that were recorded before and after $sec
 */
function find_nearest($arr, $sec) { 
  static $i = 0;
  $count = count($arr);
  while ($i < $count) { 
    if ($arr[$i]['recorded'] > $sec) {
      if ($i > 0) {
        $next_time = $arr[$i]['recorded'];
        $last_time = $arr[$i-1]['recorded'];
        $next_val = $arr[$i]['value'];
        $last_val = $arr[$i-1]['value'];
        $frac = Meter::convertRange($sec, $last_time, $next_time, 0, 1);
        // $now_time = $last_time + (($next_time-$last_time)*$frac);
        $now_val = $last_val + (($next_val-$last_val)*$frac);
        if ($i === $count-1) {
          $i = 0;
        } else {
          $i++;
        }
        return $now_val;
      } else { // first index was recorded before $sec
        return $arr[0]['value'];
      }
    }
    if ($i === $count-1) {
      $i = 0;
      return null; // all of the data in this array was recorded before $sec
    } else {
      $i++;
    }
  }
}

function change_resolution_frames($data, $result_size) { // similar to TimeSeries::change_resolution()
    $count = count($data);
    $return = array();
    for ($i = 0; $i < $result_size; $i++) {
      $index_fraction = Meter::convertRange($i, 0, $result_size-1, 0, $count-1);
      $floor = floor($index_fraction); // index of current data point
      $ceil = ceil($index_fraction); // index of next point
      $current_point = $data[$floor];
      $next_point = $data[$ceil];
      $pct = $index_fraction - $floor;
      $diff = $next_point - $current_point;
      if ($current_point === null || $next_point === null) {
        $return[$i] = 25; // pick a neutral frame if there's no data
      } else {
        $return[$i] = round($current_point+($pct*$diff));
      }
    }
    return $return;
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
    <!-- <g id="ground">
      <image overflow="visible" enable-background="new    " width="500" height="500" xlink:href="https://oberlindashboard.org/oberlin/cwd/img/ground.svg" x="745" y="-50">
      </image>
    </g> -->
     <g id="secondground">
      <image overflow="visible" enable-background="new    " width="500" height="500" xlink:href="https://oberlindashboard.org/oberlin/cwd/img/ground.svg" x="745" y="-5">
      </image>
    </g>
    <g id="thirdground">
      <image overflow="visible" enable-background="new    " width="500" height="500" xlink:href="https://oberlindashboard.org/oberlin/cwd/img/ground.svg" x="745" y="40">
      </image>
    </g>
    <g id="house"> 
      <image xmlns="http://www.w3.org/2000/svg" overflow="visible" enable-background="new    " width="200" height="250" id="houses" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="http://67.205.179.187/oberlin/cwd/img/houses.png" x="780" y="180">
      </image>
    </g>
    <g id="bigpowerline">
      <image xmlns="http://www.w3.org/2000/svg" overflow="visible" enable-background="new    " width="100" height="100" xmlns:xlink="http://www.w3.org/1999/xlink" 
      xlink:href="https://oberlindashboard.org/oberlin/cwd/img/powerline.svg" x="730" y="130">
      </image>
    </g>
    <!-- <g id="powerlines1" xmlns="http://www.w3.org/2000/svg" transform="translate(160, -60)">
      <polygon fill="#FFFFFF" points="628.964,206.729 620.326,206.729 621.426,204.984 628.964,204.984             "/>
      <polygon fill="#FFFFFF" points="613.1,204.984 620.575,204.984 619.496,206.729 613.1,206.729             "/>
      <polygon fill="#FFFFFF" points="612.456,204.984 612.456,206.729 609.009,206.729 609.009,204.984             "/>
      <polygon fill="#FFFFFF" points="608.324,206.729 601.928,206.729 600.641,204.984 608.324,204.984             "/>
      <path fill="#FFFFFF" d="M589.365,204.984h10.424l1.308,1.745H589.51C589.15,206.688,589.104,206.106,589.365,204.984z"/>
      <polygon fill="#FFFFFF" points="629.255,200.185 613.1,200.185 613.1,198.502 629.255,198.502             "/>
      <polygon fill="#FFFFFF" points="609.009,200.185 609.009,198.502 612.456,198.502 612.456,200.185             "/>
      <path fill="#FFFFFF" d="M608.324,200.185h-11.13l-0.604-0.894c-0.125-0.166-0.27-0.18-0.436-0.042             c-0.208,0.125-0.242,0.27-0.104,0.437l0.291,0.499h-6.52c-0.401-0.028-0.45-0.589-0.146-1.683h18.647L608.324,200.185             L608.324,200.185z"/>
      <path fill="#231F20" d="M629.909,200.188c0.229,0.033,0.349,0.149,0.349,0.349c0.033,0.233-0.062,0.35-0.299,0.35h-6.333             l-1.795,3.441h7.38c0.298,0,0.415,0.15,0.349,0.45l0.062,0.199v1.746c0.229,0,0.35,0.116,0.35,0.349             c0.033,0.233-0.062,0.35-0.3,0.35H619.9c-3.125,4.324-5.39,7.616-6.772,9.877v65.253c0,0.166-0.083,0.283-0.25,0.349             c-0.166,0.033-0.283,0-0.354-0.1l-0.053-0.199v-64.106l-0.05,0.049c-0.104,0.167-0.25,0.217-0.449,0.15             c-0.229-0.1-0.276-0.25-0.146-0.449l0.646-1.098v-9.728h-3.438v9.379l0.601,1.047c0.134,0.2,0.104,0.366-0.101,0.499             c-0.166,0.133-0.314,0.099-0.447-0.1l-0.05-0.1v64.405c1.098,0.565,2.061,0.599,2.893,0.1             c0.104-0.066,0.199-0.083,0.301-0.049c0.104,0.032,0.167,0.1,0.197,0.199c0.065,0.166,0.032,0.299-0.1,0.399             c-1.064,0.665-2.229,0.665-3.491,0c-0.271,0.133-0.438,0.066-0.498-0.2l-0.146-0.1c-0.139-0.1-0.188-0.233-0.146-0.398             c0.03-0.2,0.133-0.284,0.3-0.25V217c-1.463-2.395-3.708-5.587-6.729-9.578H589.19c-0.139,0-0.229-0.066-0.305-0.2             c-0.466-0.299-0.525-1.047-0.191-2.245c-0.168-0.066-0.229-0.199-0.197-0.399c0-0.167,0.102-0.25,0.301-0.25h10.521             l-2.438-3.441h-7.382c-0.133,0-0.229-0.066-0.301-0.199c-0.438-0.3-0.498-1.048-0.196-2.246             c-0.166-0.066-0.232-0.199-0.198-0.398c0-0.167,0.104-0.25,0.3-0.25h19.247v-2.045c0-0.233,0.116-0.35,0.354-0.35             c0-0.132,0.062-0.25,0.195-0.35c0.526-0.232,1.479-0.349,2.845-0.349c0.229,0,0.364,0.1,0.396,0.299             c0.032,0.334,0.146,0.798,0.354,1.397v-0.648c0-0.233,0.114-0.35,0.353-0.35c0.197-0.033,0.306,0.066,0.306,0.299v2.095             h16.398c0.304,0,0.416,0.15,0.354,0.449l0.104,0.199v1.697L629.909,200.188z M629.261,198.492h-16.155v1.696h16.155V198.492z              M628.962,204.977h-7.53l-1.097,1.746h8.626L628.962,204.977L628.962,204.977z M622.878,200.887h-9.771v3.441h7.928             C621.896,202.798,622.512,201.651,622.878,200.887z M620.585,204.977h-7.479v1.746h6.382L620.585,204.977z M611.759,196.446             l-0.3-1.047c-1.062,0-1.844,0.099-2.343,0.299l-0.104,0.05v2.045h3.441v-1.372c-0.008,0.216-0.124,0.324-0.354,0.324             C611.909,196.779,611.792,196.679,611.759,196.446z M609.016,200.188h3.441v-1.696h-3.441V200.188z M609.016,206.723h3.441             v-1.746h-3.441V206.723z M612.457,200.887h-3.441v3.441h3.441V200.887z M613.105,216.002c1.263-1.962,3.229-4.822,5.934-8.58             h-5.934V216.002z M608.318,198.492h-18.649c-0.3,1.097-0.25,1.663,0.149,1.696h6.521l-0.299-0.499             c-0.133-0.166-0.1-0.316,0.104-0.449c0.166-0.133,0.312-0.117,0.439,0.05l0.604,0.897h11.115L608.318,198.492L608.318,198.492             z M599.792,204.977H589.37c-0.271,1.131-0.216,1.713,0.146,1.746h11.568L599.792,204.977z M601.936,206.723h6.396v-1.746             h-7.688L601.936,206.723z M608.318,200.887h-10.621l2.438,3.441h8.188L608.318,200.887L608.318,200.887z M608.318,215.703             v-8.281h-5.896C605.027,210.847,606.989,213.608,608.318,215.703z"/>
      <path fill="#FFFFFF" d="M612.456,207.415v9.744l-0.644,1.081c-0.146,0.208-0.103,0.359,0.145,0.457             c0.194,0.069,0.346,0.021,0.457-0.146l0.042-0.062V282.6l0.042,0.208h-0.083c-0.042-0.097-0.111-0.166-0.208-0.208             c-0.097-0.027-0.194-0.007-0.291,0.062c-0.831,0.499-1.799,0.464-2.907-0.104v-64.402l0.062,0.083             c0.125,0.208,0.27,0.242,0.436,0.104c0.208-0.125,0.242-0.291,0.104-0.499l-0.604-1.039v-9.391L612.456,207.415             L612.456,207.415z"/>
      <path fill="#FFFFFF" d="M612.456,197.796h-3.447v-2.057l0.104-0.042c0.499-0.194,1.271-0.291,2.347-0.291l0.291,1.039             c0.042,0.235,0.159,0.333,0.353,0.291c0.232,0,0.354-0.094,0.354-0.281L612.456,197.796L612.456,197.796z"/>
    </g>       -->
    <g id="lines" transform="translate(155, -50)">
      <path xmlns="http://www.w3.org/2000/svg" fill="none" stroke="#000000" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="3" d="M624.023,200.2     c64.021,63.933,133.396,96.833,208.1,98.7"/>
    </g>
    <g id="powerlines2">
          <g opacity="0.2">
            <path d="M886.57,361.189v0.104l-0.771,0.848c0.164-0.018,0.246,0.036,0.246,0.164c-0.055-0.019-0.062,0-0.021,0.062         c-0.138,0.055-0.282,0.091-0.477,0.104l-7.312-0.021l-3.578,1.639l8.548,0.054c0.292,0,0.364,0.098,0.219,0.273l-0.812,0.979         c0.229-0.018,0.312,0.036,0.244,0.165c-0.107,0.098-0.271,0.144-0.489,0.144l-11.361-0.062         c-5.681,2.149-9.877,3.814-12.59,4.998l-34.028,36.869c-0.128,0.073-0.291,0.154-0.486,0.246l-0.402-0.104h-0.146         c-0.029,0.067-0.137,0.146-0.3,0.225c-0.966,0.128-1.814,0.255-2.562,0.382c-0.781-0.073-1.477-0.2-2.059-0.382         c-0.454,0.056-0.629,0.018-0.52-0.108h-0.164c-0.188-0.104-0.188-0.21,0-0.301c0.127-0.164,0.328-0.229,0.602-0.188         l34.574-36.896c-0.638-1.188-1.757-2.787-3.354-4.812l-14.609-0.104c-0.2-0.062-0.282-0.104-0.245-0.144         c-0.292-0.104-0.292-0.271,0-0.485c0.146-0.2,0.438-0.4,0.874-0.604c-0.164-0.072-0.2-0.136-0.104-0.188         c-0.021-0.036,0.01-0.045,0.08-0.027c0.06-0.108,0.221-0.146,0.483-0.108l12.289,0.062l-1.199-1.694l-8.566-0.054         c-0.199-0.021-0.291-0.063-0.271-0.139c-0.382-0.187-0.091-0.55,0.874-1.097c-0.184-0.054-0.195-0.116-0.057-0.188         c0.057-0.128,0.221-0.175,0.491-0.144l22.34,0.191l0.955-1.01c0.058-0.128,0.229-0.188,0.548-0.165         c0.021-0.073,0.146-0.144,0.384-0.191c0.689-0.104,1.839-0.152,3.438-0.137c0.221-0.036,0.312,0.009,0.303,0.137         c-0.128,0.146-0.188,0.382-0.188,0.71l0.245-0.328c0.07-0.146,0.271-0.195,0.573-0.164c0.198-0.031,0.28,0.015,0.244,0.143         l-1.019,1.01l18.95,0.107C886.635,360.962,886.697,361.043,886.57,361.189z M864.449,364.057l9.229,0.081         c1.657-0.776,2.896-1.338,3.688-1.666l-11.279-0.136L864.449,364.057z M865.296,362.391l-3.987-0.081l-1.584,1.748l4.021,0.027         L865.296,362.391z M860.517,362.282l-12.344-0.082l1.174,1.771l9.531,0.061L860.517,362.282z M859.042,369.929         c2.386-1.02,6.045-2.442,10.979-4.289l-6.977-0.054L859.042,369.929z M857.458,365.586l-6.909-0.056         c1.383,1.729,2.376,3.141,2.979,4.229L857.458,365.586z"/>
          </g>
          <g transform="translate(160, -65), scale(1)">
            <g>
              <g>
                <polygon fill="#FFFFFF" points="844.979,307.962 833.616,307.962 835.062,305.668 844.979,305.668             "/>
                <polygon fill="#FFFFFF" points="824.112,305.668 833.944,305.668 832.524,307.962 824.112,307.962             "/>
                <polygon fill="#FFFFFF" points="823.266,305.668 823.266,307.962 818.732,307.962 818.732,305.668             "/>
                <polygon fill="#FFFFFF" points="817.831,307.962 809.42,307.962 807.727,305.668 817.831,305.668            "/>
                <path fill="#FFFFFF" d="M792.896,305.668h13.709l1.722,2.294h-15.239C792.614,307.907,792.552,307.143,792.896,305.668z"/>
                <polygon fill="#FFFFFF" points="845.359,299.36 824.112,299.36 824.112,297.147 845.359,297.147             "/>
                <polygon fill="#FFFFFF" points="818.732,299.36 818.732,297.147 823.266,297.147 823.266,299.36             "/>
                <path fill="#FFFFFF" d="M817.831,299.36h-14.638l-0.792-1.175c-0.164-0.218-0.355-0.236-0.574-0.054             c-0.272,0.164-0.318,0.354-0.136,0.573l0.382,0.656h-8.575c-0.528-0.037-0.592-0.774-0.191-2.212h24.524V299.36             L817.831,299.36z"/>
                <path fill="#231F20" d="M846.22,299.363c0.312,0.044,0.459,0.196,0.459,0.459c0.044,0.307-0.086,0.459-0.393,0.459h-8.33             l-2.36,4.524h9.707c0.396,0,0.546,0.197,0.458,0.591l0.065,0.262v2.295c0.307,0,0.46,0.153,0.46,0.459             c0.043,0.306-0.089,0.46-0.396,0.46h-12.852c-4.11,5.684-7.083,10.012-8.918,12.984v85.772c0,0.229-0.11,0.373-0.329,0.459             c-0.219,0.043-0.373,0-0.459-0.131l-0.066-0.262v-84.272l-0.062,0.065c-0.131,0.22-0.329,0.285-0.591,0.197             c-0.312-0.138-0.372-0.329-0.196-0.597l0.854-1.438V308.87h-4.524v12.329l0.787,1.377c0.175,0.262,0.131,0.479-0.131,0.656             c-0.229,0.175-0.415,0.13-0.591-0.137l-0.065-0.132v84.664c1.442,0.744,2.711,0.787,3.805,0.131             c0.131-0.087,0.271-0.104,0.396-0.062c0.132,0.043,0.22,0.131,0.263,0.262c0.088,0.218,0.043,0.396-0.131,0.525             c-1.399,0.875-2.932,0.875-4.592,0c-0.354,0.175-0.566,0.087-0.654-0.271l-0.197-0.13c-0.175-0.131-0.229-0.307-0.188-0.521             c0.045-0.271,0.176-0.368,0.396-0.324V321.46c-1.925-3.146-4.876-7.345-8.854-12.591h-16.33c-0.188,0-0.312-0.088-0.396-0.263             c-0.604-0.396-0.689-1.376-0.262-2.953c-0.219-0.087-0.308-0.262-0.264-0.524c0-0.219,0.132-0.329,0.395-0.329h13.837             l-3.214-4.524h-9.707c-0.173,0-0.305-0.087-0.394-0.262c-0.567-0.394-0.655-1.377-0.271-2.952             c-0.219-0.088-0.307-0.262-0.262-0.524c0-0.22,0.131-0.329,0.394-0.329h25.313v-2.688c0-0.307,0.15-0.459,0.459-0.459             c0-0.175,0.088-0.329,0.263-0.46c0.699-0.306,1.944-0.458,3.737-0.458c0.307,0,0.479,0.131,0.521,0.393             c0.048,0.439,0.193,1.049,0.456,1.837v-0.853c0-0.307,0.152-0.459,0.459-0.459c0.271-0.044,0.396,0.087,0.396,0.393v2.754             H845.7c0.394,0,0.547,0.197,0.459,0.59l0.132,0.262v2.231L846.22,299.363z M845.368,297.134h-21.247v2.229h21.247V297.134z              M844.974,305.659h-9.896l-1.441,2.295h11.353L844.974,305.659L844.974,305.659z M836.974,300.282H824.12v4.524h10.427             C835.683,302.795,836.492,301.287,836.974,300.282z M833.957,305.659h-9.837v2.295h8.394L833.957,305.659z M822.35,294.444             l-0.396-1.376c-1.398,0-2.425,0.13-3.081,0.393l-0.132,0.066v2.688h4.521v-1.804c-0.01,0.285-0.162,0.426-0.459,0.426             C822.546,294.883,822.392,294.75,822.35,294.444z M818.741,299.363h4.521v-2.229h-4.521V299.363z M818.741,307.954h4.521             v-2.295h-4.521V307.954z M823.267,300.282h-4.521v4.524h4.521V300.282z M824.12,320.152c1.661-2.579,4.262-6.338,7.804-11.278             h-7.804V320.152z M817.824,297.134h-24.526c-0.396,1.442-0.329,2.186,0.196,2.229h8.591l-0.394-0.656             c-0.188-0.217-0.145-0.415,0.131-0.59c0.219-0.174,0.415-0.153,0.59,0.066l0.787,1.18h14.624L817.824,297.134L817.824,297.134             z M806.61,305.659h-13.708c-0.354,1.487-0.283,2.252,0.188,2.295h15.225L806.61,305.659z M809.43,307.954h8.396v-2.295             h-10.104L809.43,307.954z M817.824,300.282h-13.969l3.214,4.524h10.755V300.282z M817.824,319.758v-10.885h-7.738             C813.496,313.376,816.075,317.005,817.824,319.758z"/>
                <path fill="#FFFFFF" d="M823.266,308.863v12.809l-0.847,1.42c-0.182,0.273-0.118,0.479,0.191,0.604             c0.255,0.09,0.455,0.021,0.601-0.191l0.055-0.083V407.7l0.062,0.271h-0.109c-0.055-0.127-0.146-0.218-0.271-0.271             c-0.128-0.036-0.268-0.009-0.396,0.082c-1.092,0.646-2.354,0.604-3.812-0.146v-84.654l0.082,0.104             c0.164,0.273,0.354,0.318,0.562,0.136c0.273-0.165,0.318-0.382,0.146-0.648l-0.792-1.366v-12.344L823.266,308.863             L823.266,308.863z"/>
                <path fill="#FFFFFF" d="M823.266,296.219h-4.521v-2.704l0.137-0.054c0.655-0.256,1.688-0.383,3.086-0.383l0.382,1.366             c0.062,0.309,0.211,0.437,0.471,0.382c0.312,0,0.459-0.124,0.469-0.37L823.266,296.219L823.266,296.219z"/>
              </g>
            </g>
          </g>
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
    <g id="staticbanknote">
      <image overflow="visible" enable-background="new    " width="20" height="20" xlink:href="https://oberlindashboard.org/oberlin/cwd/img/banknote.svg" x="750" y="170">
      </image> 
      <image overflow="visible" enable-background="new    " width="20" height="20" xlink:href="https://oberlindashboard.org/oberlin/cwd/img/banknote.svg" x="770" y="140">
      </image>
      <image overflow="visible" enable-background="new    " width="20" height="20" xlink:href="https://oberlindashboard.org/oberlin/cwd/img/banknote.svg" x="775" y="185">
      </image>
      <image overflow="visible" enable-background="new    " width="20" height="20" xlink:href="https://oberlindashboard.org/oberlin/cwd/img/banknote.svg" x="790" y="120">
      </image>
      <image overflow="visible" enable-background="new    " width="20" height="20" xlink:href="https://oberlindashboard.org/oberlin/cwd/img/banknote.svg" x="795" y="160">
      </image>
      <image overflow="visible" enable-background="new    " width="20" height="20" xlink:href="https://oberlindashboard.org/oberlin/cwd/img/banknote.svg" x="800" y="100">
      </image>
      <image overflow="visible" enable-background="new    " width="20" height="20" xlink:href="https://oberlindashboard.org/oberlin/cwd/img/banknote.svg" x="820" y="160">
      </image>
      <image overflow="visible" enable-background="new    " width="20" height="20" xlink:href="https://oberlindashboard.org/oberlin/cwd/img/banknote.svg" x="830" y="130">
      </image>
      <image overflow="visible" enable-background="new    " width="20" height="20" xlink:href="https://oberlindashboard.org/oberlin/cwd/img/banknote.svg" x="830" y="100">
      </image>
      <image overflow="visible" enable-background="new    " width="20" height="20" xlink:href="https://oberlindashboard.org/oberlin/cwd/img/banknote.svg" x="850" y="160">
      </image>
      <image overflow="visible" enable-background="new    " width="20" height="20" xlink:href="https://oberlindashboard.org/oberlin/cwd/img/banknote.svg" x="865" y="115">
      </image>
      <image overflow="visible" enable-background="new    " width="20" height="20" xlink:href="https://oberlindashboard.org/oberlin/cwd/img/banknote.svg" x="870" y="170">
      </image>
      <image overflow="visible" enable-background="new    " width="20" height="20" xlink:href="https://oberlindashboard.org/oberlin/cwd/img/banknote.svg" x="870" y="80">
      </image>
      <image overflow="visible" enable-background="new    " width="20" height="20" xlink:href="https://oberlindashboard.org/oberlin/cwd/img/banknote.svg" x="890" y="140">
      </image>
       <image overflow="visible" enable-background="new    " width="20" height="20" xlink:href="https://oberlindashboard.org/oberlin/cwd/img/banknote.svg" x="900" y="100">
      </image>
      <image overflow="visible" enable-background="new    " width="20" height="20" xlink:href="https://oberlindashboard.org/oberlin/cwd/img/banknote.svg" x="910" y="180">
      </image>
      <image overflow="visible" enable-background="new    " width="20" height="20" xlink:href="https://oberlindashboard.org/oberlin/cwd/img/banknote.svg" x="920" y="150">
      </image>
      <image overflow="visible" enable-background="new    " width="20" height="20" xlink:href="https://oberlindashboard.org/oberlin/cwd/img/banknote.svg" x="930" y="120">
      </image>
      <image overflow="visible" enable-background="new    " width="20" height="20" xlink:href="https://oberlindashboard.org/oberlin/cwd/img/banknote.svg" x="950" y="110">
      </image> 
      <image overflow="visible" enable-background="new    " width="20" height="20" xlink:href="https://oberlindashboard.org/oberlin/cwd/img/banknote.svg" x="950" y="175">
      </image>  
      <image overflow="visible" enable-background="new    " width="20" height="20" xlink:href="https://oberlindashboard.org/oberlin/cwd/img/banknote.svg" x="950" y="140">
      </image>  
      <image overflow="visible" enable-background="new    " width="20" height="20" xlink:href="https://oberlindashboard.org/oberlin/cwd/img/banknote.svg" x="970" y="170">
      </image>
      <image overflow="visible" enable-background="new    " width="20" height="20" xlink:href="https://oberlindashboard.org/oberlin/cwd/img/banknote.svg" x="970" y="100">
      </image>
    </g>
  </g>
  <g id="empathetic-char">
    <?php
    if ($charachter === 'fish') {
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
  function convertRange(val, old_min, old_max, new_min, new_max) {
    if (old_max == old_min) {
      return 0;
    }
    return (((new_max - new_min) * (val - old_min)) / (old_max - old_min)) + new_min;
  }
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

  
  var smokespeed = 0;
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

    var banknote = TweenMax.to($('#banknote > image'), 2, {y: "300px", x: "10px", ease: Power0.easeNone, repeat: -1, repeatDelay: 2});

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
    // if ($typical_time_frame) {
    //   $charachter_moods = change_resolution_frames($orb_values, 750);
    // } else {
    if ($typical_time_frame) {
      $relativized_points = $typical_ts->circlepoints;
    } else {
      $relativized_points = $historical_ts->circlepoints;
    }
    $diff_min = PHP_INT_MAX;
    $diff_max = PHP_INT_MIN;
    // calculate the $diff_min/$diff_max
    for ($i=0; $i < count($main_ts->circlepoints); $i++) {
      $scaled = round($pct_through*$i);
      $d = $main_ts->circlepoints[$i][1] - $relativized_points[$scaled][1];
      $charachter_moods[] = $d; // save difference to scale later
      if ($d > $diff_max) {
        $diff_max = $d;
      }
      if ($d < $diff_min) {
        $diff_min = $d;
      }
    }
    // scale the difference between two points to a gif frame
    for ($i=0; $i < count($charachter_moods); $i++) {
      if ($charachter_moods[$i] <= 0) { // current point is below typical
        $charachter_moods[$i] = round(Meter::convertRange(($diff_max-abs($charachter_moods[$i])), $diff_min, $diff_max, 0, ceil($number_of_frames/2)));
      } else {
        $charachter_moods[$i] = round(Meter::convertRange(($charachter_moods[$i]), $diff_min, $diff_max, floor($number_of_frames/2), $number_of_frames));
      }
      // $logrthm = log(abs($charachter_moods[$i]));
      // echo "$logrthm \n\n";
      // $charachter_moods[$i] = round(Meter::convertRange($logrthm, $diff_min, $diff_max, 0, $number_of_frames));
    }
    // }
    echo "var charachter_moods = " . json_encode($charachter_moods) . ";\n";
  ?>
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
    smokespeed = current_frame;
    smokespeed = convertRange(smokespeed, 0, Math.max.apply(null, charachter_moods), 0, 5);
    var smoke = $('#smoke').children();
    var newsmoke = TweenMax.to($('#smoke > image'), smokespeed, {y: "-60px", x: "20px", scaleX: 2, scaleY: 1.5, opacity: 0, ease:Power0.easeNone, repeat: -1, repeatDelay: 3});
    //console.log("Here is the smokespeed: " + smokespeed);
    // console.log("Here is the number of frames: " + frames.length);

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
    //console.log('play_data');
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
    //console.log('play_movie');
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