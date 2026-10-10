<?php
/**
 * SMTP Mail Server Configuration for Travel Buddy
 * 
 * Gmail SMTP Configuration:
 * 1. Requires 2-Step Verification enabled on the Google Account.
 * 2. Generate a 16-character App Password at: https://myaccount.google.com/apppasswords
 */
return [
    'smtp_host'   => 'smtp.gmail.com',
    'smtp_port'   => 465,                             // 465 (SSL) or 587 (TLS)
    'smtp_user'   => 'abdmgalib2001@gmail.com',          // Sender Gmail address
    'smtp_pass'   => 'lyziyyagtwwmsdyd',              // 16-character Google App Password
    'from_email'  => 'abdmgalib2001@gmail.com',          // Sender email
    'from_name'   => 'Travel Buddy'                   // Sender name
];
?>
