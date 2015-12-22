
var running = true;
var progress = '0%';


jQuery(document).ready( function($) {

	function runExport(){
		formData = new FormData();
		formData.append( 'action', 'export_users_data' );
		
		$.ajaxSetup({
			type:'POST',
			url:$('.progress').data('listener'),
			data:formData,
			processData: false,
    		contentType: false,
			beforeSend:function(){
				
			},
			complete:function(jqXHR, textStatus){
				
			},
			success:function(response){
				parsedResponse = $.parseJSON(response);
				running = parsedResponse.running;
				progress = parsedResponse.progress;
				$('.progress>div>div').css('width', progress);
				$('.progress>span').html(progress);
				//alert('Got this from the server: ' + response);
			},
		});
		$.ajax().done(function(){
			if(running)
				runExport();
			else{
				$('.let-finish').slideUp('slow', function(){
					$('.download').slideDown('slow');
				});
			}
		});
	}

    if($('.progress').length == 1){
		runExport();
	};

} );
