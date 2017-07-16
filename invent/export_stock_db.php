<?php 
	$page_menu = "invent_stock_report";
	$page_name = "ส่งออกฐานข้อมูลสินค้า";
	$id_profile = $_COOKIE['profile_id'];
	function category_name($id_category)
	{
		$name = "";
		$qs = dbQuery("SELECT category_name FROM tbl_category WHERE id_category = ".$id_category);
		if(dbNumRows($qs) == 1 )
		{
			list($name) = dbFetchArray($qs);
		}
		return $name;
	}
	if( isset($_GET['clear_filter']) )
	{
		setcookie("db_search_text", "", time()-3600, "/");
	}
?>
<div class="container">

<div class="row" style="height:35px;">
	<div class="col-lg-8" style="padding-top:10px;"><h4 class="title"><i class="fa fa-file-text-o"></i> <?php echo $page_name; ?></h4></div>
    <div class="col-lg-4">
   		<p class="pull-right" style="margin-bottom:0px;">
        	<button class="btn btn-info btn-sm" type="button" onclick="do_export_all()"><i class="fa fa-file-text-o"></i> ส่งออก ยอดคงยกไปปลายงวดแยกตามพื้นที่จัดเก็บ</button>    
        </p>
    </div>
</div>
<hr style='border-color:#CCC; margin-top: 0px; margin-bottom:10px;' />

</div>   <!-- End container --> 
<script>

function do_export_all()
{
	var token = new Date().getTime();
	get_download(token);
	window.location.href = "controller/exportController.php?export_stock_zone&token="+token;
}
</script>