<?php
function entete(string $titre, string $actif = ''): void
{
    $a = admin();
    $ed = edition_admin();
    $eds = q("SELECT id, nom FROM editions WHERE nom <> 'Anciens participants' ORDER BY id DESC")->fetchAll();
    $menu = ['index' => 'Tableau de bord', 'participants' => 'Participants', 'invitations' => 'Invitations', 'edition' => 'Édition',
             'mails' => 'Mails', 'admins' => 'Administrateurs'];
    ?><!doctype html><html lang="fr"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($titre) ?> – Cadavre Exquis</title><link rel="stylesheet" href="../assets/style.css?v=<?= APP_VERSION ?>"><?= pwa_head() ?>
<body class="admin">
<header class="barre">
  <a class="logo" href="index.php">Cadavre <span>Exquis</span></a>
  <nav><?php foreach ($menu as $k => $lib): ?><a href="<?= $k ?>.php" class="<?= $actif === $k ? 'actif' : '' ?>"><?= $lib ?></a><?php endforeach ?></nav>
  <div class="droite">
    <?php if ($eds): ?>
    <form method="get" action="index.php"><select name="edition" onchange="this.form.submit()">
      <?php foreach ($eds as $e): ?><option value="<?= $e['id'] ?>" <?= $ed && $ed['id'] == $e['id'] ? 'selected' : '' ?>><?= h($e['nom']) ?></option><?php endforeach ?>
    </select></form>
    <?php endif ?>
    <span class="version" title="<?= h(version_texte()) ?>">v<?= APP_VERSION ?></span>
    <span><?= h($a['nom'] ?: $a['login']) ?></span><a href="logout.php">Déconnexion</a>
  </div>
</header>
<main>
<?php foreach (flash() as [$type, $msg]): ?><p class="alerte <?= $type ?>"><?= h($msg) ?></p><?php endforeach;
}

function pied(): void
{
    echo '<footer class="pied-app">Cadavre Exquis ' . h(version_texte())
        . ' · <a href="https://github.com/pschwaller69/CadavreExquis/blob/main/CHANGELOG.md" target="_blank">nouveautés</a></footer></main></body></html>';
}

function badge(string $statut, ?string $lib = null): string
{
    $libs = ['active' => 'en cours', 'en_cours' => 'en cours', 'rendu' => 'rendu', 'attente' => 'en attente',
             'close' => 'terminée', 'passe' => 'passé', 'abandon' => 'abandon', 'affecte' => 'placé',
             'preparation' => 'préparation', 'terminee' => 'terminée', 'retard' => 'en retard'];
    return '<span class="badge ' . h($statut) . '">' . h($lib ?? ($libs[$statut] ?? $statut)) . '</span>';
}

function en_retard(array $m): bool { return $m['statut'] === 'en_cours' && $m['date_limite'] < now(); }
