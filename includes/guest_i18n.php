<?php
/** Texts of the guest pages (the table QR), in the guest's browser language. */
declare(strict_types=1);

const GUEST_LANGS = ['it' => 'Italiano', 'en' => 'English', 'de' => 'Deutsch', 'fr' => 'Français', 'es' => 'Español'];

const GUEST_TEXT = [
    'table'        => ['it' => 'Tavolo', 'en' => 'Table', 'de' => 'Tisch', 'fr' => 'Table', 'es' => 'Mesa'],
    'enter_code'   => ['it' => 'Inserisci il codice del tavolo', 'en' => 'Enter the table code', 'de' => 'Tischcode eingeben', 'fr' => 'Saisissez le code de la table', 'es' => 'Introduce el código de la mesa'],
    'code_hint'    => ['it' => 'Il codice te lo dà il cameriere.', 'en' => 'Your waiter will give you the code.', 'de' => 'Den Code erhalten Sie vom Kellner.', 'fr' => 'Le serveur vous donnera le code.', 'es' => 'El camarero te dará el código.'],
    'confirm'      => ['it' => 'Conferma', 'en' => 'Confirm', 'de' => 'Bestätigen', 'fr' => 'Valider', 'es' => 'Confirmar'],
    'wrong_code'   => ['it' => 'Codice errato. Riprova.', 'en' => 'Wrong code. Try again.', 'de' => 'Falscher Code. Bitte erneut versuchen.', 'fr' => 'Code incorrect. Réessayez.', 'es' => 'Código incorrecto. Inténtalo de nuevo.'],
    'too_many'     => ['it' => 'Troppi tentativi. Riprova tra qualche minuto o chiedi al cameriere.', 'en' => 'Too many attempts. Try again in a few minutes or ask your waiter.', 'de' => 'Zu viele Versuche. Bitte in einigen Minuten erneut versuchen oder den Kellner fragen.', 'fr' => 'Trop de tentatives. Réessayez dans quelques minutes ou demandez au serveur.', 'es' => 'Demasiados intentos. Inténtalo en unos minutos o pregunta al camarero.'],
    'expired'      => ['it' => 'Il conto di questo tavolo è stato chiuso. Per una nuova chiamata chiedi il nuovo codice al cameriere.', 'en' => 'The bill for this table has been closed. Ask your waiter for the new code.', 'de' => 'Die Rechnung für diesen Tisch wurde abgeschlossen. Bitte fragen Sie den Kellner nach dem neuen Code.', 'fr' => 'L\'addition de cette table a été clôturée. Demandez le nouveau code au serveur.', 'es' => 'La cuenta de esta mesa se ha cerrado. Pide el nuevo código al camarero.'],
    'call_waiter'  => ['it' => 'Chiama il cameriere', 'en' => 'Call the waiter', 'de' => 'Kellner rufen', 'fr' => 'Appeler le serveur', 'es' => 'Llamar al camarero'],
    'ask_bill'     => ['it' => 'Chiedi il conto', 'en' => 'Ask for the bill', 'de' => 'Rechnung anfordern', 'fr' => 'Demander l\'addition', 'es' => 'Pedir la cuenta'],
    'menu'         => ['it' => 'Menu', 'en' => 'Menu', 'de' => 'Speisekarte', 'fr' => 'Menu', 'es' => 'Carta'],
    'pay_how'      => ['it' => 'Come vuoi pagare?', 'en' => 'How would you like to pay?', 'de' => 'Wie möchten Sie bezahlen?', 'fr' => 'Comment souhaitez-vous payer ?', 'es' => '¿Cómo quieres pagar?'],
    'cash'         => ['it' => 'Contanti', 'en' => 'Cash', 'de' => 'Bar', 'fr' => 'Espèces', 'es' => 'Efectivo'],
    'card'         => ['it' => 'Carta', 'en' => 'Card', 'de' => 'Karte', 'fr' => 'Carte', 'es' => 'Tarjeta'],
    'cancel'       => ['it' => 'Annulla', 'en' => 'Cancel', 'de' => 'Abbrechen', 'fr' => 'Annuler', 'es' => 'Cancelar'],
    'waiter_open'  => ['it' => 'Cameriere chiamato: arriva a breve.', 'en' => 'Waiter called: on the way soon.', 'de' => 'Kellner gerufen: kommt gleich.', 'fr' => 'Serveur appelé : il arrive bientôt.', 'es' => 'Camarero llamado: llegará enseguida.'],
    'waiter_taken' => ['it' => 'Il cameriere sta arrivando.', 'en' => 'The waiter is coming.', 'de' => 'Der Kellner kommt.', 'fr' => 'Le serveur arrive.', 'es' => 'El camarero está llegando.'],
    'bill_open'    => ['it' => 'Conto richiesto.', 'en' => 'Bill requested.', 'de' => 'Rechnung angefordert.', 'fr' => 'Addition demandée.', 'es' => 'Cuenta solicitada.'],
    'bill_taken'   => ['it' => 'Il cameriere sta portando il conto.', 'en' => 'The waiter is bringing the bill.', 'de' => 'Der Kellner bringt die Rechnung.', 'fr' => 'Le serveur apporte l\'addition.', 'es' => 'El camarero trae la cuenta.'],
    'call_again'   => ['it' => 'Sollecita', 'en' => 'Remind', 'de' => 'Erinnern', 'fr' => 'Relancer', 'es' => 'Recordar'],
    'reminded'     => ['it' => 'Sollecito inviato.', 'en' => 'Reminder sent.', 'de' => 'Erinnerung gesendet.', 'fr' => 'Relance envoyée.', 'es' => 'Recordatorio enviado.'],
    'wait'         => ['it' => 'Richiesta già inviata, attendi qualche secondo.', 'en' => 'Already sent, please wait a few seconds.', 'de' => 'Bereits gesendet, bitte einige Sekunden warten.', 'fr' => 'Déjà envoyé, patientez quelques secondes.', 'es' => 'Ya enviado, espera unos segundos.'],
    'error'        => ['it' => 'Errore di connessione. Riprova.', 'en' => 'Connection error. Try again.', 'de' => 'Verbindungsfehler. Bitte erneut versuchen.', 'fr' => 'Erreur de connexion. Réessayez.', 'es' => 'Error de conexión. Inténtalo de nuevo.'],
    'not_found'    => ['it' => 'Questo QR non è attivo. Chiedi al personale.', 'en' => 'This QR code is not active. Please ask the staff.', 'de' => 'Dieser QR-Code ist nicht aktiv. Bitte fragen Sie das Personal.', 'fr' => 'Ce QR code n\'est pas actif. Demandez au personnel.', 'es' => 'Este código QR no está activo. Pregunta al personal.'],
    'back'         => ['it' => 'Indietro', 'en' => 'Back', 'de' => 'Zurück', 'fr' => 'Retour', 'es' => 'Volver'],
    'download'     => ['it' => 'Scarica PDF', 'en' => 'Download PDF', 'de' => 'PDF herunterladen', 'fr' => 'Télécharger le PDF', 'es' => 'Descargar PDF'],
    'loading'      => ['it' => 'Caricamento…', 'en' => 'Loading…', 'de' => 'Wird geladen…', 'fr' => 'Chargement…', 'es' => 'Cargando…'],
];

function guest_lang(): string
{
    static $lang = null;
    if ($lang !== null) return $lang;
    $pick = (string) ($_GET['lang'] ?? $_COOKIE['glang'] ?? '');
    if (!isset(GUEST_LANGS[$pick])) {
        $pick = 'it';
        foreach (explode(',', (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')) as $part) {
            $code = strtolower(substr(trim($part), 0, 2));
            if (isset(GUEST_LANGS[$code])) { $pick = $code; break; }
        }
    } elseif (isset($_GET['lang'])) {
        setcookie('glang', $pick, ['expires' => time() + 365 * 86400, 'path' => app_path(), 'samesite' => 'Lax']);
    }
    return $lang = $pick;
}

function gt(string $key): string
{
    return GUEST_TEXT[$key][guest_lang()] ?? GUEST_TEXT[$key]['it'] ?? $key;
}

/** All texts in the guest's language, for guest.js. */
function guest_texts(): array
{
    $out = [];
    foreach (array_keys(GUEST_TEXT) as $k) $out[$k] = gt($k);
    return $out;
}
