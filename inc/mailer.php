<?php
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

/** PHPMailer sans encodage de l'en-tête List-Unsubscribe (il doit rester lisible tel quel). */
class MailerCadavre extends PHPMailer
{
    public function encodeHeader($str, $position = 'text')
    {
        if (str_starts_with((string) $str, '<mailto:')) return $str;
        return parent::encodeHeader($str, $position);
    }
}

/** Remplace {variables} dans un texte. */
function remplir(string $txt, array $vars): string
{
    return preg_replace_callback('/\{(\w+)\}/', fn($m) => array_key_exists($m[1], $vars) ? (string) $vars[$m[1]] : $m[0], $txt);
}

/** Texte brut -> HTML simple (liens cliquables, paragraphes). */
function texte_vers_html(string $txt): string
{
    $html = h($txt);
    $html = preg_replace('~(https?://[^\s<]+)~', '<a href="$1" style="color:#7c3aed">$1</a>', $html);
    $html = nl2br($html);
    return '<div style="font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.5;color:#222;max-width:640px">' . $html . '</div>';
}

/**
 * Envoie un mail et le journalise. Retourne true si OK.
 */
function envoyer_mail(string $to, string $sujet, string $corps, ?string $nom = null, array $opts = []): bool
{
    $statut = 'envoye';
    $erreur = null;
    if (cfg('mail.mode') === 'smtp') {
        $m = new MailerCadavre(true);
        try {
            $m->isSMTP();
            $m->Host = cfg('mail.host');
            $m->Port = (int) cfg('mail.port');
            $m->SMTPAuth = true;
            $m->Username = cfg('mail.user');
            $m->Password = cfg('mail.pass');
            $m->SMTPSecure = cfg('mail.secure') === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $m->CharSet = 'UTF-8';
            $m->setFrom(cfg('mail.from'), cfg('mail.from_name'));
            $m->addReplyTo(cfg('mail.from'), cfg('mail.from_name'));
            if (!empty($opts['desinscription'])) {
                // Mails envoyés à plusieurs personnes : lien de désinscription standard (moins de risque de spam)
                $m->addCustomHeader('List-Unsubscribe', '<mailto:' . cfg('mail.from') . '?subject=STOP>');
            }
            $m->addAddress($to, $nom ?? '');
            $m->Subject = $sujet;
            $m->isHTML(true);
            $m->Body = texte_vers_html($corps);
            $m->AltBody = $corps;
            $m->send();
        } catch (Throwable $e) {
            $statut = 'erreur';
            $erreur = $m->ErrorInfo ?: $e->getMessage();
        }
    } else {
        $statut = 'simule';
    }
    insert('journal_mails', [
        'date_envoi' => now(), 'destinataire' => $to, 'sujet' => $sujet,
        'corps' => $corps, 'statut' => $statut, 'erreur' => $erreur,
    ]);
    return $statut !== 'erreur';
}

/** Envoie un mail à partir d'un modèle (table modeles_mail). */
function envoyer_modele(string $code, string $to, array $vars, ?string $nom = null): bool
{
    $mod = q1('SELECT * FROM modeles_mail WHERE code = ?', [$code]);
    if (!$mod) return false;
    $groupes = ['invitation', 'file_attente', 'fin']; // mails envoyés à beaucoup de monde d'un coup
    return envoyer_mail($to, remplir($mod['sujet'], $vars), remplir($mod['corps'], $vars), $nom,
        ['desinscription' => in_array($code, $groupes, true)]);
}

/** Pause entre deux mails d'un envoi groupé (évite le classement en spam). */
function pause_envoi(): void
{
    if (cfg('mail.mode') === 'smtp') sleep(max(0, (int) cfg('mail_pause_sec', 4)));
}

function mail_admin(string $sujet, string $corps): void
{
    if (cfg('mail.admin')) envoyer_mail(cfg('mail.admin'), '[Cadavre Exquis] ' . $sujet, $corps);
}
