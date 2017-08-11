// JavaScript Document
function deleteTransfer(id_tranfer, reference){
	swal({
		title: 'คุณแน่ใจ ?',
		text: 'ต้องการลบ '+ reference +' หรือไม่ ?',
		type: 'warning',
		showCancelButton: true,
		comfirmButtonColor: '#DD6855',
		confirmButtonText: 'ใช่ ฉันต้องการลบ',
		cancelButtonText: 'ยกเลิก',
		closeOnConfirm: false
	}, function(){
		$.ajax({
			url:"controller/tranferController.php?deleteTranfer",
			type:"POST", cache:"false", data:{ "id_tranfer" : id_tranfer },
			success: function(rs){
				var rs = $.trim(rs);
				if( rs == 'success' ){
					swal({ title:'Deleted', text: 'ลบรายการเรียบร้อยแล้ว', type: 'success', timer: 1000 });
					$("#row_"+id_tranfer).remove();
				}else{
					swal("ข้อผิดพลาด", rs, "error");
				}
			}
		});
	});
}



function deleteMoveItem(id_tranfer_detail, product_reference){
	swal({
		title: 'คุณแน่ใจ ?',
		text: 'ต้องการลบ '+ product_reference +' หรือไม่ ?',
		type: 'warning',
		showCancelButton: true,
		comfirmButtonColor: '#DD6855',
		confirmButtonText: 'ใช่ ฉันต้องการลบ',
		cancelButtonText: 'ยกเลิก',
		closeOnConfirm: false
	}, function(){
		$.ajax({
			url:"controller/tranferController.php?deleteTranferDetail",
			type:"POST", cache:"false", data:{ "id_tranfer_detail" : id_tranfer_detail },
			success: function(rs){
				var rs = $.trim(rs);
				if( rs == 'success' ){
					swal({ title:'Deleted', text: 'ลบรายการเรียบร้อยแล้ว', type: 'success', timer: 1000 });
					$("#row-"+id_tranfer_detail).remove();
				}else{
					swal("ข้อผิดพลาด", rs, "error");
				}
			}
		});
	});
}

//--------------  ย้ายสินค้าจาก temp เข้าโซนปลายทางทีเดียวทั้งหมด
function move_in_all(){
	var id_zone_to	= $("#id_zone_to").val();
	var id_tranfer = $("#id_tranfer").val();
	if( id_zone_to.length == 0 ){
		swal("กรุณาระบุโซนปลายทาง");
		return false;
	}
	
	var sameZone = countSameZone();
	
	if( sameZone > 0 ){
		swal("ข้อผิดพลาด !", "พบรายการที่โซนต้นทางตรงกับโซนปลายทาง "+sameZone+" รายการ", "warning");	
		return false;
	}
	

	load_in();
	$.ajax({
		url:"controller/tranferController.php?moveAllToZone",
		type:"GET", cache:"false", 
		data:{ 
				"id_tranfer" : id_tranfer,
				"id_zone_to" : id_zone_to
		},success: function(rs){
			load_out();
			var rs = $.trim(rs);
			if( rs == 'success' ){
				load_out();
				getTransferTable();
				swal({ title: 'Success', text: 'ย้ายสินค้าเข้าเรียบร้อยแล้ว', type: 'success', timer: 100 });
			}else{
				swal("ข้อผิดพลาด !", rs, "error");	
			}
		}
	});	
}

function countSameZone(){
	var count = 0;
	var to	= $("#id_zone_to").val();
	$(".row-zone-from").each(function(index, element) {
        count += ($(this).val() == to ? 1 : 0);
    });	
	return count;
	
}

