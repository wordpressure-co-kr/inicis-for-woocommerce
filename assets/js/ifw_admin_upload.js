jQuery(function(a){jQuery("#upload_logo_button").click(function(){formfield=jQuery("#upload_logo").attr("name");tb_show("","media-upload.php?type=image&amp;TB_iframe=true");return false});window.send_to_editor=function(c){var b=jQuery("img",c).attr("src");jQuery("#upload_logo").val(b);jQuery("#upload_logo_preview").attr("src",b);tb_remove()}});