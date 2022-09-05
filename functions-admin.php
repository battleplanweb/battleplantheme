<?php
/* Battle Plan Web Design Functions: Admin

 
/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Shortcodes
# Admin Columns Set Up 
# Admin Interface Set Up
# Site Stats
# Site Audit Set Up
# Contact Form 7 Set Up

--------------------------------------------------------------*/

/*--------------------------------------------------------------
# Shortcodes
--------------------------------------------------------------*/

if ( is_admin() ) : 
	if (function_exists('battleplan_updateSiteOptions')) battleplan_updateSiteOptions();
endif;

// Remove buttons from WordPress text editor
add_filter( 'quicktags_settings', 'battleplan_delete_quicktags', 10, 2 );
function battleplan_delete_quicktags( $qtInit, $editor_id = 'content' ) {
	//$qtInit['buttons'] = 'strong,em,link,block,del,ins,img,ul,ol,code,more,close';
	$qtInit['buttons'] = 'strong,em,link,ul,ol,more,close';
	return $qtInit;
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
						'field'=>'log-last-viewed',
						'field_type'=>'date',
						'date_format'=>'diff',
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
					'views_today'=>array(
						'type'=>'column-meta',
						'label'=>'Yesterday',
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
								'field'=>'log-last-viewed',
								'field_type'=>'date',
								'date_format'=>'diff',
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
							'views_today'=>array(
								'type'=>'column-meta',
								'label'=>'Yesterday',
								'width'=>'',
								'width_unit'=>'%',
								'field'=>'log-views-today',
								'field_type'=>'numeric',
								'number_format'=>'formatted',
                				'number_decimals'=>'0',
                				'number_decimal_point'=>'.',
                				'number_thousands_separator'=>',',
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
								'number_format'=>'formatted',
                				'number_decimals'=>'0',
                				'number_decimal_point'=>'.',
                				'number_thousands_separator'=>',',
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
								'number_format'=>'formatted',
                				'number_decimals'=>'0',
                				'number_decimal_point'=>'.',
                				'number_thousands_separator'=>',',
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
								'number_format'=>'formatted',
                				'number_decimals'=>'0',
                				'number_decimal_point'=>'.',
                				'number_thousands_separator'=>',',
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
							'views_year'=>array(
								'type'=>'column-meta',
								'label'=>'Year',
								'width'=>'',
								'width_unit'=>'%',
								'field'=>'log-views-total-365day',
								'field_type'=>'numeric',
								'number_format'=>'formatted',
                				'number_decimals'=>'0',
                				'number_decimal_point'=>'.',
                				'number_thousands_separator'=>',',
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
	$wp_admin_bar->remove_node('tribe-events');	
	$wp_admin_bar->remove_node('wp-mail-smtp-menu');	
}

// Create additional admin pages
add_action( 'admin_menu', 'battleplan_admin_menu' );
function battleplan_admin_menu() {
	$chron = 50 - get_option( 'bp_chrons_pages' );
	//add_menu_page( __( 'Run Chron', 'battleplan' ), __( 'Run Chron', 'battleplan' ), 'manage_options', 'run-chron', 'battleplan_force_run_chron', 'dashicons-performance', 3 );
	add_submenu_page( 'index.php', 'Clear ALL', 'Clear ALL', 'manage_options', 'clear-all', 'battleplan_clear_all' );	
	add_submenu_page( 'index.php', 'Clear HVAC', 'Clear HVAC', 'manage_options', 'clear-hvac', 'battleplan_clear_hvac' );	
	add_submenu_page( 'index.php', 'Run Chron', 'Run Chron <span class="awaiting-mod">'.$chron.'</span>', 'manage_options', 'run-chron', 'battleplan_force_run_chron' );	
	add_submenu_page( 'index.php', 'Site Audit', 'Site Audit', 'manage_options', 'site-audit', 'battleplan_site_audit' );	
}

function battleplan_addSitePage() { 
	echo '<h1>Admin Page</h1>';
}

// Replace WordPress copyright message at bottom of admin page
add_filter('admin_footer_text', 'battleplan_admin_footer_text');
function battleplan_admin_footer_text() { 
	$printFooter = '<div style="float:left; margin-right:8px;"><img src="https://battleplanwebdesign.com/wp-content/uploads/site-icon-80x80.png" /></div>';
	$printFooter .= '<div style="float:left; margin-top:8px;">Powered by <a href="https://battleplanwebdesign.com" target="_blank">Battle Plan Web Design</a><br>';
	$siteUpdated = str_replace('-', '', get_option( "site_updated" ));
	if ( _USER_LOGIN == "battleplanweb" ) $printFooter .= 'Site Audit: '.date("F j, Y", (int)$siteUpdated).'<br>'; 
	$printFooter .= 'Framework '._BP_VERSION.'<br>';
	$printFooter .= 'WP '.get_bloginfo('version').'<br></div>';	
	
	$printFooter .= '<div style="float:right; margin-right: 50px">';	
	$printFooter .= get_option('customer_info')['area-before'].get_option('customer_info')['area'].get_option('customer_info')['area-after'].get_option('customer_info')['phone'].'<br>';
	$printFooter .= get_option('customer_info')['street'].'<br>';
	$printFooter .= get_option('customer_info')['city'].', '.get_option('customer_info')['state-abbr'].' '.get_option('customer_info')['zip'].'<br>';
	if ( isset(get_option('customer_info')['lat']) ) $printFooter .= get_option('customer_info')['lat'].', '.get_option('customer_info')['long'].'<br>';	
	$printFooter .= '</div><div style="float:right; margin-right: 50px">';
	
	if ( isset(get_option('customer_info')['pid']) ) $printFooter .= '<b>PID:</b> <a href = "https://search.google.com/local/writereview?placeid='.get_option('customer_info')['pid'].'" target="_blank">'.get_option('customer_info')['pid'].'</a><br>';
	$printFooter .= '<b>Email:</b> <a href = "mailto:'.get_option('customer_info')['email'].'">'.get_option('customer_info')['email'].'</a><br>';
	if ( isset(get_option('customer_info')['owner-email']) ) $printFooter .= '<b>Owner:</b> <a href = "mailto:'.get_option('customer_info')['owner-email'].'">'.get_option('customer_info')['owner-email'].'</a><br>';	
	if ( isset(get_option('customer_info')['facebook']) ) $printFooter .= '<b>Facebook:</b> <a href = "'.get_option('customer_info')['facebook'].'" target="_blank">'.get_option('customer_info')['facebook'].'</a><br>';
	if ( isset(get_option('customer_info')['twitter']) ) $printFooter .= '<b>Twitter:</b> <a href = "'.get_option('customer_info')['twitter'].'" target="_blank">'.get_option('customer_info')['twitter'].'</a><br>';
	if ( isset(get_option('customer_info')['instagram']) ) $printFooter .= '<b>Instagram:</b> <a href = "'.get_option('customer_info')['instagram'].'" target="_blank">'.get_option('customer_info')['instagram'].'</a><br>';
	if ( isset(get_option('customer_info')['pinterest']) ) $printFooter .= '<b>Pinterest:</b> <a href = "'.get_option('customer_info')['pinterest'].'" target="_blank">'.get_option('customer_info')['pinterest'].'</a><br>';
	if ( isset(get_option('customer_info')['yelp']) ) $printFooter .= '<b>Yelp:</b> <a href = "'.get_option('customer_info')['yelp'].'" target="_blank">'.get_option('customer_info')['yelp'].'</a><br>';
	if ( isset(get_option('customer_info')['tiktok']) ) $printFooter .= '<b>Tiktok:</b> <a href = "'.get_option('customer_info')['tiktok'].'" target="_blank">'.get_option('customer_info')['tiktok'].'</a><br>';
	if ( isset(get_option('customer_info')['youtube']) ) $printFooter .= '<b>You Tube:</b> <a href = "'.get_option('customer_info')['youtube'].'" target="_blank">'.get_option('customer_info')['youtube'].'</a><br>';
	
	if ( isset(get_option('customer_info')['google-tags']['prop-id']) ) $printFooter .= '<b>Analytics:</b> <a href = "https://analytics.google.com/analytics/web/#/p'.get_option('customer_info')['google-tags']['prop-id'].'/reports/explorer?params=_u..nav%3Dmaui%26_u..pageSize%3D25%26_r.explorerCard..selmet%3D%5B%22sessions%22%5D%26_r.explorerCard..seldim%3D%5B%22sessionDefaultChannelGrouping%22%5D&r=lifecycle-traffic-acquisition-v2&collectionId=life-cycle" target="_blank">View Stats</a><br>';
	
	if ( isset(get_option('customer_info')['serpfox']) ) $printFooter .= '<b>Keywords:</b> <a href = "//app.serpfox.com/shared/'.get_option('customer_info')['serpfox'].'" target="_blank">View Rankings</a><br>';
		
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
	remove_submenu_page( 'options-general.php', 'git-updater' );   					//Settings => Git Updater
	remove_submenu_page( 'tools.php', 'export-personal-data.php' );   				//Tools => Export Personal Data  
	remove_submenu_page( 'tools.php', 'erase-personal-data.php' );   				//Tools => Erase Personal Data
	remove_submenu_page( 'wpseo_dashboard', 'wpseo_workouts' );   					//Yoast SEO => Workouts
	remove_submenu_page( 'wpseo_dashboard', 'wpseo_licenses' );   					//Yoast SEO => Premium
	remove_submenu_page( 'wpseo_dashboard', 'wpseo_redirects' );   					//Yoast SEO => Redirects
	remove_submenu_page( 'wpseo_dashboard', 'wpseo_redirects' );   					//Yoast SEO => Redirects

	add_submenu_page( 'upload.php', 'Favicon', 'Favicon', 'manage_options', 'customize.php' );	
	
	$the_query = new WP_Query( array('post_type' => 'elements', 'posts_per_page' => -1, 'orderby' => 'menu_order', 'order' => 'asc') );
	if ( $the_query->have_posts() ) : while ( $the_query->have_posts() ) : $the_query->the_post();
		add_submenu_page( 'edit.php?post_type=elements', get_the_title(), get_the_title(), 'manage_options', '/post.php?post='.get_the_ID().'&action=edit' );
	endwhile; endif;			
	wp_reset_postdata();	
	
	if ( is_null(get_page_by_path('widgets', OBJECT, 'elements')) ) :
		add_submenu_page( 'edit.php?post_type=elements', 'Widgets', 'Widgets', 'manage_options', 'widgets.php' );
	endif;	

	add_submenu_page( 'edit.php?post_type=elements', 'Menus', 'Menus', 'manage_options', 'nav-menus.php' );		
	add_submenu_page( 'edit.php?post_type=elements', 'Contact Forms', 'Contact Forms', 'manage_options', 'admin.php?page=wpcf7' );	
	add_submenu_page( 'edit.php?post_type=elements', 'Comments', 'Comments', 'manage_options', 'edit-comments.php' );
	add_submenu_page( 'edit.php?post_type=elements', 'Custom Fields', 'Custom Fields', 'manage_options', 'edit.php?post_type=acf-field-group' );		
	add_submenu_page( 'edit.php?post_type=elements', 'Themes', 'Themes', 'manage_options', 'themes.php' );		
	if ( is_plugin_active( 'ari-fancy-lightbox/ari-fancy-lightbox.php' ) ) { add_submenu_page( 'options-general.php', 'Lightbox', 'Lightbox', 'manage_options', 'admin.php?page=ari-fancy-lightbox' );	 }		
	add_submenu_page( 'options-general.php', 'Options', 'Options', 'manage_options', 'options.php' );
	if ( _USER_LOGIN == "battleplanweb" ) add_submenu_page( 'tools.php', 'Git Updater', 'Git Updater', 'manage_options', 'options-general.php?page=git-updater' );
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
	remove_meta_box('wp_mail_smtp_reports_widget_pro','dashboard','normal');	// WP Mail SMTP Pro
}

// Add new dashboard widgets
add_action( 'wp_dashboard_setup', 'battleplan_add_dashboard_widgets' );
function battleplan_add_dashboard_widgets() {
	if ( _USER_LOGIN == "battleplanweb" ) :
		add_meta_box( 'battleplan_site_stats', 'Site Visitors', 'battleplan_admin_site_stats', 'dashboard', 'normal', 'high' );
		add_meta_box( 'battleplan_referrer_stats', 'Referrers', 'battleplan_admin_referrer_stats', 'dashboard', 'normal', 'high' );
		add_meta_box( 'battleplan_location_stats', 'Locations', 'battleplan_admin_location_stats', 'dashboard', 'normal', 'high' );
		add_meta_box( 'battleplan_tech_stats', 'Tech Info', 'battleplan_admin_tech_stats', 'dashboard', 'normal', 'high' );	

		add_meta_box( 'battleplan_pages_stats', 'Most Popular Pages', 'battleplan_admin_pages_stats', 'dashboard', 'side', 'high' );
		add_meta_box( 'battleplan_content_stats', 'Content Visibility', 'battleplan_admin_content_stats', 'dashboard', 'side', 'high' );

		add_meta_box( 'battleplan_weekly_stats', 'Weekly Visitor Trends', 'battleplan_admin_weekly_stats', 'dashboard', 'column3', 'high' );		
		add_meta_box( 'battleplan_monthly_stats', 'Monthly Visitor Trends', 'battleplan_admin_monthly_stats', 'dashboard', 'column3', 'high' );		
		add_meta_box( 'battleplan_quarterly_stats', 'Quarterly Visitor Trends', 'battleplan_admin_quarterly_stats', 'dashboard', 'column3', 'high' );
	endif;
}

/*--------------------------------------------------------------
# Site Stats
--------------------------------------------------------------*/

// Set up dashboard stats review
$GLOBALS['displayTerms'] = array( 'week'=>7, 'month'=>30, 'quarter'=>90, 'year'=>365 );
$GLOBALS['btn1'] = get_option('bp_admin_btn1') != null ? get_option('bp_admin_btn1') : "month";
$GLOBALS['btn2'] = get_option('bp_admin_btn2') != null ? get_option('bp_admin_btn2') : "sessions";
$GLOBALS['btn3'] = get_option('bp_admin_btn3') != null ? get_option('bp_admin_btn3') : "not-active";

$siteHits = get_option('bp_site_hits_ga4');	
$siteHitsUA1 = get_option('bp_site_hits_ua_1') ? get_option('bp_site_hits_ua_1') : array();
$siteHitsUA2 = get_option('bp_site_hits_ua_2') ? get_option('bp_site_hits_ua_2') : array();
$siteHitsUA3 = get_option('bp_site_hits_ua_3') ? get_option('bp_site_hits_ua_3') : array();
$siteHitsUA4 = get_option('bp_site_hits_ua_4') ? get_option('bp_site_hits_ua_4') : array();
$siteHitsUA5 = get_option('bp_site_hits_ua_5') ? get_option('bp_site_hits_ua_5') : array();
$siteHitsUA = array_merge( $siteHitsUA1, $siteHitsUA2, $siteHitsUA3, $siteHitsUA4, $siteHitsUA5);
if ( $siteHitsUA && is_array($siteHits)) $siteHits = array_merge($siteHits, $siteHitsUA);

$today = date( "Y-m-d" );	
$GLOBALS['citiesToExclude'] = array('Orangetree, FL', 'Ashburn, VA', 'Boardman, OR'); // also change in functions-chron-jobs.php

// Set up array accounting for each day, no skips	
$blankDate = 0;
if ( is_array($siteHits) ) $blankDate = strtotime($siteHits[array_key_last($siteHits)]['date']);
$totalDays = (strtotime($today) - $blankDate) / 86400;

for ( $x=0;$x<$totalDays;$x++) :
	$blankDate = $blankDate + 86400;		
	$dailyStats[ date('Y-m-d', $blankDate) ] = array ('location'=>array(), 'source'=>array(), 'medium'=>array(), 'page'=>array(), 'browser'=>array(), 'device'=>array(), 'resolution'=>array(), 'pages-viewed'=>'0', 'sessions'=>'0', 'engaged'=>'0', 'new-users'=>'0' );
endfor;

// Compile data into daily stats
if ( !is_array($siteHits) ) $siteHits = array();
$lastView = $totalPageviews = $totalSessions = $totalEngaged = $totalNewUsers = $allPages = 0;
$allLocations = $allSources = $allMediums = $allPages = $allBrowsers = $allDevices = $allResolutions = array();

foreach ( $siteHits as $siteHit ) :	
	if ( isset($siteHit['location']) && !in_array( $siteHit['location'], $GLOBALS['citiesToExclude'] ) ) :
		if ( $GLOBALS['btn3'] != "active" || ( $siteHit['location'] == get_option('customer_info')['state-full'] || str_contains($siteHit['location'], get_option('customer_info')['state-abbr'] ) )) :
	
			$processing = strtotime($siteHit['date']);
			$timeSinceToday = strtotime($today) - $processing;				
			$timeSinceLastView = $lastView - $processing;			
			$daysSinceToday = $timeSinceToday / 86400;

			if ( $timeSinceLastView > 0 ) :			
				$dailyStats[ date('Y-m-d', ($processing + 86400)) ] = array ('location'=>$allLocations, 'source'=>$allSources, 'medium'=>$allMediums, 'page'=>$allPages, 'browser'=>$allBrowsers, 'device'=>$allDevices, 'resolution'=>$allResolutions, 'pages-viewed'=>$totalPageviews, 'sessions'=>$totalSessions, 'engaged'=>$totalEngaged, 'new-users'=>$totalNewUsers );	

				$allLocations = $allSources = $allMediums = $allPages = $allBrowsers = $allDevices = $allResolutions = array();
				$totalPageviews = $totalSessions = $totalEngaged = $totalNewUsers = 0;
			endif;

			$lastView = $processing;

			if ( isset($siteHit['pages-viewed']) ) $totalPageviews = $totalPageviews + (int)$siteHit['pages-viewed'];
			if ( isset($siteHit['sessions']) ) $totalSessions = $totalSessions + (int)$siteHit['sessions'];
			if ( isset($siteHit['engaged']) ) $totalEngaged = $totalEngaged + (int)$siteHit['engaged'];
			if ( isset($siteHit['new-users']) ) $totalNewUsers = $totalNewUsers + (int)$siteHit['new-users'];											

			if ( isset($siteHit['page']) && isset($siteHit['pages-viewed']) ) :
				if ( is_array($allPages) && array_key_exists($siteHit['page'], $allPages ) ) :
					$allPages[$siteHit['page']] += (int)$siteHit['pages-viewed'];
				else:
					$allPages[$siteHit['page']] = (int)$siteHit['pages-viewed'];
				endif;	
			endif;

			if ( $siteHit['sessions'] == 1 ) :
				if ( isset($siteHit['location']) ) :
					if ( is_array($allLocations) && array_key_exists($siteHit['location'], $allLocations ) ) :
						$allLocations[$siteHit['location']] += 1;
					else:
						$allLocations[$siteHit['location']] = 1;
					endif;									
				endif;									

				if ( isset($siteHit['source']) ) :
					if ( is_array($allSources) && array_key_exists($siteHit['source'], $allSources ) ) :
						$allSources[$siteHit['source']] += 1;
					else:
						$allSources[$siteHit['source']] = 1;
					endif;									
				endif;									

				if ( isset($siteHit['medium']) ) :
					if ( is_array($allMediums) && array_key_exists($siteHit['medium'], $allMediums ) ) :
						$allMediums[$siteHit['medium']] += 1;
					else:
						$allMediums[$siteHit['medium']] = 1;
					endif;									
				endif;									

				if ( isset($siteHit['browser']) ) :
					if ( is_array($allBrowsers) && array_key_exists($siteHit['browser'], $allBrowsers ) ) :
						$allBrowsers[$siteHit['browser']] += 1;
					else:
						$allBrowsers[$siteHit['browser']] = 1;
					endif;									
				endif;									

				if ( isset($siteHit['device']) ) :
					if ( is_array($allDevices) && array_key_exists($siteHit['device'], $allDevices ) ) :
						$allDevices[$siteHit['device']] += 1;
					else:
						$allDevices[$siteHit['device']] = 1;
					endif;									
				endif;									

				if ( isset($siteHit['resolution']) ) :
					if ( is_array($allResolutions) && array_key_exists($siteHit['resolution'], $allResolutions ) ) :
						$allResolutions[$siteHit['resolution']] += 1;
					else:
						$allResolutions[$siteHit['resolution']] = 1;
					endif;									
				endif;									
			endif;
		endif;
	endif;
endforeach;	

krsort($dailyStats);
array_shift($dailyStats);
$GLOBALS['dailyStats'] = $dailyStats;

if ( !is_array($GLOBALS['dailyStats']) ) $GLOBALS['dailyStats'] = array();	
$GLOBALS['dates'] = array_keys($GLOBALS['dailyStats']);

// Set up Site Visitors widget on dashboard
function battleplan_admin_site_stats() {
	$lastVisitTime = timeElapsed( get_option('last_visitor_time'), 2);	
	
	$count = $users = $search = $pagesViewed = $sessions = $engaged = $engagement = $endOfCol = $viewsToday = $last7Views = $last30Views = $last90Views = $last180Views = $lastYearViews = $last2YearViews = $last3YearViews = 0;
		
	for ($x = 0; $x < 1096; $x++) {	
		if ( !isset($GLOBALS['dates'][$x])) break;
		$theDate = $GLOBALS['dates'][$x];
		if ( isset ($GLOBALS['dailyStats'][$theDate]['new-users']) ) $dailyUsers = intval($GLOBALS['dailyStats'][$theDate]['new-users']); 
		$users = $users + $dailyUsers;			
				
		if ( $x == 1 ) $viewsToday = $users; 
		if ( $x == 7 ) $last7Views = $users; $last7Avg = number_format(($last7Views / 7),1);
		if ( $x == 30 ) $last30Views = $users; $last30Avg = number_format(($last30Views / 30),1);
		if ( $x == 90 ) $last90Views = $users; $last90Avg = number_format(($last90Views / 90),1);
		if ( $x == 180 ) $last180Views = $users; $last180Avg = number_format(($last180Views / 180),1);		
		if ( $x == 365 ) $lastYearViews = $users; $lastYearAvg = number_format(($lastYearViews / 365),1);		
		if ( $x == 730 ) $last2YearViews = $users; $last2YearAvg = number_format(($last2YearViews / 730),1);		
		if ( $x == 1095 ) $last3YearViews = $users; $last3YearAvg = number_format(($last3YearViews / 1095),1);
	} 		 
		
	echo "<table><tr><td class='label'>Last Visit</td><td class='last-visit'>".$lastVisitTime." ago</td></tr>";
	
	echo "<tr><td>&nbsp;</td></tr>";	
	
	echo "<tr><td class='label'>Yesterday</td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $viewsToday, 'battleplan' ), number_format($viewsToday))."</td></tr>";	
	
	echo "<tr><td class='label'>This Week</td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $last7Views, 'battleplan' ), number_format($last7Views) )."</td><td><b>".$last7Avg."</b> /day</td></tr>";
	
	if ( $last30Views != $last7Views) echo "<tr><td class='label'>This Month</td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $last30Views, 'battleplan' ), number_format($last30Views) )."</td><td><b>".$last30Avg."</b> /day</td></tr>";
	
	if ( $last90Views != $last30Views) echo "<tr><td class='label'>3 Months</td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $last90Views, 'battleplan' ), number_format($last90Views) )."</td><td><b>".$last90Avg."</b> /day</td></tr>";
	
	if ( $last180Views != $last90Views) echo "<tr><td class='label'>6 Months</td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $last180Views, 'battleplan' ), number_format($last180Views) )."</td><td><b>".$last180Avg."</b> /day</td></tr>";
	
	if ( $lastYearViews != $last180Views) echo "<tr><td class='label'>1 Year</td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $lastYearViews, 'battleplan' ), number_format($lastYearViews) )."</td><td><b>".$lastYearAvg."</b> /day</td></tr>";
	
	if ( $last2YearViews != $lastYearViews) echo "<tr><td class='label'>2 Years</td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $last2YearViews, 'battleplan' ), number_format($last2YearViews) )."</td><td><b>".$last2YearAvg."</b> /day</td></tr>";
	
	if ( $last3YearViews != $last2YearViews) echo "<tr><td class='label'>3 Years</td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $last3YearViews, 'battleplan' ), number_format($last3YearViews) )."</td><td><b>".$last3YearAvg."</b> /day</td></tr>";
	
	echo '<tr><td>&nbsp;</td></tr></table>';
}

// Set up Visitor Referrers widget on dashboard
function battleplan_admin_referrer_stats() {
	echo '<div class="last-visitors-buttons">';	
		foreach ( $GLOBALS['displayTerms'] as $display=>$days ) :
			if ( $GLOBALS['btn1'] == $display ) : $active = " active"; else: $active = ""; endif;
			echo do_shortcode('[btn size="1/5" class="'.$display.$active.'"]'.ucwords($display).'[/btn]');	
		endforeach;	
	echo '</div>';
				
	echo '<div class="local-visitors-buttons">'.do_shortcode('[btn size="1/5" class="local '.$GLOBALS['btn3'].'"]Local[/btn]').'</div>';	
	
	foreach ( $GLOBALS['displayTerms'] as $display=>$days ) :
		$allSources = array();
		for ($x = 0; $x < $days; $x++) :	
		 	if ( !isset($GLOBALS['dates'][$x])) break;
			$theDate = $GLOBALS['dates'][$x];
			if ( isset ($GLOBALS['dailyStats'][$theDate]['source']) ) $sources = $GLOBALS['dailyStats'][$theDate]['source'];	
			
			foreach ( $sources as $source=>$counts ) :			
				$switchRef = array ('(direct)'=>'Direct', 'google'=>'Google', 'facebook'=>'Facebook', 'yelp'=>'Yelp', 'yahoo'=>'Yahoo', 'bing'=>'Bing', 'duckduckgo'=>'DuckDuckGo', 'youtube'=>'YouTube', 'instagram'=>'Instagram');
				foreach ( $switchRef as $find=>$replace ) :
					if ( strpos( $source, $find ) !== false ) $source = $replace;
				endforeach;
				
				if ( is_array($allSources) && array_key_exists($source, $allSources ) ) :
					$allSources[$source] += $counts;
				else:
					$allSources[$source] = $counts;
				endif;		
			endforeach;		
		endfor;		
		
		if ( is_array($allSources) ) arsort($allSources);
		
		if ( $GLOBALS['btn1'] == $display ) : $active = " active"; else: $active = ""; endif;
		echo '<div class="handle-label handle-label-'.$display.$active.'"><ul>';		
		echo '<li class="sub-label" style="column-span: all">Last '.number_format(array_sum($allSources)).' Sessions</li>';	
		
		foreach ( $allSources as $source=>$count ) :
			echo "<li><div class='value'><b>".number_format($count)."</b></div><div class='label'>".$source."</div></li>";
		endforeach;			
			
		echo '</ul></div>';		
	endforeach;			
}

// Set up Visitor Locations widget on dashboard
function battleplan_admin_location_stats() {
	foreach ( $GLOBALS['displayTerms'] as $display=>$days ) :
		$allLocations = array();
		for ($x = 0; $x < $days; $x++) :	
			if ( !isset($GLOBALS['dates'][$x])) break;
			$theDate = $GLOBALS['dates'][$x];
			if ( isset ($GLOBALS['dailyStats'][$theDate]['location']) ) $locations = $GLOBALS['dailyStats'][$theDate]['location'];	
			
			foreach ( $locations as $location=>$counts ) :			
				if ( is_array($allLocations) && array_key_exists($location, $allLocations ) ) :
					$allLocations[$location] += $counts;
				else:
					$allLocations[$location] = $counts;
				endif;		
			endforeach;		
		endfor;		
		
		if ( is_array($allLocations) ) arsort($allLocations);
		
		if ( $GLOBALS['btn1'] == $display ) : $active = " active"; else: $active = ""; endif;
		echo '<div class="handle-label handle-label-'.$display.$active.'"><ul>';		
		echo '<li class="sub-label" style="column-span: all">Last '.number_format(array_sum($allLocations)).' Sessions</li>';	
		
		foreach ( $allLocations as $location=>$count ) :
			echo "<li><div class='value'><b>".number_format($count)."</b></div><div class='label'>".$location."</div></li>";
		endforeach;			
			
		echo '</ul></div>';		
	endforeach;			
}

// Set up Tech Info widget on dashboard
function battleplan_admin_tech_stats() {
//Browser
	foreach ( $GLOBALS['displayTerms'] as $display=>$days ) :
		$allBrowsers = array();
		for ($x = 0; $x < $days; $x++) :	
			if ( !isset($GLOBALS['dates'][$x])) break;
			$theDate = $GLOBALS['dates'][$x];
			if ( isset ($GLOBALS['dailyStats'][$theDate]['browser']) ) $browsers = $GLOBALS['dailyStats'][$theDate]['browser'];	
			
			foreach ( $browsers as $browser=>$counts ) :			
				if ( is_array($allBrowsers) && array_key_exists($browser, $allBrowsers ) ) :
					$allBrowsers[$browser] += $counts;
				else:
					$allBrowsers[$browser] = $counts;
				endif;		
			endforeach;		
		endfor;		
		
		if ( is_array($allBrowsers) ) arsort($allBrowsers);
		
		if ( $GLOBALS['btn1'] == $display ) : $active = " active"; else: $active = ""; endif;
		echo '<div class="handle-label handle-label-'.$display.$active.'"><ul><li class="sub-label">Browsers</li>';	
		
		foreach ( $allBrowsers as $browser=>$count ) :
			$count = ($count / array_sum($allBrowsers)) * 100;	
			if ( $count > 3) echo "<li><div class='value'><b>".number_format($count,1)."%</b></div><div class='label'>".ucwords($browser)."</div></li>";
		endforeach;			
			
		echo '</ul></div>';		
	endforeach;		

// Devices
	$allTracking = get_option('bp_tracking_content');
	$allSpeed = array();

	if ( isset($allTracking) && is_array($allTracking) ) :
		foreach ( $allTracking as $tracking ) :
			$site_speed = $tracking['speed'];
			$location = $tracking['location'];

			if ( !in_array( $location, $GLOBALS['citiesToExclude']) && $site_speed != '' ) :			
				$pageID = strtok($site_speed,  '»');
				if ( strpos($site_speed, 'desktop') !== false ) : $device = "desktop"; else: $device = "mobile"; endif;			
				$speed = (float)str_replace($pageID.'»'.$device.'«', '', $site_speed);

				if ( is_array($allSpeed) && array_key_exists($pageID, $allSpeed ) ) :
					$allSpeed[$pageID]['speed'] += $speed;
					$allSpeed[$pageID]['hits'] += 1;
				else:
					$allSpeed[$pageID]['speed'] = $speed;
					$allSpeed[$pageID]['hits'] = 1;
				endif;						 		

				if ( $allSpeed[$pageID]['hits'] > 0 ) $allSpeed[$pageID]['avg'] = round($allSpeed[$pageID]['speed'] / $allSpeed[$pageID]['hits'], 2);	

				if ( is_array($allSpeed) && array_key_exists($device, $allSpeed ) ) :
					$allSpeed[$device]['speed'] += $speed;
					$allSpeed[$device]['hits'] += 1;
				else:
					$allSpeed[$device]['speed'] = $speed;
					$allSpeed[$device]['hits'] = 1;
				endif;	

				if ( $allSpeed[$device]['hits'] > 0 ) $allSpeed[$device]['avg'] = round($allSpeed[$device]['speed'] / $allSpeed[$device]['hits'], 2);	
			endif;			
		endforeach;		
	endif;
	
	foreach ( $GLOBALS['displayTerms'] as $display=>$days ) :
		$allDevices = array();
		for ($x = 0; $x < $days; $x++) :	
			if ( !isset($GLOBALS['dates'][$x])) break;
			$theDate = $GLOBALS['dates'][$x];
			if ( isset ($GLOBALS['dailyStats'][$theDate]['device']) ) $devices = $GLOBALS['dailyStats'][$theDate]['device'];	
			
			foreach ( $devices as $device=>$counts ) :			
				if ( is_array($allDevices) && array_key_exists($device, $allDevices ) ) :
					$allDevices[$device] += $counts;
				else:
					$allDevices[$device] = $counts;
				endif;		
			endforeach;		
		endfor;		
		
		if ( is_array($allDevices) ) arsort($allDevices);
		
		if ( $GLOBALS['btn1'] == $display ) : $active = " active"; else: $active = ""; endif;
		echo '<div class="handle-label handle-label-'.$display.$active.'"><ul><li class="sub-label">Devices</li>';	
		
		foreach ( $allDevices as $device=>$count ) :
			$count = ($count / array_sum($allDevices)) * 100;
			if ( isset($allSpeed[$device]['avg'])) :
				echo '<li><div class="value"><b>'.number_format($count,1).'%</b></div><div class="label-half">'.ucwords($device).'</div><div class="label-half">'.number_format($allSpeed[$device]['avg'],1).' sec</div></li>';
				updateOption('load_time_'.$device, number_format($allSpeed[$device]['avg'],1), false );
			endif;
		endforeach;			
			
		echo '</ul></div>';		
	endforeach;		
	
// Screen Resolution	
	foreach ( $GLOBALS['displayTerms'] as $display=>$days ) :
		$allResolutions = array();
		for ($x = 0; $x < $days; $x++) :	
			if ( !isset($GLOBALS['dates'][$x])) break;
			$theDate = $GLOBALS['dates'][$x];
			if ( isset ($GLOBALS['dailyStats'][$theDate]['resolution']) ) $resolutions = $GLOBALS['dailyStats'][$theDate]['resolution'];	
			
			foreach ( $resolutions as $resolution=>$counts ) :	
				if ( $resolution ) :
					if ( is_array($allResolutions) && array_key_exists($resolution, $allResolutions ) ) :
						$allResolutions[$resolution] += $counts;
					else:
						$allResolutions[$resolution] = $counts;
					endif;	
				endif;
			endforeach;		
		endfor;		
		
		if ( is_array($allResolutions) ) arsort($allResolutions); 
		
		if ( $GLOBALS['btn1'] == $display ) : $active = " active"; else: $active = ""; endif;
		echo '<div class="handle-label handle-label-'.$display.$active.'"><ul><li class="sub-label">Screen Widths</li><div style="column-count:2">';	
		
		foreach ( $allResolutions as $resolution=>$count ) :
			$resolution = substr($resolution, 0, strpos($resolution, 'x'));
			$count = ($count / array_sum($allResolutions)) * 100;
			if ( $count > 2) echo "<li><div class='value'><b>".number_format($count,1)."%</b></div><div class='label'>".ucwords($resolution)." px</div></li>";
		endforeach;			
			
		echo '</div></ul></div>';		
	endforeach;	
}

// Set up Content Info widget on dashboard
function battleplan_admin_content_stats() {
	$contentTracking = $totalTracking = array();
	$allTracking = get_option('bp_tracking_content');
	
	if ( isset($allTracking) && is_array ($allTracking) ) :
		foreach ( $allTracking as $tracking ) :
			$content_tracking = $tracking['content'];			
			$location = $tracking['location'];

			if ( !in_array( $location, $GLOBALS['citiesToExclude']) && $content_tracking != '' ) :
				$pageID = strtok($content_tracking,  '-');
				$track = str_replace($pageID.'-', '', $content_tracking);

				if ( isset($contentTracking) && isset($contentTracking[$pageID]) ):		
					if ( is_array($contentTracking) && array_key_exists($pageID, $contentTracking ) ) :
						$contentTracking[$pageID][$track] += 1;
					else:
						$contentTracking[$pageID][$track] = 1;
					endif;	
				endif;
			endif;			
		endforeach;
	endif;
	
	if ( isset($contentTracking) && is_array ($contentTracking) ) :
		foreach ( $contentTracking as $id=>$content) :
			foreach ( $content as $track=>$count ) :	
				if ( $id == "track" ) :
					if ( is_array($componentTracking) && array_key_exists($track, $componentTracking ) ) :
						$componentTracking[$track] += $count;
					else:
						$componentTracking[$track] = $count;
					endif;				
				elseif ( strpos($track, '.') !== false ) :
					$track = explode(".", $track);
					$page = ucwords(get_the_title($id));
					$page = (strlen($page) > 17) ? substr($page,0,15).'&hellip;' : $page;			
					$column = $page.' · s'.$track[0].' c'.$track[1];
					if ( is_array($colTracking) && array_key_exists($column, $colTracking ) ) :
						$colTracking[$column] += $count;
					else:
						$colTracking[$column] = $count;
					endif;				
				else :
					if ( is_array($totalTracking) && array_key_exists($track, $totalTracking ) ) :
						$totalTracking[$track] += $count;
					else:
						$totalTracking[$track] = $count;
					endif;				
				endif;
			endforeach;
		endforeach;
	endif;
	
	echo '<div>';
	
	if ( isset($totalTracking['init'])) : 
		echo '<ul><li class="sub-label">Last '.$totalTracking['init'].' Pageviews</li>';
		if ( $totalTracking['init'] > 0 ) :
			echo "<li><div class='value'><b>".number_format((round($totalTracking['100']/$totalTracking['init'],3) * 100),1)."%</b></div><div class='label'><b>viewed ALL of main content</b></div></li>";	
			echo "<li><div class='value'><b>".number_format((round($totalTracking['80']/$totalTracking['init'],3) * 100),1)."%</b></div><div class='label'><b>viewed at least 80% of main content</b></div></li>";	
			echo "<li><div class='value'><b>".number_format((round($totalTracking['60']/$totalTracking['init'],3) * 100),1)."%</b></div><div class='label'><b>viewed at least 60% of main content</b></div></li>";	
			echo "<li><div class='value'><b>".number_format((round($totalTracking['40']/$totalTracking['init'],3) * 100),1)."%</b></div><div class='label'><b>viewed at least 40% of main content</b></div></li>";	
			echo "<li><div class='value'><b>".number_format(100-(round($totalTracking['20']/$totalTracking['init'],3) * 100),1)."%</b></div><div class='label'><b>viewed less than 20% of main content</b></div></li>";
		endif;
		echo '</ul>';
	endif;
	
	if ( isset($componentTracking) && is_array($componentTracking)) :
		arsort($componentTracking);
		echo '<ul><li class="sub-label">Components</li>';
		foreach($componentTracking as $track=>$count) :
			if ( $track != "init" && $componentTracking['init'] > 0 ) echo "<li><div class='value'><b>".number_format((round($componentTracking[$track]/$componentTracking['init'],3) * 100),1)."%</b></div><div class='label'><b>".ucwords($track)."</b></div></li>";	

			updateOption('pct-viewed-'.$track, number_format((round($componentTracking[$track]/$componentTracking['init'],3) * 100),1), false );
		endforeach;	
		echo '</ul>';
	endif;

	if ( isset($colTracking) && is_array($colTracking)) :
		arsort($colTracking);
		echo '<ul><li class="sub-label">Best Column Positions</li><div style="column-count:2">';		
		foreach ( $colTracking as $page=>$count) :
			echo "<li><div class='value'><b>".$count."</b></div><div class='label'>".$page."</div></li>";	
		endforeach;
		echo '</div></ul>';
	endif;
	
	echo '</div>';
}

// Set up Popular Pages widget on dashboard
function battleplan_admin_pages_stats() {
	foreach ( $GLOBALS['displayTerms'] as $display=>$days ) :
		$allPages = array();
		for ($x = 0; $x < $days; $x++) :	
			if ( !isset($GLOBALS['dates'][$x])) break;
			$theDate = $GLOBALS['dates'][$x];
			if ( isset ($GLOBALS['dailyStats'][$theDate]['page']) ) $pages = $GLOBALS['dailyStats'][$theDate]['page'];	
			
			foreach ( $pages as $page=>$counts ) :	
				$excludePage = false;
				$excludes = array ( '?fbclid', '?dMe', '?_sm_nck', '?mscl' );
				foreach ( $excludes as $exclude ) :
					if ( strpos( $page, $exclude ) !== false ) $excludePage = true;
				endforeach;		
			
				if ( $excludePage == false ) :
					if ( is_array($allPages) && array_key_exists($page, $allPages ) ) :
						$allPages[$page] += $counts;
					else:
						$allPages[$page] = $counts;
					endif;	
				endif;
			endforeach;		
		endfor;		
		
		if ( is_array($allPages) ) arsort($allPages);
		
		if ( $GLOBALS['btn1'] == $display ) : $active = " active"; else: $active = ""; endif;
		echo '<div class="handle-label handle-label-'.$display.$active.'"><ul>';		
		echo '<li class="sub-label" style="column-span: all">Last '.number_format(array_sum($allPages)).' Pageviews</li>';	
		
		foreach ( $allPages as $page=>$count ) :
			if ( $page == "" ) :
				$title = "Home";					
			else:
				$title = get_the_title(getID($page));
				if ( !$title ) :
					$page = str_replace('/', ' » ', $page);				
					$page = str_replace('-', ' ', $page);				
					$title = ucwords($page);		
				endif;
			endif;	

			echo "<li><div class='value'><b>".number_format($count)."</b></div><div class='label'>".$title."</div></li>";
		endforeach;			
			
		echo '</ul></div>';		
	endforeach;			
}

// Set up Visitor Trends widget on dashboard
function battleplan_admin_stats($time,$minDays,$maxDays,$colEnd) {
	$count = $sessions = $search = $users = $pagesViewed = $engaged = $engagement = $endOfCol = 0;
	$days = $minDays;		
	$colNum = 1;
	
	echo "<table class='trends trends-".$time." trends-col-".$colNum."'><tr><td class='header dates'>".ucfirst($time)."</td><td class='page visits'>".ucwords($GLOBALS['btn2'])."</td></tr>";	

	for ($x = 0; $x < 1500; $x++) {	
		if ( !isset($GLOBALS['dates'][$x])) break;
		$theDate = $GLOBALS['dates'][$x];
		$dailyTime = date("M j, Y", strtotime($theDate)); 
		if ( isset($GLOBALS['dailyStats'][$theDate]['sessions'])) $dailySessions = intval($GLOBALS['dailyStats'][$theDate]['sessions']); 
		if ( isset($GLOBALS['dailyStats'][$theDate]['medium']['organic'])) $dailySearch = intval($GLOBALS['dailyStats'][$theDate]['medium']['organic']); 
		if ( isset($GLOBALS['dailyStats'][$theDate]['new-users'])) $dailyUsers = intval($GLOBALS['dailyStats'][$theDate]['new-users']); 		
		if ( isset($GLOBALS['dailyStats'][$theDate]['pages-viewed'])) $dailyPageviews = intval($GLOBALS['dailyStats'][$theDate]['pages-viewed']);
		if ( isset($GLOBALS['dailyStats'][$theDate]['engaged'])) $dailyEngaged = intval($GLOBALS['dailyStats'][$theDate]['engaged']); 
		
		$count++;		
		if (isset($dailySessions) ) $sessions = $sessions + $dailySessions; 		
		if (isset($dailySearch) ) $search = $search + $dailySearch; 
		if (isset($dailyUsers) ) $users = $users + $dailyUsers;			
		if (isset($$dailyPageviews) ) $pagesViewed = $pagesViewed + $dailyPageviews; 		
		if (isset($dailyEngaged) ) $engaged = $engaged + $dailyEngaged; 
		
		if ( $sessions > 0 ) $pagesPerSession = number_format( (round(($pagesViewed / $sessions), 3)) , 1, '.', '');
		if ( $sessions > 0 ) $engagement = number_format( ((round(($engaged / $sessions), 3)) * 100), 1, '.', '');		
								
		if ( $count == 1 ) $end = $dailyTime;
		if ( $count == $days && $dailyTime != "Jan 1, 1970" ) :
			if ( $endOfCol == $colEnd ) :
				$colNum++;
				echo "</table><table class='trends trends-".$time." trends-col-".$colNum."'><tr><td class='header dates'>".ucfirst($time)."</td><td class='page visits'>".ucwords($GLOBALS['btn2'])."</td></tr>";
				$endOfCol = 0;
			endif;
			$endOfCol++;
			
			$active['sessions'] = $active['search'] = $active['new'] = $active['pages'] = $active['engaged'] = '';
			$active[$GLOBALS['btn2']] = " active";

		 	echo "<tr class='coloration trends sessions".$active['sessions']."' data-count='".$sessions."'><td class='dates'>".$end."</td><td class='visits'><b>".number_format($sessions)."</b></td></tr>";
			echo "<tr class='coloration trends search".$active['search']."' data-count='".$search."'><td class='dates'>".$end."</td><td class='visits'><b>".number_format($search)."</b></td></tr>";			
		 	echo "<tr class='coloration trends new".$active['new']."' data-count='".$users."'><td class='dates'>".$end."</td><td class='visits'><b>".number_format($users)."</b></td></tr>";
			echo "<tr class='coloration trends pages".$active['pages']."' data-count='".$pagesViewed."'><td class='dates'>".$end."</td><td class='visits'><b>".number_format($pagesPerSession,1)."</b></td></tr>";
			echo "<tr class='coloration trends engaged".$active['engaged']."' data-count='".$engagement."'><td class='dates'>".$end."</td><td class='visits'><b>".$engagement."%</b></td></tr>";
			$count = $sessions = $search = $users = $pagesViewed = $engaged = $engagement = $pagesPerSession = 0;
			if ( $days == $maxDays ) : $days = $minDays; else: $days = $maxDays; endif;
		endif;	
	} 		
	echo "</table>";
}

function battleplan_admin_weekly_stats() {
	$active['sessions'] = $active['search'] = $active['new'] = $active['pages'] = $active['engaged'] = '';
	$active[$GLOBALS['btn2']] = " active";
	
	echo '<div class="trend-buttons">';
		echo do_shortcode('[btn size="1/5" class="sessions'.$active['sessions'].'"]Sessions[/btn]');
		echo do_shortcode('[btn size="1/5" class="search'.$active['search'].'"]Search[/btn]');
		echo do_shortcode('[btn size="1/5" class="new'.$active['new'].'"]New[/btn]');
		echo do_shortcode('[btn size="1/5" class="pages'.$active['pages'].'"]Pageviews[/btn]');
		echo do_shortcode('[btn size="1/5" class="engaged'.$active['engaged'].'"]Engagement[/btn]');
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
	echo "<tr><td><b>Yesterday</b></td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $viewsToday, 'battleplan' ), number_format($viewsToday) )."</td></tr>";	
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
			
	$lastViewed = readMeta( $post_id, 'log-last-viewed' );
	if ( !$lastViewed ) updateMeta( $post_id, 'log-last-viewed', strtotime("-2 days"));	

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
	if ( isset($actions['edit']) ) $edit = str_replace( "Edit", "<i class='dashicons-edit'></i>", $actions['edit'] );
	if ( isset($actions['delete']) ) $delete = str_replace( "Delete", "<i class='dashicons-trash'></i>", $actions['delete'] );
	if ( isset($actions['switch_to_user']) ) $switch = str_replace( "Switch&nbsp;To", "<i class='dashicons-randomize'></i>", $actions['switch_to_user'] );

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
		updateMeta( $post_ID, 'log-last-viewed', strtotime("-2 days"));		
		updateMeta( $post_ID, 'log-views-today', '0' );		
		updateMeta( $post_ID, 'log-views-total-7day', '0' );		
		updateMeta( $post_ID, 'log-views-total-30day', '0' );
		updateMeta( $post_ID, 'log-views-total-90day', '0' );
		updateMeta( $post_ID, 'log-views-total-365day', '0' );
		updateMeta( $post_ID, 'log-views', array( 'date'=> strtotime(date("F j, Y")), 'views' => 0 ));					
	} 
}

// Force clear all views for posts/pages
function battleplan_force_run_chron() {
	updateOption('bp_chrons_pages', 10000);	
	header("Location: /wp-admin/index.php");
	exit();
}  

/*--------------------------------------------------------------
# Site Audit Set Up
--------------------------------------------------------------*/

// Keep logs of site audits
function battleplan_site_audit() {
	$today = date( "Y-m-d" );
	$submitCheck = $_POST['submit_check'];
	$siteType = get_option('customer_info')['site-type'];
	
	$criteria = array ('lighthouse-mobile-score', 'lighthouse-mobile-fcp', 'lighthouse-mobile-si', 'lighthouse-mobile-lcp', 'lighthouse-mobile-tti', 'lighthouse-mobile-tbt', 'lighthouse-mobile-cls', 'lighthouse-desktop-score', 'lighthouse-desktop-fcp', 'lighthouse-desktop-si', 'lighthouse-desktop-lcp', 'lighthouse-desktop-tti', 'lighthouse-desktop-tbt', 'lighthouse-desktop-cls', 'keyword-page-1', 'keyword-needs-attn', 'cite-citations', 'cite-total-links', 'cite-local-links', 'cite-domains', 'gmb-overview', 'gmb-calls', 'gmb-msg', 'gmb-clicks', 'google-rating', 'google-reviews');
	
	if ( $submitCheck == "true" ) :
		$siteAudit = get_option('bp_site_audit_details');
		if ( !is_array($siteAudit) ) $siteAudit = array();	
		foreach ( $criteria as $log ) :
			if ( $_POST[$log] || $_POST[$log] == '0' ) :
				$decimals = 0;
				if ( $log == "lighthouse-mobile-si" || $log == "lighthouse-desktop-si" || $log == "lighthouse-mobile-fcp" || $log == "lighthouse-desktop-fcp" || $log == "lighthouse-mobile-lcp" || $log == "lighthouse-desktop-lcp" || $log == "lighthouse-mobile-tti" || $log == "lighthouse-desktop-tti" ) : $decimals = 1; endif;
				if ( $log == "lighthouse-mobile-cls" || $log == "lighthouse-desktop-cls" ) : $decimals = 3; endif;
				$updateNum = number_format((string)$_POST[$log], $decimals);
				$siteAudit[$today][$log] = str_replace('.0', '', $updateNum);
			elseif ( !$siteAudit[$today][$log] && $siteAudit[$today][$log] != '0' ) : 
				$siteAudit[$today][$log] = "n/a";
			endif;		
		endforeach;	
	endif;
		
	array_push( $criteria, 'load_time_mobile', 'load_time_desktop', 'optimized', 'testimonials', 'menu-testimonials', 'testimonials-pct', 'coupon', 'coupon-pct', 'blog', 'galleries', 'home-call-to-action', 'homepage-teasers', 'logo-slider', 'service-map', 'bbb-link', 'financing-link', 'menu-finance', 'finance-pct', 'why-choose', 'emergency-service', 'symptom-checker', 'faq', 'tip-of-the-month', 'maintenance-tips');	
	
	if ( $submitCheck == "true" ) :
		if ( $_POST['notes'] ) :
			$siteAudit[$today]['notes'] .= "  ".$_POST['notes'];
		endif;	
		
		$googleInfo = get_option('bp_google_reviews');
		$siteAudit[$today]['google-rating'] = number_format($googleInfo['rating'], 1, '.', ',');
		$siteAudit[$today]['google-reviews'] = $googleInfo['number'];
		$siteAudit[$today]['load_time_mobile'] = get_option('load_time_mobile');	
		$siteAudit[$today]['load_time_desktop'] = get_option('load_time_desktop');		
		$siteAudit[$today]['testimonials-pct'] = get_option('pct-viewed-testimonials').'%';
		$siteAudit[$today]['coupon-pct'] = get_option('pct-viewed-coupon').'%';
		$siteAudit[$today]['finance-pct'] = get_option('pct-viewed-financing').'%';
		
		if ( wp_count_posts( 'post' )->publish > 0 ) : $siteAudit[$today]['blog'] = wp_count_posts( 'post' )->publish; else: $siteAudit[$today]['blog'] = "false"; endif;
		
		if ( wp_count_posts( 'optimized' )->publish > 0 ) : $siteAudit[$today]['optimized'] = wp_count_posts( 'optimized' )->publish; else: $siteAudit[$today]['optimized'] = "false"; endif;
		
		if ( wp_count_posts( 'testimonials' )->publish > 0 ) : $siteAudit[$today]['testimonials'] = wp_count_posts( 'testimonials' )->publish; else: $siteAudit[$today]['testimonials'] = "false"; endif;
		
		if ( wp_count_posts( 'galleries' )->publish > 0 ) : $siteAudit[$today]['galleries'] = wp_count_posts( 'galleries' )->publish; else: $siteAudit[$today]['galleries'] = "false"; endif;
		
		$coupon = $whyChoose = $logoSlider = $tip = $hvacMaint = $homeTeasers = $emergency = $bbb = $financing = $symptomChecker = $faq = $menuTestimonials = $menuFinance = $homeCallToAction = $serviceMap = "false";

		$args = array ('posts_per_page'=>-1, 'post_type'=>array('page', 'post', 'optimized'));
		$check_posts = new WP_Query($args);		
		if( $check_posts->have_posts() ) : while ($check_posts->have_posts() ) : $check_posts->the_post();	

			$checkContent = get_the_content();	
			if ( rtrim(get_the_permalink(),'/') == get_site_url() && strpos(substr(trim($checkContent), -7), "</h") !== false ) $homeCallToAction = "true";
			if ( strpos(get_the_permalink(), "contact") !== false && ( strpos($checkContent, "map") !== false || strpos($checkContent, "Map") !== false )) $serviceMap = "true";

			$checkContent = $checkContent.get_post_meta( get_the_ID(), 'page-bottom_text' )[0];			
			if ( strpos($checkContent, "coupon") !== false ) $coupon = "true";	
			if ( strpos($checkContent, "[why-choose") !== false ) $whyChoose = "true";	
			if ( strpos($checkContent, "[get-logo-slider") !== false ) $logoSlider = "true";			
			if ( strpos($checkContent, "[hvac-tip-of-the-month") !== false ) $tip = "true";	
			if ( strpos($checkContent, "[hvac-maintenance-tips") !== false ) $hvacMaint = "true";	
			if ( strpos($checkContent, "Home Page Teasers") !== false ) $homeTeasers = "true";					
			if ( strpos($checkContent, "[get-emergency-service") !== false ) $emergency = "true";	
			if ( strpos($checkContent, "[get-financing") !== false || strpos($checkContent, "[get-wells-fargo") !== 'false' ) $financing = "true";	
		endwhile; endif; wp_reset_postdata();

		$check_menus = wp_get_nav_menu_items('main-menu');
		foreach ($check_menus as $menu_item) :
			if (empty($menu_item->menu_item_parent)) :
				if ( strpos($menu_item->title, "FAQ") !== false ) $faq = "true";
				if ( strpos($menu_item->title, "Testimonials") !== false ) $menuTestimonials = "true";
				if ( strpos($menu_item->title, "Financing") !== false ) $menuFinance = "true";					
			endif;
		endforeach;
		
		$args = array ('posts_per_page'=>-1, 'post_type'=>'elements', 's' => 'Widgets');
		$check_posts = new WP_Query($args);	
		if( $check_posts->have_posts() ) : while ($check_posts->have_posts() ) : $check_posts->the_post();	
			$checkContent = strtolower(get_the_content());	
			if ( strpos( $checkContent, "emergency service") !== false || strpos( $checkContent, "emergency-service") !== false ) $emergency = "true";	
			if ( strpos( $checkContent, "bbb") !== false ) $bbb = "true";
			if ( strpos( $checkContent, "[get-financing") !== false || strpos($sidebar_contents, "[get-wells-fargo") !== 'false' ) $financing = "true";	
			if ( strpos( $checkContent, "symptom checker") !== false || strpos( $checkContent, "symptom-checker") !== false ) $symptomChecker = "true";
		endwhile; endif; wp_reset_postdata();

		$siteAudit[$today]['coupon'] = $coupon;	
		$siteAudit[$today]['why-choose'] = $whyChoose;	
		$siteAudit[$today]['logo-slider'] = $logoSlider;	
		$siteAudit[$today]['tip-of-the-month'] = $tip;	
		$siteAudit[$today]['maintenance-tips'] = $hvacMaint;	
		$siteAudit[$today]['homepage-teasers'] = $homeTeasers;	
		$siteAudit[$today]['emergency-service'] = $emergency;	
		$siteAudit[$today]['bbb-link'] = $bbb;	
		$siteAudit[$today]['financing-link'] = $financing;	
		$siteAudit[$today]['symptom-checker'] = $symptomChecker;	
		$siteAudit[$today]['faq'] = $faq;	
		$siteAudit[$today]['menu-testimonials'] = $menuTestimonials;	
		$siteAudit[$today]['menu-finance'] = $menuFinance;	
		$siteAudit[$today]['home-call-to-action'] = $homeCallToAction;
		$siteAudit[$today]['service-map'] = $serviceMap;						

		updateOption('bp_site_audit_details', $siteAudit, false);
	endif;
	
	$siteAuditPage = '<div class="wrap">';
	$siteAuditPage .= '<h1>Site Audit</h1>';
	
	$siteAuditPage .= '<form method="post">';
	
	$siteAuditPage .= '[section][layout class="inputs"][col]';
	
		$siteAuditPage .= '<h1>Lighthouse</h1>';
		
		$siteAuditPage .= '<h3>Mobile</h3>';		
		$siteAuditPage .= '<div class="form-input"><label for="lighthouse-mobile-score">Performance Score:</label><input id="lighthouse-mobile-score" type="text" name="lighthouse-mobile-score" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="lighthouse-mobile-fcp">First Contentful Paint:</label><input id="lighthouse-mobile-fcp" type="text" name="lighthouse-mobile-fcp" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="lighthouse-mobile-si">Speed Index:</label><input id="lighthouse-mobile-si" type="text" name="lighthouse-mobile-si" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="lighthouse-mobile-lcp">Largest Contentful Paint:</label><input id="lighthouse-mobile-lcp" type="text" name="lighthouse-mobile-lcp" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="lighthouse-mobile-tti">Time To Interactive:</label><input id="lighthouse-mobile-tti" type="text" name="lighthouse-mobile-tti" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="lighthouse-mobile-tbt">Total Blocking Time:</label><input id="lighthouse-mobile-tbt" type="text" name="lighthouse-mobile-tbt" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="lighthouse-mobile-cls">Cumulative Layout Shift:</label><input id="lighthouse-mobile-cls" type="text" name="lighthouse-mobile-cls" value=""></div>';
		
		$siteAuditPage .= '<h3>Desktop</h3>';			
		$siteAuditPage .= '<div class="form-input"><label for="lighthouse-desktop-score">Performance Score:</label><input id="lighthouse-desktop-score" type="text" name="lighthouse-desktop-score" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="lighthouse-desktop-fcp">First Contentful Paint:</label><input id="lighthouse-desktop-fcp" type="text" name="lighthouse-desktop-fcp" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="lighthouse-desktop-si">Speed Index:</label><input id="lighthouse-desktop-si" type="text" name="lighthouse-desktop-si" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="lighthouse-desktop-lcp">Largest Contentful Paint:</label><input id="lighthouse-desktop-lcp" type="text" name="lighthouse-desktop-lcp" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="lighthouse-desktop-tti">Time To Interactive:</label><input id="lighthouse-desktop-tti" type="text" name="lighthouse-desktop-tti" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="lighthouse-desktop-tbt">Total Blocking Time:</label><input id="lighthouse-desktop-tbt" type="text" name="lighthouse-desktop-tbt" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="lighthouse-desktop-cls">Cumulative Layout Shift:</label><input id="lighthouse-desktop-cls" type="text" name="lighthouse-desktop-cls" value=""></div>';
		
	$siteAuditPage .= '[/col][col]';
	
		$siteAuditPage .= '<h1>Keyword Rank</h1>';	
		$siteAuditPage .= '<div class="form-input"><label for="keyword-page-1">Page One:</label><input id="keyword-page-1" type="text" name="keyword-page-1" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="keyword-needs-attn">Needs Attention:</label><input id="keyword-needs-attn" type="text" name="keyword-needs-attn" value=""></div>';
		
		$siteAuditPage .= '<br>';

		$siteAuditPage .= '<h1>Backlinks</h1>';	
		$siteAuditPage .= '<div class="form-input"><label for="cite-citations">Citations:</label><input id="cite-citations" type="text" name="cite-citations" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="cite-total-links">Total Links:</label><input id="cite-total-links" type="text" name="cite-total-links" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="cite-local-links">Local Links:</label><input id="cite-local-links" type="text" name="cite-local-links" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="cite-domains">Linking Domains:</label><input id="cite-domains" type="text" name="cite-domains" value=""></div>';
		
		$siteAuditPage .= '<br>';

		$siteAuditPage .= '<h1>Google My Business</h1>';
		$siteAuditPage .= '<div class="form-input"><label for="gmb-overview">Overview:</label><input id="gmb-overview" type="text" name="gmb-overview" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="gmb-calls">Calls:</label><input id="gmb-calls" type="text" name="gmb-calls" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="gmb-msg">Messages:</label><input id="gmb-msg" type="text" name="gmb-msg" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="gmb-clicks">Clicks:</label><input id="gmb-clicks" type="text" name="gmb-clicks" value=""></div>';

	$siteAuditPage .= '[/col][col]';
	
		$siteAuditPage .= '<h1>Notes</h1>';	
		$siteAuditPage .= '<div class="form-input"><textarea id="notes" name="notes" cols="40" rows="10"></textarea></div>';
		$siteAuditPage .= '<input type="hidden" id="submit_check" name="submit_check" value="true">';
		$siteAuditPage .= '<br><input type="submit" value="Submit">';	
		
	$siteAuditPage .= '[/col][/layout][/section]';	

	$siteAuditPage .= '</form>';	
	
	$siteAuditPage .= '[clear height="50px"]';	
	$siteAuditPage .= '<div class="scroll-stats"><h1>Historical<br>Performance</h1>';
	$siteAuditPage .= '[clear height="60px"]';
		
	$siteAuditPage .= '[section][layout class="stats '.$siteType.'"]';	
	$siteAuditPage .= '[col class="empty"][/col]';
	$siteAuditPage .= '[col class="headline lighthouse-mobile-score"]Performance Score[/col]';	
	$siteAuditPage .= '[col class="headline lighthouse-mobile-fcp"]First Contentful Paint[/col]';	
	$siteAuditPage .= '[col class="headline lighthouse-mobile-si"]Speed Index[/col]';	
	$siteAuditPage .= '[col class="headline lighthouse-mobile-lcp"]Largest Contentful Paint[/col]';	
	$siteAuditPage .= '[col class="headline lighthouse-mobile-tti"]Time To Interactive[/col]';	
	$siteAuditPage .= '[col class="headline lighthouse-mobile-tbt"]Total Blocking Time[/col]';	
	$siteAuditPage .= '[col class="headline lighthouse-mobile-cls"]Cumulative Layout Shift[/col]';
	$siteAuditPage .= '[col class="headline lighthouse-desktop-score"]Performance Score[/col]';	
	$siteAuditPage .= '[col class="headline lighthouse-desktop-fcp"]First Contentful Paint[/col]';	
	$siteAuditPage .= '[col class="headline lighthouse-desktop-si"]Speed Index[/col]';	
	$siteAuditPage .= '[col class="headline lighthouse-desktop-lcp"]Largest Contentful Paint[/col]';	
	$siteAuditPage .= '[col class="headline lighthouse-desktop-tti"]Time To Interactive[/col]';	
	$siteAuditPage .= '[col class="headline lighthouse-desktop-tbt"]Total Blocking Time[/col]';	
	$siteAuditPage .= '[col class="headline lighthouse-desktop-cls"]Cumulative Layout Shift[/col]';	
	$siteAuditPage .= '[col class="headline keyword-page-1"]Page One[/col]';	
	$siteAuditPage .= '[col class="headline keyword-needs-attn"]Needs Attention[/col]';	
	$siteAuditPage .= '[col class="headline cite-citations"]Citations[/col]';	
	$siteAuditPage .= '[col class="headline cite-total-links"]Total Links[/col]';	
	$siteAuditPage .= '[col class="headline cite-local-links"]Local Links[/col]';	
	$siteAuditPage .= '[col class="headline cite-domains"]Linking Domains[/col]';
	$siteAuditPage .= '[col class="headline gmb-overview"]Overview[/col]';	
	$siteAuditPage .= '[col class="headline gmb-calls"]Calls[/col]';	
	$siteAuditPage .= '[col class="headline gmb-msg"]Messages[/col]';	
	$siteAuditPage .= '[col class="headline gmb-clicks"]Clicks[/col]';		
	$siteAuditPage .= '[col class="headline google-rating"]Rating[/col]';		
	$siteAuditPage .= '[col class="headline google-reviews"]Number[/col]';	
	$siteAuditPage .= '[col class="headline load_time_mobile"]Mobile[/col]';		
	$siteAuditPage .= '[col class="headline load_time_desktop"]Desktop[/col]';			
	$siteAuditPage .= '[col class="headline optimized"]Optimized[/col]';
	$siteAuditPage .= '[col class="headline testimonials"]Testimonials[/col]';		
	$siteAuditPage .= '[col class="headline menu-testimonials"]Testimonials Button[/col]';		
	$siteAuditPage .= '[col class="headline testimonials-pct"]Testimonials View %[/col]';	
	$siteAuditPage .= '[col class="headline coupon"]Coupon[/col]';				
	$siteAuditPage .= '[col class="headline coupon-pct"]Coupon View %[/col]';
	$siteAuditPage .= '[col class="headline blog"]Blog[/col]';	
	$siteAuditPage .= '[col class="headline galleries"]Galleries[/col]';	
	$siteAuditPage .= '[col class="headline home-call-to-action"]Home Call To Action[/col]';			
	$siteAuditPage .= '[col class="headline homepage-teasers"]Home Page Teasers[/col]';	
	$siteAuditPage .= '[col class="headline logo-slider"]Logo Slider[/col]';			
	$siteAuditPage .= '[col class="headline service-map"]Service Map[/col]';		
	$siteAuditPage .= '[col class="headline bbb-link"]BBB[/col]';	
	$siteAuditPage .= '[col class="headline financing-link"]Financing Ad[/col]';
	$siteAuditPage .= '[col class="headline menu-finance"]Financing Button[/col]';		
	$siteAuditPage .= '[col class="headline finance-pct"]Financing View %[/col]';
	$siteAuditPage .= '[col class="headline why-choose"]Why Choose Us[/col]';		
	$siteAuditPage .= '[col class="headline emergency-service"]24/7 Service[/col]';	
	$siteAuditPage .= '[col class="headline symptom-checker"]Symptom Checker[/col]';			
	$siteAuditPage .= '[col class="headline faq"]FAQ[/col]';				
	$siteAuditPage .= '[col class="headline tip-of-the-month"]Tip Of The Month[/col]';			
	$siteAuditPage .= '[col class="headline maintenance-tips"]Maintenance Tips[/col]';
	
	$siteAuditPage .= '[col class="subhead date"]Date[/col]';	
	$siteAuditPage .= '[col class="subhead lighthouse mobile"]Mobile[/col]';	
	$siteAuditPage .= '[col class="subhead lighthouse desktop"]Desktop[/col]';	
	$siteAuditPage .= '[col class="subhead keywords"]Keywords[/col]';	
	$siteAuditPage .= '[col class="subhead citations"]Citations[/col]';	
	$siteAuditPage .= '[col class="subhead gmb"]Google My Business[/col]';		
	$siteAuditPage .= '[col class="subhead revs"]Reviews[/col]';	
	$siteAuditPage .= '[col class="subhead site-speed"]Site Speed[/col]';	
	$siteAuditPage .= '[col class="subhead site-elements"]Site Elements[/col]';
	$siteAuditPage .= '[col class="subhead site-hvac"]HVAC Elements[/col]';	
	
	$siteAudit = get_option('bp_site_audit_details');
	
	if (is_array($siteAudit) )$siteAudit = array_reverse($siteAudit);
	foreach ( $siteAudit as $date=>$auditDetails ) :
		$siteAuditPage .= '[col class="when"]'.date("M j, Y", strtotime($date)).'[/col]';
		foreach ( $criteria as $auditDetail ) :			
			$siteAuditPage .= '[col class="stat '.$auditDetail.'"]';
			
			if ( $auditDetails[$auditDetail] == "true" ) :
				$siteAuditPage .= '●';
			elseif ( $auditDetails[$auditDetail] == "false" ) :
				$siteAuditPage .= '◦';
			elseif ( !is_numeric($auditDetails[$auditDetail]) ) :
				$siteAuditPage .= $auditDetails[$auditDetail];
			else:
				$siteAuditPage .= $auditDetails[$auditDetail];
			endif;
			$siteAuditPage .= '[/col]';
		endforeach;	
		$siteAuditPage .= '[col class="notes"]'.$auditDetails['notes'].'[/col]';
	endforeach;
	
	$siteAuditPage .= '[/layout][/section]</div></div> <!--site-audit-wrap-->';
	echo do_shortcode($siteAuditPage);
	
	if ( is_array($siteAudit)) updateOption( 'site_updated', strtotime(array_key_first($siteAudit)) ); 
	exit();
}  

// Set up brand new site
function battleplan_clear_all() {
	$products = get_posts( array('post_type'=>'products', 'numberposts'=>-1) );
	foreach ($products as $post) wp_delete_post( $post->ID, true );

	$args = array( 'post_status' => 'inherit', 'posts_per_page' => -1, 'post_type' => 'attachment', 'post_mime_type' => 'image', );
	$args['tax_query'] = array( array( 'taxonomy' => 'image-categories', 'terms' => 'products', 'field' => 'slug', ),);
	$getImg = new WP_Query( $args );

	if ( $getImg->have_posts() ) : while ( $getImg->have_posts() ) :
		$getImg->the_post();		
		wp_delete_attachment( get_the_ID(), true );
	endwhile; endif;

	wp_reset_postdata();	
	battleplan_clear_hvac();
}

function battleplan_clear_hvac() {
	$optimized = get_posts( array('post_type'=>'optimized', 'numberposts'=>-1) );
	$testimonials = get_posts( array('post_type'=>'testimonials', 'numberposts'=>-1) );
	$galleries = get_posts( array('post_type'=>'galleries', 'numberposts'=>-1) );
	$posts = get_posts( array('post_type'=>'post', 'numberposts'=>-1) );
	foreach ($optimized as $post) wp_delete_post( $post->ID, true );
	foreach ($testimonials as $post) wp_delete_post( $post->ID, true );
	foreach ($galleries as $post) wp_delete_post( $post->ID, true );
	foreach ($posts as $post) wp_delete_post( $post->ID, true );

	$args = array( 'post_status' => 'inherit', 'posts_per_page' => -1, 'post_type' => 'attachment', 'post_mime_type' => 'image', );
	$terms = array ('testimonials', 'photos', 'graphics', 'logos');
	$args['tax_query'] = array( array( 'taxonomy' => 'image-categories', 'terms' => $terms, 'field' => 'slug', ),);
	$getImg = new WP_Query( $args );

	if ( $getImg->have_posts() ) : while ( $getImg->have_posts() ) :
		$getImg->the_post();		
		if ( basename( get_attached_file( get_the_ID() )) != 'logo.png' && basename( get_attached_file( get_the_ID() )) != 'site-icon.png' && basename( get_attached_file( get_the_ID() )) != 'favicon.png' ) wp_delete_attachment( get_the_ID(), true );
	endwhile; endif;

	wp_reset_postdata();
	
	updateOption('bp_chrons_pages', 0);
	updateOption('bp_launch_date', date('Y-m-d'));
	
	delete_option('bp_google_reviews');		
	delete_option('bp_site_hits_ga4');	
	delete_option('bp_site_hits_ua_1');	
	delete_option('bp_site_hits_ua_2');		
	delete_option('bp_site_hits_ua_3');		
	delete_option('bp_site_hits_ua_4');		
	delete_option('bp_site_hits_ua_5');	
	
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
?>