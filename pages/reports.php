<?php
$pageTitle = 'Reports & Analytics';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/payments.php';

$db = getDB();
$today = new DateTime();
$startDate = new DateTime(START_DATE);

// Monthly totals for the year
$monthlyData = [];
for ($m = 1; $m <= 12; $m++) {
    $res = getMonthlyTotals(2026, $m);
    $daysInMonth  = cal_days_in_month(CAL_GREGORIAN, $m, 2026);
    $memberCount  = $db->query("SELECT COUNT(*) FROM members WHERE status='active'")->fetchColumn();
    $firstOfMonth = new DateTime("2026-$m-01");
    $lastOfMonth  = new DateTime("2026-$m-" . $daysInMonth);
    $lastOfMonth->setTime(23,59,59);

    if ($firstOfMonth > $today) {
        $expected = 0;
    } else {
        $effectiveLast = min($lastOfMonth, $today);
        $effectiveFirst = max($firstOfMonth, $startDate);
        $elapsedDays = $effectiveFirst <= $effectiveLast ? (int)$effectiveFirst->diff($effectiveLast)->days + 1 : 0;
        $expected = $elapsedDays * $memberCount * 5;
    }

    $monthlyData[$m] = [
        'month'    => date('M', mktime(0,0,0,$m,1,2026)),
        'total'    => (float)($res['total'] ?? 0),
        'expected' => $expected,
        'members'  => $res['members_paid'] ?? 0,
    ];
}

