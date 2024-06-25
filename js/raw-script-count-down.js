document.addEventListener("DOMContentLoaded", function () {	"use strict";
														   
// Raw Script: Count Down	
														   
	window.addEventListener("load", () => {
	
		const countdownObj = getObject("#countdown");
		const countdownDays = getObject("#bp_countdown_days", countdownObj);
		const countdownHours = getObject("#bp_countdown_hours", countdownObj);
		const countdownMin = getObject("#bp_countdown_minutes", countdownObj);
		const countdownSec = getObject("#bp_countdown_seconds", countdownObj);
		const separator = countdownDays.dataset.separator;
		const year = countdownDays.dataset.year;
		const month = countdownDays.dataset.month;
		const day = countdownDays.dataset.day;
		const hour = countdownDays.dataset.hour - countdownDays.dataset.offset;
		const minute = countdownDays.dataset.minute;
		const second = countdownDays.dataset.second;
		const countDownDate = new Date(year, month - 1, day, hour, minute, 0).getTime();

		const x = setInterval(function() {
			const now = new Date().getTime();
			const distance = countDownDate - now;
			const days = Math.floor(distance / (1000 * 60 * 60 * 24));
			const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
			const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
			const seconds = Math.floor((distance % (1000 * 60)) / 1000);

			// Update HTML using innerHTML and direct element selection
			countdownDays.innerHTML = "<span class='bp_countdown_number'>" + days + "</span><span class='bp_countdown_label'> day" + (days !== 1 ? "s" : "") + separator + "</span>";
			countdownHours.innerHTML = "<span class='bp_countdown_number'>" + hours + "</span><span class='bp_countdown_label'> hour" + (hours !== 1 ? "s" : "") + separator + "</span>";
			countdownMin.innerHTML = "<span class='bp_countdown_number'>" + minutes + "</span><span class='bp_countdown_label'> minute" + (minutes !== 1 ? "s" : "") + separator + "</span>";
			countdownSec.innerHTML = "<span class='bp_countdown_number'>" + seconds + "</span><span class='bp_countdown_label'> second" + (seconds !== 1 ? "s" : "") + "</span>";

			// Handle expiration
			if (distance < 0) {
				clearInterval(x);
				countdownObj.innerHTML = "EXPIRED";
			}
		}, 1000);
	});	
});