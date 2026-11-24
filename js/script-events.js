document.addEventListener("DOMContentLoaded", function () {	"use strict";

// Raw Script: Events

	window.addEventListener("load", () => {

	// Build calendar
		const calendar = document.getElementById("calendar");
		if (!calendar || typeof eventLog === "undefined") return;

		const prevButton = document.getElementById("prevButton");
		const nextButton = document.getElementById("nextButton");
		const currentButton = document.getElementById("currentButton");
		const monthTitle = document.getElementById("calendar-month");
		const today = new Date();
		let currentMonth = today.getMonth();
		let currentYear = today.getFullYear();
		let selectedDay = 0;

		// eventLog dates are "j, n, Y"; group them once by that key.
		const byDate = {};
		eventLog.forEach(function (entry) {
			(byDate[entry.date] = byDate[entry.date] || []).push(entry.event);
		});
		const keyFor = (y, m, d) => d + ", " + (m + 1) + ", " + y;
		const daysOfWeek = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];

		function cell(y, m, d, outside) {
			const day = document.createElement("div");
			const isToday = d === today.getDate() && m === today.getMonth() && y === today.getFullYear();
			const isPast = new Date(y, m, d) < new Date(today.getFullYear(), today.getMonth(), today.getDate());
			day.className = "day" + (outside ? " day-empty day-outside" : "") + (isToday ? " current-day" : "") + (isPast ? " day-past" : "");
			if (outside) {
				day.innerHTML = '<div class="date">' + d + '</div>';
				return day;
			}
			day.id = "day-" + d;
			day.dataset.day = d;
			const events = byDate[keyFor(y, m, d)] || [];
			if (events.length) {
				day.classList.add("event-day");
				day.setAttribute("tabindex", "0");
				day.setAttribute("role", "button");
				day.setAttribute("aria-label", new Date(y, m, d).toLocaleDateString("en-US", { weekday: "long", month: "long", day: "numeric" }) + ", " + events.length + " event" + (events.length === 1 ? "" : "s"));
			}
			day.innerHTML = '<div class="date">' + d + '</div><div class="events">' + events.join("") + '</div>';
			return day;
		}

		// Phones: the grid shows dots; the tapped day's events list under it.
		function drawAgenda() {
			const agenda = calendar.querySelector(".calendar-agenda");
			if (!agenda) return;
			calendar.querySelectorAll(".day.is-selected").forEach(el => el.classList.remove("is-selected"));
			const day = selectedDay ? calendar.querySelector('.body .day[data-day="' + selectedDay + '"]') : null;
			if (!day) {
				agenda.innerHTML = '<p class="calendar-agenda-empty">No events this month.</p>';
				return;
			}
			day.classList.add("is-selected");
			const label = new Date(currentYear, currentMonth, selectedDay).toLocaleDateString("en-US", { weekday: "long", month: "long", day: "numeric" });
			agenda.innerHTML = '<h3 class="calendar-agenda-title">' + label + '</h3>' + day.querySelector(".events").innerHTML;
		}

		function generateCalendar(year, month) {
			const daysInMonth = new Date(year, month + 1, 0).getDate();
			const firstDayOfMonth = new Date(year, month, 1).getDay();
			const prevDays = new Date(year, month, 0).getDate();

			monthTitle.textContent = new Date(year, month).toLocaleDateString("en-US", { month: "long", year: "numeric" });

			const weekDays = document.createElement("div");
			weekDays.className = "titles";
			daysOfWeek.forEach(function (name) {
				const weekday = document.createElement("div");
				weekday.id = "title-" + name;
				weekday.className = "title";
				weekday.innerHTML = '<abbr title="' + name + '">' + name.substring(0, 3) + '</abbr>';
				weekDays.appendChild(weekday);
			});

			// Whole weeks: the neighbouring months' days fill the first and last rows, muted.
			const days = document.createElement("div");
			days.className = "body";
			for (let i = firstDayOfMonth - 1; i >= 0; i--) days.appendChild(cell(year, month - 1, prevDays - i, true));
			for (let i = 1; i <= daysInMonth; i++) days.appendChild(cell(year, month, i, false));
			const trailing = (7 - (firstDayOfMonth + daysInMonth) % 7) % 7;
			for (let i = 1; i <= trailing; i++) days.appendChild(cell(year, month + 1, i, true));

			const agenda = document.createElement("div");
			agenda.className = "calendar-agenda";
			agenda.setAttribute("aria-live", "polite");

			calendar.innerHTML = "";
			calendar.appendChild(weekDays);
			calendar.appendChild(days);
			calendar.appendChild(agenda);

			// The day the agenda opens on: today if it has events, else the next day that does, else the first.
			const eventDays = Array.from(days.querySelectorAll(".event-day")).map(el => parseInt(el.dataset.day, 10));
			const isThisMonth = year === today.getFullYear() && month === today.getMonth();
			selectedDay = eventDays.find(d => !isThisMonth || d >= today.getDate()) || eventDays[0] || 0;
			drawAgenda();

			currentButton.hidden = isThisMonth;

			// Booking (script-booking.js) refreshes live seat counts on every month drawn.
			document.dispatchEvent(new CustomEvent("bp:calendar-rendered", { detail: { calendar: calendar } }));
		}

		function shift(by) {
			currentMonth += by;
			if (currentMonth < 0) { currentMonth = 11; currentYear--; }
			if (currentMonth > 11) { currentMonth = 0; currentYear++; }
			generateCalendar(currentYear, currentMonth);
		}

		generateCalendar(currentYear, currentMonth);

		prevButton.addEventListener("click", () => shift(-1));
		nextButton.addEventListener("click", () => shift(1));
		currentButton.addEventListener("click", function () {
			currentMonth = today.getMonth();
			currentYear = today.getFullYear();
			generateCalendar(currentYear, currentMonth);
		});

		// Picking a day (tap, or Enter/Space on a focused day) shows its events in the agenda.
		const pick = function (e) {
			if (e.target.closest("a, button")) return;
			const day = e.target.closest(".body .event-day");
			if (!day) return;
			selectedDay = parseInt(day.dataset.day, 10);
			drawAgenda();
		};
		calendar.addEventListener("click", pick);
		calendar.addEventListener("keydown", function (e) {
			if (e.key !== "Enter" && e.key !== " ") return;
			if (!e.target.matches(".body .event-day")) return;
			e.preventDefault();
			pick(e);
		});

		// Swipe left/right on the grid to change month.
		let touchX = null, touchY = null;
		calendar.addEventListener("touchstart", function (e) {
			touchX = e.touches[0].clientX;
			touchY = e.touches[0].clientY;
		}, { passive: true });
		calendar.addEventListener("touchend", function (e) {
			if (touchX === null || !e.target.closest(".body")) { touchX = null; return; }
			const dx = e.changedTouches[0].clientX - touchX;
			const dy = e.changedTouches[0].clientY - touchY;
			touchX = null;
			if (Math.abs(dx) > 60 && Math.abs(dx) > Math.abs(dy) * 1.5) shift(dx < 0 ? 1 : -1);
		}, { passive: true });
	});
});
