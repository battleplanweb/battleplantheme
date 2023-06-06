document.addEventListener("DOMContentLoaded", function () {	"use strict"; (function($) {

	$(window).on("load", function() {		
				
	// Build calendar
		if ( $('body').hasClass('slug-calendar') ) {		
			var calendar = document.getElementById("calendar"), prevButton = document.getElementById("prevButton"), nextButton = document.getElementById("nextButton"), currentButton = document.getElementById("currentButton"), today = new Date(), currentMonth = today.getMonth(), currentYear = today.getFullYear();		

			function generateCalendar(year, month) {
				var daysInMonth = new Date(year, month + 1, 0).getDate(), firstDayOfMonth = new Date(year, month, 1).getDay(), lastDayOfMonth = new Date(year, month, daysInMonth).getDay(), daysOfWeek = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];
				    
				var header = document.createElement("div");
				header.classList.add("header");
				header.textContent = new Date(year, month).toLocaleDateString("en-US", { month: "long", year: "numeric" });

				var weekDays = document.createElement("div");
				weekDays.classList.add("titles");
				daysOfWeek.forEach(function(day) {
					var weekday = document.createElement("div");
					weekday.setAttribute("id", "title-"+day);
					if ( abbr_days == 'true' ) {						
						weekday.classList.add("title", "title-abbr");
						weekday.textContent = day.substring(0, 3);
					} else {
						weekday.classList.add("title", "title-full");
						weekday.innerHTML = '<span class="hide-1 hide-2">'+day+'</span><span class="hide-3 hide-4 hide-5">'+day.substring(0, 3)+'</span>';
					}
					weekDays.appendChild(weekday);
				});

				var i, j, day, days = document.createElement("div");
				days.classList.add("body");

				for (i = 0; i < firstDayOfMonth; i++) {
					day = document.createElement("div");
					day.classList.add("day", "day-empty");
					days.appendChild(day);
				}

				for (i = 1; i <= daysInMonth; i++) {
					var event = ''; 
					day = document.createElement("div");
					day.classList.add("day");
					day.setAttribute("id", "day-" + i);
					if ( i === today.getDate() && month === today.getMonth() && year === today.getFullYear() ) {
						day.classList.add("current-day");
					}
					for (var n = 0; n < eventLog.length; n++) {
						var element = eventLog[n];
						if ( element.date == i+", "+(month+1)+", "+year) {
							event += element.event;
							day.classList.add("event-day");
						}
					}

					day.innerHTML = '<div class="date">'+i+'</div>' + event;				
					days.appendChild(day);				
				}

				for (i = lastDayOfMonth + 1; i <= 6; i++) {
					day = document.createElement("div");
					day.classList.add("day");
					days.appendChild(day);
				}

				calendar.innerHTML = "";
				calendar.appendChild(header);
				calendar.appendChild(weekDays);
				calendar.appendChild(days);

				prevButton.textContent = new Date(year, month-1).toLocaleDateString("en-US", { month: "long", year: "numeric" });
				nextButton.textContent = new Date(year, month+1).toLocaleDateString("en-US", { month: "long", year: "numeric" });

				if ( today.getMonth() != currentMonth || today.getFullYear() != currentYear ) {				
					currentButton.textContent = 'Return to ' + new Date(today.getFullYear(), today.getMonth()).toLocaleDateString("en-US", { month: "long", year: "numeric" });
					$('#currentButton').fadeIn();
				} else {
					$('#currentButton').fadeOut();
					currentButton.textContent = '';
				}
			}
			generateCalendar(today.getFullYear(), today.getMonth());


	// Build calendar buttons
			prevButton.addEventListener("click", function() {
				currentMonth--;
				if (currentMonth < 0) {
					currentMonth = 11;
					currentYear--;
				}
				generateCalendar(currentYear, currentMonth);
			});

			nextButton.addEventListener("click", function() {
				currentMonth++;
				if (currentMonth > 11) {
					currentMonth = 0;
					currentYear++;
				}
				generateCalendar(currentYear, currentMonth);
			});

			currentButton.addEventListener("click", function() {
				currentMonth = today.getMonth();
				currentYear = today.getFullYear();
				generateCalendar(currentYear, currentMonth);
			});

		}
		
	// Handle the "expired events" toggle checkbox	
		var expiredEvents = $('.col.expired'); 

		if ( getCookie('ecal-show-exp') == 'true' ) {
			$('a.show-expired-btn').text('Hide Past Events');
			expiredEvents.fadeIn();
		}
		
		if ( expiredEvents.length < 1 ) {
			$('a.show-expired-btn').addClass('disabled');
		}
		
		$('a.show-expired-btn').click(function() {
			 var thisBtn = $(this), showExpired = getCookie('ecal-show-exp');
			 if ( showExpired == 'false' ) {
				 thisBtn.text('Hide Past Events');
				 expiredEvents.fadeIn();
				 setCookie('ecal-show-exp', 'true', 365);
			 } else {
				 expiredEvents.fadeOut();
				 thisBtn.text('Show Past Events');
				 setCookie('ecal-show-exp', 'false', 365);
			 }
		 });
	});
	
})(jQuery); }); 