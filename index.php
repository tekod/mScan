<?php

/**
 * Launcher for mScan (directory scanner and reporter)
 * 
 * For details look docblock of mScan class, this file is just launcher.
 * 
 * Customize this file for your use-case but keep main class unmodified.
 * If you need to modify main class contact author to implement feature
 * or extend configuration options.
 */

ini_set('display_errors', '1'); // optional
ini_set('log_errors', '1'); // optional

// load main class
include 'mScan.php';

// execute 
$mScan_Application= new mScan(array(
    'PathsToScan'=> array(
        '/var/www/public_html'                          // list of paths to scan
    ),
    'EmailReport'=> array(
        'Enabled'=> true,                               // enable sending emails
        'ToAddress'=> 'my.private.address@gmail.com',   // where to send report
        'FromAddress'=> 'mscan@your.hosting.com',       // email's "From:" field
        'Subject'=> 'mScan report',                     // email's "Subject"
    ),    
));
$mScan_Application->Run();


?>