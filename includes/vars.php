<?php
$log = array(); // For debugging
$now = time();
$time_frame = (!empty($_GET['time'])) ? $_GET['time'] : 'live'; // Which button on the bottom is selected
// Colors, fonts, sizing, etc
$primary_color = '#fff';//'#f5f5f5';//'#fafafa';//'#ecf0f1';
$secondary_color = '#2ecc71';
$font_color = '#7f8c8d';
$interval_color = $font_color;
$font_family = 'Roboto, sans-serif';
$height = 400;
$width = 1000;
$graph_width = $width * 0.745; // for 1000x400
//$graph_height = $height * 0.6;
//$graph_offset = $height * 0.3; // Controls how much space is between the minimium value of the time series and the bottom of the image
$graph_height = $height * 0.8;
$graph_offset = $height * 0.09;
$chart_padding = 40;
?>