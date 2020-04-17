<?php

return [
    'bootstrap' => \GranularAccess\Bootstrap::class,
    
    'entities' => [
		'granular_access' => [
			'type' => 'object',
			'subtype' => 'granular_access',
			'class' => ElggObject::class,
			'searchable' => false,
		],
    ],
    
    'upgrades' => [
		\GranularAccess\Upgrades\SetAclSubtype::class,
	],
];