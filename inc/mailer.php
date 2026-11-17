<?php
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

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
function envoyer_mail(string $to, string $sujet, string $corps, ?string $nom = null): bool
{
    $statut = 'envoye';
    $erreur = null;
    if (cfg('mail.mode') === 'smtp') {
        $m = new PHPMailer(true);
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
    return envoyer_mail($to, remplir($mod['sujet'], $vars), remplir($mod['corps'], $vars), $nom);
}

function mail_admin(string $sujet, string $corps): void
{
    if (cfg('mail.admin')) envoyer_mail(cfg('mail.admin'), '[Cadavre Exquis] ' . $sujet, $corps);
}
