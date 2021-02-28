<?php
/* Battle Plan Web Design Functions (Admin)
 
/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Shortcodes
# Set Up Admin Columns
# Admin Interface Set Up

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
			QTags.addButton( 'bp_paragraph', 'p', '<p>', '</p>\n\n', 'p', 'Paragraph Tag', 1 );
			QTags.addButton( 'bp_li', 'li', ' <li>', '</li>', 'li', 'List Item', 100 );

			QTags.addButton( 'bp_section', 'section', '[section name="becomes id attribute" style="corresponds to css" width="default, stretch, full, edge, inline" background="url" left="50" top="50" class="" start="YYYY-MM-DD" end="YYYY-MM-DD"]\n', '[/section]\n\n', 'section', 'Section', 1000 );		
			QTags.addButton( 'bp_layout', 'layout', ' [layout grid="1-auto, 1-1-1-1, 5e, content" break="3, 4" valign="start, stretch, center, end" class=""]\n\n', ' [/layout]\n', 'layout', 'Layout', 1000 );
			QTags.addButton( 'bp_column', 'column', '  [col name="becomes id attribute" align="center, left, right" valign="start, stretch, center, end" background="url" left="50" top="50" class="" start="YYYY-MM-DD" end="YYYY-MM-DD"]\n', '  [/col]\n\n', 'column', 'Column', 1000 );
			QTags.addButton( 'bp_image', 'image', '   [img size="100 1/2 1/3 1/4 1/6 1/12" order="1, 2, 3" link="url to link to" new-tab="false, true" ada-hidden="false, true" class="" start="YYYY-MM-DD" end="YYYY-MM-DD"]', '[/img]\n', 'image', 'Image', 1000 );
			QTags.addButton( 'bp_video', 'video', '   [vid size="100 1/2 1/3 1/4 1/6 1/12" order="1, 2, 3" link="url of video" class="" related="false, true" start="YYYY-MM-DD" end="YYYY-MM-DD"]', '[/vid]\n', 'video', 'Video', 1000 );
			QTags.addButton( 'bp_caption', 'caption', '[caption align="aligncenter, alignleft, alignright" width="800"]<img src="/filename.jpg" alt="" class="size-full-s" />Type caption here.', '[/caption]\n', 'caption', 'Caption', 1000 );
			QTags.addButton( 'bp_group', 'group', '   [group size = "100 1/2 1/3 1/4 1/6 1/12" order="1, 2, 3" class="" start="YYYY-MM-DD" end="YYYY-MM-DD"]\n', '   [/group]\n\n', 'group', 'Group', 1000 );	
			QTags.addButton( 'bp_text', 'text', '   [txt size="100 1/2 1/3 1/4 1/6 1/12" order="2, 1, 3" class="" start="YYYY-MM-DD" end="YYYY-MM-DD"]\n', '   [/txt]\n', 'text', 'Text', 1000 );
			QTags.addButton( 'bp_button', 'button', '   [btn size="100 1/2 1/3 1/4 1/6 1/12" order="3, 1, 2" align="center, left, right" link="url to link to" get-biz="link in functions.php" new-tab="false, true" class="" ada="text for ada button" start="YYYY-MM-DD" end="YYYY-MM-DD"]', '[/btn]\n', 'button', 'Button', 1000 );	
			QTags.addButton( 'bp_social', 'social', '   [social-btn type="email, facebook, twitter" img="none, link"]', '', 'social', 'Social', 1000 );	
			QTags.addButton( 'bp_accordion', 'accordion', '   [accordion title="clickable title" excerpt="false, true" class="" icon="true, false" start="YYYY-MM-DD" end="YYYY-MM-DD"]', '[/accordion]\n\n', 'accordion', 'Accordion', 1000 );
			QTags.addButton( 'bp_expire-content', 'expire', '[expire start="YYYY-MM-DD" end="YYYY-MM-DD"]', '[/expire]\n\n', 'expire', 'Expire', 1000 );			
			QTags.addButton( 'bp_lock-section', 'lock', '[lock name="becomes id attribute" style="(lock) corresponds to css" width="edge, default, stretch, full, inline" position="bottom, top, modal" delay="3000" show="session, never, always, # days" background="url" left="50" top="50" class="" start="YYYY-MM-DD" end="YYYY-MM-DD"]\n', '[/lock]\n\n', 'lock', 'Lock', 1000 );		
			QTags.addButton( 'bp_random-image', 'random image', '   [get-random-image id="" tag="random" size="thumbnail, third-s" link="no, yes" number="1" offset="" align="left, right, center" order_by="recent, rand, menu_order, title, id, post_date, modified, views" order="asc, desc" shuffle="no, yes"]\n', '', 'random image', 'Random Image', 1000 );
			QTags.addButton( 'bp_random-post', 'random post', '   [get-random-posts num="1" offset="0" type="post" tax="" terms="" orderby="recent, rand, views-today, views-7day, views-30day, views-90day, views-180day, views-365day, views-all" sort="asc, desc" count_tease="true, false" count_view="true, false" thumb_only="false, true" thumb_col="1, 2, 3, 4" show_title="true, false" title_pos="outside, inside" show_date="false, true" show_author="false, true" show_excerpt="true, false" show_social="false, true" show_btn="true, false" button="Read More" btn_pos="inside, outside" thumbnail="force, false" link="post, false, /link-destination/" start="" end="" exclude="" x_current="true, false" size="thumbnail, size-third-s" pic_size="1/3" text_size=""]\n', '', 'random post', 'Random Post', 1000 );
			QTags.addButton( 'bp_random-text', 'random text', '   [get-random-text cookie="true, false" text1="" text2="" text3="" text4="" text5="" text6="" text7=""]\n', '', 'random text', 'Random Text', 1000 );
			QTags.addButton( 'bp_row-of-pics', 'row of pics', '   [get-row-of-pics id="" tag="row-of-pics" col="4" size="half-s, thumbnail" valign="center, start, stretch, end" link="no, yes" order_by="recent, rand, menu_order, title, id, post_date, modified, views" order="asc, desc" shuffle="no, yes" class=""]\n', '', 'row of pics', 'Row Of Pics', 1000 );
			QTags.addButton( 'bp_post-slider', 'post slider', '   [get-post-slider type="" auto="yes, no" interval="6000" loop="true, false" num="4" offset="0" pics="yes, no" controls="yes, no" controls_pos="below, above" indicators="no, yes" pause="true, false" tax="" terms="" orderby="recent, rand, id, author, title, name, type, date, modified, parent, comment_count, relevance, menu_order, (images) views, (posts) views-today, views-7day, views-30day, views-90day, views-180day, views-365day, views-all" order="asc, desc" post_btn="" all_btn="View All" link="" start="" end="" excluse="" x_current="true, false" show_excerpt="true, false" show_content="false, true" size="thumbnail" pic_size="1/3" text_size="" class="" (images) slide_type="box, screen, fade" tag="" caption="no, yes" id="" size="thumbnail, half-s" mult="1"]\n', '', 'post slider', 'Post Slider', 1000 );

			QTags.addButton( 'bp_images-slider', 'Images Slider', '<div class="alignright size-half-s">[get-post-slider type="images" num="6" size="half-s" controls="no" indicators="yes" tag="featured" all_btn="" link="none, alt, description, blank" slide_type="box, screen, fade" orderby="recent"]</div>\n\n', '', 'images-slider', 'Images Slider', 1000 );	
			QTags.addButton( 'bp_testimonial-slider', 'Testimonial Slider', '  [col]\n   <h2>What Our Customers Say...</h2>\n   [get-post-slider type="testimonials" num="6" pic_size="1/3"]\n  [/col]\n\n', '', 'testimonial-slider', 'Testimonial Slider', 1000 );
			QTags.addButton( 'bp_random-product', 'Random Product', '  [col]\n   <h2>Featured Product</h2>\n   [get-random-posts type="products" offset="1" button="Learn More" orderby="views-30day" sort="desc"]\n  [/col]\n\n', '', 'random-product', 'Random Product', 1000 );
		</script>
	<?php }
}

/*--------------------------------------------------------------
# Set up Admin Columns
--------------------------------------------------------------*/
add_action( 'ac/ready', 'battleplan_column_settings' );
function battleplan_column_settings() {
	ac_register_columns( 'page', array(
		array(
			'columns'=>array(
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
				'last_viewed'=>array(
					'type'=>'column-meta',
					'label'=>'Last Viewed',
					'width'=>'130',
					'width_unit'=>'px',
					'field'=>'log-views-now',
					'field_type'=>'date',
					'date_format'=>'wp_default',
					'before'=>'',
					'after'=>'',
					'edit'=>'off',
					'sort'=>'on',
					'filter'=>'on',
					'filter_label'=>'',
					'filter_format'=>'monthly',
					'name'=>'last_viewed',
					'label_type'=>'',
					'search'=>'on'
				),	
				'views_week'=>array(
					'type'=>'column-meta',
					'label'=>'Week',
					'width'=>'65',
					'width_unit'=>'px',
					'field'=>'log-views-total-7day',
					'field_type'=>'numeric',
					'before'=>'',
					'after'=>'',
					'edit'=>'off',
					'sort'=>'on',
					'filter'=>'on',
					'filter_label'=>'',
					'name'=>'views_week',
					'label_type'=>'',
					'search'=>'on'
				),
				'views_month'=>array(
					'type'=>'column-meta',
					'label'=>'Month',
					'width'=>'65',
					'width_unit'=>'px',
					'field'=>'log-views-total-30day',
					'field_type'=>'numeric',
					'before'=>'',
					'after'=>'',
					'edit'=>'off',
					'sort'=>'on',
					'filter'=>'on',
					'filter_label'=>'',
					'name'=>'views_month',
					'label_type'=>'',
					'search'=>'on'
				),
				'views_quarter'=>array(
					'type'=>'column-meta',
					'label'=>'Quarter',
					'width'=>'65',
					'width_unit'=>'px',
					'field'=>'log-views-total-90day',
					'field_type'=>'numeric',
					'before'=>'',
					'after'=>'',
					'edit'=>'off',
					'sort'=>'on',
					'filter'=>'on',
					'filter_label'=>'',
					'name'=>'views_total',
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
				'id'=>'5cbb31579168e',
				'name'=>'battleplan',
				'roles'=>false,
				'users'=>false,
				'read_only'=>false
			)			
		)
	) );
	ac_register_columns( 'optimized', array(
		array(
			'columns'=>array(
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
				'last_viewed'=>array(
					'type'=>'column-meta',
					'label'=>'Last Viewed',
					'width'=>'130',
					'width_unit'=>'px',
					'field'=>'log-views-now',
					'field_type'=>'date',
					'date_format'=>'wp_default',
					'before'=>'',
					'after'=>'',
					'edit'=>'off',
					'sort'=>'on',
					'filter'=>'on',
					'filter_label'=>'',
					'filter_format'=>'monthly',
					'name'=>'last_viewed',
					'label_type'=>'',
					'search'=>'on'
				),	
				'views_week'=>array(
					'type'=>'column-meta',
					'label'=>'Week',
					'width'=>'65',
					'width_unit'=>'px',
					'field'=>'log-views-total-7day',
					'field_type'=>'numeric',
					'before'=>'',
					'after'=>'',
					'edit'=>'off',
					'sort'=>'on',
					'filter'=>'on',
					'filter_label'=>'',
					'name'=>'views_week',
					'label_type'=>'',
					'search'=>'on'
				),
				'views_month'=>array(
					'type'=>'column-meta',
					'label'=>'Month',
					'width'=>'65',
					'width_unit'=>'px',
					'field'=>'log-views-total-30day',
					'field_type'=>'numeric',
					'before'=>'',
					'after'=>'',
					'edit'=>'off',
					'sort'=>'on',
					'filter'=>'on',
					'filter_label'=>'',
					'name'=>'views_month',
					'label_type'=>'',
					'search'=>'on'
				),
				'views_quarter'=>array(
					'type'=>'column-meta',
					'label'=>'Quarter',
					'width'=>'65',
					'width_unit'=>'px',
					'field'=>'log-views-total-90day',
					'field_type'=>'numeric',
					'before'=>'',
					'after'=>'',
					'edit'=>'off',
					'sort'=>'on',
					'filter'=>'on',
					'filter_label'=>'',
					'name'=>'views_total',
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
				'id'=>'5cbb31579168e',
				'name'=>'battleplan',
				'roles'=>false,
				'users'=>false,
				'read_only'=>false
			)			
		)
	) );
	ac_register_columns( 'elements', array(
		array(
			'columns'=>array(
				'title'=>array(
					'type'=>'title',
					'label'=>'Page',
					'width'=>'170',
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
				'id'=>'5cbb31579168e',
				'name'=>'battleplan',
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
				'title'=>array(
					'type'=>'title',
					'label'=>'Name',
					'width'=>'',
					'width_unit'=>'%',
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
					'filter'=>'on',
					'filter_label'=>'',
					'filter_format'=>'monthly',
					'name'=>'last_viewed',
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
					'filter'=>'on',
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
					'filter'=>'on',
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
					'filter'=>'on',
					'filter_label'=>'',
					'name'=>'views_total',
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
				'id'=>'5cbb315787688',
				'name'=>'battleplan',
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
				'title'=>array(
					'type'=>'title',
					'label'=>'Title',
					'width'=>'',
					'width_unit'=>'%',
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
				)
			),
			'layout'=>array(
				'id'=>'5cbb31578ee04',
				'name'=>'battleplan',
				'roles'=>false,
				'users'=>false,
				'read_only'=>false
			)			
		)
	) );
	ac_register_columns( 'post', array(
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
				'title'=>array(
					'type'=>'title',
					'label'=>'Title',
					'width'=>'',
					'width_unit'=>'%',
					'edit'=>'on',
					'sort'=>'on',
					'name'=>'title',
					'label_type'=>'',
					'search'=>'on'
				),
				'column-slug'=>array(
					'type'=>'column-slug',
					'label'=>'Slug',
					'width'=>'',
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
				'last_viewed'=>array(
					'type'=>'column-meta',
					'label'=>'Last Viewed',
					'width'=>'130',
					'width_unit'=>'px',
					'field'=>'log-views-now',
					'field_type'=>'date',
					'date_format'=>'wp_default',
					'before'=>'',
					'after'=>'',
					'edit'=>'off',
					'sort'=>'on',
					'filter'=>'on',
					'filter_label'=>'',
					'filter_format'=>'monthly',
					'name'=>'last_viewed',
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
					'filter'=>'on',
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
					'filter'=>'on',
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
					'filter'=>'on',
					'filter_label'=>'',
					'name'=>'views_total',
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
				)
			),
			'layout'=>array(
				'id'=>'5cbb31579092a',
				'name'=>'battleplan',
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
				'filename'=>array(
					'type'=>'column-file_name',
					'label'=>'Filename',
					'width'=>'',
					'width_unit'=>'%',
					'sort'=>'on',
					'name'=>'filename',
					'label_type'=>''
				),
				'alt-text' => array(
					'type' => 'column-alternate_text',
					'label' => 'Alt Text',
					'width' => '150',
					'width_unit' => 'px',
					'use_icons' => '',
					'name' => 'column-alternate_text',
					'label_type' => '',
					'edit' => 'on',
					'sort' => 'on',
					'filter'=>'on',
					'filter_label'=>'',
					'bulk-editing' => '',
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
					'search'=>'on'
				),
				'image-id'=>array(
					'type'=>'column-mediaid',
					'label'=>'ID',
					'width'=>'',
					'width_unit'=>'%',
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
					'filter'=>'on',
					'filter_label'=>'',
					'filter_format'=>'monthly',
					'name'=>'last_viewed',
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
					'filter'=>'on',
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
					'filter'=>'on',
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
					'filter'=>'on',
					'filter_label'=>'',
					'name'=>'views_total',
					'label_type'=>'',
					'search'=>'on'
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
					'sort'=>'on',
					'name'=>'sizes',
					'label_type'=>''
				)
			),
			'layout'=>array(
				'id'=>'5cbb3157923d6',
				'name'=>'battleplan',
				'roles'=>false,
				'users'=>false,
				'read_only'=>false
			)			
		)
	) );
}

/*--------------------------------------------------------------
# Admin Interface Set Up
--------------------------------------------------------------*/

// Disable Gutenburg
add_filter('use_block_editor_for_post', '__return_false');

// Disable Visual Editor
add_filter( 'user_can_richedit' , '__return_false', 50 );

// Add, Remove and Reorder Items in Admin Bar
add_action( 'wp_before_admin_bar_render', 'battleplan_reorderAdminBar');
function battleplan_reorderAdminBar() {
    global $wp_admin_bar;
	
	$wp_admin_bar->add_node( array( 'id' => 'tagline', 'title' => '-&nbsp;&nbsp;'.get_bloginfo( 'description' ).'&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;', 'href'  => esc_url(site_url()), ) );	
	
	$suspended = get_post_meta( get_page_by_path('site-header', OBJECT, 'elements')->ID, '_suspend_site', true);	
	if ( $_SERVER['QUERY_STRING'] == "page=suspend-site" ) :	
		if ( $suspended == "yes" ) : $suspended = "no";
		else : $suspended = "yes";
		endif;
	endif;
	if ( $suspended == "yes" ) :
		$wp_admin_bar->add_node( array( 'id' => 'suspend', 'title' => 'Reinstate Website', 'href' => 'admin.php?page=suspend-site' ) );	
	else:
		$wp_admin_bar->add_node( array( 'id' => 'suspend', 'title' => 'Suspend Website', 'href' => 'admin.php?page=suspend-site' ) );	
	endif;
	
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
}

// Create "Suspend Website" admin page
add_action( 'admin_menu', 'battleplan_admin_menu' );
function battleplan_admin_menu() {
	add_menu_page( __( 'Suspend Site', 'battleplan' ), __( 'Suspend Site', 'battleplan' ), 'manage_options', 'suspend-site', 'battleplan_suspendSitePage', 'dashicons-schedule', 3 );
}

function battleplan_suspendSitePage() { 
	$siteHeader = get_page_by_path('site-header', OBJECT, 'elements')->ID;
	$suspended = get_post_meta($siteHeader, '_suspend_site', true);	
	if ( $suspended == "yes" || $suspended == null || $suspended = "" ) :
		if ( !add_post_meta( $siteHeader, '_suspend_site', $suspended, true ) ) :
			update_post_meta( $siteHeader, '_suspend_site', 'no' );
		endif;
		echo '<h1>Website Reinstated</h1>';
	else:
		update_post_meta( $siteHeader, '_suspend_site', 'yes' );
		echo '<h1>Website Suspended</h1>';
	endif;
}

// Replace WordPress copyright message at bottom of admin page
add_filter('admin_footer_text', 'battleplan_remove_footer_admin');
function battleplan_remove_footer_admin () { echo 'Powered by <b><a href="https://battleplanwebdesign.com" target="_blank">Battle Plan Web Design</b></a><br/>Framework <b>'._BP_VERSION.'</b></b></p>'; }

// Change Howdy text
add_filter( 'admin_bar_menu', 'battleplan_replace_howdy', 25 );
function battleplan_replace_howdy( $wp_admin_bar ) {
	 $my_account=$wp_admin_bar->get_node('my-account');
	 $newtitle = str_replace( 'Howdy,', 'Welcome,', $my_account->title );
	 $wp_admin_bar->add_node( array( 'id'=>'my-account', 'title'=>$newtitle, ) );
 }

// Remove https://domain.com, width & height params from the <img> inserted by WordPress
add_filter( 'image_send_to_editor', 'battleplan_remove_junk_from_image', 10 );
function battleplan_remove_junk_from_image( $html ) {
   $html = preg_replace( '/(width|height)="\d*"\s/', "", $html );
   $html = str_replace( get_site_url(), "", $html );
   return $html;
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

// Display custom fields in WordPress admin edit screen
//add_filter('acf/settings/remove_wp_meta_box', '__return_false');

// Add 'log-views' fields to an image when it is uploaded
add_action( 'add_attachment', 'battleplan_addWidgetPicViewsToImg', 10, 9 );
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

// Add 'log-views' fields to posts/pages when published 
add_action( 'save_post', 'battleplan_addViewsToPost', 10, 3 );
function battleplan_addViewsToPost() {
	global $post; $post_ID = $post->ID;	
	if ( readMeta( $post_ID, 'log-views') == '' ) {
		updateMeta( $post_ID, 'log-views-now', strtotime("-1 day"));	
		updateMeta( $post_ID, 'log-views-time', strtotime("-1 day"));			
		updateMeta( $post_ID, 'log-tease-time', strtotime("-1 day"));			
		updateMeta( $post_ID, 'log-views-total-7day', '0' );		
		updateMeta( $post_ID, 'log-views-total-30day', '0' );
		updateMeta( $post_ID, 'log-views-total-90day', '0' );
		updateMeta( $post_ID, 'log-views-total-180day', '0' );
		updateMeta( $post_ID, 'log-views-total-365day', '0' );
		updateMeta( $post_ID, 'log-views', array( 'date' => strtotime(date("F j, Y")), 'views' => 0 ));					
	}
}

// Add & Remove WP Admin Menu items
add_action( 'admin_init', 'battleplan_remove_menus', 999 );
function battleplan_remove_menus() {   
	remove_menu_page( 'link-manager.php' );       						//Links
	remove_submenu_page( 'themes.php', 'theme-editor.php' );        	//Appearance -> Theme Editor
	remove_submenu_page( 'themes.php', 'widgets.php' );        			//Appearance -> Widgets
	remove_submenu_page( 'themes.php', 'nav-menus.php' );        		//Appearance -> Menus
	remove_submenu_page( 'tools.php', 'export-personal-data.php' );   	//Tools - Export Personal Data  
	remove_submenu_page( 'tools.php', 'erase-personal-data.php' );   	//Tools - Erase Personal Data
	remove_menu_page( 'meowapps-main-menu' );       					//Meow Apps
	add_menu_page('Perfect Images', 'Perfect Images', 'manage_options', '/admin.php?page=wr2x_settings', '', 'dashicons-welcome-view-site');
	add_submenu_page( 'edit.php?post_type=elements', 'Widgets', 'Widgets', 'manage_options', 'widgets.php' );	
	add_submenu_page( 'edit.php?post_type=elements', 'Menus', 'Menus', 'manage_options', 'nav-menus.php' );	
}

// Reorder WP Admin Menu Items
add_filter( 'custom_menu_order', 'battleplan_custom_menu_order', 10, 1 );
add_filter( 'menu_order', 'battleplan_custom_menu_order', 10, 1 );
function battleplan_custom_menu_order( $menu_ord ) {
    if ( !$menu_ord ) return true;	
	$getCPT = get_post_types();  
	$displayTypes = array('index.php', 'separator1', 'upload.php', 'edit.php?post_type=elements', 'edit.php?post_type=page');
	unset($getCPT['attachment'], $getCPT['revision'], $getCPT['nav_menu_item'], $getCPT['custom_css'], $getCPT['customize_changeset'], $getCPT['oembed_cache'], $getCPT['user_request'], $getCPT['wp_block'], $getCPT['acf-field-group'], $getCPT['acf-field'], $getCPT['wpcf7_contact_form'], $getCPT['wphb_minify_group'], $getCPT['elements']); 	
	foreach ($getCPT as $postType) {
		array_push($displayTypes, 'edit.php?post_type='.$postType);
	}
	array_push($displayTypes, 'edit.php', 'edit-comments.php', 'wpcf7', 'separator2', 'wpengine-common', 'themes.php', 'plugins.php', 'options-general.php', 'tools.php', 'edit.php?post_type=acf-field-group', 'users.php', 'separator-last', 'wds_wizard', 'smush', 'wr2x_settings', 'wpmudev');	
	return $displayTypes;
}

// Remove unwanted dashboard widgets
add_action('wp_dashboard_setup', 'battleplan_remove_dashboard_widgets');
function battleplan_remove_dashboard_widgets () {
	remove_action('welcome_panel','wp_welcome_panel'); 						//Welcome to WordPress!
	remove_meta_box('dashboard_primary','dashboard','normal'); 				//WordPress.com Blog
	remove_meta_box('dashboard_primary','dashboard','side'); 				//WordPress.com Blog
	remove_meta_box('dashboard_right_now','dashboard','normal');	
	remove_meta_box('dashboard_right_now','dashboard','side');
	remove_meta_box('dashboard_quick_press','dashboard','normal'); 			//Quick Press widget
	remove_meta_box('dashboard_quick_press','dashboard','side'); 			//Quick Press widget
	remove_meta_box('tribe_dashboard_widget', 'dashboard', 'normal'); 		//News From Modern Tribe	
	remove_meta_box('tribe_dashboard_widget', 'dashboard', 'side'); 		//News From Modern Tribe
	remove_meta_box('wpe_dify_news_feed','dashboard','normal'); 			//WP Engine	
	remove_meta_box('wpe_dify_news_feed','dashboard','side'); 				//WP Engine
	remove_meta_box('wds_sitemaps_dashboard_widget','dashboard','normal');	//SmartCrawl Site Maps
	remove_meta_box('wds_sitemaps_dashboard_widget','dashboard','side');	//SmartCrawl Site Maps
	remove_meta_box('dashboard_activity','dashboard','normal');				//Activity
	remove_meta_box('dashboard_activity','dashboard','side');				//Activity
	remove_meta_box('dashboard_site_health','dashboard','normal');			//Site Health
	remove_meta_box('dashboard_site_health','dashboard','side');			//Site Health
	remove_meta_box('woocommerce_dashboard_status','dashboard','normal');	//Woocommerce
	remove_meta_box('woocommerce_dashboard_status','dashboard','side');	//Woocommerce
}

// Add new dashboard widgets
add_action( 'wp_dashboard_setup', 'battleplan_add_dashboard_widgets' );
function battleplan_add_dashboard_widgets() {
    //wp_add_dashboard_widget( 'battleplan_site_stats', 'Site Stats', 'battleplan_admin_site_stats' );
    //wp_add_dashboard_widget( 'battleplan_location_stats', 'Location Stats', 'battleplan_admin_location_stats' );
	add_meta_box( 'battleplan_site_stats', 'Site Visitors', 'battleplan_admin_site_stats', 'dashboard', 'normal', 'high' );		
	add_meta_box( 'battleplan_speed_stats', 'Site Speed', 'battleplan_admin_speed_stats', 'dashboard', 'side', 'high' );	
	add_meta_box( 'battleplan_location_stats', 'Visitor Locations', 'battleplan_admin_location_stats', 'dashboard', 'side', 'high' );
	add_meta_box( 'battleplan_trends_stats', 'Visitor Trends', 'battleplan_admin_trends_stats', 'dashboard', 'column3', 'high' );		
}

// Set up Site Stats widget on dashboard
function battleplan_admin_site_stats() {
	$siteHeader = getID('site-header');
	$rightNow = strtotime(date("F j, Y g:i a"));
	$today = strtotime(date("F j, Y"));
	$getViews = readMeta($siteHeader, 'log-views');
	$getViews = maybe_unserialize( $getViews );
	$viewsToday = number_format($getViews[0]['views']);
	$firstDate = strtotime($getViews[0]['date']);
	if ( $firstDate != $today ) $viewsToday = 0;
	$last7Views = number_format(readMeta($siteHeader, "log-views-total-7day"));
	$last30Views = number_format(readMeta($siteHeader, "log-views-total-30day"));
	$last90Views = number_format(readMeta($siteHeader, "log-views-total-90day"));	
	$last180Views = number_format(readMeta($siteHeader, "log-views-total-180day"));	
	$last365Views = number_format(readMeta($siteHeader, "log-views-total-365day"));
	$lastViewed = readMeta($siteHeader, 'log-views-now');		
	$dateDiff = (($rightNow - $lastViewed) / 60 / 60 / 24); $howLong = "day";
	if ( $dateDiff < 1 ) : $dateDiff = (($rightNow - $lastViewed) / 60 / 60); $howLong = "hour"; endif;	
	if ( $dateDiff < 1 ) : $dateDiff = (($rightNow - $lastViewed) / 60); $howLong = "minute"; endif;
	if ( $dateDiff != 1 ) $howLong = $howLong."s";	
	$dateDiff = number_format($dateDiff, 0);
	
	echo "<table>";		
	echo "<tr><td><b>Last Visitor</b></td><td><b>".$dateDiff."</b> ".$howLong." ago</td></tr>";			
	echo "<tr><td><b>Today</b></td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $viewsToday, 'battleplan' ), $viewsToday )."</td></tr>";	
	echo "<tr><td>&nbsp;</td></tr>";
	echo "<tr><td><b>Last 7 Days</b></td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $last7Views, 'battleplan' ), $last7Views )."</td></tr>";
	if ( $last30Views != $last7Views) echo "<tr><td><b>Last 30 Days</b></td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $last30Views, 'battleplan' ), $last30Views )."</td></tr>";
	if ( $last90Views != $last30Views) echo "<tr><td><b>Last 90 Days</b></td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $last90Views, 'battleplan' ), $last90Views )."</td></tr>";
	if ( $last180Views != $last90Views) echo "<tr><td><b>Last 180 Days</b></td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $last180Views, 'battleplan' ), $last180Views )."</td></tr>";
	if ( $last365Views != $last180Views) echo "<tr><td><b>Last 365 Days</b></td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $last365Views, 'battleplan' ), $last365Views )."</td></tr>";
	echo "<tr><td>&nbsp;</td></tr>";
	echo "<tr><td><b><u>Top 10 Most Visited Days</u></b></td><td>&nbsp;</td></tr>";

	$sort = array();
	foreach($getViews as $k=>$v) {
		$sort['date'][$k] = $v['date'];
		$sort['views'][$k] = $v['views'];
	}
	array_multisort($sort['views'], SORT_DESC, SORT_NUMERIC, $sort['date'], SORT_DESC, $getViews);
	
	for ($x = 0; $x < 10; $x++) {
		$dailyTime = date("M j, Y", strtotime($getViews[$x]['date'])); 				
		$dailyViews = intval($getViews[$x]['views']); 	
		$rank = $x + 1;
		if ( $dailyViews > 0 ) echo "<tr><td>&nbsp;#".$rank."&nbsp;&nbsp;&nbsp;<b>".$dailyTime."</b></td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $dailyViews, 'battleplan' ), $dailyViews )."</td></tr>";
	} 		
	echo '</table>';
}

// Set up Site Speed widget on dashboard
function battleplan_admin_speed_stats() {
	$siteHeader = getID('site-header');
	$desktopCounted = readMeta($siteHeader, "load-number-desktop");
	$desktopSpeed = readMeta($siteHeader, "load-speed-desktop");	
	$mobileCounted = readMeta($siteHeader, "load-number-mobile");
	$mobileSpeed = readMeta($siteHeader, "load-speed-mobile");
	$lastEmail = readMeta($siteHeader, "last-email");
	$rightNow = strtotime(date("F j, Y g:i a"));
	$today = strtotime(date("F j, Y"));
	$daysSinceEmail = number_format((($rightNow - $lastEmail) / 60 / 60 / 24));
	$totalCounted = $desktopCounted + $mobileCounted;		
	
	echo "<table>";
		echo "<tr><td><b>".$totalCounted."</b> pageloads in the last </td><td>".sprintf( _n( '<b>%s</b> day', '<b>%s</b> days', $daysSinceEmail, 'battleplan' ), $daysSinceEmail )."</td></tr>";
		echo "<tr><td>&nbsp;</td></tr>";
		echo "<tr><td><b>Desktop</b></td><td><b>".$desktopSpeed."s</b> on ".sprintf( _n( '<b>%s</b> pageload', '<b>%s</b> pageloads', $desktopCounted, 'battleplan' ), $desktopCounted )."</td></tr>";
		echo "<tr><td><b>Mobile</b></td><td><b>".$mobileSpeed."s</b> on ".sprintf( _n( '<b>%s</b> pageload', '<b>%s</b> pageloads', $mobileCounted, 'battleplan' ), $mobileCounted )."</td></tr>";
	echo "</table>";
}

// Set up Visitor Locations widget on dashboard
function battleplan_admin_location_stats() {
	$siteHeader = getID('site-header');
	$locations = readMeta($siteHeader, "log-views-cities");
	$locations = maybe_unserialize($locations);
	$locNum = count($locations);
	$tallyCounts = array_count_values($locations);
	$uniqueLocs = array_unique($locations);
	$combineLocs = [];
	
	foreach ($uniqueLocs as $uniqueLoc) :
		$combineLocs[$uniqueLoc]=$tallyCounts[$uniqueLoc];
	endforeach; 	
	//ksort($combineLocs, SORT_STRING);
	//arsort($combineLocs, SORT_NUMERIC);
	
	uksort( $combineLocs, function($a, $b) use ($combineLocs) { return [$combineLocs[$b], $a] <=> [$combineLocs[$a], $b]; } );
		
	echo "<div>Last <b>".$locNum."</b> visitors</b><br/><br/>";
	echo "<ul>";
	foreach ($combineLocs as $city=>$cityNum) :
		echo "<li><span class='city-name'>".$city."</span><span class='city-num'><b>".$cityNum."</b></span></li>";
	endforeach; 	
	echo '</ul></div>';
}

// Set up Trends widget on dashboard
function battleplan_admin_trends_stats() {
	$siteHeader = getID('site-header');
	$today = strtotime(date("F j, Y"));
	$getViews = readMeta($siteHeader, 'log-views');
	$getViews = maybe_unserialize( $getViews );
	
 	$count = $views = $cutoff = 0;	
	echo "<table><tr><td><b><u>Weekly</u></b></td></tr>";		
	for ($x = 0; $x < 1095; $x++) {		
		$dailyTime = date("M j, Y", strtotime($getViews[$x]['date'])); 
		$dailyViews = intval($getViews[$x]['views']); 
		$count++;
		$views = $views + $dailyViews;		
		if ( $count == 1 ) $end = $dailyTime;
		if ( $count == 7 ) :
		 	echo "<tr><td class='dates'><b>".$dailyTime." - ".$end."</b></td><td class='visits'>".$views." visits</td></tr>";
 			$count = $views = 0;	
			if ( $views < 1 ) : $cutoff++; if ( $dailyTime == "Jan 1, 1970" || $cutoff == 5) : break; endif; endif;
		endif;	
	} 		
	echo "</table>";
	
	$count = $views = $cutoff = 0;	
	echo "<table><tr><td><b><u>Monthly</u></b></td></tr>";		
	for ($x = 0; $x < 1095; $x++) {		
		$dailyTime = date("M j, Y", strtotime($getViews[$x]['date'])); 
		$dailyViews = intval($getViews[$x]['views']); 
		$count++;
		$views = $views + $dailyViews;		
		if ( $count == 1 ) $end = $dailyTime;
		if ( $count == 31 ) :
		 	echo "<tr><td class='dates'><b>".$dailyTime." - ".$end."</b></td><td class='visits'>".$views." visits</td></tr>";
 			$count = $views = 0;	
			if ( $views < 1 ) : $cutoff++; if ( $dailyTime == "Jan 1, 1970" || $cutoff == 2) : break; endif; endif;
		endif;	
	} 		
	echo "</table>";

	$count = $views = $cutoff = 0;	
	echo "<table><tr><td><b><u>Quarterly</u></b></td></tr>";		
	for ($x = 0; $x < 1095; $x++) {		
		$dailyTime = date("M j, Y", strtotime($getViews[$x]['date'])); 
		$dailyViews = intval($getViews[$x]['views']); 
		$count++;
		$views = $views + $dailyViews;		
		if ( $count == 1 ) $end = $dailyTime;
		if ( $count == 91 ) :
		 	echo "<tr><td class='dates'><b>".$dailyTime." - ".$end."</b></td><td class='visits'>".$views." visits</td></tr>";
 			$count = $views = 0;	
			if ( $views < 1 ) : $cutoff++; if ( $dailyTime == "Jan 1, 1970" || $cutoff == 1) : break; endif; endif;
		endif;	
	} 		
	echo "</table>";

	echo "<div class='clearfix'></div>";	
}

// Add custom meta boxes to posts & pages
add_action("add_meta_boxes", "battleplan_add_custom_meta_boxes");
function battleplan_add_custom_meta_boxes() {
    add_meta_box("page-stats-box", "Page Stats", "battleplan_page_stats", "page", "side", "default", null);
    add_meta_box("page-stats-box", "Page Stats", "battleplan_page_stats", "post", "side", "default", null);
	add_meta_box("page-stats-box", "Page Stats", "battleplan_page_stats", "products", "side", "default", null);	
	add_meta_box("page-stats-box", "Page Stats", "battleplan_page_stats", "testimonials", "side", "default", null);
}

// Set up Page Stats widget on posts & pages
function battleplan_page_stats() {
	global $post;
	$rightNow = strtotime(date("F j, Y g:i a"));
	$today = strtotime(date("F j, Y"));
	$lastViewed = readMeta($post->ID, 'log-views-now');		
	$getViews = readMeta($post->ID, 'log-views');
	$getViews = maybe_unserialize( $getViews );
	$viewsToday = number_format($getViews[0]['views']);
	$firstDate = strtotime($getViews[0]['date']);
	if ( $firstDate != $today ) $viewsToday = 0;
	$last7Views = number_format(readMeta($post->ID, "log-views-total-7day"));
	$last30Views = number_format(readMeta($post->ID, "log-views-total-30day"));
	$last90Views = number_format(readMeta($post->ID, "log-views-total-90day"));	
	$last180Views = number_format(readMeta($post->ID, "log-views-total-180day"));	
	$last365Views = number_format(readMeta($post->ID, "log-views-total-365day"));
	$dateDiff = (($rightNow - $lastViewed) / 60 / 60 / 24); $howLong = "day";
	if ( $dateDiff < 1 ) : $dateDiff = (($rightNow - $lastViewed) / 60 / 60); $howLong = "hour"; endif;	
	if ( $dateDiff < 1 ) : $dateDiff = (($rightNow - $lastViewed) / 60); $howLong = "minute"; endif;
	if ( $dateDiff != 1 ) $howLong = $howLong."s";	
	$dateDiff = number_format($dateDiff, 0);	
	
	echo "<table>";		
	echo "<tr><td><b>Last Viewed</b></td><td><b>".$dateDiff."</b> ".$howLong." ago</td></tr>";	
	echo "<tr><td>&nbsp;</td></tr>";		
	echo "<tr><td><b>Today</b></td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $viewsToday, 'battleplan' ), $viewsToday )."</td></tr>";	
	echo "<tr><td><b>Last 7 Days</b></td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $last7Views, 'battleplan' ), $last7Views )."</td></tr>";
	if ( $last30Views != $last7Views) echo "<tr><td><b>Last 30 Days</b></td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $last30Views, 'battleplan' ), $last30Views )."</td></tr>";
	if ( $last90Views != $last30Views) echo "<tr><td><b>Last 90 Days</b></td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $last90Views, 'battleplan' ), $last90Views )."</td></tr>";
	if ( $last180Views != $last90Views) echo "<tr><td><b>Last 180 Days</b></td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $last180Views, 'battleplan' ), $last180Views )."</td></tr>";
	if ( $last365Views != $last180Views) echo "<tr><td><b>Last 365 Days</b></td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $last365Views, 'battleplan' ), $last365Views )."</td></tr>";
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
	 
add_action("save_post", "battleplan_save_remove_sidebar", 10, 3);
function battleplan_save_remove_sidebar($post_id, $post, $update) {
	if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return $post_id;
	if ( defined('DOING_AJAX') && DOING_AJAX ) return $post_id;
    if ( !current_user_can("edit_post", $post_id) ) return $post_id;

    $updateRemoveSidebar = "";
    if ( isset($_POST["remove_sidebar"]) ) $updateRemoveSidebar = $_POST["remove_sidebar"];   
    update_post_meta($post_id, "_bp_remove_sidebar", $updateRemoveSidebar);
}

// Add "duplicate post/page" function to WP core
add_filter( 'post_row_actions', 'battleplan_duplicate_post_link', 10, 2 );
add_filter( 'page_row_actions', 'battleplan_duplicate_post_link', 10, 2 );
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
		
		updateMeta( $new_post_id, 'log-views-now', strtotime("-1 day"));		
		updateMeta( $new_post_id, 'log-views-time', strtotime("-1 day"));			
		updateMeta( $new_post_id, 'log-tease-time', strtotime("-1 day"));			
		updateMeta( $new_post_id, 'log-views-total-7day', '0' );		
		updateMeta( $new_post_id, 'log-views-total-30day', '0' );
		updateMeta( $new_post_id, 'log-views-total-90day', '0' );
		updateMeta( $new_post_id, 'log-views-total-180day', '0' );
		updateMeta( $new_post_id, 'log-views-total-365day', '0' );
		updateMeta( $new_post_id, 'log-views', array( 'date'=> strtotime(date("F j, Y")), 'views' => 0 ));					

		wp_redirect( admin_url( 'post.php?action=edit&post=' . $new_post_id ) );
		exit;
	} else {
		wp_die('Post creation failed, could not find original post: ' . $post_id);
	}
}
 
function battleplan_duplicate_post_link( $actions, $post ) {
	if (current_user_can('edit_posts')) {
		$actions['duplicate'] = '<a href="' . wp_nonce_url('admin.php?action=battleplan_duplicate_post_as_draft&post=' . $post->ID, basename(__FILE__), 'duplicate_nonce' ) . '" title="Duplicate this item" rel="permalink">Duplicate</a>';
	}
	return $actions;
}

// Move Site Header, Site Footer, Site Message, Office Hours, Privacy Policy, etc. to Elements post type
add_action( 'admin_init', 'battleplan_setupElements', 999 );
function battleplan_setupElements() {   
	if ( get_page_by_path('site-header', OBJECT, 'page' ) ) :
		$post_id = get_page_by_path('site-header', OBJECT, 'page' )->ID;
		$my_post = array( 'ID' => $post_id, 'post_type' => 'elements', ); 
		wp_update_post( $my_post );
	endif;
	if ( get_page_by_path('site-footer', OBJECT, 'page' ) ) :
		$post_id = get_page_by_path('site-footer', OBJECT, 'page' )->ID;
		$my_post = array( 'ID' => $post_id, 'post_type' => 'elements', ); 
		wp_update_post( $my_post );
	endif;
	if ( get_page_by_path('site-message', OBJECT, 'page' ) ) :
		$post_id = get_page_by_path('site-message', OBJECT, 'page' )->ID;
		$my_post = array( 'ID' => $post_id, 'post_type' => 'elements', ); 
		wp_update_post( $my_post );
	endif;
	if ( get_page_by_path('privacy-policy', OBJECT, 'page' ) ) :
		$post_id = get_page_by_path('privacy-policy', OBJECT, 'page' )->ID;
		$my_post = array( 'ID' => $post_id, 'post_type' => 'elements', ); 
		wp_update_post( $my_post );
	endif;
	if ( get_page_by_path('office-hours', OBJECT, 'page' ) ) :
		$post_id = get_page_by_path('office-hours', OBJECT, 'page' )->ID;
		$my_post = array( 'ID' => $post_id, 'post_type' => 'elements', ); 
		wp_update_post( $my_post );
	endif;
}

?>