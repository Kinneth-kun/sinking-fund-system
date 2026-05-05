<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/payments.php';

$stats = getDashboardStats();
?>

<div class="stat-grid">
  <div class="stat-card gold">
    <span class="stat-icon">💰</span>
    <div class="stat-label">Total Collected</div>
    <div class="stat-value">₱<?= number_format($stats['actual_total'], 2) ?></div>
    <div class="stat-sub">of ₱<?= number_format($stats['expected_total'], 2) ?> expected</div>
    <div style="margin-top:10px">
      <div class="progress-wrap"><div class="progress-bar" data-width="<?= min(100, $stats['collection_rate']) ?>"></div></div>
      <div class="stat-sub" style="margin-top:4px"><?= $stats['collection_rate'] ?>% collection rate</div>
    </div>
  </div>

  <div class="stat-card green">
    <span class="stat-icon">👥</span>
    <div class="stat-label">Active Members</div>
    <div class="stat-value green"><?= $stats['total_members'] ?></div>
    <div class="stat-sub">Paying ₱5/day each</div>
  </div>

  <div class="stat-card blue">
    <span class="stat-icon">📅</span>
    <div class="stat-label">Days Since Start</div>
    <div class="stat-value blue"><?= $stats['days_elapsed'] ?></div>
    <div class="stat-sub">Since Jan 1, 2026</div>
  </div>

  <div class="stat-card gold">
    <span class="stat-icon">📆</span>
    <div class="stat-label">This Month</div>
    <div class="stat-value">₱<?= number_format($stats['this_month'], 2) ?></div>
    <div class="stat-sub"><?= date('F Y') ?></div>
  </div>
</div>

<div class="grid-2 mb-6">
  <!-- Top Payers -->
  <div class="card">
    <div class="section-header mb-4">
      <span class="section-title">🏆 Top Payers</span>
      <span class="chip">All Time</span>
    </div>
    <?php foreach ($stats['top_payers'] as $i => $payer): ?>
    <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border2);">
      <span class="rank-medal"><?= ['🥇','🥈','🥉','4️⃣','5️⃣'][$i] ?></span>
      <div class="avatar-sm"><?= strtoupper(substr($payer['name'],0,1)) ?></div>
      <div style="flex:1">
        <div class="fw-600"><?= htmlspecialchars($payer['name']) ?></div>
        <div class="text-muted" style="font-size:11px"><?= $payer['days_paid'] ?> days paid</div>
      </div>
      <div class="text-gold fw-600">₱<?= number_format($payer['total_paid'], 2) ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Recent Payments -->
  <div class="card">
    <div class="section-header mb-4">
      <span class="section-title">🕐 Recent Payments</span>
      <a href="payments.php" class="btn btn-ghost btn-sm">View All</a>
    </div>
    <?php if (empty($stats['recent_payments'])): ?>
      <p class="text-muted" style="text-align:center;padding:20px 0">No payments yet.</p>
    <?php else: ?>
    <?php foreach ($stats['recent_payments'] as $pay): ?>
    <div style="display:flex;align-items:center;gap:12px;padding:9px 0;border-bottom:1px solid var(--border2);">
      <div class="avatar-sm" style="font-size:10px"><?= strtoupper(substr($pay['member_name'],0,1)) ?></div>
      <div style="flex:1">
        <div class="fw-600" style="font-size:13px"><?= htmlspecialchars($pay['member_name']) ?></div>
        <div class="text-muted" style="font-size:11px">
          <?= date('M d', strtotime($pay['date_from'])) ?> – <?= date('M d, Y', strtotime($pay['date_to'])) ?>
          · <?= $pay['days_covered'] ?> days
        </div>
      </div>
      <div class="text-success fw-600">+₱<?= number_format($pay['amount'], 2) ?></div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- Members Balance Table -->
<div class="card">
  <div class="section-header mb-4">
    <span class="section-title">📋 Members Balance Status</span>
    <input type="text" id="member-search" class="form-control" placeholder="🔍 Search member..." style="width:200px;padding:8px 12px;font-size:13px;">
  </div>
  <div class="table-wrap">
    <table id="balance-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Member</th>
          <th>Days Paid</th>
          <th>Unpaid Days</th>
          <th>Balance Due</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($stats['unpaid_summary'] as $i => $m): ?>
        <tr>
          <td class="text-muted"><?= $i+1 ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:8px;">
              <div class="avatar-sm" style="font-size:11px"><?= strtoupper(substr($m['name'],0,1)) ?></div>
              <span class="fw-600"><?= htmlspecialchars($m['name']) ?></span>
            </div>
          </td>
          <td><span class="text-success"><?= $m['paid_days'] ?></span> <span class="text-muted">/ <?= $m['total_days'] ?></span></td>
          <td><?= $m['unpaid_days'] > 0 ? "<span class='text-danger'>{$m['unpaid_days']}</span>" : '<span class="text-success">0</span>' ?></td>
          <td><?= $m['balance_due'] > 0 ? "<span class='text-danger fw-600'>₱".number_format($m['balance_due'],2)."</span>" : '<span class="text-success">✅ Settled</span>' ?></td>
          <td>
            <?php
            $rate = $m['total_days'] > 0 ? ($m['paid_days'] / $m['total_days'] * 100) : 0;
            if ($rate >= 100) echo '<span class="badge badge-success">Full</span>';
            elseif ($rate >= 70) echo '<span class="badge badge-warning">Good</span>';
            else echo '<span class="badge badge-danger">Behind</span>';
            ?>
          </td>
          <td>
            <a href="payments.php?member_id=<?= $m['id'] ?>" class="btn btn-gold btn-sm">+ Pay</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
filterTable('member-search', 'balance-table');

// Animate progress bars
document.querySelectorAll('.progress-bar[data-width]').forEach(bar => {
  const w = parseFloat(bar.dataset.width);
  setTimeout(() => bar.style.width = Math.min(100,w) + '%', 200);
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>