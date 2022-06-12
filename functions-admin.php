<?php
/* Battle Plan Web Design Functions: Admin

 
/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Shortcodes
# Admin Columns Set Up 
# Admin Interface Set Up
# Contact Form 7 Set Up
# Set Global Options

--------------------------------------------------------------*/

/*--------------------------------------------------------------
# Shortcodes
--------------------------------------------------------------*/

// Remove buttons from WordPress text editor
add_filter( 'quicktags_settings', 'battleplan_delete_quicktags', 10, 2 );
function battleplan_delete_quicktags( $qtInit, $editor_id = 'content' ) {
	//$qtInit['buttons'] = 'strong,em,link,block,del,ins,img,ul,ol,code,more,close';
	$qtInit['buttons'] = 'strong,em,link,ul,ol,more,close';
	return $qtInit;
}
 
// Add new buttons to WordPress text editor
add_action( 'admin_print_footer_scripts', 'battleplan_add_quicktags' );
function battleplan_add_quicktags() {
	if ( wp_script_is( 'quicktags' ) ) { ?>
		<script type="text/javascript">
			QTags.addButton( 'bp_paragraph', 'p', '<p>', '</p>\n', 'p', 'Paragraph Tag', 1 );
			QTags.addButton( 'bp_li', 'li', ' <li>', '</li>', 'li', 'List Item', 100 );

			QTags.addButton( 'bp_section', 'section', '[section name="becomes id attribute" hash="compensation for scroll on one-page sites" style="corresponds to css" width="default, stretch, full, edge, inline" background="url" left="50" top="50" class="" start="YYYY-MM-DD" end="YYYY-MM-DD"]\n', '[/section]\n\n', 'section', 'Section', 1000 );		
			QTags.addButton( 'bp_layout', 'layout', ' [layout grid="1-auto, 1-1-1-1, 5e, content, 80px 100px 1fr" break="none, 3, 4" valign="start, stretch, center, end" class=""]\n\n', ' [/layout]\n', 'layout', 'Layout', 1000 );
			QTags.addButton( 'bp_column', 'column', '  [col name="becomes id attribute" hash="compensation for scroll on one-page sites" align="center, left, right" valign="start, stretch, center, end" background="url" left="50" top="50" class="" start="YYYY-MM-DD" end="YYYY-MM-DD"]\n', '  [/col]\n\n', 'column', 'Column', 1000 );
			QTags.addButton( 'bp_image', 'image', '   [img size="100 1/2 1/3 1/4 1/6 1/12" order="1, 2, 3" link="url to link to" new-tab="false, true" ada-hidden="false, true" class="" start="YYYY-MM-DD" end="YYYY-MM-DD"]', '[/img]\n', 'image', 'Image', 1000 );
			QTags.addButton( 'bp_video', 'video', '   [vid size="100 1/2 1/3 1/4 1/6 1/12" order="1, 2, 3" link="url of video" thumb="url of thumb, if not using auto" preload="false, true" class="" related="false, true" start="YYYY-MM-DD" end="YYYY-MM-DD"]', '[/vid]\n', 'video', 'Video', 1000 );
			QTags.addButton( 'bp_caption', 'caption', '[caption align="aligncenter, alignleft, alignright | size-full-s" width="800"]<img src="/filename.jpg" alt="" class="size-full-s" />Type caption here.[/caption]\n', '', 'caption', 'Caption', 1000 );
			QTags.addButton( 'bp_group', 'group', '   [group size = "100 1/2 1/3 1/4 1/6 1/12" order="1, 2, 3" class="" start="YYYY-MM-DD" end="YYYY-MM-DD"]\n', '   [/group]\n\n', 'group', 'Group', 1000 );	
			QTags.addButton( 'bp_text', 'text', '   [txt size="100 1/2 1/3 1/4 1/6 1/12" order="2, 1, 3" class="" start="YYYY-MM-DD" end="YYYY-MM-DD"]\n', '   [/txt]\n', 'text', 'Text', 1000 );
			QTags.addButton( 'bp_button', 'button', '   [btn size="100 1/2 1/3 1/4 1/6 1/12" order="3, 1, 2" align="center, left, right" link="url to link to" get-biz="link in functions.php" new-tab="false, true" class="" icon="fas fa-chevron-right" fancy="(blank), 2" ada="text for ada button" start="YYYY-MM-DD" end="YYYY-MM-DD"]', '[/btn]\n', 'button', 'Button', 1000 );	
			QTags.addButton( 'bp_social', 'social', '   [social-btn type="email, facebook, twitter" img="none, link"]', '', 'social', 'Social', 1000 );	
			QTags.addButton( 'bp_accordion', 'accordion', '   [accordion title="clickable title" class="" excerpt="false, whatever text you want the excerpt to be" active="false, true" icon="true, false, /wp-content/uploads/image.jpg" btn="false/true/ Open Button Text" btn_collapse="Close Button Text" start="YYYY-MM-DD" end="YYYY-MM-DD"]', '[/accordion]\n\n', 'accordion', 'Accordion', 1000 );			
			
			QTags.addButton( 'bp_expire-content', 'expire', '[expire start="YYYY-MM-DD" end="YYYY-MM-DD"]', '[/expire]\n\n', 'expire', 'Expire', 1000 );			
			QTags.addButton( 'bp_restrict-content', 'restrict', '[restrict max="administrator, any role" min="none, any role"]', '[/restrict]\n\n', 'restrict', 'Restrict', 1000 );	
			
			QTags.addButton( 'bp_lock-section', 'lock', '[lock name="becomes id attribute" style="(lock) corresponds to css" width="edge, default, stretch, full, inline" position="bottom, top, modal, header" delay="3000" show="session, never, always, # days" background="url" left="50" top="50" class="" start="YYYY-MM-DD" end="YYYY-MM-DD" btn-activated="no, yes"]\n [layout]\n\n', ' [/layout]\n[/lock]\n\n', 'lock', 'Lock', 1000 );	
			
			QTags.addButton( 'bp_clear', 'clear', '[clear height="px, em" class=""]\n\n', '', 'clear', 'Clear', 1000 );	
			
			QTags.addButton( 'bp_images_side-by-side', 'side by side images', '[side-by-side img="ids" size="half-s, third-s, full" align="center, left, right" full="id" pos="bottom, top"]\n\n', '', 'side by side images', 'Side By Side Images', 1000 );			
			QTags.addButton( 'bp_get-wp-page', 'get wp page', '[get-wp-page type="page, post, cpt" id="" slug="" title="" display="content, excerpt, title, thumbnail, link"]\n\n', '', 'get wp page', 'Get WP Page', 1000 );
			
			QTags.addButton( 'bp_random-image', 'random image', '   [get-random-image id="" tag="random" size="thumbnail, third-s" link="no, yes" number="1" offset="" align="left, right, center" order_by="recent, rand, menu_order, title, id, post_date, modified, views" order="asc, desc" shuffle="no, yes" lazy="true, false"]\n\n', '', 'random image', 'Random Image', 1000 );
			QTags.addButton( 'bp_random-post', 'random post', '   [get-random-posts num="1" offset="0" leeway="0" type="post" tax="" terms="" orderby="recent, rand, views-today, views-7day, views-30day, views-90day, views-180day, views-365day, views-all" sort="asc, desc" count_tease="true, false" count_view="true, false" thumb_only="false, true" thumb_col="1, 2, 3, 4" show_title="true, false" title_pos="outside, inside" show_date="false, true" show_author="false, true" show_excerpt="true, false" show_social="false, true" show_btn="true, false" button="Read More" btn_pos="inside, outside" thumbnail="force, false" link="post, false, cf-field_name, /link-destination/" start="" end="" exclude="" x_current="true, false" size="thumbnail, size-third-s" pic_size="1/3" text_size=""]\n\n', '', 'random post', 'Random Post', 1000 );
			QTags.addButton( 'bp_random-text', 'random text', '   [get-random-text cookie="true, false" text1="" text2="" text3="" text4="" text5="" text6="" text7=""]\n\n', '', 'random text', 'Random Text', 1000 );
			QTags.addButton( 'bp_row-of-pics', 'row of pics', '   [get-row-of-pics id="" tag="row-of-pics" col="4" row="1" offset="0" size="half-s, thumbnail" valign="center, start, stretch, end" link="no, yes" order_by="recent, rand, menu_order, title, id, post_date, modified, views" order="asc, desc" shuffle="no, yes" lazy="true, false" class=""]\n\n', '', 'row of pics', 'Row Of Pics', 1000 );
			QTags.addButton( 'bp_post-slider', 'post slider', '   [get-post-slider type="" auto="yes, no" interval="6000" loop="true, false" num="4" offset="0" pics="yes, no" controls="yes, no" controls_pos="below, above" indicators="no, yes" justify="space-around, space-evenly, space-between, center" pause="true, false" tax="" terms="" orderby="recent, rand, id, author, title, name, type, date, modified, parent, comment_count, relevance, menu_order, (images) views, (posts) views-today, views-7day, views-30day, views-90day, views-180day, views-365day, views-all" order="asc, desc" post_btn="" all_btn="View All" link="" start="" end="" exclude="" x_current="true, false" show_excerpt="true, false" show_content="false, true" size="thumbnail, half-s" pic_size="1/3" text_size="" class="" (images) slide_type="box, screen, fade" tag="" caption="no, yes" id="" mult="1" truncate="true, false, # of characters" lazy="true, false"]\n\n', '', 'post slider', 'Post Slider', 1000 );

			QTags.addButton( 'bp_images-slider', 'Images Slider', '<div class="alignright size-half-s">[get-post-slider type="images" num="6" size="half-s" controls="no" indicators="yes" tag="featured" all_btn="" link="none, alt, description, blank" slide_type="box, screen, fade" orderby="recent"]</div>\n\n', '', 'images-slider', 'Images Slider', 1000 );	
			QTags.addButton( 'bp_testimonial-slider', 'Testimonial Slider', '  [col]\n   <h2>What Our Customers Say...</h2>\n   [get-post-slider type="testimonials" num="6" pic_size="1/3"]\n  [/col]\n\n', '', 'testimonial-slider', 'Testimonial Slider', 1000 );
			QTags.addButton( 'bp_logo-slider', 'Logo Slider', '[section name="Logo Slider" style="1" width="edge"]\n [layout]\n  [col]\n   [get-logo-slider num="-1" space="15" size="full, thumbnail, quarter-s" max_w="85" tag="featured" package="null, hvac" orderby="rand, id, title, date, modified, menu_order, recent, views" order="asc, desc" shuffle="false, true" speed="slow, fast, #" delay="0" pause="no, yes" link="false, true"]\n  [/col]\n [/layout]\n[/section]\n\n', '', 'logo-slider', 'Logo Slider', 1000 );
			QTags.addButton( 'bp_random-product', 'Random Product', '  [col]\n   <h2>Featured Product</h2>\n   [get-random-posts type="products" leeway="1" button="Learn More" orderby="views-30day" sort="desc"]\n  [/col]\n\n', '', 'random-product', 'Random Product', 1000 );		
		</script>
	<?php }
}

