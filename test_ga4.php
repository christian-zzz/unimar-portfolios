<?php
require "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Google\Analytics\Data\V1beta\Client\BetaAnalyticsDataClient;

$client = new BetaAnalyticsDataClient([
    "credentials" => json_decode(config("analytics.credentials_json"), true) ?: config("analytics.credentials")
]);
$response = $client->getMetadata("properties/" . config("analytics.property_id") . "/metadata");

foreach ($response->getDimensions() as $dim) {
    echo $dim->getApiName() . "\n";
}
