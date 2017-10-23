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
	var _cancelUrl = "";
	var _windowState = 0;

	return {
		init: function (epayArgsJson, cancelUrl, windowState) {
			_epayArgsJson = epayArgsJson;
			_cancelUrl = cancelUrl;
			_windowState = windowState;
		},
		getJsonData: function() {
			return _epayArgsJson;
		},
		getCancelUrl: function() {
			return _cancelUrl;
		},
		getWindowState: function() {
			return _windowState;
		}
	}
}());

var isPaymentWindowReady = false;
var timerOpenWindow;

function PaymentWindowReady() {
	paymentwindow = new PaymentWindow(BamboraOnlineClassicPaymentWindow.getJsonData());
	if (BamboraOnlineClassicPaymentWindow.getWindowState() === 1) {
		paymentwindow.on('close',
			function() {
				window.location.href = BamboraOnlineClassicPaymentWindow.getCancelUrl();
			});
	}
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
