<?php
require "../../library/config.php";
require "../../library/functions.php";
require "../function/tools.php";
include "../function/product_helper.php";
include "../function/transfer_helper.php";

if( isset( $_GET['deleteTranfer'] ) )
{
	$sc = 'success';
	$id_tranfer = $_POST['id_tranfer'];
	$cs 			= new transfer();
	$hasDetail	= $cs->hasDetail($id_tranfer);
	if( $hasDetail === TRUE )
	{
		$sc = 'ไม่สามารถลบได้เนื่องจากเอกสารไม่ว่างเปล่า';
		
	}
	else
	{
		if( $cs->delete($id_tranfer) === FALSE )
		{
			$sc = 'ลบรายการไม่สำเร็จ';
		}
	}
	
	echo $sc;
}

if( isset( $_GET['deleteTranferDetail'] ) )
{
	$sc = 'success';
	$result = TRUE;
	$id_tranfer_detail	= $_POST['id_tranfer_detail'];
	//----- ดึงรายการที่จะลบมาตรวจสอบก่อน
	$qs = dbQuery("SELECT * FROM tbl_tranfer_detail WHERE id_tranfer_detail = ".$id_tranfer_detail);
	if( dbNumRows($qs) == 1 )
	{
		startTransection();
		$rs = dbFetchObject($qs);
		if( $rs->valid == 1 OR $rs->id_zone_to != 0 )
		{
			$cs = new transfer($rs->id_tranfer);
			//------ ตรวจสอบยอดคงเหลือในโซนก่อนว่าพอที่จะย้ายกลับมั้ย
			$isEnough = isEnough($rs->id_zone_to, $rs->id_product_attribute, $rs->tranfer_qty);
			
			//----- ถ้าพอย้าย ดำเนินการย้าย
			if( $isEnough === TRUE )
			{
				//----- update_stock_zone ตัดยอดออกจากโซนปลายทาง
				$ra = update_stock_zone(($rs->tranfer_qty * -1), $rs->id_zone_to, $rs->id_product_attribute);
				if( $ra === FALSE ){ $sc = 'update stock fail for desination zone'; }
				
				//----- update stock_movement เอารายการที่ย้ายเข้า มาโซนปลายทาง ออก
				$rb = stock_movement('in', 1, $rs->id_product_attribute, get_warehouse_by_zone($rs->id_zone_to), ($rs->tranfer_qty * -1), $cs->reference, $cs->date_add, $rs->id_zone_to);
				if( $rb === FALSE ){ $sc = 'update stock movement fail for desination zone'; }
				
				//------ update stock zone คืนยอดให้โซนต้นทาง
				$rc = update_stock_zone($rs->tranfer_qty, $rs->id_zone_from, $rs->id_product_attribute);
				if( $rc === FALSE ){ $sc = 'update stock fail for source zone'; }
				
				//------ update stock_movement เอารายการที่ย้ายออกจากโซนต้นทาง ออก
				$rd = stock_movement('out', 2, $rs->id_product_attribute, get_warehouse_by_zone( $rs->id_zone_from), ($rs->tranfer_qty * -1 ), $cs->reference, $cs->date_add, $rs->id_zone_from);
				if( $rd === FALSE ){ $sc = 'update stock movement fail for source zone'; }
				
				//------- delete tranfer detail
				$re = $cs->deleteDetail($rs->id_tranfer_detail);
				if( $re === FALSE ){ $sc = 'delete transfer detail fail'; }
				
				if( $ra === FALSE || $rb === FALSE || $rc === FALSE || $rd === FALSE || $re === FALSE )
				{
					$result = FALSE;
					$sc = 'ทำรายการไม่สำเร็จ';
				}				
				
			}
			else
			{
				$result = FALSE;
				$sc = 'ยอดคงเหลือในโซนไม่พอให้ย้ายกลับ';
			}	
		}
		else /////---- if valid
		{
			//------- move stock in temp to original zone 
			//-------  get stock in temp
			$qr = dbQuery("SELECT * FROM tbl_tranfer_temp WHERE id_tranfer_detail = ".$id_tranfer_detail);
			if( dbNumRows($qr) == 1 )
			{
				$res = dbFetchObject($qr);
				$cs = new transfer();
				//------- move stock in to original zone
				$ra = update_stock_zone($res->qty, $res->id_zone, $res->id_product_attribute);
				if( $ra === FALSE ){ $sc = 'update stock fail'; }
				
				//----- delete tranfer temp
				$rb = $cs->deleteTransferTemp($res->id_tranfer_detail);
				if( $rb === FALSE ){ $sc = 'delete temp fail'; }
				
				//---- delete tranfer detail
				$rc = $cs->deleteDetail($res->id_tranfer_detail);
				if( $rc === FALSE ){ $sc = 'delete detail fail'; }
				
				if( $ra === FALSE || $rb === FALSE || $rc === FALSE )
				{
					$result = FALSE;
				}
				
			}//--- end if temp dbNumRows
			
		}// -- end if valid
		
		//---- delete stock movement where contain 0 move_in and 0 move_out
		drop_zero_movement();
		
		if( $result === TRUE )
		{
			commitTransection();
		}
		else if( $result === FALSE )
		{
			dbRollback();
		}
		endTransection();
	}
	else
	{
		$sc = 'ไม่พบโซนปลายทาง';	
	}//--- end if dbNumRows
	
	echo $sc;
}



