jQuery(document).ready(function () {
	jQuery('.wp_xemhuongnha .xem').on('click', function () {
		var this_form = jQuery(this).closest('.wp_xemhuongnha');
		var namsinh = this_form.find('select.ns').val();
		var gioitinh = this_form.find('select.gt').val();
		var huongnha = this_form.find('select.hn').val();
		this_form.addClass('loading');
		wppt_ajax_get_huongnha(namsinh, gioitinh, huongnha);
	});
	jQuery('.wp_tuoixaydung .xem').on('click', function () {
		var this_form = jQuery(this).closest('.wp_tuoixaydung');
		var namsinh = this_form.find('select.ns').val();
		var namxay = this_form.find('select.nx').val();
		this_form.addClass('loading');
		wppt_ajax_get_tuoixaydung(namsinh, namxay);
	});
});

function wppt_ajax_get_huongnha(namsinh, gioitinh, huongnha) {
	data = {
		action        : 'wppt_ajax_huongnha',
		namsinh       : namsinh,
		gioitinh      : gioitinh,
		huongnha      : huongnha,
		wppt_nonce: wppt_vars.wppt_nonce
	};
	jQuery.post(wppt_vars.ajax_url, data, function (response) {
		jQuery('.wp_xemhuongnha').removeClass('loading');
		jQuery.featherlight({html: response});
	});
}

function wppt_ajax_get_tuoixaydung(namsinh, namxay) {
	data = {
		action        : 'wppt_ajax_tuoixaydung',
		namsinh       : namsinh,
		namxay        : namxay,
		wppt_nonce: wppt_vars.wppt_nonce
	};
	jQuery.post(wppt_vars.ajax_url, data, function (response) {
		jQuery('.wp_tuoixaydung').removeClass('loading');
		jQuery.featherlight({html: response});
	});
}