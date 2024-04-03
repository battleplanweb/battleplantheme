<?php /* Template part for displaying dogs */

global $noPic;
$name = esc_html(get_the_title());
$callname = esc_attr(get_field( "call_name" ));
$birth_date = esc_attr(get_field( "birth_date" ));
$birthdate = date_create($birth_date);
$birthdate = date_format($birthdate,"M j, Y");
$sex = esc_attr(get_field( "sex" )) == "Legacy" ? "Male" : esc_attr(get_field( "sex" ));
$color = esc_attr(get_field( "color" ));
$geno_values = get_field("geno");
$studFee = esc_attr(get_field( "stud_fee" ));
$hips = esc_attr(get_field( "hips" ));
$cnm = esc_attr(get_field( "cnm" ));
$eic = esc_attr(get_field( "eic" ));
$elbows = esc_attr(get_field( "elbows" ));
$eyes = esc_attr(get_field( "eyes" ));
$pra = esc_attr(get_field( "pra" ));
$info = esc_attr(get_field( "breeding_info" ));

$search = ['( BLK )', '( YLW )', '( CHOC )'];
$replace = ['', '', ''];

$a1 = str_replace( $search, $replace, esc_attr(get_field( "sire" )) );
$a2 = str_replace( $search, $replace, esc_attr(get_field( "dam" )) );
$b1 = str_replace( $search, $replace, esc_attr(get_field( "grandparent_1" )) );
$b2 = str_replace( $search, $replace, esc_attr(get_field( "grandparent_2" )) );
$b3 = str_replace( $search, $replace, esc_attr(get_field( "grandparent_3" )) );
$b4 = str_replace( $search, $replace, esc_attr(get_field( "grandparent_4" )) );
$c1 = str_replace( $search, $replace, esc_attr(get_field( "great_grandparent_1" )) );
$c2 = str_replace( $search, $replace, esc_attr(get_field( "great_grandparent_2" )) );
$c3 = str_replace( $search, $replace, esc_attr(get_field( "great_grandparent_3" )) );
$c4 = str_replace( $search, $replace, esc_attr(get_field( "great_grandparent_4" )) );
$c5 = str_replace( $search, $replace, esc_attr(get_field( "great_grandparent_5" )) );
$c6 = str_replace( $search, $replace, esc_attr(get_field( "great_grandparent_6" )) );
$c7 = str_replace( $search, $replace, esc_attr(get_field( "great_grandparent_7" )) );
$c8 = str_replace( $search, $replace, esc_attr(get_field( "great_grandparent_8" )) );
$postDate = the_date('F Y', '', '', FALSE); 
$modDate = the_modified_date( 'F Y', '', '', FALSE);
$theContent = get_the_content();
?> 

	<div class="entry-content">
						
		<?php if ( has_post_thumbnail() ) : $dogPic = get_the_post_thumbnail(get_the_ID(), 'size-half-s'); else: $dogPic = wp_get_attachment_image( $noPic, "thumbnail"); endif;
			
		$dogInfo = '<h3>';
		if ( $callname ) $dogInfo .= $callname."'s ";
		$dogInfo .= 'Info</h3><ul>';

		if ( $sex != "NA" && $sex != "" ) $dogInfo .= "<li><span class='label'>Sex:</span> ".$sex."</li>";
		if ( $color != "NA" && $color != "" ) $dogInfo .= "<li><span class='label'>Color:</span> ".$color."</li>";
		if( !empty($geno_values) ) { 
    		$geno_list = is_array($geno_values) ? implode(" & ", array_map('esc_attr', $geno_values)) : esc_attr($geno_values);
			if ($geno_list != "") {
				$dogInfo .= "<li><span class='label'>Hidden:</span> ".$geno_list."</li>";
			}
		}		
		if ( $birth_date != "NA" && $birth_date != "" ) $dogInfo .= "<li><span class='label'>Born:</span> ".$birthdate."</li>";
		if ( $eic != "NA" && $eic != "" ) $dogInfo .= "<li><span class='label'>EIC:</span> ".$eic."</li>";
		if ( $cnm != "NA" && $cnm != "" ) $dogInfo .= "<li><span class='label'>CNM:</span> ".$cnm."</li>";
		if ( $hips != "NA" && $hips != "" ) $dogInfo .= "<li><span class='label'>Hips:</span> ".$hips."</li>";
		if ( $elbows != "NA" && $elbows != "" ) $dogInfo .= "<li><span class='label'>Elbows:</span> ".$elbows."</li>";
		if ( $eyes != "NA" && $eyes != "" ) $dogInfo .= "<li><span class='label'>Eyes:</span> ".$eyes."</li>";
		if ( $pra != "NA" && $pra != "" ) $dogInfo .= "<li><span class='label'>PRA:</span> ".$pra."</li>";
		if ($studFee != "0" && $studFee != "") : $studFee = number_format($studFee, 2, '.', ','); $dogInfo .= "<li><span class='label'>Stud Fee:</span> $'.$studFee.'</li>"; endif;
		$dogInfo .= '</ul>';
		if ( $theContent ) $dogInfo .= "<h4>Other Points of Interest</h4>".do_shortcode('[p]'.$theContent.'[/p]');		

		foreach ( get_posts( array ( 'numberposts'=>-1, 'post_type'=>'litters', ) ) as $post ) {
			$id = $post->ID;
			$sire = get_field( "sire", $id );
			$dam = get_field( "dam", $id );
			if ( esc_html(get_the_title($sire)) == $name ) :			
				if ( !$buildLitters ) : $buildLitters = "<b>Litters:</b><ul>"; endif;			
				$buildLitters .= "<li><a href='".esc_url(get_post_permalink( $id ))."'>x ".esc_html(get_the_title($dam))."</a></li>";	
			endif;
			if ( esc_html(get_the_title($dam)) == $name ) :			
				if ( !$buildLitters ) : $buildLitters = "<b>Litters:</b><ul>"; endif;			
				$buildLitters .= "<li><a href='".esc_url(get_post_permalink( $id ))."'>x ".esc_html(get_the_title($sire))."</a></li>";	
			endif;
		}
		if ( $buildLitters ) : $buildLitters .= "</ul>"; $dogInfo .= $buildLitters; endif;

		if ( $info ) $dogInfo .= 'For breeding information, please call:<br/>'.$info;

		$buildDog = do_shortcode('[col class="dog-pic"]'.$dogPic.'[/col]');									 
		$buildDog .= do_shortcode('[col class="dog-info"][txt]'.$dogInfo.'[/txt][/col]');		

		$buildPedigree = do_shortcode('[bracket a1="'.$a1.'" a2="'.$a2.'" b1="'.$b1.'" b2="'.$b2.'" b3="'.$b3.'" b4="'.$b4.'" c1="'.$c1.'" c2="'.$c2.'" c3="'.$c3.'" c4="'.$c4.'" c5="'.$c5.'" c6="'.$c6.'" c7="'.$c7.'" c8="'.$c8.'"]');

		$singleContent = do_shortcode('[layout grid="1-1"]'.$buildDog.'[/layout]').do_shortcode('[section name="wrapper-bracket" style="1" width="stretch" class="section-bracket"][layout]<h4>Pedigree</h4><h2>'.$name.'</h2>'.$buildPedigree.'[/layout][/section]');	
		
		echo $singleContent; ?>
	</div><!-- .entry-content -->