if( isset( $_GET['moveAllToZone'] ) )
{
	$sc = TRUE;
	$id_tranfer	= $_GET['id_tranfer'];
	$id_zone_to	= $_GET['id_zone_to'];
	$cs 			= new transfer($id_tranfer);
	
	$qs = dbQuery("SELECT * FROM tbl_tranfer_temp WHERE id_tranfer = ".$id_tranfer);
	if( dbNumRows($qs) > 0 )
	{
		startTransection();
		while( $rs = dbFetchObject($qs) )
		{
			//---- move to zone
			$ra = update_stock_zone($rs->qty, $id_zone_to, $rs->id_product_attribute);
			//---- if move success
			if( $ra === TRUE )
			{
				//------ Insert stock_movement
				$rb = stock_movement('out', 2, $rs->id_product_attribute, get_warehouse_by_zone($rs->id_zone), $rs->qty, $cs->reference, $cs->date_add, $rs->id_zone);
				$rc = stock_movement('in', 1, $rs->id_product_attribute, get_warehouse_by_zone($id_zone_to), $rs->qty, $cs->reference, $cs->date_add, $id_zone_to);
				
				//------ if success remove temp
				if( $rb === TRUE && $rc === TRUE )
				{
					$rd = dbQuery("DELETE FROM tbl_tranfer_temp WHERE id_tranfer_detail = ".$rs->id_tranfer_detail);
					//---- if remove temp successful  do update tranfer_detail field
					if( $rd === TRUE )
					{
						//-----  Update desination zone and valid
						$re = dbQuery("UPDATE tbl_tranfer_detail SET id_zone_to = ".$id_zone_to.", valid = 1 WHERE id_tranfer_detail = ".$rs->id_tranfer_detail);
						if( $re === FALSE )
						{
							$sc = FALSE;
						}
					}
					else
					{
						$sc  = FALSE;
					}
				}
				else
				{
					$sc = FALSE;
				}
				
			}
			else
			{
				$sc = FALSE;
			}
			
			
			if( $sc === TRUE )
			{
				commitTransection();	
			}
			else
			{
				dbRollback();
			}
			
			
		}//---- end while
		
		endTransection();	
		
		
	}//--- end if dbNumRows
	else
	{
		$sc = FALSE;	
	}
	
	echo $sc === TRUE ? 'success' : 'ย้ายสินค้าเข้าโซนไม่สำเร็จ';
}

