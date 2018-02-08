define(function(require) {
	var $ = require('jquery');
        var elgg = require("elgg");
        
        var GRANULAR_ACCESS_OPTION = elgg.data.granular_access.GRANULAR_ACCESS_OPTION;
        var GRANULAR_ACCESS_OPTION_STR = GRANULAR_ACCESS_OPTION.toString();
        var GRANULAR_ACCESS_SEPARATOR = elgg.data.granular_access.GRANULAR_ACCESS_SEPARATOR;

	function replace_granular_access_by_custom_access() {
                // Get all access input fields
                var fields = $('.elgg-input-access').each(function(k, v) {
                        // Get text of the selected option
                        var option = $(this).find('option[selected="selected"]');

                        // Set 'Custom' as the selected value if the selected value
                        // matches the machine name of a custom access list
                        if ( option.text().indexOf('granular_access:') != -1) {
                                $(this).val(GRANULAR_ACCESS_OPTION_STR);
                                option.remove();
                        }
                });
        }
        
        replace_granular_access_by_custom_access();
        
        // lightbox use cases (widgets...)
        $(document).bind('cbox_complete', function(e) {
                replace_granular_access_by_custom_access();
        });
        
	/**
	 * Toggle visibility of the granular access input field
	 * depending on whether user has selected 'Custom' from
	 * the default access input field.
	 */
	$(document).on('change', 'select', function(e) {
                if ($(this).hasClass('elgg-input-access')) {
                        var val = $(this).attr('name');

                        var field = val.match(/^(.+)\[(.+)\]$/);
                        if(field && field.length > 2) {
                            val = field[1]+GRANULAR_ACCESS_SEPARATOR+field[2];
                        }
                        var granular = $('.granular-access-wrapper[data-name="' + val + '"]');

                        if ($(this).val() === GRANULAR_ACCESS_OPTION_STR) {
                                granular.slideDown();
                        } else {
                                granular.slideUp();
                        }
                }
	});
});
