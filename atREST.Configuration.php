<?php

namespace atREST;

$atRESTConfguration = array(
	'Core' => array(
		'AllowCORS'		=> false,
		'MemoryLimit'	=> '32M',
		'Mode'			=> 'Development',
		'Modules'		=> array('Autoloader', 'ErrorLogger', 'WebUI'),
		'Log' 			=> array(
			'FileNameFormat'	=> 'Y-m-d',
			'MaxLevel'			=> Core::Warning
		),
	),

	'Authorization' => array(
		'Salt'			=> 'atrest1234',
		'Key'			=> 'ALfQHpviCfy5HWDY',
		'Lifetime'		=> 36000,
		'RefreshWindow'	=> 10,
	),

	'Storage' => array(
		'Default' => 'atREST',
	),

	'Email' => array(
		'Server' => 'smtp.gmail.com',
		'Port' => 587,
		'Username' => '',
		'Password' => '',
	),
);

$atRESTConfguration['Production'] = array(
	'Storage' => array(
		'atREST' => array(
			'Connector' => 'SQL',
			'Type' => 'MariaDB',
			'Username' => 'atrest',
			'Password' => 'atrest',
			'Host' => 'localhost',
			'Database' => 'atrest',
			'TablePrefix' => 'atrest_',
			'Port' => 3306,
		)
	)
);

$atRESTConfguration['Development'] = array(
	'Core' => array(
		'Log' => array(
			'MaxLevel' => Core::Debug
		),
	),

	'Storage' => array(
		'atREST' => array(
			'Connector' => 'SQL',
			'Type' => 'SQLite',
			'File' => 'atrest',
			'TablePrefix' => '',
			'Port' => 3306,
		)
	)
);
