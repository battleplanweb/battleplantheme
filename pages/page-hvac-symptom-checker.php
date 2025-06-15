<?php
/* Battle Plan Web Design - HVAC Symptom Checker */

$printPage = '
	<h1>Symptom Checker</h1>

	<p>System isn’t working? No matter what the problem, we’re here to help. Before you call for service, try these simple tips for troubleshooting your heating and cooling system.</p>

	<p><strong>Is it getting power?</strong> Check your fuses or circuit breakers, and remember that if your home\'s power is out or disconnected, your system may not work.</p>

	<p><strong>Is the thermostat set correctly?</strong> Make sure your thermostat has power, that it is set to cooling or heating mode and not "off", and that it is set to the correct setting and temperature.</p>

	<p>Still not working?  For more tips, click the symptom below that best fits your problem:</p>


	[accordion title="No Heat / Insufficient Heat"]
	 <ul>
	  <li>Replace the batteries in your thermostat.</li>  
	  <li>Check to see if your thermostat is on and set to the correct temperature. If your thermostat is turned off or set incorrectly, turn on and/or reset thermostat.</li>  
	  <li>Check the air filters in each of your system components. Dirty filters can cause severe problems with your unit.  If any of your filters are dirty, consult your manual to clean or replace them.</li>
	  <li>Check the doors and windows in your home. Close any open doors or windows as cool air may be escaping through them.</li>  
	  <li>Check your home\'s circuit breakers or fuse box. If you have an open circuit breaker or burned-out fuse, switch on the circuit or replace the fuse.</li>
	  <li>Remove any snow drifts resting against your outdoor unit.</li>
	  <li>Do you have a new or newly remodeled home? Was any work done on your fuel or electricity lines recently? Check to see if your gas or electricity has been turned off. If this is the case, having it turned back on may solve the problem.</li>
	 </ul>
	[/accordion]


	[accordion title="No Cooling / Insufficient Cooling"]
	 <ul>
	  <li>Replace the batteries in your thermostat.</li>  
	  <li>Check to see if your thermostat is on and set to the correct temperature. If your thermostat is turned off or set incorrectly, turn it on and/or reset thermostat.</li>
	  <li>Check the air filters in each of your system components. Dirty filters can cause severe problems with your unit.  If any of your filters are dirty, consult your manual to clean or replace them.</li>
	  <li>Check the doors and windows in your home. Close any open doors or windows as cool air may be escaping through them.</li>  
	  <li>Check your home\'s circuit breakers or fuse box. If you have an open circuit breaker or burned-out fuse, switch on the circuit or replace the fuse.</li>
	  <li>Remove any leaves or debris that might be restricting air flow to your outdoor unit.</li>
	  <li>Do you have a new or newly remodeled home? Was any work done on your fuel or electricity lines recently? Check to see if your gas or electricity has been turned off. If this is the case, having it turned back on may solve the problem.</li>
	 </ul>
	[/accordion]


	[accordion title="No Air Flow"]
	 <ul>
	  <li>Check around your outdoor unit. If there are any leaves, hedges or property walls butting up against it, your system may have frozen up due to a dirty coil. Make sure your outdoor unit has 6 inches of clearance all around it.</li>
	  <li>Check the air filters in each of your system components. Dirty filters can cause severe problems with your unit.  If any of your filters are dirty, consult your manual to clean or replace them.</li>
	  <li>Check to see if there is any air coming through your vent. Your indoor blower may not be operating. If this is the case, you should contact [get-biz info="name"] at [get-biz info="phone-link"].</li>
	 </ul>
	[/accordion]


	[accordion title="Stale, Stuffy Air"]
	 <ul>
	  <li>If you have a whole-home air cleaner or air exchanger, make sure it is switched on and its filter is clean.</li>
	 </ul>
	[/accordion]


	[accordion title="Too Dry or Too Much Moisture In The Air"]
	 <ul>
	  <li>Check to make sure your humidifier is switched on. Many times, homeowners turn off the humidifier at the end of the previous heating season and forget to turn it back on when needed.</li>
	  <li>Make sure your humidifier’s damper or water valve is open. If it’s closed, consult your manual to open or unclog.</li>
	  <li>Check your humidifier setting and adjust the indoor relative humidity settings to bring greater comfort to your home.</li>
	 </ul>
	[/accordion]


	[accordion title="Noisy Air Vents"]
	 <ul>
	  <li>A high pitched sound often, although not always, indicates a lack of return air. Make sure your return and supply vents are open and free of any blockages including furniture.</li>
	  <li>Other noises (e.g., rattling, humming, thumping or scraping sounds) could be a sign of undersized or flimsy duct work, clogged filter or wear and tear on your system’s internal components. If you hear an unusual sound, call [get-biz info="name"] at [get-biz info="phone-link"] for service.</li>
	 </ul>
	[/accordion]
	';
 
return $printPage;	
?>