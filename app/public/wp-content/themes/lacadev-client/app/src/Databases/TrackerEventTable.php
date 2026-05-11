<?php

namespace App\Databases;

/**
 * TrackerEventTable
 *
 * Local outbox + audit table for tracker delivery and support requests.
 */
class TrackerEventTable extends AbstractTable
{
    protected static function baseName(): string
    {
        return 'laca_tracker_events';
    }

    protected static function schema(): string
    {
        return <<<SQL
CREATE TABLE {table} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_uuid CHAR(36) NOT NULL,
    channel VARCHAR(32) NOT NULL DEFAULT 'tracker',
    event_type VARCHAR(64) NOT NULL DEFAULT 'other',
    status VARCHAR(20) NOT NULL DEFAULT 'queued',
    payload LONGTEXT NOT NULL,
    context LONGTEXT NULL,
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    last_error TEXT NULL,
    next_attempt_at DATETIME NULL,
    delivered_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_event_uuid (event_uuid),
    KEY idx_channel_status (channel, status),
    KEY idx_status_next_attempt (status, next_attempt_at),
    KEY idx_created_at (created_at),
    KEY idx_delivered_at (delivered_at)
) {charset_collate};
SQL;
    }

    public static function create(
        string $channel,
        string $eventType,
        array $payload,
        array $context = [],
        string $status = 'queued'
    ): int {
        return static::insert([
            'event_uuid'      => wp_generate_uuid4(),
            'channel'         => sanitize_key($channel),
            'event_type'      => sanitize_key($eventType ?: 'other'),
            'status'          => sanitize_key($status),
            'payload'         => wp_json_encode($payload, JSON_UNESCAPED_UNICODE),
            'context'         => empty($context) ? null : wp_json_encode($context, JSON_UNESCAPED_UNICODE),
            'attempts'        => 0,
            'last_error'      => null,
            'next_attempt_at' => current_time('mysql'),
            'delivered_at'    => null,
            'created_at'      => current_time('mysql'),
            'updated_at'      => current_time('mysql'),
        ], ['%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s']);
    }

    public static function findPending(int $limit = 10): array
    {
        global $wpdb;
        $table = static::tableName();
        $limit = max(1, min(50, $limit));

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT *
                 FROM {$table}
                 WHERE status IN ('queued', 'retry')
                   AND (next_attempt_at IS NULL OR next_attempt_at <= %s)
                 ORDER BY created_at ASC
                 LIMIT %d",
                current_time('mysql'),
                $limit
            ),
            ARRAY_A
        ) ?: [];
    }

    public static function markDelivered(int $id, int $attempts): void
    {
        static::update([
            'status'          => 'delivered',
            'attempts'        => $attempts,
            'last_error'      => null,
            'next_attempt_at' => null,
            'delivered_at'    => current_time('mysql'),
            'updated_at'      => current_time('mysql'),
        ], ['id' => $id]);
    }

    public static function markRetry(int $id, int $attempts, string $error, ?string $nextAttemptAt): void
    {
        static::update([
            'status'          => 'retry',
            'attempts'        => $attempts,
            'last_error'      => wp_strip_all_tags($error),
            'next_attempt_at' => $nextAttemptAt,
            'updated_at'      => current_time('mysql'),
        ], ['id' => $id]);
    }

    public static function markFailed(int $id, int $attempts, string $error): void
    {
        static::update([
            'status'          => 'failed',
            'attempts'        => $attempts,
            'last_error'      => wp_strip_all_tags($error),
            'next_attempt_at' => null,
            'updated_at'      => current_time('mysql'),
        ], ['id' => $id]);
    }

    public static function countByStatus(?string $status = null, ?string $channel = null): int
    {
        global $wpdb;
        $table = static::tableName();

        $where = [];
        $args = [];

        if ($status !== null && $status !== '') {
            $where[] = 'status = %s';
            $args[] = sanitize_key($status);
        }

        if ($channel !== null && $channel !== '') {
            $where[] = 'channel = %s';
            $args[] = sanitize_key($channel);
        }

        $sql = "SELECT COUNT(*) FROM {$table}";
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        if (!empty($args)) {
            $sql = $wpdb->prepare($sql, ...$args);
        }

        return (int) $wpdb->get_var($sql);
    }

    public static function getRecent(int $limit = 20, ?string $channel = null): array
    {
        global $wpdb;
        $table = static::tableName();
        $limit = max(1, min(100, $limit));

        if ($channel !== null && $channel !== '') {
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE channel = %s ORDER BY created_at DESC LIMIT %d",
                    sanitize_key($channel),
                    $limit
                ),
                ARRAY_A
            ) ?: [];
        }

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        ) ?: [];
    }

    public static function getSummarySince(int $days = 7): array
    {
        global $wpdb;
        $table = static::tableName();
        $days = max(1, min(365, $days));

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT channel, status, event_type, COUNT(*) AS total
                 FROM {$table}
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
                 GROUP BY channel, status, event_type",
                $days
            ),
            ARRAY_A
        ) ?: [];

        $summary = [
            'total' => 0,
            'by_channel' => [],
            'by_status' => [],
            'by_type' => [],
        ];

        foreach ($rows as $row) {
            $count = (int) ($row['total'] ?? 0);
            $channel = (string) ($row['channel'] ?? 'tracker');
            $status = (string) ($row['status'] ?? 'queued');
            $type = (string) ($row['event_type'] ?? 'other');

            $summary['total'] += $count;
            $summary['by_channel'][$channel] = ($summary['by_channel'][$channel] ?? 0) + $count;
            $summary['by_status'][$status] = ($summary['by_status'][$status] ?? 0) + $count;
            $summary['by_type'][$type] = ($summary['by_type'][$type] ?? 0) + $count;
        }

        return $summary;
    }

    public static function decodeJsonColumn(?string $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    public static function purgeOld(int $days = 90): void
    {
        global $wpdb;
        $table = static::tableName();
        $days = max(7, $days);

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        );
    }
}
