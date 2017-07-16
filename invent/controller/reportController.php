<?php 
require "../../library/config.php";
require "../../library/functions.php";
require "../../library/class/php-excel.class.php";
require "../function/tools.php";
require "../function/report_helper.php";
include "reportController2.php";
include "reportController3.php";


///////////////////  AutoComplete //////////////////////
if(isset($_REQUEST['term'])){
	$qstring = "SELECT reference FROM tbl_product_attribute WHERE reference LIKE '%".$_REQUEST['term']."%' ORDER BY reference ASC";
	$result = dbQuery($qstring);//query the database for entries containing the term
if ($result->num_rows>0)
	{
		$data= array();
	while($row = $result->fetch_array())//loop through the retrieved values
		{
				$data[] = $row['reference'];
		}
		echo json_encode($data);//format the array into json data
	}else {
		echo "error";
	}

}



/***********************  รายงานยอดรวมสินค้า เข้า-ออก *************************************/
if(isset($_GET['total_fifo_report'])&&isset($_GET['product'])&&isset($_GET['view'])){	
	$warehouse_rank = $_GET['warehouse'];
	$product_rank = $_GET['product'];
	$view_rank = $_GET['view'];	
	$today = date('Y-m-d');
	$from = "";
	$to = "";
	if(isset($_GET['product_from'])&&isset($_GET['product_to'])){ // *** เรียงลำดับ id_product จากน้อยไปมาก
		$p_from  = getProductAttributeID($_GET['product_from']);
		$p_to = getProductAttributeID($_GET['product_to']);
			if($p_to < $p_from){
				$product_from = $p_to;
				$product_to = $p_from;
			}else{
				$product_from = $p_from;
				$product_to = $p_to;
			}
	}else{ 
		$product_from =""; $product_to = "";
	}
	if(isset($_GET['product_selected'])){ $product_selected = $_GET['product_selected'];}else{ $product_selected="";}
	if($product_rank==0){  //// product
		$product ="id_product_attribute !=''";
		}else if($product_rank==1){ 
			$product ="(id_product_attribute BETWEEN '$product_from' AND '$product_to' )";
		}else if($product_rank ==2){
			$product_selected = getProductAttributeID($product_selected);
			$product ="id_product_attribute = '$product_selected'";
		}
	if(isset($_GET['warehouse_selected'])){ $warehouse_selected = trim($_GET['warehouse_selected']);}else{ $warehouse_selected="";}
	if($warehouse_rank==0){  //// customer
		$warehouse ="id_warehouse !='-1'";
		$id_warehouse = "";
		}else if($warehouse_rank ==1){
				$warehouse ="id_warehouse = '$warehouse_selected'";	
				$id_warehouse = $warehouse_selected;
		}
	if(isset($_GET['view_selected'])){ $view_selected = $_GET['view_selected'];}else{ $view_selected = "";}
	 if($view_rank==1){
				switch($view_selected){
					case "week" :
						$rang = getWeek($today);
						break;
					case "month" :
						$rang = getMonth();
						break;
					case "year" :
						$rang = getYear();
						break ;
					default :
						$rang = getMonth();
						break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = "AND (date_upd BETWEEN '$from' AND '$to') ";
		}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')."00:00:00"; $to = date('Y-m-d')."23:59:59"; }
					$view = "AND (date_upd BETWEEN '$from' AND '$to') "; 
		}
		$before_date = date('Y-m-d', strtotime("-1day $from"));
	/////////////////////////////////////////////////////////////////////
	if($warehouse_selected !=""){ $wh="  คลัง : ".getWarehouseName($warehouse_selected);}else{$wh =" รวมทุกคลัง";}
	$report_title = "รายงานยอดรวมสินค้า เข้า-ออก วันที่ ".thaiTextDate($from)."  ถึง ".thaiTextDate($to);
	$html = "<h4>$report_title &nbsp;&nbsp; $wh</h4><hr style='border-color:#CCC; margin-top: 0px; margin-bottom:0px;' /><table class='table table-striped table-hover'>";
	$html .= "<table class='table table-striped'><thead><tr><th style='width:5%; text-align: center;'>ลำดับ</th><th style='width:10%;'>บาร์โค้ด</th><th style='width:15%;'>รหัส</th><th style='width:30%;'>สินค้า</th>
				<th style='width:10%;'>คลัง</th><th style='width:10%; text-align: right;'>ต้นทุน</th><th style='width:10%; text-align: right;'>เข้า</th><th style='width:10%; text-align: right;'>ออก</th></tr></thead>";
	
		$qr = dbQuery("SELECT id_product_attribute, id_warehouse, SUM(move_in), SUM(move_out) FROM tbl_stock_movement WHERE id_reason != 9 AND $product  AND $warehouse $view GROUP BY id_product_attribute, id_warehouse ORDER BY id_product_attribute ASC");
		$row = dbNumRows($qr); 
		$i = 0;
		$n = 1;
		$total_in = 0;
		$total_out = 0;
		if($row>0){
			while($i<$row){
				list($id_product_attribute, $wh_id, $move_in, $move_out) = dbFetchArray($qr);
				$product = new product();
				$id_product = $product->getProductId($id_product_attribute);
				$product->product_detail($id_product);
				$product->product_attribute_detail($id_product_attribute);
				$html .= "<tr style='font-size: 12px;'><td align='center'>$n</td><td>".$product->barcode."</td><td>".$product->reference."</td><td>".$product->product_name."</td><td>".get_warehouse_name_by_id($wh_id)."</td>";
				$html .= "<td align='right'>".number_format($product->product_cost)."</td><td align='right'>".number_format($move_in)."</td><td align='right'>".number_format($move_out)."</td></tr>";
				$total_in += $move_in;
				$total_out += $move_out;
				$i++; $n++;
			}
			$html .= "<tr style='font-size: 12px;'><td colspan='6' align='right'>รวม</td><td align='right'>".number_format($total_in)."</td><td align='right'>".number_format($total_out)."</td></tr>";
		}else{
			$html .= "<tr><td colspan='9'><h4 align='center'>ไม่มีรายการตามเงื่อนไขที่เลือก</h4></td></tr>";
		}
		$html .= "</table>";
	echo $html;
}

/***********************  รายงานยอดรวมสินค้า เข้า-ออก export to excel *************************************/
if(isset($_GET['export_total_fifo_report'])&&isset($_GET['product'])&&isset($_GET['view'])){	
	$warehouse_rank = $_GET['warehouse'];
	$product_rank = $_GET['product'];
	$view_rank = $_GET['view'];	
	$today = date('Y-m-d');
	$from = "";
	$to = "";
	if(isset($_GET['product_from'])&&isset($_GET['product_to'])){ // *** เรียงลำดับ id_product จากน้อยไปมาก
		$p_from  = getProductAttributeID($_GET['product_from']);
		$p_to = getProductAttributeID($_GET['product_to']);
			if($p_to < $p_from){
				$product_from = $p_to;
				$product_to = $p_from;
			}else{
				$product_from = $p_from;
				$product_to = $p_to;
			}
	}else{ 
		$product_from =""; $product_to = "";
	}
	if(isset($_GET['product_selected'])){ $product_selected = $_GET['product_selected'];}else{ $product_selected="";}
	if($product_rank==0){  //// product
		$product ="id_product_attribute !=''";
		}else if($product_rank==1){ 
			$product ="(id_product_attribute BETWEEN '$product_from' AND '$product_to' )";
		}else if($product_rank ==2){
			$product_selected = getProductAttributeID($product_selected);
			$product ="id_product_attribute = '$product_selected'";
		}
	if(isset($_GET['warehouse_selected'])){ $warehouse_selected = trim($_GET['warehouse_selected']);}else{ $warehouse_selected="";}
	if($warehouse_rank==0){  //// customer
		$warehouse ="id_warehouse !='-1'";
		$id_warehouse = "";
		}else if($warehouse_rank ==1){
				$warehouse ="id_warehouse = '$warehouse_selected'";	
				$id_warehouse = $warehouse_selected;
		}
	if(isset($_GET['view_selected'])){ $view_selected = $_GET['view_selected'];}else{ $view_selected = "";}
	 if($view_rank==1){
				switch($view_selected){
					case "week" :
						$rang = getWeek($today);
						break;
					case "month" :
						$rang = getMonth();
						break;
					case "year" :
						$rang = getYear();
						break ;
					default :
						$rang = getMonth();
						break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = "AND (date_upd BETWEEN '$from' AND '$to') ";
		}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')."00:00:00"; $to = date('Y-m-d')."23:59:59"; }
					$view = "AND (date_upd BETWEEN '$from' AND '$to') "; 
		}
		$before_date = date('Y-m-d', strtotime("-1day $from"));
	/////////////////////////////////////////////////////////////////////
	if($warehouse_selected !=""){ $wh="  คลัง : ".getWarehouseName($warehouse_selected);}else{$wh =" รวมทุกคลัง";}
	$report_title = "รายงานยอดรวมสินค้า เข้า-ออก วันที่ ".thaiTextDate($from)."  ถึง ".thaiTextDate($to)." : ".COMPANY;
	$title = array(1=>array($report_title.$wh));
	$line = array(1=>array("------------------------------------------------------------------------------------------------------------------------------------"));
	$sub_header = array("ลำดับ","บาร์โค้ด","รหัส","สินค้า","คลัง","ต้นทุน","เข้า","ออก");
	$body = array();
	array_push($body, $sub_header);
	$qr = dbQuery("SELECT id_product_attribute, id_warehouse, SUM(move_in), SUM(move_out) FROM tbl_stock_movement WHERE id_reason != 9 AND $product  AND $warehouse $view GROUP BY id_product_attribute, id_warehouse ORDER BY id_product_attribute ASC");
	$row = dbNumRows($qr); 
		$i = 0;
		$n = 1;
		$total_in = 0;
		$total_out = 0;
		if($row>0){
			while($i<$row){
				list($id_product_attribute, $wh_id, $move_in, $move_out) = dbFetchArray($qr);
				$product = new product();
				$id_product = $product->getProductId($id_product_attribute);
				$product->product_detail($id_product);
				$product->product_attribute_detail($id_product_attribute);
				$arr = array($n, $product->barcode, $product->reference, $product->product_name, get_warehouse_name_by_id($wh_id), number_format($product->product_cost,2), number_format($move_in), number_format($move_out));
				array_push($body, $arr);
				$total_in += $move_in;
				$total_out += $move_out;
				$i++; $n++;
			}
			$arr = array(" "," "," "," "," ","รวม", number_format($total_in), number_format($total_out));
			array_push($body, $arr);
		}else{
			$arr = array("-------------------------------- ไม่มีรายการตามเงื่อนไขที่เลือก  ----------------------------------------");
			array_push($body, $arr);
		}
		$sheet_name = "Stock_Total_Movement_Report";
		$xls = new Excel_XML('UTF-8', false, $sheet_name); 
		$xls->addArray($title);
		$xls->addArray($line);
		$xls->addArray ( $body ); 
		$xls->generateXML("Stock_total_movement_report"); 
}
//****************************  รายงานสินค้าคงเหลือ ****************************************//
	if(isset($_GET['stock_report'])&&isset($_GET['product'])&&isset($_GET['warehouse'])&&isset($_GET['view'])){
	$warehouse_rank = $_GET['warehouse'];
	$product_rank = $_GET['product'];
	$view_rank = $_GET['view'];	
	$today = date('Y-m-d H:i:s');
	$from = "";
	$to = "";
	if($product_rank==0){  //// product
		$product ="tbl_product_attribute.id_product !=''";
		$p_title = "ทุกรายการ";
	}else if($product_rank ==1){
			$pro_from = $_GET['product_from'];
			$pro_to = $_GET['product_to'];
			if($pro_from > $pro_to){ 	$product_from = $pro_to; $product_to = $pro_from; }else{ $product_from = $pro_from; $product_to = $pro_to; }
			$qa = dbQuery("SELECT id_product FROM tbl_product WHERE product_code BETWEEN '".$product_from."' AND '".$product_to."' ORDER BY product_code ASC");
			$rw = dbNumRows($qa);
			$a = 1;
			$in = "";
			while($rx = dbFetchArray($qa)){
				$id_product = $rx['id_product'];
				$in .= $id_product;
				if($a<$rw){ $in .= ", "; }
				$a++;
			}
			$product ="tbl_product_attribute.id_product IN(".$in.")";
			$p_title = $product_from." ถึง ".$product_to; 
	}else if($product_rank == 2){
		$product_selected = $_GET['product_selected'];
		$product = "tbl_product_attribute.id_product = $product_selected";
		$p_title = "เฉพาะ ".$_GET['product_code_selected']; 
	}		
	if($warehouse_rank == 0){
				$warehouse = "id_warehouse !=''";
				$id_warehouse = "";
	}else if($warehouse_rank==1){
					$warehouse_selected = $_GET['warehouse_selected'];
					$warehouse = "id_warehouse = '$warehouse_selected'";   
					$id_warehouse = "id_warehouse = $warehouse_selected";    
	}
	
	if(isset($_GET['view_selected'])){ $date = dbDate($_GET['view_selected']); $date_selected= date('Y-m-d',strtotime("+1day $date"));}else{ $date = date('Y-m-d'); $date_selected = $date; }
	/////////////////////////////////////////////////////////////////////
	$report_title = "รายงานสินค้าคงเหลือ ณ วันที่ ".thaiTextDate($date); if($id_warehouse ==""){ $report_title .=" รวมคลังทุกคลัง";}else{ $report_title .="  คลัง : ".getWarehouseName($warehouse_selected);}
	$report_title .= "  สินค้า : ".$p_title;
	
	$html = "<h4 align='center'>$report_title</h4><table class='table table-striped'><hr style='border-color:#CCC; margin-top: 0px; margin-bottom:0px;' />
	<thead style='font-size: 12px;'>
		<th style='width:5%; text-align:center;'>ลำดับ</th>
		<th style='width:10%;'>บาร์โค้ด</th>
		<th style='width:10%;;'>รหัส</th>
		<th style='width:20%;'>ชื่อสินค้า</th>
		<th style='width:10%; text-align:center;'>สี</th>
		<th style='width:5%; text-align:center'>ไซด์</th>
		<th style='width:10%; text-align:center'>คุณลักษณะ</th>
		<th style='width:10%; text-align: right;'>ราคาทุน</th>
		<th style='width:10%; text-align: right;'>คงเหลือ</th>
		<th style='width:10%; text-align: right;'>มูลค่า</th>
	</thead>";
	
		$qr = dbQuery("SELECT id_product_attribute, reference, barcode, product_name, cost, id_color, id_size, id_attribute FROM tbl_product_attribute JOIN tbl_product ON tbl_product_attribute.id_product = tbl_product.id_product WHERE $product ORDER BY product_code ASC");
		$row = dbNumRows($qr); 
		$total_qty = 0;
		$total_amount = 0;
		$i = 0;
		$n = 1;
		while($i<$row){
			$rs = dbFetchArray($qr);
			$id_product_attribute = $rs['id_product_attribute'];
			$reference = $rs['reference'];
			$barcode = $rs['barcode'];
			$product_name = $rs['product_name'];
			$cost = $rs['cost'];
			$color = $rs['id_color'] != 0 ? get_color_code($rs['id_color'])." : ".color_name($rs['id_color']) : "";
			$size  = $rs['id_size'] != 0 ? get_size_name($rs['id_size']) : "";
			$attribute = $rs['id_attribute'] != 0 ? get_attribute_name($rs['id_attribute']) : "";
			$product = new product();
			if($warehouse_rank == 0){
				$qty = $product->all_available_qty($id_product_attribute);
			}else if($warehouse_rank == 1){
				$stock_qty = $product->stock_qty_by_warehouse($id_product_attribute, $warehouse_selected);
				$move_qty = $product->move_qty_by_warehouse($id_product_attribute, $warehouse_selected);
				$cancle_qty = $product->cancle_qty_by_warehouse($id_product_attribute, $warehouse_selected);
				$buffer_qty = $product->buffer_qty_by_warehouse($id_product_attribute, $warehouse_selected);
				$qty = $stock_qty + $move_qty + $cancle_qty + $buffer_qty;
			}
			if($view_rank==1){
				$sql = "SELECT SUM(move_in) AS stock_in, SUM(move_out) AS stock_out FROM tbl_stock_movement";
				$sql .=" WHERE id_product_attribute = $id_product_attribute AND $warehouse AND (date_upd BETWEEN '$date_selected 00:00:00' AND '$today 23:59:59') GROUP BY id_product_attribute";
				list($stock_in, $stock_out) = dbFetchArray(dbQuery($sql));
					$stock = $qty + ($stock_out - $stock_in);
				}else	if($view_rank==0){ 
					$stock = $qty;
				}
			if($stock ==0){ 
			$amount = 0;
			 }else{
			$amount = $stock * $cost;
			$html .= "<tr style='font-size: 12px;'><td align='center'>".$n."</td><td>".$barcode."</td><td>".$reference."</td><td>".$product_name."</td>
			<td align='center'>".$color."</td><td align='center'>".$size."</td><td align='center'>".$attribute."</td>
			<td align='right'>".number_format($cost,2)."</td><td align='right'>".number_format($stock)."</td><td align='right'>".number_format($amount,2)."</td></tr>";
			$n++;
			}
			$total_qty = $total_qty + $stock;
			$total_amount = $total_amount + $amount;
			$i++;	
		}
		$html .="<tr><td colspan='8' style='text-align:right; padding-right:10px;'><h4>รวม</h4></td>
					<td style='text-align:right; padding-right:10px;'><h4>".number_format($total_qty)."</h4></td><td style='text-align:right; padding-right:10px;'><h4>".number_format($total_amount,2)."</h4></td></tr></table>";
		echo $html;
}
//****************************  รายงานสินค้าคงเหลือ  export to excel ****************************************//
	if(isset($_GET['export_stock_report'])&&isset($_GET['product'])&&isset($_GET['warehouse'])&&isset($_GET['view'])){
	$warehouse_rank = $_GET['warehouse'];
	$product_rank = $_GET['product'];
	$view_rank = $_GET['view'];	
	$today = date('Y-m-d');
	$from = "";
	$to = "";
	if($product_rank==0){  //// product
		$product ="tbl_product_attribute.id_product !=''";
		$p_title = "ทุกรายการ";
	}else if($product_rank ==1){
		$pro_from = $_GET['product_from'];
			$pro_to = $_GET['product_to'];
			if($pro_from > $pro_to){ 	$product_from = $pro_to; $product_to = $pro_from; }else{ $product_from = $pro_from; $product_to = $pro_to; }
			$qa = dbQuery("SELECT id_product FROM tbl_product WHERE product_code BETWEEN '".$product_from."' AND '".$product_to."' ORDER BY product_code ASC");
			$rw = dbNumRows($qa);
			$a = 1;
			$in = "";
			while($rx = dbFetchArray($qa)){
				$id_product = $rx['id_product'];
				$in .= $id_product;
				if($a<$rw){ $in .= ", "; }
				$a++;
			}
			$product ="tbl_product_attribute.id_product IN(".$in.")";
			$p_title = $product_from." ถึง ".$product_to; 
	}else if($product_rank == 2){
		$product_selected = $_GET['product_selected'];
		$product = "tbl_product_attribute.id_product = $product_selected";
		$p_title = "เฉพาะ ".$_GET['product_code_selected']; 
	}		
	if($warehouse_rank == 0){
				$warehouse = "id_warehouse !=''";
				$id_warehouse = "";
	}else if($warehouse_rank==1){
					$warehouse_selected = $_GET['warehouse_selected'];
					$warehouse = "id_warehouse = '$warehouse_selected'";   
					$id_warehouse = "id_warehouse = $warehouse_selected";    
	}
	
	if(isset($_GET['view_selected'])){ $date = dbDate($_GET['view_selected']); $date_selected = date('Y-m-d',strtotime("+1day $date"));}else{ $date = date('Y-m-d'); $date_selected = $date; }
	/////////////////////////////////////////////////////////////////////
	$report_title = "รายงานสินค้าคงเหลือ ณ วันที่ ".thaiTextDate($date); if($id_warehouse ==""){ $report_title .=" รวมคลังทุกคลัง";}else{ $report_title .="  คลัง : ".getWarehouseName($warehouse_selected);}
	$report_title .= " : ".COMPANY;
	$title = array(1=>array($report_title));
	$report_title2 = "สินค้า : ".$p_title;
	$title2 = array(1=>array($report_title2));
	$sub_header = array("ลำดับ","รุ่น","บาร์โค้ด","รหัส","ขื่อสินค้า","สี", "ไซด์", "คุณลักษณะ","ราคาทุน","คงเหลือ","มูลค่า");
	$line = array(1=>array("======================================================================================="));
	$body = array();
	$qr = dbQuery("SELECT id_product_attribute, product_code, reference, barcode, product_name, cost, id_color, id_size, id_attribute FROM tbl_product_attribute JOIN tbl_product ON tbl_product_attribute.id_product = tbl_product.id_product WHERE $product ORDER BY product_code");
		$row = dbNumRows($qr); 
		$total_qty = 0;
		$total_amount = 0;
		$i = 0;
		$n = 1;
		array_push($body, $sub_header);
		while($i<$row){
			$rs = dbFetchArray($qr);
			$id_product_attribute = $rs['id_product_attribute'];
			$reference = $rs['reference'];
			$barcode = $rs['barcode'];
			$product_code = $rs['product_code'];
			$product_name = $rs['product_name'];
			$cost = $rs['cost'];
			$color = $rs['id_color'] != 0 ? get_color_code($rs['id_color'])." : ".color_name($rs['id_color']) : "";
			$size  = $rs['id_size'] != 0 ? get_size_name($rs['id_size']) : "";
			$attribute = $rs['id_attribute'] != 0 ? get_attribute_name($rs['id_attribute']) : "";
			$product = new product();
			if($warehouse_rank == 0){
				$qty = $product->all_available_qty($id_product_attribute);
			}else if($warehouse_rank == 1){
				$stock_qty = $product->stock_qty_by_warehouse($id_product_attribute, $warehouse_selected);
				$move_qty = $product->move_qty_by_warehouse($id_product_attribute, $warehouse_selected);
				$cancle_qty = $product->cancle_qty_by_warehouse($id_product_attribute, $warehouse_selected);
				$buffer_qty = $product->buffer_qty_by_warehouse($id_product_attribute, $warehouse_selected);
				$qty = $stock_qty + $move_qty + $cancle_qty + $buffer_qty;
			}
			
			if($view_rank==1){
				$sql = "SELECT SUM(move_in) AS stock_in, SUM(move_out) AS stock_out FROM tbl_stock_movement";
				$sql .=" WHERE id_product_attribute = ".$id_product_attribute." AND ".$warehouse." AND (date_upd BETWEEN '$date_selected 00:00:00' AND '$today 23:59:59') GROUP BY id_product_attribute";
				list($stock_in, $stock_out) = dbFetchArray(dbQuery($sql));
				$stock = $qty + ($stock_out - $stock_in);
			}else	if($view_rank==0){ 
				$stock = $qty;
			}
			if($stock ==0){ 
				$amount = 0;
			}else{
			$amount = $stock * $cost;
			$arr = array($n, $product_code, $barcode, $reference, $product_name, $color, $size, $attribute, $cost, $stock, $amount);
			array_push($body, $arr);
			$total_qty = $total_qty + $stock;
			$total_amount = $total_amount + $amount;
			$n++;
			}
			$i++;
		}
		$arr = array(" ", " ", ""," ", " ", "", "", "","รวม", $total_qty, $total_amount);
		array_push($body, $arr);
		$sheet_name = "Stock_report";
		$xls = new Excel_XML('UTF-8', false, $sheet_name); 
		$xls->addArray($title);
		$xls->addArray($title2);
		$xls->addArray($line);
		$xls->addArray ($body); 
		$xls->generateXML("Stock_report");
}

