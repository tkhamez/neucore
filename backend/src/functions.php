<?php

function dump(mixed $data): void
{
    error_log(print_r($data, true));
}
