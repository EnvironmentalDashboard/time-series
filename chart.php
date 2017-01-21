<?php
error_reporting(-1);
ini_set('display_errors', 'On');
header('Content-Type: image/svg+xml; charset=UTF-8'); // We'll be outputting a SVG
require '../includes/db.php';
require '../includes/class.TimeSeries.php';
require 'includes/vars.php'; // Including in seperate file to keep this file clean
require 'includes/really-long-switch.php';
?>
<svg height="<?php echo $height; ?>" width="<?php echo $width; ?>" viewBox="0 0 <?php echo $width; ?> <?php echo $height; ?>" class="chart" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
<?php
// var_dump($from);var_dump($now);exit;
$main_ts = new TimeSeries($db, $_GET['meter_id'], $from, $now, $res); // The main timeseries
$secondary_ts = new TimeSeries($db, $_GET['meter_id2'], $from, $now, $res); // "Second variable" timeseries
$historical_ts = new TimeSeries($db, $_GET['meter_id'], $double_time, $from, $res); // Historical data of main
$meter = new Meter($db);
$typical_time_frame = ($time_frame === 'today' || $time_frame === 'week');

if ($typical_time_frame) {
  $stmt = $db->query('SELECT grouping, npoints FROM meters WHERE id = '.intval($_GET['meter_id']));
  $settings = $stmt->fetch();
  $result = array();
  $recorded_vals = $from;
  $last_data = null;
  if ($time_frame === 'today') { // Get the typical data for today
    $group = implode(',', $meter->currentGrouping($settings['grouping']));
    $stmt = $db->prepare(
    'SELECT value, recorded FROM meter_data
    WHERE meter_id = ? AND value IS NOT NULL AND resolution = ?
    AND DAYOFWEEK(FROM_UNIXTIME(recorded)) IN ('.$group.')
    ORDER BY recorded DESC LIMIT ' . intval($settings['npoints']*24));
    $stmt->execute(array($_GET['meter_id'], 'hour'));
    $typical_data = $stmt->fetchAll();
    for ($i = 0; $i < 96; $i++) { // 15 min res over day = 96 points
      $buffer = array();
      $hour = date('G', $recorded_vals);
      foreach ($typical_data as $value) { // Get all the data that was recorded in the same hour as the data point we're plotting
        if ($hour === date('G', $value['recorded'])) {
          $buffer[] = $value['value'];
        }
      }
      $median = array_sum($buffer)/count($buffer);//median($buffer);
      if ($last_data !== null) { // Interpolate next 45 mins -- 3 data points -- worth of data
        $diff = ($last_data - $median)/4;
        $result[] = array('recorded' => $recorded_vals, 'value' => $last_data+$diff);
        $recorded_vals += 900; // 900s = 15min
        $result[] = array('recorded' => $recorded_vals, 'value' => $last_data+($diff*2));
        $recorded_vals += 900;
        $result[] = array('recorded' => $recorded_vals, 'value' => $last_data+($diff*3));
        $recorded_vals += 900;
        $i += 3;
      }
      $result[] = array('recorded' => $recorded_vals, 'value' => $median);
      $last_data = $median;
      $recorded_vals += 900;
    }
  } else { // week
    $group = $meter->grouping($settings['grouping']);
    $stmt = $db->prepare(
    'SELECT value, recorded FROM meter_data
    WHERE meter_id = ? AND value IS NOT NULL AND resolution = ?
    ORDER BY DAYOFWEEK(FROM_UNIXTIME(recorded)) ASC');
    $stmt->execute(array($_GET['meter_id'], 'hour'));
    $typical_data = $stmt->fetchAll();
    for ($i = 0; $i < 168; $i++) { // 1 hour res over week = 168 points
      $buffer = array();
      $hour = date('G', $recorded_vals);
      $day = date('N', $recorded_vals);
      $days = $group[recursive_array_search($day, $group)]; // Get group of days which contains the current day
      foreach ($typical_data as $value) { // Get all the data that was recorded in the same hour as the data point we're plotting
        if ($hour === date('G', $value['recorded']) && in_array(date('N', $value['recorded']), $days)) {
          $buffer[] = $value['value'];
        }
      }
      $median = array_sum($buffer)/count($buffer);//median($buffer);
      $result[] = array('recorded' => $recorded_vals, 'value' => $median);
      $recorded_vals += 3600;
    }
  }
  $typical_ts = new TimeSeries($db, $_GET['meter_id'], $from, $now, $res, null, null, $result);
  $typical_ts->dashed(false);
  $typical_ts->fill(false);
  $typical_ts->color('#f39c12');
}

$main_ts->dashed( (!empty($_GET['dasharr1'])) ? true : false );
$historical_ts->dashed( (!empty($_GET['dasharr2'])) ? true : false );
$secondary_ts->dashed( (!empty($_GET['dasharr3'])) ? true : false );

$main_ts->fill( (isset($_GET['fill1']) && $_GET['fill1'] === 'off') ? false : true );
$historical_ts->fill( (isset($_GET['fill2']) && $_GET['fill2'] === 'off') ? false : true );
$secondary_ts->fill( (isset($_GET['fill3']) && $_GET['fill3'] === 'off') ? false : true );

$current_graph_color = (!empty($_GET['color1'])) ? $_GET['color1'] : '#2ecc71';
$historical_graph_color = (!empty($_GET['color2'])) ? $_GET['color2'] : '#bdc3c7';
$var2_graph_color = (!empty($_GET['color3'])) ? $_GET['color3'] : '#33A7FF';
$main_ts->color($current_graph_color);
$historical_ts->color($historical_graph_color);
$secondary_ts->color($var2_graph_color);

$main_ts->setMin(); $main_ts->setMax();
$historical_ts->setMin(); $historical_ts->setMax();
$secondary_ts->setMin(); $secondary_ts->setMax();
// If the units of both timeseries are the same, scale the charts to each other
$main_ts->setUnits();
$secondary_ts->setUnits();
if ($secondary_ts->units === $main_ts->units && $main_ts->units !== null) {
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
if ($typical_time_frame) { $typical_ts->setMin($min); $typical_ts->setMax($max); }
$main_ts->yAxis();
$historical_ts->yAxis();
$secondary_ts->yAxis();

$main_ts->setTimes();
$name1 = $main_ts->getName();
$name2 = $secondary_ts->getName();
$name = (!empty($_GET['name'])) ? $_GET['name'] : $name2 . ' vs. ' . $name1;
// URLs for buttons on bottom
$curr_url = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
parse_str(parse_url($curr_url, PHP_URL_QUERY), $tmp);
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
function recursive_array_search($needle,$haystack) {
  foreach($haystack as $key=>$value) {
    $current_key=$key;
    if($needle===$value || (is_array($value) && recursive_array_search($needle,$value) !== false)) {
      return $current_key;
    }
  }
  return false;
}
?>
<defs>
  <linearGradient id="shadow">
    <stop class="stop1" stop-color="#777" offset="0%"/>
    <stop class="stop2" stop-color="#777" offset="100%"/>
  </linearGradient>
</defs>
<style>
/* <![CDATA[ */
@import url(https://fonts.googleapis.com/css?family=Roboto:400,700);
@keyframes anim {
  0% { width: 100%; }
  100% { width: 0%; }
}
.anim { animation: anim 1s cubic-bezier(.17,.67,.83,.67) 1; animation-fill-mode: forwards; }
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
#kwh, #kwh-active, #co2, #co2-active, #money, #money-active {
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
    <circle cx="-10" cy="0" id="historical-circle" <?php echo $circle_size . $historical_graph_color . '"'; ?> />
  </g>

  <!-- Typical chart -->
  <g id="typical-chart" style="opacity: 1;">
    <?php if ($typical_time_frame) {$typical_ts->printChart($graph_height, $graph_width, $graph_offset, $historical_ts->yaxis_min, $historical_ts->yaxis_max);} ?>
    <circle cx="-10" cy="0" id="typical-circle" <?php echo $circle_size . '#f39c12' . '"'; ?> />
  </g>

  <!-- Second variable overlay -->
  <g id="second-chart" style="opacity: 0;">
    <?php $secondary_ts->printChart($graph_height, $graph_width * $pct_through, $graph_offset, $secondary_ts->yaxis_min, $secondary_ts->yaxis_max); ?>
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

  <g id="y-axis-left" text-anchor="start">
    <?php
    $chart_min = $graph_offset;
    $chart_max = $graph_height + $graph_offset;
    $interval = ($chart_max - $chart_min)/count($main_ts->yaxis);
    echo "<!-- {$main_ts->min}";
    var_dump($main_ts->yaxis);
    echo " {$main_ts->max} -->";
    foreach ($main_ts->yaxis as $y) {
      echo "<text x='5' y='{$chart_max}' font-size='13' fill='{$font_color}'>{$y}</text>";
      $chart_max -= $interval;
    }
    ?>
    <text x="5" y="<?php echo $graph_height + $graph_offset + 10; ?>" font-size="10" fill="<?php echo $font_color; ?>"><?php echo $main_ts->units; ?></text>
  </g>
  <?php if (isset($secondary_ts->yaxis)) { ?>
  <g id="y-axis-right" text-anchor="end" style="opacity: 0">
    <?php
    $chart_min = $graph_offset;
    $chart_max = $graph_height + $graph_offset;
    $interval = ($chart_max - $chart_min)/count($secondary_ts->yaxis);
    foreach ($secondary_ts->yaxis as $y) {
      echo "<text x='".($graph_width-5)."' y='{$chart_max}' font-size='13' fill='{$font_color}'>{$y}</text>";
      $chart_max -= $interval;
    }
    ?>
    <text x="<?php echo $graph_width - 5; ?>" y="<?php echo $graph_height + $graph_offset + 10; ?>" font-size="10" fill="<?php echo $font_color; ?>"><?php echo $secondary_ts->units; ?></text>
  </g>
  <?php } ?>

  <!-- Dates at bottom -->
  <?php echo $dates; ?>

  <!-- Current time -->
  <rect width="<?php echo $width * 0.1; ?>px" height="<?php echo $height * 0.06; ?>px" x="-9999" y="<?php echo $height * 0.075; ?>" style="fill:<?php echo $font_color; ?>;" id="current-time-rect" />
  <text fill="<?php echo $primary_color; ?>" id="current-time-text" text-anchor="middle"
        x="-9999" y="<?php echo $height * 0.115; ?>"
        font-size="12"></text>

  <!-- Main button -->
  <g id="layer-btn" style="cursor: pointer;" class="noselect">
    <rect width="<?php echo $width * 0.1; ?>px" height="<?php echo $height * 0.075; ?>px" x="0" y="0" fill="<?php echo '#2196F3';//$primary_color; ?>" stroke="<?php echo $font_color; ?>" stroke-width="0.5" style="stroke-dasharray:0,<?php echo ($width * 0.1) . ',' . (($width*0.1) + ($height * 0.075)) . ',' . ($height * 0.075); ?>" />
    <text x="1.2%" y="5%" font-size="12" id="show-less" fill="#ECEFF1" style="font-weight: 400">SHOW MORE</text>
  </g>
  <g id="dropdown" style="opacity: 0;">
    <rect width="<?php echo $width * 0.175; ?>px" height="<?php echo ($typical_time_frame) ? $height * 0.185 : $height * 0.12; ?>px" x="0" y="<?php echo ($height * 0.075); ?>" fill="<?php echo $font_color; ?>" stroke="<?php echo $font_color; ?>" stroke-width="1" />
    <text style="cursor:pointer" id="historical" x="1.25%" y="<?php echo ($height * 0.075) + 15; ?>" font-size="12" fill="<?php echo $primary_color; ?>"><?php echo ($show_hist) ? 'Hide' : 'Show'; ?> previous <?php
      if ($time_frame === 'live') { echo 'hour'; }
      elseif ($time_frame === 'today') { echo 'day'; }
      else { echo $time_frame; }
      ?></text>
    <line x1="0" y1="<?php echo ($height * 0.075) + 25; ?>" x2="<?php echo ($width * 0.175); ?>" y2="<?php echo ($height * 0.075) + 25; ?>" stroke="<?php echo $primary_color; ?>" stroke-width="1" />
    <text style="cursor:pointer" id="second" x="1.25%" y="<?php echo ($height * 0.075) + 40; ?>" font-size="12" fill="<?php echo $primary_color; ?>">Show <?php echo $name2; ?></text>
    <?php if ($typical_time_frame) { ?>
    <line x1="0" y1="<?php echo ($height * 0.075) + 50; ?>" x2="<?php echo ($width * 0.175); ?>" y2="<?php echo ($height * 0.075) + 50; ?>" stroke="<?php echo $primary_color; ?>" stroke-width="1" />
    <text style="cursor:pointer" id="typical" x="1.25%" y="<?php echo ($height * 0.075) + 65; ?>" font-size="12" fill="<?php echo $primary_color; ?>">Show typical</text>
    <?php } ?>
  </g>

  <!-- Sidebar -->
  <rect width="<?php echo $width - $graph_width; ?>px" height="<?php echo $height; ?>px" x="<?php echo $graph_width ?>" y="0" style="fill:<?php echo $primary_color; ?>;" />
  <text id="suggestion" text-anchor="middle" x="<?php echo $graph_width + (($width - $graph_width)/2) ?>" y="200" font-size="18" width="<?php echo $width - ($graph_width+20); ?>px">Move your mouse over the data</text>
  <!-- <image display="none" id="error" xlink:href='images/error.svg' height="120px" width="150px" y="50" x="<?php //echo $graph_width + (($width - $graph_width)/4) ?>" /> -->
  <text text-anchor="middle" x="<?php echo $graph_width + (($width - $graph_width)/2) ?>" y="200" font-size="15" width="<?php echo $width - ($graph_width+20); ?>px" display="none" id="error-msg">Data are not available for this point</text>
  <?php
  if ($main_ts->units === 'Gallons / hour' || $main_ts->units === 'Liters / hour' || $main_ts->units === 'Liters') {
    $number_of_frames = 59;
    for ($i = 0; $i <= $number_of_frames; $i++) { 
      echo "<image id='frame_{$i}' xlink:href='images/second_frames/frame_{$i}.gif' height='100%' width='";
      echo $width - $graph_width . "px' x='";
      echo $graph_width . "' ";
      echo ($i !== 0) ? 'display="none"' : '';
      echo ' y="0" />';
    }
  } else {
    $number_of_frames = 46;
    for ($i = 0; $i <= $number_of_frames; $i++) { 
      echo "<image id='frame_{$i}' xlink:href='images/main_frames/frame_{$i}.gif' height='100%' width='";
      echo $width - $graph_width . "px' x='";
      echo $graph_width . "' ";
      echo ($i !== 0) ? 'display="none"' : '';
      echo ' y="0" />';
    }
  }
  ?>
  <image id='movie' xlink:href='' height='100%' width='<?php echo $width - $graph_width ?>px' x="<?php echo $graph_width ?>" y="0" display="none" />
  <text id="current-value-container" text-anchor="middle" fill="<?php echo $primary_color; ?>" x="<?php echo $width * 0.88; ?>" y="<?php echo $height * 0.2; ?>" font-size="20"><tspan id="current-value" font-size="50"></tspan> <tspan x="<?php echo $width * 0.88; ?>" dy="1.2em"><?php echo $main_ts->units; ?></tspan></text>
  <?php if ($main_ts->units === 'Kilowatts') { ?>
  <rect height='60' width='<?php echo $width - $graph_width - 30 ?>px' x="<?php echo $graph_width + 15 ?>" y="<?php echo $graph_height - 30 ?>" fill="#ECEFF1" />
  <text id="accum-label" text-anchor="middle" fill="#333" x="<?php echo $width * 0.88; ?>" y="<?php echo $height * 0.8; ?>" font-size="15"><tspan id="accum-label-value" font-size="30">0</tspan> <tspan x="<?php echo $width * 0.88; ?>" dy="1.2em" id="accum-label-units">Kilowatt-hours <?php echo $so_far; ?></tspan></text>
  <?php } ?>
  <rect width="20px" height="<?php echo $height; ?>px" x="<?php echo $graph_width ?>" y="0" fill="url(#shadow)" />

  <!-- Topbar -->
  <rect width="<?php echo $width * 0.9; ?>px" height="<?php echo $height * 0.075; ?>px" x="<?php echo $width * 0.1; ?>" y="0" style="fill:<?php echo '#ECEFF1';//$primary_color; ?>;stroke:<?php echo $font_color; ?>;
               stroke-dasharray:0,<?php echo (($width * 0.9) + ($height * 0.075)) . ',' . ($width * 0.9) . ',' . ($height * 0.075); ?>;" stroke-width="0.5" />
  <!-- <line x1="0" y1="0" x2="<?php echo $width; ?>px" y2="0" stroke-width="0.5" stroke="<?php echo $font_color; ?>"/> -->
  <text fill="<?php echo $font_color; ?>" id="legend"
        x="<?php echo ($width * 0.12); ?>" y="<?php echo $height * 0.049; ?>"
        font-size="13" style="font-weight: 400">
        <tspan dy="8" style='font-size: 40px;fill: <?php echo $current_graph_color; ?>'>&#9632;</tspan>
        <tspan dy="-8"><?php echo $name1; ?></tspan>
        &#160;&#160;&#160;
        <?php if ($typical_time_frame) { ?>
          <tspan dy="8" style='font-size: 40px;fill: #f39c12'>&#9632;</tspan>
          <tspan dy="-8">Typical Use</tspan>
          &#160;&#160;&#160;
        <?php } ?>
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
        &#160;&#160;&#160;
        <tspan dy="8" style='font-size: 40px;fill: <?php echo $var2_graph_color; ?>'>&#9632;</tspan>
        <tspan dy="-8"><?php echo $name2; ?></tspan>
        </text>

  <!-- Bottom bar -->
  <rect width="100%" height="<?php echo $height * 0.075; ?>px" x="0" y="<?php echo $height * 0.925; ?>" style="fill:<?php echo '#ECEFF1';//$primary_color; ?>;" />
  <line x1="0" y1="<?php echo $height; ?>" x2="<?php echo $width; ?>px" y2="<?php echo $height; ?>" stroke-width="0.5" stroke="<?php echo $font_color; ?>"/>
  <line x1="0" y1="<?php echo $height - ($height * 0.075); ?>" x2="<?php echo $width; ?>px" y2="<?php echo $height - ($height * 0.075); ?>" stroke-width="0.5" stroke="<?php echo $font_color; ?>"/>
  <a xlink:href="<?php echo str_replace('&', '&amp;', $url1h); ?>">
    <?php if ($time_frame !== 'live') { ?>
    <text fill="<?php echo $font_color; ?>" x="<?php echo $width * 0.15; ?>" y="<?php echo $height * 0.975; ?>" font-size="14" style="font-weight:400">Hour</text>
    <?php } else { ?>
      <rect width="<?php echo $width * 0.1; ?>px" height="3" x="<?php echo $width * 0.12; ?>" y="<?php echo $height * 0.925; ?>" style="fill:#2196F3" />
      <text fill="#2196F3" x="<?php echo $width * 0.155; ?>" y="<?php echo $height * 0.975; ?>" font-size="14" style="font-weight:400">Hour</text>
    <?php } ?>
  </a>
  <a xlink:href="<?php echo str_replace('&', '&amp;', $url1d); ?>">
    <?php if ($time_frame !== 'today') { ?>
    <text fill="<?php echo $font_color; ?>" x="<?php echo $width * 0.25; ?>" y="<?php echo $height * 0.975; ?>" font-size="14" style="font-weight:400">Today</text>
    <?php } else { ?>
      <rect width="<?php echo $width * 0.1; ?>px" height="3" x="<?php echo $width * 0.222; ?>" y="<?php echo $height * 0.925; ?>" style="fill:#2196F3;" />
      <text fill="#2196F3" x="<?php echo $width * 0.25; ?>" y="<?php echo $height * 0.975; ?>" font-size="14" style="font-weight:400">Today</text>
    <?php } ?>
  </a>
  <a xlink:href="<?php echo str_replace('&', '&amp;', $url1w); ?>">
    <?php if ($time_frame !== 'week') { ?>
    <text fill="<?php echo $font_color; ?>" x="<?php echo $width * 0.35; ?>" y="<?php echo $height * 0.975; ?>" font-size="14" style="font-weight:400">Week</text>
    <?php } else { ?>
      <rect width="<?php echo $width * 0.1; ?>px" height="3" x="<?php echo $width * 0.322; ?>" y="<?php echo $height * 0.925; ?>" style="fill:#2196F3;" />
      <text fill="#2196F3" x="<?php echo $width * 0.355; ?>" y="<?php echo $height * 0.975; ?>" font-size="14" style="font-weight:400">Week</text>
    <?php } ?>
  </a>
  <a xlink:href="<?php echo str_replace('&', '&amp;', $url1m); ?>">
    <?php if ($time_frame !== 'month') { ?>
    <text fill="<?php echo $font_color; ?>" x="<?php echo $width * 0.45; ?>" y="<?php echo $height * 0.975; ?>" font-size="14" style="font-weight:400">Month</text>
    <?php } else { ?>
      <rect width="<?php echo $width * 0.1; ?>px" height="3" x="<?php echo $width * 0.425; ?>" y="<?php echo $height * 0.925; ?>" style="fill:#2196F3;" />
      <text fill="#2196F3" x="<?php echo $width * 0.455; ?>" y="<?php echo $height * 0.975; ?>" font-size="14" style="font-weight:400">Month</text>
    <?php } ?>
  </a>
  <a xlink:href="<?php echo str_replace('&', '&amp;', $url1y); ?>">
    <?php if ($time_frame !== 'year') { ?>
    <text fill="<?php echo $font_color; ?>" x="<?php echo $width * 0.55; ?>" y="<?php echo $height * 0.975; ?>" font-size="14" style="font-weight:400">Year</text>
    <?php } else { ?>
      <rect width="<?php echo $width * 0.1; ?>px" height="3" x="<?php echo $width * 0.518; ?>" y="<?php echo $height * 0.925; ?>" style="fill:#2196F3;" />
      <text fill="#2196F3" x="<?php echo $width * 0.555; ?>" y="<?php echo $height * 0.975; ?>" font-size="14" style="font-weight:400">Year</text>
    <?php } ?>
  </a>
  <!-- accum_btnulation selection -->
  <?php if ($main_ts->units === 'Kilowatts') { ?>
  <g id="kwh" style="display: none">
    <text fill="<?php echo $font_color; ?>" x="<?php echo $graph_width + 70; ?>" y="<?php echo $height * 0.975; ?>" font-size="14" style="font-weight:400">kWh</text>
  </g>
  <g id="kwh-active">
    <rect width="<?php echo $width * 0.05; ?>px" height="3" x="<?php echo $graph_width + 60; ?>" y="<?php echo $height * 0.925; ?>" style="fill:#2196F3;" />
    <text fill="#2196F3" x="<?php echo $graph_width + 70; ?>" y="<?php echo $height * 0.975; ?>" font-size="14" style="font-weight:400">kWh</text>
  </g>
  <g id="co2">
    <text fill="<?php echo $font_color; ?>" x="<?php echo $graph_width + 120; ?>" y="<?php echo $height * 0.975; ?>" font-size="14" style="font-weight:400">CO2</text>
  </g>
  <g id="co2-active" style="display: none">
    <rect width="<?php echo $width * 0.05; ?>px" height="3" x="<?php echo $graph_width + 110; ?>" y="<?php echo $height * 0.925; ?>" style="fill:#2196F3;" />
    <text fill="#2196F3" x="<?php echo $graph_width + 120; ?>" y="<?php echo $height * 0.975; ?>" font-size="14" style="font-weight:400">CO2</text>
  </g>
  <g id="money">
    <text fill="<?php echo $font_color; ?>" x="<?php echo $graph_width + 175; ?>" y="<?php echo $height * 0.975; ?>" font-size="14" style="font-weight:400">$</text>
  </g>
  <g id="money-active" style="display: none">
    <rect width="<?php echo $width * 0.04; ?>px" height="3" x="<?php echo $graph_width + 160; ?>" y="<?php echo $height * 0.925; ?>" style="fill:#2196F3;" />
    <text fill="#2196F3" x="<?php echo $graph_width + 175; ?>" y="<?php echo $height * 0.975; ?>" font-size="14" style="font-weight:400">$</text>
  </g>
  <?php } ?>

  <script type="text/javascript" xlink:href="js/jquery.min.js"/>
  <script type="text/javascript">
  // <![CDATA[
  //console.log(<?php //echo json_encode($log) ?>);
  /* BUTTON/MENU FUNCTIONALITY */
  var accum_btn = $('#kwh');
  var active_accum_btn = $('#kwh-active');
  $('#kwh').on('click', function() {
    active_accum_btn.css('display', 'none');
    accum_btn.css('display', '');
    $('#kwh').css('display', 'none');
    $('#kwh-active').css('display', '');
    accum_btn = $('#kwh');
    active_accum_btn = $('#kwh-active');
    $('#accum-label-units').text('Kilowatt-hours <?php echo $so_far; ?>');
    var elapsed = (current_timestamps[current_timestamps.length-1]-current_timestamps[0])/3600;
    var kw = 0;
    var kw_count = 0;
    for (var i = current_timestamps.length-1; i >= 0; i--) {
      kw += raw_data[i];
      kw_count++;
    }
    $('#accum-label-value').text(Math.round(elapsed*(kw/kw_count)).toLocaleString());
  });
  $('#co2').on('click', function() {
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
  });
  $('#money').on('click', function() {
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
  });
  $('#layer-btn').on("click", function() {
    var dropdown = $('#dropdown');
    if (dropdown.css('opacity') === '0') {
      dropdown.css('opacity', '1');
      $('#show-less').text('SHOW LESS');
    }
    else {
      dropdown.css('opacity', '0');
      $('#show-less').text('SHOW MORE');
    }
  });
  $('#historical').on("click", function() {
    var historical = $('#historical-chart');
    if (historical.css('opacity') === '0') {
      historical.css('opacity', '1');
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
  $('#second').on("click", function() {
    var second = $('#second-chart');
    if (second.css('opacity') === '0') {
      $('#y-axis-right').css('opacity', '1');
      second.css('opacity', '1');
      curtain('curtain');
      $(this).text('Hide <?php echo $name2; ?>');
    }
    else {
      $('#y-axis-right').css('opacity', '0');
      second.css('opacity', '0');
      $(this).text('Show <?php echo $name2; ?>');
    }
  });
  $('#typical').on("click", function() {
    var typ = $('#typical-chart');
    if (typ.css('opacity') === '0') {
      typ.css('opacity', '1');
      curtain('curtain');
      $(this).text('Hide typical');
    }
    else {
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
  var diff_min = Number.MAX_VALUE;
  var diff_max = 0;
  for (var i = current_points.length - 1; i >= 0; i--) {
    var d = current_points[i][1] - relativized_points[i][1]; // can use same index for both b/c they're the same resolution
    if (d > diff_max) {
      diff_max = d;
    }
    if (d < diff_min) {
      diff_min = d;
    }
  }
  $(svg).one('mousemove', function() {
    $('#suggestion').attr('display', 'none');
    $('#error-msg').attr('display', '');
  });
  $(svg).on('mousemove', function(evt) {
    playing = true;
    var loc = cursorPoint(evt);
    var pct_through = (loc.x / <?php echo (($graph_width)*$pct_through); ?>);
    var pct_through_whole = (loc.x / <?php echo $graph_width; ?>);
    if (pct_through > 1) {
      return;
    }
    index_rn = Math.round(pct_through * (current_points.length-1)); // Coords for circle (subtract 1 to 0-base for array index)
    var index2 = Math.round(pct_through_whole * (historical_points.length-1)); // Coords for historical circle
    $('#current-circle').attr('cx', current_points[index_rn][0]);
    $('#current-circle').attr('cy', current_points[index_rn][1]);
    <?php if ($typical_time_frame) { ?>
      $('#typical-circle').attr('cx', relativized_points[index2][0]);
      $('#typical-circle').attr('cy', relativized_points[index2][1]);
    <?php } else { ?>
      $('#historical-circle').attr('cx', historical_points[index2][0]);
      $('#historical-circle').attr('cy', historical_points[index2][1]);
    <?php } ?>
    $('#current-value').text(raw_data_formatted[index_rn]);
    $('#current-time-rect').attr('x', current_points[index_rn][0] - <?php echo $width * 0.05; ?>);
    $('#current-time-text').attr('x', current_points[index_rn][0]);
    $('#current-time-text').text(current_times[index_rn]);
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
    var diff = current_points[index_rn][1] - relativized_points[index_rn][1];
    current_frame = Math.round(( (diff - diff_min) / (diff_max - diff_min) ) * (<?php echo $number_of_frames ?> - 0) + 0);
    current_frame = Math.abs( Math.round( ((raw_data[index_rn] - min) / (max - min)) * <?php echo $number_of_frames; ?> ) - <?php echo $number_of_frames; ?> );
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
      $('#current-value-container').attr('display', '');
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
    if (Math.random() > 0.6) { // Randomly either play through the data or play movie
      play_data();
    } else {
      play_movie();
    }
  }

  function play_data() {
    console.log('play_data');
    $('#current-value-container').attr('display', '');
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
      var diff = current_points[i][1] - relativized_points[i][1];
      current_frame = Math.round(( (diff - diff_min) / (diff_max - diff_min) ) * (<?php echo $number_of_frames ?> - 0) + 0); // there are $number_of_frames frames
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
    }, 200);
  }

  function sortNumber(a,b) { return a - b; }

  function play_movie() {
    console.log('play_movie');
    playing = false;
    var val = (raw_data[index_rn] == null) ? 0 : raw_data[index_rn];
    var raw_data_copy_sorted = raw_data.slice().sort(sortNumber);
    var indexof = raw_data_copy_sorted.indexOf(val);
    var relative_value = ((indexof) / raw_data_copy_sorted.length) * 100; // Get percent (0-100)
    <?php
    if ($main_ts->units === 'Kilowatts') {
      $charachter = 'squirrel';
    } else if ($main_ts->units === 'Gallons / hour' || $main_ts->units === 'Liters / hour' || $main_ts->units === 'Liters') {
      $charachter = 'fish';
    } else {
      $charachter = 'both';
    }
    ?>
    // console.log('relative_value: '+relative_value);
    $.get("movie.php", {relative_value: relative_value, count: movies_played, charachter: <?php echo json_encode($charachter) ?>}, function(data) {
      movies_played++;
      var split = data.split('$SEP$');
      console.log(split)
      var len = split[1];
      var name = split[0];
      $('#movie').attr('xlink:href', 'images/' + name + '.gif').attr('display', '');
      if (name.indexOf("Story") >= 0 || name.indexOf("Idea") >= 0) {
        $('#current-value-container').attr('display', 'none');
      }
      alreadydone = false;
      setTimeout(function() {
        if (!alreadydone) {
          $('#movie').attr('display', 'none');
          $('#frame_' + current_frame).attr('display', '');
          $('#current-value-container').attr('display', '');
          playing = true;
        }
        clearTimeout(timeout2); timeout2 = null;
        timeout2 = setTimeout(play, mouse_idle_ms);
      }, len);
    }, 'text');
    // Get gif lengths: http://gifduration.konstochvanligasaker.se/
  }

  function accumulation(time_sofar, avg_kw) {
    <?php if ($main_ts->units === 'Kilowatts') { ?>
    var kwh = (time_sofar/3600)*avg_kw;
    console.log('time elapsed in hours: '+(time_sofar/3600)+"\navg_kw: "+ avg_kw+"\nkwh: "+kwh);
    if (accum_btn.attr('id') === 'kwh') {
      $('#accum-label-value').text(Math.round(kwh).toLocaleString()); // kWh = time elapsed in hours * kilowatts so far
    }
    else if (accum_btn.attr('id') === 'co2') {
      $('#accum-label-value').text(Math.round(kwh*1.22).toLocaleString()); // pounds of co2 per kwh https://www.eia.gov/tools/faqs/faq.cfm?id=74&t=11
    } else { // money
      $('#accum-label-value').text('$'+Math.round(kwh*0.11).toLocaleString()); // average cost of kwh http://www.npr.org/sections/money/2011/10/27/141766341/the-price-of-electricity-in-your-state
    }
    <?php } ?>
  }

  play_data(); // start by playing data

  var last_animated = current_frame;
  setInterval(function(){
    if (frames.length > 0 && playing) {
      var shift = frames.shift();
      $('#frame_' + last_animated).attr('display', 'none');
      $('#frame_' + shift).attr('display', '');
      last_animated = shift;
    }
  }, 10);

  <?php
  if ($time_frame === 'live') {
    echo 'setTimeout(function(){ window.location.reload(); }, 120000);';
  }
  ?>
  // ]]>
  </script>
</svg>