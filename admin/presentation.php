<?php
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/presentation.php';
require_admin();

$ed = edition_admin();
$chaines = get('chaine') !== ''
    ? q('SELECT * FROM chaines WHERE id = ? AND edition_id = ?', [(int) get('chaine'), $ed['id']])->fetchAll()
    : q('SELECT * FROM chaines WHERE edition_id = ? ORDER BY numero', [$ed['id']])->fetchAll();

afficher_presentation($ed, $chaines,
    fn(string $type, ?int $id) => $type === 'base' ? '../img.php?base=' . $ed['id'] : '../img.php?m=' . $id,
    ['head' => pwa_head(), 'retour' => 'index.php', 'titre' => 'Présentation']);
