<?php
echo "Step 1: config\n";
require_once __DIR__ . '/../config/config.php';
echo "  OK\n";

echo "Step 2: db\n";
require_once __DIR__ . '/../config/db.php';
echo "  OK\n";

echo "Step 3: Repo\n";
require_once __DIR__ . '/../includes/repositories/Repo.php';
echo "  OK\n";

echo "Step 4: Order\n";
require_once __DIR__ . '/../includes/models/Order.php';
echo "  OK\n";

echo "Step 5: User\n";
require_once __DIR__ . '/../includes/models/User.php';
echo "  OK\n";

echo "Step 6: CreditService\n";
require_once __DIR__ . '/../includes/services/CreditService.php';
echo "  OK\n";

echo "Step 7: PricingService\n";
require_once __DIR__ . '/../includes/services/PricingService.php';
echo "  OK\n";

echo "Step 8: HttpClient\n";
require_once __DIR__ . '/../includes/HttpClient.php';
echo "  OK\n";

echo "Step 9: DB\n";
$pdo = DB::getInstance()->getPDO();
echo "  OK\n";

echo "Step 10: API call\n";
$http = new HttpClient();
try {
    $result = $http->getJson('https://donatello.to/api/v1/donates?page=0&size=5', [
        'X-Token: ' . DONATELLO_TOKEN
    ], 15);
    echo "  OK HTTP " . $result['http_code'] . "\n";
} catch (\Throwable $e) {
    echo "  ERROR: " . $e->getMessage() . "\n";
}

echo "DONE\n";
