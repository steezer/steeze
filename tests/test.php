<?php
$array=[
	'static' => [
		'callback' => [
			'static' => [
				'callback' => [
					'static' => [
						'destination' => [
							'this' => 'Library\Application',
							'parameter' => ['$request' => '<required>']
						]
					],
					'this' => 'Library\Pipeline',
					'parameter' => ['$passable' => '<required>']
				],
				'pipe' => 'App\Home\Middleware\CharsetConvert'
			],
			'this' => 'Library\Pipeline',
			'parameter' => ['$passable' => '<required>']
		],
		'pipe' => 'App\Home\Middleware\Authorize'
	],
	'this' => 'Library\Pipeline',
	'parameter' => ['$passable' => '<required>']
];

var_dump($array);