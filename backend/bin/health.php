<?php
$body=@file_get_contents('http://127.0.0.1:9501/health');
exit($body&&str_contains($body,'"status":"ok"')?0:1);