//********************************** ปริ้น สต็อกการ์ด *****************************//
if(isset($_GET['print_stock_card'])&&isset($_GET['id_product_attribute'])){
	$id_product_attribute = $_GET['id_product_attribute'];
	$from_date = $_GET['from_date'];
	$to_date = $_GET['to_date'];
	$before_date = $_GET['before_date'];
	$id_warehouse = $_GET['id_warehouse'];
	$title = $_GET['title'];
	if($id_warehouse ==""){
		$warehouse = "id_warehouse != -1";
	}else{
		$warehouse = "id_warehouse = $id_warehouse";
	}
echo "<!DOCTYPE html>
				<html>
				<head>
					<meta charset='utf-8'>
					<meta name='viewport' content='width=device-width, initial-scale=1.0'>
					<link rel='icon' href='../favicon.ico' type='image/x-icon' />
					<title>ออเดอร์</title>
					<!-- Core CSS - Include with every page -->
					<link href='../../library/css/bootstrap.css' rel='stylesheet'>
					<link href='../../library/css/font-awesome.css' rel='stylesheet'>
					<link href='../../library/css/bootflat.min.css' rel='stylesheet'>
					 <link rel='stylesheet' href='../../library/css/jquery-ui-1.10.4.custom.min.css' />
					 <script src='../../library/js/jquery.min.js'></script>
					<script src='../../library/js/jquery-ui-1.10.4.custom.min.js'></script>
					<script src='../../library/js/bootstrap.min.js'></script>  
					<!-- SB Admin CSS - Include with every page -->
					<link href='../../library/css/sb-admin.css' rel='stylesheet'>
					<link href='../../library/css/template.css' rel='stylesheet'>
				</head>
				<body style='padding-top:10px;'><div style='width:180mm; margin-right:auto; margin-left:auto; padding:10px'>
				<div class=\"hidden-print\">	<button  class='btn btn-primary pull-right' onClick=\"print();\" type='button' />พิมพ์</button></div>
				<h5 style='float:left'>$title</h5>	
				<table class='table table-striped table-hover'>";
				
			$header_row = "
			<tr style='font-size:12px;'>
			<td width='10%'>วันที่ </td><td width='15%'>เลขที่เอกสาร</td><td width='10%' align='right'>เข้า</td><td width='10%' align='right'>ออก</td>
			<td width='10%' align='right'>ต้นทุน</td><td width='10%' align='right'>ต้นทุนรวม</td><td width='10%' align='right'>คงเหลือ</td><td width='15%' align='right'>มูลค่า</td>
			</tr>";
			$product = new product();
			$id_product = $product->getProductId($id_product_attribute);
			$product->product_detail($id_product);
			$product->product_attribute_detail($id_product_attribute);
			$barcode = $product->barcode;
			$reference = $product->reference;
			$product_name = $product->product_name;
			$product_cost = $product->product_cost;
				$sql = dbQuery("SELECT date_upd, reference, sum(move_in), sum(move_out) FROM tbl_stock_movement WHERE id_product_attribute = $id_product_attribute AND $warehouse AND (date_upd BETWEEN '$from_date' AND '$to_date') GROUP BY reference, id_warehouse ORDER BY date_upd ASC");
				$rows = dbNumRows($sql);
				$total_in = 0;
				$total_out = 0;
				$v = 0;
				list($stock_in, $stock_out) = dbFetchArray(dbQuery("SELECT SUM(move_in) AS stock_in, SUM(move_out) AS stock_out FROM tbl_stock_movement WHERE id_product_attribute = $id_product_attribute AND $warehouse AND date_upd < '$from_date'"));
				$bf_balance = $stock_in-$stock_out;
				echo"<tr style='font-size:12px;'><td colspan='8'>$reference  :  $product_name  :  $barcode </td></tr>";
				echo $header_row;
				echo"<tr style='font-size:12px;'><td>".thaiDate($before_date)."</td><td colspan='5'>ยอดยกมา</td><td align='right'>".number_format($bf_balance)."</td><td align='right'>".number_format($bf_balance*$product_cost,2)."</td></tr>";
				$balance = $bf_balance;
				while($v<$rows){
				list($date_upd, $document, $move_in, $move_out) = dbFetchArray($sql);
				$balance = $balance+$move_in-$move_out;		
				echo" <tr style='font-size:12px;'><td>".thaiDate($date_upd)."</td><td>$document</td><td align='right'>$move_in</td><td align='right'>$move_out</td><td align='right'>$product_cost</td>
				<td align='center'>"; if($move_in !=0){ echo number_format($move_in*$product_cost,2);} else { echo number_format($move_out*$product_cost,2);} echo"</td>
				<td align='right'>$balance</td><td align='right'>".number_format($balance*$product_cost,2)."</td><tr>";
				$total_in += $move_in;
				$total_out += $move_out;
				$v++;
				}
				$movement = $total_in - $total_out;
				echo 	"<tr style='font-size:12px;'><td colspan='4' style='vertical-align:middle;'>&nbsp;</td><td align='right'>รวมเข้า</td><td align='right'>รวมออก</td><td align='right'>เคลื่อนไหว</td><td align='right'>มูลค่า</td></tr>
						<tr style='font-size:12px;'><td colspan='4' align='right'>&nbsp;</td><td align='right'>$total_in</td><td align='right'>$total_out</td><td align='right'>$movement</td>
						<td align='right'>".number_format($movement*$product_cost,2)."</td></tr>
						<tr style='font-size:12px;'><td colspan='8'><h4>&nbsp;</h4></td></tr>";
				echo "</table>";
}
//********************************  รายงานการตรวจนับ export to excel  *********************************//
if(isset($_GET['report_check_stock'])){
		$id_check = $_GET['id_check'];
		$check_stock = new checkstock();
		$check_stock->detail($id_check);
		$name_check = $check_stock->name_check;
		$report_title = "รายงาน$name_check";
		$qr = dbQuery("SELECT SUM(qty_before),SUM(qty_after),Product FROM stock_check WHERE id_check = $id_check GROUP BY id_product_attribute ORDER BY Product ASC");
		$row = dbNumRows($qr); 
		$i = 0;
		$n = 1;
		$header = array(1=>array($report_title));
		 $myarray = array(1=>array("ลำดับ","ชื่อสินค้า", "จำนวนสต็อก", "จำนวนที่เช็คได้", "ยอดต่าง"));
		while($i<$row){
			list($sumqty_before,$sumqty_after,$product) = dbFetchArray($qr);
			$diff = $sumqty_after - $sumqty_before;
			$data = array($n,$product,$sumqty_before, $sumqty_after, $diff);
			array_push($myarray, $data);
			$i++;
			$n++;
		}
	$sheet_name = "Check_stock_report";
	$xls = new Excel_XML('UTF-8', false, $sheet_name); 
	$xls->addArray($header);
	$xls->addArray ( $myarray ); 
	$xls->generateXML( "check_stock_report" );
}

//******************************** รายงานยอดขายแยกตามพื้นที่การขาย export to excel  ************************************//
if(isset($_GET['sale_report_zone'])&&isset($_GET['from_date'])&&isset($_GET['to_date'])){
	//////////////    แสดงผลรายงาน ตามเงื่อนไขที่เลือกไป /////////////////////
	$from_date = $_GET['from_date'];
	$to_date = $_GET['to_date'];
	if($from_date !=="เลือกวัน" || $to_date !=="เลือกวัน"){
		$from = dbDate($from_date);
		$to = dbDate($to_date); 
	}else{
		$rang = getMonth();
		$to = $rang['to']." 23:59:59";
		$from = $rang['from']." 00:00:00";
	}
	$title = "รายงานยอดขายแยกตามพื้นที่การขาย วันที่ ".thaiTextDate($from)." ถึง ".thaiTextDate($to)." : ".COMPANY;
		$header = array(1=>array($title));
		$body = array(1=>array("ลำดับ","พี้นที่การขาย","ยอดขาย"));
		$line = array(1=>array("====================================================================="));
		$sale = new sale();
		$result = $sale->groupLeaderBoard($from, $to);
		$n = 1;
		$total_amount = 0;
		foreach($result as $data){
			$zone_name = $data['zone_name'];
			$amount = $data['sale_amount'];
			$arr = array($n, $zone_name, number_format($amount,2));
			array_push($body, $arr);
			$total_amount = $total_amount+$amount;
			$n++;
		}
		$arr = array(" "," รวม ",number_format($total_amount,2));
		array_push($body, $arr);
		$sheet_name = "Sale_Report_BY_Zone";
		$xls = new Excel_XML('UTF-8', false, $sheet_name); 
		$xls->addArray($header);
		$xls->addArray($line);
		$xls->addArray ( $body ); 
		$xls->generateXML("Sale_Report_BY_Zone");
}

//*******************************  รายงานยอดขาย แยกตามพนักงานขาย export to excel  **************************************//
if(isset($_GET['sale_report_employee'])&&isset($_GET['from_date'])&&isset($_GET['to_date'])){
	//////////////    แสดงผลรายงาน ตามเงื่อนไขที่เลือกไป /////////////////////
	$from_date = $_GET['from_date'];
	$to_date = $_GET['to_date'];
	if($from_date !=="เลือกวัน" || $to_date !=="เลือกวัน"){
		$from = dbDate($from_date);
		$to = dbDate($to_date); 
	}else{
		$rang = getMonth();
		$to = $rang['to']." 23:59:59";
		$from = $rang['from']." 00:00:00";
	}
	$title = "รายงานยอดขายแยกตามพนักงานขาย วันที่ ".thaiTextDate($from)." ถึง ".thaiTextDate($to)." : ".COMPANY;
		$header = array(1=>array($title));
		$body = array(1=>array("ลำดับ","พนักงานขาย","พี้นที่การขาย","ยอดขาย"));
		$line = array(1=>array("====================================================================="));
		$sale = new sale();
		$result = $sale->saleLeaderBoard($from, $to);
		$n = 1;
		$total_amount = 0;
		foreach($result as $data){
			$salex = new sale($data['id']);
			$sale_name = $salex->full_name;
			$zone_name = $salex->group_name;
			$amount = $data['sale_amount'];
			$arr = array($n,$sale_name, $zone_name, number_format($amount,2));
			array_push($body, $arr);
			$total_amount = $total_amount+$amount;
			$n++;
		}
		$arr = array(" "," "," รวม ",number_format($total_amount,2));
		array_push($body, $arr);
		$sheet_name = "Sale_Report_BY_Employee";
		$xls = new Excel_XML('UTF-8', false, $sheet_name); 
		$xls->addArray($header);
		$xls->addArray($line);
		$xls->addArray ( $body ); 
		$xls->generateXML("Sale_Report_BY_Employee");
}
//************************** รายงานยอดขาย แยกตามลูกค้า export to excel *****************************//
if(isset($_GET['sale_by_customer'])&&isset($_GET['view'])&&isset($_GET['customer'])){
	$customer_rank = $_GET['customer'];
	$view_rank = $_GET['view'];	
	$today = date('Y-m-d');
	$from = "";
	$to = "";
	if(isset($_GET['customer_from'])&&isset($_GET['customer_to'])){ // *** เรียงลำดับ id_customer จากน้อยไปมาก
		$c = reorder(trim($_GET['customer_from']), trim($_GET['customer_to']));
		$customer_from = $c['first'];
		$customer_to 	= $c['last'];		
	}else{ 
		$customer_from =""; $customer_to = "";
	}
	if(isset($_GET['customer_selected'])){ $customer_selected = trim($_GET['customer_selected']);}else{ $customer_selected="";}
	if(isset($_GET['view_selected'])){ $view_selected = $_GET['view_selected'];}else{ $view_selected = "";}
	if($customer_rank==0){  //// customer
		$customer ="customer_code !='-1'";
		if($view_rank == 0){
				$view = "";
				}else if($view_rank==1){
					switch($view_selected){
						case "week" :
							$rang = getWeek($today);
							break;
						case "month" :
							$rang = getMonth();
							break;
						case "year" :
							$rang = getYear();
							break ;
						default :
							$rang = getMonth();
							break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = " AND (tbl_order_detail_sold.date_upd BETWEEN '$from' AND '$to') ";   
				}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')."00:00:00"; $to = date('Y-m-d')."23:59:59"; }
					$view = "AND (tbl_order_detail_sold.date_upd BETWEEN '$from' AND '$to') "; 
				}
		}else if($customer_rank==1){ 
			$customer ="(customer_code BETWEEN '$customer_from' AND '$customer_to' )";
			if($view_rank == 0){
				$view = "";
				}else if($view_rank==1){
					switch($view_selected){
						case "week" :
							$rang = getWeek($today);
							break;
						case "month" :
							$rang = getMonth();
							break;
						case "year" :
							$rang = getYear();
							break ;
						default :
							$rang = getMonth();
							break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = "AND (tbl_order_detail_sold.date_upd BETWEEN '$from' AND '$to') ";
				}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')."00:00:00"; $to = date('Y-m-d')."23:59:59"; }
					$view = "AND (tbl_order_detail_sold.date_upd BETWEEN '$from' AND '$to') "; 
				}
			}else if($customer_rank ==2){
				//$customer ="customer_code = '$customer_selected'";
				$customer = "tbl_customer.id_customer = '$customer_selected'";
				if($view_rank == 0){
				$view = "";
				}else if($view_rank==1){
					switch($view_selected){
						case "week" :
							$rang = getWeek($today);
							break;
						case "month" :
							$rang = getMonth();
							break;
						case "year" :
							$rang = getYear();
							break ;
						default :
							$rang = getMonth();
							break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = "AND (tbl_order_detail_sold.date_upd BETWEEN '$from' AND '$to') ";
				}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')."00:00:00"; $to = date('Y-m-d')."23:59:59"; }
					$view = "AND (tbl_order_detail_sold.date_upd BETWEEN '$from' AND '$to') "; 
				}
			}
		
	/////////////////////////////////////////////////////////////////////
	if($view_rank ==0){ $rank = " ทั้งหมด"; }else{ $rank = thaiDate($from)." ถึง ".thaiDate($to); }
	$report_title = "รายงานยอดขาย แยกตามลูกค้า วันที่ ".$rank." : ".COMPANY;
	$html = " 	<h4 align='center'>$report_title</h4> <hr style='border-color:#CCC; margin-top: 0px; margin-bottom:0px;' />
	<table class='table table-striped table-hover'>
	<thead><th style='width:5%; text-align: center;'>ลำดับ</th><th style='width:10%; '>รหัสลูกค้า</th><th style='width:25%; '>ชื่อลูกค้า</th><th style='width:25%;'>ชื่อร้าน/บริษัท</th>
	<th style='width:10%;'>กลุ่มหลัก</th><th style='width:10%; text-align:'>พนักงานขาย</th><th style='width:15%; text-align: right;'>ยอดขาย</th></thead>"; 

		$qr = dbQuery("SELECT tbl_order_detail_sold.id_customer FROM tbl_order_detail_sold LEFT JOIN tbl_customer ON tbl_order_detail_sold.id_customer = tbl_customer.id_customer WHERE $customer  $view AND id_role IN(1,5) GROUP BY tbl_order_detail_sold.id_customer");
		$row = dbNumRows($qr); 
		$i = 0;
		$n = 1;
		$total_movement = 0;
		$dataset = array();
		if($row>0){
		while($i<$row){
			list($id_customer) = dbFetchArray($qr);
			$customer = new customer($id_customer);
			$customer_name = $customer->full_name;
			$company = $customer->company;
			$customer_code = $customer->customer_code;
			$customer_group = customer_group($customer->id_default_group);
			$sale = new sale($customer->id_sale);
			$sale_name = $sale->first_name;
			$bill_discount	= $customer->total_bill_discount($id_customer, $from, $to);
			$sql = dbQuery("SELECT SUM(total_amount) FROM tbl_order_detail_sold WHERE id_customer = $id_customer $view AND id_role IN(1,5) GROUP BY id_customer");
			list($total_amount) = dbFetchArray($sql);
			$total_amount -= $bill_discount;
			$arr = array("customer_code"=>$customer_code, "customer_name"=>$customer_name, "company"=>$company, "customer_group"=>$customer_group, "sale_name"=>$sale_name, "total_amount"=>$total_amount);
			array_push($dataset, $arr);	
			$total_movement += $total_amount;
			$i++;
		}
		function customer_amount_desc($item1,$item2){
			if ($item1['total_amount'] == $item2['total_amount']) return 0;
			return ($item1['total_amount'] < $item2['total_amount']) ? 1 : -1;
		}
		uasort($dataset, 'customer_amount_desc');
		foreach($dataset as $data){
			$customer_name = $data['customer_name'];
			$company = $data['company'];
			$customer_code = $data['customer_code'];
			$customer_group = $data['customer_group'];
			$sale_name = $data['sale_name'];
			$total_amount = $data['total_amount'];
			$html .="<tr><td align='center'>$n</td><td>$customer_code</td><td>$customer_name</td><td>$company</td><td>$customer_group</td><td>$sale_name</td><td align='right'>".number_format($total_amount,2)."</td></tr>";	
			$n++;
		}
				$html .="<tr><td colspan='6' style='text-align: right;'><h4>รวม</h4></td><td style='text-align: right;'><h4>".number_format($total_movement,2)."</h4></td></tr>
				<tr><td colspan='7'><h4>&nbsp;</h4></td></tr>";
		}else{
			$html .="<tr><td colspan='7'><h4 align='center'>ไม่มีรายการตามเงื่อนไขที่เลือก</h4></td></tr>";
		}
		$html ."</table>";
		echo $html;
}
//*********************************************  รายงานสินค้า แยกตามลูกค้า  Export to excel ************************************************************//
if(isset($_GET['export_sale_by_customer'])&&isset($_GET['view'])&&isset($_GET['customer'])){
	$customer_rank = $_GET['customer'];
	$view_rank = $_GET['view'];	
	$today = date('Y-m-d');
	$from = "";
	$to = "";
	if(isset($_GET['customer_from'])&&isset($_GET['customer_to'])){ // *** เรียงลำดับ id_customer จากน้อยไปมาก
		$p_from  = trim($_GET['customer_from']);
		$p_to = trim($_GET['customer_to']);
			if($p_to < $p_from){
				$customer_from = $p_to;
				$customer_to = $p_from;
			}else{
				$customer_from = $p_from;
				$customer_to = $p_to;
			}
	}else{ 
		$customer_from =""; $customer_to = "";
	}
	if(isset($_GET['customer_selected'])){ $customer_selected = trim($_GET['customer_selected']);}else{ $customer_selected="";}
	if(isset($_GET['view_selected'])){ $view_selected = $_GET['view_selected'];}else{ $view_selected = "";}
	if($customer_rank==0){  //// customer
		$customer ="customer_code !='-1'";
		if($view_rank == 0){
				$view = "";
				}else if($view_rank==1){
					switch($view_selected){
						case "week" :
							$rang = getWeek($today);
							break;
						case "month" :
							$rang = getMonth();
							break;
						case "year" :
							$rang = getYear();
							break ;
						default :
							$rang = getMonth();
							break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = " AND (tbl_order_detail_sold.date_upd BETWEEN '$from' AND '$to') ";   
				}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";

					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')."00:00:00"; $to = date('Y-m-d')."23:59:59"; }
					$view = "AND (tbl_order_detail_sold.date_upd BETWEEN '$from' AND '$to') "; 
				}
		}else if($customer_rank==1){ 
			$customer ="(customer_code BETWEEN '$customer_from' AND '$customer_to' )";
			if($view_rank == 0){
				$view = "";
				}else if($view_rank==1){
					switch($view_selected){
						case "week" :
							$rang = getWeek($today);
							break;
						case "month" :
							$rang = getMonth();
							break;
						case "year" :
							$rang = getYear();
							break ;
						default :
							$rang = getMonth();
							break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = "AND (tbl_order_detail_sold.date_upd BETWEEN '$from' AND '$to') ";
				}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')."00:00:00"; $to = date('Y-m-d')."23:59:59"; }
					$view = "AND (tbl_order_detail_sold.date_upd BETWEEN '$from' AND '$to') "; 
				}
			}else if($customer_rank ==2){
				$customer ="customer_code = '$customer_selected'";
				if($view_rank == 0){
				$view = "";
				}else if($view_rank==1){
					switch($view_selected){
						case "week" :
							$rang = getWeek($today);
							break;
						case "month" :
							$rang = getMonth();
							break;
						case "year" :
							$rang = getYear();
							break ;
						default :
							$rang = getMonth();
							break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = "AND (tbl_order_detail_sold.date_upd BETWEEN '$from' AND '$to') ";
				}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')."00:00:00"; $to = date('Y-m-d')."23:59:59"; }
					$view = "AND (tbl_order_detail_sold.date_upd BETWEEN '$from' AND '$to') "; 
				}
			}
	/////////////////////////////////////////////////////////////////////
	if($view_rank ==0){ $rank = " ทั้งหมด"; }else{ $rank = thaiDate($from)." ถึง ".thaiDate($to); }
	$report_title = "รายงานยอดขาย แยกตามลูกค้า วันที่ ".$rank." : ".COMPANY;
	$title = array(1=>array($report_title));
	$sub_header = array("ลำดับ","รหัสลูกค้า","ชื่อลูกค้า","ชื่อร้าน/บริษัท","กลุ่มหลัก","พนักงานขาย","ยอดขาย");
	$body = array();
	$line = array(1=>array("==========================================================="));
		$qr = dbQuery("SELECT tbl_order_detail_sold.id_customer FROM tbl_order_detail_sold LEFT JOIN tbl_customer ON tbl_order_detail_sold.id_customer = tbl_customer.id_customer WHERE $customer  $view AND id_role IN(1,5) GROUP BY tbl_order_detail_sold.id_customer");
		$row = dbNumRows($qr); 
		$i = 0;
		$n = 1;
		$total_movement = 0;
		array_push($body, $sub_header);
		if($row>0){
		while($i<$row){
			list($id_customer) = dbFetchArray($qr);
			$customer = new customer($id_customer);
			$bill_discount = $customer->total_bill_discount($id_customer, $from, $to);/// ส่วนลดท้ายบิล
			$customer_name = $customer->full_name;
			$company = $customer->company;
			$customer_code = $customer->customer_code;
			$customer_group = customer_group($customer->id_default_group);
			$sale = new sale($customer->id_sale);
			$sale_name = $sale->first_name;
			$sql = dbQuery("SELECT SUM(total_amount) FROM tbl_order_detail_sold WHERE id_customer = $id_customer $view AND id_role IN(1,5) GROUP BY id_customer");
			list($total_amount) = dbFetchArray($sql);
			$total_amount -= $bill_discount;
				$arr = array($n, $customer_code, $customer_name, $company, $customer_group, $sale_name, $total_amount);
				array_push($body, $arr);	
				$total_movement += $total_amount;
				$i++; $n++;
				}
				$arr = array("","","","","","รวม", number_format($total_movement,2)); 
				array_push($body, $arr);
		}else{
			$arr = array("=========================  ไม่มีรายการตามเงื่อนไขที่เลือก ===============================");
		}
		$sheet_name = "Sale_amount_by_customer";
		$xls = new Excel_XML('UTF-8', false, $sheet_name); 
		$xls->addArray($title);
		$xls->addArray($line);
		$xls->addArray ($body); 
		$xls->generateXML("Sale_amount_by_customer");
}
//************************** รายงานยอดขาย แยกตามสินค้า export to excel *****************************//
if(isset($_GET['sale_report_product'])&&isset($_GET['from_date'])&&isset($_GET['to_date'])){
	//////////////    แสดงผลรายงาน ตามเงื่อนไขที่เลือกไป /////////////////////
	$from_date = $_GET['from_date'];
	$to_date = $_GET['to_date'];
	if($from_date !=="เลือกวัน" || $to_date !=="เลือกวัน"){
		$from = dbDate($from_date);
		$to = dbDate($to_date); 
	}else{
		$rang = getMonth();
		$to = $rang['to']." 23:59:59";
		$from = $rang['from']." 00:00:00";
	}
	$title = "รายงานยอดขายแยกตามสินค้า วันที่ ".thaiTextDate($from)." ถึง ".thaiTextDate($to)." : ".COMPANY;
		$header = array(1=>array($title));
		$body = array(1=>array("ลำดับ","ชื่อสินค้า","ยอดขาย"));
		$line = array(1=>array("====================================================================="));
		$qr = dbQuery("SELECT id_product FROM tbl_product");
		$n = 1;
		$grand_amount = 0;
		while($data=dbFetchArray($qr)){
			$id_product = $data['id_product'];
			$product = new product($id_product);
			$product->product_detail($id_product);
			$sold = dbNumRows(dbQuery("SELECT id_product FROM tbl_order_detail_sold WHERE id_product = $id_product AND id_role =1 AND (date_upd BETWEEN '$from' AND '$to')"));
			if($sold>0){
			$sqr = dbQuery("SELECT SUM(total_amount) FROM tbl_order_detail_sold WHERE id_product = $id_product AND id_role =1 AND (date_upd BETWEEN '$from' AND '$to')");
			list($amount) = dbFetchArray($sqr);
			$total_amount = $amount;
			$arr = array($n, $product->product_code." : ".$product->product_name, number_format($total_amount,2));
			array_push($body, $arr);
			$grand_amount = $grand_amount+$total_amount;
			$n++;
			}
		}
		$arr = array(" ", "รวมทั้งหมด",number_format($grand_amount, 2));
		array_push($body, $arr);
		
		///////////////////////////
		$sheet_name = "Sale_Report_BY_Products";
		$xls = new Excel_XML('UTF-8', false, $sheet_name); 
		$xls->addArray($header);
		$xls->addArray($line);
		$xls->addArray ( $body ); 
		$xls->generateXML("Sale_Report_BY_Products");
}
//********************************************report สินค้าค้างส่ง export to excel **************************************//
if(isset($_GET['report_stock_backlogs'])&&isset($_GET['from_date'])&&isset($_GET['to_date'])){
	$from_date = $_GET['from_date'];
	$to_date = $_GET['to_date'];
	if($from_date !=="เลือกวัน" || $to_date !=="เลือกวัน"){
		$from = dbDate($from_date);
		$to = dbDate($to_date); 
	}else{
		$rang = getMonth();
		$to = $rang['to']." 23:59:59";
		$from = $rang['from']." 00:00:00";
	}
	////************************************รายงานตามorder*****************************************//
	if($_POST['i'] == "1"){
	$title = "รายงานออร์เดอร์ค้างส่ง วันที่ ".thaiTextDate($from)." ถึง ".thaiTextDate($to)." : ".COMPANY;
		$header = array(1=>array($title));
		$body = array(1=>array("ลำดับ","เลขที่","ลูกค้า","พนักงาน","ยอดเงิน","สถานะ","วันที่สั่ง"));
		$line = array(1=>array("====================================================================="));
			$sql = dbQuery("SELECT id,reference,cus_first_name,cus_last_name,amount,payment,status,date_add,employee_name FROM order_table WHERE date_add BETWEEN '$from' AND '$to' AND current_state IN (1,2,3,4,5,10,11)");
				$i = 0;
				$grand_amount = 0;
				$row = dbNumRows($sql);
				while($rs = dbFetchArray($sql)){
					$id = $rs['id'];
					$reference = $rs['reference'];
					$full_name_cus = $rs['cus_first_name']." ".$rs['cus_last_name'];
					$amount = $rs['amount'];
					$payment = $rs['payment'];
					$status = $rs['status'];
					$date_add = $rs['date_add'];
					$employee_name = $rs['employee_name'];
			$arr = array($i+1,$reference, $full_name_cus,$employee_name,number_format($amount),$status,$date_add);
			array_push($body, $arr);
			$grand_amount = $grand_amount+$amount;
			$i++;
		}
		$arr = array(" "," "," ", "รวมทั้งหมด",number_format($grand_amount, 2));
		array_push($body, $arr);
		
		///////////////////////////
		$sheet_name = "report_order_backlogs";
		$xls = new Excel_XML('UTF-8', false, $sheet_name); 
		$xls->addArray($header);
		$xls->addArray($line);
		$xls->addArray ( $body ); 
		$xls->generateXML("report_order_backlogs");
		//*****************************รายงานตามสินค้า**************************************//
	}else if($_POST['i'] == "2"){
		$title = "รายงานออร์เดอร์ค้างส่ง วันที่ ".thaiTextDate($from)." ถึง ".thaiTextDate($to);
		$header = array(1=>array($title));
		$body = array(1=>array("ลำดับ","สินค้า","จำนวน"));
		$line = array(1=>array("====================================================================="));
			$sql = dbQuery("SELECT product_name,SUM(product_qty) AS product_qty,product_reference FROM tbl_order_detail LEFT JOIN tbl_order ON tbl_order_detail.id_order = tbl_order.id_order WHERE tbl_order.date_add BETWEEN '$from' AND '$to' AND current_state IN (1,2,3,4,5,10,11) GROUP BY id_product_attribute");
				$i = 0;
				$sumqty = 0;
				$row = dbNumRows($sql);
				while($rs = dbFetchArray($sql)){
					$product_name = $rs['product_reference']." ".$rs['product_name'];
					$product_qty = $rs['product_qty'];
			$arr = array($i+1,$product_name, $product_qty);
			array_push($body, $arr);
			$sumqty = $sumqty+$product_qty;
			$i++;
		}
		$arr = array(" ", "รวมทั้งหมด",number_format($sumqty, 2));
		array_push($body, $arr);
		
		///////////////////////////
		$sheet_name = "report_product_backlogs";
		$xls = new Excel_XML('UTF-8', false, $sheet_name); 
		$xls->addArray($header);
		$xls->addArray($line);
		$xls->addArray ( $body ); 
		$xls->generateXML("report_product_backlogs");
	}
}

