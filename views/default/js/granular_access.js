define(function(require) {
	var $ = require('jquery');

	// Get all access input fields
	var fields = $('.elgg-input-access').each(function(k, v) {
		// Get text of the selected option
		var option = $(this).find('option[selected="selected"]').text();

		// Set 'Custom' as the selected value if the selected value
		// matches the machine name of a custom access list
		if (option.indexOf('granular_access:') != -1) {
			$(this).val('granular');
		}
	});

	/**
	 * Toggle visibility of the granular access input field
	 * depending on whether user has selected 'Custom' from
	 * the default access input field.
	 */
	$(document).on('change', 'select', function(e) {
		var val = $(this).attr('name');

		var granular = $('.granular-access-wrapper[data-name="' + val + '"]');

		if ($(this).val() === 'granular') {
			granular.slideDown();
		} else {
			granular.slideUp();
		}
	});
});