//--------------  ย้ายสินค้าจาก temp เข้าโซนปลายทางทีละรายการ
function move_in(id_tranfer_detail, id_zone_from){
	var id_zone_to	= $("#id_zone_to").val();
	var id_tranfer = $("#id_tranfer").val();
	console.log(id_zone_from);
	console.log(id_zone_to);
	//--- ตรวจสอบโซนปลายทาง มีการกำหนดไว้แล้วหรือยัง
	if( id_zone_to.length == 0 ){
		swal("ข้อผิดพลาด", "กรุณาระบุโซนปลายทาง", "warning");
		return false;
	}
	
	//----- ตรวจสอบโซนปลายทาง ต้องไม่ตรง กับโซนต้นทาง
	if( id_zone_from == id_zone_to ){
		swal("ข้อผิดพลาด", "โซนปลายทางต้องไม่ใช่โซนเดียวกันกับโซนต้นทาง", "warning");
		return false;
	}
	
	$.ajax({
		url:"controller/tranferController.php?moveToZone",
		type:"GET", cache:"false", 
		data:{ 
				"id_tranfer_detail" : id_tranfer_detail, 
				"id_tranfer" : id_tranfer,
				"id_zone_from" : id_zone_from, 
				"id_zone_to" : id_zone_to
		},success: function(rs){
			var rs = $.trim(rs);
			if( rs == 'success' ){
				$("#row-label-"+id_tranfer_detail).text($("#toZone").val());	
			}else{
				swal("ข้อผิดพลาด !", rs, "error");	
			}
		}
	});	
}





//------------  ตาราง tranfer_detail
function getTransferTable(){
	var id_tranfer	= $("#id_tranfer").val();
	var canAdd		= $("#canAdd").val();
	var canEdit		= $("#canEdit").val();
	$.ajax({
		url:"controller/tranferController.php?getTransferTable",
		type:"GET", cache:"false",data:{ "id_tranfer" : id_tranfer, "canAdd" : canAdd, "canEdit" : canEdit },
		success: function(rs){
			if( isJson(rs) ){
				var source 	= $("#transferTableTemplate").html();
				var data		= $.parseJSON(rs);
				var output	= $("#transfer-list");
				render(source, data, output);
			}
		}
	});
}





//------------  เพิ่มรายการลงใน tranfer detail แล้ว เพิ่มลงใน tranfer_temp  และ update stock ตามรายการที่ใส่ตัวเลข
function addToTransfer(){
	var id_tranfer	= $("#id_tranfer").val();
	var id_zone		= $("#id_zone_from").val();
	var count = countInput();
	if( count > 0 ){
		$.ajax({
			url:"controller/tranferController.php?addToTransfer&id_tranfer="+id_tranfer+"&id_zone="+id_zone,
			type:"POST", cache:"false", data: $("#productForm").serializeArray() ,
			success: function(rs){
				var rs = $.trim(rs);
				if( rs == 'success' ){
					swal({title: 'success', text: 'เพิ่มรายการเรียบร้อยแล้ว', type: 'success', timer: 1000 });
					getTransferTable();
					setTimeout( function(){ showTransferTable(); }, 1200);
				}else{
					swal("ข้อผิดพลาด", "เพิ่มรายการไม่สำเร็จ", "error");	
				}
			}
		});
	}else{
		swal('ข้อผิดพลาด !', 'กรุณาระบุจำนวนในรายการที่ต้องการย้าย อย่างน้อย 1 รายการ', 'warning');
	}
}





//------------  เพิ่มรายการลงใน tranfer detail แล้ว เพิ่มลงใน tranfer_temp  และ update stock รายการทั้งหมด

function addAllToTransfer(){
	var id_tranfer 	= $("#id_tranfer").val();
	var id_zone		= $("#id_zone_from").val();
	var allowUnderZero = ( $("#allowUnderZero").is(':checked') == true ? 1 : 0 );
	var count		= countUnderZero();	
	
	if( count > 0 && allowUnderZero == 0 ){
		swal("ข้อผิดพลาด !", "พบรายการที่ติดลบ ไม่สามารถดำเนินการต่อได้", "warning");
		return false;
	}
	
	if( count == 0 || allowUnderZero == 1 ){
		$.ajax({
			url:"controller/tranferController.php?addAllToTransfer",
			type:"GET", cache:"false", data:{ "id_tranfer" : id_tranfer, "id_zone" : id_zone, "allowUnderZero" : allowUnderZero },
			success: function(rs){
				var rs = $.trim(rs);
				if( rs == 'success' ){
					swal({title: 'success', text: 'เพิ่มรายการเรียบร้อยแล้ว', type: 'success', timer: 1000 });
					getTransferTable();
					setTimeout( function(){ showTransferTable(); }, 1200);
				}else{
					swal("ข้อผิดพลาด", "เพิ่มรายการไม่สำเร็จ", "error");	
				}
			}
		});
	}
}