/*--------------------------------------------------------------
# Admin Columns Set Up
--------------------------------------------------------------*/
add_action( 'init', 'battleplan_column_settings' );
function battleplan_column_settings() {				
	if (function_exists('ac_get_site_url')) {
		ac_register_columns( 'wp-media', array(
			array(
				'columns'=>array(
					'image'=>array(
						'type'=>'column-image',
						'label'=>'Image',
						'width'=>'140',
						'width_unit'=>'px',
						'image_size'=>'cpac-custom',
						'image_size_w'=>'130',
						'image_size_h'=>'130',
						'name'=>'image',
						'label_type'=>''
					),
					'image-id'=>array(
						'type'=>'column-mediaid',
						'label'=>'ID',
						'width'=>'100',
						'width_unit'=>'px',
						'sort'=>'on',
						'name'=>'image-id',
						'label_type'=>'',
						'search'=>'on'
					),
					'filename'=>array(
						'type'=>'column-file_name',
						'label'=>'Filename',
						'width'=>'200',
						'width_unit'=>'px',
						'sort'=>'on',
						'name'=>'filename',
						'label_type'=>''
					),
					'alt-text' => array(
						'type' => 'column-alternate_text',
						'label' => 'Alt Text',
						'width' => '500',
						'width_unit' => 'px',
						'use_icons' => '',
						'name' => 'column-alternate_text',
						'label_type' => '',
						'edit' => 'on',
						'sort' => 'on',
						'filter'=>'on',
						'filter_label'=>'',
						'bulk-editing' =>'',
						'export' => '',
						'search' => ''
					),
					'date'=>array(
						'type'=>'date',
						'label'=>'Date',
						'width'=>'100',
						'width_unit'=>'px',
						'edit'=>'off',
						'sort'=>'on',
						'filter'=>'on',
						'filter_label'=>'',
						'filter_format'=>'monthly',
						'name'=>'date',
						'label_type'=>'',
						'search'=>'on',
					),
					'taxonomy-image-categories'=>array(
						'type'=>'taxonomy-image-categories',
						'label'=>'Categories',
						'width'=>'',
						'width_unit'=>'%',
						'edit'=>'on',
						'enable_term_creation'=>'on',
						'sort'=>'on',
						'filter'=>'on',
						'filter_label'=>'',
						'name'=>'taxonomy-image-categories',
						'label_type'=>'',
						'search'=>'on'
					),
					'taxonomy-image-tags'=>array(
						'type'=>'taxonomy-image-tags',
						'label'=>'Tags',
						'width'=>'',
						'width_unit'=>'%',
						'edit'=>'on',
						'enable_term_creation'=>'on',
						'sort'=>'on',
						'filter'=>'on',
						'filter_label'=>'',
						'name'=>'taxonomy-image-tags',
						'label_type'=>'',
						'search'=>'on'
					),
					'sizes'=>array(
						'type'=>'column-available_sizes',
						'label'=>'Sizes',
						'width'=>'200',
						'width_unit'=>'px',
						'include_missing_sizes'=>'',
						'sort'=>'off',
						'name'=>'sizes',
						'label_type'=>''
					)
				),
				'layout'=>array(
					'id'=>'battleplan-media-main',
					'name'=>'Main View',
					'roles'=>false,
					'users'=>false,
					'read_only'=>false
				)			
			)
		) );
		ac_register_columns( 'wp-media', array(
			array(
				'columns'=>array(
					'image'=>array(
						'type'=>'column-image',
						'label'=>'Image',
						'width'=>'140',
						'width_unit'=>'px',
						'image_size'=>'cpac-custom',
						'image_size_w'=>'130',
						'image_size_h'=>'130',
						'name'=>'image',
						'label_type'=>''
					),
					'image-id'=>array(
						'type'=>'column-mediaid',
						'label'=>'ID',
						'width'=>'100',
						'width_unit'=>'px',
						'sort'=>'on',
						'name'=>'image-id',
						'label_type'=>'',
						'search'=>'on'
					),
					'last_viewed'=>array(
						'type'=>'column-meta',
						'label'=>'Last Viewed',
						'width'=>'',
						'width_unit'=>'%',
						'field'=>'log-views-now',
						'field_type'=>'date',
						'date_format'=>'wp_default',
						'before'=>'',
						'after'=>'',
						'edit'=>'off',
						'sort'=>'on',
						'filter'=>'off',
						'filter_label'=>'',
						'filter_format'=>'monthly',
						'name'=>'last_viewed',
						'label_type'=>'',
						'search'=>'on'
					),	
					'last_teased'=>array(
						'type'=>'column-meta',
						'label'=>'Last Teased',
						'width'=>'',
						'width_unit'=>'%',
						'field'=>'log-tease-time',
						'field_type'=>'date',
						'date_format'=>'wp_default',
						'before'=>'',
						'after'=>'',
						'edit'=>'off',
						'sort'=>'on',
						'filter'=>'off',
						'filter_label'=>'',
						'filter_format'=>'monthly',
						'name'=>'last_teased',
						'label_type'=>'',
						'search'=>'on'
					),	
					'views_today'=>array(
						'type'=>'column-meta',
						'label'=>'Today',
						'width'=>'',
						'width_unit'=>'%',
						'field'=>'log-views-today',
						'field_type'=>'numeric',
						'before'=>'',
						'after'=>'',
						'edit'=>'off',
						'sort'=>'on',
						'filter'=>'off',
						'filter_label'=>'',
						'name'=>'views_today',
						'label_type'=>'',
						'search'=>'on'
					),	
					'views_week'=>array(
						'type'=>'column-meta',
						'label'=>'Week',
						'width'=>'',
						'width_unit'=>'%',
						'field'=>'log-views-total-7day',
						'field_type'=>'numeric',
						'before'=>'',
						'after'=>'',
						'edit'=>'off',
						'sort'=>'on',
						'filter'=>'off',
						'filter_label'=>'',
						'name'=>'views_week',
						'label_type'=>'',
						'search'=>'on'
					),
					'views_month'=>array(
						'type'=>'column-meta',
						'label'=>'Month',
						'width'=>'',
						'width_unit'=>'%',
						'field'=>'log-views-total-30day',
						'field_type'=>'numeric',
						'before'=>'',
						'after'=>'',
						'edit'=>'off',
						'sort'=>'on',
						'filter'=>'off',
						'filter_label'=>'',
						'name'=>'views_month',
						'label_type'=>'',
						'search'=>'on'
					),
					'views_quarter'=>array(
						'type'=>'column-meta',
						'label'=>'Quarter',
						'width'=>'',
						'width_unit'=>'%',
						'field'=>'log-views-total-90day',
						'field_type'=>'numeric',
						'before'=>'',
						'after'=>'',
						'edit'=>'off',
						'sort'=>'on',
						'filter'=>'off',
						'filter_label'=>'',
						'name'=>'views_quarter',
						'label_type'=>'',
						'search'=>'on'
					),
					'views_semester'=>array(
						'type'=>'column-meta',
						'label'=>'Semester',
						'width'=>'',
						'width_unit'=>'%',
						'field'=>'log-views-total-180day',
						'field_type'=>'numeric',
						'before'=>'',
						'after'=>'',
						'edit'=>'off',
						'sort'=>'on',
						'filter'=>'off',
						'filter_label'=>'',
						'name'=>'views_semester',
						'label_type'=>'',
						'search'=>'on'
					),
					'views_year'=>array(
						'type'=>'column-meta',
						'label'=>'Year',
						'width'=>'',
						'width_unit'=>'%',
						'field'=>'log-views-total-365day',
						'field_type'=>'numeric',
						'before'=>'',
						'after'=>'',
						'edit'=>'off',
						'sort'=>'on',
						'filter'=>'off',
						'filter_label'=>'',
						'name'=>'views_year',
						'label_type'=>'',
						'search'=>'on'
					)
				),
				'layout'=>array(
					'id'=>'battleplan-media-stats',
					'name'=>'Stats View',
					'roles'=>false,
					'users'=>false,
					'read_only'=>false
				)			
			)
		) );	
		ac_register_columns( 'elements', array(
			array(
				'columns'=>array(
					'post-id'=>array(
						'type'=>'column-postid',
						'label'=>'ID',
						'width'=>'60',
						'width_unit'=>'px',
						'before'=>'',
						'after'=>'',
						'sort'=>'on',
						'filter'=>'on',
						'filter_label'=>'',
						'name'=>'post-id',
						'label_type'=>'',
						'search'=>'on'
					),
					'title'=>array(
						'type'=>'title',
						'label'=>'Page',
						'width'=>'200',
						'width_unit'=>'px',
						'edit'=>'on',
						'sort'=>'on',
						'name'=>'title',
						'label_type'=>'',
						'search'=>'on'
					),
					'slug'=>array(
						'type'=>'column-slug',
						'label'=>'Slug',
						'width'=>'130',
						'width_unit'=>'px',
						'edit'=>'on',
						'sort'=>'on',
						'name'=>'slug',
						'label_type'=>'',
						'search'=>'on'
					),
					'last-modified'=>array(
						'type'=>'column-modified',
						'label'=>'Modified',
						'width'=>'130',
						'width_unit'=>'px',
						'date_format'=>'diff',
						'edit'=>'on',
						'sort'=>'on',
						'filter'=>'on',
						'filter_label'=>'',
						'filter_format'=>'monthly',
						'name'=>'last-modified',
						'label_type'=>'',
						'search'=>'on'
					),
					'date-published'=>array(
						'type'=>'column-date_published',
						'label'=>'Published',
						'width'=>'130',
						'width_unit'=>'px',
						'date_format'=>'wp_default',
						'edit'=>'on',
						'sort'=>'on',
						'filter'=>'on',
						'filter_label'=>'',
						'filter_format'=>'monthly',
						'name'=>'date-published',
						'label_type'=>'',
						'search'=>'on'
					),
					'attachments'=>array(
						'type'=>'column-attachment',
						'label'=>'Attachments',
						'width'=>'400',
						'width_unit'=>'px',
						'attachment_display'=>'thumbnail',
						'image_size'=>'cpac-custom',
						'image_size_w'=>'60',
						'image_size_h'=>'60',
						'number_of_items'=>'10',
						'edit'=>'on',
						'sort'=>'on',
						'name'=>'attachments',
						'label_type'=>''
					)
				),
				'layout'=>array(
					'id'=>'battleplan-elements-main',
					'name'=>'Main View',
					'roles'=>false,
					'users'=>false,
					'read_only'=>false
				)			
			)
		) );	
		ac_register_columns( 'page', array(
			array(
				'columns'=>array(
					'post-id'=>array(
						'type'=>'column-postid',
						'label'=>'ID',
						'width'=>'100',
						'width_unit'=>'px',
						'before'=>'',
						'after'=>'',
						'sort'=>'on',
						'filter'=>'on',
						'filter_label'=>'',
						'name'=>'post-id',
						'label_type'=>'',
						'search'=>'on'
					),
					'title'=>array(
						'type'=>'title',
						'label'=>'Page',
						'width'=>'200',
						'width_unit'=>'px',
						'edit'=>'on',
						'sort'=>'on',
						'name'=>'title',
						'label_type'=>'',
						'search'=>'on'
					),
					'slug'=>array(
						'type'=>'column-slug',
						'label'=>'Slug',
						'width'=>'130',
						'width_unit'=>'px',
						'edit'=>'on',
						'sort'=>'on',
						'name'=>'slug',
						'label_type'=>'',
						'search'=>'on'
					),
					'top-exists'=>array(
						'type'=>'column-meta',
						'label'=>'Top',
						'width'=>'60',
						'width_unit'=>'px',
						'field'=>'page-top_text',
						'field_type'=>'has_content',
						'before'=>'',
						'after'=>'',
						'sort'=>'on',
						'filter'=>'on',
						'filter_label'=>'',
						'name'=>'top-exists',
						'label_type'=>'',
					),
					'bottom-exists'=>array(
						'type'=>'column-meta',
						'label'=>'Bottom',
						'width'=>'80',
						'width_unit'=>'px',
						'field'=>'page-bottom_text',
						'field_type'=>'has_content',
						'before'=>'',
						'after'=>'',
						'sort'=>'on',
						'filter'=>'on',
						'filter_label'=>'',
						'name'=>'bottom-exists',
						'label_type'=>'',
					),
					'last-modified'=>array(
						'type'=>'column-modified',
						'label'=>'Modified',
						'width'=>'130',
						'width_unit'=>'px',
						'date_format'=>'diff',
						'edit'=>'on',
						'sort'=>'on',
						'filter'=>'on',
						'filter_label'=>'',
						'filter_format'=>'monthly',
						'name'=>'last-modified',
						'label_type'=>'',
						'search'=>'on'
					),
					'date-published'=>array(
						'type'=>'column-date_published',
						'label'=>'Published',
						'width'=>'130',
						'width_unit'=>'px',
						'date_format'=>'wp_default',
						'edit'=>'on',
						'sort'=>'on',
						'filter'=>'on',
						'filter_label'=>'',
						'filter_format'=>'monthly',
						'name'=>'date-published',
						'label_type'=>'',
						'search'=>'on'
					),
					'attachments'=>array(
						'type'=>'column-attachment',
						'label'=>'Attachments',
						'width'=>'400',
						'width_unit'=>'px',
						'attachment_display'=>'thumbnail',
						'image_size'=>'cpac-custom',
						'image_size_w'=>'60',
						'image_size_h'=>'60',
						'number_of_items'=>'10',
						'edit'=>'on',
						'sort'=>'on',
						'name'=>'attachments',
						'label_type'=>''
					)
				),
				'layout'=>array(
					'id'=>'battleplan-pages-main',
					'name'=>'Main View',
					'roles'=>false,
					'users'=>false,
					'read_only'=>false
				)			
			)
		) );	
		ac_register_columns( 'testimonials', array(
			array(
				'columns'=>array(
					'featured-image'=>array(
						'type'=>'column-featured_image',
						'label'=>'',
						'width'=>'5',
						'width_unit'=>'%',
						'featured_image_display'=>'image',
						'image_size'=>'cpac-custom',
						'image_size_w'=>'60',
						'image_size_h'=>'60',
						'edit'=>'off',
						'sort'=>'off',
						'filter'=>'off',
						'filter_label'=>'',
						'name'=>'featured-image',
						'label_type'=>'',
						'search'=>'on'
					),	
					'post-id'=>array(
						'type'=>'column-postid',
						'label'=>'ID',
						'width'=>'100',
						'width_unit'=>'px',
						'before'=>'',
						'after'=>'',
						'sort'=>'on',
						'filter'=>'on',
						'filter_label'=>'',
						'name'=>'post-id',
						'label_type'=>'',
						'search'=>'on'
					),
					'title'=>array(
						'type'=>'title',
						'label'=>'Name',
						'width'=>'200',
						'width_unit'=>'px',
						'edit'=>'on',
						'sort'=>'on',
						'name'=>'title',
						'label_type'=>'',
						'search'=>'on'
					),	
					'date-published'=>array(
						'type'=>'column-date_published',
						'label'=>'Published',
						'width'=>'',
						'width_unit'=>'%',
						'date_format'=>'wp_default',
						'edit'=>'off',
						'sort'=>'on',
						'filter'=>'on',
						'filter_label'=>'',
						'filter_format'=>'monthly',
						'name'=>'date-published',
						'label_type'=>'',
						'search'=>'on'
					),		
					'rating'=>array(
						'type'=>'column-meta',
						'label'=>'Rating',
						'width'=>'',
						'width_unit'=>'%',
						'field'=>'testimonial_rating',
						'field_type'=>'numeric',
						'before'=>'',
						'after'=>'',
						'edit'=>'off',
						'sort'=>'on',
						'filter'=>'on',
						'filter_label'=>'',
						'name'=>'rating',
						'label_type'=>'',
						'editable_type'=>'textarea',
						'search'=>'on'
					),
					'platform'=>array(
						'type'=>'column-meta',
						'label'=>'Platform',
						'width'=>'',
						'width_unit'=>'%',
						'field'=>'testimonial_platform',
						'field_type'=>'',
						'before'=>'',
						'after'=>'',
						'sort'=>'on',
						'filter'=>'on',
						'filter_label'=>'',
						'name'=>'platform',
						'label_type'=>'',
						'search'=>'on'
					),
					'location'=>array(
						'type'=>'column-meta',
						'label'=>'Location',
						'width'=>'',
						'width_unit'=>'%',
						'field'=>'testimonial_location',
						'field_type'=>'checkmark',
						'before'=>'',
						'after'=>'',
						'sort'=>'on',
						'filter'=>'on',
						'filter_label'=>'',
						'name'=>'location',
						'label_type'=>'',
						'search'=>'on'
					),
					'business'=>array(
						'type'=>'column-meta',
						'label'=>'Business',
						'width'=>'',
						'width_unit'=>'%',
						'field'=>'testimonial_biz',
						'field_type'=>'checkmark',
						'before'=>'',
						'after'=>'',
						'sort'=>'on',
						'filter'=>'on',
						'filter_label'=>'',
						'name'=>'business',
						'label_type'=>'',
						'search'=>'on'
					),
					'website'=>array(
						'type'=>'column-meta',
						'label'=>'Website',
						'width'=>'',
						'width_unit'=>'%',
						'field'=>'testimonial_website',
						'field_type'=>'checkmark',
						'before'=>'',
						'after'=>'',
						'sort'=>'on',
						'filter'=>'on',
						'filter_label'=>'',
						'name'=>'website',
						'label_type'=>'',
						'search'=>'on'
					)
				),
				'layout'=>array(
					'id'=>'battleplan-testimonials-main',
					'name'=>'Main View',
					'roles'=>false,
					'users'=>false,
					'read_only'=>false
				)			
			)
		) );
		ac_register_columns( 'galleries', array(
			array(
				'columns'=>array(
					'featured-image'=>array(
						'type'=>'column-featured_image',
						'label'=>'',
						'width'=>'80',
						'width_unit'=>'px',
						'featured_image_display'=>'image',
						'image_size'=>'cpac-custom',
						'image_size_w'=>'60',
						'image_size_h'=>'60',
						'edit'=>'off',
						'sort'=>'off',
						'filter'=>'off',
						'filter_label'=>'',
						'name'=>'featured-image',
						'label_type'=>'',
						'search'=>'on'
					),
					'post-id'=>array(
						'type'=>'column-postid',
						'label'=>'ID',
						'width'=>'100',
						'width_unit'=>'px',
						'before'=>'',
						'after'=>'',
						'sort'=>'on',
						'filter'=>'on',
						'filter_label'=>'',
						'name'=>'post-id',
						'label_type'=>'',
						'search'=>'on'
					),
					'title'=>array(
						'type'=>'title',
						'label'=>'Title',
						'width'=>'200',
						'width_unit'=>'px',
						'edit'=>'on',
						'sort'=>'on',
						'name'=>'title',
						'label_type'=>'',
						'search'=>'on'
					),
					'slug'=>array(
						'type'=>'column-slug',
						'label'=>'Slug',
						'width'=>'15',
						'width_unit'=>'%',
						'edit'=>'on',
						'sort'=>'on',
						'name'=>'slug',
						'label_type'=>'',
						'search'=>'on'
					),
					'gallery-type'=>array(
						'type'=>'column-taxonomy',
						'label'=>'Type',
						'width'=>'',
						'width_unit'=>'%',
						'taxonomy'=>'gallery-type',
						'edit'=>'on',
						'enable_term_creation'=>'on',
						'sort'=>'on',
						'filter'=>'on',
						'filter_label'=>'',
						'name'=>'gallery-type',
						'label_type'=>'',
						'search'=>'on'
					),
					'last-modified'=>array(
						'type'=>'column-modified',
						'label'=>'Modified',
						'width'=>'',
						'width_unit'=>'%',
						'date_format'=>'diff',
						'edit'=>'on',
						'sort'=>'on',
						'filter'=>'on',
						'filter_label'=>'',
						'filter_format'=>'monthly',
						'name'=>'last-modified',
						'label_type'=>'',
						'search'=>'on'
					),
					'date-published'=>array(
						'type'=>'column-date_published',
						'label'=>'Published',
						'width'=>'',
						'width_unit'=>'%',
						'date_format'=>'wp_default',
						'edit'=>'on',
						'sort'=>'on',
						'filter'=>'on',
						'filter_label'=>'',
						'filter_format'=>'monthly',
						'name'=>'date-published',
						'label_type'=>'',
						'search'=>'on'
					),
					'gallery-tags'=>array(
						'type'=>'column-taxonomy',
						'label'=>'Gallery Tags',
						'width'=>'',
						'width_unit'=>'%',
						'taxonomy'=>'gallery-tags',
						'edit'=>'on',
						'enable_term_creation'=>'on',
						'sort'=>'on',
						'filter'=>'on',
						'filter_label'=>'',
						'name'=>'gallery-tags',
						'label_type'=>'',
						'search'=>'on'
					),
					'menu-order'=>array(
						'type'=>'column-order',
						'label'=>'Order',
						'width'=>'100',
						'width_unit'=>'px',
						'edit'=>'on',
						'enable_term_creation'=>'on',
						'sort'=>'on',
						'filter'=>'on',
						'filter_label'=>'',
						'name'=>'menu-order',
						'label_type'=>'',
						'search'=>''
					)
				),
				'layout'=>array(
					'id'=>'battleplan-galleries-main',
					'name'=>'Main View',
					'roles'=>false,
					'users'=>false,
					'read_only'=>false
				)		
			)
		) );	
		ac_register_columns( 'optimized', array(
			array(
				'columns'=>array(
					'post-id'=>array(
						'type'=>'column-postid',
						'label'=>'ID',
						'width'=>'100',
						'width_unit'=>'px',
						'before'=>'',
						'after'=>'',
						'sort'=>'on',
						'filter'=>'on',
						'filter_label'=>'',
						'name'=>'post-id',
						'label_type'=>'',
						'search'=>'on'
					),
					'title'=>array(
						'type'=>'title',
						'label'=>'Page',
						'width'=>'200',
						'width_unit'=>'px',
						'edit'=>'on',
						'sort'=>'on',
						'name'=>'title',
						'label_type'=>'',
						'search'=>'on'
					),
					'slug'=>array(
						'type'=>'column-slug',
						'label'=>'Slug',
						'width'=>'130',
						'width_unit'=>'px',
						'edit'=>'on',
						'sort'=>'on',
						'name'=>'slug',
						'label_type'=>'',
						'search'=>'on'
					),
					'top-exists'=>array(
						'type'=>'column-meta',
						'label'=>'Top',
						'width'=>'60',
						'width_unit'=>'px',
						'field'=>'page-top_text',
						'field_type'=>'has_content',
						'before'=>'',
						'after'=>'',
						'sort'=>'on',
						'filter'=>'on',
						'filter_label'=>'',
						'name'=>'top-exists',
						'label_type'=>'',
					),
					'bottom-exists'=>array(
						'type'=>'column-meta',
						'label'=>'Bottom',
						'width'=>'80',
						'width_unit'=>'px',
						'field'=>'page-bottom_text',
						'field_type'=>'has_content',
						'before'=>'',
						'after'=>'',
						'sort'=>'on',
						'filter'=>'on',
						'filter_label'=>'',
						'name'=>'bottom-exists',
						'label_type'=>'',
					),
					'last-modified'=>array(
						'type'=>'column-modified',
						'label'=>'Modified',
						'width'=>'130',
						'width_unit'=>'px',
						'date_format'=>'diff',
						'edit'=>'on',
						'sort'=>'on',
						'filter'=>'on',
						'filter_label'=>'',
						'filter_format'=>'monthly',
						'name'=>'last-modified',
						'label_type'=>'',
						'search'=>'on'
					),
					'date-published'=>array(
						'type'=>'column-date_published',
						'label'=>'Published',
						'width'=>'130',
						'width_unit'=>'px',
						'date_format'=>'wp_default',
						'edit'=>'on',
						'sort'=>'on',
						'filter'=>'on',
						'filter_label'=>'',
						'filter_format'=>'monthly',
						'name'=>'date-published',
						'label_type'=>'',
						'search'=>'on'
					),
					'attachments'=>array(
						'type'=>'column-attachment',
						'label'=>'Attachments',
						'width'=>'400',
						'width_unit'=>'px',
						'attachment_display'=>'thumbnail',
						'image_size'=>'cpac-custom',
						'image_size_w'=>'60',
						'image_size_h'=>'60',
						'number_of_items'=>'10',
						'edit'=>'on',
						'sort'=>'on',
						'name'=>'attachments',
						'label_type'=>''
					)
				),
				'layout'=>array(
					'id'=>'battleplan-optimized-main',
					'name'=>'Main View',
					'roles'=>false,
					'users'=>false,
					'read_only'=>false
				)			
			)
		) );

		$getCPT = get_post_types(); 
		foreach ($getCPT as $postType) :
			$exclude = array( "elements", "page", "testimonials", "galleries", "optimized", "products", "dogs", "litters", "resources", "tribe_events", "product", "shop_order" );
			if ( !in_array( $postType, $exclude) ):
				ac_register_columns( $postType, array(
					array(
						'columns'=>array(
							'featured-image'=>array(
								'type'=>'column-featured_image',
								'label'=>'',
								'width'=>'80',
								'width_unit'=>'px',
								'featured_image_display'=>'image',
								'image_size'=>'cpac-custom',
								'image_size_w'=>'60',
								'image_size_h'=>'60',
								'edit'=>'off',
								'sort'=>'off',
								'filter'=>'off',
								'filter_label'=>'',
								'name'=>'featured-image',
								'label_type'=>'',
								'search'=>'on'
							),
							'post-id'=>array(
								'type'=>'column-postid',
								'label'=>'ID',
								'width'=>'100',
								'width_unit'=>'px',
								'before'=>'',
								'after'=>'',
								'sort'=>'on',
								'filter'=>'on',
								'filter_label'=>'',
								'name'=>'post-id',
								'label_type'=>'',
								'search'=>'on'
							),
							'title'=>array(
								'type'=>'title',
								'label'=>'Title',
								'width'=>'200',
								'width_unit'=>'px',
								'edit'=>'on',
								'sort'=>'on',
								'name'=>'title',
								'label_type'=>'',
								'search'=>'on'
							),
							'column-slug'=>array(
								'type'=>'column-slug',
								'label'=>'Slug',
								'width'=>'15',
								'width_unit'=>'%',
								'edit'=>'on',
								'sort'=>'on',
								'name'=>'column-slug',
								'label_type'=>'',
								'search'=>'on'
							),
							'last-modified'=>array(
								'type'=>'column-modified',
								'label'=>'Modified',
								'width'=>'130',
								'width_unit'=>'px',
								'date_format'=>'diff',
								'edit'=>'on',
								'sort'=>'on',
								'filter'=>'on',
								'filter_label'=>'',
								'filter_format'=>'monthly',
								'name'=>'last-modified',
								'label_type'=>'',
								'search'=>'on'
							),
							'date-published'=>array(
								'type'=>'column-date_published',
								'label'=>'Published',
								'width'=>'130',
								'width_unit'=>'px',
								'date_format'=>'wp_default',
								'edit'=>'on',
								'sort'=>'on',
								'filter'=>'on',
								'filter_label'=>'',
								'filter_format'=>'monthly',
								'name'=>'date-published',
								'label_type'=>'',
								'search'=>'on'
							),
							'categories'=>array(
								'type'=>'categories',
								'label'=>'Categories',
								'width'=>'100',
								'width_unit'=>'px',
								'edit'=>'on',
								'enable_term_creation'=>'on',
								'sort'=>'on',
								'filter'=>'on',
								'name'=>'categories',
								'label_type'=>'',
								'filter_label'=>'',
								'search'=>'on'
							),
							'tags'=>array(
								'type'=>'tags',
								'label'=>'Tags',
								'width'=>'100',
								'width_unit'=>'px',
								'edit'=>'on',
								'enable_term_creation'=>'on',
								'sort'=>'on',
								'filter'=>'on',
								'filter_label'=>'',
								'name'=>'tags',
								'label_type'=>'',
								'search'=>'on'
							),
							'author'=>array(
								'type'=>'author',
								'label'=>'Author',
								'width'=>'',
								'width_unit'=>'%',
								'edit'=>'on',
								'sort'=>'on',
								'name'=>'author',
								'label_type'=>'',
								'search'=>'on'
							),
							'menu-order'=>array(
								'type'=>'column-order',
								'label'=>'Order',
								'width'=>'100',
								'width_unit'=>'px',
								'edit'=>'on',
								'enable_term_creation'=>'on',
								'sort'=>'on',
								'filter'=>'on',
								'filter_label'=>'',
								'name'=>'menu-order',
								'label_type'=>'',
								'search'=>''
							)
						),
						'layout'=>array(
							'id'=>'battleplan-'.$postType.'-main',
							'name'=>'Main View',
							'roles'=>false,
							'users'=>false,
							'read_only'=>false
						)			
					)
				) );
			endif;
			$exclude = array( "elements", "shop_order" );
			if ( !in_array( $postType, $exclude) ):
				ac_register_columns( $postType, array(
					array(
						'columns'=>array(
							'featured-image'=>array(
								'type'=>'column-featured_image',
								'label'=>'',
								'width'=>'80',
								'width_unit'=>'px',
								'featured_image_display'=>'image',
								'image_size'=>'cpac-custom',
								'image_size_w'=>'60',
								'image_size_h'=>'60',
								'edit'=>'off',
								'sort'=>'off',
								'filter'=>'off',
								'filter_label'=>'',
								'name'=>'featured-image',
								'label_type'=>'',
								'search'=>'on'
							),
							'post-id'=>array(
								'type'=>'column-postid',
								'label'=>'ID',
								'width'=>'100',
								'width_unit'=>'px',
								'before'=>'',
								'after'=>'',
								'sort'=>'on',
								'filter'=>'on',
								'filter_label'=>'',
								'name'=>'post-id',
								'label_type'=>'',
								'search'=>'on'
							),
							'title'=>array(
								'type'=>'title',
								'label'=>'Title',
								'width'=>'200',
								'width_unit'=>'px',
								'edit'=>'on',
								'sort'=>'on',
								'name'=>'title',
								'label_type'=>'',
								'search'=>'on'
							),
							'last_viewed'=>array(
								'type'=>'column-meta',
								'label'=>'Last Viewed',
								'width'=>'',
								'width_unit'=>'%',
								'field'=>'log-views-now',
								'field_type'=>'date',
								'date_format'=>'wp_default',
								'before'=>'',
								'after'=>'',
								'edit'=>'off',
								'sort'=>'on',
								'filter'=>'off',
								'filter_label'=>'',
								'filter_format'=>'monthly',
								'name'=>'last_viewed',
								'label_type'=>'',
								'search'=>'on'
							),	
							'last_teased'=>array(
								'type'=>'column-meta',
								'label'=>'Last Teased',
								'width'=>'',
								'width_unit'=>'%',
								'field'=>'log-tease-time',
								'field_type'=>'date',
								'date_format'=>'wp_default',
								'before'=>'',
								'after'=>'',
								'edit'=>'off',
								'sort'=>'on',
								'filter'=>'off',
								'filter_label'=>'',
								'filter_format'=>'monthly',
								'name'=>'last_teased',
								'label_type'=>'',
								'search'=>'on'
							),	
							'views_today'=>array(
								'type'=>'column-meta',
								'label'=>'Today',
								'width'=>'',
								'width_unit'=>'%',
								'field'=>'log-views-today',
								'field_type'=>'numeric',
								'before'=>'',
								'after'=>'',
								'edit'=>'off',
								'sort'=>'on',
								'filter'=>'off',
								'filter_label'=>'',
								'name'=>'views_today',
								'label_type'=>'',
								'search'=>'on'
							),	
							'views_week'=>array(
								'type'=>'column-meta',
								'label'=>'Week',
								'width'=>'',
								'width_unit'=>'%',
								'field'=>'log-views-total-7day',
								'field_type'=>'numeric',
								'before'=>'',
								'after'=>'',
								'edit'=>'off',
								'sort'=>'on',
								'filter'=>'off',
								'filter_label'=>'',
								'name'=>'views_week',
								'label_type'=>'',
								'search'=>'on'
							),
							'views_month'=>array(
								'type'=>'column-meta',
								'label'=>'Month',
								'width'=>'',
								'width_unit'=>'%',
								'field'=>'log-views-total-30day',
								'field_type'=>'numeric',
								'before'=>'',
								'after'=>'',
								'edit'=>'off',
								'sort'=>'on',
								'filter'=>'off',
								'filter_label'=>'',
								'name'=>'views_month',
								'label_type'=>'',
								'search'=>'on'
							),
							'views_quarter'=>array(
								'type'=>'column-meta',
								'label'=>'Quarter',
								'width'=>'',
								'width_unit'=>'%',
								'field'=>'log-views-total-90day',
								'field_type'=>'numeric',
								'before'=>'',
								'after'=>'',
								'edit'=>'off',
								'sort'=>'on',
								'filter'=>'off',
								'filter_label'=>'',
								'name'=>'views_quarter',
								'label_type'=>'',
								'search'=>'on'
							),
							'views_semester'=>array(
								'type'=>'column-meta',
								'label'=>'Semester',
								'width'=>'',
								'width_unit'=>'%',
								'field'=>'log-views-total-180day',
								'field_type'=>'numeric',
								'before'=>'',
								'after'=>'',
								'edit'=>'off',
								'sort'=>'on',
								'filter'=>'off',
								'filter_label'=>'',
								'name'=>'views_semester',
								'label_type'=>'',
								'search'=>'on'
							),
							'views_year'=>array(
								'type'=>'column-meta',
								'label'=>'Year',
								'width'=>'',
								'width_unit'=>'%',
								'field'=>'log-views-total-365day',
								'field_type'=>'numeric',
								'before'=>'',
								'after'=>'',
								'edit'=>'off',
								'sort'=>'on',
								'filter'=>'off',
								'filter_label'=>'',
								'name'=>'views_year',
								'label_type'=>'',
								'search'=>'on'
							)
						),
						'layout'=>array(
							'id'=>'battleplan-'.$postType.'-stats',
							'name'=>'Stats View',
							'roles'=>false,
							'users'=>false,
							'read_only'=>false
						)			
					)
				) );
			endif;				
			ac_register_columns( 'wp-users', array(
			array(
				'columns'=>array(
					'username'=>array(
						'type'=>'username',
						'label'=>'Username',
						'width'=>'170',
						'width_unit'=>'px',
						'edit'=>'on',
						'sort'=>'on',
						'name'=>'username',
						'label_type'=>'',
						'filter'=>'on',
						'filter_label'=>'',
						'bulk-editing' =>'',
						'search'=>'on'
					),
					'first_name'=>array(
						'type'=>'column-first_name',
						'label'=>'First Name',
						'width'=>'170',
						'width_unit'=>'px',
						'edit'=>'on',
						'sort'=>'on',
						'name'=>'first_name',
						'label_type'=>'',
						'filter'=>'on',
						'filter_label'=>'',
						'bulk-editing' =>'',
						'search'=>'on'
					),		
					'last_name'=>array(
						'type'=>'column-last_name',
						'label'=>'Last Name',
						'width'=>'170',
						'width_unit'=>'px',
						'edit'=>'on',
						'sort'=>'on',
						'name'=>'last_name',
						'label_type'=>'',
						'filter'=>'on',
						'filter_label'=>'',
						'bulk-editing' =>'',
						'search'=>'on',
					),			
					'display_name'=>array(
						'type'=>'column-display_name',
						'label'=>'Display Name',
						'width'=>'170',
						'width_unit'=>'px',
						'edit'=>'on',
						'sort'=>'on',
						'name'=>'display_name',
						'edit'=>'on',
						'sort'=>'on',
						'filter'=>'on',
						'filter_label'=>'',
						'bulk-editing' =>'on',
						'search'=>'on'
					),		
					'email'=>array(
						'type'=>'email',
						'label'=>'Email',
						'width'=>'200',
						'width_unit'=>'px',
						'edit'=>'on',
						'sort'=>'on',
						'name'=>'email',
						'label_type'=>'',
						'filter'=>'on',
						'filter_label'=>'',
						'bulk-editing' =>'',
						'search'=>'on'
					), 
					'user_registered'=>array(
						'type'=>'column-user_registered',
						'label'=>'Registered',
						'width'=>'130',
						'width_unit'=>'px',
						'date_format'=>'wp_default',
						'edit'=>'on',
						'sort'=>'on',
						'filter'=>'on',
						'filter_label'=>'',
						'filter_format'=>'monthly',
						'name'=>'user_registered',
						'label_type'=>'',
						'search'=>'on'
					),
					'role'=>array(
						'type'=>'role',
						'label'=>'Role',
						'width'=>'200',
						'width_unit'=>'px',
						'edit'=>'on',
						'sort'=>'on',
						'name'=>'role',
						'label_type'=>'',
						'filter'=>'on',
						'filter_label'=>'',
						'bulk-editing' =>'',
						'search'=>'on'
					),
					'posts'=>array(
						'type'=>'posts',
						'label'=>'Posts',
						'width'=>'200',
						'width_unit'=>'px',
						'edit'=>'on',
						'sort'=>'on',
						'name'=>'posts',
						'label_type'=>'',
						'filter'=>'on',
						'filter_label'=>'',
						'bulk-editing' =>'',
						'search'=>'on'
					),
				),
				'layout'=>array(
					'id'=>'battleplan-media-main',
					'name'=>'Main View',
					'roles'=>false,
					'users'=>false,
					'read_only'=>false
				)			
			)
		) );				
		endforeach;
	}
}

