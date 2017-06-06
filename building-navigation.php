<?php
require '../includes/db.php';
require '../includes/class.TimeSeries.php';
error_reporting(-1);
ini_set('display_errors', 'On');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <link href="https://fonts.googleapis.com/css?family=Roboto:400,700" rel="stylesheet">
  <link rel="stylesheet" href="css/bootstrap.grid.css">
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.5.0/css/font-awesome.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/3.5.2/animate.min.css">
  <title>Building Navigation</title>
  <style>
    body {
      padding-top: 100px;
      background: #ecf0f1;
      color: #2c3e50;
      font-family: 'Roboto', Helvetica, sans-serif;
    }
    #object {
      width: 100%;
      height: auto;
      display: block;
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      box-shadow: 0px 0px 15px 5px rgba(0,0,0,0.5);
      display: none;
    }
    .card {
      height: 400px;
      width: 100%;
      margin-bottom: 30px;
      background: #fff;
      padding: 10px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
    }
    .card img {
      width: 100%;
    }
    .side1, .side2 {
      display: flex;
      flex-direction: column;
      height: 390px;
      background: #fff;
    }
    .align-bottom {
      font-size: 1.5rem;
      cursor: pointer;
      margin-top: auto;
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
      box-shadow: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
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
      box-shadow: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
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
          <?php foreach($db->query('SELECT DISTINCT building_type FROM buildings WHERE user_id = {$user_id} AND custom_img IS NOT NULL AND id IN (SELECT building_id FROM meters WHERE gauges_using > 0 OR for_orb > 0 OR orb_server > 0 OR timeseries_using > 0) ORDER BY building_type ASC') as $building) { ?>
          <li class="filter-btn" data-buildingtype="<?php echo $building['building_type']; ?>"><?php echo $building['building_type']; ?></li>
          <?php } ?>
        </ul>
      </div>
      <div class="col-sm-9 col-sm-pull-3">
        <div class="row">
        <?php
        foreach($db->query('SELECT id, name, building_type, custom_img FROM buildings WHERE user_id = {$user_id} AND custom_img IS NOT NULL AND id IN (SELECT building_id FROM meters WHERE gauges_using > 0 OR for_orb > 0 OR orb_server > 0 OR timeseries_using > 0) ORDER BY building_type ASC, name') as $building) {
          $stmt = $db->prepare('SELECT id, name FROM meters WHERE building_id = ? AND (gauges_using > 0 OR for_orb > 0 OR orb_server > 0 OR timeseries_using > 0)');
          $stmt->execute(array($building['id']));
          $meters = $stmt->fetchAll();
        ?>
        <div class="col-sm-4 card-col" data-title="<?php echo $building['name'] ?>" data-buildingtype="<?php echo $building['building_type'] ?>">
          <div class="card animated fadeInDown">
            <div class="side1" id="side1<?php echo $building['id']; ?>">
              <img src="<?php echo $building['custom_img'] ?>" alt="<?php echo $building['name'] ?>">
              <h1><?php echo $building['name'] ?></h1>
              <h3 class="text-muted"><?php echo $building['building_type'] ?></h3>
              <p class="more-meters align-bottom" data-side1="side1<?php echo $building['id'] ?>" data-side2="side2<?php echo $building['id'] ?>">View <?php echo count($meters) ?> meters <i class="fa fa-long-arrow-right"></i></p>
            </div>
            <div class="side2 hidden" id="side2<?php echo $building['id']; ?>">
              <h3>Meters</h3>
              <p>
                <?php
                foreach ($meters as $meter) {
                  // echo "<a href='http://104.131.103.232/oberlin/time-series/index.php?meter_id={$meter['id']}&meter_id2={$meter['id']}'>{$meter['name']}</a>";
                  echo "<a href='#' data-meterid='{$meter['id']}' class='show-timeseries'>{$meter['name']}</a><br>";
                } ?>
              </p>
              <p class="align-bottom fewer-meters" data-side1="side1<?php echo $building['id'] ?>" data-side2="side2<?php echo $building['id'] ?>"><i class="fa fa-long-arrow-left"></i> Back</p>
            </div>
          </div>
        </div>
        <?php } ?>
        </div>
      </div>
    </div>
  </div>
  <div id="bg" style="display: none;height: 100%;width: 100%;position: absolute;top: 0;left: 0;right: 0;bottom: 0;background: #fff"></div>
  <img src="images/close.svg" alt="" height="50" width="50" id="close-timeseries" style="display: none;cursor: pointer;position: fixed;top: 0;right: 0;">
  <object id="object" type="image/svg+xml" data=""></object>
  <script
  src="https://code.jquery.com/jquery-3.1.1.min.js"
  integrity="sha256-hVVnYaiADRTO2PzUGmuLJr8BLUSjGIZsDYGmIJLv2b8="
  crossorigin="anonymous"></script>
  <script>
    $.fn.extend({
      animateCss: function (animationName) {
        var animationEnd = 'webkitAnimationEnd mozAnimationEnd MSAnimationEnd oanimationend animationend';
        this.addClass('animated ' + animationName).one(animationEnd, function() {
          $(this).removeClass('animated ' + animationName);
          // if (animationName === 'fadeOut') {
          //   $(this).addClass('hidden');
          // }
        });
      }
    });
    $('#search').on('input', function() {
      var query = $('#search').val().toLowerCase();
      $('.card-col').each(function() {
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
      $('.card-col').each(function() {
        if ($(this).data('buildingtype') == buildingtype) {
          $(this).removeClass('hidden');
        } else {
          $(this).addClass('hidden');
        }
      });
    });
    $('.more-meters').on('click', function() {
      var side1 = $('#' + $(this).data('side1'));
      var side2 = $('#' + $(this).data('side2'));
      side1.addClass('hidden');//.animateCss('fadeOut');
      side2.removeClass('hidden').animateCss('zoomIn');
    });
    $('.fewer-meters').on('click', function() {
      var side1 = $('#' + $(this).data('side1'));
      var side2 = $('#' + $(this).data('side2'));
      side2.addClass('hidden');
      side1.removeClass('hidden').animateCss('zoomIn');
    })
    $('.show-timeseries').on('click', function() {
      var meter_id = $(this).data('meterid');
      $('#object').attr('data', 'chart.php?meter_id='+meter_id+'&meter_id2=' + meter_id).css('display', 'initial');
      $('#close-timeseries').css('display', 'initial');
      $('#bg').css('display', 'block');
    });
    $('#close-timeseries').on('click', function() {
      $('#close-timeseries').css('display', 'none');
      $('#object').css('display', 'none');
      $('#bg').css('display', 'none');
    });
  </script>
</body>

</html>
