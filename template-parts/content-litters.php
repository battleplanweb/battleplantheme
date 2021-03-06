<?php
/* Template part for displaying dogs */

global $noPic;
$sireNoLink = esc_attr(get_field( "sire_no_link" ));
$damNoLink = esc_attr(get_field( "dam_no_link" ));
$sire = get_field( "sire" );
$sireID = $sire->ID;
$sireFull = esc_html(get_the_title($sireID));
$sireCall = esc_attr(get_post_meta( $sireID, 'call_name', true ));
if ( !$sireCall ) $sireCall = $sireFull;
$sireEIC = esc_attr(get_post_meta( $sireID, 'eic', true ));
$sireCNM = esc_attr(get_post_meta( $sireID, 'cnm', true ));
$sireHips = esc_attr(get_post_meta( $sireID, 'hips', true ));
$sireElbows = esc_attr(get_post_meta( $sireID, 'elbows', true ));
$sireEyes = esc_attr(get_post_meta( $sireID, 'eyes', true ));
$dam = get_field( "dam" );
$damID = $dam->ID;
$damFull = esc_html(get_the_title($damID));
$damCall = esc_attr(get_post_meta( $damID, 'call_name', true ));
if ( !$damCall ) $damCall = $damFull;
$sirePic = get_the_post_thumbnail($sireID, 'thumbnail');
if ( !$sirePic ) $sirePic = wp_get_attachment_image( $noPic, 'thumbnail');
$damPic = get_the_post_thumbnail($damID, 'thumbnail');
if ( !$damPic ) $damPic = wp_get_attachment_image( $noPic, 'thumbnail');
$damEIC = esc_attr(get_post_meta( $damID, 'eic', true ));
$damCNM = esc_attr(get_post_meta( $damID, 'cnm', true ));
$damHips = esc_attr(get_post_meta( $damID, 'hips', true ));
$damElbows = esc_attr(get_post_meta( $damID, 'elbows', true ));
$damEyes = esc_attr(get_post_meta( $damID, 'eyes', true ));
$litterStatus = esc_attr(get_field( "litter_status" ));
$readyDate = esc_attr(get_field( "ready_date" ));
$price = esc_attr(get_field( "price" ));
$deposit = esc_attr(get_field( "deposit" ));
$b1 = esc_attr(get_post_meta( $sireID, 'sire', true ));
$b2 = esc_attr(get_post_meta( $sireID, 'dam', true ));
$c1 = esc_attr(get_post_meta( $sireID, 'grandparent_1', true ));
$c2 = esc_attr(get_post_meta( $sireID, 'grandparent_2', true ));
$c3 = esc_attr(get_post_meta( $sireID, 'grandparent_3', true ));
$c4 = esc_attr(get_post_meta( $sireID, 'grandparent_4', true ));
$b3 = esc_attr(get_post_meta( $damID, 'sire', true ));
$b4 = esc_attr(get_post_meta( $damID, 'dam', true ));
$c5 = esc_attr(get_post_meta( $damID, 'grandparent_1', true ));
$c6 = esc_attr(get_post_meta( $damID, 'grandparent_2', true ));
$c7 = esc_attr(get_post_meta( $damID, 'grandparent_3', true ));
$c8 = esc_attr(get_post_meta( $damID, 'grandparent_4', true ));
$postDate = the_date('F Y', '', '', FALSE); 
$modDate = the_modified_date( 'F Y', '', '', FALSE);
?>

	<div class="entry-content">
		<?php 
				
		if ( !$sireNoLink ) :
			$setupSire = '<div class="text-dogs litter-sire span-5">'.$sirePic.'<h2>'.$sireCall.'</h2><h3>'.esc_html(get_the_title($sire)).'</h3>'.do_shortcode('[btn link="'.esc_url(get_permalink($sireID)).'"]View Sire[/btn]').'</div>';
		else:
			$setupSire = '<div class="text-dogs litter-sire span-5">'.$sirePic.'<h2>'.$sireNoLink.'</h2></div>';
		endif;

		if ( !$damNoLink ) :
			$setupDam = '<div class="text-dogs litter-dam span-5">'.$damPic.'<h2>'.$damCall.'</h2><h3>'.esc_html(get_the_title($dam)).'</h3>'.do_shortcode('[btn link="'.esc_url(get_permalink($damID)).'"]View Dam[/btn]</div>');	
		else:
			$setupDam = '<div class="text-dogs litter-dam span-5">'.$damPic.'<h2>'.$damNoLink.'</h2></div>';		
		endif;
		
		$setupCenter = '<div class="litter-x span-2"><h2>x</h2></div>';				
		$buildLitter = do_shortcode('[col class="col-litters"]'.$setupSire.$setupCenter.$setupDam.'[/col]'); 		

		$buildLitter .= '<ul class="litter-details"><h4>Litter Details</h4>';
		if ( $litterStatus == "Expecting" ) : $buildLitter .= '<li><span class="label">Expected: </span>'.date('F Y', strtotime($readyDate)).'</li>';		
		elseif ( date('F j, Y') > $readyDate ) : $buildLitter .= '<li><span class="label">Ready: </span>'.$readyDate.'</li>'; 		
		else : $buildLitter .= '<li><span class="label">Available Now</li>'; endif;
		if ( $price ) : $buildLitter .= '<li><span class="label">Price: </span>$'.number_format($price, 0, ".", ",").' <span style="font-size:70%;">+ Sales Tax</span></li>';
		else: $buildLitter .= "Call For Price"; endif;
		if ( $deposit ) : $buildLitter .= '<li><span class="label">Deposit: </span>$'.number_format($deposit, 0, ".", ","); endif;
		
		$singleContent = wp_kses_post(get_the_content());
		if ( $singleContent ) $buildLitter .= do_shortcode('[p]'.$singleContent.'[/p]');
		
		$buildLitter .= "</ul>";
		
		$buildPedigree = do_shortcode('[bracket a1="'.$sireFull.'" a2="'.$damFull.'" b1="'.$b1.'" b2="'.$b2.'" b3="'.$b3.'" b4="'.$b4.'" c1="'.$c1.'" c2="'.$c2.'" c3="'.$c3.'" c4="'.$c4.'" c5="'.$c5.'" c6="'.$c6.'" c7="'.$c7.'" c8="'.$c8.'"]');
		
		if ( !$sireNoLink && !$damNoLink ) :
			$singleContent = $buildLitter.do_shortcode('[section name="wrapper-bracket" style="1" width="stretch" class="section-bracket"][layout]<h4>Pedigree</h4><h2>'.$sireCall." x ".$damCall.'</h2>'.$buildPedigree.'[/layout][/section]');	
		else:
			$singleContent = $buildLitter;	
		endif;
		
		echo $singleContent; ?>
	</div><!-- .entry-content -->