//----- นับจำนวน ช่องที่มีการใส่ตัวเลข
function countInput(){
	var count = 0;
	$(".input-qty").each(function(index, element) {
        count += ($(this).val() == "" ? 0 : 1 );
    });	
	return count;
}



//------ นับจำนวนรายการที่ยอดติดลบ
function countUnderZero(){
	var count = 0;
	$(".qty-label").each(function(index, element) {
        count += (parseInt($(this).text()) < 0 ? 1 : 0);
    });	
	return count;
}



//-------  ใส่ได้เฉพาะตัวเลขเท่านั้น
function validQty(id, qty){
	var input = $("#moveQty_"+id).val();
	if( input.length > 0 && isNaN( parseInt( input ) ) ){
		swal('กรุณาใส่ตัวเลขเท่านั้น');
		$("#moveQty_"+id).val('');
		return false;
	}
	
    if( $("#underZero_"+id).is(':checked') == false && ( parseInt( input ) > parseInt(qty) ) ){
		swal('จำนวนในโซนมีไม่พอ');
		$("#moveQty_"+id).val('');
		return false;
	}
}


//-------  ดึงรายการสินค้าในโซน
function getProductInZone(){
	var id_zone = $("#id_zone_from").val();
	if( id_zone.length > 0 ){
		load_in();
		$.ajax({
			url:"controller/tranferController.php?getProductInZone&id_zone="+id_zone,
			type:"GET", cache:"false",
			success: function(rs){
				load_out();
				var rs = 	$.trim(rs);
				if( isJson(rs) ){
					var source = $("#zoneTemplate").html();
					var data		= $.parseJSON(rs);
					var output	= $("#zone-list");
					render(source, data, output);
					$("#transfer-table").addClass('hide');
					$("#zone-table").removeClass('hide');	
				}
			}
		});
	}
}




$("#fromZone").autocomplete({
	source: "controller/autoComplete.php?getTransferZone&id_warehouse="+ $("#id_wh_from").val(),
	autoFocus: true,
	close: function(){
		var rs = $(this).val();
		var rs = rs.split(' | ');
		if( rs.length == 2 ){
			$("#id_zone_from").val(rs[1]);
			$(this).val(rs[0]);	
		}else{
			$("#id_zone_from").val('');
			$(this).val('');
		}
	}
});




$("#toZone").autocomplete({
	source: "controller/autoComplete.php?getTransferZone&id_warehouse="+ $("#id_wh_to").val(),
	autoFocus: true,
	close: function(){
		var rs = $(this).val();
		var rs = rs.split(' | ');
		if( rs.length == 2 ){
			$("#id_zone_to").val(rs[1]);
			$(this).val(rs[0]);	
			$("#btn-move-all").removeClass('not-show');
		}else{
			$("#id_zone_to").val('');
			$(this).val('');
			$("#btn-move-all").addClass('not-show');
		}
	}
});




$("#fromZone").keyup(function(e) {
    if( e.keyCode == 13 ){
		setTimeout(function(){ getProductInZone(); }, 100);
	}
});



//------- สลับไปแสดงหน้า tranfer_detail
function showTransferTable(){
	$("#zone-table").addClass('hide');
	$("#transfer-table").removeClass('hide');
}
	


function addNew(){
	var dateAdd = $("#dateAdd").val();
	var fromWH	= $("#fromWH").val();
	var toWH		= $("#toWH").val();
	var remark	= $("#remark").val();
	
	if( ! isDate( dateAdd ) ){
		swal('วันที่ไม่ถูกต้อง');
		return false;
	}
	
	if( fromWH == 0 || toWH == 0 ){
		swal('คลังสินค้าไม่ถูกต้อง');
		return false;
	}
	
	load_in();
	$.ajax({
		url:"controller/tranferController.php?addNew",
		type:"POST", cache:"false", data:{ "date_add" : dateAdd, "fromWH" : fromWH, "toWH" : toWH, "remark" : remark },
		success: function(rs){
			load_out();
			if( isJson(rs) ){
				var ds = $.parseJSON(rs);
				var id = ds.id;
				window.location.href = "index.php?content=tranfer&add&id_tranfer="+id;
			}else{
				swal('ข้อผิดพลาด !', rs, 'error');
			}
		}
	});	
}