//*************************************** รายงานรับสินค้าเข้า  *************************************************//
if(isset($_GET['recieved_report'])&&isset($_GET['view'])&&isset($_GET['product'])){
	$product_rank = $_GET['product'];
	$view_rank = $_GET['view'];	
	$today = date('Y-m-d');
	$from = "";
	$to = "";
	if(isset($_GET['product_from'])&&isset($_GET['product_to'])){ // *** เรียงลำดับ id_product จากน้อยไปมาก
		$p_from  = getProductAttributeID($_GET['product_from']);
		$p_to = getProductAttributeID($_GET['product_to']);
			if($p_to < $p_from){
				$product_from = $p_to;
				$product_to = $p_from;
			}else{
				$product_from = $p_from;
				$product_to = $p_to;
			}
	}else{ 
		$product_from =""; $product_to = "";
	}
	if(isset($_GET['product_selected'])){ $product_selected = getProductAttributeID($_GET['product_selected']);}else{ $product_selected="";}
	if(isset($_GET['view_selected'])){ $view_selected = $_GET['view_selected'];}else{ $view_selected = "";}
	if($product_rank==0){  //// product
		$product ="id_product_attribute !=''";
		if($view_rank == 0){
				$view = "";
				}else if($view_rank==1){
					switch($view_selected){
						case "week" :
							$rang = getWeek($today);
							break;
						case "month" :
							$rang = getMonth();
							break;
						case "year" :
							$rang = getYear();
							break ;
						default :
							$rang = getMonth();
							break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = " AND (tbl_recieved_detail.date BETWEEN '$from' AND '$to') ";   
				}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')."00:00:00"; $to = date('Y-m-d')."23:59:59"; }
					$view = "AND (tbl_recieved_detail.date BETWEEN '$from' AND '$to') "; 
				}
		}else if($product_rank==1){ 
			$product ="(id_product_attribute BETWEEN '$product_from' AND '$product_to' )";
			if($view_rank == 0){
				$view = "";
				}else if($view_rank==1){
					switch($view_selected){
						case "week" :
							$rang = getWeek($today);
							break;
						case "month" :
							$rang = getMonth();
							break;
						case "year" :
							$rang = getYear();
							break ;
						default :
							$rang = getMonth();
							break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = "AND (tbl_recieved_detail.date BETWEEN '$from' AND '$to') ";
				}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')."00:00:00"; $to = date('Y-m-d')."23:59:59"; }
					$view = "AND (tbl_recieved_detail.date BETWEEN '$from' AND '$to') "; 
				}
			}else if($product_rank ==2){
				$product ="id_product_attribute = '$product_selected'";
				if($view_rank == 0){
				$view = "";
				}else if($view_rank==1){
					switch($view_selected){
						case "week" :
							$rang = getWeek($today);
							break;
						case "month" :
							$rang = getMonth();
							break;
						case "year" :
							$rang = getYear();
							break ;
						default :
							$rang = getMonth();
							break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = "AND (tbl_recieved_detail.date BETWEEN '$from' AND '$to') ";
				}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')."00:00:00"; $to = date('Y-m-d')."23:59:59"; }
					$view = "AND (tbl_recieved_detail.date BETWEEN '$from' AND '$to') "; 
				}
			}
		
	/////////////////////////////////////////////////////////////////////
	if($view_rank ==0){ $rank = " ทั้งหมด"; }else{ $rank = thaiDate($from)." ถึง ".thaiDate($to); }
	$report_title = "รายงานการรับสินค้า วันที่ ".$rank." : ".COMPANY;
	$html = " 	<h4 align='center'>$report_title</h4> <hr style='border-color:#CCC; margin-top: 0px; margin-bottom:0px;' />
	<table class='table table-striped table-hover'>
		<thead>
			<th style='width:8%;'>วันที่</th><th style='width:10%;'>เอกสาร</th><th style='width:10%;'>บาร์โค้ด</th><th style='width:15%;'>รหัส</th>
			<th style='text-align:center; width:8%;'>จำนวน</th><th style='text-align: right; width:8%;'>ทุน</th><th style='text-align: right; width:10%;'>มูลค่า</th>
			<th style='text-align:center; width:10%;'>โซน</th><th style='text-align:center; width:8%;'>พนักงาน</th><th style='width:15%;'>อ้างอิง</th>
		</thead>";
		$sql = dbQuery("SELECT tbl_recieved_detail.date, tbl_recieved_product.recieved_product_no, tbl_recieved_product.reference_no, id_product_attribute, qty, id_warehouse, id_zone, id_employee FROM tbl_recieved_detail LEFT JOIN tbl_recieved_product ON tbl_recieved_detail.id_recieved_product = tbl_recieved_product.id_recieved_product WHERE $product  $view AND tbl_recieved_detail.status = 1 ORDER BY tbl_recieved_detail.date ASC");
		$row = dbNumRows($sql); 
		$i = 0;
		$movement = 0;
		$total_cost = 0;
		while($i<$row){
			list($date, $reference, $remark, $id_product_attribute, $qty, $id_warehouse, $id_zone, $id_employee ) = dbFetchArray($sql);
			//list($product_reference) = dbFetchArray(dbQuery("SELECT reference FROM tbl_product_attribute WHERE id_product_attribute = $id_product_attribute"));
			$product = new product();
			$id_product = $product->getProductId($id_product_attribute);
			$product->product_detail($id_product);
			$product->product_attribute_detail($id_product_attribute);
			$product_reference = $product->reference;
			$barcode = $product->barcode;
			$cost = $product->product_cost;
			$cost_amount = $qty*$cost;
			$employee = new employee($id_employee);
			$employee_name = $employee->first_name;
			$zone_name = get_zone($id_zone);
			$date = thaiDate($date);
			$html .=" <tr style='font-size:12px;'><td>$date</td><td>$reference</td><td>$barcode</td><td>$product_reference</td>
						<td align='center'>".number_format($qty)."</td><td align='right'>".number_format($cost,2)."</td><td align='right'>".number_format($cost_amount,2)."</td><td align='center'>$zone_name</td><td align='center'>$employee_name</td><td>$remark</td><tr>";
				$i++;
			$movement += $qty;
			$total_cost += $cost_amount;
		}
		$html .= "<tr><td colspan='10' align='center'><h4>รวม &nbsp;".number_format($movement )."&nbsp; รายการ  มูลค่า &nbsp;".number_format($total_cost,2 )."&nbsp; บาท </h4></td></tr>";
		$html .="</table>";
		echo $html;
}
//*************************************** รายงานรับสินค้าเข้า export to excel  *************************************************//
if(isset($_GET['export_recieved_report'])&&isset($_GET['view'])&&isset($_GET['product'])){
	$product_rank = $_GET['product'];
	$view_rank = $_GET['view'];	
	$today = date('Y-m-d');
	$from = "";
	$to = "";
	if(isset($_GET['product_from'])&&isset($_GET['product_to'])){ // *** เรียงลำดับ id_product จากน้อยไปมาก
		$re = reorder(getProductAttributeID($_GET['product_from']), getProductAttributeID($_GET['product_to']));
		$product_from = $re['from'];
		$product_to = $re['to'];
	}else{ 
		$product_from =""; $product_to = "";
	}
	if(isset($_GET['product_selected'])){ $product_selected = getProductAttributeID($_GET['product_selected']);}else{ $product_selected="";}
	if(isset($_GET['view_selected'])){ $view_selected = $_GET['view_selected'];}else{ $view_selected = "";}
	if($product_rank==0){  //// product
		$product ="id_product_attribute !=''";
	}else if($product_rank==1){ 
		$product ="(id_product_attribute BETWEEN '$product_from' AND '$product_to' )";
	}else if($product_rank ==2){
		$product ="id_product_attribute = '$product_selected'";
	}
	if($view_rank == 0){
				$view = "";
		}else if($view_rank==1){
			$rang = view_report($view_selected);
			$from = $rang['from'];
			$to = $rang['to'];
			$view = "(tbl_recieved_detail.date BETWEEN '$from' AND '$to') ";   
		}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')."00:00:00"; $to = date('Y-m-d')."23:59:59"; }
					$view = "(tbl_recieved_detail.date BETWEEN '$from' AND '$to') "; 
		}	
	/////////////////////////////////////////////////////////////////////
	if($view_rank ==0){ $rank = " ทั้งหมด"; }else{ $rank = thaiDate($from)." ถึง ".thaiDate($to); }
	$report_title = "รายงานการรับสินค้า วันที่ ".$rank." : ".COMPANY;
	$title = array(1=>array($report_title));
	$sub_header = array("วันที่","เอกสาร","บาร์โค้ด","รหัส","สินค้า","จำนวน","ทุน","มูลค่า","โซน","พนักงาน","อ้างอิง");
	$body = array();
	$line = array(1=>array("==========================================================="));
	array_push($body, $sub_header);
		$sql = dbQuery("SELECT tbl_recieved_detail.date, tbl_recieved_product.recieved_product_no, tbl_recieved_product.reference_no, id_product_attribute, qty, id_warehouse, id_zone, id_employee FROM tbl_recieved_detail LEFT JOIN tbl_recieved_product ON tbl_recieved_detail.id_recieved_product = tbl_recieved_product.id_recieved_product WHERE $product AND $view AND tbl_recieved_detail.status = 1 ORDER BY tbl_recieved_detail.date ASC");
		$row = dbNumRows($sql); 
		if($row >0){
		$i = 0;
		$movement = 0;
		$total_cost = 0;
		while($i<$row){
			list($date, $reference, $remark, $id_product_attribute, $qty, $id_warehouse, $id_zone, $id_employee ) = dbFetchArray($sql);
			$product = new product();
			$product->product_attribute_detail($id_product_attribute);
			$product->product_detail($product->id_product);
			$product_reference = $product->reference;
			$product_name = $product->product_name;
			$barcode = $product->barcode;
			$cost = $product->product_cost;
			$cost_amount = $qty*$cost;
			$employee = new employee($id_employee);
			$employee_name = $employee->first_name;
			$zone_name = get_zone($id_zone);
			$date = thaiDate($date);
			$arr = array($date, $reference, $barcode, $product_reference, $product_name, number_format($qty), number_format($cost,2), number_format($cost_amount,2), $zone_name, $employee_name, $remark);
			array_push($body, $arr);
				$i++;
			$movement += $qty;
			$total_cost += $cost_amount;
		}
		$arr = array("","","","","รวม ", number_format($movement ), "", number_format($total_cost,2));
		array_push($body, $arr);
		}else{
		$arr = array("ไม่มีรายการตามเงื่อนไขที่กำหนด");	
		array_push($body, $arr);
		}
	$sheet_name = "Recieved_product_report";
	$xls = new Excel_XML('UTF-8', false, $sheet_name); 
	$xls->addArray($title);
	$xls->addArray($line);
	$xls->addArray ($body); 
	$xls->generateXML("Recieved_product_report"); 
}

//*************************************** รายงานการร้องขอสินค้า  *************************************************************//
if(isset($_GET['request_report'])){
	$product_rank = $_GET['product'];
	$view_rank = $_GET['view'];	
	$today = date('Y-m-d');
	if(isset($_GET['product_from'])&&isset($_GET['product_to'])){ 
		$reorder = reorder($_GET['product_from'], $_GET['product_to']); // *** เรียงลำดับ id_product จากน้อยไปมาก ได้ค่ากลับมาเป็น array from, to
		$product_from = $reorder['from'];
		$product_to = $reorder['to'];
	}else{ 
		$product_from =""; $product_to = "";
	}
	if(isset($_GET['product_selected'])){ $product_selected = $_GET['product_selected'];}else{ $product_selected="";}
	if(isset($_GET['view_selected'])){ $view_selected = $_GET['view_selected'];}else{ $view_selected = "";}
	//******  product  ************//
	if($product_rank==0){  
		$product ="id_product !=''";
	}else if($product_rank==1){ 
		$product ="(id_product BETWEEN $product_from AND $product_to )";
	}else if($product_rank ==2){
		$product ="id_product = $product_selected";
	}
	//********  view  ***************//
	if($view_rank == 0){
				$view = "";
	}else if($view_rank==1){
		$rang = report_view($view_selected);
		$from = $rang['from'];
		$to = $rang['to'];
		$view = "(date_upd BETWEEN '$from' AND '$to') ";
	}else if($view_rank ==2){
		$from = dbDate($_GET['from_date'])." 00:00:00";
		$to = dbDate($_GET['to_date'])." 23:59:59";
		if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')." 00:00:00"; $to = date('Y-m-d')." 23:59:59"; }
		$view = "(date_upd BETWEEN '$from' AND '$to') "; 
	}
	//**************************  report  ************************//
	if($view_rank ==0){ $rank = " ทั้งหมด"; }else{ $rank = thaiDate($from)." ถึง ".thaiDate($to); }
	$report_title = "รายงานการร้องขอสินค้า วันที่ ".$rank." : ".COMPANY;
	$html = "<h4 align='center'>$report_title</h4><hr style='border-color:#CCC; margin-top: 0px; margin-bottom:0px;' />";
	$html .="<div class='row'><div class='col-lg-12'><div id='accordion' class='panel-group panel-group-lists collapse in' style='height: auto;'>";
		$qr = dbQuery("SELECT id_product, SUM(qty) FROM tbl_request_order_detail WHERE $product AND $view GROUP BY id_product");
		$row = dbNumRows($qr); 
		if($row>0){
		$i = 0;
		while($i<$row){
			list($id_product, $total_qty) = dbFetchArray($qr);
			$sql = dbQuery("SELECT id_product_attribute, SUM(qty) FROM tbl_request_order_detail WHERE id_product = $id_product AND $view GROUP BY id_product_attribute");
			$pro = new product();
			$pro->product_detail($id_product);
			$product_name = $pro->product_code." : ".$pro->product_name;
			$rs = dbNumRows($sql);
			$n = 0;
			if($rs>0){
			$html .= "<div class='panel'>
							<div class='panel-heading'>
								<h4 class='panel-title'><a class='collapsed' data-toggle='collapse' data-parent='#accordion' href='#collapse".$id_product."'>".$pro->getCoverImage($id_product,1)."&nbsp; &nbsp; $product_name <span class='badge badge-success'>$total_qty</span></a></h4>
								
							</div>
							<div id='collapse".$id_product."' class='panel-collapse collapse'>
								<div class='panel-body'>
								<table class='table table-striped'>
									<thead>
										<th style='text-align:center; width:10%;'>รูป</th><th style='text-align:center; width:80%;'>สินค้า</th><th style='text-align:center; width:10%;'>จำนวน</th>
									</thead>";
					while($n<$rs){
						list($id_product_attribute, $qty) = dbFetchArray($sql);
						$pro->product_attribute_detail($id_product_attribute);
						$html .="<tr><td align='center'><img src='".$pro->get_product_attribute_image($id_product_attribute,1)."' /></td><td>".$pro->reference." : ".$pro->product_name."	</td><td align='center'>$qty</td>";
						$n++;
					}
					$html .="</table></div></div></div>";
			}
			$i++;
		}
		$html .="</div></div></div><h4>&nbsp;</h4>";		
		}else{
			$html .= "<h4 style='text-align:center'>ไม่มีรายการร้องขอตามเงิ่อนไขที่เลือก</h4></div></div></div>";
		}
	echo $html;	
}



//*************************************** รายงานการร้องขอสินค้า Export_to_excel *************************************************************//
if(isset($_GET['export_request_report'])){
	$product_rank = $_GET['product'];
	$view_rank = $_GET['view'];	
	$today = date('Y-m-d');
	if(isset($_GET['product_from'])&&isset($_GET['product_to'])){ 
		$reorder = reorder($_GET['product_from'], $_GET['product_to']); // *** เรียงลำดับ id_product จากน้อยไปมาก ได้ค่ากลับมาเป็น array from, to
		$product_from = $reorder['from'];
		$product_to = $reorder['to'];
	}else{ 
		$product_from =""; $product_to = "";
	}
	if(isset($_GET['product_selected'])){ $product_selected = $_GET['product_selected'];}else{ $product_selected="";}
	if(isset($_GET['view_selected'])){ $view_selected = $_GET['view_selected'];}else{ $view_selected = "";}
	//******  product  ************//
	if($product_rank==0){  
		$product ="id_product !=''";
	}else if($product_rank==1){ 
		$product ="(id_product BETWEEN $product_from AND $product_to )";
	}else if($product_rank ==2){
		$product ="id_product = $product_selected";
	}
	//********  view  ***************//
	if($view_rank == 0){
				$view = "";
	}else if($view_rank==1){
		$rang = report_view($view_selected);
		$from = $rang['from'];
		$to = $rang['to'];
		$view = "(date_upd BETWEEN '$from' AND '$to') ";
	}else if($view_rank ==2){
		$from = dbDate($_GET['from_date'])." 00:00:00";
		$to = dbDate($_GET['to_date'])." 23:59:59";
		if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')." 00:00:00"; $to = date('Y-m-d')." 23:59:59"; }
		$view = "(date_upd BETWEEN '$from' AND '$to') "; 
	}
	
	/////////////////////////////////////////////////////////////////////
	if($view_rank ==0){ $rank = " ทั้งหมด"; }else{ $rank = thaiDate($from)." ถึง ".thaiDate($to); }
	$report_title = "รายงานการร้องขอสินค้า วันที่ ".$rank." : ".COMPANY;
	$title = array(1=>array($report_title));
	$sub_header = array("ลำดับ","บาร์โค้ด","รหัส","สินค้า","จำนวน");
	$body = array();
	$line = array(1=>array("==========================================================="));
		$qr = dbQuery("SELECT id_product, SUM(qty) FROM tbl_request_order_detail WHERE $product AND $view GROUP BY id_product");
		$row = dbNumRows($qr); 
		if($row>0){
		$i = 0;
		$r = 1;
		array_push($body, $sub_header);
		while($i<$row){
			list($id_product, $total_qty) = dbFetchArray($qr);
			$sql = dbQuery("SELECT id_product_attribute, SUM(qty) FROM tbl_request_order_detail WHERE id_product = $id_product AND $view GROUP BY id_product_attribute");
			$pro = new product();
			$pro->product_detail($id_product);
			$product_name = $pro->product_code." : ".$pro->product_name;
			$rs = dbNumRows($sql);
			$n = 0;
			if($rs>0){
					while($n<$rs){
						list($id_product_attribute, $qty) = dbFetchArray($sql);
						$pro->product_attribute_detail($id_product_attribute);
						$arr = array($r, $pro->barcode, $pro->reference, $pro->product_name, $qty);
						array_push($body, $arr);
						$n++; $r++;
					}
			}
			$i++;
		}	
	}else{
		$arr = array("ไม่มีรายการร้องขอตามเงิ่อนไขที่เลือก");
		array_push($body, $arr);	
		}
	$sheet_name = "Product_request_report";
	$xls = new Excel_XML('UTF-8', false, $sheet_name); 
	$xls->addArray($title);
	$xls->addArray($line);
	$xls->addArray ($body); 
	$xls->generateXML("Product_request_report"); 
}