// Top members all time
$topMembers = $db->query("
    SELECT m.name,
           COUNT(pd.id) FILTER (WHERE pd.covered_date <= CURRENT_DATE) as days_paid,
           COUNT(pd.id) FILTER (WHERE pd.covered_date > CURRENT_DATE) as advance_days,
           MAX(pd.covered_date) FILTER (WHERE pd.covered_date > CURRENT_DATE) as advance_until,
           COUNT(pd.id) * 5 as total_paid,
           (CURRENT_DATE - DATE '2026-01-01' + 1) as elapsed
    FROM members m
<<<<<<< HEAD
    LEFT JOIN payment_days pd ON pd.member_id = m.id
=======
    LEFT JOIN payment_days pd ON pd.member_id = m.id AND pd.covered_date <= CURRENT_DATE
>>>>>>> 865812cf6d8f3ebf1e433273b3e7b0202a801b93
    WHERE m.status = 'active'
    GROUP BY m.id, m.name
    ORDER BY total_paid DESC
")->fetchAll();

// Members with most arrears
$mostArrears = $db->query("
    SELECT m.name, m.id,
           (CURRENT_DATE - DATE '2026-01-01' + 1 - COUNT(pd.id)) as unpaid_days,
           (CURRENT_DATE - DATE '2026-01-01' + 1 - COUNT(pd.id)) * 5 as arrears
    FROM members m
    LEFT JOIN payment_days pd ON pd.member_id = m.id AND pd.covered_date <= CURRENT_DATE
    WHERE m.status = 'active'
    GROUP BY m.id, m.name
    ORDER BY arrears DESC
    LIMIT 5
")->fetchAll();

// Grand totals
$grandTotal = $db->query("SELECT COUNT(*) * 5 FROM payment_days")->fetchColumn();
$elapsed = max(1, (int)$startDate->diff($today)->days + 1);
$memberCount = $db->query("SELECT COUNT(*) FROM members WHERE status='active'")->fetchColumn();
$expectedTotal = $elapsed * $memberCount * 5;
$collectionRate = $expectedTotal > 0 ? round(($grandTotal / $expectedTotal) * 100, 1) : 0;
?>

<div class="stat-grid mb-6">
  <div class="stat-card gold">
    <span class="stat-icon"><?= uiIcon('wallet') ?></span>
    <div class="stat-label">Grand Total Collected</div>
    <div class="stat-value">₱<?= number_format($grandTotal, 2) ?></div>
    <div class="stat-sub"><?= $collectionRate ?>% of expected</div>
  </div>
  <div class="stat-card green">
    <span class="stat-icon"><?= uiIcon('check-circle') ?></span>
    <div class="stat-label">Collection Rate</div>
    <div class="stat-value green"><?= $collectionRate ?>%</div>
    <div class="stat-sub">₱<?= number_format($expectedTotal,2) ?> expected</div>
  </div>
  <div class="stat-card blue">
    <span class="stat-icon"><?= uiIcon('calendar') ?></span>
    <div class="stat-label">Deficit</div>
    <div class="stat-value blue">₱<?= number_format(max(0,$expectedTotal - $grandTotal), 2) ?></div>
    <div class="stat-sub">Uncollected so far</div>
  </div>
  <div class="stat-card gold">
    <span class="stat-icon"><?= uiIcon('users') ?></span>
    <div class="stat-label">Active Members</div>
    <div class="stat-value"><?= $memberCount ?></div>
    <div class="stat-sub"><?= $elapsed ?> days tracked</div>
  </div>
</div>

<div class="grid-2 mb-6">
  <!-- Monthly Chart -->
  <div class="card">
    <div class="section-title mb-4"><?= uiIcon('chart') ?> Monthly Collections (2026)</div>
    <div id="chart-container" style="position:relative;height:240px">
      <?php
      $maxVal = max(1, max(array_column($monthlyData, 'expected')));
      ?>
      <div style="display:flex;align-items:flex-end;gap:6px;height:200px;padding-bottom:8px;border-bottom:1px solid var(--border2)">
        <?php foreach ($monthlyData as $mn => $md):
          $collectedH = $md['expected'] > 0 ? ($md['total']    / $maxVal * 100) : 0;
          $expectedH  = $md['expected'] > 0 ? ($md['expected'] / $maxVal * 100) : 0;
          $isCurrentMonth = ($mn == (int)$today->format('n') && 2026 == (int)$today->format('Y'));
        ?>
        <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:2px;position:relative">
          <div style="width:100%;display:flex;gap:2px;align-items:flex-end;height:160px">
            <div style="flex:1;background:rgba(226,192,125,.15);border-radius:4px 4px 0 0;height:<?= $expectedH ?>%;max-height:100%;border:1px solid var(--border)"></div>
            <div style="flex:1;background:linear-gradient(180deg,var(--gold),var(--gold2));border-radius:4px 4px 0 0;height:<?= $collectedH ?>%;max-height:100%;animation:grow .6s ease <?= $mn*0.05 ?>s both"
                 title="<?= $md['month'] ?>: Collected ₱<?= number_format($md['total'],2) ?>"></div>
          </div>
          <span style="font-size:9px;color:var(--text2);margin-top:4px;<?= $isCurrentMonth?'color:var(--gold);font-weight:700':'' ?>"><?= $md['month'] ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <div style="display:flex;gap:16px;margin-top:12px;font-size:11px">
        <span><span style="display:inline-block;width:12px;height:12px;background:rgba(226,192,125,.15);border:1px solid var(--border);border-radius:2px;vertical-align:middle"></span> Expected</span>
        <span><span style="display:inline-block;width:12px;height:12px;background:linear-gradient(180deg,var(--gold),var(--gold2));border-radius:2px;vertical-align:middle"></span> Collected</span>
      </div>
    </div>
  </div>

  <!-- Members Arrears -->
  <div class="card">
    <div class="section-title mb-4"><?= uiIcon('alert') ?> Highest Arrears</div>
    <?php foreach ($mostArrears as $i => $ma): ?>
    <div style="padding:10px 0;border-bottom:1px solid var(--border2)">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
        <div style="display:flex;align-items:center;gap:8px">
          <span style="font-size:14px"><?= ['🔴','🟠','🟡','🟢','🔵'][$i] ?></span>
          <span class="fw-600"><?= htmlspecialchars($ma['name']) ?></span>
        </div>
        <span class="text-danger fw-600">₱<?= number_format($ma['arrears'], 2) ?></span>
      </div>
      <div style="font-size:11px;color:var(--text2)"><?= $ma['unpaid_days'] ?> unpaid days</div>
      <div class="progress-wrap" style="margin-top:6px"><div class="progress-bar" style="background:rgba(231,76,106,.6);width:<?= min(100, ($ma['arrears'] / max(1, $mostArrears[0]['arrears'])) * 100) ?>%"></div></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Full Member Report -->
<div class="card mb-6">
  <div class="section-header mb-4">
    <span class="section-title"><?= uiIcon('trophy') ?> Full Member Report (All Time)</span>
    <span class="chip"><?= $elapsed ?> days tracked</span>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Rank</th>
          <th>Member</th>
          <th>Days Paid</th>
          <th>Total Paid</th>
          <th>Unpaid Days</th>
          <th>Balance Due</th>
          <th>Advance</th>
          <th>Rate</th>
          <th>Standing</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($topMembers as $i => $m):
          $unpaid  = max(0, $elapsed - (int)$m['days_paid']);
          $balance = $unpaid * 5;
          $rate    = $elapsed > 0 ? round(($m['days_paid'] / $elapsed) * 100, 1) : 0;
        ?>
        <tr>
          <td>
            <span class="text-muted"><?= $i + 1 ?></span>
          </td>
          <td>
            <div style="display:flex;align-items:center;gap:8px">
              <div class="avatar-sm" style="font-size:11px"><?= strtoupper(substr($m['name'],0,1)) ?></div>
              <span class="fw-600"><?= htmlspecialchars($m['name']) ?></span>
            </div>
          </td>
          <td class="text-success fw-600"><?= $m['days_paid'] ?></td>
          <td class="fw-600">₱<?= number_format($m['total_paid'], 2) ?></td>
          <td class="<?= $unpaid>0?'text-danger':'text-success' ?>"><?= $unpaid ?></td>
          <td><?= $balance>0 ? "<span class='text-danger fw-600'>₱".number_format($balance,2)."</span>" : '<span class="text-success">' . uiIcon('check') . '</span>' ?></td>
          <td>
            <?php if ((int)$m['advance_days'] > 0): ?>
              <span class="badge badge-info"><?= (int)$m['advance_days'] ?> days</span>
              <div class="text-muted" style="font-size:10px;margin-top:3px">through <?= date('M d, Y', strtotime($m['advance_until'])) ?></div>
            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
          </td>
          <td>
            <div style="display:flex;align-items:center;gap:6px">
              <div class="progress-wrap" style="width:60px"><div class="progress-bar <?= $rate>=100?'green':'' ?>" data-width="<?= $rate ?>"></div></div>
              <span style="font-size:12px;color:var(--text2)"><?= $rate ?>%</span>
            </div>
          </td>
          <td>
            <?php
            if ((int)$m['advance_days'] > 0) echo '<span class="badge badge-info">Paid Ahead</span>';
            elseif ($rate >= 100) echo '<span class="badge badge-success">Up to Date</span>';
            elseif ($rate >= 85) echo '<span class="badge badge-info">Good</span>';
            elseif ($rate >= 60) echo '<span class="badge badge-warning">Fair</span>';
            else echo '<span class="badge badge-danger">Behind</span>';
            ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Monthly Breakdown Table -->
<div class="card">
  <div class="section-title mb-4"><?= uiIcon('calendar') ?> Monthly Breakdown</div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Month</th>
          <th>Collected</th>
          <th>Expected</th>
          <th>Deficit</th>
          <th>Rate</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($monthlyData as $mn => $md):
          $deficit = max(0, $md['expected'] - $md['total']);
          $rate2   = $md['expected'] > 0 ? round(($md['total'] / $md['expected']) * 100, 1) : ($md['expected'] == 0 ? null : 0);
          $isFuture = (new DateTime("2026-$mn-01")) > $today;
        ?>
        <tr style="<?= $isFuture ? 'opacity:.5' : '' ?>">
          <td class="fw-600"><?= $md['month'] ?> 2026 <?= $mn == (int)$today->format('n') ? '<span class="badge badge-gold" style="font-size:10px">Current</span>' : '' ?></td>
          <td class="text-success fw-600">₱<?= number_format($md['total'], 2) ?></td>
          <td class="text-muted">₱<?= number_format($md['expected'], 2) ?></td>
          <td><?= $deficit > 0 ? "<span class='text-danger'>₱".number_format($deficit,2)."</span>" : ($isFuture ? '—' : '<span class="text-success">' . uiIcon('check') . '</span>') ?></td>
          <td>
            <?php if ($rate2 !== null && !$isFuture): ?>
            <div style="display:flex;align-items:center;gap:6px">
              <div class="progress-wrap" style="width:70px"><div class="progress-bar <?= $rate2>=100?'green':'' ?>" data-width="<?= min(100,$rate2) ?>"></div></div>
              <span style="font-size:12px"><?= $rate2 ?>%</span>
            </div>
            <?php else: echo '<span class="text-muted">—</span>'; endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<style>
@keyframes grow { from { height: 0; } }
</style>

<script>
document.querySelectorAll('.progress-bar[data-width]').forEach(bar => {
  const w = parseFloat(bar.dataset.width);
  setTimeout(() => bar.style.width = Math.min(100,w) + '%', 300);
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
