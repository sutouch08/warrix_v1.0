<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<!-- Fav and touch icons -->
<link rel="apple-touch-icon-precomposed" sizes="144x144" href="assets/ico/apple-touch-icon-144-precomposed.png">
<link rel="apple-touch-icon-precomposed" sizes="114x114" href="assets/ico/apple-touch-icon-114-precomposed.png">
<link rel="apple-touch-icon-precomposed" sizes="72x72" href="assets/ico/apple-touch-icon-72-precomposed.png">
<link rel="apple-touch-icon-precomposed" href="ico/apple-touch-icon-57-precomposed.png">
<link rel="shortcut icon" href="assets/ico/favicon.ico">
<title><?php echo $pageTitle ; ?></title>
<!-- Bootstrap core CSS -->
<link href="assets/bootstrap/css/bootstrap.css" rel="stylesheet">
<!-- Custom styles for this template -->
<link href="assets/css/style.css" rel="stylesheet">
<!-- css3 animation effect for this template -->
<link href="assets/css/animate.min.css" rel="stylesheet">
<!-- styles needed by carousel slider -->
<link href="assets/css/owl.carousel.css" rel="stylesheet">
<link href="assets/css/owl.theme.css" rel="stylesheet">
<!-- styles needed by checkRadio -->
<link href="assets/css/ion.checkRadio.css" rel="stylesheet">
<link href="assets/css/ion.checkRadio.cloudy.css" rel="stylesheet">
<!-- styles needed by mCustomScrollbar -->
<link href="assets/css/jquery.mCustomScrollbar.css" rel="stylesheet">
<link href='assets/css/jquery.minimalect.min.css' rel='stylesheet'>
<!-- Just for debugging purposes. -->
<!-- HTML5 shim and Respond.js IE8 support of HTML5 elements and media queries -->
<!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
      <script src="https://oss.maxcdn.com/libs/respond.js/1.3.0/respond.min.js"></script>
<![endif]-->
<script type="text/javascript" src="assets/js/jquery/1.8.3/jquery.js"></script>
<!-- include pace script for automatic web page progress bar  -->

<script>
    paceOptions = {
      elements: true
    };
</script>
<script src="assets/js/pace.min.js"></script>
</head>

<body>
<!-- Modal Login start -->
<?php include "login.php"; ?>
<!-- Modal Signup start -->
<?php  include "top_menu.php"; ?>

  <?php
			include $content;	 
		?>
<!--<div class="container main-container headerOffset">         
</div>-->
<?php // include "footer.php"; ?>
<!-- Le javascript
================================================== --> 

    <!-- Core Scripts - Include with every page -->
 <div id="loader" style="position:absolute; padding: 15px 25px 15px 25px; background-color:#fff; opacity:0.0; box-shadow: 0px 0px 25px #CCC; top:-20px; z-index:10001; display:none;">
       <center><i class="fa fa-spinner fa-5x fa-spin blue"></i></center><center>Uploading</center>
</div> 
<script>
function load_in(){
	var x = ($(document).innerWidth()/2)-50;
	var y = ($(document).innerHeight()/2)-50;
	$("#loader").css("display","");
	$("#loader").css("left",x);
	$("#loader").animate({opacity:0.8, top:y},300);		
}
function load_out(){
	$("#loader").animate({opacity:0, top:-20},300, function(){ $("#loader").css("display","none");});
}   
</script>
<!-- Placed at the end of the document so the pages load faster --> 
<script src="assets/bootstrap/js/bootstrap.min.js"></script> 
<!-- include jqueryCycle plugin --> 
<script src="assets/js/jquery.cycle2.min.js"></script> 
<!-- include easing plugin --> 
<script src="assets/js/jquery.easing.1.3.js"></script> 
<!-- include  parallax plugin --> 
<script type="text/javascript"  src="assets/js/jquery.parallax-1.1.js"></script> 
<!-- optionally include helper plugins --> 
<script type="text/javascript"  src="assets/js/helper-plugins/jquery.mousewheel.min.js"></script> 
<!-- include mCustomScrollbar plugin //Custom Scrollbar  --> 
<script type="text/javascript" src="assets/js/jquery.mCustomScrollbar.js"></script> 
<!-- include checkRadio plugin //Custom check & Radio  --> 
<script type="text/javascript" src="assets/js/ion-checkRadio/ion.checkRadio.min.js"></script> 
<!-- include grid.js // for equal Div height  --> 
<script src="assets/js/grids.js"></script> 
<!-- include carousel slider plugin  --> 
<script src="assets/js/owl.carousel.min.js"></script> 
<!-- jQuery minimalect // custom select   --> 
<script src="assets/js/jquery.minimalect.min.js"></script> 
<!-- include touchspin.js // touch friendly input spinner component   --> 
<script src="assets/js/bootstrap.touchspin.js"></script> 
<!-- include custom script for site  --> 
<script src="assets/js/script.js"></script>

</body>
</html>


