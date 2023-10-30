document.addEventListener("DOMContentLoaded", function () {	"use strict"; (function($) {

	$(window).on("load", function() {
		
		var separator = $("#bp_countdown_days").data("separator");
		var year = $("#bp_countdown_days").data("year");
		var month = $("#bp_countdown_days").data("month");
		var day = $("#bp_countdown_days").data("day");
		var hour = $("#bp_countdown_days").data("hour") - $("#bp_countdown_days").data("offset");
		var minute = $("#bp_countdown_days").data("minute");
		var second = $("#bp_countdown_days").data("second");
		//var countDownDate = new Date(month + ' ' + day +', ' + year + ' ' + hour + ':' + minute + ':00' ).getTime();
		
		var countDownDate = new Date( year, month - 1, day, hour, minute, 0 ).getTime();
		
		var x = setInterval(function() {
			var now = new Date().getTime();
			var distance = countDownDate - now;
			var days = Math.floor(distance / (1000 * 60 * 60 * 24));
			var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
			var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
			var seconds = Math.floor((distance % (1000 * 60)) / 1000);
			$("#bp_countdown_days").html("<span class='bp_countdown_number'>" + days + "</span><span class='bp_countdown_label'> day" + (days !== 1 ? "s" : "") + separator + "</span>");
			$("#bp_countdown_hours").html("<span class='bp_countdown_number'>" + hours + "</span><span class='bp_countdown_label'> hour" + (hours !== 1 ? "s" : "") + separator + "</span>");
			$("#bp_countdown_minutes").html("<span class='bp_countdown_number'>" + minutes + "</span><span class='bp_countdown_label'> minute" + (minutes !== 1 ? "s" : "") + separator + "</span>");
			$("#bp_countdown_seconds").html("<span class='bp_countdown_number'>" + seconds + "</span><span class='bp_countdown_label'> second" + (seconds !== 1 ? "s" : "") + "</span>");

			if (distance < 0) {
				clearInterval(x);
				$("#countdown").html("EXPIRED");
			}
		}, 1000);
	});
	
})(jQuery); });