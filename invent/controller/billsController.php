<?php
require "../../library/config.php";
require "../../library/functions.php";
require "../function/tools.php";
require "../function/bill_helper.php";

//---------------- ขายปกติ
if( isset( $_GET['confirm_order'] ) && isset( $_GET['order'] ) )
{
	$id_order 		= $_GET['id_order'];
	$id_employee 	= $_GET['id_employee'];
	$bill_discount 	= bill_discount($id_order);
	$order 			= new order($id_order);	
	$state 			= get_current_state($id_order);
	$sc				= TRUE;
	$message		= 'success';
	if( $state == 10 )
	{
		//--- เปลี่ยนเป็นเปิดบิลแล้วก่อน กันคนอื่นมาเปิดซ้ำกัน
		order_state_change($id_order, 9, $id_employee); 
		
		//--------- Query From QC
		$qr = "SELECT qc.id_product_attribute AS id_pa, SUM( qc.qty ) AS qty, tmp.id_zone, tmp.id_warehouse ";
		$qr .= "FROM tbl_qc AS qc ";
		$qr .= "JOIN tbl_temp AS tmp ";
		$qr .= "USING (id_temp) ";
		$qr .= "WHERE qc.id_order = ".$id_order." AND qc.valid = 1 ";
		$qr .= "GROUP BY qc.id_product_attribute, tmp.id_zone";
		
		$qs = dbQuery($qr);
		
		//------ กรณีฝากขาย
		if( $order->role == 5 )
		{
			include 'subController/consignProcess.php';
		}
		
		//------ กรณีขายทั่วไป
		if( $order->role == 1 )
		{
			include 'subController/orderProcess.php';
		}
		
		//----- กรณีสปอนเซอร์สโมสร
		if( $order->role == 4 )
		{
			include '../function/sponsor_helper.php';
			include 'subController/sponsorProcess.php';
		}
		
		//----- กรณีเบิกอภินันท์
		if( $order->role == 7 )
		{
			include '../function/support_helper.php';
			include 'subController/supportProcess.php';	
		}
		
		//----- กรณี ยืมสินค้า
		if( $order->role == 3 )
		{
			include '../function/lend_helper.php';
			include 'subController/lendProcess.php';	
		}
		
		//------ กรณีเบิกแปรสภาพ
		if( $order->role == 2 OR $order->role == 6 )
		{
			include 'subController/orderProcess.php';
		}		
		
		
		//------ ถ้าไม่สำเร็จ ย้อนสถานะไปเป้นรอเปิดบิลเหมือนเดิม
		if( $sc === FALSE )
		{
			//-----  ย้อนสถานะไปเป้นรอเปิดบิลเหมือนเดิม
			$sc = dbQuery("UPDATE tbl_order SET current_state = 10 WHERE id_order = ".$id_order);
			
			//------ กันเหนียว
			if( $sc === FALSE )
			{
				dbQuery("UPDATE tbl_order SET current_state = 10 WHERE id_order = ".$id_order);
			}
			
			//------ ลบล่องรอยใน order stage change
			dbQuery("DELETE FROM tbl_order_state_change WHERE id_order = ".$id_order." AND id_order_state = 9 AND id_employee = ".$id_employee);	
		}
	}
	else
	{
		$message = 'สถานะเอกสารถูกเปลี่ยนไปแล้ว';	
	}
	
	echo $message;
}


?>