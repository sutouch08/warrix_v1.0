<?php 
	$page_name = "โอนคลัง";
	$id_tab = 43;
	$id_profile = $_COOKIE['profile_id'];
    $pm = checkAccess($id_profile, $id_tab);
	$view = $pm['view'];
	$add = $pm['add'];
	$edit = $pm['edit'];
	$delete = $pm['delete'];
	accessDeny($view);	
	$btn = "";
	if( isset($_GET['edit']) && isset($_GET['id_received_product']) ) : 
		$btn = "<a href='index.php?content=product_in'><button type='button' class='btn btn-warning btn-sm'><i class='fa fa-arrow-left'></i>&nbsp; กลับ</button></a>";
		$btn .= "<button type='button' class='btn btn-success btn-sm' style='margin-left:10px;' onclick='edit_stock()'><i class='fa fa-save'></i>&nbsp; บันทึก</button>";
	elseif( isset($_GET['edit']) || isset($_GET['add']) ) : 
		$btn = "<a href='index.php?content=tranfer'><button type='button' class='btn btn-warning btn-sm'><i class='fa fa-arrow-left'></i>&nbsp; กลับ</button></a>";
		if( isset($_GET['id_tranfer']) ) : 
			$id_tranfer = $_GET['id_tranfer']; 
			$btn .= "<a href='controller/tranferController.php?print=y&id_tranfer=".$id_tranfer."' style='text-decoration:none; margin-left:10px;'><button type='button' class='btn btn-success btn-sm'><i class='fa fa-print'></i>&nbsp;พิมพ์</button></a>";			
		endif;	
	elseif( isset($_GET['view_detail']) ) :
		$btn = "<a href='index.php?content=tranfer'><button type='button' class='btn btn-warning btn-sm'><i class='fa fa-arrow-left'></i>&nbsp; กลับ</button></a>";
	else :
		if($add) :
			$btn = "<a href='index.php?content=tranfer&add=y'><button type='button' class='btn btn-success btn-sm' ><i class='fa fa-plus'></i>&nbsp;เพิ่มใหม่</button></a>";
		endif;
	endif;
	?>
    
<div class="container">
<!-- page place holder -->

<div class="row" style="height:35px;">
	<div class="col-xs-6" style="margin-top:10px;">
    	<h4 class="title"><i class="fa fa-upload"></i>&nbsp;<?php echo $page_name; ?></h4>
	</div>
    <div class="col-xs-6">
      	<p class="pull-right" style="margin-bottom:0px;">
        	<?php echo $btn; ?>
        </p>
    </div>
</div>
<hr style='border-color:#CCC; margin-top: 0px; margin-bottom:15px;' />
<!-- End page place holder -->
<?php if( isset( $_GET['add'] ) ) : ?>


<?php else : ?>
<div class="row">
	<div class="col-lg-3">
    	<label>เงื่อนไข</label>
        <select class="form-control input-sm" name="filter" id="filter">
        	<option value=""
        </select>
    </div>
</div>

<?php endif; ?>