/*--------------------------------------------------------------
# Admin Interface Set Up
--------------------------------------------------------------*/

// Disable Gutenburg
add_filter( 'use_block_editor_for_post', '__return_false' );
add_filter( 'gutenberg_use_widgets_block_editor', '__return_false' );
add_filter( 'wp_use_widgets_block_editor', '__return_false' );

// Disable Visual Editor
add_filter( 'user_can_richedit' , '__return_false', 50 );

// Add, Remove and Reorder Items in Admin Bar
add_action( 'wp_before_admin_bar_render', 'battleplan_reorderAdminBar');
function battleplan_reorderAdminBar() {
    global $wp_admin_bar;
	
	if (get_bloginfo( 'description' )) $wp_admin_bar->add_node( array( 'id' => 'tagline', 'title' => '-&nbsp;&nbsp;'.get_bloginfo( 'description' ).'&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;', 'href'  => esc_url(site_url()), ) );	
	
    $IDs_sequence = array('site-name', 'tagline', 'suspend' );
    $nodes = $wp_admin_bar->get_nodes();
    foreach ( $IDs_sequence as $id ) {
        if ( ! isset($nodes[$id]) ) continue;
        $wp_admin_bar->remove_menu($id);
        $wp_admin_bar->add_node($nodes[$id]);
        unset($nodes[$id]);
    }
    foreach ( $nodes as $id => &$obj ) {
        if ( ! empty($obj->parent) ) continue;
        $wp_admin_bar->remove_menu($id);
        $wp_admin_bar->add_node($obj);
    }
	$wp_admin_bar->remove_node('wp-logo');
	$wp_admin_bar->remove_node('wphb');
	$wp_admin_bar->remove_node('updates');
    $wp_admin_bar->remove_node('comments');
    $wp_admin_bar->remove_node('new-content');
    $wp_admin_bar->remove_node('wpengine_adminbar');
	$wp_admin_bar->remove_node('view-site');	
	$wp_admin_bar->remove_node('wpseo-menu');	
}