if( isset( $_GET['moveToZone'] ) )
{
	$sc = TRUE;
	$id_tranfer_detail 	= $_GET['id_tranfer_detail'];
	$id_tranfer 			= $_GET['id_tranfer'];
	$id_zone_to			= $_GET['id_zone_to'];
	$cs  					= new transfer($id_tranfer);
	$qs = dbQuery("SELECT * FROM tbl_tranfer_temp WHERE id_tranfer_detail = ".$id_tranfer_detail);
	if( dbNumRows($qs) == 1 )
	{
	
		$rs = dbFetchObject($qs);
		startTransection();
		//---- move to zone
		$ra = update_stock_zone($rs->qty, $id_zone_to, $rs->id_product_attribute);
		//---- if move success
		if( $ra === TRUE )
		{
			//------ Insert stock_movement
			$rb = stock_movement('out', 2, $rs->id_product_attribute, get_warehouse_by_zone($rs->id_zone), $rs->qty, $cs->reference, $cs->date_add, $rs->id_zone);
			$rc = stock_movement('in', 1, $rs->id_product_attribute, get_warehouse_by_zone($id_zone_to), $rs->qty, $cs->reference, $cs->date_add, $id_zone_to);
			
			//------ if success remove temp
			if( $rb === TRUE && $rc === TRUE )
			{
				$rd = dbQuery("DELETE FROM tbl_tranfer_temp WHERE id_tranfer_detail = ".$id_tranfer_detail);
				//---- if remove temp successful  do update tranfer_detail field
				if( $rd === TRUE )
				{
					//-----  Update desination zone and valid
					$re = dbQuery("UPDATE tbl_tranfer_detail SET id_zone_to = ".$id_zone_to.", valid = 1 WHERE id_tranfer_detail = ".$id_tranfer_detail);
					if( $re === FALSE )
					{
						$sc = FALSE;
					}
				}
				else
				{
					$sc  = FALSE;
				}
			}
			else
			{
				$sc = FALSE;
			}
			
		}
		else
		{
			$sc = FALSE;
		}
		
		
		if( $sc === TRUE )
		{
			commitTransection();	
		}
		else
		{
			dbRollback();
		}
		endTransection();		
		
		
	}
	else
	{
		$sc = FALSE;	
	}
	
	echo $sc === TRUE ? 'success' : 'ย้ายสินค้าเข้าโซนไม่สำเร็จ';
}



if( isset( $_GET['getTransferTable'] ) )
{
	$id			= $_GET['id_tranfer'];
	$canAdd	= $_GET['canAdd'];
	$canEdit	= $_GET['canEdit'];
	$ds = array();
	$cs = new transfer();
	$qs = $cs->getMoveList($id);
	if( dbNumRows($qs) > 0 )
	{
		$no = 1;
		while( $rs = dbFetchObject($qs) )
		{
			$pReference = get_product_reference($rs->id_product_attribute);
			$toZone	= $rs->id_zone_to == 0 ? '<button type="button" class="btn btn-xs btn-primary" onclick="move_in('.$rs->id_tranfer_detail.', '.$rs->id_zone_from.')">ย้ายเข้าโซน</button>' : get_zone($rs->id_zone_to);
			$btn_delete = ($canAdd == 1 OR $canEdit == 1 ) ? '<button type="button" class="btn btn-xs btn-danger" onclick="deleteMoveItem(' . $rs->id_tranfer_detail .' , \'' . $pReference.'\')"><i class="fa fa-trash"></i></button>' : '';
			$arr = array(
						'no'			=> $no,
						'id'				=> $rs->id_tranfer_detail,
						'barcode'	=> get_barcode($rs->id_product_attribute),
						'products'	=> $pReference,
						'id_zone_from'	=> $rs->id_zone_from,
						'fromZone'	=> get_zone($rs->id_zone_from),
						'toZone'		=> $toZone,
						'qty'			=> number_format($rs->tranfer_qty),
						'btn_delete'	=> $btn_delete
						);
			array_push($ds, $arr);	
			$no++;					
		}
	}
	else
	{
		array_push($ds, array('nodata' => 'nodata'));	
	}
	echo json_encode($ds);
}



