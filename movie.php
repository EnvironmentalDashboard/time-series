<?php
header("Content-Type: text/plain");
require '../includes/db.php';
$rv = $_GET['relative_value'];
$count = $_GET['count'];
if ($count < 1) {
  $keyword = 'Point';
} else if ($count < 3) {
  $keyword = 'Emot';
} else {
  $keyword = ''; // Empty string means any name
}
if ($rv > 80) { $bin = 'bin1'; }
else if ($rc > 60) { $bin = 'bin2'; }
else if ($rc > 40) { $bin = 'bin3'; }
else if ($rc > 20) { $bin = 'bin4'; }
else { $bin = 'bin5'; }
$stmt = $db->prepare("SELECT name, length FROM time_series WHERE length > 0 AND {$bin} > 0 AND name LIKE ? ORDER BY {$bin} * rand() DESC LIMIT 1");
$stmt->execute(array("%{$keyword}%"));
$result = $stmt->fetch();
if (empty($result)) {
  echo 'SQ_Fill_NeutralActionsEyeroll$SEP$18160';
}
else {
  echo implode('$SEP$', $result);
}
?>