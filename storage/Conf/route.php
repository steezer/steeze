<?php
return [
	'default' => [
		'/'=> 'auth&convert>home/index@index',
		'auth&convert' => [
			'/{c}/{a}'=>'home/{c}@{a}',
			'/{c}/{a}/{user|d}'=>'home/{c}@{a}',
		]
	]
];