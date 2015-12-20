
jQuery(document).ready(function($) {
    alert( $('.progress').length );



});



var running = true;
var progress = '0%';


jQuery(document).ready( function($) {

	function runExport(){
		delete formData;
		formData = new FormData();
		//formData.append('img_file', file);
		formData.append( 'action', 'export_users_data' );
		
		$.ajaxSetup({
			type:'POST',
			url:$('.progress').data('listener')/*http://localhost/wordpress28/wp-admin/admin-ajax.php'/*$(this).data('ajax-listener')*/,
			data:formData,
			processData: false,
    		contentType: false,
			beforeSend:function(){
				//$('#img-gal-uploader>div>.loading').removeClass('hide');
			},
			complete:function(jqXHR, textStatus){
				//alert(jqXHR);
			},
			success:function(response){
				parsedResponse = $.parseJSON(response);
				running = parsedResponse.running;
				progress = parsedResponse.progress;
				$('.progress>div>div').css('width', progress);
				$('.progress>span').html(progress);
				alert('Got this from the server: ' + response);
				
				//alert(parsedResponse.url);
				//$('.edit-img-gal').prepend('<div data-atta-id="'+parsedResponse.atta_id+'"><img src="'+parsedResponse.url+'" /><div class="delete"><i class="icon-trash fa-3x"></i></div></div>');
				//dialog.dialog( "close" );
				//$('.delete').off('click');
				//bindDeleteMethod();
				//runExport();

			},
		});
		$.ajax().done(function(){
			//alert(running);
			if(running)
				runExport();
		});
	}

    if($('.progress').length == 1){
		runExport();
	};
} );
