<?php
switch ($time_frame) {
  case 'live':
    $res = 'live';
    $stroke_width = 2;
    $circle_size = 'r="6" stroke-width="3" fill="' . $primary_color . '" stroke="';
    $from = strtotime(date('Y-m-d H') . ':00:00'); // Start of hour
    $to = strtotime(date('Y-m-d H') . ":59:59") + 1; // End of the hour
    $g = date('g', $from);
    $labels = array(
      ($g . ":00"),
      ($g . ":10"),
      ($g . ":20"),
      ($g . ":30"),
      ($g . ":40"),
      ($g . ":50"),
      (date('g', $to) . ":00")
      );
    $pct_through = ($now - $from) / 3600;
    $double_time = $from - 3600;
    $dates = "<text fill='{$interval_color}' x='1' y='" . $height * 0.13 . "'
              font-size='13' font-family='{$font_family}'>
              <tspan x='{$chart_padding}' text-anchor='start'>{$labels[0]}</tspan>";
    $dates.= '<tspan text-anchor="middle" x="' . ($graph_width * (1/6) + $chart_padding) . '">' . $labels[1] . '</tspan>
              <tspan text-anchor="middle" x="' . ($graph_width * (2/6) + $chart_padding) . '">' . $labels[2] . '</tspan>
              <tspan text-anchor="middle" x="' . ($graph_width * (3/6) + $chart_padding) . '">' . $labels[3] . '</tspan>
              <tspan text-anchor="middle" x="' . ($graph_width * (4/6) + $chart_padding) . '">' . $labels[4] . '</tspan>
              <tspan text-anchor="middle" x="' . ($graph_width * (5/6) + $chart_padding) . '">' . $labels[5] . '</tspan>
              <tspan text-anchor="end" x="' . ($graph_width * (6/6) + $chart_padding) . '">' . $labels[6] . '</tspan>
              </text>';
    break;
  case 'today':
    $res = 'quarterhour';
    $stroke_width = 2;
    $circle_size = 'r="6" stroke-width="3" fill="' . $primary_color . '" stroke="';
    $from = strtotime(date('Y-m-d') . " 00:00:00"); // Start of day
    $to = strtotime(date('Y-m-d') . " 23:59:59") + 1; // End of day
    $pct_through = ($now - $from) / 86400; // 86400 seconds in a day
    $double_time = $from - 86400;
    $dates = "<text fill='{$interval_color}' x='1' y='" . $height * 0.13 . "'
              font-size='13' font-family='{$font_family}'>
              <tspan x='{$chart_padding}' text-anchor='start'>12am</tspan>";
    $dates.= '<tspan text-anchor="middle" x="' . (($graph_width * (1/14))+$chart_padding+10) . '">2am</tspan>
              <tspan text-anchor="middle" x="' . (($graph_width * (2/14))+$chart_padding) . '">4am</tspan>
              <tspan text-anchor="middle" x="' . (($graph_width * (3/14))+$chart_padding) . '">6am</tspan>
              <tspan text-anchor="middle" x="' . (($graph_width * (4/14))+$chart_padding) . '">8am</tspan>
              <tspan text-anchor="middle" x="' . (($graph_width * (5/14))+$chart_padding) . '">10am</tspan>
              <tspan text-anchor="middle" x="' . (($graph_width * (6/14))+$chart_padding) . '">12pm</tspan>
              <tspan text-anchor="middle" x="' . (($graph_width * (7/14))+$chart_padding) . '">2pm</tspan>
              <tspan text-anchor="middle" x="' . (($graph_width * (8/14))+$chart_padding) . '">4pm</tspan>
              <tspan text-anchor="middle" x="' . (($graph_width * (9/14))+$chart_padding) . '">6pm</tspan>
              <tspan text-anchor="middle" x="' . (($graph_width * (10/14))+$chart_padding) . '">8pm</tspan>
              <tspan text-anchor="middle" x="' . (($graph_width * (11/14))+$chart_padding-10) . '">10pm</tspan>
              <tspan text-anchor="end" x="' . (($graph_width * (12/14))+$chart_padding) . '">12am</tspan>
              </text>';
    break;
  case 'week':
    $res = 'hour';
    $stroke_width = 2;
    $circle_size = 'r="6" stroke-width="0" stroke="' . $primary_color . '" fill="';
    $from = mktime(0, 0, 0, date("n"), date("j") - date("N")); // Start of the week
    $to = mktime(0, 0, 0, date("n"), date("j") + (7-date("N"))); // End of the week
    $pct_through = ($now - $from) / 604800;
    $double_time = $from - 604800;
    $dates = "<text fill=\"{$interval_color}\" x=\"1\" y='" . $height * 0.13 . "'
              font-size=\"17\" font-family=\"{$font_family}\">
              <tspan text-anchor=\"start\" x=\"{$chart_padding}\">Sun</tspan>
              <tspan text-anchor=\"start\" x=\"" . (($graph_width * (1/7))+$chart_padding) . "\">Mon</tspan>
              <tspan text-anchor=\"start\" x=\"" . (($graph_width * (2/7))+$chart_padding) . "\">Tue</tspan>
              <tspan text-anchor=\"start\" x=\"" . (($graph_width * (3/7))+$chart_padding) . "\">Wed</tspan>
              <tspan text-anchor=\"start\" x=\"" . (($graph_width * (4/7))+$chart_padding) . "\">Thu</tspan>
              <tspan text-anchor=\"start\" x=\"" . (($graph_width * (5/7))+$chart_padding) . "\">Fri</tspan>
              <tspan text-anchor=\"end\" x=\"" . (($graph_width * (6/7))+$chart_padding) . "\">Sat</tspan>
              </text>";
    break;
  case 'month':
    $res = 'hour';
    $stroke_width = 1;
    $circle_size = 'r="3" stroke-width="0" stroke="' . $primary_color . '" fill="';
    $from = strtotime(date('Y-m-') . "01 00:00:00"); // Start of the month
    $to = strtotime(date('Y-m-t') . " 24:00:00"); // End of the month
    $secs_in_month = $to - $from; // Different months have diff # days/diff seconds
    $pct_through = ($now - $from) / $secs_in_month;
    $double_time = $from - $secs_in_month;
    $num_days = date('t', $from);
    $dates = '<text fill="' . $interval_color . '" x="1" y="' . $height * 0.13 . '"
              font-size="13" font-family="'. $font_family .'">';
    for ($i = 0; $i < $num_days; $i++) { 
      $dates .= '<tspan x="';
      $dates .= $graph_width * ($i/$num_days);
      $dates .= '">';
      $dates .= $i + 1;
      $dates .= '</tspan>';
    }
    $dates .= '</text>';
    break;
  case 'year':
    $res = 'month';
    $stroke_width = 2;
    $circle_size = 'r="6" stroke-width="3" fill="' . $primary_color . '" stroke="';
    $from = strtotime(date('Y') . "-01-01 00:00:00"); // First day of the year
    $to = strtotime(date('Y') . "-12-31 24:00:00"); // Last day of the year
    $secs_in_year = $to - $from; // Different if leap year, using strtotime() solves this
    $pct_through = ($now - $from) / $secs_in_year;
    $double_time = $from - $secs_in_year;
    $dates = '<text fill="' . $interval_color . '" x="1" y="' . $height * 0.13 . '"
              font-size="13" font-family="'. $font_family .'">
              <tspan x="1" text-anchor="start">Jan</tspan>
              <tspan text-anchor="middle" x="' . $graph_width * (1/12) . '">Feb</tspan>
              <tspan text-anchor="middle" x="' . $graph_width * (2/11) . '">Mar</tspan>
              <tspan text-anchor="middle" x="' . $graph_width * (3/11) . '">Apr</tspan>
              <tspan text-anchor="middle" x="' . $graph_width * (4/11) . '">May</tspan>
              <tspan text-anchor="middle" x="' . $graph_width * (5/11) . '">Jun</tspan>
              <tspan text-anchor="middle" x="' . $graph_width * (6/11) . '">Jul</tspan>
              <tspan text-anchor="middle" x="' . $graph_width * (7/11) . '">Aug</tspan>
              <tspan text-anchor="middle" x="' . $graph_width * (8/11) . '">Sep</tspan>
              <tspan text-anchor="middle" x="' . $graph_width * (9/11) . '">Oct</tspan>
              <tspan text-anchor="middle" x="' . $graph_width * (10/11) . '">Nov</tspan>
              <tspan text-anchor="end" x="' . $graph_width * (11/11) . '">Dec</tspan>
              </text>';
    break;
  default:
    die('<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><text y="20">Invalid time frame.</text></svg>');
    die();
}
?>