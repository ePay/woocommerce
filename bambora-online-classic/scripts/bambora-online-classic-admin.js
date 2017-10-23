/**
 * Copyright (c) 2017. All rights reserved ePay A/S (a Bambora Company).
 *
 * This program is free software. You are allowed to use the software but NOT allowed to modify the software.
 * It is also not legal to do any changes to the software and distribute it in your own name / brand.
 *
 * All use of the payment modules happens at your own risk. We offer a free test account that you can use to test the module.
 *
 * @author    Bambora Online
 * @copyright Bambora Online (https://bambora.com) (http://www.epay.dk)
 * @license   Bambora Online
 */

jQuery( document ).ready(function () {
	jQuery( ".boclassic-amount" ).keydown(function (e) {
		var digit = String.fromCharCode( e.which || e.keyCode );
		if (e.which !== 8 && e.which !== 46 && ! (e.which >= 37 && e.which <= 40) && e.which !== 110 && e.which !== 188
			&& e.which !== 190 && e.which !== 35 && e.which !== 36 && ! (e.which >= 96 && e.which <= 106)) {
			var reg = new RegExp( /^(?:\d+(?:,\d{0,3})*(?:\.\d{0,2})?|\d+(?:\.\d{0,3})*(?:,\d{0,2})?)$/ );

			return reg.test( digit );
		}
	});

	jQuery( "#boclassic-format-error" )
		.click(function () {
			if (jQuery( "#boclassic-format-error" ).css( "display" ) !== "none") {
				jQuery( "#boclassic-format-error" ).toggle();
			}
		});

	jQuery( "#boclassic-capture-submit" )
		.click(function (e) {
			e.preventDefault();
			return boclassicAction( 'capture' );
		});
	jQuery( "#boclassic-refund-submit" )
		.click(function(e) {
			e.preventDefault();
			return boclassicAction( 'refund' );
		});
	jQuery( "#boclassic-delete-submit" )
		.click(function(e) {
			e.preventDefault();
			return boclassicAction( 'delete' );
		});

	function boclassicAction(action) {
		var inputField = jQuery( "#boclassic-" + action + "-amount" );
		var reg = new RegExp( /^(?:[\d]+([,.]?[\d]{0,3}))$/ );
		var amount = inputField.val();
		if ((inputField.length > 0 && ! reg.test( amount )) && action != 'delete') {
			jQuery( "#boclassic-format-error" ).toggle();
			return false;
		}
		var messagDialogText = jQuery( "#boclassic-" + action + "-message" ).val();

		var confirmResult = confirm( messagDialogText );
		if (confirmResult === false) {
			return false;
		}
		var currency = jQuery( "#boclassic-currency" ).val();
		var params = "&boclassicaction=" + action + "&amount=" + amount + "&currency=" + currency;
		var url = window.location.href + params;

		window.location.href = url;
		return false;
	}
});
