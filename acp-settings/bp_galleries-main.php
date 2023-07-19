<?php

return array (
  	'version' => '6.2.1',
  	'title' => 'Galleries - Main View',
  	'type' => 'galleries',
  	'id' => 'bp_galleries-main',
  	'updated' => 1684240270, 
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
			'date_format'=>'F j, Y g:i a',
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
	)		
);