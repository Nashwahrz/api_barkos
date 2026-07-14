<?php
$data = json_encode(['message' => 'halo', 'history' => []]);
$options = [
    'http' => [
        'header'  => "Content-type: application/json\r\n",
        'method'  => 'POST',
        'content' => $data,
        'ignore_errors' => true,
    ]
];
$context  = stream_context_create($options);
$result = file_get_contents('http://127.0.0.1:8000/api/chatbot', false, $context);
echo "Response:\n$result\n";
