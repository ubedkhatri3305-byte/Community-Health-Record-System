<?php
// Lightweight deployment health check endpoint
http_response_code(200);
header('Content-Type: text/plain');
echo "OK";
