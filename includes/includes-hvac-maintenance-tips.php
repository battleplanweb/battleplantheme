<?php
/* Battle Plan Web Design - HVAC Maintenance Tips */

$printPage = '
	<h1>Tips For Maintaining Your System</h1>

	<p>The life of your heating and air conditioning system depends on the service and care you give it. Proper care assures good performance. Lack of care can damage the unit (and invalidate your warranty) - causing you a needless expense.</p>

	<p>The operation and care of your air conditioning or heating system is simple and easy. In fact, it\'s less of a chore than maintaining your vehicle! There are a few things you should do, and a few you should NOT do, to result in better, longer, and more reliable service from heating and air conditioning equipment.</p>

	<h2>Things you SHOULD Do:</h2>
	<ol>
		<li>Do use filters, and check them every 3 to 4 weeks. Make sure they are clean.</li>
		<li>Do keep windows and doors closed (and pull drapes or shades on windows exposed to the sun). The less heat and moisture there is to overcome - the lower your operating costs become.</li>
		<li>Do turn on kitchen exhaust fans when cooking (one burner on high requires 1 ton of cooling to offset it.) Vent your clothes drier outside - up to 3 gallons of water come out of a single load.</li>
		<li>Do your "heat and moisture" work in the morning or evening as much as possible. Then your system can offset the effects of washing, drying, mopping, etc. before the afternoon heat arrives.</li>
		<li>Do turn on the bathroom exhaust fan (or open window slightly) during showers. Use plastic shower curtains instead of moisture holding fabric curtains.</li>
		<li>Do keep attic ventilators open. Attic space can become an oven unless there is good ventilation.</li>
		<li>Do become familiar with the operating and maintenance requirements of your system.</li>
	</ol>

	<h2>Things you SHOULDN\'T Do:</h2>
	<ol>
		<li>Don\'t be a "thermostat jiggler." Set it at the desired temperature and forget it. Frequent changing upsets humidity control and may increase operating costs.</li>
		<li>Don\'t set your thermostat too low. Most people find 76 to 78 degrees to be ideal. The greater the difference between outdoor and indoor temperatures - the greater the operating cost.</li>
		<li>Don\'t turn off the system just because you\'ll be away for the day. Heat and moisture build up in the house. It takes quite a while to restore comfort - but it costs relatively little to maintain it.</li>
		<li>Don\'t be concerned if your unit operates after sundown - heat stored in the roof and walls is still there. Also, on exceptionally hot days, expect your unit to work more.</li>
		<li>Don\'t open windows after dark. Night air may seem cool but it is also moisture-laden. This increases the work your system must do the next day.</li>
		<li>Don\'t block registers with furniture. Don\'t let shrubs, vines or fences block air intake and discharge on the condenser unit outside. Don\'t put a lamp, TV or radio too near your thermostat.</li>
	</ol>

	<p>If you are experiencing problems with your heating and cooling equipment, check our handy <a href="/symptom-checker/">Symptom Checker</a> to determine if it\'s an easy fix, or if you need a service call.</p>';
 
if ( $type == "teaser" ) :
	return do_shortcode('
		 [txt size="100"]
		 <h2>Maintenance Tips</h2>

		 <img src="/wp-content/themes/battleplantheme/common/hvac-generic/maintenance-tips-teaser.jpg" loading="lazy" alt="Above view of equipment being worked on." class="alignright size-quarter-s" width="338" height="338" />

		 <p>The life of your heating & cooling system depends on the service and care you give it. Proper care assures good performance.</p>
		 <p>We have put together a set of simple "Dos and Don\'ts" that every homeowner should follow.</p>

		 [btn link="/maintenance-tips/" ada="about maintaining your HVAC system"]Learn More[/btn]
		[/txt]
	');	

else : 
	return $printPage;	
endif;
?>