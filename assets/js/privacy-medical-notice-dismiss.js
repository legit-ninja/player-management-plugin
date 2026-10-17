(function ($) {
	$(function () {
		$(document).on(
			'click',
			'.intersoccer-privacy-medical-notice .notice-dismiss',
			function () {
				if (
					typeof intersoccerPrivacyMedicalNotice === 'undefined' ||
					!intersoccerPrivacyMedicalNotice.ajaxUrl
				) {
					return;
				}

				$.post(intersoccerPrivacyMedicalNotice.ajaxUrl, {
					action: intersoccerPrivacyMedicalNotice.action,
					nonce: intersoccerPrivacyMedicalNotice.nonce,
				});
			}
		);
	});
})(jQuery);
