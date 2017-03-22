<?php
header("Content-Type: text/plain");
require '../includes/db.php';
$rv = $_GET['relative_value'];
$count = $_GET['count'];
if ($count < 1) {
  $keyword = 'Point';
} else if ($count < 3) {
  $keyword = 'Emo';
} else {
  $keyword = ''; // Empty string means any name
}
if ($_GET['charachter'] === 'squirrel') {
  $keyword2 = 'SQ_';
} else if ($_GET['charachter'] === 'fish') {
  $keyword2 = 'Wally_';
} else {
  $keyword2 = '';
}
if ($rv > 80) { $bin = 'bin1'; $bg_name = 'bg3'; }
else if ($rv > 60) { $bin = 'bin2'; $bg_name = 'bg2'; }
else if ($rv > 40) { $bin = 'bin3'; $bg_name = 'bg2'; }
else if ($rv > 20) { $bin = 'bin4'; $bg_name = 'bg1'; }
else { $bin = 'bin5'; $bg_name = 'bg1'; }
$stmt = $db->prepare("SELECT name, length FROM time_series WHERE length > 0 AND {$bin} > 0 AND user_id = ? AND name LIKE ? AND name LIKE ? ORDER BY {$bin} * rand() * rand() * rand() * rand() * rand() * rand() DESC LIMIT 1"); // Multiply by random numbers to reduce the influence of the bin but still use it for weighting the randomness
$stmt->execute(array($user_id, "%{$keyword}%", "%{$keyword2}%"));
$result = $stmt->fetch();
if ($_GET['charachter'] === 'fish') {
  array_push($result, $bg_name);
} else {
  array_push($result, 'none');
}
if (empty($result)) { // choose a random default
  echo 'SQ_Fill_NeutralActionsEyeroll$SEP$18160';
}
else {
  echo implode('$SEP$', $result);
}
?>