// Create additional admin pages
add_action( 'admin_menu', 'battleplan_admin_menu' );
function battleplan_admin_menu() {
	add_menu_page( __( 'Run Chron', 'battleplan' ), __( 'Run Chron', 'battleplan' ), 'manage_options', 'run-chron', 'battleplan_force_run_chron', 'dashicons-performance', 3 );
	//add_submenu_page( 'tools.php', 'Clear Stats', 'Clear Stats', 'manage_options', 'battleplan_force_run_chron' );	
}

function battleplan_addSitePage() { 
	echo '<h1>Admin Page</h1>';
}

// Replace WordPress copyright message at bottom of admin page
add_filter('admin_footer_text', 'battleplan_admin_footer_text');
function battleplan_admin_footer_text() { 
	$printFooter = '<div style="float:left; margin-right:8px;"><img src="https://battleplanwebdesign.com/wp-content/uploads/site-icon-80x80.png" /></div>';
	$printFooter .= '<div style="float:left; margin-top:8px;">Powered by <a href="https://battleplanwebdesign.com" target="_blank">Battle Plan Web Design</a><br>';
	$printFooter .= 'Periodic Review: '.date('F j, Y', get_option( 'site_updated' )).'<br>'; 
	$printFooter .= 'Framework '._BP_VERSION.'<br>';
	$printFooter .= 'WP '.get_bloginfo('version').'<br></div>';
	
	$printFooter .= '<div style="float:right; margin-right: 50px">';	
	$printFooter .= get_option('customer_info')['area-before'].get_option('customer_info')['area'].get_option('customer_info')['area-after'].get_option('customer_info')['phone'].'<br>';
	$printFooter .= get_option('customer_info')['street'].'<br>';
	$printFooter .= get_option('customer_info')['city'].', '.get_option('customer_info')['state-abbr'].' '.get_option('customer_info')['zip'].'<br>';
	if ( get_option('customer_info')['lat'] ) $printFooter .= get_option('customer_info')['lat'].', '.get_option('customer_info')['long'].', '.get_option('customer_info')['radius'].'<br>';	
	$printFooter .= '</div><div style="float:right; margin-right: 50px">';
	
	if ( get_option('customer_info')['pid'] ) $printFooter .= '<b>PID:</b> <a href = "https://search.google.com/local/writereview?placeid='.get_option('customer_info')['pid'].'" target="_blank">'.get_option('customer_info')['pid'].'</a><br>';
	$printFooter .= '<b>Email:</b> <a href = "mailto:'.get_option('customer_info')['email'].'">'.get_option('customer_info')['email'].'</a><br>';
	if ( get_option('customer_info')['owner-email'] ) $printFooter .= '<b>Owner:</b> <a href = "mailto:'.get_option('customer_info')['owner-email'].'">'.get_option('customer_info')['owner-email'].'</a><br>';	
	if ( get_option('customer_info')['facebook'] ) $printFooter .= '<b>Facebook:</b> <a href = "'.get_option('customer_info')['facebook'].'" target="_blank">'.get_option('customer_info')['facebook'].'</a><br>';
	if ( get_option('customer_info')['twitter'] ) $printFooter .= '<b>Twitter:</b> <a href = "'.get_option('customer_info')['twitter'].'" target="_blank">'.get_option('customer_info')['twitter'].'</a><br>';
	if ( get_option('customer_info')['instagram'] ) $printFooter .= '<b>Instagram:</b> <a href = "'.get_option('customer_info')['instagram'].'" target="_blank">'.get_option('customer_info')['instagram'].'</a><br>';
	if ( get_option('customer_info')['pinterest'] ) $printFooter .= '<b>Pinterest:</b> <a href = "'.get_option('customer_info')['pinterest'].'" target="_blank">'.get_option('customer_info')['pinterest'].'</a><br>';
	if ( get_option('customer_info')['yelp'] ) $printFooter .= '<b>Yelp:</b> <a href = "'.get_option('customer_info')['yelp'].'" target="_blank">'.get_option('customer_info')['yelp'].'</a><br>';
	if ( get_option('customer_info')['tiktok'] ) $printFooter .= '<b>Tiktok:</b> <a href = "'.get_option('customer_info')['tiktok'].'" target="_blank">'.get_option('customer_info')['tiktok'].'</a><br>';
	if ( get_option('customer_info')['youtube'] ) $printFooter .= '<b>You Tube:</b> <a href = "'.get_option('customer_info')['youtube'].'" target="_blank">'.get_option('customer_info')['youtube'].'</a><br>';
	
	if ( get_option('customer_info')['google-tags']['prop-id'] ) $printFooter .= '<b>Analytics:</b> <a href = "https://analytics.google.com/analytics/web/#/p'.get_option('customer_info')['google-tags']['prop-id'].'/reports/explorer?params=_u..nav%3Dmaui%26_u..pageSize%3D25%26_r.explorerCard..selmet%3D%5B%22sessions%22%5D%26_r.explorerCard..seldim%3D%5B%22sessionDefaultChannelGrouping%22%5D&r=lifecycle-traffic-acquisition-v2&collectionId=life-cycle" target="_blank">View Stats</a><br>';
		
	$printFooter .= '</div>';
	
	echo $printFooter;
}

