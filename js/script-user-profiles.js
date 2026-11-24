document.addEventListener("DOMContentLoaded", function () {	"use strict"; 
														   
// Raw Script: User Profiles
														   
	window.addEventListener("load", () => {

	// Real time profile search
		const sortBox = getObject("#sort-box");
		if (!sortBox) return;
		
		sortBox.addEventListener("change", function() {
			fetch(`https://${window.location.hostname}/wp-admin/admin-ajax.php`, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: new URLSearchParams({
					action: 	"update_meta",
					type: 		'user',
					key: 		'profile-sort',
					value: this.value
				})
			}).then(() => window.location.reload());
		});

	// Real-time profile search
		const searchBox = getObject("#search-box");
		if (!searchBox) return;

		searchBox.addEventListener("input", function() {
			const search = this.value.toLowerCase();
			getObjects("a.link-profiles").forEach(link => {
				const name = link.getAttribute('data-user').toLowerCase();
				link.classList.toggle('hide-profile', !name.includes(search));
			});
		});

	});	
}); 