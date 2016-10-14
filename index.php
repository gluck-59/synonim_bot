<?php
$input = 'хуЙ';
if (preg_match('/^хуй$/iu', $input)) {
    echo 'Совпадение!';
}

