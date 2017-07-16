<?php
//-------- Export 	รายงานสินค้าคงเหลือแยกตามโซน -------------//
	$p_rank 		= $_GET['product_rank'];
	$zone_rank	= $_GET['zone_rank'];
	$id_wh		= $_GET['wh'];
	$data 		= array();
	$qs			= "SELECT tbl_stock.id_product_attribute, zone_name, barcode, reference, cost, qty ";
	$qs 			.= "FROM tbl_stock JOIN tbl_zone ON tbl_stock.id_zone = tbl_zone.id_zone JOIN tbl_product_attribute ON tbl_stock.id_product_attribute = tbl_product_attribute.id_product_attribute ";
	$qs 			.= "JOIN tbl_product ON tbl_product_attribute.id_product = tbl_product.id_product ";
	if( $p_rank == 1 && $id_wh == 0 && $zone_rank == 1 )
	{
		$qs .= "ORDER BY tbl_product_attribute.id_product ASC";
		$p = "ทั้งหมด";  $w = "ทั้งหมด"; $z = "ทั้งหมด";
	}
	else if( $p_rank == 1 && $id_wh != 0 && $zone_rank == 1 )
	{
		$qs .= "WHERE id_warehouse = ".$id_wh." ORDER BY tbl_product_attribute.id_product ASC";
		$p = "ทั้งหมด";  $w = get_warehouse_name_by_id($id_wh); $z = "ทั้งหมด";
	}
	else if( $p_rank == 2 && $id_wh == 0 && $zone_rank == 1 )
	{
		$qs .= "WHERE 	product_code BETWEEN '".$_GET['from']."' AND '".$_GET['to']."' ORDER BY tbl_product_attribute.id_product ASC";
		$p = "จาก ".$_GET['from']."  ถึง ".$_GET['to'];  $w = "ทั้งหมด"; $z = "ทั้งหมด";
	}
	else if( $p_rank == 2 && $id_wh != 0 && $zone_rank == 1 )
	{
		$qs .= "WHERE id_warehouse = ".$id_wh." AND	(product_code BETWEEN '".$_GET['from']."' AND '".$_GET['to']."') ORDER BY tbl_product_attribute.id_product ASC";
		$p = "จาก ".$_GET['from']."  ถึง ".$_GET['to']; $w = get_warehouse_name_by_id($id_wh); $z = "ทั้งหมด"; 
	}
	else if( $p_rank == 1 && $zone_rank == 2 )
	{
		$qs .= "WHERE zone_name = '".$_GET['zone_name']."' ORDER BY tbl_product_attribute.id_product ASC";
		$p = "ทั้งหมด"; if($id_wh == 0 ){ $w = "ทั้งหมด"; }else{ $w = get_warehouse_name_by_id($id_wh); }  $z = $_GET['zone_name'];
	}
	else if( $p_rank == 2 &&$zone_rank == 2 )
	{
		$qs .= "WHERE 	zone_name = '".$_GET['zone_name']."' AND (product_code BETWEEN '".$_GET['from']."' AND '".$_GET['to']."') ORDER BY tbl_product_attribute.id_product ASC";
		$p = "จาก ".$_GET['from']."  ถึง ".$_GET['to'];  if($id_wh == 0 ){ $w = "ทั้งหมด"; }else{ $w = get_warehouse_name_by_id($id_wh); }  $z = $_GET['zone_name'];
	}
	$arr = array("รายงานสินค้าคงเหลือแยกตามโซน วันที่ ".date("d-m-Y"));
	array_push($data, $arr);
	$arr = array("สินค้า : ", $p, "คลัง : ", $w, "โซน : ", $z);
	array_push($data, $arr);
	$qs = dbQuery($qs);
	if(dbNumRows($qs) > 0 )
	{
		$n = 1;
		$total_qty = 0;
		$total_amount = 0;
		while($rs = dbFetchArray($qs) ) :
			$arr = array( $n, $rs['zone_name'], $rs['barcode'], $rs['reference'], $rs['cost'], $rs['qty'], $rs['cost'] * $rs['qty'] );
				array_push($data, $arr);
				$n++; 
				$total_qty += $rs['qty'];
				$total_amount += $rs['qty'] * $rs['cost'];
		endwhile;
		$arr = array("","", "", "", "รวม", $total_qty, $total_amount);
		array_push($data, $arr);
	}
	else
	{
		$arr = array("NODATA");
		array_push($data, $arr);
	}
	
	$excel = new Excel_XML("UTF-8", false, "Stock_zone_report");
	$excel->addArray($data);
	$excel->generateXML("Stock_zone_report");
	setToken($_GET['token']);

?>