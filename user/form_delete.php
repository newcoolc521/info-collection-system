<?php
require_once __DIR__ . '/../lib.php';
require_role('user');

$uid = uid();
$form_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$form = get_form_by_id($form_id);

if ($form && $form['username'] === $uid) {
    delete_form($form_id);
}

redirect('index.php');