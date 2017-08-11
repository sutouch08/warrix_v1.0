<?php

function isEnough($id_zone, $id_pa, $qty){
	$sc = FALSE;
	$qs = dbQuery("SELECT qty FROM tbl_stock WHERE id_zone = ".$id_zone." AND id_product_attribute = ".$id_pa." AND qty >= ".$qty);
	if( dbNumRows($qs) == 1 )
	{
		$sc = TRUE;
	}
	return $sc;		
}

function isComplete($id)
{
	$sc = TRUE;
	$qs = dbQuery("SELECT id_product_attribute FROM tbl_tranfer_detail WHERE id_tranfer = ".$id." AND valid = 0 LIMIT 1");
	if( dbNumRows($qs) > 0 )
	{
		$sc = FALSE;
	}
	return $sc;
}

function not_complete_in()
{
	$sc = 0;
	$qs = dbQuery("SELECT id_tranfer FROM tbl_tranfer_detail WHERE valid = 0 GROUP BY id_tranfer");
	if( dbNumRows($qs) > 0 )
	{
		while( $rs = dbFetchObject($qs) )
		{
			$sc .= ", ".$rs->id_tranfer;
		}
	}
	return $sc;
}

function WHList($id = "")
{
	$sc = '<option value="0">กรุณาเลือก</option>';
	$qs = dbQuery("SELECT * FROM tbl_warehouse");
	if( dbNumRows($qs) > 0 )
	{
		while( $rs = dbFetchObject($qs) )
		{
			$sc .= '<option value="'.$rs->id_warehouse.'" '.isSelected($id, $rs->id_warehouse).'>'.$rs->warehouse_name.'</option>';
		}
	}
	return $sc;
}