if( isset( $_GET['addToTransfer'] ) )
{
	$sc = TRUE;
	$id_tranfer 	= $_GET['id_tranfer'];
	$id_zone		= $_GET['id_zone'];
	$moveQty 	= $_POST['moveQty'];
	$pd			= $_POST['id_pa'];
	$udz			= isset( $_POST['underZero'] ) ? $_POST['underZero'] : array();
	$cs = new transfer();
	foreach( $moveQty as $name => $val)
	{
		startTransection();
		if( $val != '' && $val != 0 )
		{
			$id_pa	= $pd[$name];
			$qty		= $val;
			$arr = array( 
							"id_tranfer" => $id_tranfer,
							"id_product_attribute"	=> $id_pa,
							"id_zone_from"	=> $id_zone,
							"id_zone_to"		=> 0,
							"tranfer_qty"		=> $qty
							);	
			$rs = $cs->isExistsDetail($arr);
			if( $rs !== FALSE )
			{
				//----- if exists detail update 
				$id = $cs->updateDetail($rs, $arr);
				
			}
			else
			{
				//---- if not exists insert new row
				$id = $cs->addDetail($arr);
				
			}
			
			if( $id === FALSE )
			{
				//----- If insert or update tranfer detail fail
				$sc = FALSE;
			}
			else
			{
				//----- If insert or update tranfer detail successful  do insert or update tranfer temp
				$temp = array(
									"id_tranfer_detail"	=> $id,
									"id_tranfer"			=> $id_tranfer,
									"id_product_attribute"	=> $id_pa,
									"id_zone"		=> $id_zone,
									"qty"	=> $qty,
									"id_employee"	=> getCookie('user_id')
									);
				$ra = $rs == FALSE ? $cs->addTransferTemp($temp) : $cs->updateTransferTemp($temp);	
				if( $ra === TRUE )
				{
					//---- if insert or update tranfer temp success do update stock in zone
					$rd = $cs->updateStock($id_zone, $id_pa, ($qty * -1));
					if( $rd === FALSE )
					{
						//--- if update stock fail
						$sc = FALSE;
					}
				}
				else
				{
					//---- if insert or update tranfer temp fail
					$sc = FALSE;	
				}
			}
		}
	}
	
	if( $sc === TRUE )
	{
		commitTransection();
	}
	else
	{
		dbRollback();	
	}
	endTransection();
	
	echo $sc === TRUE ? 'success' : 'fail';
}




