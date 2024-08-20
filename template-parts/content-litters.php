<?php /* Template part for displaying dogs */

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
$birthDate = esc_attr(get_field( "birth_date" ));
$birthDateObj = new DateTime($birthDate);
$birthDateObj->modify('+8 weeks');
$readyDate = $birthDateObj->format("F j, Y"); 
//$readyDate = esc_attr(get_field( "ready_date" ));
$price = esc_attr(get_field( "price" ));
$depositHold = esc_attr(get_field( "deposit_hold" ));
$depositBorn = esc_attr(get_field( "deposit_born" ));
$deposit = $depositHold + $depositBorn;

$search = ['( BLK )', '( YLW )', '( CHOC )'];
$replace = ['', '', ''];

$b1 = str_replace( $search, $replace, esc_attr(get_post_meta( $sireID, 'sire', true )) );
$b2 = str_replace( $search, $replace, esc_attr(get_post_meta( $sireID, 'dam', true )) );
$c1 = str_replace( $search, $replace, esc_attr(get_post_meta( $sireID, 'grandparent_1', true )) );
$c2 = str_replace( $search, $replace, esc_attr(get_post_meta( $sireID, 'grandparent_2', true )) );
$c3 = str_replace( $search, $replace, esc_attr(get_post_meta( $sireID, 'grandparent_3', true )) );
$c4 = str_replace( $search, $replace, esc_attr(get_post_meta( $sireID, 'grandparent_4', true )) );
$b3 = str_replace( $search, $replace, esc_attr(get_post_meta( $damID, 'sire', true )) );
$b4 = str_replace( $search, $replace, esc_attr(get_post_meta( $damID, 'dam', true )) );
$c5 = str_replace( $search, $replace, esc_attr(get_post_meta( $damID, 'grandparent_1', true )) );
$c6 = str_replace( $search, $replace, esc_attr(get_post_meta( $damID, 'grandparent_2', true )) );
$c7 = str_replace( $search, $replace, esc_attr(get_post_meta( $damID, 'grandparent_3', true )) );
$c8 = str_replace( $search, $replace, esc_attr(get_post_meta( $damID, 'grandparent_4', true )) );
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
		
		$buildLitter .= '<p style="margin-top: -10px; font-size: 70%; text-align:center"><em>(Updated: '.get_the_modified_date().')</em></p>'; 

		
		if ( $price ) : $buildLitter .= '<li><span class="label">Price: </span>$'.number_format($price, 0, ".", ",").' <span style="font-size:70%;">+ sales tax</span></li>';
		else: $buildLitter .= "Call For Price"; endif;
		
		//if ( $deposit && ( $litterStatus == "Expecting" || strtotime(date('F j, Y')) < strtotime($readyDate)) ) : $buildLitter .= '<li style="margin-top:-0.5em"><span class="label">Deposit: </span>$'.number_format($deposit, 0, ".", ",").'</li>'; endif;
		
		if ( $depositHold && ( $litterStatus == "Expecting" || strtotime(date('F j, Y')) < strtotime($readyDate)) ) : $buildLitter .= '<li style="margin-top:-0.5em"><span class="label">Deposit: </span>$'.number_format($depositHold, 0, ".", ",").' <span style="font-size:70%;">to hold a pup</span></li>'; endif;
		
		if ( $depositBorn && ( $litterStatus == "Expecting" || strtotime(date('F j, Y')) < strtotime($readyDate)) ) : $buildLitter .= '<li style="margin-top:-0.5em"><span class="label"></span>$'.number_format($depositBorn, 0, ".", ",").' <span style="font-size:70%;">due at birth</span></li>'; endif;
		
		if ( $litterStatus == "Expecting" && $birthDate != '' ) : 
			$buildLitter .= '<li><span class="label">Expected: </span>'.date('F Y', strtotime($birthDate)).'</li>';	
		elseif ( $birthDate != '' ): 
			$buildLitter .= '<li><span class="label">Born: </span>'.date('F j, Y', strtotime($birthDate)).'</li>';			
			if ( strtotime(date('F j, Y')) < strtotime($readyDate) ) : $buildLitter .= '<li style="margin-top:-0.5em"><span class="label">Ready: </span>'.$readyDate.'</li>'; 		
			else : $buildLitter .= '<li><span class="label label-full">Ready To Go Home</li>'; 
			endif;
		endif;
		
		$singleContent = wp_kses_post(get_the_content());

		if ( $singleContent ) $buildLitter .= do_shortcode('[p]'.$singleContent.'[/p]');
		
		$buildLitter .= "</ul>";
				
		$buildLitter .= '<p style="font-size:90%;"><em><b>Effective Sept 1, 2024</b></em><br>Microchip registration fee will be prepaid by Mill Pond Retrievers & included in purchase price. If we do not have a puppy available, your deposit is refundable or can be applied to a different litter. Pups are sold on a first come first serve basis. The sex of pup has to be chosen when the deposit is made.</p>';		

		$buildPedigree = do_shortcode('[bracket a1="'.$sireFull.'" a2="'.$damFull.'" b1="'.$b1.'" b2="'.$b2.'" b3="'.$b3.'" b4="'.$b4.'" c1="'.$c1.'" c2="'.$c2.'" c3="'.$c3.'" c4="'.$c4.'" c5="'.$c5.'" c6="'.$c6.'" c7="'.$c7.'" c8="'.$c8.'"]');
		
		if ( !$sireNoLink && !$damNoLink ) :
			$singleContent = $buildLitter.do_shortcode('[section name="wrapper-bracket" style="1" width="stretch" class="section-bracket"][layout]<h4>Pedigree</h4><h2>'.$sireCall." x ".$damCall.'</h2>'.$buildPedigree.'[/layout][/section]');	
		else:
			$singleContent = $buildLitter;	
		endif;
		
		echo do_shortcode($singleContent); ?>
	</div><!-- .entry-content -->