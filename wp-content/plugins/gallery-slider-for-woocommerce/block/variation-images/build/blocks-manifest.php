<?php
// This file is generated. Do not modify it manually.
return array(
	'build' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'shapedplugin/variation-images',
		'version' => '0.1.0',
		'title' => 'Variation Images',
		'category' => 'WooCommerce',
		'description' => 'A block to demonstrate extending the Product Editor',
		'attributes' => array(
			'variation_images' => array(
				'type' => 'string'
			)
		),
		'supports' => array(
			'html' => false,
			'inserter' => false
		),
		'textdomain' => 'gallery-slider-for-woocommerce',
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'style' => 'file:./style-index.css'
	)
);
