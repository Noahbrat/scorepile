<?php
declare(strict_types=1);

if (version_compare(PHP_VERSION, '8.1.0', '<')) {
    trigger_error('Your PHP version must be equal or higher than 8.1.0 to use CakePHP. You are using ' . PHP_VERSION . '.', E_USER_ERROR);
}

if (!extension_loaded('mbstring')) {
    trigger_error('You must enable the mbstring extension to use CakePHP.', E_USER_ERROR);
}

if (!extension_loaded('intl')) {
    trigger_error('You must enable the intl extension to use CakePHP.', E_USER_ERROR);
}
