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

var BamboraOnlineClassicPaymentWindow = BamboraOnlineClassicPaymentWindow ||
(function() {
	var _epayArgsJson = {};
	return {
		init: function (epayArgsJson) {
			_epayArgsJson = epayArgsJson;
		},
		getJsonData: function() {
			return _epayArgsJson;
		},
	}
}());

var isPaymentWindowReady = false;
var timerOpenWindow;

function PaymentWindowReady() {
	paymentwindow = new PaymentWindow(BamboraOnlineClassicPaymentWindow.getJsonData());

	isPaymentWindowReady = true;
}
function openPaymentWindow() {
	if (isPaymentWindowReady) {
		clearInterval(timerOpenWindow);
		paymentwindow.open();
	}
}
document.onreadystatechange = function () {
	if (document.readyState === "complete") {
		timerOpenWindow = setInterval("openPaymentWindow()", 500);
	}
}
