<?php

/**
 * @param mixed $data
 */
function dump($data): void
{
    error_log(print_r($data, true));
}
