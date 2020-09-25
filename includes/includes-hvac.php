<?php
/* Battle Plan Web Design HVAC Includes
 
/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Product Overview
# American Standard Customer Care
# HVAC FAQ
# HVAC Symptom Checker
# HVAC Maintenance Tips
# HVAC Tip Of The Month

--------------------------------------------------------------*/



/*--------------------------------------------------------------
# Product Overview
--------------------------------------------------------------*/
add_shortcode( 'product-overview', 'battleplan_product_overview' );
function battleplan_product_overview( $atts, $content = null ) {
	$a = shortcode_atts( array( 'type'=>'', ), $atts );
	$type = esc_attr($a['type']);
	
	if ( $type == "american standard air conditioners" ) :
		$title 		= "Air Conditioners";
		$excerpt	= "<p>Keep cool and comfortable when it heats up outside with an air conditioner you can rely on. Our home air conditioners and central air conditioners cool the hottest days with reliability and efficiency you can count on year after year.</p>";
		$link 		= "/product-type/air-conditioners/";
		$pic 		= "/wp-content/uploads/American-Standard-01-320x320.jpg";
		$alt 		= "American Standard Air Conditioners";
	endif;
	
	if ( $type == "american standard air handlers" ) :
		$title 		= "Air Handlers";
		$excerpt	= "<p>Air handlers make sure newly cooled or heated air gets to every corner in your house, even the tight spaces. Team up an air handler unit with an air conditioner or heat pump to circulate cool air in the summer and warm air in the winter.</p>";
		$link 		= "/product-type/air-handlers/";
		$pic 		= "/wp-content/uploads/American-Standard-31-320x320.jpg";
		$alt 		= "American Standard Air Handlers";
	endif;
	
	if ( $type == "american standard heat pumps" ) :
		$title 		= "Heat Pumps";
		$excerpt	= "<p>A heater in the winter and an air conditioner in the summer, American Standard's high-efficiency heat pumps keep the temperature just how you like it.</p>";
		$link 		= "/product-type/heat-pumps/";
		$pic 		= "/wp-content/uploads/American-Standard-02-320x320.jpg";
		$alt 		= "American Standard Heat Pumps";
	endif;	
	
	if ( $type == "american standard furnaces" ) :
		$title 		= "Furnaces";
		$excerpt	= "<p>Keep your home as warm and cozy as you need it with efficient gas furnaces and oil furnaces from American Standard.</p>";
		$link 		= "/product-type/furnaces/";
		$pic 		= "/wp-content/uploads/American-Standard-32-320x320.jpg";
		$alt 		= "American Standard Furnaces";
	endif;	
	
	if ( $type == "american standard packaged units" ) :
		$title 		= "Packaged Units";
		$excerpt	= "<p>Everything you want is in one easy package. Single-cabinet systems contain all your heating and cooling needs, from central heating and air cooling systems to heat pump systems for certain types of homes.</p>";
		$link 		= "/product-type/packaged-units/";
		$pic 		= "/wp-content/uploads/American-Standard-11-320x320.jpg";
		$alt 		= "American Packaged Units";
	endif;	
	
	if ( $type == "american standard automation systems" ) :
		$title 		= "Automation Systems";
		$excerpt	= "<p>Monitor and control the temperature in your home via most web-enabled cell phones, computers and tablets. For total home automation, you can even remotely turn your lights, appliances and wireless keypad locks on and off.</p>";
		$link 		= "/products/nexia-home-intelligence/";
		$pic 		= "/wp-content/uploads/Nexia-Home-Intelligence-320x320.jpg";
		$alt 		= "Nexia Home Intelligence";
	endif;	
	
	if ( $type == "american standard ductless systems" ) :
		$title 		= "Ductless Systems";
		$excerpt	= "<p>Gain full comfort control over traditional problem areas that don't cool or heat properly. Makes expansion onto existing homes a breeze, such as finishing and conditioning a garage or attic space.</p>";
		$link 		= "/product-type/ductless-systems/";
		$pic 		= "/wp-content/uploads/Samsung-Max-320x320.jpg"; 
		$alt 		= "Ductless Systems";
	endif;	
	
	return do_shortcode('
		[col class="col-archive col-products"]
		 [img size="1/3" link="'.$link.'" ada-hidden="true"]<img class="img-archive img-products" src="'.$pic.'" alt="'.$alt.'" />[/img]
		 [group size="2/3"]
		  [txt size="100" class="text-products"]<h3><a class="link-archive link-products" href="'.$link.'" aria-hidden="true" tabindex="-1">'.$title.'</a></h3>'.$excerpt.'[/txt]
		  [btn size="100" class="button-products" link="'.$link.'"]View '.$title.'[/btn]
		 [/group]
		[/col]
	');	
}


/*--------------------------------------------------------------
# American Standard Customer Care
--------------------------------------------------------------*/
add_shortcode( 'american-standard-customer-care', 'battleplan_american_standard_customer_care' );
function battleplan_american_standard_customer_care( $atts, $content = null ) {
	$a = shortcode_atts( array( 'type'=>'', ), $atts );
	$type = esc_attr($a['type']);
	
	if ( $type != "teaser" ) :	
		return do_shortcode('
			<h1>American Standard</h1>
			<h2>Customer Care Dealer</h2>

			<img class="alignright noFX size-half-s" src="/wp-content/uploads/AS-Customer-Care-Logo-258x258.png" alt="American Standard Customer Care Dealer" />

			<p>[get-biz info="name"] is proud to be an authorized American Standard Customer Care Dealer.</p>

			<p>American Standard Heating &amp; Air Conditioning is the industry leader in HVAC systems. Known for reliability, sustainability, air quality and customer care, they take great pride in providing top-notch customer service.</p>

			<p>The Customer Care program is made up of handpicked dealers who are ready to listen, evaluate and find solutions that work smarter for you and your home.</p>

			<h2>Customer Care Dealer Advantage</h2>

			<p>Not just any HVAC representative can wear the American Standard Customer Care Dealer badge. This recognition must be proven through a commitment to service and properly trained staff. If you are working with a Customer Care Dealer, you can feel confident knowing they have a commitment to the following:</p>

			<img class="alignright noFX size-half-s" src="/wp-content/uploads/AS-Product-Display-384x247.png" alt="American Standard Heating and Cooling Products" />

			<ul>
				<li><b>Product Knowledge Experts:</b> Customer Care Dealers are up-to-date on the latest technology and products, so they are able to provide recommendations for maximum efficiency and comfort to best meet your needs. Staff training is a top priority.</li>
				<li><b>Commitment to Customer Service:</b> Providing excellent customer service is critical, and Customer Care Specialists are committed to listening to your needs and responding quickly and appropriately. Efficient Climate guarantee a 100% customer satisfaction.</li>
				<li><b>Seeking Feedback:</b> The Customer Care program prides itself on providing great customer experiences, and are always looking for ways to improve. It welcomes feedback, and provides a homeowner satisfaction survey to learn and maintain this satisfaction.</li>
			</ul>

			<p>For more information, call <b>[get-biz info="area-phone"]</b> to speak with your local American Standard Customer Care Dealer today!</p>
		');
	else:
		return do_shortcode('
			 [txt size="100"]
			  <h2>What is a Customer Care Dealer?</h2>

			  <img src="/wp-content/uploads/AS-Customer-Care-Logo-258x258.png" alt="We are an American Standard Customer Care Dealer." class="alignright size-quarter-s noFX" />

			  <p>The Customer Care program is made up of handpicked dealers who are ready to listen, evaluate and find solutions that work smarter for you and your home.</p>
			  <p><strong><a href="/customer-care-dealer/">Learn more about the Customer Care program.</a></strong></p>
			 [/txt]
		');	
	endif;
}	


/*--------------------------------------------------------------
# HVAC FAQ
--------------------------------------------------------------*/
add_shortcode( 'hvac-faq', 'battleplan_hvac_faq' );
function battleplan_hvac_faq( $atts, $content = null ) {
	return do_shortcode('
		<h1>Frequently Asked Questions</h1>

		<p>Click a question below to reveal the answer.</p>

		[accordion title="How does my A/C system work?"]
			 <p>Your air conditioning system is actually designed with two separate units which perform together to provide you the ultimate comfort you deserve. The unit on the outside of your home contains a liquid refrigerant that is distributed over the coil located indoors. Through this process,the heat and humidity are removed from the air and your comfort is maintained by cooling the house.</p>
		[/accordion]

		[accordion title="What does SEER mean?"]
			 <p>The efficiency of a heating and cooling system is calculated by utilizing a ratio standard referred to as Seasonal Energy Efficiency Ratio (SEER). Governmental standards state that the SEER of any air conditioning system must meet a minimum of 13 SEER as enacted in 1992. An A/C system that is considered to be “high efficient” must be between 15-20 “SEER”. In order to receive the benefits of a 13 SEER or higher, you must upgrade the entire system. [get-biz info="name"] will be happy to custom design an air conditioning and heating system that fits your needs and budget.</p>
		[/accordion]
		
		[accordion title="What factors contribute to my home\'s comfort levels?"]
		   <p>There are four contributing factors that play a vital role to the efficiency of your air conditioning and heating system.</p>
		   <ul>
			<li><b>Temperature:</b> To the average consumer, temperature is typically the beginning and the end of discussion when the topic of indoor comfort arises. However, temperature is actually just the beginning. At [get-biz info="name"], we will recommend a heating and cooling system customized to your needs.</li>
			<li><b>Clean and Fresh Air:</b> A dusty home can have disastrous effects on both the efficiency of your heating and cooling system and your health. Our specialists at [get-biz info="name"] will be happy to suggest an air purifier that will assist to remove the dust and other allergens within your homes air so that not only will you and your family breath easier but your system will also have the opportunity to run more effectively.</li>
			<li><b>Humidity:</b> During the winter months, the air is very dry which causes static electricity and itchy skin. If humidity is added, the uncomfortable feelings of dry skin is relieved. In the summer months, if the humidity is removed, the air becomes less sticky and the summer becomes more enjoyable.</li>
			<li><b>System Control:</b> On a daily basis we seem to always answer the same question. Why are the temperatures so inconsistent from room to room? The front of the house is warmer or cooler than the back and vice versa. Why?? Your answer to that question is a self-adjusting thermostat will balance the air throughout your home and provide an even distribution of air throughout the home. Our specialists at [get-biz info="name"] will be happy to suggest a programmable thermostat designed for your needs.</li>
		   </ul>
		[/accordion]

		[accordion title="What causes a condensation leak problem?"]
		   <p>The evaporator or cooling coil is located in the unit inside the home. As it creates condensation by removing moisture from the air, a drain pan will normally collect the water. If the drain line becomes clogged, a leak occurs from the overflow. Factors that lead to a clog in a drain line include algae build-up, crimped lines, settled wood platforms, snakes or other animals or just poor system maintenance.</p>
		[/accordion]

		[accordion title="Why are my indoor coils, pipes or compressor covered with ice?"]
		   <p>There are two reasons why ice would be found on coils, pipes, etc. The first being a low level of refrigerant or a clogged air filter. If the problem is not corrected as soon as possible, it could have disastrous effects on the compressor of the system. Should you notice freezing, you should first turn the system off, check and change the air filter and if the air filter is not clogged, then immediately consult our professionals at [get-biz info="name"]. We will promptly be at your service to resolve your freezing problem.</p>
		[/accordion]

		[accordion title="What can be done to make my home\'s air cleaner?"]
		  <p>Ensuring your home has the proper ventilation can reduce moisture levels, which can greatly increase indoor air quality. Salt lamps, activated charcoal and houseplants have been found to naturally purify indoor air. Your carpets are a toxic sponge, and should be cleaned regularly.  Avoid smoking and using chemicals in the air.  And finally, the best way to improve your indoor air quality is to install a whole house air filtration system.  Our system can remove up to 99.98% of airborne allergens from your air.</p>
		[/accordion]

		[accordion title="How often should my filters be cleaned or replaced?"]
		  <p>It is recommended to inspect your filters at least once each month. Cleaning and replacement should be done on an as needed basis. As with the rest of your heating and cooling system, cleaner is always better.</p>
		[/accordion]

		[accordion title="Why should I consider a new high-efficiency filter?"]
		  <p>High-efficiency filters remove more of the smaller particles from the air. This will help us breathe better and reduce sinus problems, headaches and colds. Many people miss fewer days from school and work and use less allergy medication. The air is filtered before entering the air conditioning and furnace.</p>
		[/accordion]

		[accordion title="How much am I overpaying on utility bills?"]
		  <p>For any system that is in excess of 10 years old, unfortunately you are paying an average of 30% more on your overall utility expenses due to a lack in efficiency standard. For example, by upgrading a unit which has a 10 SEER rating to a new updated system with a minimum of 13 SEER, you will see a savings of at least 30%. An important point to remember is that when you upgrade to a 13 SEER or higher, you must install an ARI matched system.</p>
		[/accordion]

		[accordion title="How often should my system be checked?"]
		  <p>In able to ensure your system maintains a maximum operating standard, our experts at [get-biz info="name"] recommend a thorough check-up at least twice a year. In doing so, we have designed our preventative maintenance agreement that will do exactly that! Our preventative maintenance agreement will provide two scheduled visits each year.</p>
		[/accordion]
	');
}


/*--------------------------------------------------------------
# HVAC Symptom Checker
--------------------------------------------------------------*/
add_shortcode( 'hvac-symptom-checker', 'battleplan_symptom_checker' );
function battleplan_symptom_checker( $atts, $content = null ) {
	return do_shortcode('
		<h1>Symptom Checker</h1>

		<p>System isn’t working? No matter what the problem, we’re here to help. Before you call for service, try these simple tips for troubleshooting your heating and cooling system.</p>

		<p><strong>Is it getting power?</strong> Check your fuses or circuit breakers, and remember that if your home\'s power is out or disconnected, your system may not work.</p>

		<p><strong>Is the thermostat set correctly?</strong> Make sure your thermostat has power, that it is set to cooling or heating mode and not "off", and that it is set to the correct setting and temperature.</p>

		<p>Still not working?  For more tips, click the symptom below that best fits your problem:</p>


		[accordion title="No Heat / Insufficient Heat"]
		 <ul>
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
		  <li>Check around your outdoor unit. If there are any leaves, hedges or property walls butting up against it, your system may have frozen up due to a dirty coil. Make sure your outdoor unit has 1 inch of clearance all around it.</li>
		  <li>Check the air filters in each of your system components. Dirty filters can cause severe problems with your unit.  If any of your filters are dirty, consult your manual to clean or replace them.</li>
		  <li>Check to see if there is any air coming through your vent. Your indoor blower may not be operating. If this is the case, you should contact your dealer.</li>
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
		  <li>Other noises (e.g., rattling, humming, thumping or scraping sounds) could be a sign of undersized or flimsy duct work, clogged filter or wear and tear on your system’s internal components. If you hear an unusual sound, call your local dealer for service.</li>
		 </ul>
		[/accordion]
	');
}
	
	
/*--------------------------------------------------------------
# HVAC Maintenance Tips
--------------------------------------------------------------*/
add_shortcode( 'hvac-maintenance-tips', 'battleplan_hvac_maintenance_tips' );
function battleplan_hvac_maintenance_tips( $atts, $content = null ) {
	$a = shortcode_atts( array( 'type'=>'', ), $atts );
	$type = esc_attr($a['type']);
	
	if ( $type != "teaser" ) :	
		return do_shortcode('
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

			<p>If you are experiencing problems with your heating and cooling equipment, check our handy <a href="/symptom-checker/">Symptom Checker</a> to determine if it\'s an easy fix, or if you need a service call.</p>
		');
	else:
		return do_shortcode('
			[txt size="100"]
			 <h2>Maintenance Tips</h2>

			 <img src="/wp-content/uploads/maintenance-tips-teaser.jpg" alt="Above view of equipment being worked on." class="alignright size-quarter-s" />

			 <p>The life of your heating & cooling system depends on the service and care you give it. Proper care assures good performance.</p>
			 <p>We have put together a set of simple \"Dos and Don\'ts\" that every homeowner should follow.</p>
			 
			 [btn link="/maintenance-tips/" ada="about maintaining your HVAC system"]Learn More[/btn]
			[/txt]
		');	
	endif;
}	
	
	
/*--------------------------------------------------------------
# HVAC Tip Of The Month
--------------------------------------------------------------*/
add_shortcode( 'hvac-tip-of-the-month', 'battleplan_hvac_tip_of_the_month' );
function battleplan_hvac_tip_of_the_month( $atts, $content = null ) {
	$month = date('F');
	$imageBase = "https://battleplanassets.com/images/shearer-supply/tip-of-the-month/";
	
	if ( $month == "January" ) :
		$image = $imageBase."tip-of-the-month-january.jpg";
		$alt = "Invest In Efficiency";
		$headline = "Invest In Efficiency";
		$tip = do_shortcode("
			<p>Invest in a high efficiency furnace, boiler, or heat pump.  Of course it will cost you upfront, but with the money you'll save on heating costs due to increased efficiency, a new system will end up paying for itself!</p>
			<p>More importantly, you will enjoy the peace of mind that comes with a brand new warranty, which means no expensive repair bills to worry about.</p>
			<p>Call us at <strong>[get-biz info='area-phone']</strong> and we'll make a recommendation based on your home and budget.</p>
		");
	endif;

	if ( $month == "February" ) :
		$image = $imageBase."tip-of-the-month-february.jpg";
		$alt = "Cut Down Your Heating Cost";
		$headline = "Cut Down Your Heating Cost";
		$tip = do_shortcode("
			<p>The Department of Energy says that setting the thermostat 5 degrees lower for just 8 hours a day can save up to 5% in energy costs.</p>
			<p>That means a programmable thermostat, when used properly, can save you up to $200 per year!</p>
			<p>We can help you take control of your home’s energy usage. Call us at <strong>[get-biz info='area-phone']</strong> for more information.</p>
		");
	endif;

	if ( $month == "March" ) :
		$image = $imageBase."tip-of-the-month-march.jpg";
		$alt = "Warm Weather Coming Soon";
		$headline = "Warm Weather Soon";
		$tip = do_shortcode("
			<p>Spring is right around the corner. Milder weather means you won't be depending on your HVAC system as much.  This is the perfect time to do a little maintenance so that your air conditioner is running properly and efficiently for summer.</p>
			<p>First and foremost, change your air filter!  You should do this on a monthly basis, because it helps avoid dust build-up which obstructs airflow and affects both your comfort and energy bills.</p>
			<p>Call us at <strong>[get-biz info='area-phone']</strong> for a spring tune-up that ensures a comfortable, efficient, and cost effective summer!</p>
		");
	endif;

	if ( $month == "April" ) :
		$image = $imageBase."tip-of-the-month-april.jpg";
		$alt = "Spring Has Sprung";
		$headline = "Spring Has Sprung";
		$tip = do_shortcode("
			<p>Mild spring weather means you won't be using your heating and cooling system as much.  This is the perfect time to do a little maintenance so that your HVAC is running properly and efficiently for summer.</p>
			<p>Electrical connections that are faulty can make the operation of your HVAC system unsafe and reduce the lifespan of its major components. A regular step in your spring HVAC checklist should be checking and tightening these connections.</p>
			<p>Call us at <strong>[get-biz info='area-phone']</strong> for a spring tune-up that ensures a comfortable, efficient, and cost effective summer!</p>
		");
	endif;

	if ( $month == "May" ) :
		$image = $imageBase."tip-of-the-month-may.jpg";
		$alt = "Clean Your Outdoor HVAC";
		$headline = "Clean Your Outdoor HVAC";
		$tip = do_shortcode("
			<p>Summer will be here soon... and you'll be depending heavily on your air conditioner to maintain you family's comfort. An important step in ensuring it does it's job is making sure the outdoor unit is clean.</p>
			<p>Clean out any leaves, grass or dirt that may be blocking your vents. If you don't clean them out, it will restrict the airflow in your home, which will reduce your HVAC system's efficiency.</p>
			<p>Call us at <strong>[get-biz info='area-phone']</strong> for a spring tune-up that ensures a comfortable, efficient, and cost effective summer!</p>
		");
	endif;

	if ( $month == "June" ) :
		$image = $imageBase."tip-of-the-month-june.jpg";
		$alt = "Is your HVAC drainage hole clogged?";
		$headline = "Drainage Hole Clogged?";
		$tip = do_shortcode("
			<p>Summer weather is upon us! One way to get better efficiency from your HVAC unit is to clear the drainage hole.</p>
			<p>Air conditioners commonly have a drainage hole located at their cabinet’s base. In order for your air conditioner to work effectively, this hole must be kept clear.</p>
			<p>Call us at <strong>[get-biz info='area-phone']</strong> and let us tune up your A/C unit for a comfortable and cost effective summer!</p>
		");
	endif;

	if ( $month == "July" ) :
		$image = $imageBase."tip-of-the-month-july.jpg";
		$alt = "Cool Your Home For Less";
		$headline = "Cool Your Home For Less";
		$tip = do_shortcode("
			<p>Smart thermostats allow you to program the temperature settings in your home to use less energy while you're away, and keep you more comfortable when you're home.</p>
			<p>Save energy and lower your monthly electric bill.  Plus, enjoy the convenience of controlling your home via your mobile device!</p>
			<p>We can make your home smarter. Call us at <strong>[get-biz info='area-phone']</strong> for more information.</p>
		");
	endif;

	if ( $month == "August" ) :
		$image = $imageBase."tip-of-the-month-august.jpg";
		$alt = "Money Saving Tips";
		$headline = "Money Saving Tips";
		$tip = do_shortcode("
			<p>Avoid placing lamps or TV sets near your thermostat. These appliances generate heat and will cause the air conditioner to run longer than necessary.</p>
			<p>Vacuum registers regularly to remove any dust buildup. Ensure that furniture and other objects are not blocking the airflow through your registers.</p>
			<p>Call us at <strong>[get-biz info='area-phone']</strong> for more tips on how to save money on your monthly electric bill.</p>
		");
	endif;

	if ( $month == "September" ) :
		$image = $imageBase."tip-of-the-month-september.jpg";
		$alt = "Clean your bathroom fans.";
		$headline = "Clean Bathroom Fans";
		$tip = do_shortcode("
			<p>Your bathroom fans work hard all year, and this is the perfect time to ensure the work they do is as efficient as possible.</p>
			<p>Remove the covers from your fans, and wash them thoroughly with soap and water. Once the covers are off, use a toothbrush to clean the fan blades before reapplying the cover.</p>
			<p>Call us at <strong>[get-biz info='area-phone']</strong> for more tips on how to save money on your monthly electric bill.</p>
		");
	endif;
	
	if ( $month == "October" ) :
		$image = $imageBase."tip-of-the-month-october.jpg";
		$alt = "Schedule Chores To Save Money";
		$headline = "Schedule Chores To Save Money";
		$tip = do_shortcode("
			<p>Many utility companies have \"dual time\" rates, which means they charge more for energy that is used during peak times.</p>
			<p>If your electric company charges more for day time energy consumption, switch your chores (runing the dishwasher, washing machine, and dryer) to nighttime and your energy costs will be less!</p>
			<p>For a complete energy audit of your home, call us at <strong>[get-biz info='area-phone']</strong>.</p>
		");
	endif;
	
	if ( $month == "November" ) :
		$image = $imageBase."tip-of-the-month-november.jpg";
		$alt = "Winter Is Coming!";
		$headline = "Winter Is Coming!";
		$tip = do_shortcode("
			<p>Caulking and weather stripping doors and windows will seal out cold air.  Wrapping your hot water heater with insulation will help save on heating costs.</p>
			<p>Another great tip is to install gaskets on all electrical outlets and switches located on outside walls. These are available at hardware stores.</p>
			<p>Call us at <strong>[get-biz info='area-phone']</strong> for a quick inspection of your equipment to make sure you are ready for winter!</p>
		");
	endif;
	
	if ( $month == "December" ) :
		$image = $imageBase."tip-of-the-month-december.jpg";
		$alt = "Fireplace Efficiency";
		$headline = "Fireplace Efficiency";
		$tip = do_shortcode("
			<p>Experts agree that the typical fireplace loses more heat than it generates, especially in cold climates. As smoke rises up thru the chimney, your heated air follows. Consider installing a fireplace insert which helps make better use of the heat.</p>
			<p>When your fireplace is not in use, make sure the damper is set to the closed position. An open damper is like leaving a window open.</p>
			<p>For a full evaluation of the efficiency of your home's heating system, call us at <strong>[get-biz info='area-phone']</strong>.</p>
		");
	endif;	
	
	return do_shortcode('
		[txt size="100"]
		 <h2>'.$headline.'</h2>

		 <img src="'.$image.'" alt="'.$alt.'" class="alignright size-third-s" width="260" height="260" />

		 '.$tip.'
		[/txt]			
	');
}
	
?>