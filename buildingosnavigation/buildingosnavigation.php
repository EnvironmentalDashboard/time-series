<?php
error_reporting(-1);
ini_set('display_errors', 'On');
require '../includes/db.php';
require '../includes/class.TimeSeries.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <link href="https://fonts.googleapis.com/css?family=Roboto:400,700" rel="stylesheet">
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
  <link rel="stylesheet" href="css/bootstrap.grid.css">
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.5.0/css/font-awesome.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/3.5.2/animate.min.css">
  <link rel="stylesheet" href="buildingosnavigation/buildosnavstyle.css">
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
  <title>Building Navigation</title>
</head>

<body>
  <!--WEBSITE CONTAINER-->
  <div class="container" style="margin-left: 20px; margin-right: 20px;">
    <!--<div class="row">-->
      <div class="col-sm-3">
        <!--EMPTY-->
      </div>
      <div class="col-sm-12 col-sm-pull-0">
        <div class="row">
        <?php
             $sql = "SELECT id, name, db_link, building_type, area, custom_img FROM buildings WHERE org_id IN (SELECT org_id from users_orgs_map WHERE user_id = {$user_id}) AND custom_img IS NOT NULL AND id IN (SELECT building_id FROM meters WHERE for_orb > 0 OR timeseries_using > 0 OR bos_uuid IS NOT NULL) AND area != 0 ORDER BY name ASC";
        foreach($db->query($sql) as $building)  {
          $stmt = $db->prepare('SELECT id, scope, current, name, for_orb, bos_uuid, units FROM meters WHERE building_id = ? ORDER BY name ASC');
          $stmt->execute(array($building['id']));
          $meters = $stmt->fetchAll();
        ?>
        <div class="col-xs-4 col-sm-3 col-md-2 col-lg-2 card-col" data-title="<?php echo $building['name'] ?>" data-buildingtype="<?php echo $building['building_type'] ?>" data-consumption="<?php echo $meters[0]['current']?>">
            <div class="card">
              <div class="side1" id="side1<?php echo $building['id']; ?>">
                <img src="<?php echo $building['custom_img'] ?>" alt="<?php echo $building['name'] ?>" align="middle">
                <div class="card-text">
                  <?php echo "<a href='{$building['db_link']}' target='_top'>";?>
                    <h1><?php echo $building['name'] ?></h1>
                  </a>
                  <h2 class="text-muted"><?php echo $building['building_type'] ?></h2>
                  <div class="meter-num"><p> 
                  <?php 
                  $count = 0;
                  foreach ($meters as $meter){
                      $count++;
                  }
                  if ($count != 1){echo $count; echo " meters";} else{echo $count; echo " meter";} ?></p>
                </div>
                  <div class="relval">
                  <?php
                    foreach ($meters as $meter) {
                      if ($meter['scope'] == "Whole building"){
                        $stmt = $db->prepare('SELECT relative_value FROM relative_values WHERE (meter_uuid = ?) LIMIT 1');
                        $stmt->execute(array($meter['bos_uuid']));
                        if ($meter['units'] == "Kilowatts"){
                          $elecrelval = $stmt->fetchColumn();
                          if ($elecrelval <= 20){
                            echo "<img src='images/nav_images/electricity1.svg' 
                            height='40px' width='20px' style='position: relative; display: inline; float: left;'>";
                          }
                          else if ($elecrelval <= 40){
                            echo "<img src='images/nav_images/electricity2.svg'
                            height='40px' width='20px' style='position: relative; display: inline; float: left;'>";
                          }
                          else if ($elecrelval <= 60){
                            echo "<img src='images/nav_images/electricity3.svg'  
                            height='40px' width='20px' style='position: relative; display: inline; float: left;'>";
                          }
                          else if ($elecrelval <= 80){
                            echo "<img src='images/nav_images/electricity4.svg' 
                            height='40px' width='20px' style='position: relative; display: inline; float: left;'>";
                          }
                          else{
                            echo "<img src='images/nav_images/electricity5.svg'  
                            height='40px' width='20px' style='position: relative; display: inline; float: left;'>";
                          }
                        }
                        else if ($meter['units'] == "Gallons / hour"){
                          $waterrelval = $stmt->fetchColumn();
                          if ($waterrelval <= 20){
                            echo "<img src='images/nav_images/water1.svg'  
                            height='40px' width='20px' style='position: relative; display: inline; float: left;'>";
                          }   
                          else if ($waterrelval <= 40){
                            echo "<img src='images/nav_images/water2.svg'  
                            height='40px' width='20px' style='position: relative; display: inline; float: left;'>";
                          }
                          else if ($waterrelval <= 60){
                            echo "<img src='images/nav_images/water3.svg'  
                            height='40px' width='20px' style='position: relative; display: inline; float: left;'>";
                          }
                          else if ($waterrelval <= 80){
                            echo "<img src='images/nav_images/water4.svg'  
                            height='40px' width='20px' style='position: relative; display: inline; float: left;'>";
                          }
                          else{
                            echo "<img src='images/nav_images/water5.svg' 
                            height='40px' width='20px' style='position: relative; display: inline; float: left;'>";
                          }
                        }
                      }
                    } 
                  ?>
                  </div>
              </div>
              </div>
            </div>

        </div>
        <?php } ?>
        </div>
      </div>
    <!--</div>-->
  </div>
  <div class="navbar">
    <input type="text" id="search" placeholder="Search">
    <!--FILTERING BUTTONS-->
    <div class="btn-group">
      <div class="dropdown">
        <button class="btn btn-default dropdown-toggle" type="button" data-toggle="dropdown">Select Building Type
        <span class="caret"></span></button>
        <ul class="dropdown-menu">
          <li onClick="window.location.reload()">All</li>
           <?php 
            foreach($db->query("SELECT DISTINCT building_type FROM buildings WHERE org_id IN (SELECT org_id from users_orgs_map WHERE user_id = {$user_id}) AND custom_img IS NOT NULL AND id IN (SELECT building_id FROM meters WHERE gauges_using > 0 OR for_orb > 0 OR timeseries_using > 0 OR bos_uuid IS NOT NULL)
            ORDER BY building_type ASC") as $building) { ?>
            <li class="filter-btn" data-buildingtype="<?php echo $building['building_type']; ?>"><?php echo $building['building_type']; ?></li>
          <?php } ?>
        </ul>
      </div>
      <div class="dropdown">
        <button class="btn btn-default dropdown-toggle" type="button" data-toggle="dropdown">Sort By: Alphabetical
        <span class="caret"></span></button>
        <ul class="dropdown-menu">
<!--               <li onClick="window.location.reload()">Alphabetical</li>
 -->              <li class="header">Current Use</li>
              <li onClick="location.href='https://oberlindashboard.org/oberlin/time-series/buildingosnavigation/currentelecfilteros.php';">Electricity</li>
              <li onClick="location.href='https://oberlindashboard.org/oberlin/time-series/buildingosnavigation/currentwaterfilteros.php';">Water</li>
              <li class="header">Relative Use</li>
              <li onClick="location.href='https://oberlindashboard.org/oberlin/time-series/buildingosnavigation/relvalelecfilteros.php';">Electricity</li>
              <li onClick="location.href='https://oberlindashboard.org/oberlin/time-series/buildingosnavigation/relvalwaterfilteros.php';">Water</li>
              </ul>
          </ul>
      </div>
    </div>
     <div class="key">
      <span class="notification-bubble">?</span>
      <img src='images/nav_images/electricity1.svg' height='40px' width='20px'>
      <div class="keydescription"> 
        <h2 style="font-size: 18px">Electricity Relative Use</h2>
        <p style="margin: 0 auto;">Low
          <img src='images/nav_images/electricity1.svg' height='40px' width='20px'>
          <img src='images/nav_images/electricity2.svg' height='40px' width='20px'>
          <img src='images/nav_images/electricity3.svg' height='40px' width='20px'>
          <img src='images/nav_images/electricity4.svg' height='40px' width='20px'>
          <img src='images/nav_images/electricity5.svg' height='40px' width='20px'>
        High
        <div class='line-separator'></div>
        <h5>Compares current levels to typical levels of use at this time of day </h5></p>
      </div>
    </div>
    <div class="key">
      <span class="notification-bubble">?</span>
      <img src='images/nav_images/water1.svg' height='40px' width='20px'>
      <div class="keydescription"> 
        <h2 style="font-size: 18px">Water Relative Use</h2>
        <p style="margin: 0 auto;">Low
          <img src='images/nav_images/water1.svg' height='40px' width='20px'>
          <img src='images/nav_images/water2.svg' height='40px' width='20px'>
          <img src='images/nav_images/water3.svg' height='40px' width='20px'>
          <img src='images/nav_images/water4.svg' height='40px' width='20px'>
          <img src='images/nav_images/water5.svg' height='40px' width='20px'>
        High</p>
          <div class='line-separator'></div>
        <h5>Compares current levels to typical levels of use at this time of day </h5>
      </div>
    </div>
    </div>
  <div id="bg" style="display: none;height: 100%;width: 100%;position: absolute;top: 0;left: 50px;right: 0;bottom: 0;padding: 20px 20px 20px 20px"></div>
  <object id="object" type="image/svg+xml" data=""></object>
  <img src="images/close.svg" alt="" height="75px" width="75px" id="close-timeseries" style="display: none;cursor: pointer;position: fixed;top: 7px;right: 20px;">
  <script
  src="https://code.jquery.com/jquery-3.1.1.min.js"
  integrity="sha256-hVVnYaiADRTO2PzUGmuLJr8BLUSjGIZsDYGmIJLv2b8="
  crossorigin="anonymous"></script>

  <!--ANIMATING THE BUTTONS-->
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
      //$('.filter-btn').removeClass('active');
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

    $('.sort-btn').on('click', function() {
      $('.sort-btn').addClass('active');
      var buildingconsumption = $(this).data('buildingconsumption');
      var buildingarray = $('.card-col').data('buildingconsumption');
      buildingarray.sort();
    });
    
    $('.show-timeseries').on('click', function() {
      var meter_id = $(this).data('meterid');
      $('#object').attr('data', 'index.php?meter_id='+meter_id+'&meter_id2=' + meter_id).css('display', 'initial');
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