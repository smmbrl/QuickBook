<?php
/**
 * NotificationHelper
 * Central helper for inserting notifications.
 * All notification logic goes through here so every
 * admin is always kept in the loop automatically.
 */
class NotificationHelper
{
    /**
     * Send a notification to one or more user IDs.
     *
     * @param PDO    $db
     * @param array  $userIds
     * @param string $type
     * @param string $title
     * @param string $message
     * @param string $body
     * @param string $linkUrl  Optional URL to redirect when notification is clicked
     */
    public static function send(
        PDO    $db,
        array  $userIds,
        string $type,
        string $title,
        string $message,
        string $body     = '',
        string $linkUrl  = ''
    ): void {
        if (empty($userIds)) return;

        $stmt = $db->prepare("
            INSERT INTO tbl_notifications
                (user_id, type, title, message, body, link_url, is_read, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 0, NOW())
        ");

        foreach (array_unique(array_filter($userIds)) as $uid) {
            $stmt->execute([(int)$uid, $type, $title, $message, $body, $linkUrl]);
        }
    }

    /**
     * Return all admin user IDs from the database.
     */
    public static function adminIds(PDO $db): array
    {
        $stmt = $db->query("SELECT id FROM tbl_users WHERE role = 'admin'");
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Send to primary recipients AND to every admin (with separate admin message).
     * Admins who are also in $userIds won't get a duplicate.
     */
    public static function sendWithAdmin(
        PDO    $db,
        array  $userIds,
        string $type,
        string $title,
        string $message,
        string $adminTitle,
        string $adminMessage,
        string $body         = '',
        string $linkUrl      = '',
        string $adminLinkUrl = ''
    ): void {
        self::send($db, $userIds, $type, $title, $message, $body, $linkUrl);

        $adminIds  = self::adminIds($db);
        $adminOnly = array_diff($adminIds, $userIds);
        self::send($db, $adminOnly, $type, $adminTitle, $adminMessage, $body, $adminLinkUrl ?: $linkUrl);
    }
}