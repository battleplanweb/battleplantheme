<?php

return array (
  	'version' => '6.2.1',
  	'title' => 'Events - Main View',
  	'type' => 'tribe_events',
  	'id' => 'bp_tribe_events-main',
  	'updated' => 1684240270, 
	'columns'=>array(
		'featured-image'=>array(
			'type'=>'column-featured_image',
			'label'=>'',
			'width'=>'80',
			'width_unit'=>'px',
			'featured_image_display'=>'image',
			'image_size'=>'icon',
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
		'start-date'=>array(
			'type'=>'start-date',
			'label'=>'Start Date',
			'width'=>'',
			'width_unit'=>'%',
			'date_format'=>'wp_default',
			'edit'=>'off',
			'sort'=>'on',
			'filter'=>'on',
			'filter_label'=>'',
			'filter_format'=>'monthly',
			'name'=>'start-date',
			'label_type'=>'',
			'search'=>'on'
		),		
		'end-date'=>array(
			'type'=>'end-date',
			'label'=>'End Date',
			'width'=>'',
			'width_unit'=>'%',
			'date_format'=>'wp_default',
			'edit'=>'off',
			'sort'=>'on',
			'filter'=>'on',
			'filter_label'=>'',
			'filter_format'=>'monthly',
			'name'=>'end-date',
			'label_type'=>'',
			'search'=>'on'
		),				
		'recurring'=>array(
			'type'=>'recurring',
			'label'=>'Recurring',
			'width'=>'',
			'width_unit'=>'%',
			'edit'=>'off',
			'sort'=>'on',
			'filter'=>'on',
			'filter_label'=>'',
			'name'=>'end-date',
			'label_type'=>'',
			'search'=>'on'
		),					
		'events-cats'=>array(
			'type'=>'events-cats',
			'label'=>'Categories',
			'width'=>'',
			'width_unit'=>'%',
			'edit'=>'off',
			'sort'=>'on',
			'filter'=>'on',
			'filter_label'=>'',
			'name'=>'events-cats',
			'label_type'=>'',
			'search'=>'on'
		),							
		'tags'=>array(
			'type'=>'tags',
			'label'=>'Tags',
			'width'=>'',
			'width_unit'=>'%',
			'edit'=>'off',
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
);