// Change Howdy text
add_filter( 'admin_bar_menu', 'battleplan_replace_howdy', 25 );
function battleplan_replace_howdy( $wp_admin_bar ) {
	 $my_account=$wp_admin_bar->get_node('my-account');
	 $newtitle = str_replace( 'Howdy,', 'Welcome,', $my_account->title );
	 $wp_admin_bar->add_node( array( 'id'=>'my-account', 'title'=>$newtitle, ) );
 }

// Remove https://domain.com, add aspect-ratio - <img> inserted by WordPress
add_filter( 'image_send_to_editor', 'battleplan_remove_junk_from_image', 10 );
function battleplan_remove_junk_from_image( $html ) {
	$pattern = '/(<img.*)width="(\d+)" height="(\d+)"(.*class=")(.*)" \/(>)/';
	$style = '$1class="$5" width="$2" height="$3" style="aspect-ratio:$2/$3" />';
	$html = preg_replace($pattern, $style, $html);
	$html = str_replace( get_site_url(), "", $html );

   return $html;
}

// Set the quality of compression on various WordPress generated image sizes
function av_return_100(){ return 67; }
add_filter('jpeg_quality', 'av_return_100', 9999);
add_filter('wp_editor_set_quality', 'av_return_100', 9999);

// Display custom fields in WordPress admin edit screen
//add_filter('acf/settings/remove_wp_meta_box', '__return_false');

// Add & Remove WP Admin Menu items
add_action( 'admin_init', 'battleplan_remove_menus', 999 );
function battleplan_remove_menus() {   
	remove_menu_page( 'link-manager.php' );       									//Links
	remove_menu_page( 'edit-comments.php' );       									//Comments	
	remove_menu_page( 'wpcf7' );       												//Contact Forms	
	remove_menu_page( 'edit.php?post_type=acf-field-group' );       				//Custom Fields
	remove_menu_page( 'themes.php' );       										//Appearance
	remove_menu_page( 'ari-fancy-lightbox' );       								//ARI Fancy Lightbox
	remove_submenu_page( 'plugins.php', 'plugin-editor.php' );        				//Plugins => Plugin Editor
	remove_submenu_page( 'options-general.php', 'options-writing.php' );   			//Settings => Writing 		
	remove_submenu_page( 'options-general.php', 'options-reading.php' );   			//Settings => Reading 	
	remove_submenu_page( 'options-general.php', 'options-media.php' );   			//Settings => Media 	
	remove_submenu_page( 'options-general.php', 'options-privacy.php' );   			//Settings => Privacy 	
	remove_submenu_page( 'options-general.php', 'akismet-key-config' );   			//Settings => Akismet	
	remove_submenu_page( 'options-general.php', 'srs-config' );   					//Settings => Referral Spam 	
	remove_submenu_page( 'options-general.php', 'widgetopts_plugin_settings' );   	//Settings => Widget Options
	remove_submenu_page( 'tools.php', 'export-personal-data.php' );   				//Tools => Export Personal Data  
	remove_submenu_page( 'tools.php', 'erase-personal-data.php' );   				//Tools => Erase Personal Data
	remove_submenu_page( 'wpseo_dashboard', 'wpseo_workouts' );   					//Yoast SEO => Workouts
	remove_submenu_page( 'wpseo_dashboard', 'wpseo_licenses' );   					//Yoast SEO => Premium
	remove_submenu_page( 'wpseo_dashboard', 'wpseo_redirects' );   					//Yoast SEO => Redirects

	add_submenu_page( 'upload.php', 'Favicon', 'Favicon', 'manage_options', 'customize.php' );	
	add_submenu_page( 'edit.php?post_type=elements', 'Menus', 'Menus', 'manage_options', 'nav-menus.php' );	
	add_submenu_page( 'edit.php?post_type=elements', 'Widgets', 'Widgets', 'manage_options', 'widgets.php' );	
	add_submenu_page( 'edit.php?post_type=elements', 'Contact Forms', 'Contact Forms', 'manage_options', 'admin.php?page=wpcf7' );	
	add_submenu_page( 'edit.php?post_type=elements', 'Comments', 'Comments', 'manage_options', 'edit-comments.php' );
	add_submenu_page( 'edit.php?post_type=elements', 'Custom Fields', 'Custom Fields', 'manage_options', 'edit.php?post_type=acf-field-group' );		
	add_submenu_page( 'edit.php?post_type=elements', 'Themes', 'Themes', 'manage_options', 'themes.php' );		
	if ( is_plugin_active( 'ari-fancy-lightbox/ari-fancy-lightbox.php' ) ) { add_submenu_page( 'options-general.php', 'Lightbox', 'Lightbox', 'manage_options', 'admin.php?page=ari-fancy-lightbox' );	 }		
	add_submenu_page( 'options-general.php', 'Options', 'Options', 'manage_options', 'options.php' );
	add_submenu_page( 'tools.php', 'Git Updater', 'Git Updater', 'manage_options', 'options-general.php?page=git-updater' );
}

// Reorder WP Admin Menu Items
add_filter( 'custom_menu_order', 'battleplan_custom_menu_order', 10, 1 );
add_filter( 'menu_order', 'battleplan_custom_menu_order', 10, 1 );
function battleplan_custom_menu_order( $menu_ord ) {
    if ( !$menu_ord ) return true;	
	$displayTypes = array('index.php', 'separator1', 'upload.php', 'edit.php?post_type=elements', 'edit.php?post_type=page');
	$getCPT = getCPT();
	foreach ($getCPT as $postType) {
		array_push($displayTypes, 'edit.php?post_type='.$postType);
	}
	array_push($displayTypes, 'edit.php', 'separator2', 'plugins.php', 'options-general.php', 'tools.php', 'users.php', 'separator-last', 'wpengine-common', 'wpseo_dashboard', 'edit.php?post_type=asp-products');	
	return $displayTypes;
}

// Reorder WP Admin Sub-Menu Items
add_filter( 'custom_menu_order', 'battleplan_submenu_order' );
function battleplan_submenu_order( $menu_ord ) {
    global $submenu;	
    $arr = array();
    $arr[] = $submenu['options-general.php'][10];     
    $arr[] = $submenu['options-general.php'][15];
    $arr[] = $submenu['options-general.php'][20];
    $arr[] = $submenu['options-general.php'][25];
    $arr[] = $submenu['options-general.php'][30];
    $arr[] = $submenu['options-general.php'][40];
    $arr[] = $submenu['options-general.php'][45];
    $arr[] = $submenu['options-general.php'][49];
    $arr[] = $submenu['options-general.php'][46];
    $arr[] = $submenu['options-general.php'][48];
    $arr[] = $submenu['options-general.php'][47];
    $submenu['options-general.php'] = $arr;

    return $menu_ord;
}

// Remove unwanted widgets from Elements
add_action('widgets_init', 'battleplan_unregister_default_widgets', 11);
function battleplan_unregister_default_widgets() {
	unregister_widget('Akismet_Widget');
	unregister_widget('WP_Widget_Custom_HTML');	
	unregister_widget('WP_Widget_Links');
	unregister_widget('WP_Widget_Media_Audio');
	unregister_widget('WP_Widget_Media_Gallery');
	unregister_widget('WP_Widget_Media_Image');
	unregister_widget('WP_Widget_Media_Video');
	unregister_widget('WP_Widget_Meta');
	unregister_widget('WP_Widget_Pages');
	unregister_widget('WP_Widget_Recent_Comments');
	unregister_widget('WP_Widget_Recent_Posts');
	unregister_widget('WP_Widget_RSS');
	unregister_widget('WP_Widget_Tag_Cloud');
	unregister_widget('WPE_Powered_By_Widget');
	unregister_widget('Twenty_Eleven_Ephemera_Widget');
}

// Remove unwanted dashboard widgets
add_action('wp_dashboard_setup', 'battleplan_remove_dashboard_widgets');
function battleplan_remove_dashboard_widgets () {
	remove_action('welcome_panel','wp_welcome_panel'); 							//Welcome to WordPress!
	remove_meta_box('dashboard_primary','dashboard','normal'); 					//WordPress.com Blog
	remove_meta_box('dashboard_primary','dashboard','side'); 					//WordPress.com Blog
	remove_meta_box('dashboard_right_now','dashboard','normal');	
	remove_meta_box('dashboard_right_now','dashboard','side');
	remove_meta_box('dashboard_quick_press','dashboard','normal'); 				//Quick Press widget
	remove_meta_box('dashboard_quick_press','dashboard','side'); 				//Quick Press widget
	remove_meta_box('tribe_dashboard_widget', 'dashboard', 'normal'); 			//News From Modern Tribe	
	remove_meta_box('tribe_dashboard_widget', 'dashboard', 'side'); 			//News From Modern Tribe
	remove_meta_box('wpe_dify_news_feed','dashboard','normal'); 				//WP Engine	
	remove_meta_box('wpe_dify_news_feed','dashboard','side'); 					//WP Engine
	remove_meta_box('dashboard_activity','dashboard','normal');					//Activity
	remove_meta_box('dashboard_activity','dashboard','side');					//Activity
	remove_meta_box('dashboard_site_health','dashboard','normal');				//Site Health
	remove_meta_box('dashboard_site_health','dashboard','side');				//Site Health
	remove_meta_box('woocommerce_dashboard_status','dashboard','normal');		//Woocommerce
	remove_meta_box('woocommerce_dashboard_status','dashboard','side');			//Woocommerce
	remove_meta_box('wp_mail_smtp_reports_widget_lite','dashboard','normal');	//WP Mail SMTP
	remove_meta_box('wp_mail_smtp_reports_widget_lite','dashboard','side');		//WP Mail SMTP
	remove_meta_box('wpseo-dashboard-overview','dashboard','normal');			//Yoast
	remove_meta_box('wpseo-dashboard-overview','dashboard','side');				//Yoast	
}

// Add new dashboard widgets
add_action( 'wp_dashboard_setup', 'battleplan_add_dashboard_widgets' );
function battleplan_add_dashboard_widgets() {
	add_meta_box( 'battleplan_site_stats', 'Site Visitors', 'battleplan_admin_site_stats', 'dashboard', 'normal', 'high' );				
	add_meta_box( 'battleplan_tech_stats', 'Tech Info', 'battleplan_admin_tech_stats', 'dashboard', 'normal', 'high' );		

	add_meta_box( 'battleplan_referrer_stats', 'Referrers', 'battleplan_admin_referrer_stats', 'dashboard', 'side', 'high' );	
	add_meta_box( 'battleplan_location_stats', 'Locations', 'battleplan_admin_location_stats', 'dashboard', 'side', 'high' );
	add_meta_box( 'battleplan_pages_stats', 'Most Popular Pages', 'battleplan_admin_pages_stats', 'dashboard', 'side', 'high' );
	
	add_meta_box( 'battleplan_weekly_stats', 'Weekly Visitor Trends', 'battleplan_admin_weekly_stats', 'dashboard', 'column3', 'high' );		
	add_meta_box( 'battleplan_monthly_stats', 'Monthly Visitor Trends', 'battleplan_admin_monthly_stats', 'dashboard', 'column3', 'high' );		
	add_meta_box( 'battleplan_quarterly_stats', 'Quarterly Visitor Trends', 'battleplan_admin_quarterly_stats', 'dashboard', 'column3', 'high' );
}


