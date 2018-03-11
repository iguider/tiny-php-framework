<?php
require_once "bootstrap.php";

echo Request::factory()
        ->execute()
        ->headers("Access-Control-Allow-Origin", "*")
        ->cors()
        ->send_headers(true)
        ->body();
