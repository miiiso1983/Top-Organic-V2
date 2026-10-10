<?php
namespace App\Controllers;

use App\Core\{Controller, Database, Response, Auth, MailService};

/**
 * مجموعات البريد الداخلي (قوائم توزيع مخصّصة) — أي مستخدم مسجّل دخول يمكنه
 * إنشاء مجموعة واستخدامها عند الإرسال (مثل الأقسام)، لكن التعديل والحذف
 * مقصوران على منشئ المجموعة فقط.
 */
class MailGroupController extends Controller
{
    /** يعيد المستخدم لصفحة البريد مع رسالة إذا كانت جداول migration 172 غير موجودة بعد */
    private function requireMigration(): bool
    {
        if (MailService::migrationReady()) return true;
        $this->flash('error', 'يلزم تحديث قاعدة البيانات أولاً لتفعيل مجموعات البريد');
        Response::redirect('/mail');
        return false;
    }

    public function index(): void
    {
        if (!$this->requireMigration()) return;
        $groups = Database::query(
            "SELECT g.id, g.name, g.created_by, u.full_name AS creator_name,
                    (SELECT COUNT(*) FROM mail_group_members WHERE group_id = g.id) AS members_count
             FROM mail_groups g JOIN users u ON u.id = g.created_by
             WHERE g.is_active = 1 ORDER BY g.name"
        )->fetchAll();
        $this->view('mail/groups_index', [
            'title' => 'مجموعات البريد',
            'groups' => $groups,
            'uid' => (int)Auth::id(),
        ]);
    }

    public function createForm(): void
    {
        if (!$this->requireMigration()) return;
        $uid = (int)Auth::id();
        $users = Database::query(
            "SELECT id, full_name FROM users WHERE id != ? AND is_active = 1 ORDER BY full_name",
            [$uid]
        )->fetchAll();
        $this->view('mail/groups_form', [
            'title' => 'مجموعة بريد جديدة',
            'users' => $users,
            'group' => null,
            'memberIds' => [],
        ]);
    }

    public function store(): void
    {
        if (!$this->requireMigration()) return;
        $uid = (int)Auth::id();
        $name = trim((string)($_POST['name'] ?? ''));
        $memberIds = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['member_ids'] ?? [])))));
        if ($name === '') {
            $this->flash('error', 'اسم المجموعة مطلوب');
            Response::redirect('/mail/groups/create');
            return;
        }
        Database::query("INSERT INTO mail_groups (name, created_by) VALUES (?,?)", [$name, $uid]);
        $groupId = (int)Database::lastInsertId();
        foreach ($memberIds as $mid) {
            if ($mid === $uid) continue;
            Database::query("INSERT IGNORE INTO mail_group_members (group_id, user_id) VALUES (?,?)", [$groupId, $mid]);
        }
        $this->flash('success', 'تم إنشاء المجموعة بنجاح');
        Response::redirect('/mail/groups');
    }

    public function editForm(string $id): void
    {
        if (!$this->requireMigration()) return;
        $uid = (int)Auth::id();
        $group = Database::query("SELECT * FROM mail_groups WHERE id = ? AND is_active = 1", [(int)$id])->fetch();
        if (!$group) Response::abort(404, 'المجموعة غير موجودة');
        if ((int)$group['created_by'] !== $uid) {
            $this->flash('error', 'يمكن فقط لمنشئ المجموعة تعديلها');
            Response::redirect('/mail/groups');
            return;
        }
        $users = Database::query(
            "SELECT id, full_name FROM users WHERE id != ? AND is_active = 1 ORDER BY full_name",
            [$uid]
        )->fetchAll();
        $memberIds = array_map('intval', array_column(
            Database::query("SELECT user_id FROM mail_group_members WHERE group_id = ?", [(int)$id])->fetchAll(),
            'user_id'
        ));
        $this->view('mail/groups_form', [
            'title' => 'تعديل المجموعة',
            'users' => $users,
            'group' => $group,
            'memberIds' => $memberIds,
        ]);
    }

    public function update(string $id): void
    {
        if (!$this->requireMigration()) return;
        $uid = (int)Auth::id();
        $group = Database::query("SELECT * FROM mail_groups WHERE id = ? AND is_active = 1", [(int)$id])->fetch();
        if (!$group || (int)$group['created_by'] !== $uid) {
            $this->flash('error', 'غير مصرح بتعديل هذه المجموعة');
            Response::redirect('/mail/groups');
            return;
        }
        $name = trim((string)($_POST['name'] ?? ''));
        $memberIds = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['member_ids'] ?? [])))));
        if ($name === '') {
            $this->flash('error', 'اسم المجموعة مطلوب');
            Response::redirect('/mail/groups/' . (int)$id . '/edit');
            return;
        }
        Database::query("UPDATE mail_groups SET name = ? WHERE id = ?", [$name, (int)$id]);
        Database::query("DELETE FROM mail_group_members WHERE group_id = ?", [(int)$id]);
        foreach ($memberIds as $mid) {
            if ($mid === $uid) continue;
            Database::query("INSERT IGNORE INTO mail_group_members (group_id, user_id) VALUES (?,?)", [(int)$id, $mid]);
        }
        $this->flash('success', 'تم تحديث المجموعة بنجاح');
        Response::redirect('/mail/groups');
    }

    public function delete(string $id): void
    {
        if (!$this->requireMigration()) return;
        $uid = (int)Auth::id();
        $group = Database::query("SELECT * FROM mail_groups WHERE id = ?", [(int)$id])->fetch();
        if (!$group || (int)$group['created_by'] !== $uid) {
            $this->flash('error', 'غير مصرح بحذف هذه المجموعة');
            Response::redirect('/mail/groups');
            return;
        }
        Database::query("UPDATE mail_groups SET is_active = 0 WHERE id = ?", [(int)$id]);
        $this->flash('success', 'تم حذف المجموعة');
        Response::redirect('/mail/groups');
    }
}