//--------- เพิ่มสินค้าทั้งหมดในโซนเข้าเอกสาร แล้ว ย้ายสินค้าทั้งหมดในโซนเข้า temp
if( isset( $_GET['addAllToTransfer'] ) )
{
	$sc = TRUE;
	$id_tranfer 	= $_GET['id_tranfer'];
	$id_zone		= $_GET['id_zone'];
	$udz			= $_GET['allowUnderZero'];
	$cs = new transfer();
	
	//------  ดึงสินค้าทั้งหมดในโซน
	$qs = dbQuery("SELECT * FROM tbl_stock WHERE id_zone = ".$id_zone);
	
	if( dbNumRows($qs) > 0 )
	{
		startTransection();
		while( $rs = dbFetchObject($qs) )
		{
			if( $rs->qty != 0 && ( $rs->qty > 0 OR $udz == 1 ) )
			{
				$arr = array(
							"id_tranfer"				=> $id_tranfer,
							"id_product_attribute"	=> $rs->id_product_attribute,
							"id_zone_from"			=> $rs->id_zone,
							"id_zone_to"				=> 0,
							"tranfer_qty"				=> $rs->qty
							);
				//---- check is tranfer_detail exists or not
				$ra = $cs->isExistsDetail($arr);
				if( $ra !== FALSE )
				{
					//----- if exists detail update 
					$id = $cs->updateDetail($ra, $arr);	
				}
				else
				{
					//---- if not exists insert new row
					$id = $cs->addDetail($arr);
				}
				
				if( $id === FALSE )
				{
					//----- If insert or update tranfer detail fail
					$sc = FALSE;
				}
				else
				{
					//----- If insert or update tranfer detail successful  do insert or update tranfer temp
					$temp = array(
										"id_tranfer_detail"	=> $id,
										"id_tranfer"			=> $id_tranfer,
										"id_product_attribute"	=> $rs->id_product_attribute,
										"id_zone"		=> $id_zone,
										"qty"	=> $rs->qty,
										"id_employee"	=> getCookie('user_id')
										);
					$rb = $ra == FALSE ? $cs->addTransferTemp($temp) : $cs->updateTransferTemp($temp);	
					if( $rb === TRUE )
					{
						//---- if insert or update tranfer temp success do update stock in zone
						$rd = $cs->updateStock($id_zone, $rs->id_product_attribute, ($rs->qty * -1));
						if( $rd === FALSE )
						{
							//--- if update stock fail
							$sc = FALSE;
						}
					}
					else
					{
						//---- if insert or update tranfer temp fail
						$sc = FALSE;	
					}//---- end if $rb === TRUE
				}//--- end if $id === FALSE
			}//---- end if qty != 0
		}//--- endwhile
	}//--- end if dbNumRows
	
	if( $sc === TRUE )
	{
		commitTransection();
	}
	else
	{
		dbRollback();	
	}
	
	endTransection();
	
	//------ Delete stock zone where qty = 0
	$cs->clearStockZeroZone($id_zone);
	
	echo $sc === TRUE ? 'success' : 'fail';
	
}




//----- Add new transfer document
if( isset( $_GET['addNew'] ) )
{
	$cs = new transfer();
	$date	= dbDate($_POST['date_add'], TRUE);
	$arr = array(
				'reference'			=> $cs->getNewReference($date),
				'warehouse_from'	=> $_POST['fromWH'],
				'warehouse_to'		=> $_POST['toWH'],
				'id_employee'		=> getCookie('user_id'),
				'date_add'			=> $date,
				'comment'			=> $_POST['remark']
				);
	$id = $cs->add($arr);		
	if( $id !== FALSE )
	{
		$ds = json_encode(array("id" => $id));
	}
	else
	{
		$ds = "เพิ่มรายการไม่สำเร็จ กรุณาลองใหม่อีกครั้งภายหลัง";
	}
	echo $ds;				
}

//------- Update document header
if( isset( $_GET['updateHeader'] ) )
{
	$sc = 'success';
	$id_tranfer	= $_POST['id_tranfer'];
	$date			= dbDate($_POST['date_add'], TRUE);
	$cs 			= new transfer();
	$arr = array(
				'warehouse_from'	=> $_POST['fromWH'],
				'warehouse_to'		=> $_POST['toWH'],
				'id_employee'		=> getCookie('user_id'),
				'date_add'			=> $date,
				'comment'			=> $_POST['remark']
				);
	$rs = $cs->update($id_tranfer, $arr);
	if( $rs === FALSE )
	{
		$sc = $cs->error;
	}
	echo $sc;
}