//****************************************  ส่งข้อมูลรายการสินค้าคงเหลือ  Report_attribute_grid  ******************************//
if(isset($_GET['getData'])&&isset($_GET['id_product'])){
			$id_product = $_GET['id_product'];
			$product = new product();
			$product->product_detail($id_product);
			$config = getConfig("ATTRIBUTE_GRID_HORIZONTAL");
			$sqr = dbQuery("SELECT id_$config FROM tbl_product_attribute WHERE id_product = $id_product AND id_$config !=0 GROUP BY id_$config");
			$colums = dbNumRows($sqr);
			$sqm = dbQuery("SELECT id_color, id_size, id_attribute FROM tbl_product_attribute WHERE id_product = $id_product LIMIT 1");
			list($co, $si, $at) = dbFetchArray($sqm);
			if($co !=0){ $co =1;}
			if($si !=0){ $si = 1;}
			if($at !=0){ $at = 1;}
			$count = $co+$si+$at;
			if($count >1){	$table_w = (70*($colums+1)+100); }else if($count ==1){ $table_w = 800; }
			$dataset = $product->reportAttributeGrid($id_product);
			$dataset .= "|".$table_w;
			$dataset .= "|".$product->product_code;
			echo $dataset;
}
//*************************************** รายงานยอดขาย แยกตามเลขที่เอกสาร  *******************************************//
	if(isset($_GET['sale_by_document'])&&isset($_GET['view'])){
		$view = $_GET['view'];
		switch($view){
				case "1" :
					$view_selected = $_GET['view_selected'];
					$today = date('Y-m-d');
					switch($view_selected){
									case "week" :
										$rang = getWeek($today);
										break;
									case "month" :
										$rang = getMonth();
										break;
									case "year" :
										$rang = getYear();
										break ;
									default :
										$rang = getMonth();
										break;
								}
						$from = $rang['from']." 00:00:00";
						$to = $rang['to']." 23:59:59";
						$views = "AND (date_upd BETWEEN '$from' AND '$to') ";
						$rank = "วันที่ ".thaiDate($from)." ถึง ".thaiDate($to);
						break;
					case "2" :
						$from_date = dbDate($_GET['from_date']);
						if($from_date =="1970-01-01"){ $from_date = $today; }
						$to_date = dbDate($_GET['to_date']);
						if($to_date =="1970-01-01"){ $to_date = $today; }
						$from = $from_date." 00:00:00";
						$to = $to_date." 23:59:59";
						$views = " AND (date_upd BETWEEN '$from' AND '$to') ";
						$rank = "วันที่ ".thaiDate($from)." ถึง ".thaiDate($to);
						break;
					default :
						$rang = getYear();
						$from = $rang['from']." 00:00:00";
						$to = $rang['to']." 23:59:59";
						$views = "AND (date_upd BETWEEN '$from' AND '$to') ";
						$rank = "วันที่ ".thaiDate($from)." ถึง ".thaiDate($to);
						break;
			}
			$html = "
			<div class='col-lg-12'><h4>รายงานยอดขายแยกตามเอกสาร $rank</h4></div>
			<div class='col-lg-12'><hr style='border-color:#CCC; margin-top: 0px; margin-bottom:15px;' /></div>
			<table class='table table-striped'>
				<thead>
				<th style='width:5%; text-align:center;'>ลำดับ</th>
				<th style='width:10%;'>เอกสาร</th>
				<th style='width:10%; text-align:center;'>วันที่</th>
				<th style='width:45%;'>ลูกค้า</th>
				<th style='width:10%; text-align:right;'>ส่วนลดท้ายบิล</th>
				<th style='width:20%; text-align:right;'>มูลค่า</th>
				</thead>";
			$sql = dbQuery("SELECT id_order, reference, id_customer, date_upd FROM tbl_order WHERE role = 1 AND current_state = 9 $views ORDER BY reference ASC");
			$row = dbNumRows($sql);
			if($row>0){
				$i =0;
				$n=1;
				$total_movement = 0;
				while($i<$row){
					list($id_order, $reference, $id_customer, $date_upd) = dbFetchArray($sql);
					list($total_amount) = dbFetchArray(dbQuery("SELECT SUM(total_amount) FROM tbl_order_detail_sold WHERE id_order = $id_order"));
					list($first_name, $last_name) = dbFetchArray(dbQuery("SELECT first_name, last_name FROM tbl_customer WHERE id_customer = $id_customer"));
					$customer_name = $first_name." ".$last_name;
					$bill_discount = bill_discount($id_order); /// ส่วนลดท้ายบิล
					$total_amount -= $bill_discount; /// ส่วนลดท้ายบิล
					$html .="
					<tr>
						<td align='center'>$n</td>
						<td>".$reference."</td>
						<td align='center'>".thaiDate($date_upd)."</td>
						<td>".$customer_name."</td>
						<td align='right'>".ac_format(number_format($bill_discount,2))."</td>
						<td style='text-align: right; padding-right: 5px;'>".number_format($total_amount,2)."</td>
					</tr>";
					$total_movement += $total_amount;
					$i++;
					$n++;
				}
				$html .="<tr><td colspan='5' style='text-align: right; padding-right:10px; '><h4>รวม</h4></td>".
				"<td style='text-align: right; padding-right: 5px;'><h4>".number_format($total_movement,2)."</h4></td></tr></table>";
			}else{
				$html .="<tr><td colspan='6' style='text-align:center;'><h4>ไม่มีรายการตามช่วงที่เลือก</h4></td></tr></table>";
			}
			echo $html;
				
	}
	
//*************************************** รายงานยอดขาย แยกตามเลขที่เอกสาร Export to excel  *******************************************//
	if(isset($_GET['export_sale_by_document'])&&isset($_GET['view'])){
		$view = $_GET['view'];
		switch($view){
				case "1" :
					$view_selected = $_GET['view_selected'];
					$today = date('Y-m-d');
					switch($view_selected){
									case "week" :
										$rang = getWeek($today);
										break;
									case "month" :
										$rang = getMonth();
										break;
									case "year" :
										$rang = getYear();
										break ;
									default :
										$rang = getMonth();
										break;
								}
						$from = $rang['from']." 00:00:00";
						$to = $rang['to']." 23:59:59";
						$views = "AND (date_upd BETWEEN '$from' AND '$to') ";
						$rank = "วันที่ ".thaiDate($from)." ถึง ".thaiDate($to);
						break;
					case "2" :
						$from_date = dbDate($_GET['from_date']);
						if($from_date =="1970-01-01"){ $from_date = $today; }
						$to_date = dbDate($_GET['to_date']);
						if($to_date =="1970-01-01"){ $to_date = $today; }
						$from = $from_date." 00:00:00";
						$to = $to_date." 23:59:59";
						$views = " AND (date_upd BETWEEN '$from' AND '$to') ";
						$rank = "วันที่ ".thaiDate($from)." ถึง ".thaiDate($to);
						break;
					default :
						$rang = getYear();
						$from = $rang['from']." 00:00:00";;
						$to = $rang['to']." 23:59:59";
						$views = "AND (date_upd BETWEEN '$from' AND '$to') ";
						$rank = "วันที่ ".thaiDate($from)." ถึง ".thaiDate($to);
						break;
			}
			$report_title = "รายงานยอดขายแยกตามเอกสาร ".$rank." : ".COMPANY;
			$title = array(1=>array($report_title));
			$sub_header = array("ลำดับ","เอกสาร","วันที่","ลูกค้า","ส่วนลดท้ายบิล","มูลค่า");
			$body = array();
			array_push($body, $sub_header);
			$sql = dbQuery("SELECT id_order, reference, id_customer, date_upd FROM tbl_order WHERE role = 1 AND current_state = 9 $views ORDER BY reference ASC");
			$row = dbNumRows($sql);
			if($row>0){
				$i =0;
				$n=1;
				$total_movement = 0;
				while($i<$row){
					list($id_order, $reference, $id_customer, $date_upd) = dbFetchArray($sql);
					list($total_amount) = dbFetchArray(dbQuery("SELECT SUM(total_amount) FROM tbl_order_detail_sold WHERE id_order = $id_order"));
					$bill_discount = bill_discount($id_order); /// ส่วนลดท้ายบิล
					$total_amount -= $bill_discount; /// ส่วนลดท้ายบิล
					list($first_name, $last_name) = dbFetchArray(dbQuery("SELECT first_name, last_name FROM tbl_customer WHERE id_customer = $id_customer"));
					$customer_name = $first_name." ".$last_name;
					$arr = array($n, $reference, thaiDate($date_upd), $customer_name, ac_format($bill_discount), $total_amount);
					array_push($body, $arr);
					$total_movement += $total_amount;
					$i++;
					$n++;
				}
				$arr = array("","","","","รวม", $total_movement );
				array_push($body, $arr);
			}else{
				$arr = array("ไม่มีรายการตามช่วงที่เลือก");
				array_push($body, $arr);
			}
			$sheet_name = "Sale_report_by_Document";
			$xls = new Excel_XML('UTF-8', false, $sheet_name); 
			$xls->addArray($title);
			$xls->addArray ($body); 
			$xls->generateXML("Sale_report_by_Document"); 
				
	}
//**************************************  รายงานการร้องขอสินค้า แยกตามลูกค้า  *****************************************//
if(isset($_GET['request_by_customer'])&&isset($_GET['id_product'])){
	$id_product = $_GET['id_product'];
	$view = $_GET['view'];
	$product = new product();
	$product->product_detail($id_product);
	$html = "";
	if($id_product ==0){ 
		$where = "id_product !=0";
		if($view == 0){
			$views = "";
			$and = "";
		}else{
			$and ="AND";
			switch($view){
				case "1" :
					$view_selected = $_GET['view_selected'];
					$today = date('Y-m-d');
					switch($view_selected){
									case "week" :
										$rang = getWeek($today);
										break;
									case "month" :
										$rang = getMonth();
										break;
									case "year" :
										$rang = getYear();
										break ;
									default :
										$rang = getMonth();
										break;
								}
						$from = $rang['from']." 00:00:00";
						$to = $rang['to']." 23:59:59";
						$views = "AND (tbl_request_order_detail.date_upd BETWEEN '$from' AND '$to') ";
						break;
					case "2" :
						$from_date = dbDate($_GET['from_date']);
						if($from_date =="1970-01-01"){ $from_date = $today; }
						$to_date = dbDate($_GET['to_date']);
						if($to_date =="1970-01-01"){ $to_date = $today; }
						$from = $from_date." 00:00:00";
						$to = $to_date. "23:59:59";
						$views = " AND (tbl_request_order_detail.date_upd BETWEEN '$from' AND '$to') ";
						break;
					default :
						$views = "";
						break;
			}
		}
	}else{
		$where = "id_product = $id_product ";
		if($view == 0){
			$views = "";
			$and = "";
		}else{
			$and ="AND";
			switch($view){
				case "1" :
					$view_selected = $_GET['view_selected'];
					$today = date('Y-m-d');
					switch($view_selected){
									case "week" :
										$rang = getWeek($today);
										break;
									case "month" :
										$rang = getMonth();
										break;
									case "year" :
										$rang = getYear();
										break ;
									default :
										$rang = getMonth();
										break;
								}
						$from = $rang['from']." 00:00:00";
						$to = $rang['to']." 23:59:59";
						$views = "AND (tbl_request_order_detail.date_upd BETWEEN '$from' AND '$to') ";
						break;
					case "2" :
						$from_date = dbDate($_GET['from_date']);
						if($from_date =="1970-01-01"){ $from_date = $today; }
						$to_date = dbDate($_GET['to_date']);
						if($to_date =="1970-01-01"){ $to_date = $today; }
						$from = $from_date." 00:00:00";
						$to = $to_date. "23:59:59";
						$views = "AND (tbl_request_order_detail.date_upd BETWEEN '$from' AND '$to') ";
						break;
					default :
						$views = "";
						break;
			}
		}
	}
	$sql = dbQuery("SELECT id_customer, SUM(qty) FROM tbl_request_order_detail JOIN tbl_request_order ON tbl_request_order_detail.id_request_order = tbl_request_order.id_request_order WHERE $where $views GROUP BY id_customer");
	$row = dbNumRows($sql);
	$i = 0;
	$html .="<div class='row'><div class='col-lg-12'><div id='accordion' class='panel-group panel-group-lists collapse in' style='height: auto;'>";
	if($row>0){
		while($i<$row){
			list($id_customer, $total_qty) = dbFetchArray($sql);
			$customer = new customer($id_customer);
			$customer_name = $customer->full_name;
			$qr = dbQuery("SELECT id_product_attribute, SUM(qty) FROM tbl_request_order_detail JOIN tbl_request_order ON tbl_request_order_detail.id_request_order = tbl_request_order.id_request_order WHERE $where AND id_customer = $id_customer $views GROUP BY id_product_attribute");
			$rs = dbNumRows($qr);
			$n =0;
			if($rs>0){
				$html .="<div class='panel'>
						<div class='panel-heading'>
				<h4 class='panel-title'><a class='collapsed' data-toggle='collapse' data-parent='#accordion' href='#collapse".$id_customer."'>&nbsp; &nbsp; $customer_name <span class='badge badge-success'>$total_qty</span></a></h4>
					</div>
						<div id='collapse".$id_customer."' class='panel-collapse collapse'>
							<div class='panel-body'>
								<table class='table table-striped'>
									<thead>
										<th style='text-align:center; width:10%;'>รูป</th><th style='text-align:center; width:80%;'>สินค้า</th><th style='text-align:center; width:10%;'>จำนวน</th>
									</thead>";
					while($n<$rs){
							list($id_product_attribute, $qty) = dbFetchArray($qr);
							$product = new product();
							$product->product_attribute_detail($id_product_attribute);
							$html .="<tr><td align='center'><img src='".$product->get_product_attribute_image($id_product_attribute,1)."' /></td><td>".$product->reference." : ".$product->product_name."	</td><td align='center'>$qty</td></tr>";
							$n++;
						}
						$html .="</table></div></div></div>";
			}else{
				$html .="<h4 style='text-align:center;'ไม่มีข้อมูลตามเงื่อนไข";
			}
			$i++;
	}
	}else{
		$html .="ไม่มีข้อมูลตามเงื่อนไข";
	}
	$html .="</div></div></div>";
	echo $html;
}
//**************************************  รายงานการร้องขอสินค้า แยกตามลูกค้า  Export to excel*****************************************//
if(isset($_GET['export_request_by_customer'])&&isset($_GET['id_product'])){
	$id_product = $_GET['id_product'];
	$view = $_GET['view'];
	$view_rank = $view;
	$product = new product();
	$html = "";
	if($id_product ==0){ 
		$where = "id_product !=0";
		if($view == 0){
			$views = "";
			$and = "";
		}else{
			$and ="AND";
			switch($view){
				case "1" :
					$view_selected = $_GET['view_selected'];
					$today = date('Y-m-d');
					switch($view_selected){
									case "week" :
										$rang = getWeek($today);
										break;
									case "month" :
										$rang = getMonth();
										break;
									case "year" :
										$rang = getYear();
										break ;
									default :
										$rang = getMonth();
										break;
								}
						$from = $rang['from']." 00:00:00";
						$to = $rang['to']." 23:59:59";
						$views = "AND (tbl_request_order_detail.date_upd BETWEEN '$from' AND '$to') ";
						break;
					case "2" :
						$from_date = dbDate($_GET['from_date']);
						if($from_date =="1970-01-01"){ $from_date = $today; }
						$to_date = dbDate($_GET['to_date']);
						if($to_date =="1970-01-01"){ $to_date = $today; }
						$from = $from_date." 00:00:00";
						$to = $to_date. "23:59:59";
						$views = " AND (tbl_request_order_detail.date_upd BETWEEN '$from' AND '$to') ";
						break;
					default :
						$views = "";
						break;
			}
		}
	}else{
		$where = "id_product = $id_product ";
		if($view == 0){
			$views = "";
			$and = "";
		}else{
			$and ="AND";
			switch($view){
				case "1" :
					$view_selected = $_GET['view_selected'];
					$today = date('Y-m-d');
					switch($view_selected){
									case "week" :
										$rang = getWeek($today);
										break;
									case "month" :
										$rang = getMonth();
										break;
									case "year" :
										$rang = getYear();
										break ;
									default :
										$rang = getMonth();
										break;
								}
						$from = $rang['from']." 00:00:00";
						$to = $rang['to']." 23:59:59";
						$views = "AND (tbl_request_order_detail.date_upd BETWEEN '$from' AND '$to') ";
						break;
					case "2" :
						$from_date = dbDate($_GET['from_date']);
						if($from_date =="1970-01-01"){ $from_date = $today; }
						$to_date = dbDate($_GET['to_date']);
						if($to_date =="1970-01-01"){ $to_date = $today; }
						$from = $from_date." 00:00:00";
						$to = $to_date. "23:59:59";
						$views = "AND (tbl_request_order_detail.date_upd BETWEEN '$from' AND '$to') ";
						break;
					default :
						$views = "";
						break;
			}
		}
	}
	if($view_rank ==0){ $rank = " ทั้งหมด"; }else{ $rank = thaiDate($from)." ถึง ".thaiDate($to); }
	$report_title = "รายงานการร้องขอสินค้า แยกตามลูกค้า วันที่ ".$rank." : ".COMPANY;
	$title = array(1=>array($report_title));
	$sub_header = array("ลำดับ","ลูกค้า","บาร์โค้ด","รหัส","สินค้า","จำนวน");
	$body = array();
	$line = array(1=>array("==========================================================="));
	$sql = dbQuery("SELECT id_customer, SUM(qty) FROM tbl_request_order_detail JOIN tbl_request_order ON tbl_request_order_detail.id_request_order = tbl_request_order.id_request_order WHERE $where $views GROUP BY id_customer");
	$row = dbNumRows($sql);
	$i = 0;
	$r =1;
	array_push($body, $sub_header);
	if($row>0){
		while($i<$row){
			list($id_customer, $total_qty) = dbFetchArray($sql);
			$customer = new customer($id_customer);
			$customer_name = $customer->full_name;
			$qr = dbQuery("SELECT id_product_attribute, SUM(qty) FROM tbl_request_order_detail JOIN tbl_request_order ON tbl_request_order_detail.id_request_order = tbl_request_order.id_request_order WHERE $where AND id_customer = $id_customer $views GROUP BY id_product_attribute");
			$rs = dbNumRows($qr);
			$n =0;
			if($rs>0){
					while($n<$rs){
							list($id_product_attribute, $qty) = dbFetchArray($qr);
							$product = new product();
							$product->product_attribute_detail($id_product_attribute);
							$product->product_detail($product->id_product);
							$arr = array($r, $customer_name, $product->barcode, $product->reference, $product->product_name,$qty);
							array_push($body, $arr);
							$n++; $r++;
						}
			}else{
				$arr = array("ไม่มีรายการตามเงื่อนไขที่เลือก");
				array_push($body, $arr);
			}
			$i++;
	}
	}else{
		$arr = array("ไม่มีรายการตามเงื่อนไขที่เลือก");
				array_push($body, $arr);
	}
	
	$sheet_name = "Product_request_by_customer";
	$xls = new Excel_XML('UTF-8', false, $sheet_name); 
	$xls->addArray($title);
	$xls->addArray($line);
	$xls->addArray ($body); 
	$xls->generateXML("Product_request_by_customer"); 
}	
//***************************************** รายงานลูกค้า แยกตามรายการสินค้า (product_attribute) *************************************//
if(isset($_GET['customer_by_product_attribute'])&&isset($_GET['view'])&&isset($_GET['product'])){
	$product_rank = $_GET['product'];
	$view_rank = $_GET['view'];	
	$today = date('Y-m-d');
	$from = "";
	$to = "";
	if(isset($_GET['product_from'])&&isset($_GET['product_to'])){ // *** เรียงลำดับ id_product จากน้อยไปมาก
		$p_from  = $_GET['product_from'];
		$p_to = $_GET['product_to'];
			if($p_to < $p_from){
				$product_from = $_GET['product_to'];
				$product_to = $_GET['product_from'];
			}else{
				$product_from = $_GET['product_from'];
				$product_to = $_GET['product_to'];
			}
	}else{ 
		$product_from =""; $product_to = "";
	}
	if(isset($_GET['product_selected'])){ $product_selected = $_GET['product_selected'];}else{ $product_selected="";}
	if(isset($_GET['view_selected'])){ $view_selected = $_GET['view_selected'];}else{ $view_selected = "";}
	if($product_rank==0){  //// product
		$product ="product_reference !=''";
		if($view_rank == 0){
				$view = "";
				}else if($view_rank==1){
					switch($view_selected){
						case "week" :
							$rang = getWeek($today);
							break;
						case "month" :
							$rang = getMonth();
							break;
						case "year" :
							$rang = getYear();
							break ;
						default :
							$rang = getMonth();
							break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = " AND (date_upd BETWEEN '$from' AND '$to') ";   
				}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')."00:00:00"; $to = date('Y-m-d')."23:59:59"; }
					$view = "AND (date_upd BETWEEN '$from' AND '$to') "; 
				}
		}else if($product_rank==1){ 
			$product ="(product_reference BETWEEN '$product_from' AND '$product_to' )";
			if($view_rank == 0){
				$view = "";
				}else if($view_rank==1){
					switch($view_selected){
						case "week" :
							$rang = getWeek($today);
							break;
						case "month" :
							$rang = getMonth();
							break;
						case "year" :
							$rang = getYear();
							break ;
						default :
							$rang = getMonth();
							break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = "AND (date_upd BETWEEN '$from' AND '$to') ";
				}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')."00:00:00"; $to = date('Y-m-d')."23:59:59"; }
					$view = "AND (date_upd BETWEEN '$from' AND '$to') "; 
				}
			}else if($product_rank ==2){
				$product ="product_reference = '$product_selected'";
				if($view_rank == 0){
				$view = "";
				}else if($view_rank==1){
					switch($view_selected){
						case "week" :
							$rang = getWeek($today);
							break;
						case "month" :
							$rang = getMonth();
							break;
						case "year" :
							$rang = getYear();
							break ;
						default :
							$rang = getMonth();
							break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = "AND (date_upd BETWEEN '$from' AND '$to') ";
				}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')."00:00:00"; $to = date('Y-m-d')."23:59:59"; }
					$view = "AND (date_upd BETWEEN '$from' AND '$to') "; 
				}
			}
		
	/////////////////////////////////////////////////////////////////////
	if($view_rank ==0){ $rank = " ทั้งหมด"; }else{ $rank = thaiDate($from)." ถึง ".thaiDate($to); }
	$report_title = "รายงานลูกค้าแยกตามรายการสินค้า วันที่ ".$rank." : ".COMPANY;
	$html = " 	<h4>$report_title</h4> <hr style='border-color:#CCC; margin-top: 0px; margin-bottom:0px;' />
	<table class='table table-striped table-hover'>";
	$header_row = "<tr><td width='60%'>ลูกค้า</td><td width='20%' align='right'>จำนวนรวม</td><td width='20%' align='right'>มูลค่ารวม</td></tr>"; 
		$qr = dbQuery("SELECT id_product_attribute FROM tbl_order_detail_sold WHERE $product  $view AND id_role IN(1,5) GROUP BY id_product_attribute");
		//echo "SELECT id_product_attribute FROM tbl_order_detail_sold WHERE $product  $view AND id_role IN(1,5) GROUP BY id_product_attribute";
		$row = dbNumRows($qr); 
		$i = 0;
		$n = 1;
		while($i<$row){
			$total_qty = 0;
			$total_movement = 0;
			list($id_product_attribute) = dbFetchArray($qr);
			$prod = new product();
			$prod->product_attribute_detail($id_product_attribute);
			$prod->product_detail($prod->id_product);
			$product_name = $prod->product_name;
			$product_code = $prod->reference;
			$barcode = $prod->barcode;
			$sql = dbQuery("SELECT id_customer, SUM(sold_qty), SUM(total_amount) FROM tbl_order_detail_sold WHERE id_product_attribute = $id_product_attribute $view AND id_role IN(1,5) GROUP BY id_customer");
			//echo "SELECT id_customer, SUM(sold_qty), SUM(total_amount) FROM tbl_order_detail_sold WHERE id_product = $id_product $view AND id_role IN(1,5) GROUP BY id_customer";
				$rows = dbNumRows($sql);
				$v = 0;
				$html .="<tr><td colspan='3' style='vertical-align:middle;'>$barcode : $product_code  :  $product_name </td></tr>";
				$html .= $header_row;
				while($v<$rows){
				list($id_customer, $qty, $total_amount) = dbFetchArray($sql);
				list($first_name, $last_name) = dbFetchArray(dbQuery("SELECT first_name, last_name FROM tbl_customer WHERE id_customer = $id_customer"));
				$customer_name = $first_name." ".$last_name;	
				$html .=" <tr><td>$customer_name</td><td align='right'>".number_format($qty)."</td><td align='right'>".number_format($total_amount,2)."</td><tr>";
				$total_movement += $total_amount;
				$total_qty += $qty;
				$v++;
				}
				$html .="<tr><td style='text-align: right; padding-right: 5px;'><h4>รวม</h4></td>
				<td style='text-align: right; padding-right: 5px;'><h4>".number_format($total_qty)."</h4></td>
				<td style='text-align: right; padding-right: 5px;'><h4>".number_format($total_movement,2)."</h4></td></tr>
				<tr><td colspan='3'><h4>&nbsp;</h4></td></tr>";
				$i++;
		}
		$html ."</table>";
		echo $html;
}
		
