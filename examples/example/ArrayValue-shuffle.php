<?php

use GW\Value\Wrap;

$words = Wrap::array(['do', 'or', 'do', 'not', 'there', 'is', 'no', 'try']);

echo $words->shuffle()->implode(' ')->toString();

