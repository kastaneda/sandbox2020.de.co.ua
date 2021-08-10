<?php

function render_form($form)
{
    echo '<form method="post" action="">';
    echo '<h1>' . ($form['title'] ?? 'Form') . '</h1>';

    foreach ($form['questions'] as $row) {
        echo '<p><label>' . $row['label'] . '<br>';
        
        if (($row['type'] ?? 'string') == 'select') {
            echo '<select name="q[]">';
            echo '<option value=""></option>';
            foreach ($row['options'] as $option) {
                echo '<option value="' . $option . '">' . $option . '</option>';
            }
            echo '</select>';
        } else {
            echo '<input name="q[]" type="text">';
        }
        
        echo '</label></p>';
    }

    echo '<p><input type="submit"></p></form>';
}

if (empty($_POST)) {
    render_form(require 'config.php');
} else {
    $data = [
        date('Y-m-d H:i:s'),
        $_SERVER['REMOTE_ADDR'],
    ];
    $data = array_merge($data, $_POST['q']);
    $line = '"' . join('","', $data) . '"' . PHP_EOL;
    file_put_contents('data.csv', $line, FILE_APPEND | LOCK_EX);
    echo '<h1>Thanks!</h1>';
}


