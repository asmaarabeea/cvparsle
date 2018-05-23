<?php

return [
    'default' => 'mysql',

    'connections' => [

        'mysql' => [
            'driver'    => 'mysql',
            'host'      => 'localhost',
            'database'  => 'jobzella_new_2',
            'username'  => 'root',
            'password'  => 'root',
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => ''
        ],


        # Our secondary database connection
     
        'mysql2' => [
            'driver'    => 'mysql',
            'host'      => '10.0.0.210',
            'port'      => '3306',
            'database'  => "HimmetnaDB",
            'username'  => "jobzella",
            'password'  => "J0BZ3LLA",
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
            'strict'    => false,
            'engine'    => null,
        ],

        # Our Third database connection
     
        'mysql3' => [
            'driver'    => 'mysql',
            'host'      => '10.0.0.210',
            'port'      => '3306',
            'database'  => "HimmetnaDB_As",
            'username'  => "jobzella",
            'password'  => "J0BZ3LLA",
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
            'strict'    => false,
            'engine'    => null,
        ],
        

    ],

    'migrations' => 'migrations',
];