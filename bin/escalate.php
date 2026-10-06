<?php
/**
 * Unanswered calls (nobody tapped "Prendo io"):
 *   after venues.remind_after seconds   -> the push goes again to the same waiters;
 *   after venues.escalate_after seconds -> it goes to ALL the venue's staff, and again
 *                                          every escalate_after seconds until someone takes it.
 * 0 turns a step off. Run by cron every minute; it checks twice (now and after 30 s).
 *   /etc/cron.d/chiamata: * * * * * www-data /usr/bin/php /var/www/html/chiamata/bin/escalate.php
 */
if (PHP_SAPI !== 'cli') exit;
require __DIR__ . '/../includes/app.php';

function escalate_pass(): void
{
    $pdo = db();
    $rows = $pdo->query("SELECT c.id, c.table_id, c.reminded_at, c.escalated_at,
                                TIMESTAMPDIFF(SECOND, c.created_at, NOW()) AS age,
                                TIMESTAMPDIFF(SECOND, c.escalated_at, NOW()) AS since_escalated,
                                v.remind_after, v.escalate_after, t.venue_id, t.zone
                           FROM calls c JOIN venue_tables t ON t.id = c.table_id JOIN venues v ON v.id = c.venue_id
                          WHERE c.status = 'open' AND v.active = 1")->fetchAll();
    foreach ($rows as $c) {
        $table = ['id' => (int) $c['table_id'], 'venue_id' => (int) $c['venue_id'], 'zone' => $c['zone']];
        $esc = (int) $c['escalate_after'];
        $rem = (int) $c['remind_after'];
        if ($esc > 0 && $c['age'] >= $esc && ($c['escalated_at'] === null || $c['since_escalated'] >= $esc)) {
            // The WHERE makes it happen once even if two runs overlap.
            $st = $pdo->prepare("UPDATE calls SET escalated_at = NOW(), alerts = alerts + 1
                                  WHERE id = ? AND status = 'open' AND (escalated_at IS NULL OR escalated_at <= NOW() - INTERVAL ? SECOND)");
            $st->execute([$c['id'], $esc]);
            if ($st->rowCount()) notify_staff($table, true);
        } elseif ($rem > 0 && $c['age'] >= $rem && $c['reminded_at'] === null && $c['escalated_at'] === null) {
            $st = $pdo->prepare("UPDATE calls SET reminded_at = NOW(), alerts = alerts + 1 WHERE id = ? AND status = 'open' AND reminded_at IS NULL");
            $st->execute([$c['id']]);
            if ($st->rowCount()) notify_staff($table);
        }
    }
}

$lock = fopen(sys_get_temp_dir() . '/chiamata-escalate.lock', 'c');
if (!flock($lock, LOCK_EX | LOCK_NB)) exit;   // previous run still going
escalate_pass();
if (in_array('--once', $argv, true)) exit;   // manual test run
sleep(30);
escalate_pass();
