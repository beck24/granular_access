//<script>
	
elgg.provide('elgg.granular_access');

elgg.granular_access.init = function() {
	$('select').live('change', function(e) {
		if ($(this).val() === 'granular') {
			elgg.granular_access.reveal_granular($(this));
		}
		else {
			elgg.granular_access.hide_granular($(this));
		}
	});
};

// reveal the granular access for the select
elgg.granular_access.reveal_granular = function(select) {
	var val = select.attr('name');
	
	$('.granular-access-wrapper[data-name="'+val+'"]').slideDown();
}


// hide the granular access for select
elgg.granular_access.hide_granular = function(select) {
	var val = select.attr('name');
	$('.granular-access-wrapper[data-name="'+val+'"]').slideUp();
}

elgg.register_hook_handler('init', 'system', elgg.granular_access.init);