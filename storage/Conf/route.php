<?php
return [
	'default' => [
		'auth'=>[
			'/{c}/{a}'=>'home/{c}@{a}',
			'/{c}/{a}/{user|d}'=>'home/{c}@{a}',
		]
	]
];