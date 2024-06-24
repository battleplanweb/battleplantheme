document.addEventListener("DOMContentLoaded", function () {	"use strict"; 
	window.addEventListener("load", () => {
			
	// Build calendar
		if (document.body.classList.contains('slug-calendar')) {
			const calendar = getObject("#calendar");
			const prevButton = getObject("#prevButton");
			const nextButton = getObject("#nextButton");
			const currentButton = getObject("#currentButton");
			const today = new Date();
			let currentMonth = today.getMonth();
			let currentYear = today.getFullYear();
			let abbr_days = true;

			function generateCalendar(year, month) {
				const daysInMonth = new Date(year, month + 1, 0).getDate();
				const firstDayOfMonth = new Date(year, month, 1).getDay();
				const lastDayOfMonth = new Date(year, month, daysInMonth).getDay();
				const daysOfWeek = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];
				    
				const header = document.createElement("div");
				header.classList.add("header");
				header.textContent = new Date(year, month).toLocaleDateString("en-US", { month: "long", year: "numeric" });

				const weekDays = document.createElement("div");
				weekDays.classList.add("titles");
				daysOfWeek.forEach(function(day) {
					const weekday = document.createElement("div");
					weekday.setAttribute("id", "title-"+day);
					if ( abbr_days === true ) {						
						weekday.classList.add("title", "title-abbr");
						weekday.textContent = day.substring(0, 3);
					} else {
						weekday.classList.add("title", "title-full");
						weekday.innerHTML = '<span class="hide-1 hide-2">'+day+'</span><span class="hide-3 hide-4 hide-5">'+day.substring(0, 3)+'</span>';
					}
					weekDays.appendChild(weekday);
				});

				const days = document.createElement("div");
				days.classList.add("body");

				for (let i = 0; i < firstDayOfMonth; i++) {
					const day = document.createElement("div");
					day.classList.add("day", "day-empty");
					days.appendChild(day);
				}

				for (let i = 1; i <= daysInMonth; i++) {
					let event = ''; 
					const day = document.createElement("div");
					day.classList.add("day");
					day.setAttribute("id", "day-" + i);
					if ( i === today.getDate() && month === today.getMonth() && year === today.getFullYear() ) {
						day.classList.add("current-day");
					}
					for (let n = 0; n < eventLog.length; n++) {
						const element = eventLog[n];
						if ( element.date === i+", "+(month+1)+", "+year) {
							event += element.event;
							day.classList.add("event-day");
						}
					}

					day.innerHTML = '<div class="date">'+i+'</div>' + event;				
					days.appendChild(day);				
				}

				for (let i = lastDayOfMonth + 1; i <= 6; i++) {
					const day = document.createElement("div");
					day.classList.add("day");
					days.appendChild(day);
				}

				calendar.innerHTML = "";
				calendar.appendChild(header);
				calendar.appendChild(weekDays);
				calendar.appendChild(days);

				prevButton.textContent = new Date(year, month-1).toLocaleDateString("en-US", { month: "long", year: "numeric" });
				nextButton.textContent = new Date(year, month+1).toLocaleDateString("en-US", { month: "long", year: "numeric" });

				if ( today.getMonth() !== currentMonth || today.getFullYear() !== currentYear ) {	
					//currentButton.textContent = new Date(today.getFullYear(), today.getMonth()).toLocaleDateString("en-US", { month: "long", year: "numeric" });
					currentButton.textContent = "Today";
            		currentButton.style.opacity = `1`;
				} else {
            		currentButton.style.opacity = `0`;
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
		
		if (document.body.classList.contains('slug-event') || document.body.classList.contains('slug-calendar')) {
		
	// Handle the "expired events" toggle checkbox	
			const expiredButton = getObject('a.show-expired-btn');
			const expiredEvents = getObjects('.event-expired'); 

			if (getCookie('ecal-show-exp') === 'true') {
				if (expiredButton) {
					expiredButton.textContent = 'Hide Past Events';
				}

				if (expiredEvents) {
					animateDiv(expiredEvents, 'fadeIn');
				}
			}

			if ( expiredEvents.length < 1 ) {
				expiredButton.classList.add('disabled');
			}

			expiredButton.addEventListener('click', function() {
				var showExpired = getCookie('ecal-show-exp');
				if (showExpired === 'false') {
					this.textContent = 'Hide Past Events';
					animateDiv(expiredEvents, 'fadeIn');
					setCookie('ecal-show-exp', 'true', 365);
				} else {
					animateDiv(expiredEvents, 'fadeOut');
					this.textContent = 'Show Past Events';
					setCookie('ecal-show-exp', 'false', 365);
				}
			});
		}
	});	
}); 