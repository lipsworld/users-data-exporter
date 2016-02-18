
var running = true;
var progress = '0%';
var m_f_id = 1;



jQuery(document).ready( function($) {

	jQuery('.datepicker').datepicker({dateFormat:'yy-mm-dd', changeMonth: true, changeYear: true});

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
				//alert('Got this from the server: ' + response); //Debug
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

	$('#more-meta-filter').click(function(event) {
		m_f_id++;
		masterClone = null;
		masterClone = $('.meta-filter.master').clone();
		masterClone.find('input.m_f_id').val(m_f_id);
		masterClone.find('input.equal_to').attr('id', 'add_filter_meta_equal_'+m_f_id);
		masterClone.find('input.equal_to').attr('name', 'add_meta_filter_type_'+m_f_id);
		masterClone.find('label.equal_to').attr('for', 'add_filter_meta_equal_'+m_f_id);
		masterClone.find('input.between').attr('id', 'add_filter_meta_between_'+m_f_id);
		masterClone.find('input.between').attr('name', 'add_meta_filter_type_'+m_f_id);
		masterClone.find('label.between').attr('for', 'add_filter_meta_between_'+m_f_id);
		masterClone.removeClass('master hide').appendTo('.meta-filters>div.grouped');
		rebindClose();
	});

	$('#add_meta_filter').get(0).checked = false;
	$('#add_meta_filter').click(function(event) {
		if( $(this).is(':checked') && $('.meta-filter:not(.master)').length<1 ){
			$('#more-meta-filter').click();
		}
	});

	function rebindClose(){
		$('.close').unbind('click');
		$('.close').bind('click', function(event) {
			$(this).parent().remove();
		});
	}
	

	$('#export').click(function(event) {
		$('.meta-filter.master').remove();
		$('form.export-users-data').submit();
	});

} );