// Set up Site Visitors widget on dashboard
function battleplan_admin_site_stats() {
	$getViews = array_reverse(get_option('stats_basic'));
 	$count = $views = $search = $pageviews = $sessions = $engaged = $engagement = $endOfCol = 0;

	for ($x = 0; $x < 1096; $x++) {	
		$dailyTime = date("M j, Y", strtotime($getViews[$x]['date'])); 
		$dailyViews = intval($getViews[$x]['users']); 
		
		$count++;
		$views = $views + $dailyViews;	
		
		if ( $count == 1 ) $viewsToday = $views; 
		if ( $count == 7 ) $last7Views = $views; $last7Avg = number_format(($last7Views / 7),1);
		if ( $count == 30 ) $last30Views = $views; $last30Avg = number_format(($last30Views / 30),1);
		if ( $count == 90 ) $last90Views = $views; $last90Avg = number_format(($last90Views / 90),1);
		if ( $count == 180 ) $last180Views = $views; $last180Avg = number_format(($last180Views / 180),1);		
		if ( $count == 365 ) $lastYearViews = $views; $lastYearAvg = number_format(($lastYearViews / 365),1);		
		if ( $count == 730 ) $last2YearViews = $views; $last2YearAvg = number_format(($last2YearViews / 730),1);		
		if ( $count == 1095 ) $last3YearViews = $views; $last3YearAvg = number_format(($last3YearViews / 1095),1);
	} 		
		
	echo "<table><tr><td class='label'>Yesterday</td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $viewsToday, 'battleplan' ), number_format($viewsToday))."</td></tr>";	
	
	echo "<tr><td class='label'>This Week</td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $last7Views, 'battleplan' ), number_format($last7Views) )."</td><td><b>".$last7Avg."</b> /day</td></tr>";
	
	if ( $last30Views != $last7Views) echo "<tr><td class='label'>This Month</td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $last30Views, 'battleplan' ), number_format($last30Views) )."</td><td><b>".$last30Avg."</b> /day</td></tr>";
	
	if ( $last90Views != $last30Views) echo "<tr><td class='label'>3 Months</td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $last90Views, 'battleplan' ), number_format($last90Views) )."</td><td><b>".$last90Avg."</b> /day</td></tr>";
	
	if ( $last180Views != $last90Views) echo "<tr><td class='label'>6 Months</td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $last180Views, 'battleplan' ), number_format($last180Views) )."</td><td><b>".$last180Avg."</b> /day</td></tr>";
	
	if ( $lastYearViews != $last180Views) echo "<tr><td class='label'>1 Year</td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $lastYearViews, 'battleplan' ), number_format($lastYearViews) )."</td><td><b>".$lastYearAvg."</b> /day</td></tr>";
	
	if ( $last2YearViews != $lastYearViews) echo "<tr><td class='label'>2 Years</td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $last2YearViews, 'battleplan' ), number_format($last2YearViews) )."</td><td><b>".$last2YearAvg."</b> /day</td></tr>";
	
	if ( $last3YearViews != $last2YearViews) echo "<tr><td class='label'>3 Years</td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $last3YearViews, 'battleplan' ), number_format($last3YearViews) )."</td><td><b>".$last3YearAvg."</b> /day</td></tr>";
	
	echo '<tr><td>&nbsp;</td></tr></table>';
}

// Set up Tech Info widget on dashboard
function battleplan_admin_tech_stats() {
	$getStats = get_option('stats_tech');

//Devices	
	$deviceStats = $getStats['device'];
	$deviceNum = count($deviceStats);	
	$tallyCounts = array_count_values($deviceStats);
	$uniqueTech = array_unique($deviceStats);
	$combineTech = [];	
	$totalPagesForSpeed = $deviceNum * 3;
	
	foreach ($uniqueTech as $uniqueTech) :
		$combineTech[$uniqueTech]=(($tallyCounts[$uniqueTech]/$deviceNum)*100);
	endforeach; 	
	
	uksort( $combineTech, function($a, $b) use ($combineTech) { return [$combineTech[$b], $a] <=> [$combineTech[$a], $b]; } );
	
	$timesDesktop = get_option('load_time_desktop');
	$timeDesktop = array_sum($timesDesktop) / count($timesDesktop);	
	$timesMobile = get_option('load_time_mobile');
	$timeMobile = array_sum($timesMobile) / count($timesMobile);		
		
	echo '<div><ul><li class="sub-label">Devices</li>';
	foreach ($combineTech as $device=>$deviceNum) :
		if ( $device == "desktop" ) :
			$deviceSpeed = $timeDesktop;			
			$getDesktopViews = round(($totalPagesForSpeed * $deviceNum) /100);	
			$timesDesktop = array_slice($timesDesktop, 0, $getDesktopViews); 
			update_option('load_time_desktop', $timesDesktop);
		else:
			$deviceSpeed = $timeMobile;
			$getMobileViews = round(($totalPagesForSpeed * $deviceNum) /100);		
			$timesMobile = array_slice($timesMobile, 0, $getMobileViews); 
			update_option('load_time_mobile', $timesMobile);
		endif;
		echo "<li><div class='value'><b>".number_format($deviceNum,1)."%</b></div><div class='label-half'>".ucwords($device)."</div><div class='label-half'>".number_format($deviceSpeed,2)." sec.</div></li>";
	endforeach; 	
	echo '</ul></div>';
	
// Scrolling Pct
	$contentTracking = get_option('content-tracking');
	$columnScroll = get_option('content-column-views');	
	$contentScroll = get_option('content-scroll-pct');
	$contentScrollNum = count($contentScroll);	
	$contentScrollTotal = array_sum($contentScroll);	
	$contentScrollMean = ( $contentScrollTotal / $contentScrollNum ) * 100;

	foreach ($contentScroll as $key=>$scroll) :
		$contentScrollValues .= (round($scroll,1) * 100) .',';
	endforeach;
	
	$contentScrollMode = array_count_values(explode(",", $contentScrollValues));  
	arsort($contentScrollMode); 
		
	foreach ( $columnScroll as $columns ) :
		$colViews = $columns['viewed'];
		$colTotal = $columns['total'];
		
		for($x=0;$x<$colViews;$x++) :
			if ( $col[$x]['viewed'] ) : 
				$col[$x]['viewed'] = $col[$x]['viewed'] + 1;
			else: 
				$col[$x]['viewed'] = 1; 
			endif;
		endfor;
		
		for($x=0;$x<$colTotal;$x++) :
			if ( $col[$x]['total'] ) : 
				$col[$x]['total'] = $col[$x]['total'] + 1;
			else: 
				$col[$x]['total'] = 1; 
			endif;
		endfor;	
	endforeach;	
	
	$visitsTracked = count($contentTracking);
	foreach ($contentTracking as $visitor) :
		foreach ($visitor as $key=>$tracking) :
			
		
		
		
		
		endforeach;
	endforeach;
		
	echo '<div><ul><li class="sub-label">Content</li>';
		echo "<li><div class='value'><b>".number_format($contentScrollMean,1)."%</b></div><div class='label'>Mean Viewed (".$contentScrollNum." pages)</div></li>";		
		echo "<li><div class='value'><b>".array_key_first($contentScrollMode)."%</b></div><div class='label'>Mode Viewed (".$contentScrollNum." pages)</div></li>";	
		
		echo "<br><div style='column-count:2'>";
		foreach($col as $theCol) :
			$colNum++;
			$findPct = round( (($theCol['viewed'] / $theCol['total']) * 100) ,1);
			echo "<li><div class='value'><b>".$findPct."%</b></div><div class='label'>Col #".$colNum."</div></li>";	
		endforeach;		
		echo "</div><br>";		
		
		

		//echo "<li><div class='value'><b>".$findPct."%</b></div><div class='label'>Column #".$visitsTracked."</div></li>";	


		
	echo '</ul></div>';

//Browser
	$browserStats = $getStats['browser'];
	$browserNum = count($browserStats);	
	$tallyCounts = array_count_values($browserStats);
	$uniqueTech = array_unique($browserStats);
	$combineTech = [];	
	
	foreach ($uniqueTech as $uniqueTech) :
		$combineTech[$uniqueTech]=(($tallyCounts[$uniqueTech]/$browserNum)*100);
	endforeach; 	
	
	uksort( $combineTech, function($a, $b) use ($combineTech) { return [$combineTech[$b], $a] <=> [$combineTech[$a], $b]; } );
		
	echo '<div><ul><li class="sub-label">Browser</li>';
	foreach ($combineTech as $browser=>$browserNum) :
		echo "<li><div class='value'><b>".number_format($browserNum,1)."%</b></div><div class='label'>".$browser."</div></li>";
	endforeach; 	
	echo '</ul></div>';
	
// Screen Resolution	
	$resolutionStats = $getStats['resolution'];
	$resolutionNum = count($resolutionStats);	
	foreach ($resolutionStats as $resolution) :
		$resolution = substr($resolution, 0, strpos($resolution, 'x'));
		$newResolution[] = $resolution;
	endforeach; 	

	$tallyCounts = array_count_values($newResolution);
	$uniqueTech = array_unique($newResolution);
	$combineTech = [];
	
	foreach ($uniqueTech as $uniqueTech) :
		$combineTech[$uniqueTech]=(($tallyCounts[$uniqueTech]/$resolutionNum)*100);
	endforeach; 	
	
	uksort( $combineTech, function($a, $b) use ($combineTech) { return [$combineTech[$b], $a] <=> [$combineTech[$a], $b]; } );
		
	echo '<div><ul><li class="sub-label">Screen Width</li>';
	foreach ($combineTech as $resolution=>$resolutionNum) :
		echo "<li><div class='value'><b>".number_format($resolutionNum,1)."%</b></div><div class='label'>".$resolution." px</div></li>";
	endforeach; 	
	echo '</ul></div>';
}


// Set up Visitor Referrers widget on dashboard
function battleplan_admin_referrer_stats() {
	$getStats = get_option('stats_referrers');
	$referNum = count($getStats);
	$thisDomain = str_replace("https://", "", get_site_url());
	
	foreach ($getStats as $getStat) :
		$getStat = str_replace("https://", "", $getStat);		
		$getStat = str_replace("www.", "", $getStat);		
		$getStat = substr($getStat, 0, strpos($getStat, '/'));
		if ( $getStat == $thisDomain ) $getStat = "Self";
		if ( $getStat == '' ) $getStat = "Direct";		
		$newStats[] = $getStat;
	endforeach; 	
	
	$tallyCounts = array_count_values($newStats);
	$uniqueReferrers = array_unique($newStats);
	$combineReferrers = [];
	
	foreach ($uniqueReferrers as $uniqueReferrer) :
		$combineReferrers[$uniqueReferrer]=$tallyCounts[$uniqueReferrer];
	endforeach; 	
	
	uksort( $combineReferrers, function($a, $b) use ($combineReferrers) { return [$combineReferrers[$b], $a] <=> [$combineReferrers[$a], $b]; } );
		
	echo '<div class="handle-label" data-label=" - Last '.number_format($referNum).' Visitors"><ul>';
	foreach ($combineReferrers as $referrer=>$referNum) :
		if ( $referrer == "" ) $referrer = "Direct";
		if ( $referrer == $thisDomain ) $referrer = "Self";
		echo "<li><div class='value'><b>".number_format($referNum)."</b></div><div class='label'>".$referrer."</div></li>";
	endforeach; 	
	echo '</ul></div>';
}

// Set up Visitor Locations widget on dashboard
function battleplan_admin_location_stats() {
	$getStats = get_option('stats_locations');
	$locNum = count($getStats);
	$tallyCounts = array_count_values($getStats);
	$uniqueLocs = array_unique($getStats);
	$combineLocs = [];
	
	foreach ($uniqueLocs as $uniqueLoc) :
		$combineLocs[$uniqueLoc]=$tallyCounts[$uniqueLoc];
	endforeach; 	
	
	uksort( $combineLocs, function($a, $b) use ($combineLocs) { return [$combineLocs[$b], $a] <=> [$combineLocs[$a], $b]; } );
		
	echo '<div class="handle-label" data-label=" - Last '.number_format($locNum).' Visitors"><ul>';
	foreach ($combineLocs as $city=>$cityNum) :
		echo "<li><div class='value'><b>".number_format($cityNum)."</b></div><div class='label'>".$city."</div></li>";
	endforeach; 	
	echo '</ul></div>';
}

// Set up Page Visits widget on dashboard
function battleplan_admin_pages_stats() {
	$getCPT = getCPT(); 
	
	echo '<div class="handle-label" data-label=" - Last 90 Days"><ul>';

	foreach ($getCPT as $postType) :
		$printType = ucwords($postType);
		if ( $postType == 'post' || $postType == 'page' ) $printType .= 's';
		echo '</ul><ul><li class="sub-label">'.$printType.'</li>';
		$getPosts = new WP_Query( array ('posts_per_page'=>-1, 'post_type'=>$postType, 'meta_key' => 'log-views-total-90day', 'orderby' => 'meta_value_num', 'order' => 'DESC' ));
		if ( $getPosts->have_posts() ) : while ( $getPosts->have_posts() ) : $getPosts->the_post(); 
			echo "<li><div class='value'><b>".readMeta(get_the_ID(), 'log-views-total-90day')."</b></div><div class='label'>".get_the_title()."</div></li>";
		endwhile; wp_reset_postdata(); endif;		
	endforeach;

	echo '</ul></div>';
} 

