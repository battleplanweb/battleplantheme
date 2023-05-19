<?php

return array (
  	'version' => '6.2.1',
  	'title' => 'Woocommerce Order - Main View',
  	'type' => 'shop_order',
  	'id' => 'bp_shop_order-main',
  	'updated' => 1684240270, 
	'columns'=>array(
		'order_number'=>array(
			'type'=>'order_number',
			'label'=>'Order',
			'width'=>'300',
			'width_unit'=>'px',
			'edit'=>'on',
			'sort'=>'on',
			'name'=>'order_number',
			'label_type'=>'',
			'search'=>'on'
		),
		'date-published'=>array(
			'type'=>'column-date_published',
			'label'=>'Date',
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
		'shipping_address'=>array(
			'type'=>'shipping_address',
			'label'=>'Ship To',
			'width'=>'200',
			'width_unit'=>'px',
			'edit'=>'on',
			'sort'=>'on',
			'name'=>'shipping_address',
			'label_type'=>'',
			'search'=>'on'
		),
		'order_total'=>array(
			'type'=>'order_total',
			'label'=>'Total',
			'width'=>'200',
			'width_unit'=>'px',
			'edit'=>'on',
			'sort'=>'on',
			'name'=>'order_total',
			'label_type'=>'',
			'search'=>'on'
		),
		'order_status'=>array(
			'type'=>'order_status',
			'label'=>'Status',
			'width'=>'200',
			'width_unit'=>'px',
			'edit'=>'on',
			'sort'=>'on',
			'name'=>'title',
			'label_type'=>'',
			'search'=>'on'
		),
	),
);