<?php

namespace App\Databases;

/**
 * Base class for all WordPress custom DB tables.
 */
abstract class AbstractTable
{
    abstract protected static function baseName(): string;
    abstract protected static function schema(): string;

    public static function tableName(): string
    {
        global $wpdb;
        return $wpdb->prefix . static::baseName();
    }

    public static function install(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $table   = static::tableName();
        $sql     = str_replace(
            ['{table}', '{charset_collate}'],
            [$table,    $charset],
            static::schema()
        );
        dbDelta($sql);
    }

    public static function drop(): void
    {
        global $wpdb;
        $table = static::tableName();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("DROP TABLE IF EXISTS `$table`");
    }

    public static function exists(): bool
    {
        global $wpdb;
        $table = static::tableName();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (bool) $wpdb->get_var("SHOW TABLES LIKE '$table'");
    }

    public static function insert(array $data, ?array $format = null): int
    {
        global $wpdb;
        $wpdb->insert(static::tableName(), $data, $format);
        return (int) $wpdb->insert_id;
    }

    public static function update(array $data, array $where): int|false
    {
        global $wpdb;
        return $wpdb->update(static::tableName(), $data, $where);
    }

    public static function delete(array $where): int|false
    {
        global $wpdb;
        return $wpdb->delete(static::tableName(), $where);
    }

    public static function find(int $id): ?array
    {
        global $wpdb;
        $table = static::tableName();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM `$table` WHERE id = %d", $id), ARRAY_A);
        return $row ?: null;
    }

    public static function count(): int
    {
        global $wpdb;
        $table = static::tableName();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM `$table`");
    }

    public static function query(string $sql, array $args = []): array
    {
        global $wpdb;
        if (!empty($args)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $sql = $wpdb->prepare($sql, ...$args);
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return (array) $wpdb->get_results($sql, ARRAY_A);
    }
}