//***************************************** รายงานลูกค้า แยกตามรายการสินค้า (product_attribute)  Export to excel *************************************//
if(isset($_GET['export_customer_by_product_attribute'])&&isset($_GET['view'])&&isset($_GET['product'])){
	$product_rank = $_GET['product'];
	$view_rank = $_GET['view'];	
	$today = date('Y-m-d');
	$from = "";
	$to = "";
	if(isset($_GET['product_from'])&&isset($_GET['product_to'])){ // *** เรียงลำดับ id_product จากน้อยไปมาก
		$p_from  = $_GET['product_from'];
		$p_to = $_GET['product_to'];
			if($p_to < $p_from){
				$product_from = $_GET['product_to'];
				$product_to = $_GET['product_from'];
			}else{
				$product_from = $_GET['product_from'];
				$product_to = $_GET['product_to'];
			}
	}else{ 
		$product_from =""; $product_to = "";
	}
	if(isset($_GET['product_selected'])){ $product_selected = $_GET['product_selected'];}else{ $product_selected="";}
	if(isset($_GET['view_selected'])){ $view_selected = $_GET['view_selected'];}else{ $view_selected = "";}
	if($product_rank==0){  //// product
		$product ="product_reference !=0";
		if($view_rank == 0){
				$view = "";
				}else if($view_rank==1){
					switch($view_selected){
						case "week" :
							$rang = getWeek($today);
							break;
						case "month" :
							$rang = getMonth();
							break;
						case "year" :
							$rang = getYear();
							break ;
						default :
							$rang = getMonth();
							break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = " AND (date_upd BETWEEN '$from' AND '$to') ";   
				}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')."00:00:00"; $to = date('Y-m-d')."23:59:59"; }
					$view = "AND (date_upd BETWEEN '$from' AND '$to') "; 
				}
		}else if($product_rank==1){ 
			$product ="(product_reference BETWEEN '$product_from' AND '$product_to' )";
			if($view_rank == 0){
				$view = "";
				}else if($view_rank==1){
					switch($view_selected){
						case "week" :
							$rang = getWeek($today);
							break;
						case "month" :
							$rang = getMonth();
							break;
						case "year" :
							$rang = getYear();
							break ;
						default :
							$rang = getMonth();
							break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = "AND (date_upd BETWEEN '$from' AND '$to') ";
				}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')."00:00:00"; $to = date('Y-m-d')."23:59:59"; }
					$view = "AND (date_upd BETWEEN '$from' AND '$to') "; 
				}
			}else if($product_rank ==2){
				$product ="product_reference = '$product_selected'";
				if($view_rank == 0){
				$view = "";
				}else if($view_rank==1){
					switch($view_selected){
						case "week" :
							$rang = getWeek($today);
							break;
						case "month" :
							$rang = getMonth();
							break;
						case "year" :
							$rang = getYear();
							break ;
						default :
							$rang = getMonth();
							break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = "AND (date_upd BETWEEN '$from' AND '$to') ";
				}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')."00:00:00"; $to = date('Y-m-d')."23:59:59"; }
					$view = "AND (date_upd BETWEEN '$from' AND '$to') "; 
				}
			}
		
	/////////////////////////////////////////////////////////////////////
	if($view_rank ==0){ $rank = " ทั้งหมด"; }else{ $rank = thaiDate($from)." ถึง ".thaiDate($to); }
	$report_title = "รายงานลูกค้าแยกตามรายการสินค้า วันที่ ".$rank." : ".COMPANY;
	$title = array(1=>array($report_title));
	$sub_header = array("รหัสลูกค้า","ชื่อลูกค้า","จำนวนรวม","มูลค่ารวม");
	$body = array();
	$line = array(1=>array("==========================================================="));
		$qr = dbQuery("SELECT id_product_attribute FROM tbl_order_detail_sold WHERE $product  $view AND id_role IN(1,5) GROUP BY id_product_attribute");
		$row = dbNumRows($qr); 
		$i = 0;
		$n = 1;
	if($row>0){
		while($i<$row){
			$total_qty = 0;
			$total_movement = 0;
			list($id_product_attribute) = dbFetchArray($qr);
			$prod = new product();
			$prod->product_attribute_detail($id_product_attribute);
			$prod->product_detail($prod->id_product);
			$product_name = $prod->product_name;
			$product_code = $prod->reference;
			$barcode = $prod->barcode;
			$sql = dbQuery("SELECT id_customer, SUM(sold_qty), SUM(total_amount) FROM tbl_order_detail_sold WHERE id_product_attribute = $id_product_attribute $view AND id_role IN(1,5) GROUP BY id_customer");
				$rows = dbNumRows($sql);
				$v = 0;
				$arr = array("$barcode : $product_code  :  $product_name");
				array_push($body, $arr);
				array_push($body, $sub_header);
				while($v<$rows){
				list($id_customer, $qty, $total_amount) = dbFetchArray($sql);
				list($customer_code, $first_name, $last_name) = dbFetchArray(dbQuery("SELECT customer_code, first_name, last_name FROM tbl_customer WHERE id_customer = $id_customer"));
				$customer_name = $first_name." ".$last_name;	
				$arr = array($customer_code, $customer_name, number_format($qty), number_format($total_amount,2));
				array_push($body, $arr);
				$total_movement += $total_amount;
				$total_qty += $qty;
				$v++;
				}
				$arr = array("","รวม", number_format($total_qty), number_format($total_movement,2));
				array_push($body, $arr);
				$arr = array("");
				array_push($body, $arr);
				$i++;
		}
	}else{
		$arr = array("ไม่มีรายการตามเงื่อนไขที่เลือก");
		array_push($body, $arr);
	}
	$sheet_name = "Customer_by_product_attribute";
	$xls = new Excel_XML('UTF-8', false, $sheet_name); 
	$xls->addArray($title);
	$xls->addArray($line);
	$xls->addArray ($body); 
	$xls->generateXML("Customer_by_product_attribute"); 
}
//******************************************* รายงานลูกค้า แยกตามสินค้า  *****************************************************************//
if(isset($_GET['customer_by_product'])&&isset($_GET['view'])&&isset($_GET['product'])){
	$product_rank = $_GET['product'];
	$view_rank = $_GET['view'];	
	$today = date('Y-m-d');
	$from = "";
	$to = "";
	if(isset($_GET['product_from'])&&isset($_GET['product_to'])){ // *** เรียงลำดับ id_product จากน้อยไปมาก
		$p_from  = $_GET['product_from'];
		$p_to = $_GET['product_to'];
			if($p_to < $p_from){
				$product_from = $_GET['product_to'];
				$product_to = $_GET['product_from'];
			}else{
				$product_from = $_GET['product_from'];
				$product_to = $_GET['product_to'];
			}
	}else{ 
		$product_from =""; $product_to = "";
	}
	if(isset($_GET['product_selected'])){ $product_selected = $_GET['product_selected'];}else{ $product_selected="";}
	if(isset($_GET['view_selected'])){ $view_selected = $_GET['view_selected'];}else{ $view_selected = "";}
	if($product_rank==0){  //// product
		$product ="id_product !=0";
		if($view_rank == 0){
				$view = "";
				}else if($view_rank==1){
					switch($view_selected){
						case "week" :
							$rang = getWeek($today);
							break;
						case "month" :
							$rang = getMonth();
							break;
						case "year" :
							$rang = getYear();
							break ;
						default :
							$rang = getMonth();
							break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = " AND (date_upd BETWEEN '$from' AND '$to') ";   
				}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')."00:00:00"; $to = date('Y-m-d')."23:59:59"; }
					$view = "AND (date_upd BETWEEN '$from' AND '$to') "; 
				}
		}else if($product_rank==1){ 
			$product ="(id_product BETWEEN $product_from AND $product_to )";
			if($view_rank == 0){
				$view = "";
				}else if($view_rank==1){
					switch($view_selected){
						case "week" :
							$rang = getWeek($today);
							break;
						case "month" :
							$rang = getMonth();
							break;
						case "year" :
							$rang = getYear();
							break ;
						default :
							$rang = getMonth();
							break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = "AND (date_upd BETWEEN '$from' AND '$to') ";
				}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')."00:00:00"; $to = date('Y-m-d')."23:59:59"; }
					$view = "AND (date_upd BETWEEN '$from' AND '$to') "; 
				}
			}else if($product_rank ==2){
				$product ="id_product = $product_selected";
				if($view_rank == 0){
				$view = "";
				}else if($view_rank==1){
					switch($view_selected){
						case "week" :
							$rang = getWeek($today);
							break;
						case "month" :
							$rang = getMonth();
							break;
						case "year" :
							$rang = getYear();
							break ;
						default :
							$rang = getMonth();
							break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = "AND (date_upd BETWEEN '$from' AND '$to') ";
				}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')."00:00:00"; $to = date('Y-m-d')."23:59:59"; }
					$view = "AND (date_upd BETWEEN '$from' AND '$to') "; 
				}
			}
		
	/////////////////////////////////////////////////////////////////////
	if($view_rank ==0){ $rank = " ทั้งหมด"; }else{ $rank = thaiDate($from)." ถึง ".thaiDate($to); }
	$report_title = "รายงานลูกค้าแยกตามรายการสินค้า วันที่ ".$rank." : ".COMPANY;
	$html = " 	<h4>$report_title</h4> <hr style='border-color:#CCC; margin-top: 0px; margin-bottom:0px;' />
	<table class='table table-striped table-hover'>";
	$header_row = "<tr><td width='60%'>ลูกค้า</td><td width='20%' align='right'>จำนวนรวม</td><td width='20%' align='right'>มูลค่ารวม</td></tr>"; 
		$qr = dbQuery("SELECT id_product FROM tbl_order_detail_sold WHERE $product  $view AND id_role IN(1,5) GROUP BY id_product");
		//echo "SELECT id_product FROM tbl_order_detail_sold WHERE $product  $view AND id_role IN(1,5) GROUP BY id_product";
		$row = dbNumRows($qr); 
		$i = 0;
		$n = 1;
		while($i<$row){
			$total_qty = 0;
			$total_movement = 0;
			list($id_product) = dbFetchArray($qr);
			$prod = new product();
			$prod->product_detail($id_product);
			$product_name = $prod->product_name;
			$product_code = $prod->product_code;
			$sql = dbQuery("SELECT id_customer, SUM(sold_qty), SUM(total_amount) FROM tbl_order_detail_sold WHERE id_product = $id_product $view AND id_role IN(1,5) GROUP BY id_customer");
			//echo "SELECT id_customer, SUM(sold_qty), SUM(total_amount) FROM tbl_order_detail_sold WHERE id_product = $id_product $view AND id_role IN(1,5) GROUP BY id_customer";
				$rows = dbNumRows($sql);
				$v = 0;
				$html .="<tr><td colspan='3' style='vertical-align:middle;'>$product_code  :  $product_name </td></tr>";
				$html .= $header_row;
				while($v<$rows){
				list($id_customer, $qty, $total_amount) = dbFetchArray($sql);
				list($first_name, $last_name) = dbFetchArray(dbQuery("SELECT first_name, last_name FROM tbl_customer WHERE id_customer = $id_customer"));
				$customer_name = $first_name." ".$last_name;	
				$html .=" <tr><td>$customer_name</td><td align='right'>".number_format($qty)."</td><td align='right'>".number_format($total_amount,2)."</td><tr>";
				$total_movement += $total_amount;
				$total_qty += $qty;
				$v++;
				}
				$html .="<tr><td style='text-align: right; padding-right: 5px;'><h4>รวม</h4></td>
				<td style='text-align: right; padding-right: 5px;'><h4>".number_format($total_qty)."</h4></td>
				<td style='text-align: right; padding-right: 5px;'><h4>".number_format($total_movement,2)."</h4></td></tr>
				<tr><td colspan='3'><h4>&nbsp;</h4></td></tr>";
				$i++;
		}
		$html ."</table>";
		echo $html;
}
//*****************************************  รายงานลูกค้า แยกตามสินค้า  Export to excel  **********************************************//
if(isset($_GET['export_customer_by_product'])&&isset($_GET['view'])&&isset($_GET['product'])){
	$product_rank = $_GET['product'];
	$view_rank = $_GET['view'];	
	$today = date('Y-m-d');
	$from = "";
	$to = "";
	if(isset($_GET['product_from'])&&isset($_GET['product_to'])){ // *** เรียงลำดับ id_product จากน้อยไปมาก
		$p_from  = $_GET['product_from'];
		$p_to = $_GET['product_to'];
			if($p_to < $p_from){
				$product_from = $_GET['product_to'];
				$product_to = $_GET['product_from'];
			}else{
				$product_from = $_GET['product_from'];
				$product_to = $_GET['product_to'];
			}
	}else{ 
		$product_from =""; $product_to = "";
	}
	if(isset($_GET['product_selected'])){ $product_selected = $_GET['product_selected'];}else{ $product_selected="";}
	if(isset($_GET['view_selected'])){ $view_selected = $_GET['view_selected'];}else{ $view_selected = "";}
	if($product_rank==0){  //// product
		$product ="id_product !=0";
		if($view_rank == 0){
				$view = "";
				}else if($view_rank==1){
					switch($view_selected){
						case "week" :
							$rang = getWeek($today);
							break;
						case "month" :
							$rang = getMonth();
							break;
						case "year" :
							$rang = getYear();
							break ;
						default :
							$rang = getMonth();
							break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = " AND (date_upd BETWEEN '$from' AND '$to') ";   
				}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')."00:00:00"; $to = date('Y-m-d')."23:59:59"; }
					$view = "AND (date_upd BETWEEN '$from' AND '$to') "; 
				}
		}else if($product_rank==1){ 
			$product ="(id_product BETWEEN $product_from AND $product_to )";
			if($view_rank == 0){
				$view = "";
				}else if($view_rank==1){
					switch($view_selected){
						case "week" :
							$rang = getWeek($today);
							break;
						case "month" :
							$rang = getMonth();
							break;
						case "year" :
							$rang = getYear();
							break ;
						default :
							$rang = getMonth();
							break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = "AND (date_upd BETWEEN '$from' AND '$to') ";
				}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')."00:00:00"; $to = date('Y-m-d')."23:59:59"; }
					$view = "AND (date_upd BETWEEN '$from' AND '$to') "; 
				}
			}else if($product_rank ==2){
				$product ="id_product = $product_selected";
				if($view_rank == 0){
				$view = "";
				}else if($view_rank==1){
					switch($view_selected){
						case "week" :
							$rang = getWeek($today);
							break;
						case "month" :
							$rang = getMonth();
							break;
						case "year" :
							$rang = getYear();
							break ;
						default :
							$rang = getMonth();
							break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = "AND (date_upd BETWEEN '$from' AND '$to') ";
				}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')."00:00:00"; $to = date('Y-m-d')."23:59:59"; }
					$view = "AND (date_upd BETWEEN '$from' AND '$to') "; 
				}
			}
		
	/////////////////////////////////////////////////////////////////////
	if($view_rank ==0){ $rank = " ทั้งหมด"; }else{ $rank = thaiDate($from)." ถึง ".thaiDate($to); }
	$report_title = "รายงานลูกค้าแยกตามรายการสินค้า วันที่ ".$rank." : ".COMPANY;
	$title = array(1=>array($report_title));
	$sub_header = array("ลูกค้า","จำนวนรวม","มูลค่ารวม");
	$body = array();
	$line = array(1=>array("==========================================================="));
	$qr = dbQuery("SELECT id_product FROM tbl_order_detail_sold WHERE $product  $view AND id_role IN(1,5) GROUP BY id_product");
		//echo "SELECT id_product FROM tbl_order_detail_sold WHERE $product  $view AND id_role IN(1,5) GROUP BY id_product";
		$row = dbNumRows($qr); 
		$i = 0;
		$n = 1;
		while($i<$row){
			$total_qty = 0;
			$total_movement = 0;
			list($id_product) = dbFetchArray($qr);
			$prod = new product();
			$prod->product_detail($id_product);
			$product_name = $prod->product_name;
			$product_code = $prod->product_code;
			$sql = dbQuery("SELECT id_customer, SUM(sold_qty), SUM(total_amount) FROM tbl_order_detail_sold WHERE id_product = $id_product $view AND id_role IN(1,5) GROUP BY id_customer");
			//echo "SELECT id_customer, SUM(sold_qty), SUM(total_amount) FROM tbl_order_detail_sold WHERE id_product = $id_product $view AND id_role IN(1,5) GROUP BY id_customer";
				$rows = dbNumRows($sql);
				$v = 0;
				$arr =array("$product_code  :  $product_name");
				array_push($body, $arr);
				array_push($body, $sub_header);
				while($v<$rows){
				list($id_customer, $qty, $total_amount) = dbFetchArray($sql);
				list($first_name, $last_name) = dbFetchArray(dbQuery("SELECT first_name, last_name FROM tbl_customer WHERE id_customer = $id_customer"));
				$customer_name = $first_name." ".$last_name;	
				$arr =array($customer_name, number_format($qty), number_format($total_amount,2));
				array_push($body, $arr);
				$total_movement += $total_amount;
				$total_qty += $qty;
				$v++;
				}
				$arr =array("รวม", number_format($total_qty), number_format($total_movement,2));
				array_push($body, $arr);
				$footer = array("");
				array_push($body, $footer);
				$i++;
		}
		$sheet_name = "Customer_by_product";
		$xls = new Excel_XML('UTF-8', false, $sheet_name); 
		$xls->addArray($title);
		$xls->addArray($line);
		$xls->addArray ($body); 
		$xls->generateXML("Customer_by_product"); 
}
//*********************************************  รายงานสินค้า แยกตามลูกค้า  ************************************************************//
if(isset($_GET['product_by_customer'])&&isset($_GET['view'])&&isset($_GET['customer'])){
	$customer_rank = $_GET['customer'];
	$view_rank = $_GET['view'];	
	$today = date('Y-m-d');
	$from = "";
	$to = "";
	if(isset($_GET['customer_from'])&&isset($_GET['customer_to'])){ // *** เรียงลำดับ id_customer จากน้อยไปมาก
		$p_from  = trim($_GET['customer_from']);
		$p_to = trim($_GET['customer_to']);
			if($p_to < $p_from){
				$customer_from = $p_to;
				$customer_to = $p_from;
			}else{
				$customer_from = $p_from;
				$customer_to = $p_to;
			}
	}else{ 
		$customer_from =""; $customer_to = "";
	}
	if(isset($_GET['customer_selected'])){ $customer_selected = trim($_GET['customer_selected']);}else{ $customer_selected="";}
	if(isset($_GET['view_selected'])){ $view_selected = $_GET['view_selected'];}else{ $view_selected = "";}
	if($customer_rank==0){  //// customer
		$customer ="customer_code !='-1'";
		if($view_rank == 0){
				$view = "";
				}else if($view_rank==1){
					switch($view_selected){
						case "week" :
							$rang = getWeek($today);
							break;
						case "month" :
							$rang = getMonth();
							break;
						case "year" :
							$rang = getYear();
							break ;
						default :
							$rang = getMonth();
							break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = " AND (tbl_order_detail_sold.date_upd BETWEEN '$from' AND '$to') ";   
				}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')."00:00:00"; $to = date('Y-m-d')."23:59:59"; }
					$view = "AND (tbl_order_detail_sold.date_upd BETWEEN '$from' AND '$to') "; 
				}
		}else if($customer_rank==1){ 
			$customer ="(customer_code BETWEEN '$customer_from' AND '$customer_to' )";
			if($view_rank == 0){
				$view = "";
				}else if($view_rank==1){
					switch($view_selected){
						case "week" :
							$rang = getWeek($today);
							break;
						case "month" :
							$rang = getMonth();
							break;
						case "year" :
							$rang = getYear();
							break ;
						default :
							$rang = getMonth();
							break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = "AND (tbl_order_detail_sold.date_upd BETWEEN '$from' AND '$to') ";
				}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')."00:00:00"; $to = date('Y-m-d')."23:59:59"; }
					$view = "AND (tbl_order_detail_sold.date_upd BETWEEN '$from' AND '$to') "; 
				}
			}else if($customer_rank ==2){
				$customer ="customer_code = '$customer_selected'";
				if($view_rank == 0){
				$view = "";
				}else if($view_rank==1){
					switch($view_selected){
						case "week" :
							$rang = getWeek($today);
							break;
						case "month" :
							$rang = getMonth();
							break;
						case "year" :
							$rang = getYear();
							break ;
						default :
							$rang = getMonth();
							break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = "AND (tbl_order_detail_sold.date_upd BETWEEN '$from' AND '$to') ";
				}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')."00:00:00"; $to = date('Y-m-d')."23:59:59"; }
					$view = "AND (tbl_order_detail_sold.date_upd BETWEEN '$from' AND '$to') "; 
				}
			}
		
	/////////////////////////////////////////////////////////////////////
	if($view_rank ==0){ $rank = " ทั้งหมด"; }else{ $rank = thaiDate($from)." ถึง ".thaiDate($to); }
	$report_title = "รายงานสินค้า แยกตามลูกค้า วันที่ ".$rank." : ".COMPANY;
	$html = " 	<h4 align='center'>$report_title</h4> <hr style='border-color:#CCC; margin-top: 0px; margin-bottom:0px;' />
	<table class='table table-striped table-hover'>";
	$header_row = "<tr><td width='60%'>สินค้า</td><td width='20%' align='right'>จำนวนรวม</td><td width='20%' align='right'>มูลค่ารวม</td></tr>"; 
		$qr = dbQuery("SELECT tbl_order_detail_sold.id_customer FROM tbl_order_detail_sold LEFT JOIN tbl_customer ON tbl_order_detail_sold.id_customer = tbl_customer.id_customer WHERE $customer  $view AND id_role IN(1,5) GROUP BY tbl_order_detail_sold.id_customer");
		$row = dbNumRows($qr); 
		$i = 0;
		$n = 1;
		if($row>0){
		while($i<$row){
			$total_qty = 0;
			$total_movement = 0;
			list($id_customer) = dbFetchArray($qr);
			list($first_name, $last_name) = dbFetchArray(dbQuery("SELECT first_name, last_name FROM tbl_customer WHERE id_customer = $id_customer"));
			$customer_name = $first_name." ".$last_name;	
			$sql = dbQuery("SELECT id_product, SUM(sold_qty), SUM(total_amount) FROM tbl_order_detail_sold WHERE id_customer = $id_customer $view AND id_role IN(1,5) GROUP BY id_product");
			//echo "SELECT id_customer, SUM(sold_qty), SUM(total_amount) FROM tbl_order_detail_sold WHERE id_customer = $id_customer $view AND id_role IN(1,5) GROUP BY id_customer";
				$rows = dbNumRows($sql);
				$v = 0;
				$html .="<tr><td colspan='3' style='vertical-align:middle;'><h4>$customer_name </h4></td></tr>";
				$html .= $header_row;
				while($v<$rows){
				list($id_product, $qty, $total_amount) = dbFetchArray($sql);
				$product = new product();
				$product->product_detail($id_product);
				$product_name = $product->product_name;
				$product_code = $product->product_code;
				$html .=" <tr><td>$product_code : $product_name</td><td align='right'>".number_format($qty)."</td><td align='right'>".number_format($total_amount,2)."</td><tr>";
				$total_movement += $total_amount;
				$total_qty += $qty;
				$v++;
				}
				$html .="<tr><td style='text-align: right; padding-right: 5px;'><h4>รวม</h4></td>
				<td style='text-align: right; padding-right: 5px;'><h4>".number_format($total_qty)."</h4></td>
				<td style='text-align: right; padding-right: 5px;'><h4>".number_format($total_movement,2)."</h4></td></tr>
				<tr><td colspan='3'><h4>&nbsp;</h4></td></tr>";
				$i++;
		}
		}else{
			$html .="<tr><td colspan='3'><h4 align='center'>ไม่มีรายการตามเงื่อนไขที่เลือก</h4></td></tr>";
		}
		$html ."</table>";
		echo $html;
}
//*********************************************  รายงานสินค้า แยกตามลูกค้า  Export to excel ************************************************************//
if(isset($_GET['export_product_by_customer'])&&isset($_GET['view'])&&isset($_GET['customer'])){
	$customer_rank = $_GET['customer'];
	$view_rank = $_GET['view'];	
	$today = date('Y-m-d');
	$from = "";
	$to = "";
	if(isset($_GET['customer_from'])&&isset($_GET['customer_to'])){ // *** เรียงลำดับ id_customer จากน้อยไปมาก
		$p_from  = trim($_GET['customer_from']);
		$p_to = trim($_GET['customer_to']);
			if($p_to < $p_from){
				$customer_from = $p_to;
				$customer_to = $p_from;
			}else{
				$customer_from = $p_from;
				$customer_to = $p_to;
			}
	}else{ 
		$customer_from =""; $customer_to = "";
	}
	if(isset($_GET['customer_selected'])){ $customer_selected = trim($_GET['customer_selected']);}else{ $customer_selected="";}
	if(isset($_GET['view_selected'])){ $view_selected = $_GET['view_selected'];}else{ $view_selected = "";}
	if($customer_rank==0){  //// customer
		$customer ="customer_code !='-1'";
		if($view_rank == 0){
				$view = "";
				}else if($view_rank==1){
					switch($view_selected){
						case "week" :
							$rang = getWeek($today);
							break;
						case "month" :
							$rang = getMonth();
							break;
						case "year" :
							$rang = getYear();
							break ;
						default :
							$rang = getMonth();
							break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = " AND (tbl_order_detail_sold.date_upd BETWEEN '$from' AND '$to') ";   
				}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')."00:00:00"; $to = date('Y-m-d')."23:59:59"; }
					$view = "AND (tbl_order_detail_sold.date_upd BETWEEN '$from' AND '$to') "; 
				}
		}else if($customer_rank==1){ 
			$customer ="(customer_code BETWEEN '$customer_from' AND '$customer_to' )";
			if($view_rank == 0){
				$view = "";
				}else if($view_rank==1){
					switch($view_selected){
						case "week" :
							$rang = getWeek($today);
							break;
						case "month" :
							$rang = getMonth();
							break;
						case "year" :
							$rang = getYear();
							break ;
						default :
							$rang = getMonth();
							break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = "AND (tbl_order_detail_sold.date_upd BETWEEN '$from' AND '$to') ";
				}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')."00:00:00"; $to = date('Y-m-d')."23:59:59"; }
					$view = "AND (tbl_order_detail_sold.date_upd BETWEEN '$from' AND '$to') "; 
				}
			}else if($customer_rank ==2){
				$customer ="customer_code = '$customer_selected'";
				if($view_rank == 0){
				$view = "";
				}else if($view_rank==1){
					switch($view_selected){
						case "week" :
							$rang = getWeek($today);
							break;
						case "month" :
							$rang = getMonth();
							break;
						case "year" :
							$rang = getYear();
							break ;
						default :
							$rang = getMonth();
							break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = "AND (tbl_order_detail_sold.date_upd BETWEEN '$from' AND '$to') ";
				}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')."00:00:00"; $to = date('Y-m-d')."23:59:59"; }
					$view = "AND (tbl_order_detail_sold.date_upd BETWEEN '$from' AND '$to') "; 
				}
			}
	/////////////////////////////////////////////////////////////////////
	if($view_rank ==0){ $rank = " ทั้งหมด"; }else{ $rank = thaiDate($from)." ถึง ".thaiDate($to); }
	$report_title = "รายงานสินค้า แยกตามลูกค้า วันที่ ".$rank." : ".COMPANY;
	$title = array(1=>array($report_title));
	$sub_header = array("สินค้า ","จำนวนรวม","มูลค่ารวม");
	$body = array();
	$line = array(1=>array("==========================================================="));
		$qr = dbQuery("SELECT tbl_order_detail_sold.id_customer FROM tbl_order_detail_sold LEFT JOIN tbl_customer ON tbl_order_detail_sold.id_customer = tbl_customer.id_customer WHERE $customer  $view AND id_role IN(1,5) GROUP BY tbl_order_detail_sold.id_customer");
		$row = dbNumRows($qr); 
		$i = 0;
		$n = 1;
		if($row>0){
		while($i<$row){
			$total_qty = 0;
			$total_movement = 0;
			list($id_customer) = dbFetchArray($qr);
			list($first_name, $last_name) = dbFetchArray(dbQuery("SELECT first_name, last_name FROM tbl_customer WHERE id_customer = $id_customer"));
			$customer_name = $first_name." ".$last_name;	
			$sql = dbQuery("SELECT id_product, SUM(sold_qty), SUM(total_amount) FROM tbl_order_detail_sold WHERE id_customer = $id_customer $view AND id_role IN(1,5) GROUP BY id_product");
				$rows = dbNumRows($sql);
				$v = 0;
				$arr = array($customer_name);
				array_push($body, $arr);
				array_push($body, $sub_header);
				while($v<$rows){
				list($id_product, $qty, $total_amount) = dbFetchArray($sql);
				$product = new product();
				$product->product_detail($id_product);
				$product_name = $product->product_name;
				$product_code = $product->product_code;
				$arr = array("$product_code : $product_name", number_format($qty), number_format($total_amount,2));
				array_push($body, $arr);
				$total_movement += $total_amount;
				$total_qty += $qty;
				$v++;
				}
				$arr =array("รวม", number_format($total_qty), number_format($total_movement,2));
				array_push($body, $arr);
				$arr = array("");
				array_push($body, $arr);
				$i++;
		}
		}else{
			$arr = array("ไม่มีรายการตามเงื่อนไขที่เลือก");
			array_push($body, $arr);
		}
		$sheet_name = "Product_by_customer";
		$xls = new Excel_XML('UTF-8', false, $sheet_name); 
		$xls->addArray($title);
		$xls->addArray($line);
		$xls->addArray ($body); 
		$xls->generateXML("Product_by_customer");
}
//********************************************* รายงานรายการสินค้า แยกตามลูกค้า ********************************************************************//
if(isset($_GET['product_attribute_by_customer'])&&isset($_GET['view'])&&isset($_GET['customer'])){
	$customer_rank = $_GET['customer'];
	$view_rank = $_GET['view'];	
	$today = date('Y-m-d');
	$from = "";
	$to = "";
	if(isset($_GET['customer_from'])&&isset($_GET['customer_to'])){ // *** เรียงลำดับ id_customer จากน้อยไปมาก
		$p_from  = trim($_GET['customer_from']);
		$p_to = trim($_GET['customer_to']);
			if($p_to < $p_from){
				$customer_from = $p_to;
				$customer_to = $p_from;
			}else{
				$customer_from = $p_from;
				$customer_to = $p_to;
			}
	}else{ 
		$customer_from =""; $customer_to = "";
	}
	if(isset($_GET['customer_selected'])){ $customer_selected = trim($_GET['customer_selected']);}else{ $customer_selected="";}
	if(isset($_GET['view_selected'])){ $view_selected = $_GET['view_selected'];}else{ $view_selected = "";}
	if($customer_rank==0){  //// customer
		$customer ="customer_code !='-1'";
		if($view_rank == 0){
				$view = "";
				}else if($view_rank==1){
					switch($view_selected){
						case "week" :
							$rang = getWeek($today);
							break;
						case "month" :
							$rang = getMonth();
							break;
						case "year" :
							$rang = getYear();
							break ;
						default :
							$rang = getMonth();
							break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = " AND (tbl_order_detail_sold.date_upd BETWEEN '$from' AND '$to') ";   
				}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')."00:00:00"; $to = date('Y-m-d')."23:59:59"; }
					$view = "AND (tbl_order_detail_sold.date_upd BETWEEN '$from' AND '$to') "; 
				}
		}else if($customer_rank==1){ 
			$customer ="(customer_code BETWEEN '$customer_from' AND '$customer_to' )";
			if($view_rank == 0){
				$view = "";
				}else if($view_rank==1){
					switch($view_selected){
						case "week" :
							$rang = getWeek($today);
							break;
						case "month" :
							$rang = getMonth();
							break;
						case "year" :
							$rang = getYear();
							break ;
						default :
							$rang = getMonth();
							break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = "AND (tbl_order_detail_sold.date_upd BETWEEN '$from' AND '$to') ";
				}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')."00:00:00"; $to = date('Y-m-d')."23:59:59"; }
					$view = "AND (tbl_order_detail_sold.date_upd BETWEEN '$from' AND '$to') "; 
				}
			}else if($customer_rank ==2){
				$customer ="customer_code = '$customer_selected'";
				if($view_rank == 0){
				$view = "";
				}else if($view_rank==1){
					switch($view_selected){
						case "week" :
							$rang = getWeek($today);
							break;
						case "month" :
							$rang = getMonth();
							break;
						case "year" :
							$rang = getYear();
							break ;
						default :
							$rang = getMonth();
							break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = "AND (tbl_order_detail_sold.date_upd BETWEEN '$from' AND '$to') ";
				}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')."00:00:00"; $to = date('Y-m-d')."23:59:59"; }
					$view = "AND (tbl_order_detail_sold.date_upd BETWEEN '$from' AND '$to') "; 
				}
			}
	/////////////////////////////////////////////////////////////////////
	if($view_rank ==0){ $rank = " ทั้งหมด"; }else{ $rank = thaiDate($from)." ถึง ".thaiDate($to); }
	$report_title = "รายงานรายการสินค้า แยกตามลูกค้า วันที่ ".$rank." : ".COMPANY;
	$html = " 	<h4 align='center'>$report_title</h4> <hr style='border-color:#CCC; margin-top: 0px; margin-bottom:0px;' />
	<table class='table table-striped table-hover'>";
	$header_row = "<tr><td width='15%'>บาร์โค้ด</td><td width='45%'>สินค้า</td><td width='20%' align='right'>จำนวนรวม</td><td width='20%' align='right'>มูลค่ารวม</td></tr>"; 
		$qr = dbQuery("SELECT tbl_order_detail_sold.id_customer FROM tbl_order_detail_sold LEFT JOIN tbl_customer ON tbl_order_detail_sold.id_customer = tbl_customer.id_customer WHERE $customer  $view AND id_role IN(1,5) GROUP BY tbl_order_detail_sold.id_customer");
		//echo "SELECT id_customer FROM tbl_order_detail_sold WHERE $customer  $view AND id_role IN(1,5) GROUP BY id_customer";
		$row = dbNumRows($qr); 
		$i = 0;
		$n = 1;
		if($row>0){
		while($i<$row){
			$total_qty = 0;
			$total_movement = 0;
			list($id_customer) = dbFetchArray($qr);
			list($first_name, $last_name) = dbFetchArray(dbQuery("SELECT first_name, last_name FROM tbl_customer WHERE id_customer = $id_customer"));
			$customer_name = $first_name." ".$last_name;	
			$sql = dbQuery("SELECT product_name, product_reference, barcode,  SUM(sold_qty), SUM(total_amount) FROM tbl_order_detail_sold WHERE id_customer = $id_customer $view AND id_role IN(1,5) GROUP BY id_product_attribute");
			//echo "SELECT id_customer, SUM(sold_qty), SUM(total_amount) FROM tbl_order_detail_sold WHERE id_customer = $id_customer $view AND id_role IN(1,5) GROUP BY id_customer";
				$rows = dbNumRows($sql);
				$v = 0;
				$html .="<tr><td colspan='4' style='vertical-align:middle;'><h4>$customer_name</h4></td></tr>";
				$html .= $header_row;
				while($v<$rows){
				list($product_name, $product_reference, $barcode, $qty, $total_amount) = dbFetchArray($sql);	
				$html .=" <tr style='font-size:12px;'><td>$barcode</td><td>$product_reference : $product_name</td><td align='right'>".number_format($qty)."</td><td align='right'>".number_format($total_amount,2)."</td><tr>";
				$total_movement += $total_amount;
				$total_qty += $qty;
				$v++;
				}
				$html .="<tr><td colspan='2' style='text-align: right; padding-right: 5px;'><h4>รวม</h4></td>
				<td style='text-align: right; padding-right: 5px;'><h4>".number_format($total_qty)."</h4></td>
				<td style='text-align: right; padding-right: 5px;'><h4>".number_format($total_movement,2)."</h4></td></tr>
				<tr><td colspan='4'><h4>&nbsp;</h4></td></tr>";
				$i++;
		}
		}else{
			$html .="<tr><td colspan='4'><h4 align='center'>ไม่มีรายการตามเงื่อนไขที่เลือก</h4></td></tr>";
		}
		$html ."</table>";
		echo $html;
}
//***************************************** รายงานเอกสาร แยกตามรายการสินค้า (product_attribute) *************************************//
if(isset($_GET['document_by_product_attribute'])&&isset($_GET['view'])&&isset($_GET['product'])){
	$product_rank = $_GET['product'];
	$view_rank = $_GET['view'];	
	$today = date('Y-m-d');
	$from = "";
	$to = "";
	if(isset($_GET['product_from'])&&isset($_GET['product_to'])){ // *** เรียงลำดับ id_product จากน้อยไปมาก
		$p_from  = $_GET['product_from'];
		$p_to = $_GET['product_to'];
			if($p_to < $p_from){
				$product_from = $_GET['product_to'];
				$product_to = $_GET['product_from'];
			}else{
				$product_from = $_GET['product_from'];
				$product_to = $_GET['product_to'];
			}
	}else{ 
		$product_from =""; $product_to = "";
	}
	if(isset($_GET['product_selected'])){ $product_selected = $_GET['product_selected'];}else{ $product_selected="";}
	if(isset($_GET['view_selected'])){ $view_selected = $_GET['view_selected'];}else{ $view_selected = "";}
	if($product_rank==0){  //// product
		$product ="product_reference !=''";
		if($view_rank == 0){
				$view = "";
				}else if($view_rank==1){
					switch($view_selected){
						case "week" :
							$rang = getWeek($today);
							break;
						case "month" :
							$rang = getMonth();
							break;
						case "year" :
							$rang = getYear();
							break ;
						default :
							$rang = getMonth();
							break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = " AND (tbl_order_detail_sold.date_upd BETWEEN '$from' AND '$to') ";   
				}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')."00:00:00"; $to = date('Y-m-d')."23:59:59"; }
					$view = "AND (tbl_order_detail_sold.date_upd BETWEEN '$from' AND '$to') "; 
				}
		}else if($product_rank==1){ 
			$product ="(tbl_order_detail_sold.product_reference BETWEEN '$product_from' AND '$product_to' )";
			if($view_rank == 0){
				$view = "";
				}else if($view_rank==1){
					switch($view_selected){
						case "week" :
							$rang = getWeek($today);
							break;
						case "month" :
							$rang = getMonth();
							break;
						case "year" :
							$rang = getYear();
							break ;
						default :
							$rang = getMonth();
							break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = "AND (tbl_order_detail_sold.date_upd BETWEEN '$from' AND '$to') ";
				}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')."00:00:00"; $to = date('Y-m-d')."23:59:59"; }
					$view = "AND (tbl_order_detail_sold.date_upd BETWEEN '$from' AND '$to') "; 
				}
			}else if($product_rank ==2){
				$product ="product_reference = '$product_selected'";
				if($view_rank == 0){
				$view = "";
				}else if($view_rank==1){
					switch($view_selected){
						case "week" :
							$rang = getWeek($today);
							break;
						case "month" :
							$rang = getMonth();
							break;
						case "year" :
							$rang = getYear();
							break ;
						default :
							$rang = getMonth();
							break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = "AND (tbl_order_detail_sold.date_upd BETWEEN '$from' AND '$to') ";
				}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')."00:00:00"; $to = date('Y-m-d')."23:59:59"; }
					$view = "AND (tbl_order_detail_sold.date_upd BETWEEN '$from' AND '$to') "; 
				}
			}
		
	/////////////////////////////////////////////////////////////////////
	if($view_rank ==0){ $rank = " ทั้งหมด"; }else{ $rank = thaiDate($from)." ถึง ".thaiDate($to); }
	$report_title = "รายงานเอกสาร แยกตามรายการสินค้า วันที่ ".$rank." : ".COMPANY;
	$html = " 	<h4>$report_title</h4> <hr style='border-color:#CCC; margin-top: 0px; margin-bottom:0px;' />
	<table class='table table-striped table-hover'>
		<thead>
			<th style='align:center; width:10%;'>วันที่</th><th style='align:center; width:10%;'>เอกสาร</th><th style='align:center; width:10%;'>บาร์โค้ด</th><th style='align:center; width:15%;'>รหัส</th>
			<th style='align:center; width:20%;'>ชื่อสินค้า</th><th style='align:center; width:10%;'>จำนวน</th><th style='align:center; width:20%;'>ลูกค้า</th><th style='align:center; width:10%;'>พนักงาน</th>
		</thead>";
		$qr = dbQuery("SELECT  reference, id_customer, id_employee, id_product, barcode, product_reference, sold_qty, date_upd   FROM tbl_order_detail_sold WHERE $product  $view AND id_role IN(1,5) ORDER BY product_reference ASC");
		$row = dbNumRows($qr); 
		$i = 0;
		while($i<$row){
			list($reference, $id_customer, $id_employee, $id_product, $barcode, $product_reference, $qty_sold, $date_upd) = dbFetchArray($qr);
			list($product_name) = dbFetchArray(dbQuery("SELECT product_name FROM tbl_product WHERE id_product = $id_product"));
			$employee = new employee($id_employee);
			$employee_name = $employee->first_name;
			$customer = new customer($id_customer);
			$customer_name = $customer->full_name;
			$date = thaiDate($date_upd);
			$html .=" <tr><td>$date</td><td>$reference</td><td>$barcode</td><td>$product_reference</td><td>$product_name</td><td align='center'>".number_format($qty_sold)."</td><td>$customer_name</td><td>$employee_name</td><tr>";
				$i++;
		}
		$html ."</table>";
		echo $html;
}
//***************************************** รายละเอียดการขาย แยกตามพนักงานขาย  *************************************//
if(isset($_GET['sale_amount_detail'])&&isset($_GET['view'])&&isset($_GET['sale'])){
	$sale_rank = $_GET['sale'];
	$view_rank = $_GET['view'];	
	$today = date('Y-m-d');
	$from = "";
	$to = "";
	if(isset($_GET['sale_from'])&&isset($_GET['sale_to'])){ // *** เรียงลำดับ id_sale จากน้อยไปมาก
		$p_from  = $_GET['sale_from'];
		$p_to = $_GET['sale_to'];
			if($p_to < $p_from){
				$sale_from = $_GET['sale_to'];
				$sale_to = $_GET['sale_from'];
			}else{
				$sale_from = $_GET['sale_from'];
				$sale_to = $_GET['sale_to'];
			}
	}else{ 
		$sale_from =""; $sale_to = "";
	}
	if(isset($_GET['sale_selected'])){ $sale_selected = $_GET['sale_selected'];}else{ $sale_selected="";}
	if(isset($_GET['view_selected'])){ $view_selected = $_GET['view_selected'];}else{ $view_selected = "";}
	if($sale_rank==0){  //// sale
		$sale ="id_sale !='-1'";
		if($view_rank == 0){
				$view = "";
				}else if($view_rank==1){
					switch($view_selected){
						case "week" :
							$rang = getWeek($today);
							break;
						case "month" :
							$rang = getMonth();
							break;
						case "year" :
							$rang = getYear();
							break ;
						default :
							$rang = getMonth();
							break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = " AND (date_upd BETWEEN '$from' AND '$to') ";   
				}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')." 00:00:00"; $to = date('Y-m-d')." 23:59:59"; }
					$view = "AND (date_upd BETWEEN '$from' AND '$to') "; 
				}
		}else if($sale_rank==1){ 
			$sale ="(id_sale BETWEEN '$sale_from' AND '$sale_to' )";
			if($view_rank == 0){
				$view = "";
				}else if($view_rank==1){
					switch($view_selected){
						case "week" :
							$rang = getWeek($today);
							break;
						case "month" :
							$rang = getMonth();
							break;
						case "year" :
							$rang = getYear();
							break ;
						default :
							$rang = getMonth();
							break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = "AND (date_upd BETWEEN '$from' AND '$to') ";
				}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')." 00:00:00"; $to = date('Y-m-d')." 23:59:59"; }
					$view = "AND (date_upd BETWEEN '$from' AND '$to') "; 
				}
			}else if($sale_rank ==2){
				$sale ="id_sale = '$sale_selected'";
				if($view_rank == 0){
				$view = "";
				}else if($view_rank==1){
					switch($view_selected){
						case "week" :
							$rang = getWeek($today);
							break;
						case "month" :
							$rang = getMonth();
							break;
						case "year" :
							$rang = getYear();
							break ;
						default :
							$rang = getMonth();
							break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = "AND (date_upd BETWEEN '$from' AND '$to') ";
				}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')."00:00:00"; $to = date('Y-m-d')."23:59:59"; }
					$view = "AND (date_upd BETWEEN '$from' AND '$to') "; 
				}
			}
		
	/////////////////////////////////////////////////////////////////////
	if($view_rank ==0){ $rank = " ทั้งหมด"; }else{ $rank = thaiDate($from)." ถึง ".thaiDate($to); }
	$report_title = "รายละเอียดการขาย  วันที่ ".$rank;
	$html = "<table class='table table-striped table-hover'>";
	$header = "
		<tr>
			<td style='width:10%;'>วันที่</td><td style='align:center; width:10%;'>เอกสาร</td><td style='width:15%;'>ลูกค้า</td><td style='width:10%;'>บาร์โค้ด</td><td style='width:15%;'>รหัส</td>
			<td style='text-align: right; width:10%;'>ราคา</td><td style='text-align: right; width:10%;'>จำนวน</td><td style='text-align: right; width:10%;'>ส่วนลด(มูลค่า)</td><td style='text-align: right; width:10%;'>มูลค่า</td>
		</tr>";
		$sql = dbQuery("SELECT id_sale, id_employee FROM tbl_sale WHERE $sale"); 
		$rs = dbNumRows($sql);
		if($rs>0){
			$r = 0;
			while($r<$rs){
				list($id_sale, $id_employee) = dbFetchArray($sql);
				$employee = new employee($id_employee);
				$sale_name = $employee->full_name;
				$sale_first_name = $employee->first_name;
				$html .= "<tr><td colspan='10' align='center'><h4>$report_title &nbsp;&nbsp; พนักงาน : $sale_name </h4></td></tr>";
				$html .= $header;
				$qr = dbQuery("SELECT  reference, id_customer, product_reference, barcode, product_price, sold_qty, discount_amount, total_amount, date_upd   FROM tbl_order_detail_sold WHERE id_sale = $id_sale  $view AND id_role IN(1,5) ORDER BY date_upd ASC");
				$row = dbNumRows($qr); 
				$i = 0;
				$move_qty = 0; $move_amount = 0; $discount = 0;
					while($i<$row){
						list($reference, $id_customer, $product_reference, $barcode, $product_price, $sold_qty, $discount_amount, $total_amount, $date_upd) = dbFetchArray($qr);
						$customer = new customer($id_customer);
						$customer_name = $customer->full_name;
						$date = thaiDate($date_upd);
						$html .=" <tr style='font-size:12px;'><td>$date</td><td>$reference</td><td>$customer_name</td><td>$barcode</td><td>$product_reference</td>
						<td align='right'>".number_format($product_price,2)."</td><td align='right'>".number_format($sold_qty)."</td><td align='right'>".number_format($discount_amount,2)."</td><td align='right'>".number_format($total_amount,2)."</td></tr>";
						$move_qty += $sold_qty; 
						$move_amount += $total_amount;
						$discount += $discount_amount;
							$i++;
					}
				$html .= "<tr><td colspan='6' align='right'><b>รวม </b></td><td align='right'><b>".number_format($move_qty)."</b></td>
							<td align='right'><b>".number_format($discount,2)."</b></td><td align='right'><b>".number_format($move_amount,2)."</b></td></tr>";
				$html .="<tr><td colspan='9' align='center'><h4> &nbsp;</h4></td></tr>";
				$r++;	
			}
		}
			$html .="</table>";
		echo $html;
}
//***************************************** รายละเอียดการขาย แยกตามพนักงานขาย Export_to_excel *************************************//
if(isset($_GET['export_sale_amount_detail'])&&isset($_GET['view'])&&isset($_GET['sale'])){
	$sale_rank = $_GET['sale'];
	$view_rank = $_GET['view'];	
	$today = date('Y-m-d');
	$from = "";
	$to = "";
	if(isset($_GET['sale_from'])&&isset($_GET['sale_to'])){ // *** เรียงลำดับ id_sale จากน้อยไปมาก
		$p_from  = $_GET['sale_from'];
		$p_to = $_GET['sale_to'];
			if($p_to < $p_from){
				$sale_from = $_GET['sale_to'];
				$sale_to = $_GET['sale_from'];
			}else{
				$sale_from = $_GET['sale_from'];
				$sale_to = $_GET['sale_to'];
			}
	}else{ 
		$sale_from =""; $sale_to = "";
	}
	if(isset($_GET['sale_selected'])){ $sale_selected = $_GET['sale_selected'];}else{ $sale_selected="";}
	if(isset($_GET['view_selected'])){ $view_selected = $_GET['view_selected'];}else{ $view_selected = "";}
	if($sale_rank==0){  //// sale
		$sale ="id_sale !='-1'";
		if($view_rank == 0){
				$view = "";
				}else if($view_rank==1){
					switch($view_selected){
						case "week" :
							$rang = getWeek($today);
							break;
						case "month" :
							$rang = getMonth();
							break;
						case "year" :
							$rang = getYear();
							break ;
						default :
							$rang = getMonth();
							break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = " AND (date_upd BETWEEN '$from' AND '$to') ";   
				}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')." 00:00:00"; $to = date('Y-m-d')." 23:59:59"; }
					$view = "AND (date_upd BETWEEN '$from' AND '$to') "; 
				}
		}else if($sale_rank==1){ 
			$sale ="(id_sale BETWEEN '$sale_from' AND '$sale_to' )";
			if($view_rank == 0){
				$view = "";
				}else if($view_rank==1){
					switch($view_selected){
						case "week" :
							$rang = getWeek($today);
							break;
						case "month" :
							$rang = getMonth();
							break;
						case "year" :
							$rang = getYear();
							break ;
						default :
							$rang = getMonth();
							break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = "AND (date_upd BETWEEN '$from' AND '$to') ";
				}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')." 00:00:00"; $to = date('Y-m-d')." 23:59:59"; }
					$view = "AND (date_upd BETWEEN '$from' AND '$to') "; 
				}
			}else if($sale_rank ==2){
				$sale ="id_sale = '$sale_selected'";
				if($view_rank == 0){
				$view = "";
				}else if($view_rank==1){
					switch($view_selected){
						case "week" :
							$rang = getWeek($today);
							break;
						case "month" :
							$rang = getMonth();
							break;
						case "year" :
							$rang = getYear();
							break ;
						default :
							$rang = getMonth();
							break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = "AND (date_upd BETWEEN '$from' AND '$to') ";
				}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')."00:00:00"; $to = date('Y-m-d')."23:59:59"; }
					$view = "AND (date_upd BETWEEN '$from' AND '$to') "; 
				}
			}
		
	/////////////////////////////////////////////////////////////////////
	if($view_rank ==0){ $rank = " ทั้งหมด"; }else{ $rank = thaiDate($from)." ถึง ".thaiDate($to); }
	$report_title = "รายละเอียดการขาย  วันที่ ".$rank." : ".COMPANY;
	$title = array(1=>array($report_title));
	$sub_header = array("วันที่", "เอกสาร","ลูกค้า", "บาร์โค้ด","รหัส","ราคา","จำนวน","ส่วนลด","มูลค่า");
	$body = array();
	$line = array(1=>array("==========================================================="));
		$sql = dbQuery("SELECT id_sale, id_employee FROM tbl_sale WHERE $sale");
		$rs = dbNumRows($sql);
		if($rs>0){
			$r = 0;
			while($r<$rs){
				list($id_sale, $id_employee) = dbFetchArray($sql);
				$employee = new employee($id_employee);
				$sale_name = $employee->full_name;
				$sale_first_name = $employee->first_name;
				$arr = array("พนักงาน : $sale_name ");
				array_push($body, $arr);
				array_push($body, $sub_header);
				$qr = dbQuery("SELECT  reference, id_customer, product_reference, barcode, product_price, sold_qty, discount_amount, total_amount, date_upd   FROM tbl_order_detail_sold WHERE id_sale = $id_sale  $view AND id_role IN(1,5) ORDER BY date_upd ASC");
				$row = dbNumRows($qr); 
				$i = 0;
				$move_qty = 0; $move_amount = 0; $discount =0;
					while($i<$row){
						list($reference, $id_customer, $product_reference, $barcode, $product_price, $sold_qty, $discount_amount, $total_amount, $date_upd) = dbFetchArray($qr);
						$customer = new customer($id_customer);
						$customer_name = $customer->full_name;
						$date = thaiDate($date_upd);
						$arr = array($date, $reference, $customer_name, $barcode, $product_reference, number_format($product_price,2), number_format($sold_qty), number_format($discount_amount,2), number_format($total_amount,2));
						array_push($body, $arr);
						$move_qty += $sold_qty; 
						$move_amount += $total_amount;
						$discount += $discount_amount;
							$i++;
					}
				$arr = array("","","","","","รวม", number_format($move_qty), number_format($discount,2) , number_format($move_amount,2)); 
				array_push($body, $arr);
				$arr = array("-----------------------------------------------------------------------------------------------------------------------------");
				array_push($body, $arr);
				$arr = array("");
				array_push($body, $arr);
				$r++;	
			}
		}
		$sheet_name = "Sale_amount_detail";
		$xls = new Excel_XML('UTF-8', false, $sheet_name); 
		$xls->addArray($title);
		$xls->addArray($line);
		$xls->addArray ($body); 
		$xls->generateXML("Sale_amount_detail");	
		
}
//***************************************** รายยอดขาย แยกตามพนักงานขายและเอกสาร  *************************************//
if(isset($_GET['sale_amount_document'])&&isset($_GET['view'])&&isset($_GET['sale'])){
	$sale_rank = $_GET['sale'];
	$view_rank = $_GET['view'];	
	$today = date('Y-m-d');
	$from = "";
	$to = "";
	if(isset($_GET['sale_from'])&&isset($_GET['sale_to'])){ // *** เรียงลำดับ id_sale จากน้อยไปมาก
		$p_from  = $_GET['sale_from'];
		$p_to = $_GET['sale_to'];
			if($p_to < $p_from){
				$sale_from = $_GET['sale_to'];
				$sale_to = $_GET['sale_from'];
			}else{
				$sale_from = $_GET['sale_from'];
				$sale_to = $_GET['sale_to'];
			}
	}else{ 
		$sale_from =""; $sale_to = "";
	}
	if(isset($_GET['sale_selected'])){ $sale_selected = $_GET['sale_selected'];}else{ $sale_selected="";}
	if(isset($_GET['view_selected'])){ $view_selected = $_GET['view_selected'];}else{ $view_selected = "";}
	if($sale_rank==0){  //// sale
		$sale ="id_sale !='-1'";
		if($view_rank == 0){
				$view = "";
				}else if($view_rank==1){
					switch($view_selected){
						case "week" :
							$rang = getWeek($today);
							break;
						case "month" :
							$rang = getMonth();
							break;
						case "year" :
							$rang = getYear();
							break ;
						default :
							$rang = getMonth();
							break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = " AND (date_upd BETWEEN '$from' AND '$to') ";   
				}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')." 00:00:00"; $to = date('Y-m-d')." 23:59:59"; }
					$view = "AND (date_upd BETWEEN '$from' AND '$to') "; 
				}
		}else if($sale_rank==1){ 
			$sale ="(id_sale BETWEEN '$sale_from' AND '$sale_to' )";
			if($view_rank == 0){
				$view = "";
				}else if($view_rank==1){
					switch($view_selected){
						case "week" :
							$rang = getWeek($today);
							break;
						case "month" :
							$rang = getMonth();
							break;
						case "year" :
							$rang = getYear();
							break ;
						default :
							$rang = getMonth();
							break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = "AND (date_upd BETWEEN '$from' AND '$to') ";
				}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')." 00:00:00"; $to = date('Y-m-d')." 23:59:59"; }
					$view = "AND (date_upd BETWEEN '$from' AND '$to') "; 
				}
			}else if($sale_rank ==2){
				$sale ="id_sale = '$sale_selected'";
				if($view_rank == 0){
				$view = "";
				}else if($view_rank==1){
					switch($view_selected){
						case "week" :
							$rang = getWeek($today);
							break;
						case "month" :
							$rang = getMonth();
							break;
						case "year" :
							$rang = getYear();
							break ;
						default :
							$rang = getMonth();
							break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = "AND (date_upd BETWEEN '$from' AND '$to') ";
				}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')."00:00:00"; $to = date('Y-m-d')."23:59:59"; }
					$view = "AND (date_upd BETWEEN '$from' AND '$to') "; 
				}
			}
		
	/////////////////////////////////////////////////////////////////////
	if($view_rank ==0){ $rank = " ทั้งหมด"; }else{ $rank = thaiDate($from)." &nbsp;ถึง &nbsp;".thaiDate($to); }
	$report_title = "รายงานยอดขาย &nbsp;  วันที่ &nbsp;".$rank." : ".COMPANY;
	$html = "	<table class='table table-striped table-hover'>";
	$header = "
		<tr>
			<td style='align:center; width:10%;'>วันที่</td>
			<td style='align:center; width:10%;'>เอกสาร</td>
			<td style='align:center; width:25%;'>ลูกค้า</td>
			<td style='text-align:right; width:10%;'>จำนวนเงิน</td>
			<td style='text-align: right; width:10%;'>ส่วนลด(สินค้า)</td>
			<td style='text-align: right; width:10%;'>ส่วนลด(ท้ายบิล)</td>
			<td style='text-align: right; width:10%;'>สุทธิ</td>
			<td style='text-align:center; width:15%;'>พนักงาน</td>
		</tr>";
		$sql = dbQuery("SELECT id_sale, id_employee FROM tbl_sale WHERE $sale");
		$rs = dbNumRows($sql);
		if($rs>0){
			$r = 0;
			while($r<$rs){
				list($id_sale, $id_employee) = dbFetchArray($sql);
				$employee = new employee($id_employee);
				$sale_name = $employee->full_name;
				$sale_first_name = $employee->first_name;
				$html .= "<tr><td colspan='10' align='center'><h4>$report_title &nbsp;&nbsp; พนักงาน : $sale_name </h4></td></tr>";
				$html .= $header;
				$qr = dbQuery("SELECT  id_order, reference, id_customer, SUM(discount_amount), SUM(total_amount), date_upd   FROM tbl_order_detail_sold WHERE id_sale = $id_sale  $view AND id_role IN(1,5) GROUP BY id_order ORDER BY date_upd ASC");
				$row = dbNumRows($qr); 
				$i = 0;
				$move_price = 0; $move_amount = 0; $discount =0; $total_bill_discount =0;
					while($i<$row){
						list($id_order, $reference, $id_customer, $discount_amount, $total_amount, $date_upd) = dbFetchArray($qr);
						$bill_discount = bill_discount($id_order);
						$total_amount -= $bill_discount;
						$total_price = $total_amount + $discount_amount + $bill_discount;
						$customer = new customer($id_customer);
						$customer_name = $customer->full_name;
						$date = thaiDate($date_upd);
						$html .=" 
						<tr style='font-size:12px;'>
						<td>$date</td>
						<td>$reference</td>
						<td>$customer_name</td>
						<td align='right'>".number_format($total_price,2)."</td>
						<td align='right'>".ac_format(number_format($discount_amount,2))."</td>
						<td align='right'>".ac_format(number_format($bill_discount,2))."</td>
						<td align='right'>".ac_format(number_format($total_amount,2))."</td>
						<td align='center'>$sale_first_name</td>
						</tr>";
						$discount += $discount_amount;
						$move_price += $total_price;
						$move_amount += $total_amount;
						$total_bill_discount += $bill_discount;
							$i++;
					}
				$html .= "<tr><td colspan='3' align='right'><b>รวม &nbsp; </b></td><td align='right'><b>".number_format($move_price,2)."</b></td>
							<td align='right'><b>".number_format($discount,2)."</b></td><td align='right'><b>".number_format($total_bill_discount,2)."</b></td>
							<td align='right'><b>".number_format($move_amount,2)."</b></td><td> &nbsp;</td></tr>";
				$html .="<tr><td colspan='10' align='center'><h4> &nbsp;</h4></td></tr>";
				$r++;	
			}
		}
			$html .="</table>";
		echo $html;
}
//***************************************** รายยอดขาย แยกตามพนักงานขายและเอกสาร Export to excel  *************************************//
if(isset($_GET['export_sale_amount_document'])&&isset($_GET['view'])&&isset($_GET['sale'])){
	$sale_rank = $_GET['sale'];
	$view_rank = $_GET['view'];	
	$today = date('Y-m-d');
	$from = "";
	$to = "";
	if(isset($_GET['sale_from'])&&isset($_GET['sale_to'])){ // *** เรียงลำดับ id_sale จากน้อยไปมาก
		$p_from  = $_GET['sale_from'];
		$p_to = $_GET['sale_to'];
			if($p_to < $p_from){
				$sale_from = $_GET['sale_to'];
				$sale_to = $_GET['sale_from'];
			}else{
				$sale_from = $_GET['sale_from'];
				$sale_to = $_GET['sale_to'];
			}
	}else{ 
		$sale_from =""; $sale_to = "";
	}
	if(isset($_GET['sale_selected'])){ $sale_selected = $_GET['sale_selected'];}else{ $sale_selected="";}
	if(isset($_GET['view_selected'])){ $view_selected = $_GET['view_selected'];}else{ $view_selected = "";}
	if($sale_rank==0){  //// sale
		$sale ="id_sale !='-1'";
		if($view_rank == 0){
				$view = "";
				}else if($view_rank==1){
					switch($view_selected){
						case "week" :
							$rang = getWeek($today);
							break;
						case "month" :
							$rang = getMonth();
							break;
						case "year" :
							$rang = getYear();
							break ;
						default :
							$rang = getMonth();
							break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = " AND (date_upd BETWEEN '$from' AND '$to') ";   
				}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')." 00:00:00"; $to = date('Y-m-d')." 23:59:59"; }
					$view = "AND (date_upd BETWEEN '$from' AND '$to') "; 
				}
		}else if($sale_rank==1){ 
			$sale ="(id_sale BETWEEN '$sale_from' AND '$sale_to' )";
			if($view_rank == 0){
				$view = "";
				}else if($view_rank==1){
					switch($view_selected){
						case "week" :
							$rang = getWeek($today);
							break;
						case "month" :
							$rang = getMonth();
							break;
						case "year" :
							$rang = getYear();
							break ;
						default :
							$rang = getMonth();
							break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = "AND (date_upd BETWEEN '$from' AND '$to') ";
				}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')." 00:00:00"; $to = date('Y-m-d')." 23:59:59"; }
					$view = "AND (date_upd BETWEEN '$from' AND '$to') "; 
				}
			}else if($sale_rank ==2){
				$sale ="id_sale = '$sale_selected'";
				if($view_rank == 0){
				$view = "";
				}else if($view_rank==1){
					switch($view_selected){
						case "week" :
							$rang = getWeek($today);
							break;
						case "month" :
							$rang = getMonth();
							break;
						case "year" :
							$rang = getYear();
							break ;
						default :
							$rang = getMonth();
							break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = "AND (date_upd BETWEEN '$from' AND '$to') ";
				}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')."00:00:00"; $to = date('Y-m-d')."23:59:59"; }
					$view = "AND (date_upd BETWEEN '$from' AND '$to') "; 
				}
			}
		
	/////////////////////////////////////////////////////////////////////
	if($view_rank ==0){ $rank = " ทั้งหมด"; }else{ $rank = thaiDate($from)." "." "." ถึง "." "." ".thaiDate($to); }
	$report_title = "รายงานยอดขาย "." "." "."  วันที่ "." "." ".$rank." : ".COMPANY;
	$title = array(1=>array($report_title));
	$sub_header = array("วันที่", "เอกสาร","ลูกค้า", "จำนวนเงิน","ส่วนลด(สินค้า)","ส่วนลด(ท้ายบิล)","สุทธิ","พนักงาน");
	$body = array();
		$sql = dbQuery("SELECT id_sale, id_employee FROM tbl_sale WHERE $sale");
		$rs = dbNumRows($sql);
		if($rs>0){
			$r = 0;
			while($r<$rs){
				list($id_sale, $id_employee) = dbFetchArray($sql);
				$employee = new employee($id_employee);
				$sale_name = $employee->full_name;
				$sale_first_name = $employee->first_name;
				$arr = array("พนักงาน : $sale_name ");
				array_push($body, $arr);
				array_push($body, $sub_header);
				$qr = dbQuery("SELECT  id_order, reference, id_customer, SUM(discount_amount), SUM(total_amount), date_upd   FROM tbl_order_detail_sold WHERE id_sale = $id_sale  $view AND id_role IN(1,5) GROUP BY id_order ORDER BY date_upd ASC");
				$row = dbNumRows($qr); 
				$i = 0;
				$move_price = 0; $move_amount = 0; $discount =0; $total_bill_discount = 0;
					while($i<$row){
						list($id_order, $reference, $id_customer, $discount_amount, $total_amount, $date_upd) = dbFetchArray($qr);
						$bill_discount = bill_discount($id_order);
						$total_amount -= $bill_discount;
						$total_price = $total_amount + $discount_amount + $bill_discount;
						$customer = new customer($id_customer);
						$customer_name = $customer->full_name;
						$date = thaiDate($date_upd);
						$arr = array($date, $reference, $customer_name, $total_price, $discount_amount, $bill_discount, $total_amount, $sale_first_name);
						array_push($body, $arr);
						$discount += $discount_amount;
						$move_price += $total_price;
						$move_amount += $total_amount;
						$total_bill_discount += $bill_discount;
							$i++;
					}
					$arr = array("","","รวม",$move_price, $discount, $total_bill_discount, $move_amount);
					array_push($body, $arr);
					$arr = array("----------------------------------------------------------------------------------------------------------------------");
					array_push($body, $arr);
					$arr = array("");
					array_push($body, $arr);
				$r++;	
			}
		}
		$sheet_name = "Sale_amount_document";
		$xls = new Excel_XML('UTF-8', false, $sheet_name); 
		$xls->addArray($title);
		$xls->addArray ($body); 
		$xls->generateXML("Sale_amount_document");		
		
}

