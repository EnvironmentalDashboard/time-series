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
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
  <title>Building Navigation</title>

  <!--STYLE OF THE PAGE-->
  <style>
    .navbar{
      /*overflow: hidden;*/
      top: 0px;
      width: 100%;
      position: fixed;
      padding: 10px 10px 10px 10px;
      background-color: #2C2C2C;
    }
    input {
      height: 40px;
      position: relative;
      border: none;
      width: 150px;
      border-radius: 3px;
      font-size: 1.5rem;
      margin-left: 40px;
      padding-left: 10px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
    }
    input:focus {
      outline: none;
      border: 1px solid #95a5a6;
      padding-left: 10px;
    }
    .dropdown{
      display: inline-block;
    }
    .dropdown-menu{
      min-width: 200px;
      padding: none;
    }
    ul {
      list-style: none;
      position: relative;
    }
    .dropdown-menu li {
      height: 30px;
      width: auto;
      background: #fff;
      text-align: left;
      cursor: pointer;
      font-size: 100%;
      padding-left: 10px;
    }
    .dropdown-menu li:hover{
      background-color: #21A7DF;
    }
    .dropdown-menu .header{
      clear: both;
      color: #999;
      display: block;
      font-size: 11px;
      font-weight: bold;
      line-height: 18px;
      text-transform: uppercase;
      white-space: nowrap;
    }
    .dropdown-menu .header:hover{
      background-color: #fff;
    }
    .key{
      position: relative;
      font-size: 1.5rem;
      background-color: #fff;
      height: 40px;
      width: 230px;
      position: relative;
      display: inline-block;
      border-radius: 3px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
      margin-left: 20px;
      margin-top: 0px;
      padding: 0px 10px 0px 10px;
      text-align: center;
    }
    button{
      position: relative;
      border-radius: 3px;
      font-size: 1.5rem;
      height: 40px;
      margin-left: 20px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
    }
    body {
      padding-top: 20px;
      background: #ecf0f1;
      color: #2c3e50;
      font-family: 'Roboto', Helvetica, sans-serif;
      margin-top: 80px;
    }
    #object {
      width: 100%;
      height: 100%;
      display: block;
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      box-shadow: 0px 0px 15px 5px rgba(0,0,0,0.5);
      display: none;
    }
    .card {
      height: 250px;
      width: 100%;
      margin-bottom: 30px;
      background: #fff;
      padding: 10px;
      border-radius: 3px;
      overflow: auto;
      box-shadow: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
    }
    .card img {
      width: 125px;
      height: 125px;
      display: block;
      margin: 0 auto;
      border-radius: 3px;
    }
    .relval{
      padding: 3px 3px 3px 3px;
      /*
      border-style: solid;
      border-width: 2px;
      border-color: #2C2C2C;
      */
      background-color: white;
      height: 30px;
      width: 30%;
      border-radius: 3px;
      display: inline;
      float: left;
      margin: 0 auto;
    }
    .relval img{
      height: 20px;
      width: 20px;
      display: inline;
    }
    .align-bottom {
      font-size: 1.5rem;
      cursor: pointer;
      margin-top: auto;
    }
    h1{
      margin-top: 5px;
      margin-bottom: 0px;
      font-size: 20px;
      color: #2C2C2C;
      display: block;
    }
    h2{
      font-size: 14px;
      margin-top: 5px;
      margin-bottom: 0px;
    }
    .meternum{
      position: absolute;
      left: 20px;
      bottom: 20px;
      padding: 3px 3px 3px 3px;
    }
    .line-separator{
      height:1px;
      background:#717171;
    }
    .hidden {
      display: none;
    }
    .active {
      background: #95a5a6;
      background-color: #21A7DF;
    }
  </style>
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
        $sql = "SELECT id, name, building_type, custom_img FROM buildings WHERE user_id = {$user_id} 
        AND custom_img IS NOT NULL AND id IN (SELECT building_id FROM meters WHERE for_orb > 0 OR timeseries_using > 0 
        OR bos_uuid IS NOT NULL)  ORDER BY name ASC";
        foreach($db->query($sql) as $building)  {
          $stmt = $db->prepare('SELECT id, scope, current, name, for_orb, bos_uuid, units FROM meters WHERE building_id = ? ORDER BY name ASC');
          $stmt->execute(array($building['id']));
          $meters = $stmt->fetchAll();
        ?>
        <div class="col-xs-4 col-sm-3 col-md-3 col-lg-2 card-col" data-title="<?php echo $building['name'] ?>" data-buildingtype="<?php echo $building['building_type'] ?>" data-consumption="<?php echo $meters[0]['current']?>">
          <div class="card" data-side1="side1<?php echo $building['id'] ?>" data-side2="side2<?php echo $building['id'] ?>">
            <div class="side1" id="side1<?php echo $building['id']; ?>">
              <img src="<?php echo $building['custom_img'] ?>" alt="<?php echo $building['name'] ?>" align="middle">
              <h1><?php echo $building['name'] ?></h1>
              <h2 class="text-muted"><?php echo $building['building_type'] ?></h2>
              <div class="relval">
              <?php
                foreach ($meters as $meter) {
                  if ($meter['scope'] == "Whole building" && $meter['current'] != NULL){
                    $stmt = $db->prepare('SELECT relative_value FROM relative_values WHERE (meter_uuid = ?) LIMIT 1');
                    $stmt->execute(array($meter['bos_uuid']));
                    if ($meter['units'] == "Kilowatts"){
                      if ($stmt->fetchColumn() <= 20){
                        echo "<img src='images/nav_images/electricity1.svg' 
                        height='40px' width='20px' style='position: relative; display: inline; float: left;'>";
                      }
                      else if ($stmt->fetchColumn() <= 40){
                        echo "<img src='images/nav_images/electricity2.svg'
                        height='40px' width='20px' style='position: relative; display: inline; float: left;'>";
                      }
                      else if ($stmt->fetchColumn() <= 60){
                        echo "<img src='images/nav_images/electricity3.svg'  
                        height='40px' width='20px' style='position: relative; display: inline; float: left;'>";
                      }
                      else if ($stmt->fetchColumn() <= 80){
                        echo "<img src='images/nav_images/electricity4.svg' 
                        height='40px' width='20px' style='position: relative; display: inline; float: left;'>";
                      }
                      else{
                        echo "<img src='images/nav_images/electricity5.svg'  
                        height='40px' width='20px' style='position: relative; display: inline; float: left;'>";
                      }
                    }
                    else if ($meter['units'] == "Gallons / hour"){
                      if ($stmt->fetchColumn() <= 20){
                        echo "<img src='images/nav_images/water1.svg'  
                        height='40px' width='20px' style='position: relative; display: inline; float: left;'>";
                      }   
                      else if ($stmt->fetchColumn() <= 40){
                        echo "<img src='images/nav_images/water2.svg'  
                        height='40px' width='20px' style='position: relative; display: inline; float: left;'>";
                      }
                      else if ($stmt->fetchColumn() <= 60){
                        echo "<img src='images/nav_images/water3.svg'  
                        height='40px' width='20px' style='position: relative; display: inline; float: left;'>";
                      }
                      else if ($stmt->fetchColumn() <= 80){
                        echo "<img src='images/nav_images/water4.svg'  
                        height='40px' width='20px' style='position: relative; display: inline; float: left;'>";
                      }
                      else{
                        echo "<img src='images/nav_images/water5.svg' 
                        height='40px' width='20px' style='position: relative; display: inline; float: left;'>";
                      }
                    }
                    echo"<br>";
                  }
                } 
              ?>
              </div>
              <br>
              <div class="meternum"><p> 
              <?php 
              $count = 0;
              foreach ($meters as $meter){
                if ($meter['current'] != NULL){
                  $count++;
                }
              }
              if ($count != 1){echo $count; echo " meters";} else{echo $count; echo " meter";} ?></p></div>
            </div>
            <div class="side2 hidden" id="side2<?php echo $building['id']; ?>">
              <h1>Main Meters</h1>
              <?php
                foreach ($meters as $meter) {
                  if ($meter['scope'] == "Whole building" && $meter['current'] != NULL){
                    echo "<a href='#' style='font-size: 13px;'data-meterid='{$meter['id']}' class='show-timeseries'>{$meter['name']}</a>"; 
                    echo "<div class='line-separator'></div>";
                  }
                } ?>
              <br>
              <h1>Other Meters</h1>
              <?php
                foreach ($meters as $meter) {
                  if ($meter['scope'] != "Whole building" && $meter['current'] != NULL){
                    echo "<a href='#' style='font-size: 13px;'data-meterid='{$meter['id']}' class='show-timeseries'>{$meter['name']}</a>"; 
                    echo "<div class='line-separator'></div>";
                  }
                } ?>
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
            foreach($db->query("SELECT DISTINCT building_type FROM buildings WHERE user_id = {$user_id} AND custom_img IS NOT NULL AND id IN (SELECT building_id FROM meters WHERE gauges_using > 0 
              OR for_orb > 0 OR timeseries_using > 0 OR bos_uuid IS NOT NULL)
            ORDER BY building_type ASC") as $building) { ?>
            <li class="filter-btn" data-buildingtype="<?php echo $building['building_type']; ?>"><?php echo $building['building_type']; ?></li>
          <?php } ?>
        </ul>
      </div>
      <div class="dropdown">
        <button class="btn btn-default dropdown-toggle" type="button" data-toggle="dropdown">Sort By
        <span class="caret"></span></button>
        <ul class="dropdown-menu">
            <li onClick="window.location.reload()">Alphabetical</li>
            <li class="header">Current Usage</li>
            <li>Electricity</li>
            <li>Water</li>
            <li class="header">Relative Usage</li>
            <li>Electricity</li>
            <li>Water</li>
            </ul>
        </ul>
      </div>
      <div class="key">
        <p>Electricity: Low
        <img src='images/nav_images/electricity1.svg' height='40px' width='20px' style='position: relative;'>
        <img src='images/nav_images/electricity3.svg' height='40px' width='20px' style='position: relative;'>
        <img src='images/nav_images/electricity5.svg' height='40px' width='20px' style='position: relative;'>
        High</p>
      </div>
      <div class="key" style="width: 210px;">
        <p>Water: Low
        <img src='images/nav_images/water1.svg' height='40px' width='20px' style='position: relative;'>
        <img src='images/nav_images/water3.svg' height='40px' width='20px' style='position: relative;'>
        <img src='images/nav_images/water5.svg' height='40px' width='20px' style='position: relative;'>
        High</p>
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
    var sortclick=0;
    $('.sort-btn').on('click', function() {
      sortclick +=1;
      $('.sort-btn').addClass('active');
      var buildingconsumption = $(this).data('buildingconsumption');
      var buildingarray = $('.card-col').data('buildingconsumption');
      buildingarray.sort();
    });

    $('.card').on('mouseover', function() {
      var side1 = $('#' + $(this).data('side1'));
      var side2 = $('#' + $(this).data('side2'));
      side1.addClass('hidden');
      side2.removeClass('hidden');
    });
    $('.card').on('mouseout', function() {
      var side1 = $('#' + $(this).data('side1'));
      var side2 = $('#' + $(this).data('side2'));
      side2.addClass('hidden');
      side1.removeClass('hidden');
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