function updateHeader(id){
	var dateAdd = $("#dateAdd").val();
	var fromWH	= $("#fromWH").val();
	var toWH		= $("#toWH").val();
	var remark	= $("#remark").val();
	
	if( ! isDate( dateAdd ) ){
		swal('วันที่ไม่ถูกต้อง');
		return false;
	}
	
	if( fromWH == 0 || toWH == 0 ){
		swal('คลังสินค้าไม่ถูกต้อง');
		return false;
	}
	
	load_in();
	$.ajax({
		url:"controller/tranferController.php?updateHeader",
		type:"POST", cache:"false", data:{ "id_tranfer" : id, "date_add" : dateAdd, "fromWH" : fromWH, "toWH" : toWH, "remark" : remark },
		success: function(rs){
			load_out();
			var rs = $.trim(rs);
			if( rs == 'success' ){
				swal({ title: 'success', text: 'บันทึกข้อมูลเรียบร้อยแล้ว', type: 'success', timer: 1000 });
				$("#dateAdd").attr('disabled', 'disabled');
				$('#fromWH').attr('disabled', 'disabled');
				$('#toWH').attr('disabled', 'disabled');
				$('#remark').attr('disabled', 'disabled');
				$('#btn-update').addClass('hide');
				$('#btn-edit').removeClass('hide');
			}else{
				swal('ข้อผิดพลาด !', rs, 'error');
			}
		}
	});	
}




function editHeader(){
	$(".header-box").removeAttr('disabled');
	$("#btn-edit").addClass('hide');
	$("#btn-update").removeClass('hide');
}



function toggleActive(){
	var status = $("#sStatus").val();
	if( status == 1 ){
		$("#sStatus").val(0);
		$("#btn-inComplete").removeClass('btn-info');
	}else{
		$("#sStatus").val(1);
		$("#btn-inComplete").addClass('btn-info');
	}
	$("#searchForm").submit();
}





function goBack(){
	window.location.href = "index.php?content=tranfer";
}




function goEdit(id){
	window.location.href = "index.php?content=tranfer&edit&id_tranfer="+id;	
}



function goDetail(id){
	window.location.href = "index.php?content=tranfer&view_detail&id_tranfer="+id;	
}


function getNew(){
	window.location.href = "index.php?content=tranfer&add";	
}



function printTransfer(){
	var id = $("#id_tranfer").val();
	var center = ($(document).width() - 800) /2;
	window.open("controller/tranferController.php?printTranfer&id_tranfer="+id, "_blank", "width=800, height=900, left="+center+", scrollbars=yes");
}



function getSearch(){
	var sCode 	= $("#sCode").val();
	var sEmp	 	= $("#sEmp").val();
	var from		= $("#fromDate").val();
	var to			= $("#toDate").val();
	if( sCode.length > 0 || sEmp.length > 0 || ( isDaate(from) && isDate(to) ) ) {
		$("#searchForm").submit();
	}
}






$(".search-box").keyup(function(e) {
    if( e.keyCode == 13 ){
		getSearch();
	}
});




function clearFilter(){
	$.get("controller/tranferController.php?clearFilter", function(){ goBack(); });	
}


$(document).ready(function(e) {
	if( $("#fromDate").length > 0 ){
		var from = $("#fromDate").val();
		var to	 = $("#toDate").val();
		if( isDate(from) && isDate(to) ){
			$("#toDate").datepicker('option', 'minDate', from);
			$("#fromDate").datepicker('option', 'maxDate', to);
		}
	}
});


$("#dateAdd").datepicker({
	dateFormat: 'dd-mm-yy'
});


$("#fromDate").datepicker({
	dateFormat: 'dd-mm-yy',
	onClose: function(se){
		$("#toDate").datepicker("option", "minDate", se);
	}
});




$("#toDate").datepicker({
	dateFormat : 'dd-mm-yy',
	onClose: function(se){
		$("#fromDate").datepicker("option", "maxDate", se);
	}
});