// Set up Visitor Trends widget on dashboard
function battleplan_admin_stats($time,$minDays,$maxDays,$colEnd) {
	$getViews = array_reverse(get_option('stats_basic'));
 	$count = $views = $search = $pageviews = $sessions = $engaged = $engagement = $endOfCol = 0;
	$days = $minDays;
	
	echo "<table class='trends trends-".$time."'><tr><td class='header dates'>".ucfirst($time)."</td><td class='page visits'>Visitors</td></tr>";		
	for ($x = 0; $x < 1460; $x++) {	
		$dailyTime = date("M j, Y", strtotime($getViews[$x]['date'])); 
		$dailyViews = intval($getViews[$x]['users']); 
		$dailySearch = intval($getViews[$x]['search']); 
		$dailyPageviews = intval($getViews[$x]['pageviews']); 
		$dailySessions = intval($getViews[$x]['sessions']); 
		$dailyEngaged = intval($getViews[$x]['engaged']); 
		
		$count++;
		$views = $views + $dailyViews;	
		$search = $search + $dailySearch; 
		$pageviews = $pageviews + $dailyPageviews; 
		$sessions = $sessions + $dailySessions; 
		$engaged = $engaged + $dailyEngaged; 
		if ( $views > 0 ) $pageperuser = number_format( (round(($pageviews / $views), 3)) , 1, '.', '');
		if ( $sessions > 0 ) $engagement = number_format( ((round(($engaged / $sessions), 3)) * 100), 1, '.', '');
								
		if ( $count == 1 ) $end = $dailyTime;
		if ( $count == $days && $dailyTime != "Jan 1, 1970" ) :
			if ( $endOfCol == $colEnd ) :
				echo "</table><table class='trends trends-".$time."'><tr><td class='header dates'>".ucfirst($time)."</td><td class='page visits'>Visitors</td></tr>";
				$endOfCol = 0;
			endif;
			$endOfCol++;

		 	echo "<tr class='coloration trends users active' data-count='".$views."'><td class='dates'>".$end."</td><td class='visits'><b>".number_format($views)."</b></td></tr>";
			echo "<tr class='coloration trends search' data-count='".$search."'><td class='dates'>".$end."</td><td class='visits'><b>".$search."</b></td></tr>";
			echo "<tr class='coloration trends pageviews' data-count='".$pageperuser."'><td class='dates'>".$end."</td><td class='visits'><b>".$pageperuser."</b></td></tr>";
			echo "<tr class='coloration trends engagement' data-count='".$engagement."'><td class='dates'>".$end."</td><td class='visits'><b>".$engagement."%</b></td></tr>";
 			$count = $views = $search = $pageviews = $sessions = $engaged = $engagement = 0;
			if ( $days == $maxDays ) : $days = $minDays; else: $days = $maxDays; endif;
		endif;	
	} 		
	echo "</table>";
}

function battleplan_admin_weekly_stats() {
	echo '<div class="trend-buttons">';
		echo do_shortcode('[btn size="1/4" class="users active"]Visitors[/btn]');
		echo do_shortcode('[btn size="1/4" class="search"]Search[/btn]');
		echo do_shortcode('[btn size="1/4" class="pageviews"]Pageviews[/btn]');
		echo do_shortcode('[btn size="1/4" class="engagement"]Engagement[/btn]');
	echo '</div>';

	battleplan_admin_stats('weekly',7,7,52);
}
	
function battleplan_admin_monthly_stats() { battleplan_admin_stats('monthly',30,31,12); }
function battleplan_admin_quarterly_stats() { battleplan_admin_stats('quarterly',91,92,4); }

// Add custom meta boxes to posts & pages
add_action("add_meta_boxes", "battleplan_add_custom_meta_boxes");
function battleplan_add_custom_meta_boxes() {
	$getCPT = getCPT();
	foreach ( $getCPT as $postType ) :
		add_meta_box("page-stats-box", "Page Stats", "battleplan_page_stats", $postType, "side", "default", null);
    endforeach;
}

// Set up Page Stats widget on posts & pages
function battleplan_page_stats() {
	global $post;
	$rightNow = strtotime(date("F j, Y g:i a"));
	$today = strtotime(date("F j, Y"));
	$lastViewed = strtotime(readMeta($post->ID, 'log-views-now'));		
	$getViews = readMeta($post->ID, 'log-views');
	$getViews = maybe_unserialize( $getViews );
	//$viewsToday = $getViews[0]['views'];
	//$firstDate = strtotime($getViews[0]['date']);
	if ( $firstDate != $today ) $viewsToday = 0;
	$last7Views = (int)readMeta($post->ID, "log-views-total-7day");
	$last30Views = (int)readMeta($post->ID, "log-views-total-30day");
	$last90Views = (int)readMeta($post->ID, "log-views-total-90day");	
	$last180Views = (int)readMeta($post->ID, "log-views-total-180day");	
	$last365Views = (int)readMeta($post->ID, "log-views-total-365day");
	$dateDiff = (($rightNow - $lastViewed) / 60 / 60 / 24); $howLong = "day";
	if ( $dateDiff < 1 ) : $dateDiff = (($rightNow - $lastViewed) / 60 / 60); $howLong = "hour"; endif;	
	if ( $dateDiff < 1 ) : $dateDiff = (($rightNow - $lastViewed) / 60); $howLong = "minute"; endif;
	if ( $dateDiff != 1 ) $howLong = $howLong."s";	
	$dateDiff = number_format($dateDiff, 0);	
	
	echo "<table>";		
	echo "<tr><td><b>Last Viewed</b></td><td><b>".$dateDiff."</b> ".$howLong." ago</td></tr>";	
	echo "<tr><td>&nbsp;</td></tr>";		
	echo "<tr><td><b>Today</b></td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $viewsToday, 'battleplan' ), number_format($viewsToday) )."</td></tr>";	
	echo "<tr><td><b>Last 7 Days</b></td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $last7Views, 'battleplan' ), number_format($last7Views) )."</td></tr>";
	if ( $last30Views != $last7Views) echo "<tr><td><b>Last 30 Days</b></td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $last30Views, 'battleplan' ), number_format($last30Views) )."</td></tr>";
	if ( $last90Views != $last30Views) echo "<tr><td><b>Last 90 Days</b></td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $last90Views, 'battleplan' ), number_format($last90Views) )."</td></tr>";
	if ( $last180Views != $last90Views) echo "<tr><td><b>Last 180 Days</b></td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $last180Views, 'battleplan' ), number_format($last180Views) )."</td></tr>";
	if ( $last365Views != $last180Views) echo "<tr><td><b>Last 365 Days</b></td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $last365Views, 'battleplan' ), number_format($last365Views) )."</td></tr>";
	echo "</table>";		
}

// Add "Remove Sidebar" checkbox to Page Attributes meta box
add_action( 'page_attributes_misc_attributes', 'battleplan_remove_sidebar_checkbox', 10, 1 );
function battleplan_remove_sidebar_checkbox($post) { 
	echo '<p class="post-attributes-label-wrapper">';
	$getRemoveSidebar = get_post_meta($post->ID, "_bp_remove_sidebar", true);

	if ( $getRemoveSidebar == "" ) : echo '<input name="remove_sidebar" type="checkbox" value="true">';
	else: echo '<input name="remove_sidebar" type="checkbox" value="true" checked>';
	endif;	
	
	echo '<label class="post-attributes-label" for="remove_sidebar">Remove Sidebar</label>';
} 
	 
add_action('save_post', 'battleplan_save_remove_sidebar', 10, 3);
function battleplan_save_remove_sidebar($post_id, $post, $update) {
	if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return $post_id;
	if ( defined('DOING_AJAX') && DOING_AJAX ) return $post_id;
    if ( !current_user_can("edit_post", $post_id) ) return $post_id;

    $updateRemoveSidebar = "";
    if ( isset($_POST["remove_sidebar"]) ) $updateRemoveSidebar = $_POST["remove_sidebar"];   
    update_post_meta($post_id, "_bp_remove_sidebar", $updateRemoveSidebar);
}

// Add "duplicate post/page" function to WP core
add_action( 'admin_action_battleplan_duplicate_post_as_draft', 'battleplan_duplicate_post_as_draft' );
function battleplan_duplicate_post_as_draft(){
	global $wpdb;
	if (! ( isset( $_GET['post']) || isset( $_POST['post'])  || ( isset($_REQUEST['action']) && 'battleplan_duplicate_post_as_draft' == $_REQUEST['action'] ) ) ) {
		wp_die('No post to duplicate has been supplied!');
	}
	if ( !isset( $_GET['duplicate_nonce'] ) || !wp_verify_nonce( $_GET['duplicate_nonce'], basename( __FILE__ ) ) )
		return;
	
	$post_id = (isset($_GET['post']) ? absint( $_GET['post'] ) : absint( $_POST['post'] ) );
	$post = get_post( $post_id );
	$current_user = wp_get_current_user();
	$new_post_author = $current_user->ID;
	if (isset( $post ) && $post != null) {
		$args = array(
			'comment_status' => $post->comment_status,
			'ping_status'    => $post->ping_status,
			'post_author'    => $new_post_author,
			'post_content'   => $post->post_content,
			'post_excerpt'   => $post->post_excerpt,
			'post_name'      => $post->post_name,
			'post_parent'    => $post->post_parent,
			'post_password'  => $post->post_password,
			'post_status'    => 'draft',
			'post_title'     => $post->post_title,
			'post_type'      => $post->post_type,
			'to_ping'        => $post->to_ping,
			'menu_order'     => $post->menu_order
		);
		$new_post_id = wp_insert_post( $args );
		$taxonomies = get_object_taxonomies($post->post_type);
		foreach ($taxonomies as $taxonomy) {
			$post_terms = wp_get_object_terms($post_id, $taxonomy, array('fields' => 'slugs'));
			wp_set_object_terms($new_post_id, $post_terms, $taxonomy, false);
		}
		$post_meta_infos = $wpdb->get_results("SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id=$post_id");
		if (count($post_meta_infos)!=0) {
			$sql_query = "INSERT INTO $wpdb->postmeta (post_id, meta_key, meta_value) ";
			foreach ($post_meta_infos as $meta_info) {
				$meta_key = $meta_info->meta_key;
				if( $meta_key == '_wp_old_slug' ) continue;
				$meta_value = addslashes($meta_info->meta_value);
				$sql_query_sel[]= "SELECT $new_post_id, '$meta_key', '$meta_value'";
			}
			$sql_query.= implode(" UNION ALL ", $sql_query_sel);
			$wpdb->query($sql_query);
		}
		
		wp_redirect( admin_url( 'post.php?action=edit&post=' . $new_post_id ) );
		exit;
	} else {
		wp_die('Post creation failed, could not find original post: ' . $post_id);
	}
}
  
// Replace Page & Post links with icons
add_filter( 'post_row_actions', 'battleplan_post_row_actions', 90, 2 );
add_filter( 'page_row_actions', 'battleplan_post_row_actions', 90, 2 );
function battleplan_post_row_actions( $actions, $post ) {
	$edit = str_replace( "Edit", "<i class='dashicons-edit'></i>", $actions['edit'] );
	$view = str_replace( "View", "<i class='dashicons-view'></i>", $actions['view'] );
	$view = str_replace( "Preview", "<i class='dashicons-view'></i>", $view );
	$delete = str_replace( "Trash", "<i class='dashicons-trash'></i>", $actions['trash'] );

	$edit = str_replace( "<a href", "<a title='Edit' target='_blank' href", $edit );
	$clone = '<a target="_blank" href="' . wp_nonce_url('admin.php?action=battleplan_duplicate_post_as_draft&post=' . $post->ID, basename(__FILE__), 'duplicate_nonce' ) . '" title="Clone" rel="permalink"><i class="dashicons-clone"></i></a>';
	$view = str_replace( "<a href", "<a title='View' target='_blank' href", $view );	
	$delete = str_replace( "<a href", "<a title='Delete' href", $delete );
	$quickEdit = '<button type="button" class="button-link editinline" aria-label="Quick edit" aria-expanded="false"><i class="dashicons-quick-edit"></i></button>';

	return array( 'edit' => $edit, 'inline hide-if-no-js' => $quickEdit, 'duplicate' => $clone, 'view' => $view, 'delete' => $delete );
}

// Replace Media Library image links with icons
add_filter('media_row_actions', 'battleplan_media_row_actions', 90, 2);
function battleplan_media_row_actions( $actions, $post ) {
	$edit = str_replace( "Edit", "<i class='dashicons-edit'></i>", $actions['edit'] );
	$view = str_replace( "View", "<i class='dashicons-view'></i>", $actions['view'] );
	$adddata = str_replace( "Replace media", "<i class='dashicons-replace'></i>", $actions['adddata'] );
	$delete = str_replace( "Delete Permanently", "<i class='dashicons-trash'></i>", $actions['delete'] );
	
	$edit = str_replace( "<a href", "<a title='Edit Media' target='_blank' href", $edit );
	$view = str_replace( "<a href", "<a title='View Media' target='_blank' href", $view );
	$adddata = str_replace( "<a href", "<a title='Replace Media' target='_blank' href", $adddata );
	$delete = str_replace( "<a href", "<a title='Delete Media' href", $delete );
	
	return array( 'edit' => $edit, 'view' => $view, 'adddata' => $adddata, 'delete' => $delete );
} 

// Replace Users links with icons
add_filter( 'user_row_actions', 'battleplan_user_row_actions', 90, 2 );
function battleplan_user_row_actions( $actions, $post ) {
	$edit = str_replace( "Edit", "<i class='dashicons-edit'></i>", $actions['edit'] );
	$delete = str_replace( "Delete", "<i class='dashicons-trash'></i>", $actions['delete'] );
	$switch = str_replace( "Switch&nbsp;To", "<i class='dashicons-randomize'></i>", $actions['switch_to_user'] );

	return array( 'edit' => $edit, 'delete' => $delete, 'switch_to_user' => $switch );
}

// Automatically set the image Title, Alt-Text, Caption & Description upon upload
add_action( 'add_attachment', 'battleplan_setImageMetaUponUpload' );
function battleplan_setImageMetaUponUpload( $post_ID ) {
	if ( wp_attachment_is_image( $post_ID ) ) {
		$imageTitle = get_post( $post_ID )->post_title;
		$imageTitle = ucwords( preg_replace( '%\s*[-_\s]+\s*%', ' ', $imageTitle )); // remove hyphens, underscores & extra spaces and capitalize
		$imageMeta = array ( 'ID' => $post_ID, 'post_title' => $imageTitle ) /* post title */;			 
		update_post_meta( $post_ID, '_wp_attachment_image_alt', $imageTitle ) /* alt text */;
		wp_update_post( $imageMeta );
	} 
}

