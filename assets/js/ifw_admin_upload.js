jQuery( function( $ ) {
	
	jQuery('#upload_logo_button').click(function() { 
		formfield = jQuery('#upload_logo').attr('name');
		tb_show('', 'media-upload.php?type=image&amp;TB_iframe=true');
		return false;	
	});
	
	window.send_to_editor = function(html){
		var imgurl = jQuery('img',html).attr('src');
		jQuery('#upload_logo').val( imgurl );
		jQuery('#upload_logo_preview').attr('src', imgurl);
		tb_remove();
	};
});
