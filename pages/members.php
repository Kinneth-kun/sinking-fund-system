<?php
$pageTitle = 'Members';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/payments.php';

$db = getDB();
$msg = ''; $msgType = 'success';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $nick = trim($_POST['nickname'] ?? $name);
        if ($name) {
            $db->prepare("INSERT INTO members (name, nickname) VALUES (?, ?)")->execute([$name, $nick ?: $name]);
            $msg = "Member '{$name}' added successfully!";
        } else { $msg = 'Name is required.'; $msgType = 'error'; }
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("UPDATE members SET status = CASE WHEN status='active' THEN 'inactive' ELSE 'active' END WHERE id = ?")->execute([$id]);
        $msg = 'Member status updated.';
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("DELETE FROM members WHERE id = ?")->execute([$id]);
        $msg = 'Member removed.';
    }

    if ($action === 'edit') {
        $id   = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $nick = trim($_POST['nickname'] ?? $name);
        if ($name && $id) {
            $db->prepare("UPDATE members SET name=?, nickname=? WHERE id=?")->execute([$name, $nick ?: $name, $id]);
            $msg = 'Member updated.';
        }
    }
}

$members = $db->query("
    SELECT m.*,
           COUNT(pd.id) FILTER (WHERE pd.covered_date <= CURRENT_DATE) as days_paid,
           COUNT(pd.id) FILTER (WHERE pd.covered_date > CURRENT_DATE) as advance_days,
           MAX(pd.covered_date) FILTER (WHERE pd.covered_date > CURRENT_DATE) as advance_until,
           COUNT(pd.id) * 5 as total_paid,
           (CURRENT_DATE - DATE '2026-01-01' + 1) as days_since_start
    FROM members m
    LEFT JOIN payment_days pd ON pd.member_id = m.id
    GROUP BY m.id
    ORDER BY m.name
")->fetchAll();
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType==='error' ? 'error' : 'success' ?>">
  <?= uiIcon($msgType==='error' ? 'alert' : 'check-circle') ?> <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<!-- ── HEADER ROW ── -->
<div class="section-header mb-4">
  <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
    <span class="section-title">All Members</span>
    <span class="chip"><?= count($members) ?> total</span>
  </div>
  <div style="display:flex;gap:10px;flex-wrap:wrap;width:100%;max-width:380px">
    <input type="text" id="member-search" class="form-control"
           placeholder="Search member…"
           style="flex:1;padding:9px 13px;font-size:13px;">
    <button class="btn btn-gold" onclick="openModal('modal-add')" style="white-space:nowrap">
      <?= uiIcon('plus') ?> Add Member
    </button>
  </div>
</div>

<!-- ══════════════════════════════════
     DESKTOP TABLE (hidden on mobile)
     ══════════════════════════════════ -->
<div class="table-wrap desktop-table">
  <table id="members-table">
    <thead>
      <tr>
        <th style="width:36px">#</th>
        <th>Name</th>
        <th>Days Paid</th>
        <th>Total Paid</th>
        <th>Balance Due</th>
        <th>Advance</th>
        <th>Rate</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($members as $i => $m):
        $elapsed = max(1, (int)$m['days_since_start']);
        $balance = max(0, ($elapsed - (int)$m['days_paid']) * 5);
        $rate    = $elapsed > 0 ? round(($m['days_paid'] / $elapsed) * 100, 1) : 0;
      ?>
      <tr>
        <td class="text-muted" style="font-size:12px"><?= $i+1 ?></td>

        <td>
          <div style="display:flex;align-items:center;gap:10px">
            <div class="avatar-sm"><?= strtoupper(substr($m['name'],0,1)) ?></div>
            <div>
              <div class="fw-600"><?= htmlspecialchars($m['name']) ?></div>
              <?php if ($m['nickname'] && $m['nickname'] !== $m['name']): ?>
              <div class="text-muted" style="font-size:11px"><?= htmlspecialchars($m['nickname']) ?></div>
              <?php endif; ?>
            </div>
          </div>
        </td>

        <td>
          <span class="text-success fw-600"><?= $m['days_paid'] ?></span>
          <span class="text-muted"> / <?= $elapsed ?></span>
        </td>

        <td class="fw-600">₱<?= number_format($m['total_paid'], 2) ?></td>

        <td>
          <?= $balance > 0
            ? "<span class='text-danger fw-600'>₱" . number_format($balance, 2) . "</span>"
            : '<span class="text-success">' . uiIcon('check') . ' Settled</span>' ?>
        </td>

        <td>
          <?php if ((int)$m['advance_days'] > 0): ?>
            <span class="badge badge-info"><?= (int)$m['advance_days'] ?> days</span>
            <div class="text-muted" style="font-size:10px;margin-top:3px">through <?= date('M d, Y', strtotime($m['advance_until'])) ?></div>
          <?php else: ?>
            <span class="text-muted">—</span>
          <?php endif; ?>
        </td>

        <td style="min-width:110px">
          <div style="display:flex;align-items:center;gap:7px;">
            <div class="progress-wrap" style="flex:1">
              <div class="progress-bar <?= $rate>=100?'green':'' ?>" data-width="<?= $rate ?>"></div>
            </div>
            <span style="font-size:11px;color:var(--text2);white-space:nowrap"><?= $rate ?>%</span>
          </div>
        </td>

        <td>
          <?= $m['status']==='active'
            ? '<span class="badge badge-success">Active</span>'
            : '<span class="badge badge-danger">Inactive</span>' ?>
        </td>

        <td>
          <div style="display:flex;gap:5px;flex-wrap:nowrap">
            <a href="payments.php?member_id=<?= $m['id'] ?>" class="btn btn-gold btn-sm"><?= uiIcon('banknote') ?> Pay</a>
            <button class="btn btn-ghost btn-sm"
                    onclick="editMember(<?= htmlspecialchars(json_encode($m)) ?>)"
                    title="Edit" aria-label="Edit"><?= uiIcon('save') ?></button>
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= $m['id'] ?>">
              <button class="btn btn-ghost btn-sm" type="submit"
                      title="<?= $m['status']==='active' ? 'Deactivate' : 'Activate' ?>">
                <?= uiIcon($m['status']==='active' ? 'pause' : 'play') ?>
              </button>
            </form>
            <form method="POST" style="display:inline"
                  onsubmit="return confirm('Delete this member and ALL their payment data?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $m['id'] ?>">
              <button class="btn btn-danger btn-sm" type="submit" title="Delete" aria-label="Delete"><?= uiIcon('trash') ?></button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- ══════════════════════════════════
     MOBILE CARD LIST (hidden on desktop)
     ══════════════════════════════════ -->
<div class="member-card-list" id="mobile-member-list">
  <?php foreach ($members as $i => $m):
    $elapsed = max(1, (int)$m['days_since_start']);
    $balance = max(0, ($elapsed - (int)$m['days_paid']) * 5);
    $rate    = $elapsed > 0 ? round(($m['days_paid'] / $elapsed) * 100, 1) : 0;
  ?>
  <div class="member-card" data-name="<?= strtolower(htmlspecialchars($m['name'])) ?>">

    <!-- Header -->
    <div class="member-card-header">
      <div class="avatar-sm" style="width:40px;height:40px;font-size:15px">
        <?= strtoupper(substr($m['name'],0,1)) ?>
      </div>
      <div style="flex:1;min-width:0">
        <div class="member-card-name"><?= htmlspecialchars($m['name']) ?></div>
        <?php if ($m['nickname'] && $m['nickname'] !== $m['name']): ?>
        <div class="member-card-nick"><?= htmlspecialchars($m['nickname']) ?></div>
        <?php endif; ?>
      </div>
      <?= $m['status']==='active'
        ? '<span class="badge badge-success">Active</span>'
        : '<span class="badge badge-danger">Inactive</span>' ?>
    </div>

    <!-- Stats grid -->
    <div class="member-card-grid">
      <div class="member-card-stat">
        <div class="member-card-stat-label">Days Paid</div>
        <div class="member-card-stat-value text-success"><?= $m['days_paid'] ?><span class="text-muted" style="font-weight:400;font-size:11px"> / <?= $elapsed ?></span></div>
      </div>
      <div class="member-card-stat">
        <div class="member-card-stat-label">Total Paid</div>
        <div class="member-card-stat-value text-gold">₱<?= number_format($m['total_paid'], 2) ?></div>
      </div>
      <div class="member-card-stat">
        <div class="member-card-stat-label">Balance Due</div>
        <div class="member-card-stat-value <?= $balance > 0 ? 'text-danger' : 'text-success' ?>">
          <?= $balance > 0 ? '₱' . number_format($balance, 2) : uiIcon('check') . ' Settled' ?>
        </div>
      </div>
      <div class="member-card-stat">
        <div class="member-card-stat-label">Pay Rate</div>
        <div class="member-card-stat-value" style="color:var(--text)"><?= $rate ?>%</div>
      </div>
    </div>

    <?php if ((int)$m['advance_days'] > 0): ?>
    <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;margin-bottom:12px;border:1px solid rgba(74,144,217,.25);background:rgba(74,144,217,.08);border-radius:9px;font-size:12px">
      <span class="text-info fw-600">Advance payment</span>
      <span><?= (int)$m['advance_days'] ?> days · paid through <?= date('M d, Y', strtotime($m['advance_until'])) ?></span>
    </div>
    <?php endif; ?>

    <!-- Progress bar -->
    <div style="margin-bottom:12px">
      <div class="progress-wrap">
        <div class="progress-bar <?= $rate>=100?'green':'' ?>" data-width="<?= $rate ?>"></div>
      </div>
    </div>

    <!-- Action buttons -->
    <div class="member-card-actions">
      <a href="payments.php?member_id=<?= $m['id'] ?>" class="btn btn-gold btn-sm"><?= uiIcon('banknote') ?> Pay</a>
      <button class="btn btn-ghost btn-sm"
              onclick="editMember(<?= htmlspecialchars(json_encode($m)) ?>)"><?= uiIcon('save') ?> Edit</button>
      <form method="POST" style="flex:1">
        <input type="hidden" name="action" value="toggle">
        <input type="hidden" name="id" value="<?= $m['id'] ?>">
        <button class="btn btn-ghost btn-sm" type="submit" style="width:100%;justify-content:center">
          <?= uiIcon($m['status']==='active' ? 'pause' : 'play') ?> <?= $m['status']==='active' ? 'Deactivate' : 'Activate' ?>
        </button>
      </form>
      <form method="POST" onsubmit="return confirm('Delete this member and ALL payment data?')">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= $m['id'] ?>">
        <button class="btn btn-danger btn-sm" type="submit" aria-label="Delete"><?= uiIcon('trash') ?></button>
      </form>
    </div>

  </div>
  <?php endforeach; ?>
</div>

<!-- ══════════════════════════════════
     ADD MEMBER MODAL
     ══════════════════════════════════ -->
<div class="modal-overlay" id="modal-add">
  <div class="modal">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
      <h2 class="modal-title" style="margin:0"><?= uiIcon('plus') ?> Add New Member</h2>
      <button class="modal-close" onclick="closeModal('modal-add')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="form-group">
        <label>Full Name *</label>
        <input type="text" name="name" class="form-control" placeholder="e.g. Mama Rosa" required>
      </div>
      <div class="form-group">
        <label>Nickname / Display Name</label>
        <input type="text" name="nickname" class="form-control" placeholder="Optional — defaults to full name">
      </div>
      <div style="display:flex;gap:10px;margin-top:4px">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-add')" style="flex:1">Cancel</button>
        <button type="submit" class="btn btn-gold" style="flex:2"><?= uiIcon('plus') ?> Add Member</button>
      </div>
    </form>
  </div>
</div>

<!-- ══════════════════════════════════
     EDIT MEMBER MODAL
     ══════════════════════════════════ -->
<div class="modal-overlay" id="modal-edit">
  <div class="modal">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
      <h2 class="modal-title" style="margin:0"><?= uiIcon('save') ?> Edit Member</h2>
      <button class="modal-close" onclick="closeModal('modal-edit')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="edit-id">
      <div class="form-group">
        <label>Full Name *</label>
        <input type="text" name="name" id="edit-name" class="form-control" required>
      </div>
      <div class="form-group">
        <label>Nickname</label>
        <input type="text" name="nickname" id="edit-nick" class="form-control">
      </div>
      <div style="display:flex;gap:10px;margin-top:4px">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-edit')" style="flex:1">Cancel</button>
        <button type="submit" class="btn btn-gold" style="flex:2"><?= uiIcon('save') ?> Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
// ── Table search (desktop) ──
filterTable('member-search', 'members-table');

// ── Card search (mobile) ──
document.getElementById('member-search').addEventListener('input', function() {
  const q = this.value.toLowerCase();
  document.querySelectorAll('#mobile-member-list .member-card').forEach(card => {
    card.style.display = card.dataset.name.includes(q) ? '' : 'none';
  });
});

// ── Edit modal ──
function editMember(data) {
  document.getElementById('edit-id').value   = data.id;
  document.getElementById('edit-name').value = data.name;
  document.getElementById('edit-nick').value = data.nickname || '';
  openModal('modal-edit');
}

// ── Animate progress bars ──
document.querySelectorAll('.progress-bar[data-width]').forEach(bar => {
  const w = parseFloat(bar.dataset.width);
  setTimeout(() => { bar.style.width = Math.min(100, w) + '%'; }, 200);
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
