<?php
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/layout.php';
require_admin();

$nouvelle = get('nouvelle') !== '';
$ed = $nouvelle ? null : edition_admin();
if (!$ed) $nouvelle = true;
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    try {
        if (post('action') === 'supprimer' && $ed) {
            if (post('confirmation') !== $ed['nom']) throw new RuntimeException('Suppression annulée : le nom saisi ne correspond pas.');
            $ids = q('SELECT id FROM chaines WHERE edition_id = ?', [$ed['id']])->fetchAll(PDO::FETCH_COLUMN);
            if ($ids) q('DELETE FROM maillons WHERE chaine_id IN (' . implode(',', array_map('intval', $ids)) . ')');
            q('DELETE FROM chaines WHERE edition_id = ?', [$ed['id']]);
            q('DELETE FROM invitations WHERE edition_id = ?', [$ed['id']]);
            q('DELETE FROM participants WHERE edition_id = ?', [$ed['id']]);
            q('DELETE FROM editions WHERE id = ?', [$ed['id']]);
            supprimer_dossier(PHOTO_DIR . '/' . (int) $ed['id']);
            unset($_SESSION['edition_id']);
            flash('Édition « ' . $ed['nom'] . ' » supprimée avec ses participants et ses photos.');
            redirect('index.php');
        }
        if (post('action') === 'supprimer_chaine' && $ed) {
            $ch = chaine((int) post('chaine'));
            if (!$ch || (int) $ch['edition_id'] !== (int) $ed['id']) throw new RuntimeException('Chaîne introuvable.');
            supprimer_chaine($ch, true);
            flash('Chaîne ' . $ch['numero'] . ' supprimée avec ses participants et ses photos.');
            redirect('edition.php');
        }
        if (post('action') === 'ajout_chaine' && $ed) {
            $max = (int) qv('SELECT COALESCE(MAX(numero + 0),0) FROM chaines WHERE edition_id = ?', [$ed['id']]);
            insert('chaines', ['edition_id' => $ed['id'], 'numero' => sprintf('%02d', $max + 1), 'statut' => 'attente', 'attente_depuis' => now()]);
            if ($ed['statut'] === 'en_cours') demarrer_edition($ed);
            flash('Chaîne ' . sprintf('%02d', $max + 1) . ' ajoutée.');
            redirect('edition.php');
        }
        $data = [
            'nom' => post('nom') ?: 'Cadavre Exquis ' . date('Y'),
            'annee' => (int) post('annee') ?: (int) date('Y'),
            'date_fin_envois' => post('date_fin_envois') ?: null,
            'max_maillons' => max(0, (int) post('max_maillons')),
            'inscriptions_ouvertes' => post('inscriptions_ouvertes') ? 1 : 0,
            'texte_accueil' => post('texte_accueil') ?: null,
        ];
        if ($nouvelle) {
            $data += ['statut' => 'preparation', 'date_creation' => now()];
            $id = insert('editions', $data);
            for ($i = 1; $i <= max(1, min(30, (int) post('nb_chaines'))); $i++) {
                insert('chaines', ['edition_id' => $id, 'numero' => sprintf('%02d', $i), 'statut' => 'active']);
            }
            $_SESSION['edition_id'] = $id;
        } else {
            $id = (int) $ed['id'];
            update('editions', $id, $data);
        }
        if (!empty($_FILES['photo_base']['name'])) {
            [$orig, $red] = enregistrer_image($_FILES['photo_base'], $id . '/depart', '00 Photo de base');
            update('editions', $id, ['photo_base' => $red]);
        }
        flash($nouvelle ? 'Édition créée.' : 'Édition enregistrée.');
        redirect($nouvelle ? 'index.php' : 'edition.php');
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$v = $ed ?? ['nom' => 'Cadavre Exquis ' . date('Y'), 'annee' => date('Y'), 'date_fin_envois' => '', 'max_maillons' => 0,
             'inscriptions_ouvertes' => 1, 'texte_accueil' => '', 'photo_base' => null];
$chaines = $ed ? q('SELECT * FROM chaines WHERE edition_id = ? ORDER BY numero', [$ed['id']])->fetchAll() : [];

entete($nouvelle ? 'Nouvelle édition' : 'Édition', 'edition');
?>
<h1><?= $nouvelle ? 'Nouvelle édition' : 'Paramètres de l\'édition' ?></h1>
<?php if (!$nouvelle): ?><div class="actions"><a class="btn sec" href="edition.php?nouvelle=1">+ Nouvelle édition</a></div><?php endif ?>
<?php if ($err): ?><p class="alerte ko"><?= h($err) ?></p><?php endif ?>

<div class="deux-col">
<form method="post" enctype="multipart/form-data" class="form panneau"><?= csrf_field() ?>
  <div class="ligne">
    <label>Nom<input name="nom" value="<?= h($v['nom']) ?>" required></label>
    <label>Année<input name="annee" type="number" value="<?= h($v['annee']) ?>"></label>
  </div>
  <?php if ($nouvelle): ?><label>Nombre de chaînes<input name="nb_chaines" type="number" min="1" max="30" value="6"></label><?php endif ?>
  <label id="photo">Photo de départ (commune à toutes les chaînes)<?= $v['photo_base'] ? ' – choisir un fichier pour la remplacer' : '' ?>
    <?php if ($v['photo_base']): ?><img class="base-photo" src="../img.php?base=<?= $ed['id'] ?>"><?php endif ?>
    <input type="file" name="photo_base" accept="image/*"></label>
  <div class="ligne">
    <label>Dernier envoi le (vide = pas de limite)<input name="date_fin_envois" type="date" value="<?= h(substr((string) $v['date_fin_envois'], 0, 10)) ?>"></label>
    <label>Photos max par chaîne (0 = illimité)<input name="max_maillons" type="number" min="0" value="<?= (int) $v['max_maillons'] ?>"></label>
  </div>
  <label class="case"><input type="checkbox" name="inscriptions_ouvertes" value="1" <?= $v['inscriptions_ouvertes'] ? 'checked' : '' ?>> Inscriptions ouvertes sur le formulaire public</label>
  <label>Texte d'accueil du formulaire (règles du jeu ; vide = texte par défaut)<textarea name="texte_accueil" style="min-height:140px;font-family:inherit"><?= h($v['texte_accueil']) ?></textarea></label>
  <button class="btn"><?= $nouvelle ? 'Créer l\'édition' : 'Enregistrer' ?></button>
</form>

<?php if (!$nouvelle): ?>
<div class="panneau">
  <h2 style="margin-top:0">Chaînes (<?= count($chaines) ?>)</h2>
  <table class="liste" style="margin-bottom:12px">
  <?php foreach ($chaines as $c): ?>
    <tr><td><a href="chaine.php?id=<?= $c['id'] ?>">Chaîne <?= h($c['numero']) ?></a></td><td><?= badge($c['statut']) ?></td>
      <td style="text-align:right"><?php if ($c['statut'] === 'close'): ?>
        <form method="post" onsubmit="return confirm('Supprimer la chaîne <?= h($c['numero']) ?>, ses participants et ses photos ?')"><?= csrf_field() ?>
          <input type="hidden" name="action" value="supprimer_chaine"><input type="hidden" name="chaine" value="<?= $c['id'] ?>"><button class="btn petit danger">✕ Supprimer</button></form>
      <?php endif ?></td></tr>
  <?php endforeach ?>
  </table>
  <p class="petit">Seules les chaînes terminées peuvent être supprimées (bouton « Terminer la chaîne » sur sa page).</p>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="ajout_chaine"><button class="btn sec">+ Ajouter une chaîne</button></form>
  <h2>Supprimer cette édition</h2>
  <form method="post" class="form" onsubmit="return confirm('Supprimer définitivement cette édition, ses participants et ses photos ?')"><?= csrf_field() ?>
    <input type="hidden" name="action" value="supprimer">
    <p class="petit" style="margin:0">Pour effacer un essai. Tapez le nom exact de l'édition (« <?= h($ed['nom']) ?> ») pour confirmer. Aucun mail n'est envoyé.</p>
    <input name="confirmation" placeholder="<?= h($ed['nom']) ?>" autocomplete="off">
    <button class="btn danger">Supprimer l'édition</button>
  </form>
  <h2>Fonctionnement automatique</h2>
  <ul class="petit">
    <li>À l'inscription : mail de bienvenue, puis placement immédiat si une chaîne attend quelqu'un.</li>
    <li>Au dépôt d'une photo : mail de remerciement et envoi au suivant de la liste d'attente (en évitant le club des 2 derniers maillons).</li>
    <li>Délai : <?= (int) cfg('delai_jours') ?> jours, relance à J+<?= (int) cfg('relance_jours') ?>, alerte à l'administrateur à l'échéance<?= cfg('auto_passer_jours', 2) ? ', sortie de la chaîne ' . (int) cfg('auto_passer_jours', 2) . ' j après sans photo' : '' ?>.</li>
    <li>Sans réponse : remis en fin de liste d'attente pour une autre chaîne, <?= (int) cfg('max_chances', 3) ?> essais au total, puis retiré du cadavre (mail à chaque fois).</li>
    <li>Une chaîne se termine à la date de dernier envoi, au nombre max de photos, ou à la clôture.</li>
  </ul>
</div>
<?php endif ?>
</div>
<?php pied();
