(function($){
	$(document).ready(function(){
        if($.fn.colorpicker){
            $('.input-colorpicker').each(function() {
                var $this = $(this);
                $this.colorpicker({
                    'input': 'input[type=text]'
                });
                var $input = $(this).find('input[type=text]');
                $input.attr('data-value', $input.val());
                $input.on('change', function() {
                    if($input.val() !== $input.attr('data-value')) {
                        $input.attr('data-value', $input.val());
                    }
                }).on('focus', function() {
                    if ($input.val() == '') {
                        $this.colorpicker('show');
                    }
                });
            });
        }

        if($.fn.checkboxpicker){
            $('[data-control=checkbox]').checkboxpicker({ onClass: 'btn-info' })
        }

        if(typeof CKEDITOR !== "undefined") {
            $('.wysiwyg_editor').each(function(){
                CKEDITOR.replace($(this).attr('id'));
            });
        } else if($.fn.ckeditor) {
            $('.wysiwyg_editor').ckeditor({
                height: 300
            });
        } else if($.fn.summernote){
            $('.wysiwyg_editor').summernote();
        }
	});
})(jQuery);