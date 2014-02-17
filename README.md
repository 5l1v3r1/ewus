ewus
====

eWUS - PHP library implementing eWUS functionality

Usage:
-----

```php
<?php

use Ewus\Ewus;

/*
 * create new client
 * 
 * options:
 * - username
 * - domain
 * - provider_type - LEK or SWD
 * - provider_code - ex. 001234
 */
$c = new Ewus(array('is_production' => FALSE, 'username' => 'TEST1', 'domain' => 15));

// authenticate
$c->authenticate();

// check PESEL
$res = $c->checkPesel('86042510356');
```