if( isset( $_GET['getProductInZone'] ) )
{
	$sc = array();
	$id_zone = $_GET['id_zone'];
	$qr = "SELECT s.id_stock, s.id_product_attribute, p.barcode, p.reference, s.qty ";
	$qr .= "FROM tbl_stock AS s ";
	$qr .= "JOIN tbl_product_attribute AS p ";
	$qr .= "USING(id_product_attribute) ";
	$qr .= "WHERE s.id_zone = ".$id_zone." AND qty != 0";
	$qs = dbQuery($qr);
	if( dbNumRows($qs) > 0 )
	{
		$no = 1;
		while( $rs = dbFetchObject($qs) )
		{
			$arr = array( 
						"no"			=> $no,
						"id_stock" 	=> $rs->id_stock, 
						"id_pa"		=> $rs->id_product_attribute,
						"barcode" 	=> $rs->barcode, 
						"products" 	=> $rs->reference, 
						"qty" 			=> $rs->qty
						);
			array_push($sc, $arr);
			$no++;
		}
	}
	else
	{
		array_push($sc, array("nodata" => "nodata"));	
	}
	echo json_encode($sc);
}




if( isset( $_GET['printTranfer'] ) )
{
	$id_tranfer		= $_GET['id_tranfer'];
	
	$print 			= new printer();
	echo $print->doc_header();
	$print->add_title("PO/ใบสั่งซื้อ");
	$header			= array("เลขที่เอกสาร"=>$po->reference, "วันที่เอกสาร"=>thaiDate($po->date_add), "ผู้ขาย"=>supplier_code($po->id_supplier)." : ".supplier_name($po->id_supplier), "กำหนดรับ"=>thaiDate($po->due_date));
	$print->add_header($header);
	$detail			= $po->get_detail($id_po);
	$total_row 		= dbNumRows($detail);
	$config 			= array("total_row"=>$total_row, "font_size"=>10, "sub_total_row"=>4);
	$print->config($config);
	$row 				= $print->row;
	$total_page 		= $print->total_page;
	$total_qty 		= 0;
	$total_price		= 0;
	$total_amount 	= 0;
	$total_discount = 0;
	$bill_discount	= $po->bill_discount;
	//**************  กำหนดหัวตาราง  ******************************//
	$thead	= array(
						array("ลำดับ", "width:5%; text-align:center; border-top:0px; border-top-left-radius:10px;"),
						array("รหัส", "width:15%; text-align:center;border-left: solid 1px #ccc; border-top:0px;"),
						array("สินค้า", "width:35%; text-align:center;border-left: solid 1px #ccc; border-top:0px;"),
						array("จำนวน", "width:10%; text-align:center; border-left: solid 1px #ccc; border-top:0px;"),
						array("ราคา", "width:10%; text-align:center; border-left: solid 1px #ccc; border-top:0px;"),
						array("ส่วนลด", "width:15%; text-align:center; border-left: solid 1px #ccc; border-top:0px;"),
						array("มูลค่า", "width:10%; text-align:center; border-left: solid 1px #ccc; border-top:0px; border-top-right-radius:10px")
						);
	$print->add_subheader($thead);
	
	//***************************** กำหนด css ของ td *****************************//
	$pattern = array(
							"text-align: center; border-top:0px;",
							"border-left: solid 1px #ccc; border-top:0px;",
							"border-left: solid 1px #ccc; border-top:0px;",
							"text-align:center; border-left: solid 1px #ccc; border-top:0px;",
							"text-align:center; border-left: solid 1px #ccc; border-top:0px;",
							"text-align:center; border-left: solid 1px #ccc; border-top:0px;",
							"text-align:right; border-left: solid 1px #ccc; border-top:0px;"
							);					
	$print->set_pattern($pattern);	
	
	//*******************************  กำหนดช่องเซ็นของ footer *******************************//
	$footer	= array( 
						array("ผู้จัดทำ", "","วันที่............................."), 
						array("ผู้ตรวจสอบ", "","วันที่............................."),
						array("ผู้อนุมัติ", "","วันที่.............................")
						);						
	$print->set_footer($footer);		
	
	$n = 1;
	while($total_page > 0 )
	{
		echo $print->page_start();
			echo $print->top_page();
			echo $print->content_start();
				echo $print->table_start();
				$i = 0;
				$product = new product();
				while($i<$row) : 
					$rs = dbFetchArray($detail);
					if(count($rs) != 0) :
						$id_product 		= $product->getProductId($rs['id_product_attribute']);
						$product_code 	= $product->product_reference($rs['id_product_attribute']);
						$product_name 	= "<input type='text' style='border:0px; width:100%;' value='".$product->product_name($id_product)."' />";
						$dis					= $po->getDiscount($rs['discount_percent'], $rs['discount_amount']); // หาส่วนลด
						$discount			= number_format($dis['value'],2)." ".$dis['unit'];
						$data 				= array($n, $product_code, $product_name, number_format($rs['qty']), number_format($rs['price'], 2), $discount, number_format($rs['total_amount'], 2) );
						$total_qty 			+= $rs['qty'];
						$total_price 		+= $rs['qty'] * $rs['price'];
						$total_amount 		+= $rs['total_amount'];
						$total_discount 	+= $rs['total_discount'];
					else :
						$data = array("", "", "", "","", "","");
					endif;
					echo $print->print_row($data);
					$n++; $i++;  	
				endwhile;
				echo $print->table_end();
				if($print->current_page == $print->total_page)
				{ 
					$qty = number_format($total_qty);
					$amount = number_format($total_price,2); 
					$total_discount_amount = number_format($total_discount+$bill_discount,2);
					$net_amount = number_format($total_price - ($total_discount + $bill_discount) ,2);
					$remark = $po->remark;
				}else{ 
					$qty = ""; 
					$amount = ""; 
					$total_discount_amount = "";
					$net_amount = "";
					$remark = ""; 
				}
				$sub_total = array(
						array("<td style='height:".$print->row_height."mm; border: solid 1px #ccc; border-bottom:0px; border-left:0px; width:60%; text-align:center;'>**** ส่วนลดท้ายบิล : ".number_format($bill_discount,2)." ****</td>
								<td style='width:20%; height:".$print->row_height."mm; border: solid 1px #ccc;'><strong>จำนวนรวม</strong></td>
								<td style='width:20%; height:".$print->row_height."mm; border: solid 1px #ccc; border-right:0px; text-align:right;'>".$qty."</td>"),
						array("<td rowspan='3' style='height:".$print->row_height."mm; border-top: solid 1px #ccc; border-bottom-left-radius:10px; width:55%; font-size:10px;'><strong>หมายเหตุ : </strong>".$remark."</td>
								<td style='width:20%; height:".$print->row_height."mm; border: solid 1px #ccc;'><strong>ราคารวม</strong></td>
								<td style='width:20%; height:".$print->row_height."mm; border: solid 1px #ccc; border-right:0px; text-align:right;'>".$amount."</td>"),
						array("<td style='height:".$print->row_height."mm; border: solid 1px #ccc; border-bottom:0px;'><strong>ส่วนลดรวม</strong></td>
						<td style='height:".$print->row_height."mm; border: solid 1px #ccc; border-right:0px; border-bottom:0px; border-bottom-right-radius:10px; text-align:right;'>".$total_discount_amount."</td>"),
						array("<td style='height:".$print->row_height."mm; border: solid 1px #ccc; border-bottom:0px;'><strong>ยอดเงินสุทธิ</strong></td>
						<td style='height:".$print->row_height."mm; border: solid 1px #ccc; border-right:0px; border-bottom:0px; border-bottom-right-radius:10px; text-align:right;'>".$net_amount."</td>")
						);
			echo $print->print_sub_total($sub_total);				
			echo $print->content_end();
			echo $print->footer;
		echo $print->page_end();
		$total_page --; $print->current_page++;
	}
	echo $print->doc_footer();
}



if( isset( $_GET['clearFilter'] ) )
{
	deleteCookie('sCode');
	deleteCookie('sEmp');
	deleteCookie('fromDate');
	deleteCookie('toDate');
	deleteCookie('sStatus');
	echo 'success';	
}
?>