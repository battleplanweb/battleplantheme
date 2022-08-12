<?php
/* Battle Plan Web Design - Trane Product Overview */

	if ( $type == "trane air conditioners" ) :
		$title 		= "Air Conditioners";
		$excerpt	= "<p>Designed for maximum efficiency and Energy Star® qualification. Trane® air conditioners deliver efficiency. In fact, some Trane Air Conditioners are so efficient, they are Energy Star® qualified.</p>";
		$link 		= "/product-type/air-conditioners/";
		$pic 		= "Trane-AC-01-320x320.jpg";
		$alt 		= "Trane Air Conditioners";
	endif;
	
	if ( $type == "trane heat pumps" ) :
		$title 		= "Heat Pumps";
		$excerpt	= "<p>Heat and cool your home with a versatile system that’s powered by electricity. Heat pumps work best in moderate climates, and help keep you comfortable while lowering your carbon footprint.</p>";
		$link 		= "/product-type/heat-pumps/";
		$pic 		= "Trane-HP-01-320x320.jpg";
		$alt 		= "Trane Heat Pumps";
	endif;	
	
	if ( $type == "trane furnaces" ) :
		$title 		= "Furnaces";
		$excerpt	= "<p>Stay warm with furnaces that use natural gas, liquid propane, or oil to heat your home. Furnaces are ideal for homeowners who experience extremely cold winters. They can be paired with an air conditioner or heat pump to cool your home in the summer.</p>";
		$link 		= "/product-type/furnaces/";
		$pic 		= "Trane-F-01-320x320.jpg";
		$alt 		= "Trane Furnaces";
	endif;

?>