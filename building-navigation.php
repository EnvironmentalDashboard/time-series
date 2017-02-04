<?php
require '../includes/db.php';
error_reporting(-1);
ini_set('display_errors', 'On');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <link href="https://fonts.googleapis.com/css?family=Roboto:400,700" rel="stylesheet">
  <link rel="stylesheet" href="css/bootstrap.grid.css">
  <!-- <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.5.0/css/font-awesome.min.css"> -->
  <title>Building Navigation</title>
  <style>
    body {
      padding-top: 100px;
      background: #ecf0f1;
      color: #2c3e50;
      font-family: 'Roboto', Helvetica, sans-serif;
    }
    .card {
      height: 450px;
      /*width: calc(33.333% - 10px);*/
      width: 100%;
      margin-bottom: 10px;
      background: #fff;
      padding: 10px;
      /*display: inline-block;*/
      /*margin-right: 10px;*/
      /*float: left;*/
      /*position: relative;*/
    }
    .card img {
      width: 100%;
    }
    .card p {
      position: absolute;
      bottom: 0px;
      overflow: scroll;
    }
    h1, h2, h3 {
      margin-top: 5px;
      margin-bottom: 0px;
    }
    input {
      height: 40px;
      border: none;
      width: 100%;
      border-radius: 3px;
      font-size: 1.5rem;
      padding: 3px;
    }
    input:focus {
      outline: none;
      border: 1px solid #95a5a6;
    }
    .hidden {
      display: none;
    }
    ul {
      list-style: none;
      padding: 0px;
    }
    li {
      height: 40px;
      width: 100%;
      background: #fff;
      padding: 5px;
      margin-bottom: 5px;
      text-align: center;
      cursor: pointer;
      padding-top: 10px;
    }
    .active {
      background: #95a5a6;
      color: #fff;
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="row">
      <div class="col-sm-3 col-sm-push-9">
        <input type="text" id="search" placeholder="Search">
        <ul>
          <?php foreach($db->query('SELECT DISTINCT building_type FROM buildings WHERE
          custom_img IS NOT NULL AND id IN (SELECT building_id FROM meters WHERE num_using > 0 OR for_orb > 0) ORDER BY building_type ASC') as $building) { ?>
          <li class="filter-btn" data-buildingtype="<?php echo $building['building_type']; ?>"><?php echo $building['building_type']; ?></li>
          <?php } ?>
        </ul>
      </div>
      <div class="col-sm-9 col-sm-pull-3">
        <div class="row">
        <?php foreach($db->query('SELECT id, name, building_type, custom_img FROM buildings WHERE
                                custom_img IS NOT NULL AND id IN
                                (SELECT building_id FROM meters WHERE num_using > 0 OR for_orb > 0)
                                ORDER BY building_type ASC, name') as $building) { ?>
        <div class="col-sm-4">
        <div class="card" data-title="<?php echo $building['name'] ?>" data-buildingtype="<?php echo $building['building_type'] ?>">
          <img src="<?php echo $building['custom_img'] ?>" alt="<?php echo $building['name'] ?>">
          <h1><?php echo $building['name'] ?></h1>
          <h3 class="text-muted"><?php echo $building['building_type'] ?></h3>
          <p style="max-height: 75px;overflow:scroll;">
            <?php
            $skip = true;
            foreach ($db->query('SELECT id, name FROM meters WHERE building_id = '.intval($building['id']).' AND (num_using > 0 OR for_orb > 0)') as $meter) {
              if ($skip) {
                $skip = false;
              } else { echo '| '; }
              echo "<a href='http://104.131.103.232/oberlin/time-series/index.php?meter_id={$meter['id']}&meter_id2={$meter['id']}'>{$meter['name']}</a> ";
            } ?>
          </p>
        </div>
        </div>
        <?php } ?>
        </div>
      </div>
    </div>
  </div>
  <script
  src="https://code.jquery.com/jquery-3.1.1.min.js"
  integrity="sha256-hVVnYaiADRTO2PzUGmuLJr8BLUSjGIZsDYGmIJLv2b8="
  crossorigin="anonymous"></script>
  <script>
    $('#search').on('input', function() {
      var query = $('#search').val().toLowerCase();
      $('.card').each(function() {
        var content = $(this).data('title') + ' ' + $(this).data('buildingtype');
        if (content.toLowerCase().indexOf(query) === -1) {
          $(this).addClass('hidden');
        } else {
          $(this).removeClass('hidden');
        }
      });
    });
    $('.filter-btn').on('click', function() {
      $('.filter-btn').removeClass('active');
      $(this).addClass('active');
      var buildingtype = $(this).data('buildingtype');
      $('.card').each(function() {
        if ($(this).data('buildingtype') == buildingtype) {
          $(this).removeClass('hidden');
        } else {
          $(this).addClass('hidden');
        }
      });
    });
  </script>
</body>

</html>