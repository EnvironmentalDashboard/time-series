<?php
require '../includes/db.php';
error_reporting(-1);
ini_set('display_errors', 'On');
date_default_timezone_set('America/New_York');
if (empty($_GET['meter_id'])) {
  $_GET['meter_id'] = 244; // some random meter id
}
if (empty($_GET['meter_id2'])) {
  $_GET['meter_id2'] = 249; // some random meter id
}
if (empty($_GET['time'])) {
  $_GET['time'] = 'today';
}
$dropdown_html1 = '';
$dropdown_html2 = '';
$buildings = $db->query('SELECT * FROM buildings ORDER BY name ASC');
foreach ($buildings->fetchAll() as $building) {
  $stmt = $db->prepare('SELECT id, name FROM meters WHERE building_id = ? AND (num_using > 0 OR for_orb = 1) ORDER BY name');
  $stmt->execute(array($building['id']));
  $once = true;
  foreach($stmt->fetchAll() as $meter) {
    if ($once) {
      $once = false;
      $dropdown_html1 .= "<optgroup label='{$building['name']}'>";
      $dropdown_html2 .= "<optgroup label='{$building['name']}'>";
    }
    if ($meter['id'] == $_GET['meter_id']) {
      $dropdown_html1 .= "<option value='{$meter['id']}' selected='selected'>{$meter['name']}</option>";
    }
    else {
      $dropdown_html1 .= "<option value='{$meter['id']}'>{$meter['name']}</option>";
    }
    if ($meter['id'] == $_GET['meter_id2']) {
      $dropdown_html2 .= "<option value='{$meter['id']}' selected='selected'>{$meter['name']}</option>";
    }
    else {
      $dropdown_html2 .= "<option value='{$meter['id']}'>{$meter['name']}</option>";
    }
  }
  if (!$once) {
    $dropdown_html1 .= '</optgroup>';
    $dropdown_html2 .= '</optgroup>';
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <link href="https://fonts.googleapis.com/css?family=Roboto:400,700" rel="stylesheet">
  <link rel="stylesheet" href="css/bootstrap.grid.css">
  <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
  <title>Time Series</title>
</head>
<body>
  <div id="modal">
    <img src="images/close.svg" height="30px" width="30px" alt="Close icon" class="close" onclick="hide_modal()">
    <h2>Customize time series</h2>
    <form action="" method="GET" onsubmit="">
      <div>
        <label for="meter_id">Primary variable</label>
        <div class="select">
          <select name="meter_id" id="meter_id">
            <?php echo $dropdown_html1; ?>
          </select>
        </div>
        <label><input style="margin-left: 10px; margin-bottom: 10px" type="checkbox" name="dasharr1" <?php echo (empty($_GET['dasharr1'])) ? '' : 'checked'; ?>> Dashed</label>
        <label><input type="checkbox" name="fill1" <?php echo (empty($_GET['fill1'])) ? '' : 'checked'; ?>> Filled</label>
      </div>
      <div>
        <label for="meter_id2">Secondary variable</label>
        <div class="select">
          <select name="meter_id2" id="meter_id2">
            <?php echo $dropdown_html2; ?>
          </select>
        </div>
        <label><input style="margin-left: 10px; margin-bottom: 10px" type="checkbox" name="dasharr2" <?php echo (empty($_GET['dasharr2'])) ? '' : 'checked'; ?>> Dashed</label>
        <label><input type="checkbox" name="fill2" <?php echo (empty($_GET['fill2'])) ? '' : 'checked'; ?>> Filled</label>
      </div>
      <div>
        Historical chart<br>
        <label><input style="margin-left: 10px; margin-bottom: 10px" type="checkbox" name="dasharr3" <?php echo (empty($_GET['dasharr3'])) ? '' : 'checked'; ?>> Dashed</label>
        <label><input type="checkbox" name="fill3" <?php echo (empty($_GET['fill3'])) ? '' : 'checked'; ?>> Filled</label>
      </div>
      <label for="time">Time frame</label>
      <div class="select">
        <select name="time" id="time">
          <option value='live' <?php echo ($_GET['time'] === 'live') ? 'selected="selected"' : ''; ?>>Live (current hour)</option>
          <option value='today' <?php echo ($_GET['time'] === 'today') ? 'selected="selected"' : ''; ?>>Today</option>
          <option value='week' <?php echo ($_GET['time'] === 'week') ? 'selected="selected"' : ''; ?>>Week</option>
          <option value='month' <?php echo ($_GET['time'] === 'month') ? 'selected="selected"' : ''; ?>>Month</option>
          <option value='year' <?php echo ($_GET['time'] === 'year') ? 'selected="selected"' : ''; ?>>Year</option>
        </select>
      </div>
      <label for="start">Start Y-axis scale from</label>
      <div class="select">
        <select name="start" id="start">
          <option value="">Auto scale</option>
          <?php
          $time_ago = strtotime('-1 week');
          $stmt = $db->prepare('SELECT value FROM meter_data WHERE meter_id = ? AND recorded > ? ORDER BY value DESC LIMIT 1');
          $stmt->execute(array($_GET['meter_id'], $time_ago));
          $max = $stmt->fetch()['value'];
          if ($max > 10000) {
            for ($i = round($max, -2); $i >= 0-round($max, -2); $i -= 1000) {
              if ($_GET['start'] == $i && !empty($_GET['start'])) {
                echo "<option value='{$i}' selected='selected'>{$i}</option>";
              }
              else {
                echo "<option value='{$i}'>{$i}</option>";
              }
            }
          }
          elseif ($max > 100) {
            for ($i = round($max, -1); $i >= 0-round($max, -1); $i -= 10) { 
              if ($_GET['start'] == $i && !empty($_GET['start'])) {
                echo "<option value='{$i}' selected='selected'>{$i}</option>";
              }
              else {
                echo "<option value='{$i}'>{$i}</option>";
              }
            } 
          }
          else {
            for ($i = round($max, -1); $i >= 0-round($max, -1); $i--) { 
              if ($_GET['start'] == $i && !empty($_GET['start'])) {
                echo "<option value='{$i}' selected='selected'>{$i}</option>";
              }
              else {
                echo "<option value='{$i}'>{$i}</option>";
              }
            }
          }
          ?>
        </select>
      </div>
      <!-- <label class="start-step">
      <p>Enter first a start and then a step for scaling the Y-axis</p>
      <input type="number" min="0" name="start" placeholder="Start" value="<?php echo $_GET['start']; ?>">
      <input type="number" min="0" name="step" placeholder="Step" value="<?php echo $_GET['step']; ?>">
      </label> -->
      <!-- <input type="submit"> -->
      <?php
      foreach ($_GET as $key => $value) {
        if (!in_array($key, array('meter_id', 'meter_id2', 'time', 'start', 'fill1', 'fill2', 'fill3', 'dasharr1', 'dasharr2', 'dasharr3', 'scale'))) {
          echo "<input type='hidden' name='{$key}' value='{$value}' />\n";
        }
      }
      ?>
      <input type="submit" class="btn" value="Update chart" style="margin-left: 10px; margin-top: 10px">
    </form>
  </div>
  <div class="container-fluid">
    <div class="row">
      <div class="col-sm-2">
        <a href="#" class="thumbnail" style="width: 100%">
          <img src="<?php
          $stmt = $db->prepare('SELECT buildings.custom_img, buildings.name FROM buildings WHERE buildings.id IN (SELECT meters.building_id FROM meters WHERE meters.id = ?) LIMIT 1');
          $stmt->execute(array($_GET['meter_id']));
          $result = $stmt->fetch();
          $title = $result['name'];
          if ($result['custom_img'] != '') {
            echo $result['custom_img'];
          }
          else {
            echo 'http://scontent.cdninstagram.com/t51.2885-15/s480x480/e35/13181517_224391237942680_681505699_n.jpg?ig_cache_key=MTI1Mzc3OTE0MjkwNDk0NDk5MA%3D%3D.2';
          }
          ?>" alt="<?php echo $title; ?>">
        </a>
      </div>
      <div class="col-sm-10">
        <h1 style="width: 90%;">
        <?php
        if (empty($_GET['name'])) {
          $stmt = $db->prepare('SELECT name FROM meters WHERE id = ? LIMIT 1');
          $stmt->execute(array($_GET['meter_id']));
          echo $title . ' ' . $stmt->fetch()['name'];
        } else { echo $_GET['name']; }
        ?>
        </h1>
        <!-- <p style="text-align: right"><a href="#" class="btn"></a></p> -->
        <img src="images/pencil-square-o.svg" height="40px" width="40px" alt="Edit icon" style="position:absolute;top:5px;right:5px;cursor:pointer;" onclick="show_modal()">
      </div>
    </div>
  </div>
  <object id="object" type="image/svg+xml" data="http://104.131.103.232/oberlin/time-series/chart.php?<?php echo http_build_query($_GET); ?>"></object>
  <script>
    function show_modal() {
      document.getElementById("modal").style.display = 'block';
    }
    function hide_modal() {
      document.getElementById("modal").style.display = 'none';
    }
  </script>
</body>

</html>