// Add 'log-views' fields to an image when it is uploaded
add_action( 'add_attachment', 'battleplan_addWidgetPicViewsToImg' );
function battleplan_addWidgetPicViewsToImg( $post_ID ) {
	if ( wp_attachment_is_image( $post_ID ) ) {		
		updateMeta( $post_ID, 'log-views-now', strtotime("-1 day"));		
		updateMeta( $post_ID, 'log-views-time', strtotime("-1 day"));		
		updateMeta( $post_ID, 'log-tease-time', strtotime("-1 day"));		
		updateMeta( $post_ID, 'log-views-total-7day', '0' );		
		updateMeta( $post_ID, 'log-views-total-30day', '0' );
		updateMeta( $post_ID, 'log-views-total-90day', '0' );
		updateMeta( $post_ID, 'log-views-total-180day', '0' );
		updateMeta( $post_ID, 'log-views-total-365day', '0' );
		updateMeta( $post_ID, 'log-views', array( 'date'=> strtotime(date("F j, Y")), 'views' => 0 ));					
	} 
}

// Force clear all views for posts/pages
function battleplan_force_run_chron() {
	update_option('bp_chrons_last_run', 0);	
	header("Location: /wp-admin/index.php");
	exit();
}  

/*--------------------------------------------------------------
# Contact Form 7 Set Up
--------------------------------------------------------------*/

	// add Something to Status box
    //add_action( 'wpcf7_admin_misc_pub_section', 'bp_cf7_add_to_status_box', 10, 0 ); 
    function bp_cf7_add_to_status_box(  ) { 
        echo '';
    }; 	
	
	// add Something after Save button
    //add_action( 'wpcf7_admin_footer', 'bp_cf7_add_after_save_button', 10, 0 ); 
    function bp_cf7_add_after_save_button(  ) { 
        echo '';
    }; 

	// add Something on Mail tab, before TO field
    //add_action( 'wpcf7_collect_mail_tags', 'bp_cf7_add_before_to_field', 10, 0 ); 
    function bp_cf7_add_before_to_field(  ) { 
        echo '';
    }; 

	// add Something before Panel tabs
   // add_filter( 'wpcf7_editor_panels', 'bp_cf7_add_before_panel_tabs', 10, 1 ); 
	function bp_cf7_add_before_panel_tabs( $panels ) { 
        echo ''; 
    }; 
	
/*--------------------------------------------------------------
# Set Global Options
--------------------------------------------------------------*/
add_action( 'admin_init', 'battleplan_setupGlobalOptions', 999 );
function battleplan_setupGlobalOptions() {  

// WP Mail SMTP Settings
	if ( is_plugin_active('wp-mail-smtp/wp_mail_smtp.php') && get_option( 'bp_setup_wp_mail_smtp_initial' ) != 'completed' ) : 	
		$apiKey1 = "keysib";
		$apiKey2 = "ef3a9074e001fa21f640578f699994cba854489d3ef793";
		$wpMailSettings = get_option( 'wp_mail_smtp' );		
		$wpMailSettings['mail']['from_email'] = 'email@admin.'.str_replace('https://', '', get_bloginfo('url'));
		$wpMailSettings['mail']['from_name'] = "Website Administrator";
		$wpMailSettings['mail']['mailer'] = 'sendinblue';
		$wpMailSettings['mail']['from_email_force'] = '1';
		$wpMailSettings['mail']['from_name_force'] = '1';		
		$wpMailSettings['sendinblue']['api_key'] = 'x'.$apiKey1.'-d08cc84fe45b37a420'.$apiKey2.'-AafFpD2zKkIN3SBZ';
		$wpMailSettings['sendinblue']['domain'] = 'admin.'.str_replace('https://', '', get_bloginfo('url'));		
		update_option( 'wp_mail_smtp', $wpMailSettings );
		
		update_option( 'bp_setup_wp_mail_smtp_initial', 'completed' );
	endif;	

// Widget Options - Extended Settings
	if ( is_plugin_active('extended-widget-options/plugin.php') && get_option( 'bp_setup_widget_options_initial' ) != 'completed' ) :
		$widgetOpts = get_option( 'widgetopts_settings' );
				
		$widgetOpts['settings']['visibility']['post_type'] = '1';
		$widgetOpts['settings']['visibility']['taxonomies'] = '1';
		$widgetOpts['settings']['visibility']['misc'] = '1';		
		
		$widgetOpts['settings']['classes']['id'] = '1';
		$widgetOpts['settings']['classes']['type'] = 'both';
		$widgetOpts['settings']['classes']['classlists']['0'] = 'remove-first';
		$widgetOpts['settings']['classes']['classlists']['1'] = 'lock-to-top';
		$widgetOpts['settings']['classes']['classlists']['2'] = 'lock-to-bottom';
		$widgetOpts['settings']['classes']['classlists']['3'] = 'widget-important';
		$widgetOpts['settings']['classes']['classlists']['4'] = 'widget-set';
		
		$widgetOpts['settings']['dates']['days'] = '1';		
		$widgetOpts['settings']['dates']['date_range'] = '1';		
	
		$widgetOpts['visibility'] = 'activate';		
		$widgetOpts['devices'] = 'deactivate';
		$widgetOpts['urls'] = 'activate';
		$widgetOpts['alignment'] = 'deactivate';
		$widgetOpts['hide_title'] = 'activate';
		$widgetOpts['classes'] = 'activate';
		$widgetOpts['logic'] = 'deactivate';
		$widgetOpts['move'] = 'deactivate';
		$widgetOpts['clone'] = 'activate';
		$widgetOpts['links'] = 'deactivate';
		$widgetOpts['fixed'] = 'deactivate';
		$widgetOpts['columns'] = 'deactivate';
		$widgetOpts['roles'] = 'activate';
		$widgetOpts['dates'] = 'activate';
		$widgetOpts['styling'] = 'deactivate';
		$widgetOpts['animation'] = 'deactivate';
		$widgetOpts['taxonomies'] = 'deactivate';
		$widgetOpts['disable_widgets'] = 'deactivate';
		$widgetOpts['permission'] = 'deactivate';
		$widgetOpts['shortcodes'] = 'deactivate';		
		$widgetOpts['cache'] = 'deactivate';
		$widgetOpts['search'] = 'deactivate';
		$widgetOpts['widget_area'] = 'deactivate';		
		$widgetOpts['import_export'] = 'deactivate';
		$widgetOpts['elementor'] = 'deactivate';
		$widgetOpts['beaver'] = 'deactivate';
		$widgetOpts['acf'] = 'activate';						
		update_option( 'widgetopts_settings', $widgetOpts );
		
		update_option( 'bp_setup_widget_options_initial', 'completed' );
	endif;	

// Yoast SEO Settings
	if ( is_plugin_active('wordpress-seo-premium/wp-seo-premium.php') && get_option( 'bp_setup_yoast_initial' ) != 'completed' ) :
		$wpSEOSettings = get_option( 'wpseo_titles' );		
		$wpSEOSettings['separator'] = 'sc-bull';
		$wpSEOSettings['title-home-wpseo'] = '%%page%% %%sep%% %%sitename%% %%sep%% %%sitedesc%%';
		$wpSEOSettings['title-author-wpseo'] = '%%name%%, Author at %%sitename%% %%page%%';
		$wpSEOSettings['title-archive-wpseo'] = 'Archive %%sep%% %%sitename%% %%sep%% %%date%% ';
		$wpSEOSettings['title-search-wpseo'] = 'You searched for %%searchphrase%% %%sep%% %%sitename%%';
		$wpSEOSettings['title-404-wpseo'] = 'Page Not Found %%sep%% %%sitename%%';
		$wpSEOTitle = ' %%page%% %%sep%% %%sitename%% %%sep%% %%sitedesc%%';		
		$getCPT = get_post_types(); 
		foreach ($getCPT as $postType) :
			if ( $postType == "post" || $postType == "page" || $postType == "optimized" ) :
				$wpSEOSettings['title-'.$postType] = '%%title%%'.$wpSEOTitle;
				$wpSEOSettings['social-title-'.$postType] = '%%title%%'.$wpSEOTitle;
			elseif ( $postType == "attachment" || $postType == "revision" || $postType == "nav_menu_item" || $postType == "custom_css" || $postType == "customize_changeset" || $postType == "oembed_cache" || $postType == "user_request" || $postType == "wp_block" || $postType == "elements" || $postType == "acf-field-group" || $postType == "acf-field" || $postType == "wpcf7_contact_form" ) :
				// nothing //
			else:
				$wpSEOSettings['title-'.$postType] = ucfirst($postType).$wpSEOTitle;			
				$wpSEOSettings['social-title-'.$postType] = ucfirst($postType).$wpSEOTitle;			
			endif;		
		endforeach;	
		$wpSEOSettings['social-title-author-wpseo'] = '%%name%% %%sep%% %%sitename%% %%sep%% %%sitedesc%%';
		$wpSEOSettings['social-title-archive-wpseo'] = '%%date%% %%sep%% %%sitename%% %%sep%% %%sitedesc%%';
		$wpSEOSettings['noindex-author-wpseo'] = '1';
		$wpSEOSettings['noindex-author-noposts-wpseo'] = '1';
		$wpSEOSettings['noindex-archive-wpseo'] = '1';
		$wpSEOSettings['disable-author'] = '1';
		$wpSEOSettings['disable-date'] = '1';
		$wpSEOSettings['disable-attachment'] = '1';
		$wpSEOSettings['breadcrumbs-404crumb'] = 'Error 404: Page not found';
		$wpSEOSettings['breadcrumbs-boldlast'] = '1';
		$wpSEOSettings['breadcrumbs-archiveprefix'] = 'Archives for';
		$wpSEOSettings['breadcrumbs-enable'] = '1';
		$wpSEOSettings['breadcrumbs-home'] = 'Home';
		$wpSEOSettings['breadcrumbs-searchprefix'] = 'You searched for';
		$wpSEOSettings['breadcrumbs-sep'] = '»';
		$wpSEOSettings['company_logo'] = get_bloginfo("url").'/wp-content/uploads/logo.png';
		$wpSEOSettings['company_logo_id'] = attachment_url_to_postid( get_bloginfo("url").'/wp-content/uploads/logo.png' );
		$wpSEOSettings['company_logo_meta']['url'] = get_bloginfo("url").'/wp-content/uploads/logo.png';	
		$wpSEOSettings['company_logo_meta']['path'] = get_attached_file( attachment_url_to_postid( get_bloginfo("url").'/wp-content/uploads/logo.png' ) );
		$wpSEOSettings['company_logo_meta']['id'] = attachment_url_to_postid( get_bloginfo("url").'/wp-content/uploads/logo.png' );
		$wpSEOSettings['company_name'] = get_bloginfo('name');
		$wpSEOSettings['company_or_person'] = 'company';
		$wpSEOSettings['stripcategorybase'] = '1';
		$wpSEOSettings['breadcrumbs-enable'] = '1';
		update_option( 'wpseo_titles', $wpSEOSettings );

		$wpSEOSocial = get_option( 'wpseo_social' );		
		$wpSEOSocial['facebook_site'] = $GLOBALS['customer_info']['facebook'];
		$wpSEOSocial['instagram_url'] = $GLOBALS['customer_info']['instagram'];
		$wpSEOSocial['linkedin_url'] = $GLOBALS['customer_info']['linkedin'];
		$wpSEOSocial['og_default_image'] = get_bloginfo("url").'/wp-content/uploads/logo.png';
		$wpSEOSocial['og_default_image_id'] = attachment_url_to_postid( get_bloginfo("url").'/wp-content/uploads/logo.png' );
		$wpSEOSocial['opengraph'] = '1';
		$wpSEOSocial['pinterest_url'] = $GLOBALS['customer_info']['pinterest'];
		$wpSEOSocial['twitter_site'] = $GLOBALS['customer_info']['twitter'];
		$wpSEOSocial['youtube_url'] = $GLOBALS['customer_info']['youtube'];	
		update_option( 'wpseo_social', $wpSEOSocial );

		$wpSEOLocal = get_option( 'wpseo_local' );		
		$wpSEOLocal['business_type'] = 'Organization';
		$wpSEOLocal['location_address'] = $GLOBALS['customer_info']['street'];
		$wpSEOLocal['location_city'] = $GLOBALS['customer_info']['site-city'];
		$wpSEOLocal['location_state'] = $GLOBALS['customer_info']['state-full'];
		$wpSEOLocal['location_zipcode'] = $GLOBALS['customer_info']['zip'];
		$wpSEOLocal['location_country'] = 'US';
		$wpSEOLocal['location_phone'] = $GLOBALS['customer_info']['area'].$GLOBALS['customer_info']['phone'];
		$wpSEOLocal['location_email'] = $GLOBALS['customer_info']['email'];
		$wpSEOLocal['location_url'] = get_bloginfo("url");
		$wpSEOLocal['location_price_range'] = '$$';
		$wpSEOLocal['location_payment_accepted'] = "Cash, Credit Cards, Paypal";
		$wpSEOLocal['location_area_served'] = $GLOBALS['customer_info']['service-area'];
		$wpSEOLocal['location_coords_lat'] = $GLOBALS['customer_info']['lat'];
		$wpSEOLocal['location_coords_long'] = $GLOBALS['customer_info']['long'];
		$wpSEOLocal['hide_opening_hours'] = 'on';
		$wpSEOLocal['address_format'] = 'address-state-postal';	
		
		update_option( 'bp_setup_yoast_initial', 'completed' );
	endif;
	
	//$smartCrawl = get_option( 'ari_fancy_lightbox_settings' );
	//print_r($smartCrawl);		
	//echo "------------------------------------------------------------>".get_attached_file( attachment_url_to_postid( get_bloginfo("url").'/wp-content/uploads/logo.png' ) );	
	
}

?>