//***************************************************  รายงานเอกสาร แยกตามลูกค้า  ***********************************************//
if(isset($_GET['document_by_customer'])&&isset($_GET['view'])&&isset($_GET['customer'])){
	$customer_rank = $_GET['customer'];
	$view_rank = $_GET['view'];	
	$today = date('Y-m-d');
	$from = "";
	$to = "";
	if(isset($_GET['customer_from'])&&isset($_GET['customer_to'])){ // *** เรียงลำดับ id_customer จากน้อยไปมาก
		$p_from  = trim($_GET['customer_from']);
		$p_to = trim($_GET['customer_to']);
			if($p_to < $p_from){
				$customer_from = $p_to;
				$customer_to = $p_from;
			}else{
				$customer_from = $p_from;
				$customer_to = $p_to;
			}
	}else{ 
		$customer_from =""; $customer_to = "";
	}
	if(isset($_GET['customer_selected'])){ $customer_selected = trim($_GET['customer_selected']);}else{ $customer_selected="";}
	if(isset($_GET['view_selected'])){ $view_selected = $_GET['view_selected'];}else{ $view_selected = "";}
	if($customer_rank==0){  //// customer
		$customer ="customer_code !='-1'";
		if($view_rank == 0){
				$view = "";
				}else if($view_rank==1){
					switch($view_selected){
						case "week" :
							$rang = getWeek($today);
							break;
						case "month" :
							$rang = getMonth();
							break;
						case "year" :
							$rang = getYear();
							break ;
						default :
							$rang = getMonth();
							break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = " AND (tbl_order_detail_sold.date_upd BETWEEN '$from' AND '$to') ";   
				}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')."00:00:00"; $to = date('Y-m-d')."23:59:59"; }
					$view = "AND (tbl_order_detail_sold.date_upd BETWEEN '$from' AND '$to') "; 
				}
		}else if($customer_rank==1){ 
			$customer ="(customer_code BETWEEN '$customer_from' AND '$customer_to' )";
			if($view_rank == 0){
				$view = "";
				}else if($view_rank==1){
					switch($view_selected){
						case "week" :
							$rang = getWeek($today);
							break;
						case "month" :
							$rang = getMonth();
							break;
						case "year" :
							$rang = getYear();
							break ;
						default :
							$rang = getMonth();
							break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = "AND (tbl_order_detail_sold.date_upd BETWEEN '$from' AND '$to') ";
				}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')."00:00:00"; $to = date('Y-m-d')."23:59:59"; }
					$view = "AND (tbl_order_detail_sold.date_upd BETWEEN '$from' AND '$to') "; 
				}
			}else if($customer_rank ==2){
				$customer ="customer_code = '$customer_selected'";
				if($view_rank == 0){
				$view = "";
				}else if($view_rank==1){
					switch($view_selected){
						case "week" :
							$rang = getWeek($today);
							break;
						case "month" :
							$rang = getMonth();
							break;
						case "year" :
							$rang = getYear();
							break ;
						default :
							$rang = getMonth();
							break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = "AND (tbl_order_detail_sold.date_upd BETWEEN '$from' AND '$to') ";
				}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')."00:00:00"; $to = date('Y-m-d')."23:59:59"; }
					$view = "AND (tbl_order_detail_sold.date_upd BETWEEN '$from' AND '$to') "; 
				}
			}
	/////////////////////////////////////////////////////////////////////
	if($view_rank ==0){ $rank = " ทั้งหมด"; }else{ $rank = thaiDate($from)." ถึง ".thaiDate($to); }
	$report_title = "รายงานเอกสาร แยกตามลูกค้า วันที่ ".$rank." : ".COMPANY;
	$html = " 	<h4 align='center'>$report_title</h4> <hr style='border-color:#CCC; margin-top: 0px; margin-bottom:0px;' />
	<table class='table table-striped table-hover'>";
	$header_row = "<tr><td width='10%'>วันที่</td><td width='10%'>เอกสาร</td><td width='25%'>ลูกค้า</td><td width='15%' align='right'>จำนวนเงิน</td>
								<td width='15%' align='right'>ส่วนลด</td><td width='15%' align='right'>สุทธิ</td><td width='10%' align='center'>พนักงาน</td></tr>"; 
		$qr = dbQuery("SELECT tbl_order_detail_sold.id_customer FROM tbl_order_detail_sold LEFT JOIN tbl_customer ON tbl_order_detail_sold.id_customer = tbl_customer.id_customer WHERE $customer  $view AND id_role IN(1,5) GROUP BY tbl_order_detail_sold.id_customer");
		//echo "SELECT id_customer FROM tbl_order_detail_sold WHERE $customer  $view AND id_role IN(1,5) GROUP BY id_customer";
		$row = dbNumRows($qr); 
		$i = 0;
		$n = 1;
		if($row>0){
		while($i<$row){
			$total_price = 0;
			$total_discount = 0;
			$total_movement = 0;
			list($id_customer) = dbFetchArray($qr);
			list($first_name, $last_name) = dbFetchArray(dbQuery("SELECT first_name, last_name FROM tbl_customer WHERE id_customer = $id_customer"));
			$customer_name = $first_name." ".$last_name;	
			$sql = dbQuery("SELECT reference, id_employee, SUM(discount_amount), SUM(total_amount), date_upd FROM tbl_order_detail_sold WHERE id_customer = $id_customer $view AND id_role IN(1,5) GROUP BY reference");
				$rows = dbNumRows($sql);
				$v = 0;
				$html .="<tr><td colspan='7' style='vertical-align:middle;'><h4>$customer_name</h4></td></tr>";
				$html .= $header_row;
				while($v<$rows){
				list($reference, $id_employee, $discount_amount, $total_amount, $date_upd) = dbFetchArray($sql);	
				$employee = new employee($id_employee);
				$employee_name = $employee->first_name;
				$amount = $total_amount + $discount_amount;
				$html .=" <tr style='font-size:12px;'><td>".thaiDate($date_upd)."</td><td>$reference</td><td >$customer_name</td><td align='right'>".number_format($amount,2)."</td>
							<td align='right'>".number_format($discount_amount,2)."</td><td align='right'>".number_format($total_amount,2)."</td><td align='center'>$employee_name</td><tr>";
				$total_movement += $total_amount;
				$total_discount += $discount_amount;
				$total_price += $amount;
				$v++;
				}
				$html .="<tr><td colspan='3' style='text-align: right;'><h4>รวม</h4></td>
				<td style='text-align: right; padding-right: 5px;'><h4>".number_format($total_price,2)."</h4></td><td style='text-align: right; padding-right: 5px;'><h4>".number_format($total_discount,2)."</h4></td>
				<td style='text-align: right; padding-right: 5px;'><h4>".number_format($total_movement,2)."</h4></td><td></td></tr>
				<tr><td colspan='7'><h4>&nbsp;</h4></td></tr>";
				$i++;
		}
		}else{
			$html .="<tr><td colspan='7'><h4 align='center'>ไม่มีรายการตามเงื่อนไขที่เลือก</h4></td></tr>";
		}
		$html ."</table>";
		echo $html;
}
//***************************************************  รายงานเอกสาร แยกตามลูกค้า Export to excel  ***********************************************//
if(isset($_GET['export_document_by_customer'])&&isset($_GET['view'])&&isset($_GET['customer'])){
	$customer_rank = $_GET['customer'];
	$view_rank = $_GET['view'];	
	$today = date('Y-m-d');
	$from = "";
	$to = "";
	if(isset($_GET['customer_from'])&&isset($_GET['customer_to'])){ // *** เรียงลำดับ id_customer จากน้อยไปมาก
		$p_from  = trim($_GET['customer_from']);
		$p_to = trim($_GET['customer_to']);
			if($p_to < $p_from){
				$customer_from = $p_to;
				$customer_to = $p_from;
			}else{
				$customer_from = $p_from;
				$customer_to = $p_to;
			}
	}else{ 
		$customer_from =""; $customer_to = "";
	}
	if(isset($_GET['customer_selected'])){ $customer_selected = trim($_GET['customer_selected']);}else{ $customer_selected="";}
	if(isset($_GET['view_selected'])){ $view_selected = $_GET['view_selected'];}else{ $view_selected = "";}
	if($customer_rank==0){  //// customer
		$customer ="customer_code !='-1'";
		if($view_rank == 0){
				$view = "";
				}else if($view_rank==1){
					switch($view_selected){
						case "week" :
							$rang = getWeek($today);
							break;
						case "month" :
							$rang = getMonth();
							break;
						case "year" :
							$rang = getYear();
							break ;
						default :
							$rang = getMonth();
							break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = " AND (tbl_order_detail_sold.date_upd BETWEEN '$from' AND '$to') ";   
				}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')."00:00:00"; $to = date('Y-m-d')."23:59:59"; }
					$view = "AND (tbl_order_detail_sold.date_upd BETWEEN '$from' AND '$to') "; 
				}
		}else if($customer_rank==1){ 
			$customer ="(customer_code BETWEEN '$customer_from' AND '$customer_to' )";
			if($view_rank == 0){
				$view = "";
				}else if($view_rank==1){
					switch($view_selected){
						case "week" :
							$rang = getWeek($today);
							break;
						case "month" :
							$rang = getMonth();
							break;
						case "year" :
							$rang = getYear();
							break ;
						default :
							$rang = getMonth();
							break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = "AND (tbl_order_detail_sold.date_upd BETWEEN '$from' AND '$to') ";
				}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')."00:00:00"; $to = date('Y-m-d')."23:59:59"; }
					$view = "AND (tbl_order_detail_sold.date_upd BETWEEN '$from' AND '$to') "; 
				}
			}else if($customer_rank ==2){
				$customer ="customer_code = '$customer_selected'";
				if($view_rank == 0){
				$view = "";
				}else if($view_rank==1){
					switch($view_selected){
						case "week" :
							$rang = getWeek($today);
							break;
						case "month" :
							$rang = getMonth();
							break;
						case "year" :
							$rang = getYear();
							break ;
						default :
							$rang = getMonth();
							break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = "AND (tbl_order_detail_sold.date_upd BETWEEN '$from' AND '$to') ";
				}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')."00:00:00"; $to = date('Y-m-d')."23:59:59"; }
					$view = "AND (tbl_order_detail_sold.date_upd BETWEEN '$from' AND '$to') "; 
				}
			}
	/////////////////////////////////////////////////////////////////////
	if($view_rank ==0){ $rank = " ทั้งหมด"; }else{ $rank = thaiDate($from)." ถึง ".thaiDate($to); }
	$report_title = "รายงานเอกสาร แยกตามลูกค้า วันที่ ".$rank." : ".COMPANY;
	$title = array(1=>array($report_title));
	$sub_header = array("วันที่","เอกสาร","ลูกค้า","จำนวนเงิน","ส่วนลด","สุทธิ","พนักงาน");
	$line = array(1=>array("======================================================================================="));
	$body = array();
	$qr = dbQuery("SELECT tbl_order_detail_sold.id_customer FROM tbl_order_detail_sold LEFT JOIN tbl_customer ON tbl_order_detail_sold.id_customer = tbl_customer.id_customer WHERE $customer  $view AND id_role IN(1,5) GROUP BY tbl_order_detail_sold.id_customer");
		$row = dbNumRows($qr); 
		$i = 0;
		$n = 1;
		if($row>0){
		while($i<$row){
			$total_price = 0;
			$total_discount = 0;
			$total_movement = 0;
			list($id_customer) = dbFetchArray($qr);
			list($first_name, $last_name) = dbFetchArray(dbQuery("SELECT first_name, last_name FROM tbl_customer WHERE id_customer = $id_customer"));
			$customer_name = $first_name." ".$last_name;	
			$sql = dbQuery("SELECT reference, id_employee, SUM(discount_amount), SUM(total_amount), date_upd FROM tbl_order_detail_sold WHERE id_customer = $id_customer $view AND id_role IN(1,5) GROUP BY reference");
				$rows = dbNumRows($sql);
				$v = 0;
				$arr = array("$customer_name");
				array_push($body, $arr);
				array_push($body, $sub_header);
				while($v<$rows){
				list($reference, $id_employee, $discount_amount, $total_amount, $date_upd) = dbFetchArray($sql);	
				$employee = new employee($id_employee);
				$employee_name = $employee->first_name;
				$amount = $total_amount + $discount_amount;
				$arr = array(thaiDate($date_upd), $reference, $customer_name, number_format($amount,2), number_format($discount_amount,2), number_format($total_amount,2), $employee_name);
				array_push($body, $arr);
				$total_movement += $total_amount;
				$total_discount += $discount_amount;
				$total_price += $amount;
				$v++;
				}
				$arr = array("", "", "รวม", number_format($total_price,2), number_format($total_discount,2), number_format($total_movement,2));
				array_push($body, $arr);
				$arr = array("-------------------------------------------------------------------------------------------------------------------------------------------------");
				array_push($body, $arr);
				$i++;
		}
		}else{
			$arr = array("================================== ไม่มีรายการตามเงื่อนไขที่เลือก=====================================");
		}
		$sheet_name = "Document_by_customer";
		$xls = new Excel_XML('UTF-8', false, $sheet_name); 
		$xls->addArray($title);
		$xls->addArray($line);
		$xls->addArray ($body); 
		$xls->generateXML("Document_by_customer");
}
//***************************************** รายสินค้าค้างส่ง  *************************************//
if(isset($_GET['stock_back_log'])&&isset($_GET['view'])&&isset($_GET['customer'])&&isset($_GET['product'])){
	$customer_rank = $_GET['customer'];
	$product_rank = $_GET['product'];
	$view_rank = $_GET['view'];	
	$today = date('Y-m-d');
	$from = "";
	$to = "";
	if(isset($_GET['product_from'])&&isset($_GET['product_to'])){ // *** เรียงลำดับ id_product จากน้อยไปมาก
		$p_from  = $_GET['product_from'];
		$p_to = $_GET['product_to'];
			if($p_to < $p_from){
				$product_from = $_GET['product_to'];
				$product_to = $_GET['product_from'];
			}else{
				$product_from = $_GET['product_from'];
				$product_to = $_GET['product_to'];
			}
	}else{ 
		$product_from =""; $product_to = "";
	}
	if(isset($_GET['product_selected'])){ $product_selected = $_GET['product_selected'];}else{ $product_selected="";}
	if($product_rank==0){  //// product
		$product ="product_reference !=''";
		}else if($product_rank==1){ 
			$product ="(product_reference BETWEEN '$product_from' AND '$product_to' )";
		}else if($product_rank ==2){
			$product ="product_reference = '$product_selected'";
		}
	if(isset($_GET['customer_from'])&&isset($_GET['customer_to'])){ // *** เรียงลำดับ id_customer จากน้อยไปมาก
		$p_from  = trim($_GET['customer_from']);
		$p_to = trim($_GET['customer_to']);
		list($p_from) = dbFetchArray(dbQuery("SELECT id_customer FROM tbl_customer WHERE customer_code ='$p_from'"));
		list($p_to) = dbFetchArray(dbQuery("SELECT id_customer FROM tbl_customer WHERE customer_code ='$p_to'"));
			if($p_to < $p_from){
				$customer_from = $p_to;
				$customer_to = $p_from;
			}else{
				$customer_from = $p_from;
				$customer_to = $p_to;
			}
	}else{ 
		$customer_from =""; $customer_to = "";
	}
	if(isset($_GET['customer_selected'])){ $customer_selected = trim($_GET['customer_selected']);}else{ $customer_selected="";}
	if($customer_rank==0){  //// customer
		$customer ="id_customer !='-1'";
		}else if($customer_rank==1){ 
			$customer ="(id_customer BETWEEN '$customer_from' AND '$customer_to' )";
		}else if($customer_rank ==2){
				$customer ="id_customer = '$customer_selected'";	
		}
	if(isset($_GET['view_selected'])){ $view_selected = $_GET['view_selected'];}else{ $view_selected = "";}
	 if($view_rank == 0){
		 $view = "";
		}else if($view_rank==1){
				switch($view_selected){
					case "week" :
						$rang = getWeek($today);
						break;
					case "month" :
						$rang = getMonth();
						break;
					case "year" :
						$rang = getYear();
						break ;
					default :
						$rang = getMonth();
						break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = "AND (date_add BETWEEN '$from' AND '$to') ";
		}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')."00:00:00"; $to = date('Y-m-d')."23:59:59"; }
					$view = "AND (date_add BETWEEN '$from' AND '$to') "; 
		}
	/////////////////////////////////////////////////////////////////////
	if($view_rank ==0){ $rank = " ทั้งหมด"; }else{ $rank = thaiDate($from)." ถึง ".thaiDate($to); }
	$report_title = "รายงานสินค้าค้างส่ง วันที่ ".$rank." : ".COMPANY;
	$html = " <div class='row'><div class='col-lg-6 col-lg-offset-3'><h4 align='center'>$report_title</h4></div><input type='hidden' name='i' id='i' value='1' />
	<div class='col-lg-3'><button type='button' class='btn btn-default pull-right' id='witch' onclick='switch_graph()'><i class='fa fa-refresh'></i>สลับ</button></div></div>
	<hr style='border-color:#CCC; margin-top: 0px; margin-bottom:0px;' />
	<div class='row' id='order_row'><div class='col-lg-12'>
	<table class='table table-striped'>
	<thead>
		<th style='width:5%; text-align:center;'>ลำดับ</th><th style='width:15%;'>เลขที่</th><th style='widht:25%;;'>ลูกค้า</th><th style='widht:25%;'>พนักงาน</th>
		<th style='widht:15%; text-align: right;'>ยอดเงิน</th><th style='widht:10%; text-align:center;'>สถานะ</th><th style='widht:10%; text-align:center;'>วันที่สั่ง</th>
	</thead>";
	//***แสดงเป็นรายการorder
		$sql = dbQuery("SELECT tbl_order.id_order, reference, id_customer, payment,current_state, date_add,id_employee FROM tbl_order_detail LEFT JOIN tbl_order ON tbl_order_detail.id_order = tbl_order.id_order WHERE $product AND $customer $view AND current_state IN (1,2,3,4,5,10,11) GROUP BY reference");
				$i = 0;
				$row = dbNumRows($sql);
			if($row>0){
				while($rs = dbFetchArray($sql)){
					$id_order = $rs['id_order'];
					$reference = $rs['reference'];
					$current_state = $rs['current_state'];
					$id_employee = $rs['id_employee'];
					$id_customer = $rs['id_customer'];
					list($cus_first_name,$cus_last_name) = dbFetchArray(dbQuery("SELECT first_name,last_name FROM tbl_customer WHERE id_customer = '$id_customer'"));
					list($amount) = dbFetchArray(dbQuery("SELECT SUM(total_amount) FROM tbl_order_detail WHERE id_order = '$id_order'"));
					list($status) = dbFetchArray(dbQuery("SELECT state_name FROM tbl_order_state WHERE id_order_state = '$current_state'"));
					list($employee_name) = dbFetchArray(dbQuery("SELECT first_name FROM tbl_employee WHERE id_employee = '$id_employee'"));
					$full_name_cus = "$cus_first_name $cus_last_name";
					$payment = $rs['payment'];
					$date_add = $rs['date_add'];
			$html .="<tr><td align='center'>".($i+1)."</td><td style='padding:10px;'>$reference</td><td style='padding:10px;'>$full_name_cus</td>
			<td>$employee_name</td><td align='right'>".number_format($amount,2)."</td><td align='center'>$status</td><td align='center'>$date_add</td></tr>";
			$i++;
				}
		$html .= "</table></div></div>";
			}else{
			$html .="<tr><td colspan='7'><h4 align='center'>ไม่มีรายการตามเงื่อนไขที่เลือก</h4></td></tr>";
			$html .= "</table></div></div>";
		}
		$html .="
		<div class='row' id='product_row' style='display:none;'><div class='col-lg-12'>
		<table class='table table-striped'>
		<thead>
			<th style='width:10%; text-align:center;'>ลำดับ</th><th style='width:50%;'>ชื่อสินค้า</th><th style='width:25%;'>ออร์เดอร์</th><th style='widht:20%;  text-align: right;'>จำนวน</th>
		</thead>";
		//******แสดงเป็นรายการสินค้า
		$sql = dbQuery("SELECT product_name,product_qty,product_reference,reference FROM tbl_order_detail LEFT JOIN tbl_order ON tbl_order_detail.id_order = tbl_order.id_order WHERE  $product AND $customer $view AND current_state IN (1,2,3,4,5,10,11) ORDER BY product_reference ASC");
				$i = 0;
				$row = dbNumRows($sql);
			if($row>0){
				while($rs = dbFetchArray($sql)){
					$product_name = $rs['product_reference']." ".$rs['product_name'];
					$product_qty = $rs['product_qty'];
			$html .="<tr><td align='center'>"; $html .= $i+1; $html .="</td><td style='padding:10px;'>$product_name</td><td style='padding:10px;'>".$rs['reference']."</td><td style='text-align: right; padding:10px;'>$product_qty</td>
			</tr>";
			$i++;
				}
		$html .= "</table></div></div>";
		}else{
			$html .="<tr><td colspan='7'><h4 align='center'>ไม่มีรายการตามเงื่อนไขที่เลือก</h4></td></tr>";
			$html .= "</table></div></div>";
		}
		echo $html;
}
//***************************************** รายสินค้าค้างส่ง  Export to excel *************************************//
if(isset($_GET['export_stock_back_log'])&&isset($_GET['view'])&&isset($_GET['customer'])&&isset($_GET['product'])){
	$customer_rank = $_GET['customer'];
	$product_rank = $_GET['product'];
	$view_rank = $_GET['view'];	
	$today = date('Y-m-d');
	$from = "";
	$to = "";
	if(isset($_GET['product_from'])&&isset($_GET['product_to'])){ // *** เรียงลำดับ id_product จากน้อยไปมาก
		$p_from  = $_GET['product_from'];
		$p_to = $_GET['product_to'];
			if($p_to < $p_from){
				$product_from = $_GET['product_to'];
				$product_to = $_GET['product_from'];
			}else{
				$product_from = $_GET['product_from'];
				$product_to = $_GET['product_to'];
			}
	}else{ 
		$product_from =""; $product_to = "";
	}
	if(isset($_GET['product_selected'])){ $product_selected = $_GET['product_selected'];}else{ $product_selected="";}
	if($product_rank==0){  //// product
		$product ="product_reference !=''";
		}else if($product_rank==1){ 
			$product ="(product_reference BETWEEN '$product_from' AND '$product_to' )";
		}else if($product_rank ==2){
			$product ="product_reference = '$product_selected'";
		}
	if(isset($_GET['customer_from'])&&isset($_GET['customer_to'])){ // *** เรียงลำดับ id_customer จากน้อยไปมาก
		$p_from  = trim($_GET['customer_from']);
		$p_to = trim($_GET['customer_to']);
		list($p_from) = dbFetchArray(dbQuery("SELECT id_customer FROM tbl_customer WHERE customer_code ='$p_from'"));
		list($p_to) = dbFetchArray(dbQuery("SELECT id_customer FROM tbl_customer WHERE customer_code ='$p_to'"));
			if($p_to < $p_from){
				$customer_from = $p_to;
				$customer_to = $p_from;
			}else{
				$customer_from = $p_from;
				$customer_to = $p_to;
			}
	}else{ 
		$customer_from =""; $customer_to = "";
	}
	if(isset($_GET['customer_selected'])){ $customer_selected = trim($_GET['customer_selected']);}else{ $customer_selected="";}
	if($customer_rank==0){  //// customer
		$customer ="id_customer !='-1'";
		}else if($customer_rank==1){ 
			$customer ="(id_customer BETWEEN '$customer_from' AND '$customer_to' )";
		}else if($customer_rank ==2){
				$customer ="id_customer = '$customer_selected'";	
		}
	if(isset($_GET['view_selected'])){ $view_selected = $_GET['view_selected'];}else{ $view_selected = "";}
	 if($view_rank == 0){
		 $view = "";
		}else if($view_rank==1){
				switch($view_selected){
					case "week" :
						$rang = getWeek($today);
						break;
					case "month" :
						$rang = getMonth();
						break;
					case "year" :
						$rang = getYear();
						break ;
					default :
						$rang = getMonth();
						break;
					}
					$from = $rang['from']." 00:00:00";
					$to = $rang['to']." 23:59:59";
					$view = "AND (date_add BETWEEN '$from' AND '$to') ";
		}else if($view_rank ==2){
					$from = dbDate($_GET['from_date'])." 00:00:00";
					$to = dbDate($_GET['to_date'])." 23:59:59";
					if($from =="1970-01-01" || $to =="1970-01-01"){ $from = date('Y-m-d')."00:00:00"; $to = date('Y-m-d')."23:59:59"; }
					$view = "AND (date_add BETWEEN '$from' AND '$to') "; 
		}
	/////////////////////////////////////////////////////////////////////
	if($view_rank ==0){ $rank = " ทั้งหมด"; }else{ $rank = thaiDate($from)." ถึง ".thaiDate($to); }
	$report_title = "รายงานสินค้าค้างส่ง วันที่ ".$rank." : ".COMPANY;
	$title = array(1=>array($report_title));
	$sub_header = array("เอกสาร","ลูกค้า","จำนวนเงิน","สถานะ","วันที่สั่ง","พนักงาน");
	$sub_header2 = array("เอกสาร","บาร์โค้ด","สินค้า","จำนวน","จำนวนเงิน","ส่วนลด","สุทธิ");
	$line = array(1=>array("======================================================================================="));
	$body = array();
	//***แสดงเป็นรายการorder
		$sql = dbQuery("SELECT tbl_order.id_order, reference, id_customer, payment,current_state, date_add,id_employee FROM tbl_order_detail LEFT JOIN tbl_order ON tbl_order_detail.id_order = tbl_order.id_order WHERE $product AND $customer $view AND current_state IN (1,2,3,4,5,10,11) GROUP BY reference");
		//echo "SELECT tbl_order.id_order, reference, id_customer, payment,current_state, date_add,id_employee FROM tbl_order_detail LEFT JOIN tbl_order ON tbl_order_detail.id_order = tbl_order.id_order WHERE $product AND $customer $view AND current_state IN (1,2,3,4,5,10,11) GROUP BY reference";
				$i = 0;
				$row = dbNumRows($sql);
			if($row>0){
				while($rs = dbFetchArray($sql)){
					$id_order = $rs['id_order'];
					$reference = $rs['reference'];
					$current_state = $rs['current_state'];
					$id_employee = $rs['id_employee'];
					$id_customer = $rs['id_customer'];
					list($cus_first_name,$cus_last_name) = dbFetchArray(dbQuery("SELECT first_name,last_name FROM tbl_customer WHERE id_customer = '$id_customer'"));
					list($amount) = dbFetchArray(dbQuery("SELECT SUM(total_amount) FROM tbl_order_detail WHERE id_order = '$id_order'"));
					list($status) = dbFetchArray(dbQuery("SELECT state_name FROM tbl_order_state WHERE id_order_state = '$current_state'"));
					list($employee_name) = dbFetchArray(dbQuery("SELECT first_name FROM tbl_employee WHERE id_employee = '$id_employee'"));
					$full_name_cus = "$cus_first_name $cus_last_name";
					$payment = $rs['payment'];
					$date_add = $rs['date_add'];
					array_push($body, $sub_header);
					$arr = array($reference, $full_name_cus, number_format($amount,2), $status, $date_add, $employee_name);
					array_push($body, $arr);
					$arr = array("---------------------------------------------------------------------------------------------------------------------------------------------");
					array_push($body, $arr);
					$qr = dbQuery("SELECT product_name, product_qty, product_reference, barcode, discount_amount, total_amount FROM tbl_order_detail WHERE $product AND id_order = $id_order");
					//echo "SELECT product_name, product_qty, product_reference, barcode, discount_amount, total_amount FROM tbl_order_detail WHERE  id_order = $id_order";
					$rows = dbNumRows($qr);
					$m = 0;
					array_push($body, $sub_header2);
					$total_qty = 0;
					$total_movement = 0;
					$total_discount = 0;
					while($m<$rows){
						list($product_name, $product_qty, $product_reference, $barcode, $discount_amount, $total_amount) = dbFetchArray($qr);
						$arr = array($reference, $barcode, $product_reference." : ".$product_name, number_format($product_qty), number_format($discount_amount,2), number_format($total_amount,2));
						array_push($body, $arr);
						$total_qty += $product_qty;
						$total_discount += $discount_amount;
						$total_movement += $total_amount;
						$m++;
					}
					$arr = array("","","รวม", $total_qty, $total_discount, $total_movement);
					array_push($body, $arr);
					$arr = array("---------------------------------------------------------------------------------------------------------------------------------------------");
					array_push($body, $arr);
				}
			}else{
			$arr = array("======================== ไม่มีรายการตามเงื่อนไขที่เลือก ===============================");
			array_push($body, $arr);
		}
		$sheet_name = "Order_back_log";
		$xls = new Excel_XML('UTF-8', false, $sheet_name); 
		$xls->addArray($title);
		$xls->addArray($line);
		$xls->addArray ($body); 
		$xls->generateXML("Order_back_log");
}
?>