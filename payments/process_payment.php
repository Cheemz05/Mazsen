<?php
declare(strict_types=1);

http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'status' => 'error',
    'message' => 'Manual reference-number payments are disabled. Start checkout from the customer order page.',
]);
